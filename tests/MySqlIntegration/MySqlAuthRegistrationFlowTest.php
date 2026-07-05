<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/WalletConfig.php';
require_once dirname(__DIR__, 2) . '/src/WalletService.php';
require_once dirname(__DIR__, 2) . '/src/Auth.php';

final class MySqlAuthRegistrationFlowTest extends MySqlIntegrationTestCase
{
    /** @var list<string> */
    private array $emailsToCleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->emailsToCleanup as $email) {
            try {
                $playerId = $this->findPlayerIdByEmail($email);
                if ($playerId !== null) {
                    $this->db->prepare('DELETE FROM email_verifications WHERE player_id = ?')->execute([$playerId]);
                    $this->db->prepare('DELETE FROM storage WHERE player_id = ?')->execute([$playerId]);
                    $this->db->prepare('DELETE FROM players WHERE id = ?')->execute([$playerId]);
                }
            } catch (Throwable $e) {
            }
        }

        $this->emailsToCleanup = [];
        parent::tearDown();
    }

    public function testLegacyRegisterUsesSharedWalletAndStorageBootstrap(): void
    {
        $username = 'phpunit_auth_' . $this->seed;
        $email = $username . '@example.test';
        $this->emailsToCleanup[] = $email;

        $result = Auth::register($username, $email, 'Secret123');

        $this->assertTrue($result['success']);
        $player = $this->fetchPlayerByEmail($email);
        $this->assertNotNull($player);
        $this->assertSame(1, (int)$player['email_verified']);
        $this->assertSame(1, (int)$player['wallet_initialized']);
        $this->assertEqualsWithDelta(5_000_000.00, (float)$player['cash'], 0.01);
        $this->assertEqualsWithDelta(5_000_000.00, (float)$player['bank_balance'], 0.01);

        $storageCap = $this->fetchStorageCapacity((int)$player['id']);
        $this->assertEqualsWithDelta(WalletConfig::NEW_PLAYER_STORAGE_CAPACITY, $storageCap, 0.01);
        $this->assertSame((int)$player['id'], (int)($_SESSION['user_id'] ?? 0), 'Legacy register should still create a login session.');
    }

    public function testPendingVerificationRegisterUsesSameBootstrapDefaults(): void
    {
        $email = 'phpunit_public_' . $this->seed . '@example.test';
        $this->emailsToCleanup[] = $email;

        $result = Auth::registerPendingVerification($email, 'Secret123', true);

        $this->assertTrue($result['success']);
        $player = $this->fetchPlayerByEmail($email);
        $this->assertNotNull($player);
        $this->assertSame(0, (int)$player['email_verified']);
        $this->assertSame(1, (int)$player['newsletter_subscribed']);
        $this->assertNotSame('', (string)($player['newsletter_token'] ?? ''));
        $this->assertSame(1, (int)$player['wallet_initialized']);
        $this->assertEqualsWithDelta(5_000_000.00, (float)$player['cash'], 0.01);
        $this->assertEqualsWithDelta(5_000_000.00, (float)$player['bank_balance'], 0.01);

        $storageCap = $this->fetchStorageCapacity((int)$player['id']);
        $this->assertEqualsWithDelta(WalletConfig::NEW_PLAYER_STORAGE_CAPACITY, $storageCap, 0.01);
    }

    private function findPlayerIdByEmail(string $email): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM players WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    /** @return array<string,mixed>|null */
    private function fetchPlayerByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, cash, bank_balance, wallet_initialized, email_verified, newsletter_subscribed, newsletter_token
               FROM players
              WHERE email = ?
              LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function fetchStorageCapacity(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT capacity FROM storage WHERE player_id = ? LIMIT 1');
        $stmt->execute([$playerId]);
        return (float)$stmt->fetchColumn();
    }
}
