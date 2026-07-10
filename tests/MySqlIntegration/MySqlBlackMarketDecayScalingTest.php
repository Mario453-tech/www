<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/BlackMarketService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/Modules/BlackMarketModule.php';

/**
 * L4: plaski decay black_market_score musi skalowac sie czasem ticka (deltaHours),
 * nie byc stalym krokiem — inaczej catch-up tick po przerwie crona odejmuje tylko
 * jeden krok zamiast rownowartosci wielu godzin.
 * L4: flat black_market_score decay must scale with tick duration (deltaHours), not be a
 * fixed step — otherwise a catch-up tick after a cron outage subtracts only one step.
 */
final class MySqlBlackMarketDecayScalingTest extends MySqlIntegrationTestCase
{
    private function setDecayConfig(float $flatPerTick): void
    {
        // Znany plaski decay + wylaczona sciezka procentowa (bm_decay_pct = 0 => early return),
        // zeby test byl deterministyczny.
        // Known flat decay + disabled percentage path so the test is deterministic.
        foreach ([['bm_score_decay_per_tick', $flatPerTick], ['bm_decay_pct', 0.0]] as [$key, $val]) {
            $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, `label`, `category`)
                 VALUES (?, ?, 'phpunit', 'black_market')
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            )->execute([$key, $val]);
        }
    }

    private function setScore(int $playerId, float $score): void
    {
        $this->db->prepare('UPDATE players SET black_market_score = ? WHERE id = ?')
            ->execute([$score, $playerId]);
    }

    private function getScore(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT black_market_score FROM players WHERE id = ?');
        $stmt->execute([$playerId]);
        return (float)$stmt->fetchColumn();
    }

    public function testFlatDecayScalesWithDeltaHours(): void
    {
        $playerId = $this->seedPlayer();
        $this->setDecayConfig(0.5);
        $svc = new BlackMarketService($this->db);

        // Normalny tick 5-min: deltaHours = 1/12 -> ticksElapsed = 1 -> decay 0.5 (jak dawniej).
        // Normal 5-min tick: deltaHours = 1/12 -> ticksElapsed = 1 -> decay 0.5 (as before).
        $this->setScore($playerId, 100.0);
        $svc->decayScores(1.0 / 12.0);
        $this->assertEqualsWithDelta(99.5, $this->getScore($playerId), 0.001, 'Normalny tick: decay = 0.5');

        // Catch-up 2h: ticksElapsed = 2 * 12 = 24 -> decay 0.5 * 24 = 12.
        $this->setScore($playerId, 100.0);
        $svc->decayScores(2.0);
        $this->assertEqualsWithDelta(88.0, $this->getScore($playerId), 0.001, 'Catch-up 2h: decay skalowany = 12');

        // Duzy catch-up nie schodzi ponizej 0 (GREATEST floor).
        // A large catch-up does not go below 0 (GREATEST floor).
        $this->setScore($playerId, 5.0);
        $svc->decayScores(24.0);
        $this->assertEqualsWithDelta(0.0, $this->getScore($playerId), 0.001, 'Decay przycięty do 0');
    }

    public function testModulePreservesDeltaScalingAndReportsStats(): void
    {
        $this->db->beginTransaction();
        try {
            $playerId = $this->seedPlayer();
            $this->setDecayConfig(0.5);
            $this->setScore($playerId, 100.0);
            $now = time();
            $upsert = $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, `label`, `category`) VALUES (?, ?, 'phpunit', 'black_market')
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            );
            $upsert->execute(['last_system_tick_at', (string)($now - 7200)]);
            $upsert->execute(['bm_offer_interval_ticks', '999999']);

            $ctx = new TickContext($this->db, (new DateTimeImmutable())->setTimestamp($now), 'test');
            $module = new BlackMarketModule();
            $module->run($ctx);
            $stats = $module->stats();

            $this->assertEqualsWithDelta(88.0, $this->getScore($playerId), 0.001);
            $this->assertEqualsWithDelta(2.0, (float)$stats['delta_hours'], 0.01);
            $this->assertSame(0, $stats['offers_generated']);
        } finally {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }
    }
}
