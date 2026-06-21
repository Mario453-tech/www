<?php
/**
 * TTS/TasksTrait.php
 * Technical tasks system - assignment, tick, effects, queue, cancellation.
 * System zadan technicznych - zlecanie, tick, efekty, kolejka, anulowanie.
 */
trait TTSTasksTrait
{
 // Zadania, ktore fizycznie wstrzymuja odwiert ("W naprawie") na czas pracy serwisanta.
 // Tasks that physically pause the well ("servicing") while the technician works.
    private const WELL_SERVICE_TASKS = ['well_maintenance', 'well_repair', 'blowout_control', 'reservoir_rehabilitation'];
 // Zadania, ktore fizycznie wstrzymuja rurociag ("W naprawie") na czas pracy serwisanta.
 // Tasks that physically pause the pipeline ("servicing") while the technician works.
    private const PIPELINE_SERVICE_TASKS = ['pipeline_maintenance', 'pipeline_repair'];

    public function getTasks(string $statusFilter = ''): array
    {
        $where = $statusFilter ? "AND tt.status = ?" : '';
        $stmt = $this->db->prepare("
            SELECT tt.*,
                   ts.first_name, ts.last_name, ts.spec_code, ts.spec_name, ts.skill_level,
                   w.location_name AS well_name,
                   h.name AS hub_name,
                   wp.name AS pipeline_name,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), tt.end_time)) AS seconds_remaining
            FROM technical_tasks tt
            JOIN technical_staff ts ON tt.staff_id = ts.id
            LEFT JOIN wells w ON tt.well_id = w.id
            LEFT JOIN logistics_hubs h ON tt.hub_id = h.id
            LEFT JOIN well_pipelines wp ON tt.pipeline_id = wp.id
            WHERE tt.player_id = ? {$where}
            ORDER BY tt.created_at DESC
            LIMIT 50
        ");
        $params = [$this->playerId];
        if ($statusFilter) $params[] = $statusFilter;
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getActiveTasks(): array
    {
        return $this->getTasks('in_progress');
    }

 // ZLECANIE ZADAN

 /**
 * Zlecenie zadania INZYNIEROWI.
 * Assign a task to an engineer.
 */
    public function assignTask(int $staffId, string $taskType, ?int $wellId = null, ?string $moduleType = null, ?int $hubId = null, ?int $pipelineId = null): array
    {
        $staff = $this->getStaffMember($staffId);
        if (!$staff) return ['success' => false, 'message' => t('technical.task_msg.staff_missing')];
        if ($staff['status'] === 'fired') return ['success' => false, 'message' => t('technical.task_msg.staff_fired')];

        $taskDef = self::getTaskDefinition($taskType);
        if (!$taskDef) return ['success' => false, 'message' => t('technical.task_msg.task_unknown')];

        if (!in_array($staff['spec_code'], $taskDef['assignable'])) {
            $allowed = implode(', ', array_map(fn($s) => (self::getSpecDefinition($s)['name'] ?? $s), $taskDef['assignable']));
            return ['success' => false, 'message' => t('technical.task_msg.task_wrong_specialist', [
                'allowed' => $allowed,
                'spec' => $staff['spec_name'],
            ])];
        }

        if ($taskDef['needs_well'] && !$wellId) {
            return ['success' => false, 'message' => t('technical.task_msg.task_requires_well')];
        }

        if (($taskDef['needs_hub'] ?? false) && !$hubId) {
            return ['success' => false, 'message' => t('technical.task_msg.task_requires_hub')];
        }

        if (($taskDef['needs_pipeline'] ?? false)) {
            if (!$pipelineId) {
                return ['success' => false, 'message' => t('technical.task_msg.task_requires_pipeline')];
            }
            $pipeStmt = $this->db->prepare("SELECT status FROM well_pipelines WHERE id = ? AND player_id = ? LIMIT 1");
            $pipeStmt->execute([$pipelineId, $this->playerId]);
            $pipeRow = $pipeStmt->fetch();
            if (!$pipeRow) {
                return ['success' => false, 'message' => t('technical.task_msg.pipeline_not_owned')];
            }
            if ($pipeRow['status'] === 'building') {
                return ['success' => false, 'message' => t('technical.task_msg.pipeline_unavailable', ['status' => $pipeRow['status']])];
            }
        } else {
            $pipelineId = null; // ignore stray pipeline id for non-pipeline tasks
        }

        if ($wellId && $taskDef['needs_well']) {
            $wellStmt = $this->db->prepare("SELECT status, paused_staff_reason FROM wells WHERE id = ? AND player_id = ? LIMIT 1");
            $wellStmt->execute([$wellId, $this->playerId]);
            $wellRow = $wellStmt->fetch();
            if (!$wellRow) {
                return ['success' => false, 'message' => t('technical.task_msg.well_not_owned')];
            }
            if ($wellRow['status'] === 'paused_staff') {
                $missing = $wellRow['paused_staff_reason'] ?? 'brak personelu';
                return [
                    'success' => false,
                    'message' => t('technical.task_msg.well_paused_staff', ['missing' => $missing]),
                ];
            }
            $isEmergency = $taskDef['emergency'] ?? false;
            if (!$isEmergency && in_array($wellRow['status'], ['seized', 'blowout'])) {
                return ['success' => false, 'message' => t('technical.task_msg.well_unavailable', ['status' => $wellRow['status']])];
            }
            if (in_array($wellRow['status'], ['sold', 'layer_switch', 'equipment_swap', 'seized'])) {
                return ['success' => false, 'message' => t('technical.task_msg.well_unavailable', ['status' => $wellRow['status']])];
            }
        }

        if ($hubId && ($taskDef['needs_hub'] ?? false)) {
            $hubStmt = $this->db->prepare("
                SELECT DISTINCT h.id
                FROM logistics_hubs h
                JOIN logistics_hub_assignments a ON a.hub_id = h.id AND a.status = 'active'
                JOIN wells w ON w.id = a.well_id
                WHERE h.id = ? AND w.player_id = ?
                LIMIT 1
            ");
            $hubStmt->execute([$hubId, $this->playerId]);
            if (!$hubStmt->fetch()) {
                return ['success' => false, 'message' => t('technical.task_msg.hub_not_used')];
            }
        }

        // Sprawdz zajetos pracownika tylko w ramach tego gracza — blokada miedzy graczami niedopuszczalna.
        // Check worker busy state only within this player — cross-player blocking is not allowed.
        $busyStmt = $this->db->prepare("SELECT id FROM technical_tasks WHERE staff_id = ? AND player_id = ? AND status = 'in_progress' LIMIT 1");
        $busyStmt->execute([$staffId, $this->playerId]);
        if ($busyStmt->fetch()) {
 // Atomowa kontrola duplikatu + wstawienie do kolejki — chroni przed race condition.
 // Atomic duplicate check + queue INSERT — guards against race condition between check and insert.
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) $this->db->beginTransaction();
            try {
 // Prevent duplicate queue entries for the same worker+task+target combination.
 // Zabezpieczenie przed duplikatami w kolejce dla tego samego pracownika i zadania.
 // Build null-safe conditions portably (MySQL <=> is not supported by SQLite).
                $dupConds  = ['staff_id = ?', 'task_type = ?'];
                $dupParams = [$staffId, $taskType];
                foreach (['well_id' => $wellId, 'hub_id' => $hubId, 'pipeline_id' => $pipelineId, 'module_type' => $moduleType] as $col => $val) {
                    if ($val === null) {
                        $dupConds[] = "$col IS NULL";
                    } else {
                        $dupConds[] = "$col = ?";
                        $dupParams[] = $val;
                    }
                }
                $dupStmt = $this->db->prepare(
                    "SELECT id FROM technical_task_queue WHERE " . implode(' AND ', $dupConds) . " LIMIT 1"
                );
                $dupStmt->execute($dupParams);
                if ($dupStmt->fetch()) {
                    if ($ownTx) $this->db->commit();
                    return ['success' => false, 'message' => t('technical.task_msg.already_queued')];
                }

                $this->db->prepare("
                    INSERT INTO technical_task_queue (player_id, staff_id, task_type, well_id, hub_id, pipeline_id, module_type)
                    VALUES (?,?,?,?,?,?,?)
                ")->execute([$this->playerId, $staffId, $taskType, $wellId, $hubId, $pipelineId, $moduleType]);
                if ($ownTx) $this->db->commit();
                return ['success' => true, 'message' => t('technical.task_msg.worker_busy_queued'), 'queued' => true];
            } catch (Throwable $e) {
                if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
                GameLog::error('TTS', 'assignTask queue FAILED', $e);
                return ['success' => false, 'message' => t('technical.task_msg.start_failed', ['error' => $e->getMessage()])];
            }
        }

        return $this->startTask($staffId, $taskType, $wellId, $moduleType, $staff, $hubId, $pipelineId);
    }

    private function startTask(int $staffId, string $taskType, ?int $wellId, ?string $moduleType, array $staff, ?int $hubId = null, ?int $pipelineId = null): array
    {
        $taskDef = self::getTaskDefinition($taskType);
        $manager = $this->getManager();
        $mBonus  = $this->getManagerBonus($manager);
        $sBonus  = $this->getStaffBonus($staff);

        $baseHours = rand($taskDef['hours_min'], $taskDef['hours_max']);
        $hours     = max(1, (int)round($baseHours * $mBonus['time_mult'] * $sBonus['time_mult']));

        // Klucz sesji blokujacy ponowne losowanie kosztu dla tej samej kombinacji zlecenia.
        // Session key locking re-rolls of cost for the same task combination.
        $quoteKey = 'tts_q_' . $staffId . '_' . $taskType . '_' . (int)$wellId . '_' . (int)$hubId . '_' . (int)$pipelineId;

        $moduleDef = $moduleType ? self::getModuleDefinition($moduleType) : null;

        // install_module ma staly koszt z konfiguracji (brak losowania) — pomijamy blokade sesji.
        // install_module has a fixed config cost (no roll) — skip session quote lock.
        $usesSessionQuote = $taskDef['cost_min'] > 0 && !($taskType === 'install_module' && $moduleDef !== null);

        if (
            $usesSessionQuote
            && isset($_SESSION[$quoteKey])
            && is_array($_SESSION[$quoteKey])
            && (int)($_SESSION[$quoteKey]['expires'] ?? 0) > time()
            && isset($_SESSION[$quoteKey]['cost'])
        ) {
            // Uzyj kosztu z sesji (wczesniej wylosowanego) — blokuje re-roll exploit.
            // Use session-stored cost (previously rolled) — prevents re-roll exploit.
            $cost = (int)$_SESSION[$quoteKey]['cost'];
        } else {
            $baseCost = $taskDef['cost_min'] > 0 ? rand($taskDef['cost_min'], $taskDef['cost_max']) : 0;
            $cost     = (int)round($baseCost * $mBonus['cost_mult'] * $sBonus['cost_mult']);
            if ($usesSessionQuote) {
                // Zapisz wylosowany koszt w sesji na 5 minut / Store rolled cost in session for 5 minutes
                $_SESSION[$quoteKey] = ['cost' => $cost, 'expires' => time() + 300];
            }
        }

        if ($taskType === 'install_module' && $moduleDef) {
            $cost = $moduleDef['cost'];
        }

        $wellName = '';
        if ($wellId) {
            $wStmt = $this->db->prepare("SELECT location_name FROM wells WHERE id = ?");
            $wStmt->execute([$wellId]);
            $w = $wStmt->fetch();
            $wellName = $w ? ' - ' . $w['location_name'] : ' - odwiert #' . $wellId;
        }
        $hubName = '';
        if ($hubId) {
            $hStmt = $this->db->prepare("SELECT name FROM logistics_hubs WHERE id = ? LIMIT 1");
            $hStmt->execute([$hubId]);
            $h = $hStmt->fetch();
            $hubName = $h ? ' - ' . $h['name'] : ' - hub #' . $hubId;
        }
        $moduleLabel = $moduleType ? (' (' . (($moduleDef['label'] ?? $moduleType)) . ')') : '';
        $title       = $taskDef['label'] . $moduleLabel . ($hubId ? $hubName : $wellName);
        $startTime   = date('Y-m-d H:i:s');
        $endTime     = date('Y-m-d H:i:s', time() + $hours * 3600);

        // FTS budowany przed transakcja, by setup schematu nie byl pominiety w otwartej transakcji.
        // Build FTS before the transaction so schema setup is not skipped inside an open transaction.
        // Constructing FTS before the transaction warms the schema; the instance itself is not used here.
        if ($cost > 0) { new FinancialTransactionService($this->db); }
        $this->db->beginTransaction();
        try {
            if ($cost > 0) {
                // Atomowe odliczenie gotowki — UPDATE powiedzie sie tylko gdy saldo wystarczajace.
                // Atomic cash deduction — UPDATE succeeds only when balance is sufficient.
                // Warunek AND cash >= ? zapobiega zejsciu salda ponizej zera (race-condition-safe).
                // Condition AND cash >= ? prevents balance going negative (race-condition-safe).
                $deductStmt = $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ? AND cash >= ?");
                $deductStmt->execute([$cost, $this->playerId, $cost]);
                if ($deductStmt->rowCount() === 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('technical.task_msg.no_funds', [
                        'cost' => number_format($cost, 0, '.', ' '),
                    ])];
                }
                try {
                    if (class_exists('FinancialTransactionService', false)) {
                        (new FinancialTransactionService($this->db))->logTransaction(
                            $this->playerId, null, $cost,
                            FinancialTransactionService::TYPE_TTS_FEE,
                            'Koszt zadania technicznego: ' . ($taskType ?? 'task')
                        );
                    }
                } catch (Throwable $le) { /* audit trail failure must not break the operation */ }
            }
            $this->db->prepare("UPDATE technical_staff SET status = 'busy' WHERE id = ?")->execute([$staffId]);
            $this->db->prepare("
                INSERT INTO technical_tasks
                    (player_id, staff_id, task_type, well_id, hub_id, pipeline_id, title, module_type,
                     start_time, end_time, duration_hours, cost, status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'in_progress')
            ")->execute([
                $this->playerId, $staffId, $taskType, $wellId, $hubId, $pipelineId, $title, $moduleType,
                $startTime, $endTime, $hours, $cost,
            ]);

 // Zamroz odwiert na czas fizycznego serwisu (status "W naprawie").
 // Freeze the well during physical service work (status "servicing").
            if ($wellId && in_array($taskType, self::WELL_SERVICE_TASKS, true)) {
                $this->db->prepare("
                    UPDATE wells
                    SET service_prev_status = status, status = 'servicing'
                    WHERE id = ? AND player_id = ?
                      AND status NOT IN ('seized','sold','layer_switch','equipment_swap','servicing')
                ")->execute([$wellId, $this->playerId]);
            }

 // Zamroz rurociąg na czas serwisu (status "W naprawie").
 // Freeze the pipeline during service work (status "servicing").
            if ($pipelineId && in_array($taskType, self::PIPELINE_SERVICE_TASKS, true)) {
                $this->db->prepare("
                    UPDATE well_pipelines
                    SET service_prev_status = status, status = 'servicing'
                    WHERE id = ? AND player_id = ?
                      AND status NOT IN ('building','servicing')
                ")->execute([$pipelineId, $this->playerId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            GameLog::error('TTS', 'startTask FAILED', $e);
            return ['success' => false, 'message' => t('technical.task_msg.start_failed', [
                'error' => $e->getMessage(),
            ])];
        }

        // Usun wycene z sesji po udanym zleceniu — kolejne zlecenie dolosuje koszt od nowa.
        // Remove session quote after successful assignment — next assignment re-rolls cost.
        if ($usesSessionQuote) {
            unset($_SESSION[$quoteKey]);
        }

        return [
            'success'  => true,
            'message'  => t('technical.task_msg.assigned', [
                'title' => $title,
                'hours' => $hours,
                'cost' => number_format($cost, 0, '.', ' '),
            ]),
            'hours'    => $hours,
            'end_time' => $endTime,
        ];
    }

 // TICK zakonczenie zadania i efekty

    public function processTick(): void
    {
        $stmt = $this->db->prepare("
            SELECT tt.*, ts.spec_code, ts.skill_level,
                   ts.first_name, ts.last_name
            FROM technical_tasks tt
            JOIN technical_staff ts ON tt.staff_id = ts.id
            WHERE tt.status = 'in_progress'
              AND tt.end_time <= NOW()
              AND tt.player_id = ?
        ");
        // Filtruj tylko zadania tego gracza — zapobiega przetwarzaniu cudzych zadan.
        // Filter to this player's tasks only — prevents processing other players' tasks.
        $stmt->execute([$this->playerId]);
        foreach ($stmt->fetchAll() as $task) {
            $this->completeTask($task);
        }
    }

 /**
 * Zastosuj efekty zakonczenia zadania.
 * Apply effects of a completed task.
 */
    public function completeTask(array $task): void
    {
        $taskId  = (int)$task['id'];
        $staffId = (int)$task['staff_id'];
        $wellId  = $task['well_id'] ? (int)$task['well_id'] : null;
        $hubId   = $task['hub_id'] ? (int)$task['hub_id'] : null;
        $pipeId  = !empty($task['pipeline_id']) ? (int)$task['pipeline_id'] : null;
        $pId     = (int)$task['player_id'];
        $skill   = (int)($task['skill_level'] ?? 5);
        $result  = [];
        $msg     = '';
        $next    = null;

 // Pojedyncza transakcja obejmuje unfreeze + efekty + zmiane statusu + kolejke.
 // Zapobiega podwojnemu wykonaniu: UPDATE WHERE status='in_progress' dostaje 0 wierszy
 // gdy inny tick juz zatwierdzil zmiane statusu — wtedy cofamy cala transakcje.
 // Single transaction covering unfreeze + effects + status change + queue.
 // Prevents double-execution: the status UPDATE matches 0 rows when another tick already
 // committed; we then roll back the entire transaction.
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
 // Wstepne sprawdzenie — szybki powrot bez efektow ubocznych gdy zadanie juz nie 'in_progress'.
 // Pre-check — quick return without side effects when the task is no longer 'in_progress'.
            $claimStmt = $this->db->prepare(
                "SELECT id FROM technical_tasks WHERE id = ? AND status = 'in_progress' LIMIT 1"
            );
            $claimStmt->execute([$taskId]);
            if (!$claimStmt->fetch()) {
 // Already completed by another concurrent tick — skip silently.
                if ($ownTx) $this->db->rollBack();
                return;
            }

 // Odmroz odwiert po serwisie (przywroc status sprzed "W naprawie"), o ile nie trwa
 // jeszcze inne zadanie serwisowe na tym odwiercie. Robione PRZED efektami zadania,
 // aby logika typu well_repair (broken -> active) widziala realny status.
 // Un-freeze the well after service (restore pre-"servicing" status) unless another
 // service task is still running on it. Done BEFORE task effects so logic like
 // well_repair (broken -> active) sees the real status.
            if ($wellId && in_array($task['task_type'], self::WELL_SERVICE_TASKS, true)) {
                if (!$this->hasOtherWellServiceTasks($wellId, $pId, $taskId)) {
                    $this->db->prepare("
                        UPDATE wells
                        SET status = COALESCE(service_prev_status, status), service_prev_status = NULL
                        WHERE id = ? AND player_id = ? AND status = 'servicing'
                    ")->execute([$wellId, $pId]);
                }
            }

 // Odmroz rurociag po serwisie (analogicznie do odwiertu).
 // Un-freeze the pipeline after service (analogous to the well).
            if ($pipeId && in_array($task['task_type'], self::PIPELINE_SERVICE_TASKS, true)) {
                if (!$this->hasOtherPipelineServiceTasks($pipeId, $pId, $taskId)) {
                    $this->db->prepare("
                        UPDATE well_pipelines
                        SET status = COALESCE(service_prev_status, status), service_prev_status = NULL
                        WHERE id = ? AND player_id = ? AND status = 'servicing'
                    ")->execute([$pipeId, $pId]);
                }
            }

 // Szansa
            $failed = ($skill <= 3) && (mt_rand(1, 100) <= (4 - $skill) * 8);

            if (!$failed) {
                switch ($task['task_type']) {

                    case 'well_maintenance':
                        if ($wellId) {
                            $gain = 20 + ($skill >= 7 ? 10 : 0);
                            $this->db->prepare("
                                UPDATE wells
                                SET technical_condition = LEAST(100, technical_condition + ?)
                                WHERE id = ? AND player_id = ?
                            ")->execute([$gain, $wellId, $pId]);
                            $result = ['condition_gain' => $gain];
                            $msg = t('technical.task_msg.well_maintenance_done', [
                                'well_id' => $wellId,
                                'title' => $task['title'],
                                'gain' => $gain,
                            ]);
                        }
                        break;

                    case 'well_repair':
                        if ($wellId) {
                            $this->db->prepare("
                                UPDATE wells
                                SET technical_condition = 100,
                                    status = CASE WHEN status IN ('broken','paused_cash') THEN 'active' ELSE status END
                                WHERE id = ? AND player_id = ?
                            ")->execute([$wellId, $pId]);
                            $result = ['condition' => 100, 'status' => 'active'];
                            $msg = t('technical.task_msg.well_repair_done', ['well_id' => $wellId]);
                        }
                        break;

                    case 'hub_maintenance':
                        if ($hubId) {
                            $gain = min(22, 12 + max(0, $skill - 4) * 2);
 // Dodaj player_id do WHERE — zapobiega modyfikacji hubu innego gracza.
 // Add player_id to WHERE — prevents modifying another player's hub.
                            $this->db->prepare("
                                UPDATE logistics_hubs
                                SET condition_pct = LEAST(100, condition_pct + ?),
                                    repair_cost_estimate = GREATEST(0, repair_cost_estimate - ?),
                                    last_maintenance_at = NOW(),
                                    updated_at = NOW()
                                WHERE id = ? AND player_id = ?
                            ")->execute([$gain, $gain * 25000, $hubId, $pId]);
                            $result = ['condition_gain' => $gain];
                            $msg = t('technical.task_msg.hub_maintenance_done', [
                                'hub_id' => $hubId,
                                'gain' => $gain,
                            ]);
                        }
                        break;

                    case 'hub_repair':
                        if ($hubId) {
                            $condition = min(100, 60 + $skill * 4);
 // Dodaj player_id do WHERE — zapobiega napraw hubu innego gracza.
 // Add player_id to WHERE — prevents repairing another player's hub.
                            $this->db->prepare("
                                UPDATE logistics_hubs
                                SET condition_pct = ?,
                                    status = CASE WHEN status IN ('damaged','disabled','paused') THEN 'active' ELSE status END,
                                    repair_cost_estimate = 0.00,
                                    last_maintenance_at = NOW(),
                                    updated_at = NOW()
                                WHERE id = ? AND player_id = ?
                            ")->execute([$condition, $hubId, $pId]);
                            $result = ['condition' => $condition, 'status' => 'active'];
                            $msg = t('technical.task_msg.hub_repair_done', ['hub_id' => $hubId]);
                        }
                        break;

                    case 'reservoir_analysis':
                        if ($wellId) {
                            if ($skill >= 7) {
                                $this->db->prepare("
                                    UPDATE wells SET pressure = LEAST(1.50, pressure + 0.05)
                                    WHERE id = ? AND player_id = ?
                                ")->execute([$wellId, $pId]);
                                $result = ['pressure_boost' => 0.05];
                            }
                            $wStmt = $this->db->prepare("SELECT reservoir_remaining, reservoir_max, pressure FROM wells WHERE id = ?");
                            $wStmt->execute([$wellId]);
                            $w      = $wStmt->fetch() ?: [];
                            $resPct = $w ? round(($w['reservoir_remaining'] / max(1, $w['reservoir_max'])) * 100, 1) : 0;
                            $msg = t('technical.task_msg.reservoir_analysis_done', [
                                'well_id' => $wellId,
                                'reservoir_pct' => $resPct,
                                'pressure' => round((float)($w['pressure'] ?? 1.0), 3),
                            ]);
                        }
                        break;

                    case 'production_optimization':
                        if ($wellId) {
                            $boost = 5 + ($skill >= 7 ? min(10, $skill - 5) * 2 : 0);
                            $this->db->prepare("
                                UPDATE wells
                                SET base_production_per_hour = CASE
                                        WHEN production_boost_pct < 50 THEN base_production_per_hour * (1 + ? / 100)
                                        ELSE base_production_per_hour
                                    END,
                                    production_boost_pct = LEAST(50, production_boost_pct + ?)
                                WHERE id = ? AND player_id = ?
                            ")->execute([$boost, $boost, $wellId, $pId]);
                            $result = ['production_boost_pct' => $boost];
                            $msg = t('technical.task_msg.production_optimization_done', [
                                'well_id' => $wellId,
                                'boost' => $boost,
                            ]);
                        }
                        break;

                    case 'install_module':
                        if ($wellId && $task['module_type']) {
                            $mod       = $task['module_type'];
                            $checkStmt = $this->db->prepare("SELECT id FROM well_upgrades WHERE well_id = ? AND upgrade_type = ?");
                            $checkStmt->execute([$wellId, $mod]);
                            if (!$checkStmt->fetch()) {
                                $this->db->prepare("INSERT INTO well_upgrades (well_id, upgrade_type, cost_paid) VALUES (?,?,?)")
                                         ->execute([$wellId, $mod, $task['cost']]);
                                if ($mod === 'pump_electric') {
                                    $this->db->prepare("UPDATE wells SET base_production_per_hour = base_production_per_hour * 1.20 WHERE id = ? AND player_id = ?")->execute([$wellId, $pId]);
                                } elseif ($mod === 'water_injection') {
                                    $this->db->prepare("UPDATE wells SET base_production_per_hour = base_production_per_hour * 1.10 WHERE id = ? AND player_id = ?")->execute([$wellId, $pId]);
                                } elseif ($mod === 'pressure_booster') {
                                    $this->db->prepare("UPDATE wells SET base_production_per_hour = base_production_per_hour * 1.15, pressure = LEAST(1.5, pressure + 0.1) WHERE id = ? AND player_id = ?")->execute([$wellId, $pId]);
                                }
                                $result = ['module' => $mod];
                            }
                            $modDef    = self::getModuleDefinition($mod) ?? [];
                            $modLabel  = $modDef['label']  ?? $mod;
                            $modEffect = $modDef['effect'] ?? '';
                            $msg = t('technical.task_msg.install_module_done', [
                                'module' => $modLabel,
                                'well_id' => $wellId,
                                'effect' => $modEffect,
                            ]);
                        }
                        break;

                    case 'pipeline_maintenance':
                    case 'pipeline_inspection':
                        if ($pipeId) {
                            $this->db->prepare("
                                UPDATE well_pipelines
                                SET transport_loss = GREATEST(0.5, transport_loss - 0.3),
                                    condition_pct  = LEAST(100, condition_pct + 10),
                                    status         = CASE
                                        WHEN status IN ('damaged', 'disabled', 'servicing') THEN status
                                        WHEN LEAST(100, condition_pct + 10) < 40 THEN 'critical'
                                        WHEN LEAST(100, condition_pct + 10) < 70 THEN 'degraded'
                                        ELSE 'active'
                                    END,
                                    last_inspected_at = NOW()
                                WHERE id = ? AND player_id = ?
                            ")->execute([$pipeId, $pId]);
                        }
                        $result = ['transport_loss_reduced' => 0.3];
                        $msg = t('technical.task_msg.pipeline_maintenance_done');
                        break;

                    case 'safety_audit':
                        $reduction = $skill >= 7 ? 3 : 2;
                        $this->db->prepare("
                            UPDATE wells SET risk_level = GREATEST(1, risk_level - ?)
                            WHERE player_id = ? AND status = 'active'
                        ")->execute([$reduction, $pId]);

                        $integrityBoost = 0;
                        if ($skill >= 6) {
                            $intStmt = $this->db->prepare("SELECT safety_procedures_level, procedure_integrity FROM players WHERE id = ?");
                            $intStmt->execute([$pId]);
                            $intRow = $intStmt->fetch();
                            if ($intRow && (int)(float)$intRow['safety_procedures_level'] > 0) {
                                $integrityBoost = $skill >= 8 ? 20 : 15;
                                $newInt = min(100.0, (float)$intRow['procedure_integrity'] + $integrityBoost);
                                $this->db->prepare("
                                    UPDATE players SET procedure_integrity = ?, procedures_last_decay_at = NOW()
                                    WHERE id = ?
                                ")->execute([$newInt, $pId]);
                            }
                        }

                        $msg = t('technical.task_msg.safety_audit_done', [
                            'reduction' => $reduction,
                        ]);
                        if ($integrityBoost > 0) {
                            $msg .= t('technical.task_msg.safety_audit_integrity', [
                                'integrity' => $integrityBoost,
                            ]);
                        }
                        break;

                    case 'blowout_control':
                        if ($wellId) {
                            $blowoutStmt = $this->db->prepare("
                                UPDATE wells
                                SET status = 'active', technical_condition = GREATEST(20, 35 + ? * 3)
                                WHERE id = ? AND player_id = ? AND status = 'blowout'
                            ");
                            $blowoutStmt->execute([$skill, $wellId, $pId]);
                            if ($blowoutStmt->rowCount() === 0) {
 // Odwiert nie jest juz w statusie 'blowout' — rownolegly task juz rozwiazal awarie.
 // Logujemy i przerywamy efekty, ale oznaczamy task jako 'completed'.
 // Well is no longer in 'blowout' — a concurrent task already resolved it.
 // Log and skip well effects but still mark task as 'completed'.
                                GameLog::error('TTS', 'blowout_control: well not in blowout state at completion — silent no-op (concurrent resolution)', null, [
                                    'task_id' => $taskId, 'well_id' => $wellId, 'player_id' => $pId,
                                ]);
                                $msg = t('technical.task_msg.blowout_control_done', ['well_id' => $wellId]);
                                break;
                            }
                            $this->db->prepare("
                                UPDATE industrial_disasters SET status = 'resolved', resolved_at = NOW()
                                WHERE player_id = ? AND well_id = ? AND disaster_type = 'blowout' AND status != 'resolved'
                            ")->execute([$pId, $wellId]);
                            $this->db->prepare("
                                UPDATE failure_log SET resolved = 1, resolved_at = NOW()
                                WHERE player_id = ? AND well_id = ? AND failure_type = 'blowout' AND resolved = 0
                            ")->execute([$pId, $wellId]);
                            $msg = t('technical.task_msg.blowout_control_done', ['well_id' => $wellId]);
                        }
                        break;

                    case 'pipeline_repair':
                        if ($pipeId) {
                            $this->db->prepare("
                                UPDATE well_pipelines
                                SET status = 'active',
                                    condition_pct = LEAST(100, 40 + ? * 5),
                                    transport_loss = GREATEST(0.5, transport_loss - 0.5),
                                    damaged_at = NULL,
                                    leak_started_at = NULL,
                                    last_inspected_at = NOW()
                                WHERE id = ? AND player_id = ?
                            ")->execute([$skill, $pipeId, $pId]);
                            $this->db->prepare("
                                UPDATE industrial_disasters SET status = 'resolved', resolved_at = NOW()
                                WHERE player_id = ? AND pipeline_id = ? AND disaster_type = 'pipeline_explosion' AND status != 'resolved'
                            ")->execute([$pId, $pipeId]);
                        }
                        $result = ['pipeline_repaired' => true];
                        $msg = t('technical.task_msg.pipeline_repair_done');
                        break;

                    case 'reservoir_rehabilitation':
                        if ($wellId) {
                            $boostPct = 10 + ($skill >= 7 ? 5 : 0);
                            $this->db->prepare("
                                UPDATE wells
                                SET base_production_per_hour = base_production_per_hour * (1 + ? / 100),
                                    status = CASE WHEN status = 'contaminated' THEN 'active' ELSE status END
                                WHERE id = ? AND player_id = ?
                                  AND status NOT IN ('seized', 'sold', 'blowout')
                            ")->execute([$boostPct, $wellId, $pId]);
                            $this->db->prepare("
                                UPDATE industrial_disasters SET status = 'resolved', resolved_at = NOW()
                                WHERE player_id = ? AND well_id = ? AND disaster_type = 'reservoir_contamination' AND status != 'resolved'
                            ")->execute([$pId, $wellId]);
                            $this->db->prepare("
                                UPDATE failure_log SET resolved = 1, resolved_at = NOW()
                                WHERE player_id = ? AND well_id = ? AND failure_type = 'reservoir_contamination' AND resolved = 0
                            ")->execute([$pId, $wellId]);
                            $result = ['production_restored_pct' => $boostPct];
                            $msg = t('technical.task_msg.reservoir_rehabilitation_done', [
                                'well_id' => $wellId,
                                'boost' => $boostPct,
                            ]);
                        }
                        break;

                    default:
                        $msg = t('technical.task_msg.task_done_generic', ['title' => $task['title']]);
                }
            } else {
                $msg = t('technical.task_msg.task_failed_generic', ['title' => $task['title']]);
            }

            $statusStmt = $this->db->prepare("
                UPDATE technical_tasks SET status = ?, result_data = ?, notified = 1
                WHERE id = ? AND status = 'in_progress'
            ");
            $statusStmt->execute([$failed ? 'failed' : 'completed', json_encode($result), $taskId]);
            if ($statusStmt->rowCount() === 0) {
 // Another concurrent tick already completed this task — roll back all effects.
                if ($ownTx) {
                    $this->db->rollBack();
                } else {
 // Wywolanie zagniezdzone (zewnetrzna transakcja): nie mozemy cofnac outer tx bezposrednio.
 // Rzucamy wyjatek — outer catch powinien zrobic rollback i nie zatwierdzac czesciowych efektow.
 // Nested call (outer transaction active): cannot rollback outer tx directly.
 // Throw so outer catch can rollback and not commit partial effects.
                    throw new \RuntimeException('completeTask: concurrent tick already completed task_id=' . $taskId . ' — aborting nested call');
                }
                return;
            }

            $this->db->prepare("UPDATE technical_staff SET status = 'active' WHERE id = ? AND player_id = ?")->execute([$staffId, $pId]);

            if (!empty($msg)) {
                $this->db->prepare("
                    INSERT INTO technical_notifications (player_id, well_id, type, message)
                    VALUES (?,?,?,?)
                ")->execute([$pId, $wellId, 'task', $msg]);
            }

 // Uruchom nastepne zadanie z kolejki dla tego pracownika.
 // Start the next queued task for this worker.
            $qStmt = $this->db->prepare("
                SELECT * FROM technical_task_queue
                WHERE staff_id = ? AND player_id = ? ORDER BY priority DESC, queued_at ASC LIMIT 1
            ");
            $qStmt->execute([$staffId, $pId]);
            $next = $qStmt->fetch();
            if ($next) {
                $this->db->prepare("DELETE FROM technical_task_queue WHERE id = ?")->execute([$next['id']]);
            }

            if ($ownTx) $this->db->commit();
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('TTS', 'completeTask FAILED', $e, ['task_id' => $taskId]);
            return;
        }

 // startTask() otwiera wlasna transakcje; wywolujemy go dopiero po zatwierdzeniu zewnetrznej.
 // startTask() opens its own transaction — call only after outer commit is done.
        if ($next) {
            if (!$ownTx) {
 // startTask() wywoluje beginTransaction() co powoduje implicit COMMIT zewnetrznej transakcji w MySQL.
 // Gdy jestesmy w outer tx, pomijamy startTask() — kolejne zadanie jest utracone, ale outer tx jest bezpieczna.
 // startTask() calls beginTransaction() which triggers an implicit COMMIT of an outer transaction in MySQL.
 // When nested in outer tx, skip startTask() — queued entry is lost but outer tx integrity is preserved.
                GameLog::error('TTS', 'completeTask: skipping queued startTask — nested in outer transaction, queue entry lost', null, [
                    'queue_id' => $next['id'], 'task_type' => $next['task_type'], 'staff_id' => $staffId,
                ]);
            } else {
                $staffRow = $this->getStaffMember($staffId);
                if ($staffRow) {
                    $startResult = $this->startTask($staffId, $next['task_type'], $next['well_id'], $next['module_type'], $staffRow, $next['hub_id'] ?? null, $next['pipeline_id'] ?? null);
                    if (!($startResult['success'] ?? false)) {
                        GameLog::error('TTS', 'completeTask: next queued startTask FAILED — queue entry permanently lost', null, [
                            'queue_id' => $next['id'], 'task_type' => $next['task_type'],
                            'staff_id' => $staffId, 'reason' => $startResult['message'] ?? 'unknown',
                        ]);
                    }
                }
            }
        }
    }

 // KOLEJKA zadan i podglad i anulowanie

    public function getQueue(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT q.*,
                       ts.first_name, ts.last_name, ts.spec_code,
                       COALESCE(hs.name, ts.spec_code) AS spec_name,
                       w.location_name AS well_name,
                       h.name AS hub_name
                FROM technical_task_queue q
                JOIN technical_staff ts ON ts.id = q.staff_id
                LEFT JOIN hr_specializations hs ON hs.code = ts.spec_code
                LEFT JOIN wells w ON w.id = q.well_id
                LEFT JOIN logistics_hubs h ON h.id = q.hub_id
                WHERE q.player_id = ?
                ORDER BY q.priority DESC, q.queued_at ASC
            ");
            $stmt->execute([$this->playerId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            GameLog::error('TTS', 'getQueue failed', $e, ['player_id' => $this->playerId]);
            return [];
        }
    }

    public function cancelTask(int $taskId): array
    {
        GameLog::step('TTS', 'cancelTask', 1, "task={$taskId}");
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, ts.first_name, ts.last_name
                FROM technical_tasks t
                JOIN technical_staff ts ON ts.id = t.staff_id
                WHERE t.id = ? AND t.player_id = ? AND t.status = 'in_progress'
                LIMIT 1
            ");
            $stmt->execute([$taskId, $this->playerId]);
            $task = $stmt->fetch();
            if (!$task) {
                return ['success' => false, 'message' => t('technical.task_msg.active_task_not_found')];
            }

            $this->db->beginTransaction();
 // Warunek AND status='in_progress' zapobiega nadpisaniu statusu 'completed' na 'cancelled'
 // gdy tick zakonczyl zadanie miedzy SELECT (linia powyzej) a BEGIN TRANSACTION.
 // AND status='in_progress' guard prevents overwriting 'completed' → 'cancelled' when the
 // tick completed the task between the SELECT above and this BEGIN TRANSACTION.
            $cancelStmt = $this->db->prepare("
                UPDATE technical_tasks SET status = 'cancelled', end_time = NOW()
                WHERE id = ? AND player_id = ? AND status = 'in_progress'
            ");
            $cancelStmt->execute([$taskId, $this->playerId]);
            if ($cancelStmt->rowCount() === 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => t('technical.task_msg.active_task_not_found')];
            }
            $this->db->prepare("
                UPDATE technical_staff SET status = 'active'
                WHERE id = ? AND player_id = ?
            ")->execute([$task['staff_id'], $this->playerId]);

 // Odmroz odwiert jezeli anulowane zadanie go serwisowalo (i brak innych serwisow).
 // Un-freeze the well if the cancelled task was servicing it (and no other service runs).
            if (!empty($task['well_id']) && in_array($task['task_type'], self::WELL_SERVICE_TASKS, true)) {
                if (!$this->hasOtherWellServiceTasks((int)$task['well_id'], $this->playerId, $taskId)) {
                    $this->db->prepare("
                        UPDATE wells
                        SET status = COALESCE(service_prev_status, status), service_prev_status = NULL
                        WHERE id = ? AND player_id = ? AND status = 'servicing'
                    ")->execute([(int)$task['well_id'], $this->playerId]);
                }
            }

 // Odmroz rurociag jezeli anulowane zadanie go serwisowalo (i brak innych serwisow).
 // Un-freeze the pipeline if the cancelled task was servicing it (and no other service runs).
            if (!empty($task['pipeline_id']) && in_array($task['task_type'], self::PIPELINE_SERVICE_TASKS, true)) {
                if (!$this->hasOtherPipelineServiceTasks((int)$task['pipeline_id'], $this->playerId, $taskId)) {
                    $this->db->prepare("
                        UPDATE well_pipelines
                        SET status = COALESCE(service_prev_status, status), service_prev_status = NULL
                        WHERE id = ? AND player_id = ? AND status = 'servicing'
                    ")->execute([(int)$task['pipeline_id'], $this->playerId]);
                }
            }

            // Pobierz nastepne zadanie z kolejki i usun je WEWNATRZ transakcji.
            // Fetch next queued task and DELETE it INSIDE the transaction.
            // startTask() otwiera wlasna transakcje — wywolujemy je dopiero PO commit().
            // startTask() opens its own transaction — call it only AFTER commit().
            $nextStmt = $this->db->prepare("
                SELECT * FROM technical_task_queue WHERE staff_id = ? AND player_id = ?
                ORDER BY priority DESC, queued_at ASC LIMIT 1
            ");
            $nextStmt->execute([$task['staff_id'], $this->playerId]);
            $nextTask = $nextStmt->fetch();
            if ($nextTask) {
                $this->db->prepare("DELETE FROM technical_task_queue WHERE id = ?")->execute([$nextTask['id']]);
            }

            $this->db->commit();
            GameLog::info('TTS', 'cancelTask OK', ['task_id' => $taskId, 'player_id' => $this->playerId]);

            // startTask() wywolujemy po commit() — PDO/MySQL nie obsluguje zagniezdzonnych transakcji.
            // startTask() is called after commit() — PDO/MySQL does not support nested transactions.
            if ($nextTask) {
                $staffRow = $this->getStaffMember((int)$task['staff_id']);
                if ($staffRow) {
                    $startResult = $this->startTask((int)$task['staff_id'], $nextTask['task_type'], $nextTask['well_id'], $nextTask['module_type'], $staffRow, $nextTask['hub_id'] ?? null, $nextTask['pipeline_id'] ?? null);
                    if (!($startResult['success'] ?? false)) {
                        GameLog::error('TTS', 'cancelTask: next queued startTask FAILED — queue entry permanently lost', null, [
                            'queue_id' => $nextTask['id'], 'task_type' => $nextTask['task_type'],
                            'staff_id' => $task['staff_id'], 'reason' => $startResult['message'] ?? 'unknown',
                        ]);
                    }
                }
            }

            return ['success' => true, 'message' => t('technical.task_msg.task_cancelled')];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('TTS', 'cancelTask FAILED', $e, ['task_id' => $taskId]);
            return ['success' => false, 'message' => t('technical.task_msg.cancel_task_failed')];
        }
    }

 // -----------------------------------------------------------------------
 // Pomocnicze metody prywatne / Private helper methods
 // -----------------------------------------------------------------------

 /**
 * Sprawdza czy dla danego odwiertu istnieja inne (nie ta sama) zadania serwisowe w toku.
 * Lista taskow pochodzi ze stalej WELL_SERVICE_TASKS — zmiana stalej automatycznie aktualizuje zapytanie.
 * Checks whether other (not this) in-progress well-service tasks exist for the given well.
 * Task list comes from WELL_SERVICE_TASKS constant — changing it automatically updates the query.
 */
    private function hasOtherWellServiceTasks(int $wellId, int $playerId, int $excludeId): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::WELL_SERVICE_TASKS), '?'));
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM technical_tasks
            WHERE well_id = ? AND player_id = ? AND status = 'in_progress'
              AND id <> ? AND task_type IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$wellId, $playerId, $excludeId], self::WELL_SERVICE_TASKS));
        return (int)$stmt->fetchColumn() > 0;
    }

 /**
 * Sprawdza czy dla danego rurociagu istnieja inne (nie ta sama) zadania serwisowe w toku.
 * Lista pochodzi ze stalej PIPELINE_SERVICE_TASKS.
 * Checks whether other in-progress pipeline-service tasks exist for the given pipeline.
 */
    private function hasOtherPipelineServiceTasks(int $pipeId, int $playerId, int $excludeId): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::PIPELINE_SERVICE_TASKS), '?'));
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM technical_tasks
            WHERE pipeline_id = ? AND player_id = ? AND status = 'in_progress'
              AND id <> ? AND task_type IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$pipeId, $playerId, $excludeId], self::PIPELINE_SERVICE_TASKS));
        return (int)$stmt->fetchColumn() > 0;
    }

    public function cancelQueueItem(int $queueId): array
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM technical_task_queue WHERE id = ? AND player_id = ?
            ");
            $stmt->execute([$queueId, $this->playerId]);
            // Jesli rowCount=0, element zostal juz przetworzony przez tick — traktujemy jako sukces (idempotentne).
            // If rowCount=0, the item was already processed by the tick — treat as success (idempotent).
            if ($stmt->rowCount() === 0) {
                GameLog::info('TTS', 'cancelQueueItem: already gone', ['queue_id' => $queueId, 'player_id' => $this->playerId]);
            } else {
                GameLog::info('TTS', 'cancelQueueItem OK', ['queue_id' => $queueId, 'player_id' => $this->playerId]);
            }
            return ['success' => true, 'message' => t('technical.task_msg.queue_item_removed')];
        } catch (Throwable $e) {
            GameLog::error('TTS', 'cancelQueueItem FAILED', $e, ['queue_id' => $queueId]);
            return ['success' => false, 'message' => t('technical.task_msg.cancel_queue_failed')];
        }
    }
}
