<?php

/**
 * FinancialStateSection detekcja kryzysu finansowego i zapis last_tick_at.
 * FinancialStateSection financial crisis detection and last_tick_at persistence.
 */
class FinancialStateSection
{
    private PDO      $db;
    private DateTime $now;

    public function __construct(PDO $db, DateTime $now)
    {
        $this->db  = $db;
        $this->now = $now;
    }

 /**
 * Ocenia stan finansowy gracza i aktualizuje financial_state / crisis_ticks.
 * Evaluates player financial state and updates financial_state / crisis_ticks.
 */
 /**
 * @param array<string, mixed> $playerData
 */
    public function process(
        int    $playerId,
        array  $playerData,
        float  $playerCash,
        float  $finRevenue,
        float  $finOpex,
        float  $finSalary,
        float  $finTransport,
        float  $finIncident,
        float  $finTax
    ): void {
        try {
            $cfgStmt = $this->db->prepare("
                SELECT `key`, `value` FROM well_config
                WHERE `key` IN (
                    'warning_cash_threshold','crisis_ticks_base',
                    'score_bonus_threshold','score_penalty_threshold'
                )
            ");
            $cfgStmt->execute();
            $cfgRows = $cfgStmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $warningThreshold      = (float)($cfgRows['warning_cash_threshold']  ?? 10000);
            $crisisTicksBase       = (int)($cfgRows['crisis_ticks_base']          ?? 48);
            $scoreBonusThreshold   = (int)($cfgRows['score_bonus_threshold']      ?? 1000);
            $scorePenaltyThreshold = (int)($cfgRows['score_penalty_threshold']    ?? 300);

            $playerCreditScore = (int)($playerData['credit_score'] ?? 50);
            $financialState    = $playerData['financial_state']    ?? 'normal';
            $crisisTicks       = (int)($playerData['crisis_ticks'] ?? 0);

            $crisisTicksLimit = $crisisTicksBase;
            if ($playerCreditScore > $scoreBonusThreshold)   $crisisTicksLimit += 24;
            if ($playerCreditScore < $scorePenaltyThreshold) $crisisTicksLimit -= 12;
            $crisisTicksLimit = max(12, $crisisTicksLimit);

            $tickNetProfit       = $finRevenue - ($finOpex + $finSalary + $finTransport + $finIncident + $finTax);
            $newFinancialState   = $financialState;
            $newCrisisTicks      = $crisisTicks;
            $newLastCrisisTickAt = $playerData['last_crisis_tick_at'] ?? null;
            $lastCrisisHour      = $newLastCrisisTickAt
                ? (int)floor(($this->now->getTimestamp() - strtotime($newLastCrisisTickAt)) / 3600)
                : 999;
            $canIncrementCrisis = ($lastCrisisHour >= 1);

            if ($playerCash <= 0 && $tickNetProfit < 0) {
                $newFinancialState = 'crisis';
                if ($canIncrementCrisis) {
                    $newCrisisTicks      = $crisisTicks + 1;
                    $newLastCrisisTickAt = $this->now->format('Y-m-d H:i:s');
                    GameLog::warn('tick', 'financial_crisis', [
                        'player_id'    => $playerId,
                        'cash'         => $playerCash,
                        'net_profit'   => round($tickNetProfit, 2),
                        'crisis_hours' => $newCrisisTicks,
                        'limit_hours'  => $crisisTicksLimit,
                        'credit_score' => $playerCreditScore,
                    ]);
                }
                if ($newCrisisTicks >= $crisisTicksLimit) {
                    $this->triggerBankruptcy($playerId, $newCrisisTicks, $playerCreditScore);
                    $newCrisisTicks = 0; $newLastCrisisTickAt = null; $newFinancialState = 'normal';
                }
            } elseif ($playerCash < $warningThreshold && $tickNetProfit < 0) {
                $newFinancialState = 'warning';
                $newCrisisTicks    = max(0, $crisisTicks - 1);
 // M5: Wyzeruj last_crisis_tick_at gdy crisis_ticks wroci do 0 w stanie warning (tak jak w else).
 // Bez tego gracz moze utknac z crisis_ticks=1 i niezerowym last_crisis_tick_at na granicy warning/normal,
 // blokujac reinicjalizacje kryzysu przez 1 godzine (canIncrementCrisis throttle).
 // M5: Clear last_crisis_tick_at when crisis_ticks reaches 0 in warning state (same as else branch).
 // Without this a player can be stuck with crisis_ticks=1 and stale last_crisis_tick_at,
 // blocking crisis re-entry for 1 hour (canIncrementCrisis throttle).
                $newLastCrisisTickAt = ($newCrisisTicks === 0) ? null : $newLastCrisisTickAt;
            } else {
                $newFinancialState   = 'normal';
                $newCrisisTicks      = max(0, $crisisTicks - 1);
                $newLastCrisisTickAt = ($newCrisisTicks === 0) ? null : $newLastCrisisTickAt;
            }

            $this->db->prepare(
                "UPDATE players SET financial_state = ?, crisis_ticks = ?, last_crisis_tick_at = ? WHERE id = ?"
            )->execute([$newFinancialState, $newCrisisTicks, $newLastCrisisTickAt, $playerId]);

        } catch (Throwable $e) {
            GameLog::error('tick', 'crisis_detection FAILED', $e, ['player_id' => $playerId]);
        }
    }

 /**
 * Zapisuje gotowke i last_tick_at gracza.
 * Saves player cash and last_tick_at timestamp.
 *
 * C3: Zapis przez totalCosts (suma ALL zamierzonych odliczen, bez przycianania do 0).
 * SQL: cash = GREATEST(0, cash - totalCosts) zamiast cash + delta.
 * Roznicowy delta (finalCash - initialCash) byl przycianany gdy koszty > initialCash,
 * co pozwalalo graczowi uniknac pelnej oplaty przy rownoczesnym przyroscie gotowki
 * (kredyt, sprzedaz) w oknie ticka: GREATEST(0, concurrent + delta) = concurrent (gratis).
 * Nowe podejscie: DB zawsze odlicza pelna kwote kosztow od aktualnego salda.
 * Gotowka przyjeta przez graczy rownolegla jest prawidlowo chroniona przez GREATEST(0,...).
 *
 * C3: Write via totalCosts (sum of ALL intended deductions, without in-memory 0-floor).
 * SQL: cash = GREATEST(0, cash - totalCosts) instead of cash + delta.
 * The differential delta (finalCash - initialCash) was clipped when costs > initialCash,
 * letting the player escape full liability on a concurrent cash increase (loan, oil sale)
 * during the tick window: GREATEST(0, concurrent + delta) = concurrent (free money).
 * New approach: DB always deducts the full cost sum from the current balance.
 * Concurrent cash received by the player is correctly protected by GREATEST(0,...).
 *
 * @param float $totalCosts suma ALL odliczen ticka (nieprzycieta) / sum of ALL tick deductions (unclipped)
 */
    public function saveCashAndTick(int $playerId, float $totalCosts): void
    {
        $this->db->prepare(
            "UPDATE players SET cash = GREATEST(0, cash - :totalCosts), last_tick_at = :now WHERE id = :pid"
        )->execute([
            ':totalCosts' => round($totalCosts, 4),
            ':now'        => $this->now->format('Y-m-d H:i:s'),
            ':pid'        => $playerId,
        ]);
    }

    private function triggerBankruptcy(int $playerId, int $crisisTicks, int $creditScore): void
    {
        if (!class_exists('BankruptcyService')) return;
        try {
            $bkSvc = new BankruptcyService($playerId);
            $bkSvc->ensureRecoveryMode();
            GameLog::error('tick', 'BANKRUPTCY TRIGGERED by crisis_hours', null, [
                'player_id'    => $playerId,
                'crisis_hours' => $crisisTicks,
                'credit_score' => $creditScore,
            ]);
        } catch (Throwable $e) {
            GameLog::error('tick', 'crisis bankruptcy trigger FAILED', $e, ['player_id' => $playerId]);
        }
    }
}
