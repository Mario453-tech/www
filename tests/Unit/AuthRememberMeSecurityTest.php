<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthRememberMeSecurityTest extends TestCase
{
    public function testRememberMeOnlyRestoresActiveVerifiedPlayers(): void
    {
        $root = dirname(__DIR__, 2);
        $auth = (string) file_get_contents($root . '/src/Auth.php');
        $methodStart = strpos($auth, 'public static function tryRememberMe(): bool');
        $methodEnd = strpos($auth, 'public static function clearRememberMe(): void');

        self::assertNotFalse($methodStart);
        self::assertNotFalse($methodEnd);

        $method = substr($auth, $methodStart, $methodEnd - $methodStart);
        self::assertStringContainsString("p.status = 'active'", $method);
        self::assertStringContainsString('COALESCE(p.email_verified, 1)', $method);
    }
}
