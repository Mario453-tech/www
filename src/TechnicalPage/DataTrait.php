<?php

trait TechnicalPageDataTrait
{
 /**
 * Execute a loader safely and return fallback on failure.
 * Bezpiecznie uruchamia loader i zwraca fallback przy bledzie.
 */
    private function safeLoad(callable $loader, string $label, mixed $fallback, ?callable $metaBuilder = null, bool $dbResult = false): mixed
    {
        try {
            $value = $loader();
            if ($dbResult) {
                GameLog::dbResult('technical.php', $label, (int) $metaBuilder($value));
            } elseif ($metaBuilder !== null) {
                GameLog::info('technical.php', $label, $metaBuilder($value));
            }
            return $value;
        } catch (Throwable $e) {
            GameLog::error('technical.php', "{$label} FAIL", $e);
            return $fallback;
        }
    }

 /**
 * Load paginated well incident data.
 * Laduje stronicowane dane incydentow odwiertow.
 */
    private function loadIncidentData(): array
    {
        $incPage = max(1, (int) ($_GET['inc_page'] ?? 1));
        $incPerPage = 10;
        $incTotal = 0;
        $incTotalPages = 1;
        $incidents = [];

        try {
            if ($this->incidentSvc) {
                $incTotal = $this->incidentSvc->countPlayerIncidents($this->playerId);
                $incTotalPages = max(1, (int) ceil($incTotal / $incPerPage));
                $incPage = min($incPage, $incTotalPages);
                $incidents = $this->incidentSvc->getPlayerIncidents($this->playerId, $incPerPage, ($incPage - 1) * $incPerPage);
                GameLog::dbResult('technical.php', 'getPlayerIncidents', count($incidents));
            }
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'getPlayerIncidents FAIL', $e);
            $incidents = [];
        }

