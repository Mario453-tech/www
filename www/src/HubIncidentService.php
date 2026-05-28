<?php

require_once __DIR__ . '/Hub/IncidentRiskTrait.php';
require_once __DIR__ . '/Hub/IncidentEffectsTrait.php';

/**
 * HubIncidentService  generuje i przetwarza incydenty logistyczne hubw.
 * HubIncidentService  generates and processes logistics hub incidents.
 *
 * Wywoywany z WellLoopSection::finalizeHubTicks() po HubTickService::processTick().
 * Called from WellLoopSection::finalizeHubTicks() after HubTickService::processTick().
 * Zdarzenia zapisywane do: logistics_hub_events, technical_notifications.
 * Events saved to: logistics_hub_events, technical_notifications.
 * Uszkodzenia kondycji aplikowane natychmiast do: logistics_hubs.condition_pct.
 * Condition damage applied immediately to: logistics_hubs.condition_pct.
 *
 * Typy incydentw (15 README):
 * Incident types (15 README):
 *   transfer_failure, equipment_damage, local_leak, loading_error, storage_jam, critical_overload
 *
 * Traits:
 *   HubIncidentRiskTrait     stae BASE_CHANCE/INCIDENTS/MSG_COUNT + calcRiskMultiplier
 *                           BASE_CHANCE/INCIDENTS/MSG_COUNT constants + calcRiskMultiplier
 *   HubIncidentEffectsTrait  generateIncident, applyConditionDamage, saveEvent, notifyPlayer
 */
class HubIncidentService
{
    use HubIncidentRiskTrait;
    use HubIncidentEffectsTrait;

    private PDO $db;
    private HubService $hubSvc;

    public function __construct(PDO $db, ?HubService $hubSvc = null)
    {
        $this->db = $db;
        $this->hubSvc = $hubSvc ?? new HubService($db);
    }

    // Main tick method

    /**
     * Sprawdza szanse incydentu dla jednego huba w jednym ticku.
     * Checks incident chances for one hub in one tick.
     * Jeli incydent wylosowany  generuje go, zapisuje i zwraca dane.
     * If an incident is rolled  generates, saves and returns it.
     *
     * @param array<string, mixed> $hub        Wiersz huba z DB / Hub row from DB
     * @param float                $inputBbl   Wolumen wejciowy tego ticka (bbl) / Input volume this tick
     * @param array<string, mixed> $tickResult Wynik z HubTickService::processTick() / Result from HubTickService
     * @param float                $deltaHours Czas ticka (godziny) / Tick duration in hours
     * @param int                  $playerId   Gracz, ktrego studnie s w hubie / Player whose wells are in the hub
     * @return ?array<string, mixed>           Dane incydentu lub null / Incident data or null
     */
    public function processTick(
        array $hub,
        float $inputBbl,
        array $tickResult,
        float $deltaHours,
        int   $playerId,
        array $hseBonus = []
    ): ?array {
        // Huby active/overloaded/damaged/critical mog mie incydenty (disabled/building/paused  nie)
        // Hubs active/overloaded/damaged/critical may have incidents (disabled/building/paused  no)
        $status = (string)($hub['status'] ?? 'active');
        if (!in_array($status, ['active', 'overloaded', 'damaged', 'critical'], true)) {
            return null;
        }

        // Brak wolumenu  brak incydentu / No volume  no incident
        if ($inputBbl < 0.001) {
            return null;
        }

        $riskMult = $this->calcRiskMultiplier($hub, $tickResult, $hseBonus);
        $loadPct  = (float)($tickResult['load_pct'] ?? 0.0);

        foreach (self::INCIDENTS as $type => $cfg) {
            // critical_overload  tylko gdy faktycznie przeciony / only when actually overloaded
            if ($type === 'critical_overload' && $loadPct <= 100.0) {
                continue;
            }

            $chance = (self::BASE_CHANCE_PER_HOUR[$type] ?? 0.01) * $deltaHours * $riskMult;
            if ((mt_rand(0, 999999) / 1_000_000.0) < $chance) {
                return $this->generateIncident($type, $cfg, $hub, $inputBbl, $tickResult, $playerId);
            }
        }

        return null;
    }

    // Data getters

    /**
     * Ostatnie incydenty hubw gracza (do wywietlenia na stronie logistyki i dziau tech).
     * Recent hub incidents for the player (for display on logistics and technical pages).
     * @return list<array<string, mixed>>
     */
    public function getPlayerRecentIncidents(int $playerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, h.name AS hub_name
            FROM   logistics_hub_events e
            LEFT   JOIN logistics_hubs h ON h.id = e.hub_id
            WHERE  e.player_id  = ?
              AND  e.event_type LIKE 'hub_incident_%'
            ORDER BY e.created_at DESC
            LIMIT  ?
        ");
        $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit,    PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liczba incydentw hubw gracza (do paginacji).
     * Count of hub incidents for the player (for pagination).
     */
    public function countPlayerIncidents(int $playerId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM   logistics_hub_events
            WHERE  player_id  = ?
              AND  event_type LIKE 'hub_incident_%'
        ");
        $stmt->execute([$playerId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Ostatnie incydenty dla konkretnego huba (do kart na logistics page).
     * @return list<array<string, mixed>>
     */
    public function getHubRecentIncidents(int $hubId, int $limit = 3): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM   logistics_hub_events
            WHERE  hub_id     = ?
              AND  event_type LIKE 'hub_incident_%'
            ORDER BY created_at DESC
            LIMIT  ?
        ");
        $stmt->bindValue(1, $hubId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //  Admin: wymuszanie awarii 

    /**
     * Forces an incident on a hub immediately  admin tool, bypasses tick and RNG.
     * Saves event to logistics_hub_events and optionally notifies a player.
     *
     * @return array<string, mixed>  Keys: success, type, severity, cond_dmg, extra_loss, message
     *                               On error: success=false, error=string
     */
    public function forceIncident(int $hubId, string $type, int $playerId = 0): array
    {
        if (!array_key_exists($type, self::INCIDENTS)) {
            return ['success' => false, 'error' => 'unknown_type'];
        }

        $hub = $this->hubSvc->getHub($hubId);
        if (!$hub) {
            return ['success' => false, 'error' => 'hub_not_found'];
        }

        $cfg        = self::INCIDENTS[$type];
        $tickResult = ['load_pct' => 0.0]; // brak ticka  obciazenie nieznane / no tick  load unknown

        $result = $this->generateIncident($type, $cfg, $hub, 0.0, $tickResult, $playerId);

        GameLog::info('admin', 'hub_incident_forced', [
            'hub_id'    => $hubId,
            'type'      => $type,
            'player_id' => $playerId,
            'cond_dmg'  => $result['cond_dmg'],
        ]);

        return array_merge(['success' => true], $result);
    }

    //  Pomocnicze 

    private function getTitle(string $type): string
    {
        return tPlain("logistics.hub.incident.title.{$type}");
    }
}
