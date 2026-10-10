<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WellEventRetentionServiceTest extends TestCase
{
    public function testPruneBeforeDeletesOnlyEventsOlderThanTheCutoff(): void
    {
        require_once dirname(__DIR__, 2) . '/src/WellEventRetentionService.php';
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('CREATE TABLE well_events (id INTEGER PRIMARY KEY, player_id INTEGER, well_id INTEGER, created_at TEXT)');
        $db->exec("INSERT INTO well_events VALUES
            (1,7,1,'2026-10-09 11:59:59'),
            (2,7,1,'2026-10-09 12:00:00'),
            (3,8,2,'2026-10-11 11:59:59')");

        $deleted = (new WellEventRetentionService($db))->pruneBefore('2026-10-09 12:00:00');

        self::assertSame(1, $deleted);
        self::assertSame([2, 3], array_map('intval', $db->query('SELECT id FROM well_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testPruneBeforeRemovesTheEntireExpiredBacklog(): void
    {
        require_once dirname(__DIR__, 2) . '/src/WellEventRetentionService.php';
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE well_events (id INTEGER PRIMARY KEY, player_id INTEGER, well_id INTEGER, created_at TEXT)');
        $insert = $db->prepare("INSERT INTO well_events VALUES (?,7,1,'2026-10-09 11:59:59')");
        $db->beginTransaction();
        for ($id = 1; $id <= 2001; $id++) $insert->execute([$id]);
        $db->exec("INSERT INTO well_events VALUES (2002,7,1,'2026-10-09 12:00:00')");
        $db->commit();

        $deleted = (new WellEventRetentionService($db))->pruneBefore('2026-10-09 12:00:00');

        self::assertSame(2001, $deleted);
        self::assertSame([2002], array_map('intval', $db->query('SELECT id FROM well_events ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
    }
}
