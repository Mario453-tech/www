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
            // seized is always blocked; blowout is blocked EXCEPT for blowout_control
            // (the task exists specifically to fix a well in blowout state).
            $blocked = $wellRow['status'] === 'seized'
                || ($wellRow['status'] === 'blowout' && $taskType !== 'blowout_control');
            if ($blocked) {
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
            // Filtruj po player_id — zapobiega wyciekiem nazwy odwiertu innego gracza (Rule 1).
            // Filter by player_id — prevents leaking another player's well name (Rule 1).
            $wStmt = $this->db->prepare("SELECT location_name FROM wells WHERE id = ? AND player_id = ?");
            $wStmt->execute([$wellId, $this->playerId]);
            $w = $wStmt->fetch();
            $wellName = $w ? ' - ' . $w['location_name'] : ' - odwiert #' . $wellId;
        }
        $hubName = '';
        if ($hubId) {
            // Filtruj po player_id — zapobiega wyciekiem nazwy huba innego gracza (Rule 1).
            // Filter by player_id — prevents leaking another player's hub name (Rule 1).
            $hStmt = $this->db->prepare("SELECT name FROM logistics_hubs WHERE id = ? AND player_id = ? LIMIT 1");
            $hStmt->execute([$hubId, $this->playerId]);
            $h = $hStmt->fetch();
            $hubName = $h ? ' - ' . $h['name'] : ' - hub #' . $hubId;
        }
        $moduleLabel = $moduleType ? (' (' . (($moduleDef['label'] ?? $moduleType)) . ')') : '';
        $title       = $taskDef['label'] . $moduleLabel . ($hubId ? $hubName : $wellName);

        // start_time / end_time zapisujemy zegarem BAZY (NOW()) — spojnie z porownaniem
        // end_time <= NOW() w processTick. Zapis zegarem PHP przy odczycie NOW() z MySQL
        // powodowal, ze przy roznicy stref PHP vs MySQL zadanie konczylo sie natychmiast
        // (pieniadze pobrane, zadanie nigdy nie widoczne jako "w toku") — regula #14.
        // Write start_time / end_time on the DB clock (NOW()) — consistent with the
        // end_time <= NOW() check in processTick. Writing them on the PHP clock while comparing
        // against MySQL NOW() made the task finish instantly under a PHP/MySQL timezone skew
        // (money charged, task never shown as in-progress) — rule #14.
        $isSqlite  = false;
        try {
            $isSqlite = ((string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
        } catch (Throwable) {
        }
        $endSql   = $isSqlite ? "datetime(NOW(), ?)" : "DATE_ADD(NOW(), INTERVAL ? HOUR)";
        $endParam = $isSqlite ? ('+' . $hours . ' hours') : $hours;
        // Tylko do komunikatu zwrotnego / for the return message only.
        $endTime  = date('Y-m-d H:i:s', time() + $hours * 3600);

        // FTS budowany przed transakcja, by setup schematu nie byl pominiety w otwartej transakcji.
        // Build FTS before the transaction so schema setup is not skipped inside an open transaction.
        $fts = ($cost > 0) ? new FinancialTransactionService($this->db) : null;
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
                    if ($fts !== null) {
                        $fts->logTransaction(
                            $this->playerId, null, $cost,
                            FinancialTransactionService::TYPE_TTS_FEE,
                            'Koszt zadania technicznego: ' . ($taskType ?? 'task')
                        );
                    }
                } catch (Throwable $le) { /* audit trail failure must not break the operation */ }
            }
            $this->db->prepare("UPDATE technical_staff SET status = 'busy' WHERE id = ? AND player_id = ?")->execute([$staffId, $this->playerId]);
            $this->db->prepare("
                INSERT INTO technical_tasks
                    (player_id, staff_id, task_type, well_id, hub_id, pipeline_id, title, module_type,
                     start_time, end_time, duration_hours, cost, status)
                VALUES (?,?,?,?,?,?,?,?, NOW(), {$endSql}, ?,?, 'in_progress')
            ")->execute([
                $this->playerId, $staffId, $taskType, $wellId, $hubId, $pipelineId, $title, $moduleType,
                $endParam, $hours, $cost,
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
        } catch (Exception $e) {
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

 // Wynik (sukces/porazka) losujemy ZANIM zajmiemy zadanie, by zajac je od razu statusem koncowym.
 // Roll the success/failure outcome BEFORE claiming, so the claim sets the final status directly.
        $failed = ($skill <= 3) && (mt_rand(1, 100) <= (4 - $skill) * 8);

 // Atomowe zajecie zadania PRZED jakimkolwiek efektem: in_progress -> status koncowy.
 // Atomic claim BEFORE any effect: flip in_progress -> final status.
 // Dwa rownolegle processTick (widok strony + tick w tle) moga wybrac to samo zadanie;
 // tylko jeden wygra ten UPDATE (rowCount=1), reszta dostaje rowCount=0 i konczy bez efektow,
 // wiec efekty (wzrost produkcji, kondycja) nigdy nie naliczaja sie dwa razy.
 // Two concurrent processTick runs (page view + background tick) may pick the same task;
 // only one wins this UPDATE (rowCount=1), the rest get rowCount=0 and bail before any effect,
 // so gameplay effects (production boost, condition gain) are never applied twice.
        try {
            $claimStmt = $this->db->prepare("
                UPDATE technical_tasks SET status = ?, notified = 1
                WHERE id = ? AND player_id = ? AND status = 'in_progress'
            ");
            $claimStmt->execute([$failed ? 'failed' : 'completed', $taskId, $pId]);
            if ($claimStmt->rowCount() === 0) {
 // Inny rownolegly tick juz zajal i przetwarza to zadanie - nie rob nic.
 // Another concurrent tick already claimed and is processing this task - do nothing.
                return;
            }
        } catch (Throwable $e) {
            GameLog::error('TTS', 'completeTask claim FAILED', $e, ['task_id' => $taskId]);
            return;
        }

 // Od tego miejsca jestesmy wylacznym wlascicielem zadania; efekty stosujemy bezpiecznie raz.
 // From here we are the sole owner of the task; effects are applied exactly once.
        try {

 // Odmroz odwiert po serwisie (przywroc status sprzed "W naprawie"), o ile nie trwa
 // jeszcze inne zadanie serwisowe na tym odwiercie. Robione PRZED efektami zadania,
 // aby logika typu well_repair (broken -> active) widziala realny status.
 // Un-freeze the well after service (restore pre-"servicing" status) unless another
 // service task is still running on it. Done BEFORE task effects so logic like
 // well_repair (broken -> active) sees the real status.
        if ($wellId && in_array($task['task_type'], self::WELL_SERVICE_TASKS, true)) {
            $otherStmt = $this->db->prepare("
                SELECT COUNT(*) FROM technical_tasks
                WHERE well_id = ? AND player_id = ? AND status = 'in_progress'
                  AND id <> ? AND task_type IN ('well_maintenance','well_repair','blowout_control','reservoir_rehabilitation')
            ");
            $otherStmt->execute([$wellId, $pId, $taskId]);
            if ((int)$otherStmt->fetchColumn() === 0) {
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
            $otherStmt = $this->db->prepare("
                SELECT COUNT(*) FROM technical_tasks
                WHERE pipeline_id = ? AND player_id = ? AND status = 'in_progress'
                  AND id <> ? AND task_type IN ('pipeline_maintenance','pipeline_repair')
            ");
            $otherStmt->execute([$pipeId, $pId, $taskId]);
            if ((int)$otherStmt->fetchColumn() === 0) {
                $this->db->prepare("
                    UPDATE well_pipelines
                    SET status = COALESCE(service_prev_status, status), service_prev_status = NULL
                    WHERE id = ? AND player_id = ? AND status = 'servicing'
                ")->execute([$pipeId, $pId]);
            }
        }

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
                        // Pelny serwis odwiertu domyka tez jego aktywne incydenty (repaired_at) —
                        // inaczej wiersz incydentu zostawal 'aktywny' na zawsze, a trwajacy
                        // prod_drop dalej dusil produkcje mimo naprawy.
                        // A full well service also closes its active incidents (repaired_at) —
                        // otherwise the incident row stayed 'active' forever and the ongoing
                        // prod_drop kept throttling production despite the repair.
                        $this->db->prepare("
                            UPDATE well_incidents
                            SET repaired_at = NOW(), repaired_by = ?
                            WHERE well_id = ? AND player_id = ? AND repaired_at IS NULL
                        ")->execute([$pId, $wellId, $pId]);
                        // Reset spirali ryzyka po pelnym serwisie (jak repairIncident dla 'major').
                        // Reset the risk spiral after a full service (as repairIncident does for 'major').
                        $this->db->prepare("
                            UPDATE wells SET post_incident_risk_boost = 0
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
                        // Filtruj po player_id — zapobiega wyciekom danych o zlozu innego gracza (Rule 1).
                        // Filter by player_id — prevents leaking another player's reservoir data (Rule 1).
                        $wStmt = $this->db->prepare("SELECT reservoir_remaining, reservoir_max, pressure FROM wells WHERE id = ? AND player_id = ?");
                        $wStmt->execute([$wellId, $pId]);
                        $w      = $wStmt->fetch();
                        $resPct = $w ? round(($w['reservoir_remaining'] / max(1, $w['reservoir_max'])) * 100, 1) : 0;
                        $msg = t('technical.task_msg.reservoir_analysis_done', [
                            'well_id' => $wellId,
                            'reservoir_pct' => $resPct,
                            'pressure' => round(($w['pressure'] ?? 1.0), 3),
                        ]);
                    }
                    break;

                case 'production_optimization':
                    if ($wellId) {
                        $boost = 5 + ($skill >= 7 ? min(10, $skill - 5) * 2 : 0);
                        $this->db->prepare("
                            UPDATE wells
                            SET base_production_per_hour = base_production_per_hour * (1 + ? / 100),
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
                        // Guard: skip INSERT if well was sold/deleted while task was in progress (FK constraint).
                        $wellExistsStmt = $this->db->prepare("SELECT id FROM wells WHERE id = ? AND player_id = ? LIMIT 1");
                        $wellExistsStmt->execute([$wellId, $pId]);
                        if (!$wellExistsStmt->fetch()) {
                            GameLog::warn('TTS', 'install_module skipped — well no longer exists', [
                                'well_id'   => $wellId,
                                'task_id'   => $taskId,
                                'module'    => $mod,
                            ]);
                            $msg = t('technical.task_result.install_module_well_gone');
                            break;
                        }
                        $checkStmt = $this->db->prepare("SELECT id FROM well_upgrades WHERE well_id = ? AND upgrade_type = ?");
                        $checkStmt->execute([$wellId, $mod]);
                        if (!$checkStmt->fetch()) {
                            $this->db->prepare("INSERT INTO well_upgrades (well_id, upgrade_type, cost_paid) VALUES (?,?,?)")
                                     ->execute([$wellId, $mod, $task['cost']]);
                            // Filtruj po player_id — izolacja gracza przy UPDATE wells (Rule 1).
                            // Filter by player_id — player isolation on UPDATE wells (Rule 1).
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
 // Sukces/komunikat ustawiamy TYLKO gdy UPDATE faktycznie cos zmienil. Jesli rurociag
 // zniknal (sprzedany) lub WHERE ... player_id trafil 0 wierszy, zadanie nie moze
 // raportowac "straty zmniejszone" (L6).
 // Set success/message ONLY when the UPDATE actually changed a row. If the pipeline is
 // gone (sold) or WHERE ... player_id matched 0 rows, the task must not report success.
                    if ($pipeId) {
                        $pipeStmt = $this->db->prepare("
                            UPDATE well_pipelines
                            SET transport_loss = GREATEST(0.5, transport_loss - 0.3),
                                condition_pct  = LEAST(100, condition_pct + 10),
                                status         = CASE
                                    WHEN LEAST(100, condition_pct + 10) < 40 THEN 'critical'
                                    WHEN LEAST(100, condition_pct + 10) < 70 THEN 'degraded'
                                    ELSE 'active'
                                END,
                                last_inspected_at = NOW()
                            WHERE id = ? AND player_id = ?
                        ");
                        $pipeStmt->execute([$pipeId, $pId]);
                        if ($pipeStmt->rowCount() > 0) {
                            $result = ['transport_loss_reduced' => 0.3];
                            $msg = t('technical.task_msg.pipeline_maintenance_done');
                        } else {
                            GameLog::warn('TTS', 'pipeline_maintenance: pipeline missing — no-op', ['pipeline_id' => $pipeId, 'player_id' => $pId]);
                            $msg = t('technical.task_msg.pipeline_gone');
                        }
                    } else {
                        $msg = t('technical.task_msg.pipeline_gone');
                    }
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
                        $blowStmt = $this->db->prepare("
                            UPDATE wells
                            SET status = 'active', technical_condition = GREATEST(20, 35 + ? * 3)
                            WHERE id = ? AND player_id = ? AND status = 'blowout'
                        ");
                        $blowStmt->execute([$skill, $wellId, $pId]);
                        if ($blowStmt->rowCount() === 0) {
                            GameLog::error('TTS', 'blowout_control: well not in blowout status — no-op', null, ['well_id' => $wellId, 'player_id' => $pId]);
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
 // Jak wyzej (L6): sukces/komunikat tylko przy realnej zmianie wiersza.
 // As above (L6): success/message only when a row was actually changed.
                    if ($pipeId) {
                        $repairStmt = $this->db->prepare("
                            UPDATE well_pipelines
                            SET status = 'active',
                                condition_pct = LEAST(100, 40 + ? * 5),
                                transport_loss = GREATEST(0.5, transport_loss - 0.5),
                                damaged_at = NULL,
                                leak_started_at = NULL,
                                last_inspected_at = NOW()
                            WHERE id = ? AND player_id = ?
                        ");
                        $repairStmt->execute([$skill, $pipeId, $pId]);
                        if ($repairStmt->rowCount() > 0) {
                            $this->db->prepare("
                                UPDATE industrial_disasters SET status = 'resolved', resolved_at = NOW()
                                WHERE player_id = ? AND disaster_type = 'pipeline_explosion' AND status != 'resolved'
                            ")->execute([$pId]);
                            $result = ['pipeline_repaired' => true];
                            $msg = t('technical.task_msg.pipeline_repair_done');
                        } else {
                            GameLog::warn('TTS', 'pipeline_repair: pipeline missing — no-op', ['pipeline_id' => $pipeId, 'player_id' => $pId]);
                            $msg = t('technical.task_msg.pipeline_gone');
                        }
                    } else {
                        $msg = t('technical.task_msg.pipeline_gone');
                    }
                    break;

                case 'reservoir_rehabilitation':
                    if ($wellId) {
                        $boostPct = 10 + ($skill >= 7 ? 5 : 0);
                        $this->db->prepare("
                            UPDATE wells
                            SET base_production_per_hour = base_production_per_hour * (1 + ? / 100),
                                status = CASE WHEN status = 'contaminated' THEN 'active' ELSE status END
                            WHERE id = ? AND player_id = ?
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

 // Finalizacja: zapis wyniku, odblokowanie pracownika, powiadomienie, nastepne z kolejki.
 // Status zostal juz ustawiony atomowym claimem na poczatku, wiec nie powtarzamy go tutaj.
 // Finalization: persist result, free the worker, notify, promote next queue item.
 // The status was already set by the atomic claim at the top, so we do not repeat it here.
            $this->db->prepare("UPDATE technical_tasks SET result_data = ? WHERE id = ? AND player_id = ?")
                ->execute([json_encode($result), $taskId, $pId]);

            $this->db->prepare("UPDATE technical_staff SET status = 'active' WHERE id = ? AND player_id = ?")->execute([$staffId, $pId]);

            if (!empty($msg)) {
                $this->db->prepare("
                    INSERT INTO technical_notifications (player_id, well_id, type, message)
                    VALUES (?,?,?,?)
                ")->execute([$pId, $wellId, 'task', $msg]);
            }

 // Start the next queued task for this worker.
 // Uruchom nastepne zadanie z kolejki dla tego pracownika.
 // startTask() manages its own transaction internally — no double-wrap needed.
            $qStmt = $this->db->prepare("
                SELECT * FROM technical_task_queue
                WHERE staff_id = ? AND player_id = ? ORDER BY priority DESC, queued_at ASC LIMIT 1
            ");
            $qStmt->execute([$staffId, $pId]);
            $next = $qStmt->fetch();
            if ($next) {
                $staffRow = $this->getStaffMember($staffId);
                if ($staffRow) {
                    $startRes = $this->startTask($staffId, $next['task_type'], $next['well_id'], $next['module_type'], $staffRow, $next['hub_id'] ?? null, $next['pipeline_id'] ?? null);
 // Usun z kolejki dopiero po udanym starcie - inaczej zadanie przepada (brak gotowki, studnia niedostepna).
 // Remove from the queue only after a successful start - otherwise the item is lost (no cash, well unavailable).
                    if (!empty($startRes['success'])) {
                        $this->db->prepare("DELETE FROM technical_task_queue WHERE id = ? AND player_id = ?")->execute([$next['id'], $pId]);
                    }
                }
            }
        } catch (Throwable $e) {
            GameLog::error('TTS', 'completeTask FAILED', $e, ['task_id' => $taskId]);
 // Zadanie zostalo juz zajete (status koncowy) - nie zostawiaj pracownika w stanie 'busy'.
 // The task is already claimed (final status) - do not leave the worker stuck as 'busy'.
            try {
                $this->db->prepare("UPDATE technical_staff SET status = 'active' WHERE id = ? AND player_id = ?")->execute([$staffId, $pId]);
            } catch (Throwable $e2) {
 // best-effort
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

            // $ownTx — zabezpieczenie przed zagniezdzona transakcja (Rule 5).
            // $ownTx — guard against nested transaction (Rule 5).
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) $this->db->beginTransaction();
            $cancelStmt = $this->db->prepare("
                UPDATE technical_tasks SET status = 'cancelled', end_time = NOW()
                WHERE id = ? AND player_id = ? AND status = 'in_progress'
            ");
            $cancelStmt->execute([$taskId, $this->playerId]);
            if ($cancelStmt->rowCount() === 0) {
                if ($ownTx) $this->db->rollBack();
                return ['success' => false, 'message' => t('technical.task_msg.active_task_not_found')];
            }
            $this->db->prepare("
                UPDATE technical_staff SET status = 'active'
                WHERE id = ? AND player_id = ?
            ")->execute([$task['staff_id'], $this->playerId]);

 // Odmroz odwiert jezeli anulowane zadanie go serwisowalo (i brak innych serwisow).
 // Un-freeze the well if the cancelled task was servicing it (and no other service runs).
            if (!empty($task['well_id']) && in_array($task['task_type'], self::WELL_SERVICE_TASKS, true)) {
                $otherStmt = $this->db->prepare("
                    SELECT COUNT(*) FROM technical_tasks
                    WHERE well_id = ? AND player_id = ? AND status = 'in_progress'
                      AND id <> ? AND task_type IN ('well_maintenance','well_repair','blowout_control','reservoir_rehabilitation')
                ");
                $otherStmt->execute([(int)$task['well_id'], $this->playerId, $taskId]);
                if ((int)$otherStmt->fetchColumn() === 0) {
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
                $otherStmt = $this->db->prepare("
                    SELECT COUNT(*) FROM technical_tasks
                    WHERE pipeline_id = ? AND player_id = ? AND status = 'in_progress'
                      AND id <> ? AND task_type IN ('pipeline_maintenance','pipeline_repair')
                ");
                $otherStmt->execute([(int)$task['pipeline_id'], $this->playerId, $taskId]);
                if ((int)$otherStmt->fetchColumn() === 0) {
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
                $this->db->prepare("DELETE FROM technical_task_queue WHERE id = ? AND player_id = ?")->execute([$nextTask['id'], $this->playerId]);
            }

            if ($ownTx) $this->db->commit();
            GameLog::info('TTS', 'cancelTask OK', ['task_id' => $taskId, 'player_id' => $this->playerId]);

            // startTask() wywolujemy po commit() — PDO/MySQL nie obsluguje zagniezdzonnych transakcji.
            // startTask() is called after commit() — PDO/MySQL does not support nested transactions.
            if ($nextTask) {
                $staffRow = $this->getStaffMember((int)$task['staff_id']);
                if ($staffRow) {
                    $this->startTask((int)$task['staff_id'], $nextTask['task_type'], $nextTask['well_id'], $nextTask['module_type'], $staffRow, $nextTask['hub_id'] ?? null, $nextTask['pipeline_id'] ?? null);
                }
            }

            return ['success' => true, 'message' => t('technical.task_msg.task_cancelled')];
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('TTS', 'cancelTask FAILED', $e, ['task_id' => $taskId]);
            return ['success' => false, 'message' => t('technical.task_msg.cancel_task_failed')];
        }
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
