<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/AdminBankAdjustment.php';
require_once dirname(__DIR__, 2) . '/src/DirectorNotificationService.php';

final class AdminDashboardMySqlTest extends TestCase
{
    public function testAtomicAuditAndLocaleIndependentNotificationStorage(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        if (!in_array($cfg['host'], ['localhost', '127.0.0.1'], true)) {
            self::markTestSkipped('Local MySQL only');
        }
        $db = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4', $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        // Temporary tables isolate all writes from real game data.
        // Tabele tymczasowe izoluja wszystkie zapisy od danych gry.
        $db->exec('CREATE TEMPORARY TABLE players (id INT PRIMARY KEY, cash DECIMAL(16,2), bank_balance DECIMAL(16,2)) ENGINE=InnoDB');
        $db->exec('INSERT INTO players VALUES (1, 1000, 1000)');
        $db->exec('CREATE TEMPORARY TABLE bank_transactions (id INT AUTO_INCREMENT PRIMARY KEY, from_player_id INT NULL, to_player_id INT NULL, amount DECIMAL(16,2), transaction_type VARCHAR(60), description TEXT, reference_type VARCHAR(60) NULL, reference_id INT NULL) ENGINE=InnoDB');
        $db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");
        $db->exec("CREATE TEMPORARY TABLE admin_logs (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(64) NOT NULL, description TEXT, target_player_id INT UNSIGNED,
            target_type ENUM('player','well','market','system') NOT NULL DEFAULT 'player',
            target_id INT UNSIGNED, admin_id INT UNSIGNED, admin_user VARCHAR(64) NOT NULL DEFAULT 'admin',
            admin_ip VARCHAR(45), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $db->beginTransaction();
        $finance = new FinancialTransactionService($db);
        $db->rollBack();
        $adjustment = new AdminBankAdjustment($db, $finance);
        foreach (['admin_credit', 'admin_debit'] as $action) {
            $result = $adjustment->adjust(1, $action, '12.34', 'Correction', 'reviewer', '127.0.0.1');
            self::assertTrue($result['success']);
            $audit = $db->query('SELECT * FROM admin_logs ORDER BY id DESC LIMIT 1')->fetch();
            self::assertSame('reviewer', $audit['admin_user']);
            self::assertSame('player', $audit['target_type']);
            self::assertSame(1, (int)$audit['target_id']);
            self::assertSame(1, (int)$audit['target_player_id']);
            self::assertStringContainsString('transaction_id=' . $result['transaction_id'] . ';', $audit['description']);
        }
        $balance = $db->query('SELECT cash + bank_balance FROM players WHERE id=1')->fetchColumn();
        $db->exec('ALTER TABLE admin_logs ADD COLUMN required_audit_field INT NOT NULL');
        $db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES'");
        try {
            $adjustment->adjust(1, 'admin_debit', '20', 'Correction', 'reviewer', '127.0.0.1');
            self::fail('Audit insert must fail');
        } catch (PDOException $e) {
            self::assertSame($balance, $db->query('SELECT cash + bank_balance FROM players WHERE id=1')->fetchColumn());
            self::assertSame(2, (int)$db->query('SELECT COUNT(*) FROM bank_transactions')->fetchColumn());
        }
        $db->exec("CREATE TEMPORARY TABLE director_notifications (id INT AUTO_INCREMENT PRIMARY KEY, player_id INT, type VARCHAR(40), priority VARCHAR(40), title VARCHAR(255), message TEXT, icon VARCHAR(40), requires_action BOOLEAN, action_url VARCHAR(255) NULL, action_label VARCHAR(255) NULL, expires_at DATETIME NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, is_read BOOLEAN DEFAULT FALSE, read_at DATETIME NULL, title_key VARCHAR(190) NULL, message_key VARCHAR(190) NULL, action_label_key VARCHAR(190) NULL, message_params JSON NULL) ENGINE=InnoDB");
        $db->exec('CREATE TEMPORARY TABLE notification_history (notification_id INT, player_id INT, action VARCHAR(40)) ENGINE=InnoDB');
        $service = (new ReflectionClass(DirectorNotificationService::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(DirectorNotificationService::class, 'db'))->setValue($service, $db);
        $service->ensureSchema();
        $previous = $_SESSION['locale'] ?? 'pl';
        try {
            $_SESSION['locale'] = 'pl';
            $id = $service->create(1, 'admin_adjustment', ['direction' => 'credit', 'amount' => '12.34', 'note' => 'A & B']);
            $stored = $db->query('SELECT * FROM director_notifications')->fetch();
            self::assertSame('', $stored['title']);
            self::assertSame('', $stored['message']);
            self::assertSame('director.admin_adjustment.message', $stored['message_key']);
            self::assertSame('credit', json_decode($stored['message_params'], true)['direction']);
            $_SESSION['locale'] = 'en';
            self::assertStringContainsString('credited', $service->getUnread(1)[0]['message']);
            self::assertFalse($service->markAsRead($id, 2));
            self::assertCount(1, $service->getUnread(1));
            self::assertTrue($service->markAsRead($id, 1));
            self::assertCount(0, $service->getUnread(1));
        } finally {
            $_SESSION['locale'] = $previous;
        }
    }
}
