<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/ChatService.php';

final class MySqlChatServiceLimitTest extends TestCase
{
    private PDO $db;
    private ChatService $service;

    protected function setUp(): void
    {
        $dsn = getenv('API_TEST_MYSQL_DSN');
        if (!$dsn) {
            self::markTestSkipped('Set API_TEST_MYSQL_DSN to an isolated MySQL test database.');
        }

        $this->db = new PDO(
            $dsn,
            getenv('API_TEST_DB_USER') ?: 'root',
            getenv('API_TEST_DB_PASS') ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ]
        );

        $this->db->exec('CREATE TEMPORARY TABLE players (
            id INT PRIMARY KEY,
            status VARCHAR(20) NOT NULL,
            company_name VARCHAR(100) NULL,
            username VARCHAR(100) NULL,
            avatar_path VARCHAR(255) NULL
        )');
        $this->db->exec('CREATE TEMPORARY TABLE chat_rooms (
            id INT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            name_pl VARCHAR(100) NULL,
            name_en VARCHAR(100) NULL,
            name_de VARCHAR(100) NULL
        )');
        $this->db->exec('CREATE TEMPORARY TABLE chat_presence (
            player_id INT PRIMARY KEY,
            current_room_slug VARCHAR(50) NULL,
            last_active_at DATETIME NOT NULL,
            is_online TINYINT NOT NULL
        )');
        $this->db->exec('CREATE TEMPORARY TABLE chat_room_translations (
            room_id INT NOT NULL, locale VARCHAR(20) NOT NULL, name VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL, PRIMARY KEY (room_id, locale)
        )');
        $this->db->exec('CREATE TEMPORARY TABLE chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NULL,
            room_slug VARCHAR(50) NULL,
            sender_id INT NULL,
            receiver_id INT NULL,
            message TEXT NOT NULL,
            channel VARCHAR(20) NOT NULL,
            is_deleted TINYINT NOT NULL DEFAULT 0,
            is_admin TINYINT NOT NULL DEFAULT 0,
            is_pinned TINYINT NOT NULL DEFAULT 0,
            pinned_at DATETIME NULL,
            attachment_path VARCHAR(255) NULL,
            attachment_name VARCHAR(255) NULL,
            attachment_type VARCHAR(50) NULL,
            created_at DATETIME NOT NULL
        )');

        $this->db->exec("INSERT INTO players (id, status, company_name, username) VALUES
            (1, 'active', 'Alpha', 'alpha'),
            (2, 'active', 'Beta', 'beta')");
        $this->db->exec("INSERT INTO chat_rooms (id, slug, name_pl, name_en, name_de)
            VALUES (1, 'polski', 'Polski', 'Polish', 'Polnisch')");
        $this->db->exec("INSERT INTO chat_room_translations VALUES (1, 'pl', 'Polski', NULL), (1, 'en', 'Polish', NULL)");
        $this->db->exec("INSERT INTO chat_presence (player_id, current_room_slug, last_active_at, is_online) VALUES
            (1, 'polski', NOW(), 1),
            (2, 'polski', NOW(), 1)");
        $this->db->exec("INSERT INTO chat_messages
            (room_id, room_slug, sender_id, receiver_id, message, channel, created_at) VALUES
            (1, 'polski', 1, NULL, 'Room one', 'global', NOW()),
            (1, 'polski', 2, NULL, 'Room two', 'global', NOW()),
            (NULL, NULL, 1, 2, 'Direct one', 'private', NOW()),
            (NULL, NULL, 2, 1, 'Direct two', 'private', NOW())");

        $reflection = new ReflectionClass(ChatService::class);
        $this->service = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($this->service, $this->db);
    }

    public function testAllChatLimitsWorkWithEmulatedPrepares(): void
    {
        self::assertCount(1, $this->service->getRoomMessages(1, 0, 1));
        self::assertCount(1, $this->service->getDirectMessages(1, 2, 0, 1));

        $active = $this->service->getActivePlayers(1, 1);
        self::assertSame(2, $active['total_online']);
        self::assertCount(1, $active['players']);
    }
}
