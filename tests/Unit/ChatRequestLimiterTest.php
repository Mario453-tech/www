<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/ChatRequestLimiter.php';
require_once dirname(__DIR__, 2) . '/src/ChatRequestPolicy.php';

final class ChatRequestLimiterTest extends TestCase
{
    public function testMissingTableRollsBackAndDoesNotBypassProtection(): void
    {
        $exception = new PDOException('Missing limiter table');
        $exception->errorInfo = ['42S02', 1146, 'Missing limiter table'];
        $db = $this->createMock(PDO::class);
        $db->expects(self::once())->method('beginTransaction')->willReturn(true);
        $db->expects(self::once())->method('prepare')->willThrowException($exception);
        $db->expects(self::once())->method('inTransaction')->willReturn(true);
        $db->expects(self::once())->method('rollBack')->willReturn(true);
        $db->expects(self::never())->method('commit');
        $db->expects(self::never())->method('exec');

        try {
            (new ChatRequestLimiter($db))->consume('heartbeat:test', 120, 60);
            self::fail('Missing limiter must block the mutation.');
        } catch (PDOException $caught) {
            self::assertSame($exception, $caught);
            self::assertSame(503, ChatRequestPolicy::error($caught)['status']);
        }
    }
}
