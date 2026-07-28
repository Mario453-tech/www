<?php
declare(strict_types=1);

require_once __DIR__ . '/StrikeEffectService.php';

final class EmployeeNegotiationEffectivenessService
{
    private readonly EmployeeSystemConfigService $config;

    public function __construct(private readonly PDO $db)
    {
        $this->config = new EmployeeSystemConfigService($db);
    }

    public function calculate(int $playerId, bool $includeMorale = true): float
    {
        $moraleFactor = $includeMorale
            ? '* (0.5 + COALESCE(es.morale,65) / 200)'
            : '';
        $stmt = $this->db->prepare(
            "SELECT AVG(
                    ((COALESCE(bm.skill_negotiation,5) + COALESCE(bm.skill_organization,5)) * 5)
                    {$moraleFactor}
                )
               FROM board_members bm
               JOIN board_roles br ON br.id=bm.role_id AND br.code='hr'
          LEFT JOIN employee_state es
                 ON es.player_id=bm.player_id
                AND es.source_type='board_member'
                AND es.source_id=bm.id
              WHERE bm.player_id=? AND bm.status='active'
                AND COALESCE(es.relation_status, 'normal') NOT IN ('on_strike','leaving','inactive')"
        );
        $stmt->execute([$playerId]);
        $value = $stmt->fetchColumn();
        $effectiveness = $value !== false && $value !== null
            ? max(0.0, min(100.0, (float)$value))
            : ($includeMorale ? 0.0 : 50.0);
        $effects = (new StrikeEffectService($this->db, $this->config))->forPlayer($playerId);
        $multiplier = (float)($effects['hr']['negotiation_effectiveness_mult'] ?? 1.0);
        return round(max(0.0, min(100.0, $effectiveness * $multiplier)), 4);
    }
}
