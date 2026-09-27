<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/AdminAuth.php';
require_once dirname(__DIR__, 2) . '/src/ChatMessageHtml.php';
require_once dirname(__DIR__, 2) . '/src/AdminChatBroadcast.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AdminChatSessionMySqlTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $dsn = getenv('OIL_ADMIN_TEST_DSN') ?: getenv('API_TEST_MYSQL_DSN');
        if (!$dsn) {
            self::markTestSkipped('Set OIL_ADMIN_TEST_DSN to an isolated MySQL test database.');
        }
        $this->db = new PDO($dsn, getenv('OIL_ADMIN_TEST_USER') ?: (getenv('API_TEST_DB_USER') ?: 'root'), getenv('OIL_ADMIN_TEST_PASS') ?: (getenv('API_TEST_DB_PASS') ?: ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
        $this->db->exec('CREATE TEMPORARY TABLE admins (id INT PRIMARY KEY, username VARCHAR(50), email VARCHAR(100), is_active INT, lock_until DATETIME NULL, last_login_at DATETIME NULL, last_login_ip VARCHAR(45))');
        $this->db->exec("INSERT INTO admins (id,username,email,is_active) VALUES (1,'test','test@example.test',1)");
        $this->db->exec('CREATE TEMPORARY TABLE admin_trusted_devices (id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT, token_hash CHAR(64) UNIQUE, expires_at DATETIME, created_ip VARCHAR(45), last_used_at DATETIME NULL)');
        $this->db->exec('CREATE TEMPORARY TABLE chat_messages (id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NULL, username VARCHAR(64), message VARCHAR(500), channel VARCHAR(20), is_admin INT)');
        $ref = new ReflectionClass(Database::class);
        $database = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('pdo')->setValue($database, $this->db);
        $ref->getProperty('instance')->setValue(null, $database);
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testBroadcastPersistsFormattingAndRejectsEmptyOrOversizeContent(): void
    {
        self::assertTrue(AdminChatBroadcast::send($this->db, '<p>Hello <strong onclick="bad()">players</strong></p>'));
        self::assertFalse(AdminChatBroadcast::send($this->db, '<p>&nbsp;</p>'));
        self::assertFalse(AdminChatBroadcast::send($this->db, '<script>bad()</script>'));
        self::assertFalse(AdminChatBroadcast::send($this->db, str_repeat('ą', 501)));
        self::assertFalse(AdminChatBroadcast::send($this->db, '<p>' . str_repeat('ą', 500) . '</p>'));
        self::assertTrue(AdminChatBroadcast::send($this->db, '<p>' . str_repeat('ą', 493) . '</p>'));
        self::assertTrue(AdminChatBroadcast::send($this->db, str_repeat('ą', 500)));
        self::assertSame(3, (int)$this->db->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn());
        $row = $this->db->query('SELECT * FROM chat_messages ORDER BY id LIMIT 1')->fetch();
        self::assertSame('<p>Hello <strong>players</strong></p>', $row['message']);
        self::assertNull($row['sender_id']);
        self::assertSame(1, (int)$row['is_admin']);
    }

    public function testRememberDeviceRestoresWithoutSessionAndIsRevokedOnLogout(): void
    {
        self::assertTrue(AdminAuth::setTrustedDevice(1));
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM admin_trusted_devices')->fetchColumn());
        $token = str_repeat('a', 64);
        $this->db->prepare('UPDATE admin_trusted_devices SET token_hash=?')->execute([hash('sha256', $token)]);
        $_COOKIE['admin_td'] = $token;
        $_SESSION = [];
        self::assertTrue(AdminAuth::tryTrustedDevice());
        self::assertTrue(AdminAuth::isLoggedIn());
        self::assertSame(1, AdminAuth::getAdminId());
        AdminAuth::logout(false);
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM admin_trusted_devices')->fetchColumn());
        $_COOKIE['admin_td'] = $token;
        self::assertFalse(AdminAuth::tryTrustedDevice());
    }

    public function testExpiredInactiveLockedAndInvalidDevicesCannotLogIn(): void
    {
        $token = str_repeat('b', 64);
        $_COOKIE['admin_td'] = $token;
        self::assertFalse(AdminAuth::tryTrustedDevice());
        $this->db->prepare("INSERT INTO admin_trusted_devices (admin_id,token_hash,expires_at,created_ip) VALUES (1,?,DATE_SUB(NOW(), INTERVAL 1 DAY),'')")
            ->execute([hash('sha256', $token)]);
        self::assertFalse(AdminAuth::tryTrustedDevice());
        $this->db->exec('UPDATE admin_trusted_devices SET expires_at=DATE_ADD(NOW(), INTERVAL 1 DAY)');
        $this->db->exec('UPDATE admins SET is_active=0');
        self::assertFalse(AdminAuth::tryTrustedDevice());
        $this->db->exec('UPDATE admins SET is_active=1, lock_until=DATE_ADD(NOW(), INTERVAL 1 HOUR)');
        self::assertFalse(AdminAuth::tryTrustedDevice());
        self::assertFalse(AdminAuth::isLoggedIn());
    }
}
