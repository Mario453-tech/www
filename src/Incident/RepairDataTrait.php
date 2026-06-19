<?php

/**
 * RepairDataTrait naprawa incydentow, zapis, efekty i gettery danych.
 * Repair & data trait incident repair, persistence, effects and data getters.
 */
trait IncidentRepairDataTrait
{
 // NAPRAWA / Repair

 /**
 * Reczna naprawa incydentu medium/major przez gracza.
 * Manual repair of a medium/major incident by the player.
 * Przywraca status odwiertu na 'active' jesli byl 'broken' z powodu tego incydentu.
 * Restores well status to 'active' if it was 'broken' due to this incident.
 */
 /** @return array<string, mixed> */
    public function repairIncident(int $incidentId, int $playerId): array
    {
        try {
            // Sprawdz bankructwo — gracz bankrutujacy nie moze przywracac produkcji.
            // Check bankruptcy — bankrupt player must not restore production.
            if (class_exists('Player', false)) {
                $playerObj = new Player($playerId);
                if ($playerObj->isBankrupt()) {
                    return ['success' => false, 'message' => $playerObj->getBankruptcyBlockMessage()];
                }
            }

            $stmt = $this->db->prepare("
                SELECT * FROM well_incidents
                WHERE id = ? AND player_id = ?
                LIMIT 1
            ");
            $stmt->execute([$incidentId, $playerId]);
            $inc = $stmt->fetch();

            if (!$inc) {
                return ['success' => false, 'message' => t('incident.err_not_found')];
            }
            if ($inc['auto_repair']) {
                return ['success' => false, 'message' => t('incident.err_auto_repair')];
            }
            if ($inc['repaired_at'] !== null) {
                return ['success' => false, 'message' => t('incident.err_already_repaired')];
            }

            // Transakcja — trzy UPDATE-y musza byc atomowe; crash miedzy nimi moglby zostawic
            // incydent oznaczony jako naprawiony, ale odwiert nadal broken (soft-lock) (Rule 5).
            // Transaction — three UPDATEs must be atomic; a crash between them could leave the
            // incident marked repaired but the well still broken (soft-lock) (Rule 5).
            // $ownTx — zabezpieczenie przed zagniezdzona transakcja (Rule 5).
            // $ownTx — guard against nested transaction (Rule 5).
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) $this->db->beginTransaction();
            try {
                // Oznacz incydent jako naprawiony; filtruj po player_id (Rule 1).
                // Mark incident as repaired; filter by player_id (Rule 1).
                $this->db->prepare("
                    UPDATE well_incidents
                    SET repaired_at = NOW(), repaired_by = ?
                    WHERE id = ? AND player_id = ?
                ")->execute([$playerId, $incidentId, $playerId]);

                // Przywr odwiert i resetuj spirale zaleznie od poziomu / Restore well and reset spiral by level
                if ($inc['level'] === 'major') {
                    $this->db->prepare("
                        UPDATE wells SET status = 'active'
                        WHERE id = ? AND player_id = ? AND status = 'broken'
                    ")->execute([$inc['well_id'], $playerId]);
                    $this->db->prepare("
                        UPDATE wells SET post_incident_risk_boost = 0
                        WHERE id = ? AND player_id = ?
                    ")->execute([$inc['well_id'], $playerId]);
                } elseif ($inc['level'] === 'medium') {
                    $this->db->prepare("
                        UPDATE wells
                        SET post_incident_risk_boost = GREATEST(0, post_incident_risk_boost * 0.5)
                        WHERE id = ? AND player_id = ?
                    ")->execute([$inc['well_id'], $playerId]);
                }

                if ($ownTx) $this->db->commit();
            } catch (\Throwable $e) {
                if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }

            GameLog::info('IncidentService', 'repairIncident_OK', [
                'incident_id' => $incidentId,
                'player_id'   => $playerId,
                'well_id'     => $inc['well_id'],
                'level'       => $inc['level'],
            ]);

            return ['success' => true, 'message' => t('incident.msg_repaired')];

        } catch (\Throwable $e) {
            GameLog::error('IncidentService', 'repairIncident FAILED', $e, ['incident_id' => $incidentId]);
            return ['success' => false, 'message' => t('incident.err_internal')];
        }
    }

 // GETTERY / Getters

 /**
 * Zwraca ostatnie incydenty dla odwiertu (do wyswietlenia w UI).
 * Returns recent incidents for a well (for display in the UI).
 * $playerId — wymagany, filtruje po wlascicielu (zapobiega wyciekom danych miedzy graczami).
 * $playerId — required, filters by owner (prevents cross-player data leak, Rule 1).
 */
    public function getRecentIncidents(int $wellId, int $limit = 10, int $playerId = 0): array
    {
        try {
            // Zawsze filtruj po player_id — zapobiega wycieku incydentow innych graczy (Rule 1).
            // Always filter by player_id — prevents leaking other players' incidents (Rule 1).
            $stmt = $this->db->prepare(
                "SELECT * FROM well_incidents WHERE well_id = ? AND player_id = ? ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->bindValue(1, $wellId,   \PDO::PARAM_INT);
            $stmt->bindValue(2, $playerId, \PDO::PARAM_INT);
            $stmt->bindValue(3, $limit,    \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            GameLog::error('IncidentService', 'getRecentIncidents FAILED', $e);
            return [];
        }
    }

 /**
 * Zwraca ostatnie incydenty dla wszystkich odwiertow gracza.
 * Returns recent incidents across all player wells.
 */
    public function countPlayerIncidents(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM well_incidents WHERE player_id = ?");
            $stmt->execute([$playerId]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getPlayerIncidents(int $playerId, int $limit = 30, int $offset = 0): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, w.location_name AS well_name
                FROM well_incidents wi
                LEFT JOIN wells w ON w.id = wi.well_id
                WHERE wi.player_id = ?
                ORDER BY wi.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, $playerId, \PDO::PARAM_INT);
            $stmt->bindValue(2, $limit,    \PDO::PARAM_INT);
            $stmt->bindValue(3, $offset,   \PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            GameLog::error('IncidentService', 'getPlayerIncidents FAILED', $e);
            return [];
        }
    }

 // ZAPIS I EFEKTY / Persistence and effects

    private function saveIncident(array $incident): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO well_incidents
                    (well_id, player_id, level, cause_type, prod_drop, hours,
                     deg_damage, cost, risk_add, auto_repair, hse_active, message)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $incident['well_id'],
                $incident['player_id'],
                $incident['level'],
                $incident['cause_type'],
                $incident['prod_drop'],
                $incident['hours'],
                $incident['deg_damage'],
                $incident['cost'],
                $incident['risk_add'],
                $incident['auto_repair'],
                $incident['hse_active'],
                $incident['message'],
            ]);
            GameLog::info('IncidentService', 'saveIncident_OK', [
                'well_id' => $incident['well_id'],
                'level'   => $incident['level'],
                'cause'   => $incident['cause_type'],
            ]);
        } catch (\Throwable $e) {
            GameLog::error('IncidentService', 'saveIncident FAILED', $e);
        }
    }

    private function applyEffects(array $incident, int $wellId, int $playerId): void
    {
        try {
            // Pobierz stan przed modyfikacja — INSERT-SELECT czytajacy po UPDATE daje zla wartosc
            // gdy GREATEST(0,...) obetnie wynik do 0 (np. cond=3, dmg=10 -> 0+10=10 zamiast 3).
            // Capture condition before mutation — INSERT-SELECT reading after UPDATE gives wrong value
            // when GREATEST(0,...) clamps to 0 (e.g. cond=3, dmg=10 -> 0+10=10 instead of 3).
            $condBefore = null;
            if ($incident['cost'] > 0) {
                $s = $this->db->prepare("SELECT technical_condition FROM wells WHERE id = ? AND player_id = ?");
                $s->execute([$wellId, $playerId]);
                $condBefore = (float)($s->fetchColumn() ?? 0.0);
            }

            // Trzy zapisy musza byc atomowe (Rule 5) — czesciowy stan przy awarii DB zostawia odwiert niespojny.
            // Three writes must be atomic (Rule 5) — partial state on DB failure leaves well inconsistent.
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) $this->db->beginTransaction();
            try {
 // Degradacja stanu technicznego; filtruj po player_id (Rule 1).
 // Technical condition degradation; filter by player_id (Rule 1).
                if ($incident['deg_damage'] > 0) {
                    $this->db->prepare("
                        UPDATE wells
                        SET technical_condition = GREATEST(0, technical_condition - ?)
                        WHERE id = ? AND player_id = ?
                    ")->execute([$incident['deg_damage'], $wellId, $playerId]);
                    GameLog::info('IncidentService', 'applyEffects_deg', [
                        'well_id'    => $wellId,
                        'deg_damage' => $incident['deg_damage'],
                    ]);
                }

 // Dodaj risk_score; filtruj po player_id (Rule 1). / Add risk_score; filter by player_id (Rule 1).
                if ($incident['risk_add'] > 0) {
                    $this->db->prepare("
                        UPDATE wells
                        SET risk_score = LEAST(100, risk_score + ?)
                        WHERE id = ? AND player_id = ?
                    ")->execute([$incident['risk_add'], $wellId, $playerId]);
                    GameLog::info('IncidentService', 'applyEffects_risk', [
                        'well_id'  => $wellId,
                        'risk_add' => $incident['risk_add'],
                    ]);
                }

 // Koszt finansowy dla gracza (medium/major) / Financial cost for the player (medium/major)
 // UWAGA: odjecie z cash nastepuje w tick.php przez $playerCash -= $inc['cost']
 // NOTE: cash deduction happens in tick.php via $playerCash -= $inc['cost']
 // Tu tylko logujemy zdarzenie w well_events
 // Here we only log the event in well_events
                if ($incident['cost'] > 0) {
                    // Uzyj parametryzowanego event_type zamiast interpolacji (Rule 7).
                    // Use parameterized event_type instead of interpolation (Rule 7).
                    $eventType = 'incident_' . $incident['level'];
                    $condAfter = max(0.0, $condBefore - (float)$incident['deg_damage']);
                    $this->db->prepare("
                        INSERT INTO well_events
                            (well_id, player_id, event_type, cost, description,
                             technical_condition_before, technical_condition_after)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $wellId, $playerId,
                        $eventType,
                        $incident['cost'],
                        $incident['message'],
                        $condBefore,
                        $condAfter,
                    ]);
                    GameLog::info('IncidentService', 'applyEffects_well_event', [
                        'well_id'   => $wellId,
                        'player_id' => $playerId,
                        'level'     => $incident['level'],
                        'cost'      => $incident['cost'],
                    ]);
                }

 // major bez auto_repair -> pauzuj odwiert; filtruj po player_id (Rule 1).
 // major without auto_repair -> pause the well; filter by player_id (Rule 1).
                if ($incident['level'] === 'major' && !$incident['auto_repair']) {
                    $this->db->prepare("
                        UPDATE wells SET status = 'broken'
                        WHERE id = ? AND player_id = ? AND status = 'active'
                    ")->execute([$wellId, $playerId]);
                    GameLog::warn('IncidentService', 'applyEffects_well_broken', ['well_id' => $wellId]);
                }

                if ($ownTx) $this->db->commit();
            } catch (\Throwable $e) {
                if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            GameLog::error('IncidentService', 'applyEffects FAILED', $e, ['well_id' => $wellId]);
        }
    }

 // HELPER

    private function weightedRand(array $weights): string
    {
        $total = array_sum($weights);
        $rand  = mt_rand(1, $total);
        $sum   = 0;
        foreach ($weights as $key => $w) {
            $sum += $w;
            if ($rand <= $sum) return $key;
        }
        return array_key_first($weights);
    }
}
