<?php
declare(strict_types=1);

require_once __DIR__ . '/PlayerTickTransaction.php';
require_once __DIR__ . '/PlayerTickSchemaCheck.php';

trait PlayerTickBoundaryTrait
{
    /** @param array<string, mixed> $playerData */
    private function processPlayer(array $playerData): void
    {
        // Constructors may prepare schemas: keep them outside the player transaction.
        // Konstruktory moga przygotowywac schemat: pozostaja poza transakcja gracza.
        $wellService = new WellService();
        Database::addColumnIfMissing('wells', 'paused_staff_prev_status', 'VARCHAR(32) NULL DEFAULT NULL');
        if (class_exists('DirectorNotificationService')) {
            new DirectorNotificationService();
        }
        $services = [
            'well' => $wellService,
            'technical' => new TechnicalTeamService((int) $playerData['id']),
            'offline' => new OfflineSection($this->db, $this->now),
            'pipelines' => new PipelineSection($this->db, $this->now, $wellService),
            'well_loop' => new WellLoopSection($this->db, $this->now, $this->oilPrice, $this->gBalanceMults, $wellService),
            'protection' => class_exists('ProtectionService') ? new ProtectionService($this->db) : null,
            'sabotage' => class_exists('SabotageService') ? new SabotageService($this->db) : null,
            'road' => class_exists('RoadTransportService') ? new RoadTransportService($this->db) : null,
            'finance' => new FinanceService(),
            'transactions' => new FinancialTransactionService($this->db),
        ];
        PlayerTickSchemaCheck::assertTransactional($this->db);
        $counters = [$this->playersProcessed, $this->playersSkipped, $this->wellsActive,
            $this->totalBbl, $this->totalRevenue, $this->totalOpex,
            $this->disastersTriggered, $this->incidentsTriggered];
        try {
            (new PlayerTickTransaction($this->db))->run((int) $playerData['id'], function (array $lockedPlayer) use ($services): void {
                $this->processLockedPlayer($lockedPlayer, $services);
            });
        } catch (Throwable $e) {
            [$this->playersProcessed, $this->playersSkipped, $this->wellsActive,
                $this->totalBbl, $this->totalRevenue, $this->totalOpex,
                $this->disastersTriggered, $this->incidentsTriggered] = $counters;
            throw $e;
        }
    }
}
