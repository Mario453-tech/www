<?php
/**
 * GeologicalLayerService - geological layer system.
 * PL: GeologicalLayerService - system warstw geologicznych.
 *
 * Each well has an active layer (shallow/mid/deep/ultra).
 * PL: Kazdy odwiert ma aktywna warstwe (shallow/mid/deep/ultra).
 * Layer affects richness, failure chance, wear and disaster spiral.
 * PL: Warstwa wplywa na richness, szanse awarii, wear i spirale katastrof.
 * Switching layers means cost plus downtime.
 * PL: Zmiana warstwy oznacza koszt plus przestoj.
 *
 * geological_layers table:
 * PL: Tabela geological_layers:
 * id, code, name, depth_m_max, reservoir_bbl, richness_mult,
 * risk_mult, wear_depth_factor, spiral_boost,
 * switch_cost, switch_hours
 *
 * wells columns:
 * PL: Kolumny w wells:
 * active_layer_id, layer_reservoir_used, layer_switch_until
 */
class GeologicalLayerService
{
    private PDO $db;

 // Layer cache loaded once per request.
 // PL: Cache warstw ladowany raz na request.
 /** @var array<int, array<string, mixed>>|null */
    private static ?array $layerCache = null;

 // Equipment rule: ultra/deep requires better gear.
 // PL: Zasada sprzetu: ultra/deep wymaga lepszego wyposazenia.
    private const BLACKMARKET_DEEP_PENALTY = 1.50;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

 // Layer reads.
 // PL: Odczyt warstw.

 /**
 * Return all available geological layers.
 * PL: Zwraca wszystkie dostepne warstwy geologiczne.
 *
 * @return array<int, array<string, mixed>>
 */
    public function getAllLayers(): array
    {
        if (self::$layerCache !== null) {
            return self::$layerCache;
        }
        try {
            $rows = $this->db->query("SELECT * FROM geological_layers ORDER BY sort_order")->fetchAll();
            self::$layerCache = [];
            foreach ($rows as $row) {
                self::$layerCache[$row['id']] = $row;
            }
        } catch (Throwable $e) {
 // Table may not exist yet before migration - use a single fallback layer.
 // PL: Tabela moze jeszcze nie istniec przed migracja - uzyj jednej warstwy fallback.
            self::$layerCache = [1 => $this->getFallbackLayer()];
        }
        return self::$layerCache;
    }

 /**
 * Return one layer by id.
 * PL: Zwraca warstwe po id.
 *
 * @return array<string, mixed>|null
 */
    public function getLayer(int $layerId): ?array
    {
        $layers = $this->getAllLayers();
        return $layers[$layerId] ?? null;
    }

 /**
 * Return one layer by code.
 * PL: Zwraca warstwe po kodzie.
 *
 * @return array<string, mixed>|null
 */
    public function getLayerByCode(string $code): ?array
    {
        foreach ($this->getAllLayers() as $layer) {
            if ($layer['code'] === $code) {
                return $layer;
            }
        }
        return null;
    }

