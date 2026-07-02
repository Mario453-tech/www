<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubEconomyService.php';
require_once dirname(__DIR__, 2) . '/src/HubViewService.php';
require_once dirname(__DIR__, 2) . '/src/WellService.php';

/**
 * Regression tests for the wells/hubs/pipelines bugfix batch.
 * Testy regresyjne dla partii poprawek odwierty/huby/rurociagi.
 */
final class BugfixWellsHubsPipelinesTest extends SqliteIntegrationTestCase
{
    // B1: getHubDetail nie moze zwrocic danych huba osobie, ktora nie jest wlascicielem ani najemca.
    // B1: getHubDetail must not return hub data to a non-owner / non-tenant player.
    public function testGetHubDetailDeniesNonOwner(): void
    {
        $db = $this->createSqlitePdo();
        $econSvc = $this->createMock(HubEconomyService::class);
        $hubSvc  = $this->createMock(HubService::class);

        // Hub nalezy do gracza 2, wynajmuje gracz 3.
        // Hub owned by player 2, rented by player 3.
        $hubSvc->method('getHub')->willReturn([
            'id'               => 55,
            'player_id'        => 2,
            'tenant_player_id' => 3,
            'status'           => 'active',
            'condition_pct'    => 80.0,
            'assigned_count'   => 0,
        ]);
        // Sub-metody wywolywane dopiero PO bramce wlasnosci — stubujemy bezpiecznie.
        // Sub-methods are reached only AFTER the ownership gate — stub them safely.
        $viewSvc = new HubViewService($db, $hubSvc, $econSvc);

        // Gracz 1 (obcy, ani wlasciciel ani najemca) — brak dostepu (403), bramka zwraca null
        // ZANIM dotknie jakichkolwiek danych huba.
        // Player 1 (neither owner nor tenant) — denied (403); the gate returns null BEFORE touching
        // any hub data.
        $this->assertNull($viewSvc->getHubDetail(55, 1), 'Non-owner must get null (403)');
    }

    // B17: getWellEvents zwraca wiersze (bindowanie LIMIT lamalo zapytanie przy emulacji prepared statements).
    // B17: getWellEvents returns rows (binding LIMIT broke the query under emulated prepares).
    public function testGetWellEventsReturnsRows(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL)');
        $db->exec('CREATE TABLE well_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            well_id INTEGER NOT NULL,
            player_id INTEGER NULL,
            event_type TEXT NULL,
            cost INTEGER NULL,
            message TEXT NULL,
            created_at TEXT NOT NULL
        )');
        $db->exec("INSERT INTO wells (id, player_id) VALUES (100, 1)");
        for ($i = 0; $i < 3; $i++) {
            $db->exec("INSERT INTO well_events (well_id, player_id, event_type, created_at)
                       VALUES (100, 1, 'failure', datetime('now'))");
        }

        // WellService self-konstruuje z singletona MySQL; w tescie omijamy konstruktor i wstrzykujemy SQLite.
        // WellService self-constructs from the MySQL singleton; bypass the constructor and inject SQLite.
        $svc = (new ReflectionClass(WellService::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($svc, WellService::class, 'db', $db);

        $events = $svc->getWellEvents(100, 1, 20);
        $this->assertCount(3, $events, 'getWellEvents must return the 3 inserted rows');
    }

    // B17b: clamp LIMIT — wartosci spoza zakresu nie wywalaja zapytania.
    // B17b: LIMIT clamp — out-of-range values do not break the query.
    public function testGetWellEventsClampsLimit(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL)');
        $db->exec('CREATE TABLE well_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            well_id INTEGER NOT NULL,
            player_id INTEGER NULL,
            created_at TEXT NOT NULL
        )');
        $db->exec("INSERT INTO wells (id, player_id) VALUES (100, 1)");
        $db->exec("INSERT INTO well_events (well_id, player_id, created_at) VALUES (100, 1, datetime('now'))");

        $svc = (new ReflectionClass(WellService::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($svc, WellService::class, 'db', $db);

        $this->assertIsArray($svc->getWellEvents(100, 1, 0), 'limit 0 must clamp, not throw');
        $this->assertIsArray($svc->getWellEvents(100, 1, 99999), 'huge limit must clamp, not throw');
    }
}
