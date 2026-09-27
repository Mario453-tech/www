<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/AdminAuth.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AdminSessionIsolationTest extends TestCase
{
    public function testAdminTimeoutKeepsPlayerSessionAndCsrf(): void
    {
        $_SESSION = [
            'admin_logged_in' => true, 'admin_id' => 7, 'admin_user' => 'test',
            'admin_last_active' => time() - 7201, 'logged_in' => true,
            'user_id' => 15, 'last_active' => time(), 'csrf_token' => 'test-token',
            'locale' => 'en',
        ];
        self::assertFalse(AdminAuth::isLoggedIn());
        self::assertSame(15, $_SESSION['user_id'] ?? null);
        self::assertSame('test-token', $_SESSION['csrf_token'] ?? null);
        self::assertSame('en', $_SESSION['locale'] ?? null);
        self::assertArrayNotHasKey('admin_logged_in', $_SESSION);
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function testActiveAdminSessionRemainsAuthenticated(): void
    {
        $_SESSION = ['admin_logged_in' => true, 'admin_id' => 7, 'admin_last_active' => time()];
        self::assertTrue(AdminAuth::isLoggedIn());
    }

    public function testLogoutRouteRequiresPostAndCsrfBeforeChangingAuthentication(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/admin/logout.php');
        $logout = strpos($source, 'AdminAuth::logout()');
        self::assertLessThan($logout, strpos($source, "\$_SERVER['REQUEST_METHOD'] !== 'POST'"));
        self::assertLessThan($logout, strpos($source, 'CSRF::validateToken('));
        self::assertStringContainsString('http_response_code(403)', $source);
        self::assertStringContainsString('http_response_code(405)', $source);
        $login = file_get_contents(dirname(__DIR__, 2) . '/admin/login.php');
        self::assertStringContainsString("!isset(\$_GET['logged_out']) && AdminAuth::trySSO()", $login);
    }
}
