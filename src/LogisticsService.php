<?php
/**
 * LogisticsService.php -- Transport optimizer for wells
 *
 * Three modes:
 * balans -- maximize transported - cost x 0.001
 * max_prod -- maximize capacity (ignore cost)
 * min_cost -- minimize unit cost (cost_per_bbl)
 *
 * Rules:
 * - offshore -> tanker only
 * - onshore -> pipeline or trucks (by mode)
 * - Change only if new score > current score (never downgrade)
 * - Cooldown 5 min (PHP session)
 */

class LogisticsService
{
    private PDO $db;
    private int $playerId;
 /** @var array<int, true> */
    private array $ownedPipelineWellIds = [];
 /** @var array<int, true> */
    private array $existingPipelineWellIds = [];

 // Transport config -- same as transport_config + WellLoopSection
    private array $transportConfig = [];

    private const COOLDOWN_KEY    = 'logistics_optimize_last';
    private const COOLDOWN_SECS   = 300; // 5 minut

 // Cost to run the optimizer per mode (in PLN)
    public const MODE_COSTS = [
        'balans'   => 2500,
        'max_prod' => 5000,
        'min_cost' => 1500,
    ];

    public function __construct(int $playerId)
    {
        $this->playerId = $playerId;
        $this->db       = Database::getInstance()->getConnection();
        $this->transportConfig = TransportConfigService::load($this->db);
        $this->existingPipelineWellIds = $this->loadExistingPipelineWellIds();
        $this->ownedPipelineWellIds = $this->loadOwnedPipelineWellIds();
    }

 /** Returns mode cost in PLN */
    public function getModeCost(string $mode): int
    {
        return self::MODE_COSTS[$mode] ?? 2500;
    }

 // -- Cooldown ---
    public function getRemainingCooldown(): int
    {
        $last = $_SESSION[self::COOLDOWN_KEY] ?? 0;
        $diff = (int)$last + self::COOLDOWN_SECS - time();
        return max(0, $diff);
    }

    private function setCooldown(): void
    {
        $_SESSION[self::COOLDOWN_KEY] = time();
    }

