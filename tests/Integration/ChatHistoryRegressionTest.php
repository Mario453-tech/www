<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/ChatService.php';

final class ChatHistoryRegressionTest extends TestCase
{
    private PDO $db;
    private ChatService $chat;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->db->exec("CREATE TABLE players (id INTEGER PRIMARY KEY, company_name TEXT, username TEXT, avatar_path TEXT);
            CREATE TABLE chat_rooms (id INTEGER PRIMARY KEY, status TEXT);
            INSERT INTO players VALUES (1, 'One', 'one', NULL), (2, 'Two', 'two', NULL), (3, 'Other', 'other', NULL);
            INSERT INTO chat_rooms VALUES (1, 'active'), (2, 'archived');
            CREATE TABLE chat_messages (id INTEGER PRIMARY KEY, room_id INTEGER, room_slug TEXT,
                sender_id INTEGER, receiver_id INTEGER, channel TEXT, message TEXT, is_admin INTEGER DEFAULT 0,
                is_deleted INTEGER DEFAULT 0, is_pinned INTEGER DEFAULT 0, pinned_at TEXT,
                attachment_path TEXT, attachment_name TEXT, attachment_type TEXT, created_at TEXT);");
        $insert = $this->db->prepare("INSERT INTO chat_messages
            (id, room_id, room_slug, sender_id, receiver_id, channel, message, created_at)
            VALUES (?, ?, 'polski', 2, 1, ?, 'Message', '2026-10-06 10:00:00')");
        for ($i = 1; $i <= 121; $i++) {
            $insert->execute([$i, 1, 'global']);
            $insert->execute([$i + 200, null, 'private']);
        }
        $insert->execute([500, 2, 'global']);
        $reflection = new ReflectionClass(ChatService::class);
        $this->chat = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($this->chat, $this->db);
    }

    public function testIncrementalRoomBacklogDoesNotSkipMessages(): void
    {
        $first = $this->chat->getRoomMessages(1, 1, 50);
        self::assertSame(range(2, 51), array_column($first, 'id'));
        self::assertSame(range(52, 101), array_column($this->chat->getRoomMessages(1, 51, 50), 'id'));
    }

    public function testDirectBacklogAndOwnership(): void
    {
        self::assertSame(range(202, 251), array_column($this->chat->getDirectMessages(1, 2, 201, 50), 'id'));
        self::assertSame([], $this->chat->getDirectMessages(3, 2));
    }

    public function testOlderHistoryIsChronological(): void
    {
        self::assertSame(range(21, 70), array_column($this->chat->getRoomMessages(1, 0, 50, 71), 'id'));
        self::assertSame(range(221, 270), array_column($this->chat->getDirectMessages(1, 2, 0, 50, 271), 'id'));
    }

    public function testArchivedRoomIsNotReadable(): void
    {
        self::assertSame([], $this->chat->getRoomMessages(2));
    }

    public function testReadCursorCannotReferenceAnotherConversation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->chat->markAsRead(3, 'direct', 2, 201);
    }
}
