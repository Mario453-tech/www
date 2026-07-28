<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';

final class StrikeEffectService
{
    /** @var array<int,array<string,array<string,float|bool>>> */
    private array $cache = [];
    /** @var array<int,true> */
    private array $loaded = [];

    public function __construct(
        private readonly PDO $db,
        private readonly EmployeeSystemConfigService $config
    ) {
    }

    /**
     * Loads all department effects once for a player.
     * Laduje wszystkie efekty dzialow jednym zapytaniem dla gracza.
     *
     * @return array<string,array<string,float|bool>>
     */
    public function forPlayer(int $playerId): array
    {
        if ($playerId <= 0 || !$this->config->getBool('feature_strike_effects')) {
            return [];
        }
        if (!isset($this->loaded[$playerId])) {
            $this->forPlayers([$playerId]);
        }
        return $this->cache[$playerId] ?? [];
    }

    /**
     * Loads active and negotiating strike effects for many players in one query.
     * Laduje efekty aktywnych i negocjowanych strajkow dla wielu graczy jednym zapytaniem.
     *
     * @param list<int> $playerIds
     * @return array<int,array<string,array<string,float|bool>>>
     */
    public function forPlayers(array $playerIds): array
    {
        $playerIds = array_values(array_unique(array_filter(
            array_map('intval', $playerIds),
            static fn(int $id): bool => $id > 0
        )));
        foreach ($playerIds as $playerId) {
            $this->cache[$playerId] ??= [];
        }
        if ($playerIds === [] || !$this->config->getBool('feature_strike_effects')) {
            return array_intersect_key($this->cache, array_flip($playerIds));
        }
        $missing = array_values(array_filter(
            $playerIds,
            fn(int $playerId): bool => !isset($this->loaded[$playerId])
        ));
        if ($missing === []) {
            return array_intersect_key($this->cache, array_flip($playerIds));
        }
        $placeholders = implode(',', array_fill(0, count($missing), '?'));
        $stmt = $this->db->prepare(
            "SELECT player_id, department_code FROM employee_strikes
              WHERE player_id IN ({$placeholders})
                AND status IN ('active','negotiating') AND open_key IS NOT NULL"
        );
        $stmt->execute($missing);
        foreach ($missing as $playerId) {
            $this->loaded[$playerId] = true;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->cache[(int)$row['player_id']][(string)$row['department_code']]
                = $this->departmentEffect((string)$row['department_code']);
        }
        return array_intersect_key($this->cache, array_flip($playerIds));
    }

    /** @return array<string,float|bool> */
    private function departmentEffect(string $department): array
    {
        return match ($department) {
            'logistics' => [
                'capacity_cap'=>$this->config->getFloat('strike_logistics_capacity_cap'),
                'transport_cost_mult'=>$this->config->getFloat('strike_road_cost_multiplier'),
                'delay_risk_mult'=>$this->config->getFloat('strike_road_delay_risk_multiplier'),
                'response_time_mult'=>$this->config->getFloat('strike_logistics_response_multiplier'),
            ],
            'technical' => [
                'repair_time_mult'=>$this->config->getFloat('strike_technical_repair_time_multiplier'),
                'emergency_cost_mult'=>$this->config->getFloat('strike_technical_emergency_cost_multiplier'),
            ],
            'hr' => [
                'recruitment_time_mult'=>$this->config->getFloat('strike_hr_recruitment_time_multiplier'),
                'negotiation_effectiveness_mult'=>$this->config->getFloat('strike_hr_negotiation_effectiveness'),
                'negative_morale_mult'=>$this->config->getFloat('strike_hr_negative_morale_multiplier'),
            ],
            'legal' => [
                'case_time_mult'=>$this->config->getFloat('strike_legal_case_time_multiplier'),
                'effectiveness_mult'=>$this->config->getFloat('strike_legal_effectiveness'),
                'deadline_risk_mult'=>$this->config->getFloat('strike_legal_deadline_risk_multiplier'),
            ],
            'finance' => ['role_bonus_active'=>false],
            default => [],
        };
    }
}
