<?php
declare(strict_types=1);

trait TickSettlementTrait
{
    /**
     * Debit available cash and audit only the amount actually paid.
     * Obciaz dostepna gotowke i zapisz tylko faktycznie pobrana kwote.
     *
     * @param array<string, float> $costs Ordered cost categories / Uporzadkowane kategorie kosztow.
     * @return array{charged: float, cash_after: float}
     */
    public function settleTickCosts(int $playerId, string $tickAt, array $costs, ?float $totalCosts = null): array
    {
        $centsByType = [];
        foreach ($costs as $type => $amount) {
            if (!in_array($type, self::TICK_AUDIT_TYPES, true)
                || !is_finite($amount) || $amount < 0 || $amount > PHP_INT_MAX / 100) {
                throw new InvalidArgumentException('Invalid tick cost');
            }
            $centsByType[$type] = (int) round($amount * 100);
        }
        $totalCosts ??= array_sum($costs);
        if (!is_finite($totalCosts) || $totalCosts < 0 || $totalCosts > PHP_INT_MAX / 100) {
            throw new InvalidArgumentException('Invalid tick total');
        }
        $unit = $this->beginUnit();
        try {
            $labels = [self::TYPE_TAX => 'bank.tx_tick_tax', self::TYPE_TICK_OPEX => 'bank.tx_tick_opex',
                self::TYPE_HUB_USAGE => 'bank.tx_tick_hub_usage', self::TYPE_TICK_SALARY => 'bank.tx_tick_salary',
                self::TYPE_TICK_TRANSPORT => 'bank.tx_tick_transport', self::TYPE_TICK_INCIDENT => 'bank.tx_tick_incident'];
            $cash = $this->lockAndReadBalance($playerId);
            if ($cash === null) {
                throw new RuntimeException('Tick player not found');
            }
            $remaining = max(0, (int) round($cash * 100));
            $budget = min($remaining, (int) round($totalCosts * 100));
            // Legacy accumulators can contain only paid costs; retain the requested total.
            // Stare akumulatory moga zawierac tylko pokryte koszty; zachowaj pelna sume.
            $centsByType[self::TYPE_TICK_OPEX] = ($centsByType[self::TYPE_TICK_OPEX] ?? 0)
                + max(0, (int) round($totalCosts * 100) - array_sum($centsByType));
            $charged = 0;
            foreach ($centsByType as $type => $requested) {
                $paid = min($budget, $requested);
                if ($paid === 0) {
                    continue;
                }
                if ($this->logTransaction($playerId, null, $paid / 100, $type,
                    tPlain($labels[$type]), 'tick', null) === null) {
                    throw new RuntimeException('Tick audit write failed');
                }
                $remaining -= $paid;
                $budget -= $paid;
                $charged += $paid;
            }
            $stmt = $this->db->prepare('UPDATE players SET cash = ?, last_tick_at = ? WHERE id = ?');
            $stmt->execute([$remaining / 100, $tickAt, $playerId]);
            $this->commitUnit($unit);
            return ['charged' => (float) ($charged / 100), 'cash_after' => (float) ($remaining / 100)];
        } catch (Throwable $e) {
            $this->rollbackUnit($unit);
            throw $e;
        }
    }
}
