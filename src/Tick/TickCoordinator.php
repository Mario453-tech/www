<?php
declare(strict_types=1);

require_once __DIR__ . '/TickContext.php';
require_once __DIR__ . '/TickEngine.php';
require_once __DIR__ . '/TickModuleConfigRepository.php';
require_once __DIR__ . '/TickModuleScheduler.php';
require_once __DIR__ . '/TickRunResult.php';
require_once __DIR__ . '/TickStatsRepository.php';

final class TickCoordinator
{
    private const LOCK_NAME = 'oilcorp_tick';

    private bool $busy = false;
    private bool $lockError = false;
    private bool $previousIncomplete = false;
    private string $summary = '';

    public function __construct(private readonly PDO $db)
    {
    }

    public function wasBusy(): bool
    {
        return $this->busy;
    }

    public function hadLockError(): bool
    {
        return $this->lockError;
    }

    public function previousRunWasIncomplete(): bool
    {
        return $this->previousIncomplete;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function run(string $source, ?DateTimeInterface $now = null, bool $forceModules = false): TickRunResult
    {
        return $this->runInternal($source, $now, $forceModules, null);
    }

    public function runModule(string $moduleKey, string $source = 'admin_module', ?DateTimeInterface $now = null): TickRunResult
    {
        return $this->runInternal($source, $now, true, $moduleKey);
    }

    private function runInternal(
        string $source,
        ?DateTimeInterface $now,
        bool $forceModules,
        ?string $moduleKey
    ): TickRunResult
    {
        $now ??= new DateTimeImmutable();
        $startTime = microtime(true);
        $singleModuleRun = $moduleKey !== null;
        $ctx = new TickContext($this->db, $now, $source, $startTime);
        $ctx->bankNegAvailable = class_exists('BankNegotiationService');
        $ctx->bankruptcyAvailable = class_exists('BankruptcyService');
        if ($singleModuleRun) {
            $ctx->setNewPrice($this->fallbackOilPrice());
        }

        $result = new TickRunResult($ctx);

        $this->applyRuntimeLimits();
        if (!$this->acquireLock()) {
            $result->addConfigurationFailure($this->lockError ? 'Tick lock acquisition failed.' : 'Tick already running.');
            $this->summary = $this->lockError
                ? "Tick skipped: lock acquisition failed\n"
                : "Tick skipped: another run in progress\n";
            return $result->finish($ctx);
        }

        try {
            $this->previousIncomplete = $singleModuleRun ? false : $this->markInProgress();
            if (!$singleModuleRun && $this->previousIncomplete && class_exists('GameLog', false)) {
                GameLog::warn('tick', 'previous tick did not finish - possible data inconsistency');
            }

            $ctx->runSequence = $singleModuleRun ? $this->currentRunSequence() : $this->nextRunSequence();
            if (class_exists('GameLog', false)) {
                GameLog::info('tick', '== START ==', [
                    'time' => $now->format('Y-m-d H:i:s'),
                    'source' => $source,
                    'sequence' => $ctx->runSequence,
                    'module' => $moduleKey,
                ]);
            }

            $scheduler = new TickModuleScheduler(new TickModuleConfigRepository($this->db));
            $engine = new TickEngine(null, $scheduler);
            $result = $moduleKey === null
                ? $engine->runAll($ctx, $forceModules)
                : $engine->runOne($moduleKey, $ctx, null, true);

            if ($result->status === TickRunResult::STATUS_FAILED) {
                if (!$singleModuleRun && class_exists('GameLog', false)) {
                    GameLog::warn('tick', 'critical tick failure left tick_in_progress enabled for crash detection');
                }
                $this->summary = $this->buildFailureSummary($result);
                return $result->finish($ctx);
            }

            if ($singleModuleRun) {
                $this->summary = $this->buildModuleSummary($moduleKey, $result);
                return $result->finish($ctx);
            }

            $this->finishSuccessfulRun($ctx, $result);
            return $result->finish($ctx);
        } finally {
            $this->releaseLock();
        }
    }

    private function applyRuntimeLimits(): void
    {
        @set_time_limit(290);
        try {
            $this->db->exec('SET SESSION lock_wait_timeout = 60');
        } catch (Throwable) {
        }
    }

    private function acquireLock(): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT GET_LOCK(?, 0)');
            $stmt->execute([self::LOCK_NAME]);
            $gotLock = (int)$stmt->fetchColumn();
            if ($gotLock !== 1) {
                $this->busy = true;
                if (class_exists('GameLog', false)) {
                    GameLog::warn('tick', 'tick already running - skipping this run');
                }
                return false;
            }
            return true;
        } catch (Throwable $e) {
            $this->lockError = true;
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'GET_LOCK FAILED - tick aborted', $e);
            }
            return false;
        }
    }

    private function releaseLock(): void
    {
        try {
            $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([self::LOCK_NAME]);
        } catch (Throwable) {
        }
    }

    private function markInProgress(): bool
    {
        $stmt = $this->db->prepare("SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1");
        $stmt->execute(['tick_in_progress']);
        $previous = $stmt->fetchColumn();

        $this->upsertConfig('tick_in_progress', '1', 'Tick in progress - crash detection', 'system');
        return $previous !== false && (int)$previous === 1;
    }

    private function nextRunSequence(): int
    {
        $this->upsertConfig('tick_run_sequence', '0', 'Tick run sequence', 'system');
        $stmt = $this->db->prepare(
            "UPDATE well_config
                SET `value` = LAST_INSERT_ID(CAST(`value` AS UNSIGNED) + 1)
              WHERE `key` = ?"
        );
        $stmt->execute(['tick_run_sequence']);
        $sequence = (int)$this->db->query('SELECT LAST_INSERT_ID()')->fetchColumn();
        return max(1, $sequence);
    }

    private function currentRunSequence(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1");
            $stmt->execute(['tick_run_sequence']);
            $value = $stmt->fetchColumn();
            return max(1, (int)($value !== false ? $value : 1));
        } catch (Throwable) {
            return 1;
        }
    }

    private function fallbackOilPrice(): float
    {
        try {
            $stmt = $this->db->prepare("SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1");
            $stmt->execute(['last_tick_oil_price']);
            $value = $stmt->fetchColumn();
            if ($value !== false && (float)$value > 0.0) {
                return (float)$value;
            }
        } catch (Throwable) {
        }

        try {
            $stmt = $this->db->prepare('SELECT current_price FROM market_state WHERE id = ? LIMIT 1');
            $stmt->execute([1]);
            $value = $stmt->fetchColumn();
            if ($value !== false && (float)$value > 0.0) {
                return (float)$value;
            }
        } catch (Throwable) {
        }

        try {
            $stmt = $this->db->prepare('SELECT oil_price FROM market_state WHERE id = ? LIMIT 1');
            $stmt->execute([1]);
            $value = $stmt->fetchColumn();
            if ($value !== false && (float)$value > 0.0) {
                return (float)$value;
            }
        } catch (Throwable) {
        }

        return 70.0;
    }

    private function finishSuccessfulRun(TickContext $ctx, TickRunResult $result): void
    {
        $stats = $ctx->collectStats();
        $runs = $ctx->collectModuleRuns();
        $marketStats = $stats['market'] ?? [];
        $bankStats = $stats['bank'] ?? [];
        $playerStats = $stats['players'] ?? [];
        $contractsStats = $stats['contracts'] ?? [];

        $this->safeUpsertConfig(
            'last_system_tick_at',
            (string)$ctx->now->getTimestamp(),
            'Last system tick timestamp',
            'system',
            'last_system_tick_at save FAILED'
        );
        $this->safeUpsertConfig(
            'last_tick_oil_price',
            (string)$ctx->newPrice,
            'Last tick oil price fallback',
            'system',
            'last_tick_oil_price save FAILED'
        );
        try {
            $this->clearInProgress();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'tick_in_progress flag clear FAILED', $e);
            }
        }

        $durationMs = (int)round((microtime(true) - $ctx->startTime) * 1000);
        if ($durationMs > 60_000 && class_exists('GameLog', false)) {
            GameLog::warn('tick', 'SLOW TICK', ['duration_ms' => $durationMs, 'threshold_ms' => 60_000]);
        }

        try {
            (new TickStatsRepository($this->db))->save([
                'ran_at' => $ctx->now->format('Y-m-d H:i:s'),
                'tick_sequence' => $ctx->runSequence,
                'source' => $ctx->source,
                'duration_ms' => $durationMs,
                'oil_price' => $ctx->newPrice,
                'trend_name' => $marketStats['trend_name'] ?? null,
                'trend_new' => !empty($marketStats['trend_new']),
                'bank_interest_processed' => (int)($bankStats['interest_processed'] ?? 0),
                'bank_installments_processed' => (int)($bankStats['installments_processed'] ?? 0),
                'bank_negotiations_resolved' => (int)($bankStats['negotiations_resolved'] ?? 0),
                'bank_loan_decisions' => (int)($bankStats['loan_decisions'] ?? 0),
                'hr_recruitments_processed' => (int)($bankStats['hr_recruitments_processed'] ?? 0),
                'bankruptcy_processed' => (int)($bankStats['bankruptcy_processed'] ?? 0),
                'bankruptcy_recovered' => (int)($bankStats['bankruptcy_recovered'] ?? 0),
                'players_processed' => (int)($playerStats['players_processed'] ?? 0),
                'wells_active' => (int)($playerStats['wells_active'] ?? 0),
                'total_production_bbl' => round((float)($playerStats['total_production_bbl'] ?? 0.0), 4),
                'total_revenue_pln' => round((float)($playerStats['total_revenue_pln'] ?? 0.0), 2),
                'total_opex_pln' => round((float)($playerStats['total_opex_pln'] ?? 0.0), 2),
                'disasters_triggered' => (int)($playerStats['disasters_triggered'] ?? 0),
                'incidents_triggered' => (int)($playerStats['incidents_triggered'] ?? 0),
                'contracts_processed' => (int)($contractsStats['processed'] ?? 0),
                'contracts_revenue_pln' => round((float)($contractsStats['revenue'] ?? 0.0), 2),
                'contracts_penalties_pln' => round((float)($contractsStats['penalties'] ?? 0.0), 2),
                'module_stats_data' => $stats,
                'module_runs_data' => $runs,
            ]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'tick stats save FAILED', $e);
            }
        }

        $this->cleanup();
        $this->logEnd($ctx, $playerStats, $marketStats);
        $this->summary = $this->buildSuccessSummary($ctx, $playerStats, $marketStats);
    }

    private function cleanup(): void
    {
        $this->cleanupTickHistoryIfDue();

        $retentionDays = $this->configInt('incident_retention_days', 30, 1);
        $incidentRetentionDays = 3;
        try {
            $stmt = $this->db->prepare('DELETE FROM well_incidents WHERE created_at < NOW() - INTERVAL ? DAY');
            $stmt->bindValue(1, $incidentRetentionDays, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'incident_retention_cleanup FAILED', $e);
            }
        }


        try {
            $stmt = $this->db->prepare(
                'DELETE FROM failure_log WHERE resolved = 1 AND resolved_at < NOW() - INTERVAL ? DAY'
            );
            $stmt->bindValue(1, $incidentRetentionDays, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->db->prepare(
                "DELETE FROM industrial_disasters
                 WHERE status = 'resolved' AND resolved_at < NOW() - INTERVAL ? DAY"
            );
            $stmt->bindValue(1, $incidentRetentionDays, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'resolved_incident_retention_cleanup FAILED', $e);
            }
        }
        try {
            $stmt = $this->db->prepare(
                'DELETE FROM technical_notifications WHERE is_read = 1 AND created_at < NOW() - INTERVAL ? DAY'
            );
            $stmt->bindValue(1, $retentionDays, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'notif_retention_cleanup FAILED', $e);
            }
        }

        try {
            $oldUnread = $retentionDays * 2;
            $stmt = $this->db->prepare(
                'DELETE FROM technical_notifications WHERE is_read = 0 AND created_at < NOW() - INTERVAL ? DAY'
            );
            $stmt->bindValue(1, $oldUnread, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'notif_old_unread_cleanup FAILED', $e);
            }
        }

        try {
            (new FinancialTransactionService($this->db))->purgeTickAudit($retentionDays);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'bank_tick_audit_cleanup FAILED', $e);
            }
        }

        try {
            $stmt = $this->db->prepare(
                'DELETE FROM tick_module_run_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
            );
            $stmt->bindValue(1, $retentionDays, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'module_run_logs_cleanup FAILED', $e);
            }
        }
    }

    private function cleanupTickHistoryIfDue(): void
    {
        $keepDays = 2;
        $lastCleanupAt = $this->configString('tick_history_cleanup_at', '');
        if ($lastCleanupAt !== '') {
            try {
                $lastCleanup = new DateTimeImmutable($lastCleanupAt);
                if ($lastCleanup > new DateTimeImmutable("-{$keepDays} days")) {
                    return;
                }
            } catch (Throwable) {
            }
        }

        try {
            $statsDeleted = (new TickStatsRepository($this->db))->cleanup($keepDays);
            $logsDeleted = (new TickModuleConfigRepository($this->db))->cleanupLogs($keepDays);
            $this->safeUpsertConfig(
                'tick_history_cleanup_at',
                (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'Last tick history cleanup timestamp',
                'system',
                'tick_history_cleanup_at save FAILED'
            );
            if (class_exists('GameLog', false)) {
                GameLog::info('tick', 'tick history cleanup OK', [
                    'keep_days' => $keepDays,
                    'tick_stats_deleted' => $statsDeleted,
                    'module_logs_deleted' => $logsDeleted,
                ]);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', 'tick history cleanup FAILED', $e);
            }
        }
    }

    private function configInt(string $key, int $default, int $min): int
    {
        try {
            $stmt = $this->db->prepare('SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? max($min, (int)$value) : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    private function configString(string $key, string $default): string
    {
        try {
            $stmt = $this->db->prepare('SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1');
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? (string)$value : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    private function upsertConfig(string $key, string $value, string $label, string $category): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO well_config (`key`, `value`, `label`, `category`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([$key, $value, $label, $category]);
    }

    private function safeUpsertConfig(
        string $key,
        string $value,
        string $label,
        string $category,
        string $errorMessage
    ): void {
        try {
            $this->upsertConfig($key, $value, $label, $category);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('tick', $errorMessage, $e);
            }
        }
    }

    private function clearInProgress(): void
    {
        $this->upsertConfig('tick_in_progress', '0', 'Tick in progress - crash detection', 'system');
    }

    /**
     * @param array<string,mixed> $playerStats
     * @param array<string,mixed> $marketStats
     */
    private function logEnd(TickContext $ctx, array $playerStats, array $marketStats): void
    {
        if (!class_exists('GameLog', false)) {
            return;
        }
        GameLog::info('tick', '== END ==', [
            'price' => $ctx->newPrice,
            'trend' => $marketStats['trend_name'] ?? 'none',
            'players' => (int)($playerStats['players_processed'] ?? 0),
            'bbl' => round((float)($playerStats['total_production_bbl'] ?? 0.0), 2),
            'revenue' => round((float)($playerStats['total_revenue_pln'] ?? 0.0), 2),
            'disasters' => (int)($playerStats['disasters_triggered'] ?? 0),
        ]);
    }

    /**
     * @param array<string,mixed> $playerStats
     * @param array<string,mixed> $marketStats
     */
    private function buildSuccessSummary(TickContext $ctx, array $playerStats, array $marketStats): string
    {
        $trendName = (string)($marketStats['trend_name'] ?? '');
        $trendInfo = $trendName !== ''
            ? ' | Trend: ' . $trendName . (!empty($marketStats['trend_new']) ? ' [NOWY]' : '')
            : '';

        $statusText = $this->hasPartialModuleFailure($ctx) ? 'Tick PARTIAL' : 'Tick OK';

        return $statusText . ': ' . $ctx->now->format('Y-m-d H:i:s') . " | Cena: {$ctx->newPrice}\${$trendInfo}"
            . ' | Gracze: ' . (int)($playerStats['players_processed'] ?? 0)
            . ' | Bbl: ' . round((float)($playerStats['total_production_bbl'] ?? 0.0), 1) . "\n";
    }

    private function hasPartialModuleFailure(TickContext $ctx): bool
    {
        foreach ($ctx->collectModuleRuns() as $run) {
            if (($run['status'] ?? '') === TickRunResult::STATUS_FAILED) {
                return true;
            }
        }
        return false;
    }

    private function buildFailureSummary(TickRunResult $result): string
    {
        $lastError = $result->errors !== [] ? $result->errors[array_key_last($result->errors)] : null;
        $module = (string)($lastError['module'] ?? 'unknown');
        $message = (string)($lastError['message'] ?? 'unknown error');
        return "Tick failed: {$module}: {$message}\n";
    }

    private function buildModuleSummary(?string $moduleKey, TickRunResult $result): string
    {
        $key = $moduleKey ?? 'unknown';
        $run = $result->moduleRuns[$key] ?? null;
        $status = (string)($run['status'] ?? $result->status);
        $durationMs = (int)($run['duration_ms'] ?? $result->durationMs);
        return "Tick module {$key}: {$status} | {$durationMs} ms\n";
    }
}
