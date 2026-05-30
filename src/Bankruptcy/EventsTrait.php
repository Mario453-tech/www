<?php

/**
 * Handles critical bankruptcy events, liquidation resets and helpers.
 * PL: Obsluguje krytyczne eventy bankructwa, reset likwidacyjny i helpery.
 */
trait BankruptcyEventsTrait
{
 // Bankruptcy flow invoked during state reads and option application.
 // PL: Flow bankructwa wywolywany podczas odczytu stanu i stosowania opcji.
    private function tickBankruptcyFlow(): void
    {
        $this->processOverdueCriticalEvents();
        $this->spawnCriticalEventIfNeeded();
    }

    private function processOverdueCriticalEvents(): void
    {
        try {
            $stmt = $this->db->prepare("SELECT id, event_type FROM bankruptcy_events WHERE player_id=? AND is_critical=1 AND resolved_at IS NULL AND due_at IS NOT NULL AND due_at <= NOW() ORDER BY due_at ASC LIMIT 5");
            $stmt->execute([$this->playerId]);
            $rows = $stmt->fetchAll() ?: [];

            foreach ($rows as $row) {
                $eventId = (int)$row['id'];
                $type = (string)$row['event_type'];
                $note = t('bankruptcy.evt_overdue_default');

                $this->db->beginTransaction();
                try {
                    if ($type === 'debt_deadline_24h') {
                        $wStmt = $this->db->prepare("SELECT id FROM wells WHERE player_id=? AND status!='seized' ORDER BY level DESC, base_production_per_hour DESC LIMIT 1");
                        $wStmt->execute([$this->playerId]);
                        $wellId = (int)$wStmt->fetchColumn();
                        if ($wellId > 0) {
                            $this->db->prepare("UPDATE wells SET status='seized' WHERE id=? AND player_id=?")->execute([$wellId, $this->playerId]);
                            $note = t('bankruptcy.evt_deadline_well_seized', ['id' => $wellId]);
                        } else {
                            $note = t('bankruptcy.evt_deadline_no_assets');
                        }
                    } elseif ($type === 'competitor_buyout') {
                        $cStmt = $this->db->prepare("SELECT cash FROM players WHERE id=? LIMIT 1");
                        $cStmt->execute([$this->playerId]);
                        $cash = (float)$cStmt->fetchColumn();
                        $penalty = (int)max(20000, min(90000, round($cash * 0.2)));
                        $this->db->prepare("UPDATE players SET cash = GREATEST(0, cash - ?) WHERE id=?")->execute([$penalty, $this->playerId]);
                        $note = t('bankruptcy.evt_competitor_buyout', ['amount' => number_format($penalty)]);
                    } elseif ($type === 'investor_offer_40') {
                        $this->db->prepare("UPDATE players SET credit_score = GREATEST(0, credit_score - 20) WHERE id=?")->execute([$this->playerId]);
                        $note = t('bankruptcy.evt_investor_expired');
                    }

                    $this->db->prepare("UPDATE bankruptcy_events SET resolved_at = NOW(), resolution_note = ? WHERE id=? AND player_id=?")
                        ->execute([$note, $eventId, $this->playerId]);
                    $this->addNotification($note);
                    $this->db->commit();
                } catch (Throwable $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    GameLog::error('BankruptcyService', 'processOverdueCriticalEvents FAILED', $e, ['player_id' => $this->playerId]);
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'processOverdueCriticalEvents failed', $e, ['player_id' => $this->playerId]);
        }
    }

    private function spawnCriticalEventIfNeeded(): void
    {
        try {
            if ($this->countOpenCriticalEvents() > 0) {
                return;
            }

            $lastStmt = $this->db->prepare("SELECT created_at FROM bankruptcy_events WHERE player_id=? AND is_critical=1 ORDER BY id DESC LIMIT 1");
            $lastStmt->execute([$this->playerId]);
            $lastCreated = $lastStmt->fetchColumn();
            if ($lastCreated) {
                $ts = strtotime((string)$lastCreated);
                if ($ts !== false && $ts > (time() - 8 * 3600)) {
                    return;
                }
            }

            $pool = [
                ['type' => 'debt_deadline_24h', 'sev' => 'critical', 'msg' => t('bankruptcy.spawn_debt_deadline')],
                ['type' => 'competitor_buyout', 'sev' => 'high', 'msg' => t('bankruptcy.spawn_competitor_buyout')],
                ['type' => 'investor_offer_40', 'sev' => 'high', 'msg' => t('bankruptcy.spawn_investor_offer')],
            ];

            $pick = $pool[$this->randBetween(0, count($pool) - 1)];
            $dueAt = date('Y-m-d H:i:s', time() + 24 * 3600);
            $this->logEvent($pick['type'], $pick['msg'], ['auto_generated' => true], $pick['sev'], 1, $dueAt);
            $this->addNotification($pick['msg']);
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'spawnCriticalEventIfNeeded failed', $e, ['player_id' => $this->playerId]);
        }
    }

