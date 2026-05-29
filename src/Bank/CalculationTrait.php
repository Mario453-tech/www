<?php

/**
 * Provides credit calculations for loans and limits.
 * Dostarcza obliczenia kredytowe dla rat i limitow.
 */
trait BankCalculationTrait
{
 /**
 * Calculates annuity installment amount.
 * Oblicza wysokosc raty annuitetowej.
 *
 * Formula:
 * R = P * r * (1+r)^n / ((1+r)^n - 1)
 * Formula:
 * R = P * r * (1+r)^n / ((1+r)^n - 1)
 *
 * P = principal
 * r = periodic rate based on APR and installment frequency
 * n = installment count
 * P = kwota kredytu
 * r = stopa okresowa na bazie APR i czestotliwosci rat
 * n = liczba rat
 *
 * For near-zero rate, fallback to P / n.
 * Dla prawie zerowej stopy uzyj fallbacku P / n.
 */
    public static function calculateAnnuityInstallment(
        float $principal,
        float $apr,
        int $nInstallments,
        int $freqHours = 12
    ): float {
        if ($nInstallments <= 0) {
            $nInstallments = 1;
        }
        $periodsPerYear = 8760.0 / $freqHours;
        $r = ($apr / 100.0) / $periodsPerYear;

        if ($r < 1e-10) {
            return round($principal / $nInstallments, 2);
        }

        $factor = pow(1 + $r, $nInstallments);
        $installment = $principal * $r * $factor / ($factor - 1);
        return round($installment, 2);
    }

 /**
 * Calculates the maximum credit limit for a player.
 * Oblicza maksymalny limit kredytowy gracza.
 *
 * The limit is based on:
 * - well asset value
 * - annual production value
 * - cash reserve contribution
 * - storage inventory contribution
 * Limit bazuje na:
 * - wartosci odwiertow
 * - wartosci rocznej produkcji
 * - wkladzie gotowki
 * - wkladzie zapasu w magazynie
 *
 * The result is capped and may be reduced for recovered bankruptcy state.
 * Wynik jest ograniczony capem i moze byc obnizony po recovered bankruptcy.
 */
    public function calculateCreditLimit(int $playerId, array $player, array $wellsData): int
    {
        try {
            $avgCondition = max(0.0, min(100.0, (float)($wellsData['avg_condition'] ?? 100.0)));
            $conditionFactor = max(0.10, $avgCondition / 100.0);

            $onshoreVal = (int)$wellsData['onshore_cnt'] * 8_000_000 * 0.5;
            $offshoreVal = (int)$wellsData['offshore_cnt'] * 40_000_000 * 0.5;
            $wellsValue = (int)round(($onshoreVal + $offshoreVal) * $conditionFactor);

            $priceRow = $this->db->query("SELECT current_price FROM market_state WHERE id = 1 LIMIT 1")->fetch();
            $oilPrice = $priceRow ? (float)$priceRow['current_price'] : 70.0;
            $prodPerH = (float)$wellsData['total_prod'];
            $annualRev = $prodPerH * 24 * 365 * $oilPrice;
            $prodValue = $annualRev * 0.15;

            $cash = (float)($player['cash'] ?? 0);
            $cashValue = max(0, $cash * 0.30);

            $storStmt = $this->db->prepare("SELECT used FROM storage WHERE player_id = :pid LIMIT 1");
            $storStmt->execute([':pid' => $playerId]);
            $storage = $storStmt->fetch();
            $storValue = $storage ? (float)$storage['used'] * $oilPrice * 0.20 : 0;

            $baseLimit = (int)round($wellsValue + $prodValue + $cashValue + $storValue);

            // Liquidity penalty: cash = 0 means no ability to service debt.
            // Bank reduces offer proportionally to how little cash the player has vs asset base.
            // Kara za brak płynności: bank obniża limit gdy gotówka jest niska względem aktywów.
            $liquidityRatio = $baseLimit > 0 ? $cash / $baseLimit : 0.0;
            $liquidityMult  = match (true) {
                $cash <= 0              => 0.10,  // brak gotówki → 10% limitu aktywowego
                $liquidityRatio < 0.02  => 0.20,  // < 2% → 20%
                $liquidityRatio < 0.05  => 0.40,  // < 5% → 40%
                $liquidityRatio < 0.10  => 0.65,  // < 10% → 65%
                $liquidityRatio < 0.20  => 0.85,  // < 20% → 85%
                default                 => 1.00,
            };
            $baseLimit = (int)round($baseLimit * $liquidityMult);

            $cap = 150_000_000;
            $limit = min($baseLimit, $cap);

            if (($player['bankruptcy_status'] ?? 'none') === 'recovered') {
                $limit = (int)round($limit * 0.5);
            }

            $limit = max(10_000, $limit);

            GameLog::info('BankService', 'calculateCreditLimit', [
                'player_id'        => $playerId,
                'avg_condition'    => round($avgCondition, 1),
                'condition_factor' => round($conditionFactor, 4),
                'wells_value'      => $wellsValue,
                'prod_value'       => (int)$prodValue,
                'cash_value'       => (int)$cashValue,
                'stor_value'       => (int)$storValue,
                'liquidity_ratio'  => round($liquidityRatio, 4),
                'liquidity_mult'   => $liquidityMult,
                'base_limit'       => $baseLimit,
                'final_limit'      => $limit,
                'oil_price'        => $oilPrice,
            ]);

            return $limit;
        } catch (Throwable $e) {
            GameLog::error('BankService', 'calculateCreditLimit FAILED', $e, ['player_id' => $playerId]);
            return 5_000_000;
        }
    }
}
