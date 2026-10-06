<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/ChatService.php';
require_once dirname(__DIR__, 2) . '/src/ChatRetentionService.php';
require_once dirname(__DIR__, 2) . '/src/ChatRequestLimiter.php';

// Migrate connection-local fixtures only. / Migruj tylko dane tymczasowe polaczenia.
final class ChatTemporaryPDO extends PDO
{
    public function exec(string $statement): int|false
    {
        return parent::exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE IF NOT EXISTS', $statement));
    }
}

final class MySqlChatComplianceTest extends TestCase
{
    private PDO $db;
    private ChatService $chat;

    protected function setUp(): void
    {
        $dsn = getenv('API_TEST_MYSQL_DSN');
        if (!$dsn) self::markTestSkipped('Set API_TEST_MYSQL_DSN to a test database.');
        $this->db = new ChatTemporaryPDO($dsn, getenv('API_TEST_DB_USER') ?: 'root', getenv('API_TEST_DB_PASS') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        $this->db->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
        $this->db->exec("CREATE TEMPORARY TABLE players (id INT PRIMARY KEY, username VARCHAR(64), company_name VARCHAR(100), avatar_path VARCHAR(255), status VARCHAR(20));");
        $this->db->exec("INSERT INTO players VALUES (1, 'one', 'One', NULL, 'active'), (2, 'two', 'Two', NULL, 'active'), (3, 'three', 'Three', NULL, 'active')");
        $this->db->exec("CREATE TEMPORARY TABLE chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NULL, receiver_id INT NULL,
            channel ENUM('global','private') NOT NULL DEFAULT 'global', username VARCHAR(64) NOT NULL,
            message VARCHAR(500) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        $this->db->exec('CREATE TEMPORARY TABLE chat_bans (player_id INT PRIMARY KEY, reason VARCHAR(255), expires_at DATETIME NULL)');
        $this->db->exec('CREATE TEMPORARY TABLE nav_items (label VARCHAR(100), url_key VARCHAR(100), location VARCHAR(100), sort_order INT, active INT, lang_key VARCHAR(100), css_class VARCHAR(100))');
        ensureChatSchema($this->db);
        $this->chat = new ChatService($this->db);
    }

    public function testStrictLegacySchemaAcceptsRoomAndDirectMessages(): void
    {
        $message = str_repeat('ą', 1000);
        self::assertSame($message, $this->chat->sendRoomMessage(1, 2, $message)['message']);
        self::assertSame($message, $this->chat->sendDirectMessage(1, 2, $message)['message']);
        self::assertSame(['one', 'one'], $this->db->query('SELECT username FROM chat_messages ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM chat_read_states')->fetchColumn());
    }

    public function testMigrationRetryPreservesAdministratorChanges(): void
    {
        $this->db->exec("UPDATE chat_room_translations SET name = 'Edited' WHERE room_id = 1 AND locale = 'pl'");
        $this->db->exec("UPDATE chat_rooms SET sort_order = 77, status = 'read_only' WHERE slug = 'polski'");
        ensureChatSchema($this->db);
        $room = $this->chat->getRoomBySlug('polski');
        self::assertSame('Edited', $room['name']);
        self::assertSame(77, $room['sort_order']);
        self::assertSame('read_only', $room['status']);
        self::assertSame(3, (int) $this->db->query('SELECT COUNT(*) FROM chat_rooms')->fetchColumn());
    }

    public function testPresenceCountIsIndependentOfListLimit(): void
    {
        $this->chat->updatePresence(1, 'polski');
        $this->chat->updatePresence(2, 'english');
        $presence = $this->chat->getActivePlayers(1, 1, 'en');
        self::assertSame(2, $presence['total_online']);
        self::assertCount(1, $presence['players']);
    }

    public function testReadStateRequiresActualConversationMessage(): void
    {
        $sent = $this->chat->sendDirectMessage(1, 2, 'Hello');
        $this->chat->markAsRead(2, 'direct', 1, $sent['id']);
        self::assertSame($sent['id'], (int) $this->db->query('SELECT last_read_message_id FROM chat_conversation_reads')->fetchColumn());
        $this->expectException(InvalidArgumentException::class);
        $this->chat->markAsRead(3, 'direct', 1, $sent['id']);
    }

    public function testReadLedgersRollbackTogether(): void
    {
        $sent = $this->chat->sendDirectMessage(1, 2, 'Atomic read');
        $this->db->exec('ALTER TABLE chat_conversation_reads ADD required_marker INT NOT NULL');
        try {
            $this->chat->markAsRead(2, 'direct', 1, $sent['id']);
            self::fail('Legacy ledger failure was ignored');
        } catch (PDOException $e) {
            self::assertFalse($this->db->inTransaction());
        }
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM chat_read_states')->fetchColumn());
    }

    public function testReadOnlyRejectionRollsBackTransaction(): void
    {
        $this->db->exec("UPDATE chat_rooms SET status='read_only' WHERE id=1");
        try {
            $this->chat->sendRoomMessage(1, 1, 'Rejected');
            self::fail('Read-only write succeeded');
        } catch (RuntimeException $e) {
            self::assertSame('chat.err_room_read_only', $e->getMessage());
        }
        self::assertFalse($this->db->inTransaction());
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn());
    }

    public function testRateLimitCountsSuccessfulWritesAcrossChannels(): void
    {
        for ($i = 0; $i < 5; $i++) $this->chat->sendRoomMessage(1, 1, 'Message');
        $this->expectExceptionMessage('chat.err_rate_limited');
        $this->chat->sendDirectMessage(1, 2, 'Sixth');
    }

    public function testRequestLimiterSharesBucketAndReturnsRetryTime(): void
    {
        $limiter = new ChatRequestLimiter($this->db);
        self::assertSame(0, $limiter->consume('send:ip', 2, 60));
        self::assertSame(0, $limiter->consume('send:ip', 2, 60));
        self::assertGreaterThan(0, $limiter->consume('send:ip', 2, 60));
        self::assertFalse($this->db->inTransaction());
        self::assertSame(0, $limiter->consume('presence:ip', 2, 60));
    }

    public function testRetentionPreservesDmPinnedMessagesAndMonotonicIds(): void
    {
        $this->db->exec('CREATE TEMPORARY TABLE well_config (`key` VARCHAR(100) PRIMARY KEY, `value` VARCHAR(255))');
        $this->db->exec("INSERT INTO well_config VALUES ('chat_auto_clear_enabled','1'), ('chat_auto_clear_interval','30')");
        $room = $this->chat->sendRoomMessage(1, 1, 'Old public');
        $direct = $this->chat->sendDirectMessage(1, 2, 'Old private');
        $pinned = $this->chat->sendRoomMessage(1, 1, 'Pinned');
        $this->db->exec('UPDATE chat_messages SET created_at = DATE_SUB(NOW(), INTERVAL 2 HOUR)');
        $this->db->prepare('UPDATE chat_messages SET is_pinned = 1 WHERE id = ?')->execute([$pinned['id']]);
        self::assertSame(1, (new ChatRetentionService($this->db))->cleanup(1));
        self::assertSame(0, (new ChatRetentionService($this->db))->cleanup());
        self::assertCount(1, $this->chat->getRoomMessages(1));
        self::assertSame([$direct['id']], array_column($this->chat->getDirectMessages(1, 2), 'id'));
        self::assertGreaterThan($pinned['id'], $this->chat->sendRoomMessage(1, 1, 'New')['id']);
    }

    public function testAuditFailureCanRollBackModeration(): void
    {
        $message = $this->chat->sendRoomMessage(1, 1, 'Keep until audited');
        $this->db->exec('ALTER TABLE chat_moderation_actions MODIFY action VARCHAR(1) NOT NULL');
        $this->db->beginTransaction();
        try {
            $this->chat->hideMessage(1, $message['id'], 'Test');
            self::fail('Audit failure was ignored');
        } catch (PDOException $e) {
            $this->db->rollBack();
        }
        self::assertSame([$message['id']], array_column($this->chat->getRoomMessages(1), 'id'));
    }

    public function testRoomUpdateSavesLanguageAndTypeAndAcceptsUnchangedValues(): void
    {
        $translations = [
            ['locale' => 'pl', 'name' => 'PL', 'description' => ''],
            ['locale' => 'en', 'name' => 'EN', 'description' => ''],
            ['locale' => 'ja', 'name' => '日本語', 'description' => '日本語の部屋'],
        ];
        $this->db->beginTransaction();
        for ($i = 0; $i < 2; $i++) {
            $this->chat->updateRoom(1, 1, $translations, 5, 'active', 'custom', 'ja');
        }
        $this->db->commit();
        $room = $this->chat->getRoomBySlug('polski', 'ja');
        self::assertSame('ja', $room['locale_code']);
        self::assertSame('custom', $room['type']);
        self::assertSame('日本語', $room['name']);
        self::assertSame('日本語の部屋', $room['description']);
        self::assertCount(3, $this->chat->getRoomTranslations([1])[1]);

        $this->chat->updateRoom(1, 1, $translations, 5, 'archived', 'custom', 'ja');
        self::assertNotFalse($this->db->query('SELECT archived_at FROM chat_rooms WHERE id = 1')->fetchColumn());
    }

    public function testRoomCanUseAnArbitraryLanguage(): void
    {
        $roomId = $this->chat->createRoom(1, 'nihongo', 'language', 'ja', [[
            'locale' => 'ja', 'name' => '日本語', 'description' => '日本語の部屋',
        ]], 40);
        self::assertGreaterThan(0, $roomId);
        self::assertSame('日本語', $this->chat->getRoomBySlug('nihongo', 'ja')['name']);
        self::assertSame('日本語', $this->chat->getRoomBySlug('nihongo', 'fr')['name']);
        self::assertSame('ja', $this->chat->getRoomTranslations([$roomId])[$roomId][0]['locale']);
    }

    public function testNonexistentRoomCannotProduceSuccessfulUpdate(): void
    {
        $this->expectExceptionMessage('chat.err_room_not_found');
        $this->chat->updateRoom(1, 999, [['locale' => 'ja', 'name' => '日本語']], 0, 'active');
    }
}