 // Liquidation and fallback restart.
 // PL: Likwidacja i awaryjny restart.
    private function applyLiquidationResetIfNeeded(bool $force): bool
    {
        try {
            $state = $this->loadState();
            if (empty($state['is_bankrupt'])) {
                return false;
            }

            $cash = (float)($state['player']['cash'] ?? 0);
            $nonSeized = (int)($state['wells']['wells_non_seized'] ?? 0);
            if (!$force && !($cash <= 0 && $nonSeized <= 0)) {
                return false;
            }

            $this->db->beginTransaction();
            try {
                $this->db->prepare("UPDATE loans SET status='defaulted', remaining_amount=0, paid_off_at=IFNULL(paid_off_at, NOW()) WHERE player_id=? AND status IN ('active','late')")
                    ->execute([$this->playerId]);

                $wStmt = $this->db->prepare("SELECT id FROM wells WHERE player_id=? AND status!='seized' ORDER BY id ASC LIMIT 1");
                $wStmt->execute([$this->playerId]);
                $wellId = (int)$wStmt->fetchColumn();
                if ($wellId > 0) {
                    $this->db->prepare("UPDATE wells SET status='active', level=1, base_production_per_hour=25.00, upkeep_cost_per_hour=650.00, technical_condition=85, transport_type='nieustawiony', transport_capacity_pct=0, transport_opex_pct=0 WHERE id=? AND player_id=?")
                        ->execute([$wellId, $this->playerId]);
                } else {
                    $this->db->prepare("INSERT INTO wells (player_id, level, status, base_production_per_hour, upkeep_cost_per_hour, technical_condition, well_type, name, location, upgrades, last_production_at, created_at, reservoir_remaining, reservoir_max, pressure, risk_level, location_name, depth_m, production_boost_pct, transport_type, transport_capacity_pct, transport_opex_pct) VALUES (?,1,'active',25.00,650.00,85,'onshore','Odwiert Restart','Pole awaryjne',NULL,NOW(),NOW(),300000.00,300000.00,1.00,20,'Pole ratunkowe',1800,0.00,'nieustawiony',0,0)")
                        ->execute([$this->playerId]);
                    $wellId = (int)$this->db->lastInsertId();
                }

                $stStmt = $this->db->prepare("SELECT capacity, used FROM storage WHERE player_id=? LIMIT 1");
                $stStmt->execute([$this->playerId]);
                $storage = $stStmt->fetch();
                if ($storage) {
                    $newCap = max(1200, min((int)$storage['capacity'], 1800));
                    $newUsed = min((int)$storage['used'], $newCap);
                    $this->db->prepare("UPDATE storage SET capacity=?, used=? WHERE player_id=?")->execute([$newCap, $newUsed, $this->playerId]);
                } else {
                    $this->db->prepare("INSERT INTO storage (player_id, capacity, used) VALUES (?,1200,0)")->execute([$this->playerId]);
                }

                $this->db->prepare("
                    UPDATE players SET cash=50000, status='active', bankruptcy_at=NULL,
                    recovery_mode=0, bankruptcy_status='recovered',
                    credit_score=GREATEST(60, LEAST(120, credit_score - 20))
                    WHERE id=?
                ")->execute([$this->playerId]);

                $this->db->prepare("DELETE FROM wells WHERE player_id=? AND status='seized'")->execute([$this->playerId]);
                $this->db->prepare("UPDATE bailiff_proceedings SET status='completed' WHERE player_id=? AND status='active'")->execute([$this->playerId]);
                $this->db->prepare("UPDATE bankruptcy_events SET resolved_at=NOW(), resolution_note=? WHERE player_id=? AND resolved_at IS NULL")
                    ->execute([t('bankruptcy.resolution_new_start'), $this->playerId]);

                $this->logEvent('new_start', t('bankruptcy.log_new_start'), ['starter_well_id' => $wellId, 'starter_cash' => 50000], 'critical', 0, null);
                $this->addNotification(t('bankruptcy.notif_new_start'));
                GameLog::info('BankruptcyService', 'applyLiquidationResetIfNeeded - new start', ['player_id' => $this->playerId, 'starter_well' => $wellId]);
                $this->db->commit();
                return true;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                GameLog::error('BankruptcyService', 'applyLiquidationResetIfNeeded FAILED', $e, ['player_id' => $this->playerId]);
                throw $e;
            }
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'applyLiquidationResetIfNeeded failed', $e, ['player_id' => $this->playerId, 'force' => $force]);
            return false;
        }
    }

 // Event and notification helpers.
 // PL: Helpery eventow i notyfikacji.
    private function logEvent(string $type, string $message, array $payload, string $severity, int $isCritical, ?string $dueAt): void
    {
        try {
            $severity = in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : 'medium';
            $payloadJson = !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
            $stmt = $this->db->prepare("INSERT INTO bankruptcy_events (player_id, event_type, message, severity, is_critical, due_at, payload_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$this->playerId, $type, $message, $severity, $isCritical, $dueAt, $payloadJson]);
        } catch (Throwable $e) {
            try {
                $fallback = $this->db->prepare("INSERT INTO bankruptcy_events (player_id, event_type, message, payload_json) VALUES (?, ?, ?, ?)");
                $fallback->execute([$this->playerId, $type, $message, !empty($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
            } catch (Throwable $fallbackError) {
                GameLog::error('BankruptcyService', 'logEvent failed', $fallbackError, ['player_id' => $this->playerId, 'event_type' => $type]);
            }
        }
    }

    private function addNotification(string $message): void
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO technical_notifications (player_id, well_id, type, message) VALUES (?, NULL, 'task', ?)");
            $stmt->execute([$this->playerId, t('bankruptcy.notif_prefix') . trim($message)]);
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'addNotification failed', $e, ['player_id' => $this->playerId]);
        }
    }

    private function randBetween(int $min, int $max): int
    {
        try {
            return random_int($min, $max);
        } catch (Throwable $e) {
            GameLog::warn('BankruptcyService', 'random_int failed - fallback to rand()', ['error' => $e->getMessage()]);
            return rand($min, $max);
        }
    }
}