 // -- Main optimization method ---
    public function optimize(string $mode = 'balans'): array
    {
        if (!in_array($mode, ['balans', 'max_prod', 'min_cost'], true)) {
            return ['success' => false, 'error' => t('logistics.err_invalid_mode')];
        }

        $cooldown = $this->getRemainingCooldown();
        if ($cooldown > 0) {
            return [
                'success'  => false,
                'cooldown' => $cooldown,
                'error'    => t('logistics.err_cooldown', ['secs' => $cooldown]),
            ];
        }

 // Check player cash
        $modeCost = self::MODE_COSTS[$mode];
        $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $cashStmt->execute([$this->playerId]);
        $playerCash = (float)($cashStmt->fetchColumn() ?? 0);

        if ($playerCash < $modeCost) {
            return [
                'success' => false,
                'error'   => t('logistics.err_insufficient_funds', [
                    'cost' => number_format($modeCost, 0, ',', ' '),
                    'cash' => number_format((int)$playerCash, 0, ',', ' '),
                ]),
            ];
        }

 // Fetch all player wells (active + paused -- not broken/blowout)
        $stmt = $this->db->prepare("
            SELECT id, well_type,
                   base_production_per_hour,
                   transport_type,
                   transport_capacity_pct,
                   transport_opex_pct,
                   status
            FROM wells
            WHERE player_id = ?
              AND status NOT IN ('broken','blowout','seized')
            ORDER BY id
        ");
        $stmt->execute([$this->playerId]);
        $wells = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($wells)) {
            return ['success' => false, 'error' => t('logistics.err_no_wells')];
        }

        $changes  = [];
        $statsBefore = ['transported' => 0.0, 'cost' => 0.0, 'loss' => 0.0];
        $statsAfter  = ['transported' => 0.0, 'cost' => 0.0, 'loss' => 0.0];

        foreach ($wells as $w) {
            $prod     = (float)$w['base_production_per_hour'];
            $wType    = $w['well_type'] ?? 'onshore';  // onshore / offshore
            $currType = (string)($w['transport_type'] ?? ($wType === 'offshore' ? 'tankowiec' : 'nieustawiony'));
            $wellId   = (int)($w['id'] ?? 0);
            $hasPipeline = isset($this->ownedPipelineWellIds[$wellId]);
            $hasAnyPipeline = isset($this->existingPipelineWellIds[$wellId]);
            $effectiveCurrType = $this->resolveEffectiveTransport($wType, $currType, $hasPipeline, $hasAnyPipeline);

 // Calculate stats BEFORE change
            $before = $this->calcStats($prod, $effectiveCurrType);
            $statsBefore['transported'] += $before['transported'];
            $statsBefore['cost']        += $before['cost'];
            $statsBefore['loss']        += $before['loss'];

 // Select best transport for this well
            $bestType = $this->chooseBest($wType, $mode, $prod, $currType, $hasPipeline);
            $effectiveBestType = $this->resolveEffectiveTransport($wType, $bestType, $hasPipeline, $hasAnyPipeline);

 // Calculate stats AFTER change
            $after = $this->calcStats($prod, $effectiveBestType);
            $statsAfter['transported'] += $after['transported'];
            $statsAfter['cost']        += $after['cost'];
            $statsAfter['loss']        += $after['loss'];

            if ($bestType !== $currType) {
                $changes[] = [
                    'well_id'      => (int)$w['id'],
                    'old_type'     => $currType,
                    'new_type'     => $bestType,
                    'old_cap'      => (float)$w['transport_capacity_pct'],
                    'new_cap'      => (float)($this->transportConfig[$bestType]['capacity'] ?? 0.0),
                    'old_opex'     => (float)$w['transport_opex_pct'],
                    'new_opex'     => (float)($this->transportConfig[$bestType]['opex'] ?? 0.0),
                    'prod'         => $prod,
                    'gain'         => round($after['transported'] - $before['transported'], 2),
                    'cost_delta'   => round($after['cost'] - $before['cost'], 4),
                ];
            }
        }

 // Apply changes + deduct cost -- all in one transaction
        $appliedCount = 0;
        try {
            $this->db->beginTransaction();

 // Deduct optimizer cost from player (row lock)
            $deduct = $this->db->prepare(
                "UPDATE players SET cash = cash - ? WHERE id = ? AND cash >= ?"
            );
            $deduct->execute([$modeCost, $this->playerId, $modeCost]);
            if ($deduct->rowCount() === 0) {
                $this->db->rollBack();
                return ['success' => false, 'error' => t('logistics.err_insufficient_funds', [
                    'cost' => number_format($modeCost, 0, ',', ' '),
                    'cash' => number_format((int)$playerCash, 0, ',', ' '),
                ])];
            }

            if (!empty($changes)) {
                $upd = $this->db->prepare("
                    UPDATE wells
                    SET transport_type         = ?,
                        transport_capacity_pct = ?,
                        transport_opex_pct     = ?
                    WHERE id = ? AND player_id = ?
                ");

                foreach ($changes as $ch) {
                    $upd->execute([
                        $ch['new_type'],
                        $ch['new_cap'],
                        $ch['new_opex'],
                        $ch['well_id'],
                        $this->playerId,
                    ]);
                    if ($upd->rowCount() > 0) $appliedCount++;
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('LogisticsService', 'transport optimize save failed', $e, ['player_id' => $this->playerId]);
            return ['success' => false, 'error' => t('logistics.err_save')];
        }

        $this->setCooldown();

 // Efficiency = transported / (prod_all * 100%)
        $totalProd = array_sum(array_map(fn($w) => (float)$w['base_production_per_hour'], $wells));
        $effBefore = $totalProd > 0 ? round($statsBefore['transported'] / $totalProd * 100, 1) : 0;
        $effAfter  = $totalProd > 0 ? round($statsAfter['transported']  / $totalProd * 100, 1) : 0;

        return [
            'success'       => true,
            'mode'          => $mode,
            'mode_cost'     => $modeCost,
            'wells_checked' => count($wells),
            'changed'       => $appliedCount,
            'changes'       => $changes,
            'before'        => [
                'loss'        => round($statsBefore['loss'],        2),
                'cost'        => round($statsBefore['cost'],        4),
                'efficiency'  => $effBefore,
                'transported' => round($statsBefore['transported'], 2),
            ],
            'after'         => [
                'loss'        => round($statsAfter['loss'],        2),
                'cost'        => round($statsAfter['cost'],        4),
                'efficiency'  => $effAfter,
                'transported' => round($statsAfter['transported'], 2),
            ],
            'cooldown_secs' => self::COOLDOWN_SECS,
        ];
    }

 // -- Choosing the best transport ---
    private function chooseBest(string $wellType, string $mode, float $prod, string $currType, bool $hasPipeline): string
    {
 // Offshore -- tanker only
        if ($wellType === 'offshore') {
            return 'tankowiec';
        }

 // Onshore -- without an owned pipeline the only valid local transport is trucks.
 // Onshore -- bez kupionego rurociagu jedynym poprawnym transportem lokalnym sa ciezarowki.
        if (!$hasPipeline) {
            return 'ciezarowki';
        }

 // Onshore -- pipeline or trucks only (no tanker allowed)
 // Start from the first candidate (not currType)
 // so a tanker set on onshore is always replaced
        $candidates = ['rurociag', 'ciezarowki'];
        $best       = $candidates[0];
        $bestScore  = $this->score($mode, $prod, $best);

        foreach (array_slice($candidates, 1) as $type) {
            $s = $this->score($mode, $prod, $type);
            if ($s > $bestScore) {
                $bestScore = $s;
                $best      = $type;
            }
        }

        return $best;
    }

 // -- Scoring function ---
    private function score(string $mode, float $prod, string $type): float
    {
        $cfg         = $this->transportConfig[$type] ?? TransportConfigService::getDefaults()['nieustawiony'];
        $transported = min($prod, $prod * ((float)$cfg['capacity'] / 100.0));
        $cost        = $transported * (float)$cfg['cost_per_bbl'];

        return match($mode) {
            'balans'   => $transported - $cost * 0.001,
            'max_prod' => $transported,
            'min_cost' => -$cost,   // lower costs = better
            default    => $transported - $cost * 0.001,
        };
    }

 // -- Stats for a single well ---
    private function calcStats(float $prod, string $type): array
    {
        $cfg         = $this->transportConfig[$type] ?? TransportConfigService::getDefaults()['nieustawiony'];
        $transported = min($prod, $prod * ((float)$cfg['capacity'] / 100.0));
        $loss        = max(0.0, $prod - $transported);
        $cost        = $transported * (float)$cfg['cost_per_bbl'];

        return [
            'transported' => $transported,
            'loss'        => $loss,
            'cost'        => $cost,
        ];
    }

 // -- Get current transport summary (for preview) ---
    public function getCurrentSummary(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, well_type, base_production_per_hour,
                   transport_type, transport_capacity_pct, transport_opex_pct, status
            FROM wells
            WHERE player_id = ? AND status NOT IN ('broken','blowout','seized')
            ORDER BY id
        ");
        $stmt->execute([$this->playerId]);
        $wells = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = ['wells' => [], 'totals' => ['transported' => 0, 'loss' => 0, 'cost' => 0]];
        foreach ($wells as $w) {
            $wellId = (int)($w['id'] ?? 0);
            $selectedTransport = (string)($w['transport_type'] ?? (($w['well_type'] ?? 'onshore') === 'offshore' ? 'tankowiec' : 'nieustawiony'));
            $effectiveTransport = $this->resolveEffectiveTransport(
                (string)($w['well_type'] ?? 'onshore'),
                (string)$selectedTransport,
                isset($this->ownedPipelineWellIds[$wellId]),
                isset($this->existingPipelineWellIds[$wellId])
            );
            $effectiveCfg = $this->transportConfig[$effectiveTransport] ?? TransportConfigService::getDefaults()['nieustawiony'];
            $stats = $this->calcStats(
                (float)$w['base_production_per_hour'],
                $effectiveTransport
            );
            $summary['wells'][] = [
                'id'           => $wellId,
                'well_type'    => $w['well_type'],
                'selected_transport' => $selectedTransport,
                'transport'    => $effectiveTransport,
                'capacity_pct' => (float)($effectiveCfg['capacity'] ?? 0.0),
                'transported'  => round($stats['transported'], 2),
                'loss'         => round($stats['loss'], 2),
                'cost'         => round($stats['cost'], 4),
            ];
            $summary['totals']['transported'] += $stats['transported'];
            $summary['totals']['loss']        += $stats['loss'];
            $summary['totals']['cost']        += $stats['cost'];
        }
        $summary['totals'] = array_map(fn($v) => round($v, 2), $summary['totals']);
        return $summary;
    }

 /**
 * @return array<int, true>
 */
    private function loadOwnedPipelineWellIds(): array
    {
        $rows = [];

        try {
            $stmt = $this->db->prepare(
                "SELECT wp.well_id
                   FROM well_pipelines wp
                   JOIN logistics_hub_assignments a
                     ON a.well_id = wp.well_id
                    AND a.status = 'active'
                   JOIN logistics_hubs h ON h.id = wp.hub_id
                  WHERE wp.player_id = ?
                    AND wp.status <> 'building'
                    AND a.hub_id = wp.hub_id
                    AND h.status NOT IN ('disabled', 'building')"
            );
            $stmt->execute([$this->playerId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[(int)($row['well_id'] ?? 0)] = true;
            }
        } catch (Throwable $e) {
            GameLog::error('LogisticsService', 'loadOwnedPipelineWellIds FAILED', $e, ['player_id' => $this->playerId]);
        }

        return $rows;
    }

 /**
 * @return array<int, true>
 */
    private function loadExistingPipelineWellIds(): array
    {
        $rows = [];

        try {
            $stmt = $this->db->prepare(
                "SELECT well_id
                   FROM well_pipelines
                  WHERE player_id = ?"
            );
            $stmt->execute([$this->playerId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[(int)($row['well_id'] ?? 0)] = true;
            }
        } catch (Throwable $e) {
            GameLog::error('LogisticsService', 'loadExistingPipelineWellIds FAILED', $e, ['player_id' => $this->playerId]);
        }

        return $rows;
    }

    private function resolveEffectiveTransport(string $wellType, string $selectedTransport, bool $hasPipeline, bool $hasAnyPipeline): string
    {
        if ($wellType === 'offshore') {
            return 'tankowiec';
        }

        if ($selectedTransport === 'nieustawiony' || $selectedTransport === '') {
            return 'nieustawiony';
        }

        if ($selectedTransport === 'rurociag' && !$hasAnyPipeline) {
            return 'nieustawiony';
        }

        if ($selectedTransport === 'rurociag' && !$hasPipeline) {
            return 'ciezarowki';
        }

        if ($selectedTransport === 'tankowiec') {
            return 'ciezarowki';
        }

        return $selectedTransport;
    }
}
