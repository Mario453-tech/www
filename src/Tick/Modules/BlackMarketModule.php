<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__, 2) . '/BlackMarketService.php';

final class BlackMarketModule implements TickModule
{
    private int $expiredOffers = 0;
    private int $generatedOffers = 0;
    private int $eligiblePlayers = 0;
    private int $tickCounter = 0;
    private float $deltaHours = 1.0 / 12.0;

    public function key(): string
    {
        return 'black_market';
    }

    public function order(): int
    {
        return 50;
    }

    public function failurePolicy(): TickFailurePolicy
    {
        return TickFailurePolicy::CONTINUE;
    }

    public function run(TickContext $ctx): void
    {
        $service = new BlackMarketService($ctx->db);
        $this->expiredOffers = $service->expireOffers();
        $this->deltaHours = $this->resolveDeltaHours($ctx);
        $service->decayScores($this->deltaHours);

        $interval = $this->configInt($ctx->db, 'bm_offer_interval_ticks', 3, 1);
        $this->tickCounter = $this->incrementTickCounter($ctx->db);
        if ($this->tickCounter < 1 || $this->tickCounter % $interval !== 0) {
            return;
        }

        $playerIds = $ctx->db->query(
            "SELECT id FROM players
              WHERE financial_state != 'crisis'
                AND id IN (
                    SELECT DISTINCT player_id FROM wells
                     WHERE status NOT IN ('seized','blowout','sold')
                )"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->eligiblePlayers = count($playerIds);

        foreach ($playerIds as $playerId) {
            $this->generatedOffers += $service->generateOffers((int)$playerId, $ctx->newPrice);
        }

        if ($this->generatedOffers > 0 && class_exists('GameLog', false)) {
            GameLog::info('tick', 'black market offers generated', [
                'offers' => $this->generatedOffers,
                'players' => $this->eligiblePlayers,
            ]);
        }
    }

    /** @return array<string,int|float> */
    public function stats(): array
    {
        return [
            'offers_expired' => $this->expiredOffers,
            'offers_generated' => $this->generatedOffers,
            'eligible_players' => $this->eligiblePlayers,
            'tick_counter' => $this->tickCounter,
            'delta_hours' => $this->deltaHours,
        ];
    }

    private function resolveDeltaHours(TickContext $ctx): float
    {
        try {
            $lastTimestamp = $ctx->db->query(
                "SELECT `value` FROM well_config WHERE `key` = 'last_system_tick_at' LIMIT 1"
            )->fetchColumn();
            if ($lastTimestamp !== false && (int)$lastTimestamp > 0) {
                $elapsed = $ctx->now->getTimestamp() - (int)$lastTimestamp;
                if ($elapsed > 0) {
                    return $elapsed / 3600.0;
                }
            }
        } catch (Throwable) {
        }

        return 1.0 / 12.0;
    }

    private function configInt(PDO $db, string $key, int $default, int $minimum): int
    {
        try {
            $stmt = $db->prepare("SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? max($minimum, (int)$value) : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    private function incrementTickCounter(PDO $db): int
    {
        $db->prepare(
            "INSERT INTO well_config (`key`, `value`, `label`, `category`)
             VALUES ('bm_tick_counter', '1', 'Czarny rynek - licznik tickow', 'black_market')
             ON DUPLICATE KEY UPDATE `value` = `value` + 1"
        )->execute();

        $stmt = $db->prepare("SELECT `value` FROM well_config WHERE `key` = 'bm_tick_counter' LIMIT 1");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}
