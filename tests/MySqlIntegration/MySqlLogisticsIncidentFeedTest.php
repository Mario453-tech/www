<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LogisticsIncidentFeed.php';

final class MySqlLogisticsIncidentFeedTest extends MySqlIntegrationTestCase
{
    public function testCombinedFeedFiltersAndPaginatesPlayerIncidents(): void
    {
        $playerId = $this->seedPlayer();
        $hubId = $this->getTrackedIds()['hubId'];
        $this->seedHub($hubId, 'Filter Test Hub', 77, 'A1', 75.0, 'active');
        $pipelineId = $this->getTrackedIds()['wellId'];
        try {
            $this->db->prepare(
                "INSERT INTO logistics_hub_events
                    (player_id, hub_id, event_type, severity, title, message, created_at)
                 VALUES (?, ?, 'hub_incident_local_leak', 'critical', 'Leak', 'Hub leak', '2026-09-28 10:00:00')"
            )->execute([$playerId, $hubId]);
            $this->db->prepare(
                "INSERT INTO well_pipeline_events
                    (player_id, well_id, pipeline_id, event_type, severity, message, created_at)
                 VALUES (?, ?, ?, 'incident', 'warning', 'Pipeline valve leak', '2026-09-29 10:00:00')"
            )->execute([$playerId, $pipelineId, $pipelineId]);
            $feed = new LogisticsIncidentFeed($this->db);

            self::assertSame(2, $feed->count($playerId));
            self::assertSame('pipeline', $feed->page($playerId, 1, 0)[0]['source']);
            self::assertSame('hub', $feed->page($playerId, 1, 1)[0]['source']);
            self::assertSame(1, $feed->count($playerId, 'valve', 'medium'));
            self::assertSame(0, $feed->count($playerId, 'valve', 'critical'));
            self::assertSame('Filter Test Hub', $feed->page($playerId, 20, 0, 'Filter Test Hub')[0]['hub_name']);
            self::assertSame(0, $feed->count($playerId + 100));
        } finally {
            $this->db->prepare('DELETE FROM well_pipeline_events WHERE player_id = ?')->execute([$playerId]);
        }
    }
}
