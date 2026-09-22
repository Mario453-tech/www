<?php
require_once __DIR__ . '/PlayerTickBoundaryTrait.php';
require_once __DIR__ . '/PlayerTickProcessingTrait.php';

/**
 * PlayersSection fasada sekcji 5 ticka (v2, pelny podzial na podsekecje).
 * PlayersSection tick section 5 facade (v2, fully split into subsections).
*
 * Deleguje logike do: / Delegates logic to:
 * OfflineSection detekcja offline + freeze mode / offline detection + freeze mode
 * WellLoopSection petla odwiertow, produkcja, OPEX, transport / well loop, production, OPEX, transport
 * PipelineSection degradacja + eksplozje rurociagow / degradation + pipeline explosions
 * SpillSection skazenie powierzchniowe (overflow magazynu) / surface contamination (storage overflow)
 * FinancialStateSection crisis detection + zapis last_tick_at / crisis detection + last_tick_at save
 */
class PlayersSection
{
    use PlayerTickBoundaryTrait;
    use PlayerTickProcessingTrait;
 // Liczniki statystyk (eksponowane do TickStatsRepository) / Stat counters (exposed to TickStatsRepository)
    public int   $playersProcessed   = 0;
    public int   $wellsActive        = 0;
    public float $totalBbl           = 0.0;
    public float $totalRevenue       = 0.0;
    public float $totalOpex          = 0.0;
    public int   $disastersTriggered = 0;
    public int   $incidentsTriggered = 0;
    /** @var array<string,int> */
    public array $sectionTimingsMs = [];
    public int $slowestPlayerMs = 0;
    public int $slowestPlayerId = 0;
    public int $playersFetched = 0;
    public int $playersSkipped = 0;
    public int $playersRemainingEstimate = 0;
    public int $playersMissingStorage = 0;
    private int $playersFetchMs = 0;
    private int $activePlayersTotal = 0;

    private PDO      $db;
    private DateTime $now;
    private float    $oilPrice;
    private int      $maxPlayersPerRun;
 /** @var array<string, mixed> */
    private array    $gBalanceMults;

 /** @param array<string, mixed> $gBalanceMults */
    public function __construct(PDO $db, DateTime $now, float $oilPrice, array $gBalanceMults, int $maxPlayersPerRun = 500)
    {
        $this->db            = $db;
        $this->now           = $now;
        $this->oilPrice      = $oilPrice;
        $this->gBalanceMults = $gBalanceMults;
        $this->maxPlayersPerRun = max(1, $maxPlayersPerRun);
    }

