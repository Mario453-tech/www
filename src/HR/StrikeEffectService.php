<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';

final class StrikeEffectService
{
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
        $stmt = $this->db->prepare(
            "SELECT department_code FROM employee_strikes
              WHERE player_id = ? AND status = 'active' AND open_key IS NOT NULL"
        );
        $stmt->execute([$playerId]);
        $effects = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $department) {
            $effects[(string)$department] = $this->departmentEffect((string)$department);
        }
        return $effects;
    }

    /** @return array<string,float|bool> */
    private function departmentEffect(string $department): array
    {
        return match ($department) {
            'logistics' => [
                'roles_active'=>false,
                'throughput_mult'=>0.70,
                'transport_cost_mult'=>1.20,
                'delay_risk_mult'=>1.15,
                'response_time_mult'=>1.25,
            ],
            'technical' => [
                'roles_active'=>false,
                'repair_time_mult'=>1.40,
                'emergency_cost_mult'=>1.15,
            ],
            'hr' => [
                'roles_active'=>false,
                'recruitment_time_mult'=>1.25,
                'negotiation_effectiveness_mult'=>0.80,
            ],
            'legal' => [
                'roles_active'=>false,
                'case_time_mult'=>1.25,
                'effectiveness_mult'=>0.85,
                'deadline_risk_mult'=>1.15,
            ],
            'finance' => ['roles_active'=>false],
            default => [],
        };
    }
}
