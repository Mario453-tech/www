<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/LogisticsIncidentFeed.php';

final class LogisticsIncidentFeedTest extends TestCase
{
    public function testPlayerScopedCombinedFeedFiltersAndPagination(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->sqliteCreateFunction('CONCAT', static fn (...$parts): string => implode('', $parts));
        $db->exec('CREATE TABLE logistics_hubs (id INTEGER, player_id INTEGER, name TEXT)');
        $db->exec('CREATE TABLE logistics_hub_events (id INTEGER, player_id INTEGER, hub_id INTEGER, event_type TEXT, severity TEXT, message TEXT, meta_json TEXT, created_at TEXT)');
        $db->exec('CREATE TABLE well_pipelines (id INTEGER, player_id INTEGER, name TEXT)');
        $db->exec('CREATE TABLE well_pipeline_events (id INTEGER, player_id INTEGER, pipeline_id INTEGER, event_type TEXT, severity TEXT, message TEXT, created_at TEXT)');
        $db->exec("INSERT INTO logistics_hubs VALUES (1, 7, 'Hub Alpha')");
        $db->exec("INSERT INTO logistics_hub_events VALUES (1, 7, 1, 'hub_incident_leak', 'critical', 'Hub leak', '{}', '2026-09-28 10:00:00')");
        $db->exec("INSERT INTO logistics_hub_events VALUES (2, 8, 1, 'hub_incident_leak', 'critical', 'Other player', '{}', '2026-09-30 10:00:00')");
        $db->exec("INSERT INTO logistics_hub_events VALUES (5, 7, 1, 'hub_incidentXother', 'low', 'Unrelated event', '{}', '2026-09-30 11:00:00')");
        $db->exec("INSERT INTO well_pipelines VALUES (5, 7, 'Pipeline Beta')");
        $db->exec("INSERT INTO well_pipeline_events VALUES (3, 7, 5, 'incident', 'warning', 'Valve leak', '2026-09-29 10:00:00')");
        $db->exec("INSERT INTO well_pipeline_events VALUES (4, 7, 5, 'pipeline_build_started', 'info', 'Building', '2026-09-30 10:00:00')");
        $feed = new LogisticsIncidentFeed($db);

        self::assertSame(2, $feed->count(7));
        self::assertSame('pipeline', $feed->page(7, 1, 0)[0]['source']);
        self::assertSame('hub', $feed->page(7, 1, 1)[0]['source']);
        self::assertSame(1, $feed->count(7, 'Valve', 'medium'));
        self::assertSame(0, $feed->count(7, 'Valve', 'critical'));
        self::assertSame('Hub Alpha', $feed->page(7, 20, 0, 'Hub Alpha')[0]['hub_name']);
        self::assertSame(1, $feed->count(8));
    }
}
