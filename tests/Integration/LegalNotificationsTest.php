<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';

/**
 * Brief §13: powiadomienia działu prawnego.
 * Brief §13: legal department notifications.
 *
 * Pokrywa nowe ścieżki:
 *  - submitApplication() → powiadomienie "Wniosek o zezwolenie złożony"
 *  - migrateTransitionalPermits() → powiadomienie "Zezwolenie przejściowe nadane"
 * oraz zabezpieczenie (guard): brak tabeli director_notifications nie przerywa
 * operacji nadrzędnej.
 */
final class LegalNotificationsTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private LegalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->service = new LegalService($this->db);
    }

    // --------------------------------------------------- Punkt 6: złożenie

    public function testSubmitApplicationCreatesSubmittedNotification(): void
    {
        $this->seedRegions();
        $this->seedConfig(1, ['application_cost' => 250000.0, 'base_review_minutes' => 30]);
        $this->seedPlayer(100, 1000000.0);

        $res = $this->service->submitApplication(100, 1);
        $this->assertTrue($res['success']);

        $rows = $this->notificationsOf(100);
        $this->assertCount(1, $rows, 'Powinno powstać dokładnie jedno powiadomienie o złożeniu.');

        $n = $rows[0];
        $this->assertSame('legal', $n['type']);
        $this->assertSame(0, (int)$n['requires_action'], 'Powiadomienie informacyjne — bez wymaganej akcji.');
        $this->assertSame('legal.php', $n['action_url']);
        $this->assertNotSame('', (string)$n['title']);
        // Treść zawiera nazwę regionu (interpolacja :region).
        $this->assertStringContainsString('Region Niski', (string)$n['message']);
    }

    public function testSubmitFailureDoesNotCreateNotification(): void
    {
        $this->seedRegions();
        $this->seedConfig(1, ['application_cost' => 250000.0]);
        $this->seedPlayer(100, 100000.0); // za mało na opłatę

        $res = $this->service->submitApplication(100, 1);
        $this->assertFalse($res['success']);
        $this->assertSame('insufficient_funds', $res['code']);

        $this->assertCount(0, $this->notificationsOf(100), 'Nieudane złożenie nie tworzy powiadomienia.');
    }

    public function testSubmitSucceedsEvenWhenNotificationsTableMissing(): void
    {
        // Guard: brak tabeli director_notifications nie może przerwać złożenia.
        $this->db->exec('DROP TABLE director_notifications');

        $this->seedRegions();
        $this->seedConfig(1, ['application_cost' => 250000.0]);
        $this->seedPlayer(100, 1000000.0);

        $res = $this->service->submitApplication(100, 1);
        $this->assertTrue($res['success'], 'Złożenie musi się udać mimo braku tabeli powiadomień.');
        $this->assertSame(750000.0, $this->cashOf(100)); // opłata pobrana normalnie
    }

    // ------------------------------------------------ Punkt 7: migracja

    public function testMigrateCreatesTransitionalNotification(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(200, 0.0);
        $this->insertWell(200, 1, 'active');

        $migrated = $this->service->migrateTransitionalPermits();
        $this->assertSame(1, $migrated);

        $rows = $this->notificationsOf(200);
        $this->assertCount(1, $rows);
        $this->assertSame('legal', $rows[0]['type']);
        $this->assertStringContainsString('Region Niski', (string)$rows[0]['message']);
        // Opis §12.2 — wyjaśnienie tymczasowego odblokowania.
        $this->assertStringContainsString('tymczasowo', (string)$rows[0]['message']);
    }

    public function testMigrateIdempotentDoesNotDuplicateNotifications(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(201, 0.0);
        $this->insertWell(201, 1, 'active');

        $this->assertSame(1, $this->service->migrateTransitionalPermits());
        // Drugi przebieg nic nie migruje → brak nowego powiadomienia.
        $this->assertSame(0, $this->service->migrateTransitionalPermits());

        $this->assertCount(1, $this->notificationsOf(201), 'Idempotentna migracja nie dubluje powiadomień.');
    }

    public function testMigrateCreatesNotificationPerMigratedRegion(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(210, 0.0);
        $this->insertWell(210, 1, 'active');
        $this->insertWell(210, 2, 'active');

        $this->assertSame(2, $this->service->migrateTransitionalPermits());
        $this->assertCount(2, $this->notificationsOf(210), 'Po jednym powiadomieniu na każdy zmigrowany region.');
    }

    // --------------------------------------------------------------- Helpers

    /** @return array<int,array<string,mixed>> */
    private function notificationsOf(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM director_notifications WHERE player_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash REAL DEFAULT 0)');
        $this->db->exec(
            'CREATE TABLE wells (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                region_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT \'active\'
            )'
        );
        $this->db->exec(
            'CREATE TABLE world_regions (
                id INTEGER PRIMARY KEY,
                code TEXT,
                name TEXT,
                political_risk INTEGER DEFAULT 1
            )'
        );
        $this->db->exec(
            "CREATE TABLE legal_region_config (
                region_id INTEGER PRIMARY KEY,
                enabled INTEGER DEFAULT 1,
                is_offshore INTEGER DEFAULT 0,
                risk_level TEXT DEFAULT 'low',
                application_cost REAL DEFAULT 100000,
                base_review_minutes INTEGER DEFAULT 60,
                delay_risk_pct REAL DEFAULT 0,
                delay_min_minutes INTEGER DEFAULT 10,
                delay_max_minutes INTEGER DEFAULT 30,
                no_decision_risk_pct REAL DEFAULT 0,
                refusal_risk_pct REAL DEFAULT 0,
                refusal_cooldown_minutes INTEGER DEFAULT 120,
                required_capital REAL DEFAULT 0,
                required_legal_level INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->db->exec(
            "CREATE TABLE drilling_permit_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                region_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                cost REAL DEFAULT 0,
                submitted_at TEXT NULL,
                decision_due_at TEXT NULL,
                decided_at TEXT NULL,
                refusal_cooldown_until TEXT NULL,
                delay_count INTEGER DEFAULT 0,
                source TEXT DEFAULT 'player',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->db->exec(
            "CREATE TABLE director_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                priority TEXT NOT NULL DEFAULT 'low',
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                icon TEXT NULL,
                requires_action INTEGER NOT NULL DEFAULT 0,
                action_url TEXT NULL,
                action_label TEXT NULL,
                expires_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    private function seedRegions(): void
    {
        $this->db->exec("INSERT INTO world_regions (id, code, name, political_risk) VALUES (1, 'low', 'Region Niski', 1)");
        $this->db->exec("INSERT INTO world_regions (id, code, name, political_risk) VALUES (2, 'med', 'Region Sredni', 2)");
    }

    /** @param array<string,mixed> $over */
    private function seedConfig(int $regionId, array $over = []): void
    {
        $cfg = array_merge([
            'enabled' => 1, 'risk_level' => 'low', 'application_cost' => 100000.0,
            'base_review_minutes' => 60, 'required_capital' => 0.0,
        ], $over);
        $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, enabled, risk_level, application_cost, base_review_minutes, required_capital)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $regionId, $cfg['enabled'], $cfg['risk_level'], $cfg['application_cost'],
            $cfg['base_review_minutes'], $cfg['required_capital'],
        ]);
    }

    private function seedPlayer(int $id, float $cash): void
    {
        $this->db->prepare("INSERT INTO players (id, cash) VALUES (?, ?)")->execute([$id, $cash]);
    }

    private function insertWell(int $playerId, int $regionId, string $status): void
    {
        $this->db->prepare(
            "INSERT INTO wells (player_id, region_id, status) VALUES (?, ?, ?)"
        )->execute([$playerId, $regionId, $status]);
    }

    private function cashOf(int $playerId): float
    {
        return (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();
    }
}