 /**
 * Return the active layer for a well.
 * PL: Zwraca aktywna warstwe dla odwiertu.
 *
 * @return array<string, mixed>
 */
    public function getActiveLayer(int $wellId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT active_layer_id FROM wells WHERE id = ? LIMIT 1");
            $stmt->execute([$wellId]);
            $layerId = (int)($stmt->fetchColumn() ?? 1);
        } catch (Throwable $e) {
 // active_layer_id may not exist yet - use shallow fallback.
 // PL: active_layer_id moze jeszcze nie istniec - uzyj plytkiego fallback.
            $layerId = 1;
        }
        return $this->getLayer($layerId) ?? $this->getFallbackLayer();
    }

 // Multipliers used by tick and incidents.
 // PL: Mnozniki uzywane przez tick i incydenty.

 /**
 * Return multipliers for the well's active layer.
 * PL: Zwraca mnozniki dla aktywnej warstwy odwiertu.
 *
 * @return array<string, mixed>
 */
    public function getLayerMultipliers(int $wellId, string $equipmentTier = 'standard'): array
    {
        $layer = $this->getActiveLayer($wellId);

        $riskMult = (float)$layer['risk_mult'];

 // Penalty: black_market + deep/ultra means extra failures.
 // PL: Kara: black_market + deep/ultra oznacza dodatkowe awarie.
        if ($equipmentTier === 'black_market'
            && in_array($layer['code'], ['deep', 'ultra'], true)) {
            $riskMult *= self::BLACKMARKET_DEEP_PENALTY;
        }

        return [
            'richness_mult' => (float)$layer['richness_mult'],
            'risk_mult'     => round($riskMult, 4),
            'wear_mult'     => (float)$layer['wear_depth_factor'],
            'spiral_boost'  => (float)$layer['spiral_boost'],
            'code'          => $layer['code'],
            'name'          => $layer['name'],
            'layer_id'      => (int)$layer['id'],
        ];
    }

 /**
 * Static multipliers helper for PHP templates.
 * PL: Statyczny helper mnoznikow dla szablonow PHP.
 *
 * @param array<string, mixed> $layer
 * @return array<string, mixed>
 */
    public static function multipliersFromLayer(array $layer, string $equipmentTier = 'standard'): array
    {
        $riskMult = (float)$layer['risk_mult'];
        if ($equipmentTier === 'black_market'
            && in_array($layer['code'], ['deep', 'ultra'], true)) {
            $riskMult *= self::BLACKMARKET_DEEP_PENALTY;
        }
        return [
            'richness_mult' => (float)$layer['richness_mult'],
            'risk_mult'     => round($riskMult, 4),
            'wear_mult'     => (float)$layer['wear_depth_factor'],
            'spiral_boost'  => (float)$layer['spiral_boost'],
        ];
    }

 // Reservoir tracking.
 // PL: Obsluga zasobow warstwy.

 /**
 * Return remaining barrels in the active layer.
 * PL: Zwraca pozostale barylki w aktywnej warstwie.
 */
    public function getRemainingReservoir(int $wellId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT w.active_layer_id, w.layer_reservoir_used,
                   gl.reservoir_bbl
            FROM wells w
            JOIN geological_layers gl ON gl.id = w.active_layer_id
            WHERE w.id = ? LIMIT 1
        ");
        $stmt->execute([$wellId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $remaining = (int)$row['reservoir_bbl'] - (int)$row['layer_reservoir_used'];
        return max(0, $remaining);
    }

 /**
 * Register produced oil consumption in the current layer reservoir.
 * PL: Rejestruje zuzycie zasobu aktualnej warstwy przez wydobycie.
 */
    public function consumeReservoir(int $wellId, float $bblProduced): bool
    {
        $this->db->prepare("
            UPDATE wells
            SET layer_reservoir_used = layer_reservoir_used + ?
            WHERE id = ?
        ")->execute([(int)ceil($bblProduced), $wellId]);

        $remaining = $this->getRemainingReservoir($wellId);
        return $remaining === null || $remaining > 0;
    }

 /**
 * Check whether the active layer is exhausted.
 * PL: Sprawdza, czy aktywna warstwa jest wyczerpana.
 */
    public function isLayerExhausted(int $wellId): bool
    {
        $remaining = $this->getRemainingReservoir($wellId);
        return $remaining !== null && $remaining <= 0;
    }

 // Layer switching flow.
 // PL: Obsluga zmiany warstwy.

 /**
 * Switch the active layer for a well.
 * PL: Zmienia aktywna warstwe odwiertu.
 *
 * @return array<string, mixed>
 */
    public function switchLayer(int $wellId, int $playerId, int $targetLayerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT w.*, gl.code AS current_layer_code, gl.name AS current_layer_name
                FROM wells w
                JOIN geological_layers gl ON gl.id = w.active_layer_id
                WHERE w.id = ? AND w.player_id = ?
            ");
            $stmt->execute([$wellId, $playerId]);
            $well = $stmt->fetch();

            if (!$well) {
                return ['success' => false, 'message' => t('geology.err_well_not_found')];
            }
            if ($well['status'] === 'seized') {
                return ['success' => false, 'message' => t('geology.err_well_seized')];
            }
            if (!empty($well['layer_switch_until'])
                && strtotime($well['layer_switch_until']) > time()) {
                $left = ceil((strtotime($well['layer_switch_until']) - time()) / 3600);
                return ['success' => false, 'message' => t('geology.err_drilling_in_progress', ['hours' => $left])];
            }

            $targetLayer = $this->getLayer($targetLayerId);
            if (!$targetLayer) {
                return ['success' => false, 'message' => t('geology.err_unknown_layer')];
            }
            if ((int)$well['active_layer_id'] === $targetLayerId) {
                return ['success' => false, 'message' => t('geology.err_layer_already_active')];
            }

            $cost = (int)$targetLayer['switch_cost'];
            $hours = (int)$targetLayer['switch_hours'];

            if ($cost > 0) {
                $player = new Player($playerId);
                if (!$player->canAfford($cost)) {
                    return [
                        'success' => false,
                        'message' => t('geology.err_no_funds', ['cost' => number_format($cost, 0, '.', ' ')]),
                    ];
                }
                $player->updateCash(-$cost, 'geological_fee', 'Oplata za zmiane warstwy geologicznej');
            }

            $switchUntil = $hours > 0
                ? date('Y-m-d H:i:s', time() + $hours * 3600)
                : null;

            $this->db->prepare("
                UPDATE wells
                SET active_layer_id      = ?,
                    layer_reservoir_used = 0,
                    layer_switch_until   = ?,
                    status               = CASE
                        WHEN ? > 0 AND status NOT IN ('seized','blowout') THEN 'paused_cash'
                        ELSE status
                    END
                WHERE id = ?
            ")->execute([$targetLayerId, $switchUntil, $hours, $wellId]);

            GameLog::info('GeologicalLayerService', 'layer_switched', [
                'well_id'      => $wellId,
                'from'         => $well['current_layer_code'],
                'to'           => $targetLayer['code'],
                'cost'         => $cost,
                'switch_until' => $switchUntil,
            ]);

            $msg = t('geology.msg_drilling_started', ['layer' => $targetLayer['name']]);
            if ($hours > 0) {
                $msg .= ' ' . t('geology.msg_drilling_paused', ['hours' => $hours, 'cost' => number_format($cost, 0, '.', ' ')]);
            }

            return ['success' => true, 'message' => $msg, 'switch_until' => $switchUntil];
        } catch (Throwable $e) {
            GameLog::error('GeologicalLayerService', 'switchLayer FAILED', $e);
            return ['success' => false, 'message' => t('geology.err_server')];
        }
    }

 /**
 * Unlock wells after drilling downtime finishes.
 * PL: Odblokowuje odwiert po zakonczeniu przestoju wiercenia.
 */
    public function processSwitchCompletion(int $wellId): bool
    {
        $stmt = $this->db->prepare("SELECT layer_switch_until, status FROM wells WHERE id = ? LIMIT 1");
        $stmt->execute([$wellId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['layer_switch_until'])) {
            return false;
        }
        if (strtotime($row['layer_switch_until']) > time()) {
            return false;
        }

 // Downtime has ended - clear it and resume.
 // PL: Przestoj skonczyl sie - wyczysc go i wznow odwiert.
        $this->db->prepare("
            UPDATE wells
            SET layer_switch_until = NULL,
                status = CASE
                    WHEN status = 'paused_cash' THEN 'active'
                    ELSE status
                END
            WHERE id = ?
        ")->execute([$wellId]);

        GameLog::info('GeologicalLayerService', 'layer_switch_completed', ['well_id' => $wellId]);
        return true;
    }

 // Fallback helpers.
 // PL: Helpery fallback.

 /**
 * Return fallback layer data when DB rows are unavailable.
 * PL: Zwraca dane fallback warstwy, gdy brakuje rekordow w bazie.
 *
 * @return array<string, mixed>
 */
    private function getFallbackLayer(): array
    {
        return [
            'id'                => 1,
            'code'              => 'shallow',
            'name'              => t('geology.fallback_name'),
            'depth_m_max'       => 300,
            'reservoir_bbl'     => 100000,
            'richness_mult'     => 0.70,
            'risk_mult'         => 5.00,
            'wear_depth_factor' => 1.00,
            'spiral_boost'      => 1.00,
            'switch_cost'       => 0,
            'switch_hours'      => 0,
            'sort_order'        => 1,
            'description'       => t('geology.fallback_description'),
        ];
    }
}
