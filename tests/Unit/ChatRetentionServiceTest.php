<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/ChatRetentionService.php';

final class RetentionConfigStatement extends PDOStatement
{
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return ['chat_auto_clear_enabled' => '1', 'chat_auto_clear_interval' => '30'];
    }
}

final class RetentionFailurePDO extends PDO
{
    public array $writes = [];
    public string $cleanupQuery = '';

    public function __construct(private array $failure) {}

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->cleanupQuery = $query;
        $error = new PDOException('Database error');
        $error->errorInfo = $this->failure;
        throw $error;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return new RetentionConfigStatement();
    }

    public function exec(string $statement): int|false
    {
        $this->writes[] = $statement;
        return 1;
    }
}

final class ChatRetentionServiceTest extends TestCase
{
    public function testMissingLimiterTableDoesNotBlockMessageRetention(): void
    {
        $db = new RetentionFailurePDO(['42S02', 1146]);
        self::assertSame(1, (new ChatRetentionService($db))->cleanup(7));
        self::assertCount(2, $db->writes);
        self::assertStringContainsString('UPDATE chat_messages', $db->writes[0]);
        self::assertStringContainsString('LIMIT 7', $db->writes[0]);
        self::assertStringContainsString('LIMIT 7', $db->cleanupQuery);
    }

    public function testOtherDatabaseFailuresRemainVisible(): void
    {
        $db = new RetentionFailurePDO(['42000', 1142]);
        try {
            (new ChatRetentionService($db))->cleanup();
            self::fail('Permission failure was ignored');
        } catch (PDOException $e) {
            self::assertSame(1142, $e->errorInfo[1]);
        }
        self::assertSame([], $db->writes);
    }
}