    public function run(): void
    {
        $playersFetchStarted = microtime(true);
        try {
            $players = $this->fetchActivePlayers();
            $this->playersFetched = count($players);
            $this->activePlayersTotal = $this->countActivePlayers();
            $this->playersMissingStorage = $this->countPlayersMissingStorage();
            $this->playersRemainingEstimate = max(0, $this->activePlayersTotal - $this->playersFetched);
            GameLog::dbResult('tick', 'active players batch', $this->playersFetched);
            if ($this->playersRemainingEstimate > 0) {
                GameLog::info('tick', 'players batch limited', [
                    'limit' => $this->maxPlayersPerRun,
                    'remaining_estimate' => $this->playersRemainingEstimate,
                ]);
            }
            if ($this->playersMissingStorage > 0) {
                GameLog::warn('tick', 'players excluded because storage is missing', [
                    'count' => $this->playersMissingStorage,
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'player fetch FAILED', $e);
            $players = [];
        }
        $this->playersFetchMs = (int)round((microtime(true) - $playersFetchStarted) * 1000);
        $this->addSectionTiming('players_fetch', $this->playersFetchMs);

        foreach ($players as $playerData) {
            $playerStarted = microtime(true);
            try {
                $this->processPlayer($playerData);
            } catch (Throwable $e) {
                GameLog::error('tick', 'player loop FAILED', $e, ['player_id' => $playerData['id'] ?? null]);
 // Rollback wiszacej transakcji zeby nastepny gracz mogl zaczac.
 // Roll back any dangling transaction so the next player can begin one.
                if ($this->db->inTransaction()) {
                    try { $this->db->rollBack(); } catch (Throwable $re) {}
                }
            } finally {
                $playerDurationMs = (int)round((microtime(true) - $playerStarted) * 1000);
                $this->addSectionTiming('player_total', $playerDurationMs);
                if ($playerDurationMs > $this->slowestPlayerMs) {
                    $this->slowestPlayerMs = $playerDurationMs;
                    $this->slowestPlayerId = (int)($playerData['id'] ?? 0);
                }
            }
        }
        $this->playersRemainingEstimate = max(
            0,
            $this->activePlayersTotal - $this->playersProcessed - $this->playersSkipped
        );
    }

    /**
     * Fetches the oldest eligible players for this run.
     * Pobiera najstarszych kwalifikujacych sie graczy do tej rundy.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchActivePlayers(): array
    {
        $stmt = $this->db->prepare("
            SELECT id,
                   COALESCE(last_tick_at, '2000-01-01 00:00:00') AS last_tick_at,
                   cash,
                   COALESCE(financial_state, 'normal') AS financial_state,
                   COALESCE(crisis_ticks, 0)           AS crisis_ticks,
                   COALESCE(last_crisis_tick_at, NULL) AS last_crisis_tick_at,
                   COALESCE(credit_score, 50)          AS credit_score,
                   COALESCE(bankruptcy_status, 'none') AS bankruptcy_status,
                   last_active_at,
                   COALESCE(offline_mode, 0)           AS offline_mode,
                   offline_since
            FROM players p
            WHERE p.status != 'bankrupt'
              AND EXISTS (
                    SELECT 1
                      FROM storage s
                     WHERE s.player_id = p.id
              )
            ORDER BY p.last_tick_at ASC, p.id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $this->maxPlayersPerRun, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function countActivePlayers(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
              FROM players p
             WHERE p.status != 'bankrupt'
               AND EXISTS (
                    SELECT 1
                      FROM storage s
                     WHERE s.player_id = p.id
               )
        ");
        return max(0, (int)$stmt->fetchColumn());
    }

    private function countPlayersMissingStorage(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
              FROM players p
             WHERE p.status != 'bankrupt'
               AND NOT EXISTS (
                    SELECT 1
                      FROM storage s
                     WHERE s.player_id = p.id
               )
        ");
        return max(0, (int)$stmt->fetchColumn());
    }


    private function addSectionTiming(string $key, int $durationMs): void
    {
        $this->sectionTimingsMs[$key] = ($this->sectionTimingsMs[$key] ?? 0) + max(0, $durationMs);
    }

    private function markPlayerSkipped(int $playerId, string $reason, bool $advanceTick): void
    {
        $this->playersSkipped++;
        if ($advanceTick) {
            try {
                $stmt = $this->db->prepare('UPDATE players SET last_tick_at = :now WHERE id = :player_id');
                $stmt->execute([
                    'now' => $this->now->format('Y-m-d H:i:s'),
                    'player_id' => $playerId,
                ]);
            } catch (Throwable $e) {
                GameLog::error('tick', 'player skip timestamp update FAILED', $e, [
                    'player_id' => $playerId,
                    'reason' => $reason,
                ]);
            }
        }
        GameLog::info('tick', 'player skipped in batch', [
            'player_id' => $playerId,
            'reason' => $reason,
            'tick_advanced' => $advanceTick,
        ]);
    }


 /**
 * Applies the second transport leg (hub -> storage) to oil delivered this tick by a
 * time-based path (road trips / marine). Mirrors WellHubSection's synchronous handling,
 * reducing storage by leg-2 losses and charging leg-2 cost, while folding the result
 * into the shared finance accumulators.
 *
 * @param array<int, float> $deliveredByWell well_id => credited bbl
 * @param array<string, mixed> $hseBonus
 */
    private function applyOutboundLeg(
        array              $deliveredByWell,
        WellLoopSection    $wellLoop,
        OutboundLegService $svc,
        float              $currentStorage,
        float              &$playerCash,
        float              $deltaHours,
        array              $hseBonus
    ): float {
        if ($deliveredByWell === []) {
            return $currentStorage;
        }

        $mults = $wellLoop->outboundMults();

        foreach ($deliveredByWell as $wellId => $bbl) {
            $wellId = (int)$wellId;
            $bbl    = (float)$bbl;
            if ($bbl <= 0.001) {
                continue;
            }

            // Ryzyko polityczne regionu huba skaluje incydenty drogowe leg-2 (jak w WellHubSection).
            // The hub region's political risk scales leg-2 road incidents (as in WellHubSection).
            $res = $svc->compute(
                $wellLoop->outboundTypeFor($wellId),
                $wellLoop->outboundPipelineFor($wellId),
                $bbl,
                $this->oilPrice,
                $mults,
                $deltaHours,
                $hseBonus,
                $wellLoop->outboundPoliticalRiskFor($wellId)
            );
            // 'blocked' (uszkodzony rurociag leg-2) traktujemy jak 'direct': ta sciezka dotyczy
            // ropy juz FIZYCZNIE dostarczonej ciezarowkami/tankowcem (brak bufora hubu, w ktorym
            // moglaby czekac) — throttling do bufora robi tylko synchroniczny WellHubSection.
            // W praktyce nieosiagalne: odwierty bez huba nie maja rurociagu wylotowego (H7).
            // 'blocked' (damaged leg-2 pipeline) is treated like 'direct' here: this path handles
            // oil already PHYSICALLY delivered by truck/tanker (no hub buffer to wait in) — buffer
            // throttling is done only by the synchronous WellHubSection. Effectively unreachable:
            // hubless wells have no outbound pipeline (H7).
            if ($res['kind'] === 'direct' || $res['kind'] === 'blocked') {
                continue;
            }

            $lossBbl = (float)$res['loss_bbl'];
            if ($lossBbl > 0.001) {
                $lossVal = (float)$res['loss_value'];
                $currentStorage                  = max(0.0, $currentStorage - $lossBbl);
                $wellLoop->finBbl               -= $lossBbl;
                $wellLoop->deliveredBbl         -= $lossBbl;
                $wellLoop->finRevenue           -= $lossVal;
                $wellLoop->finLossBbl           += $lossBbl;
                $wellLoop->finLossValue         += $lossVal;
                $wellLoop->finOutboundLossBbl   += $lossBbl;
                $wellLoop->finOutboundLossValue += $lossVal;
            }

            $cost = (float)$res['cost'];
            if ($cost > 0.0) {
                $wellLoop->finTransport += $cost;
                $wellLoop->totalCosts   += $cost;
                $playerCash              = max(0.0, $playerCash - $cost);
            }

            GameLog::info('tick', 'outbound_leg_delivery', [
                'well_id'   => $wellId,
                'kind'      => $res['kind'],
                'bbl'       => round($bbl, 2),
                'lost_bbl'  => round($lossBbl, 2),
                'cost'      => $cost,
            ]);
        }

        return $currentStorage;
    }

 /**
 * Przekazuje dostarczona rope do huba, jesli odwiert ma aktywne przypisanie.
 * Sends delivered oil into the hub if the well has an active assignment.
 *
 * @param array<int, float> $deliveredByWell
 * @return array<int, float>
 */
    private function queueHubDeliveredInputs(array $deliveredByWell, WellLoopSection $wellLoop): array
    {
        $directByWell = [];
        foreach ($deliveredByWell as $wellId => $bbl) {
            $wellId = (int)$wellId;
            $bbl    = (float)$bbl;
            if ($bbl <= 0.001) {
                continue;
            }
            if (!$wellLoop->addDeliveredHubInput($wellId, $bbl)) {
                $directByWell[$wellId] = ($directByWell[$wellId] ?? 0.0) + $bbl;
            }
        }

        return $directByWell;
    }
}