        return [
            'incPage' => $incPage,
            'incTotalPages' => $incTotalPages,
            'incTotal' => $incTotal,
            'incidents' => $incidents,
        ];
    }

 /**
 * Load recent hub incident data.
 * Laduje ostatnie incydenty hubow.
 */
    private function loadHubIncidentData(): array
    {
        $hubIncidents = [];
        $hubIncTotal = 0;

        try {
            if (isset($this->hubIncidentSvc) && $this->hubIncidentSvc !== null) {
                $hubIncTotal = $this->hubIncidentSvc->countPlayerIncidents($this->playerId);
                $hubIncidents = $this->hubIncidentSvc->getPlayerRecentIncidents($this->playerId, 20);
            }
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'loadHubIncidentData FAIL', $e);
        }

        return [
            'hubIncidents' => $hubIncidents,
            'hubIncTotal' => $hubIncTotal,
        ];
    }

 /**
 * Load the player's current cash.
 * Laduje aktualna gotowke gracza.
 */
    private function loadPlayerCash(PDO $db): float
    {
        try {
            $stmt = $db->prepare("SELECT cash FROM players WHERE id = ? LIMIT 1");
            $stmt->execute([$this->playerId]);
            return (float) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'fetch cash FAIL', $e);
            return 0.0;
        }
    }

 /**
 * Normalize runtime-only well statuses for the page view.
 * Normalizuje statusy odwiertow dla widoku strony.
 */
    private function normalizeWellStatuses(PDO $db, array $wells, float $playerCash): array
    {
        try {
            $db->prepare("UPDATE wells SET status = 'broken' WHERE player_id = ? AND status = 'active' AND technical_condition <= 0")
                ->execute([$this->playerId]);
            foreach ($wells as &$well) {
                if ($well['status'] === 'active' && (float) $well['technical_condition'] <= 0) {
                    $well['status'] = 'broken';
                }
            }
            unset($well);
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'fix zero_condition FAIL', $e);
        }

        if ($playerCash > 0) {
            try {
                $db->prepare("UPDATE wells SET status = 'active' WHERE player_id = ? AND status = 'paused_cash'")
                    ->execute([$this->playerId]);
                foreach ($wells as &$well) {
                    if ($well['status'] === 'paused_cash') {
                        $well['status'] = 'active';
                    }
                }
                unset($well);
            } catch (Throwable $e) {
                GameLog::error('technical.php', 'resume paused_cash FAIL', $e);
            }
        }

 // Resume staff-paused wells when staffing is sufficient again.
 // Wznow odwierty paused_staff, gdy obsluga znowu jest kompletna.
        try {
            $staffCheck = $this->svc->getStaffRequirementCheck();
            if ($staffCheck['meets_minimum']) {
                $db->prepare("UPDATE wells SET status = 'active', paused_staff_reason = NULL WHERE player_id = ? AND status = 'paused_staff'")
                    ->execute([$this->playerId]);
                foreach ($wells as &$well) {
                    if ($well['status'] === 'paused_staff') {
                        $well['status'] = 'active';
                        $well['paused_staff_reason'] = null;
                    }
                }
                unset($well);
                GameLog::info('technical.php', 'Resumed paused_staff wells - staff requirements met');
            }
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'resume paused_staff FAIL', $e);
        }

        return $wells;
    }

 /**
 * Load staffing coverage per well.
 * Laduje pokrycie kadrowe dla odwiertow.
 */
    private function loadWellStaffData(): array
    {
        try {
            $wellStaffSvc = new WellStaffService($this->playerId);
            $wellsStaffStatus = $wellStaffSvc->getWellsStaffStatus();
            return [
                'wellsStaffStatus' => $wellsStaffStatus,
                'wellsWithoutStaff' => count(array_filter($wellsStaffStatus, static fn($well) => !$well['has_operator'] || !$well['has_technician'])),
            ];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'WellStaffService FAIL', $e);
            return [
                'wellsStaffStatus' => [],
                'wellsWithoutStaff' => 0,
            ];
        }
    }

 /**
 * Load player pipeline data for the page.
 * Laduje dane rurociagow gracza dla strony.
 */
    private function loadPipelines(PDO $db): array
    {
        try {
            GameLog::tablesCheck($db, 'technical.php', [
                'well_pipelines',
                'technical_staff',
                'technical_tasks',
                'technical_notifications',
                'candidate_reviews',
                'failure_log',
            ]);

            $svc = new WellPipelineService($db);
            $pipelines = $svc->getPlayerPipelines($this->playerId);
            GameLog::dbResult('technical.php', 'well_pipelines', count($pipelines));

            return $pipelines;
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'well_pipelines load FAIL', $e);
            return [];
        }
    }

 /**
 * Load prerequisites for HSE procedure upgrades.
 * Laduje wymagania do ulepszania procedur BHP.
 */
    private function loadProcedureRequirements(PDO $db): array
    {
        $auditDone = false;
        $hasHseStaff = ['officer' => false, 'engineer' => false];

        try {
            $auditStmt = $db->prepare("
                SELECT id FROM technical_tasks
                WHERE player_id = ? AND task_type = 'safety_audit' AND status = 'completed'
                LIMIT 1
            ");
            $auditStmt->execute([$this->playerId]);
            $auditDone = (bool) $auditStmt->fetch();

            $staffStmt = $db->prepare("
                SELECT spec_code FROM technical_staff
                WHERE player_id = ?
                  AND spec_code IN ('safety_officer','safety_engineer')
                  AND status IN ('active','busy')
                  AND (fired_at IS NULL OR fired_at > NOW())
            ");
            $staffStmt->execute([$this->playerId]);
            foreach ($staffStmt->fetchAll() as $row) {
                if ($row['spec_code'] === 'safety_officer') {
                    $hasHseStaff['officer'] = true;
                }
                if ($row['spec_code'] === 'safety_engineer') {
                    $hasHseStaff['engineer'] = true;
                }
            }
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'auditCheck/hseStaff FAIL', $e);
        }

        return [
            'auditDone' => $auditDone,
            'hasHseStaff' => $hasHseStaff,
            'canUpgradeProcedures' => $auditDone && $hasHseStaff['officer'] && $hasHseStaff['engineer'],
        ];
    }

 /**
 * Load active industrial disasters.
 * Laduje aktywne katastrofy przemyslowe.
 */
    private function loadActiveDisasters(PDO $db): array
    {
        try {
            $stmt = $db->prepare("
                SELECT d.*, w.location_name AS well_name
                FROM industrial_disasters d
                LEFT JOIN wells w ON w.id = d.well_id
                WHERE d.player_id = ? AND d.status IN ('active','being_repaired')
                ORDER BY d.occurred_at DESC
            ");
            $stmt->execute([$this->playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'activeDisasters query FAILED', $e);
            return [];
        }
    }

 /**
 * Load recent failures shown on the page.
 * Laduje ostatnie awarie pokazywane na stronie.
 */
    private function loadFailures(PDO $db): array
    {
        try {
            $stmt = $db->prepare("
                SELECT fl.*, w.location_name
                FROM failure_log fl
                LEFT JOIN wells w ON fl.well_id = w.id
                WHERE fl.player_id = ?
                ORDER BY fl.occurred_at DESC
                LIMIT 20
            ");
            $stmt->execute([$this->playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'failure_log query FAILED', $e);
            return [];
        }
    }
}
