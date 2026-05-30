<?php

/**
 * Handles random events while a negotiation is pending.
 * Obsluguje zdarzenia losowe podczas oczekiwania na decyzje banku.
 */
trait BankNegotiationRandomEventsTrait
{
 /**
 * Tries to trigger a random event for the given negotiation.
 * Probouje uruchomic zdarzenie losowe dla danej negocjacji.
 */
    public function triggerRandomEvent(int $negotiationId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT bn.*, p.id AS pid
                FROM bank_negotiations bn
                JOIN players p ON p.id = bn.player_id
                WHERE bn.id=:id AND bn.status='pending'
            ");
            $stmt->execute([':id' => $negotiationId]);
            $neg = $stmt->fetch();
            if (!$neg) {
                return null;
            }

 // Enforce a minimum 3h cooldown between events for one negotiation.
 // Wymus minimum 3h cooldown miedzy zdarzeniami dla jednej negocjacji.
            $lastEvtStmt = $this->db->prepare("
                SELECT created_at FROM bank_negotiation_events
                WHERE negotiation_id = :nid
                ORDER BY created_at DESC LIMIT 1
            ");
            $lastEvtStmt->execute([':nid' => $negotiationId]);
            $lastEvt = $lastEvtStmt->fetch();
            if ($lastEvt) {
                $hoursSinceLast = (time() - strtotime($lastEvt['created_at'])) / 3600;
                if ($hoursSinceLast < 3.0) {
                    return null;
                }
            }

 // Cap total decision time growth to max 48h from now.
 // Ogranicz laczny wzrost czasu decyzji do max 48h od teraz.
            $maxDue = time() + 48 * 3600;
            $currentDue = strtotime($neg['decision_due_at']);
            if ($currentDue >= $maxDue) {
                return null;
            }

 // Roll event chance at 25% per call.
 // Losuj szanse zdarzenia 25% na jedno wywolanie.
            if (rand(1, 100) > 25) {
                return null;
            }

            $playerId = (int)$neg['pid'];
            $trustScore = $this->getTrustScore($playerId);

            $negative = [
                ['key' => 'analityk_niespojnosc', 'type' => 'delay', 'add_hours' => rand(1, 3), 'trust_evt' => null, 'template' => 'bank_neg.evt_inconsistency'],
                ['key' => 'cena_ropa_spada', 'type' => 'fee_increase', 'add_hours' => rand(1, 2), 'trust_evt' => null, 'template' => 'bank_neg.evt_oil_price_drop'],
                ['key' => 'audyt_knf', 'type' => 'delay', 'add_hours' => rand(3, 5), 'trust_evt' => null, 'template' => 'bank_neg.evt_knf_audit'],
                ['key' => 'brak_kworum', 'type' => 'delay', 'add_hours' => rand(2, 4), 'trust_evt' => null, 'template' => 'bank_neg.evt_no_quorum'],
                ['key' => 'ubezpieczyciel', 'type' => 'fee_increase', 'add_hours' => 0, 'trust_evt' => null, 'template' => 'bank_neg.evt_insurer'],
                ['key' => 'analityk_chorobowe', 'type' => 'delay', 'add_hours' => rand(3, 5), 'trust_evt' => null, 'template' => 'bank_neg.evt_analyst_sick'],
                ['key' => 'agencja_ratingowa', 'type' => 'delay', 'add_hours' => rand(1, 3), 'trust_evt' => null, 'template' => 'bank_neg.evt_rating_agency'],
                ['key' => 'system_serwis', 'type' => 'delay', 'add_hours' => rand(1, 3), 'trust_evt' => null, 'template' => 'bank_neg.evt_system_maintenance'],
                ['key' => 'przepisy_knf', 'type' => 'delay', 'add_hours' => rand(2, 5), 'trust_evt' => null, 'template' => 'bank_neg.evt_knf_regulations'],
                ['key' => 'volatility_wysoka', 'type' => 'delay', 'add_hours' => rand(3, 5), 'trust_evt' => null, 'template' => 'bank_neg.evt_high_volatility'],
                ['key' => 'kurs_walutowy', 'type' => 'fee_increase', 'add_hours' => 0, 'trust_evt' => null, 'template' => 'bank_neg.evt_exchange_rate'],
                [
                    'key' => 'trust_weryfikacja',
                    'type' => 'delay',
                    'add_hours' => rand(2, 4),
                    'trust_evt' => null,
                    'template' => 'bank_neg.evt_trust_verification',
                    'condition' => $trustScore < 40,
                ],
                [
                    'key' => 'zbyt_czesto',
                    'type' => 'trust_penalty',
                    'add_hours' => 0,
                    'trust_evt' => 'zbyt_czesto_wniosek',
                    'template' => 'bank_neg.evt_too_frequent',
                    'condition' => $this->getNegotiationCountThisMonth($playerId) >= 2,
                ],
            ];

            $positive = [
                ['key' => 'komitet_wczesniej', 'type' => 'speedup', 'sub_hours' => rand(1, 3), 'template' => 'bank_neg.evt_committee_early'],
                ['key' => 'cena_ropa_rosnie', 'type' => 'fee_decrease', 'sub_hours' => 0, 'template' => 'bank_neg.evt_oil_price_rise'],
                ['key' => 'kampania_banku', 'type' => 'fee_decrease', 'sub_hours' => 0, 'template' => 'bank_neg.evt_bank_campaign'],
                ['key' => 'stabilizacja_rynku', 'type' => 'fee_decrease', 'sub_hours' => 0, 'template' => 'bank_neg.evt_market_stabilization'],
                ['key' => 'nowy_odwiert', 'type' => 'approval_boost', 'sub_hours' => 0, 'template' => 'bank_neg.evt_new_well'],
                ['key' => 'konkurencja', 'type' => 'fee_decrease', 'sub_hours' => 0, 'template' => 'bank_neg.evt_competition'],
                [
                    'key' => 'priorytety_banku',
                    'type' => 'speedup',
                    'sub_hours' => rand(1, 2),
                    'template' => 'bank_neg.evt_bank_priority',
                    'condition' => $trustScore >= 60,
                ],
            ];

 // Filter conditional events before choosing a pool.
 // Odfiltruj zdarzenia warunkowe przed wyborem puli.
            $negative = array_filter($negative, fn($event) => !isset($event['condition']) || $event['condition']);
            $positive = array_filter($positive, fn($event) => !isset($event['condition']) || $event['condition']);

 // Pick negative events 60% of the time, positive 40%.
 // Wybieraj negatywne zdarzenia w 60%, pozytywne w 40%.
            $pool = (rand(1, 100) <= 60) ? array_values($negative) : array_values($positive);
            if (empty($pool)) {
                return null;
            }

            $event = $pool[array_rand($pool)];

 // Replace {hours} placeholder with correctly formatted time text.
 // Zastap placeholder {hours} poprawnie sformatowanym czasem.
            $hoursFormatted = isset($event['add_hours']) && $event['add_hours'] > 0
                ? $this->formatHours($event['add_hours'])
                : ((isset($event['sub_hours']) && $event['sub_hours'] > 0)
                    ? $this->formatHours($event['sub_hours'])
                    : '');
            $message = t($event['template'], ['hours' => $hoursFormatted]);

 // Apply event effects while respecting the total time cap.
 // Zastosuj efekty zdarzenia z zachowaniem limitu czasu.
            $newDue = null;
            if (($event['add_hours'] ?? 0) > 0) {
                $addSeconds = (int)($event['add_hours'] * 3600);
                $proposed = strtotime($neg['decision_due_at']) + $addSeconds;
                $cappedDue = min($proposed, $maxDue);
                $newDue = date('Y-m-d H:i:s', $cappedDue);
                $this->db->prepare(
                    "UPDATE bank_negotiations SET decision_due_at=:due WHERE id=:id"
                )->execute([':due' => $newDue, ':id' => $negotiationId]);
            }
            if (($event['sub_hours'] ?? 0) > 0) {
                $newDue = date(
                    'Y-m-d H:i:s',
                    max(time() + 1800, strtotime($neg['decision_due_at']) - (int)($event['sub_hours'] * 3600))
                );
                $this->db->prepare(
                    "UPDATE bank_negotiations SET decision_due_at=:due WHERE id=:id"
                )->execute([':due' => $newDue, ':id' => $negotiationId]);
            }
            if (!empty($event['trust_evt'])) {
                $this->adjustTrustScore($neg['player_id'], $event['trust_evt']);
            }

 // Persist the fired event for history and UI.
 // Zapisz uruchomione zdarzenie do historii i UI.
            $this->db->prepare("
                INSERT INTO bank_negotiation_events
                    (negotiation_id, event_key, event_type, message, hours_added, created_at)
                VALUES (:nid, :key, :type, :msg, :h, NOW())
            ")->execute([
                ':nid' => $negotiationId,
                ':key' => $event['key'],
                ':type' => $event['type'],
                ':msg' => $message,
                ':h' => $event['add_hours'] ?? -($event['sub_hours'] ?? 0),
            ]);

            return [
                'type' => $event['type'],
                'message' => $message,
                'new_due' => $newDue,
            ];
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'triggerRandomEvent FAILED', $e);
            return null;
        }
    }
}
