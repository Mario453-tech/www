<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SessionConfigurationContractTest extends TestCase
{
    public function testInitKeepsCookieAndGarbageCollectionAlignedWithSessionTtl(): void
    {
        $init = (string)file_get_contents(dirname(__DIR__, 2) . '/src/init.php');

        self::assertStringContainsString('$sessionTtl = 7200;', $init);
        self::assertStringContainsString('ini_set(\'session.gc_maxlifetime\', (string)$sessionTtl)', $init);
        self::assertStringContainsString('ini_set(\'session.use_strict_mode\', \'1\')', $init);
        self::assertStringContainsString('session_set_cookie_params([', $init);
        self::assertStringContainsString('\'lifetime\' => $sessionTtl', $init);
        self::assertStringContainsString('\'secure\' => Security::isHttpsRequest()', $init);
        self::assertStringContainsString('\'samesite\' => \'Lax\'', $init);
    }
}
