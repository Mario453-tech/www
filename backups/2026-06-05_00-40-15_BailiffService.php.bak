<?php

/**
 * Handles bailiff proceedings for overdue loans.
 * PL: Obsluguje postepowania komornicze dla opoznionych kredytow.
 *
 * Proceeding stages:
 * PL: Etapy postepowania:
 * 1. Warning with 24h repayment window.
 * PL: Ostrzezenie z 24h oknem na splate.
 * 2. Seizure of 30% cash after another 24h.
 * PL: Zajecie 30% gotowki po kolejnych 24h.
 * 3. Seizure of 50% stored oil after 48h.
 * PL: Zajecie 50% ropy z magazynu po 48h.
 * 4. Seizure of wells one by one every 72h, then bankruptcy.
 * PL: Zajecie odwiertow co 72h, a potem bankructwo.
 */
class BailiffService
{
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            GameLog::info('BailiffService', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('BailiffService', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function process(): void
    {
        $this->startNewProceedings();
        $this->processActiveProceedings();
    }

    private function startNewProceedings(): void
    {
 // Start proceedings for loans late by more than 24h without active bailiff flow.
 // PL: Startuj postepowanie dla kredytow opoznionych ponad 24h bez aktywnego flow komornika.
        $stmt = $this->db->query("
            SELECT l.*
            FROM loans l
            LEFT JOIN bailiff_proceedings bp
                   ON l.id = bp.loan_id AND bp.status = 'active'
            WHERE l.status = 'late'
              AND bp.id IS NULL
              AND l.late_since < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

        foreach ($stmt->fetchAll() as $loan) {
            $this->db->prepare("
                INSERT INTO bailiff_proceedings
                    (loan_id, player_id, stage, next_action_at, started_at, status)
                VALUES
                    (:loan_id, :player_id, 1, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), 'active')
            ")->execute([
                ':loan_id' => $loan['id'],
                ':player_id' => $loan['player_id'],
            ]);
        }
    }

    private function processActiveProceedings(): void
    {
        $stmt = $this->db->query("
            SELECT * FROM bailiff_proceedings
            WHERE status = 'active' AND next_action_at <= NOW()
        ");

        foreach ($stmt->fetchAll() as $proc) {
            $this->executeStage($proc);
        }
    }

    private function executeStage(array $proc): void
    {
 // Always check if the debt has already been cleared.
 // PL: Zawsze najpierw sprawdz czy dlug nie zostal juz splacony.
        if ($this->isDebtCleared((int)$proc['loan_id'])) {
            $this->completeProceeding((int)$proc['id']);
            return;
        }

        switch ((int)$proc['stage']) {
            case 1:
                $this->stage1Warning($proc);
                break;
            case 2:
                $this->stage2SeizeCash($proc);
                break;
            case 3:
                $this->stage3SeizeOil($proc);
                break;
            case 4:
                $this->stage4SeizeWells($proc);
                break;
            default:
                $this->declareBankruptcy($proc);
        }
    }

    private function isDebtCleared(int $loanId): bool
    {
        $stmt = $this->db->prepare("SELECT status FROM loans WHERE id = :id");
        $stmt->execute([':id' => $loanId]);
        $loan = $stmt->fetch();
        return $loan && $loan['status'] !== 'late';
    }

 // Stage 1: warning only.
 // PL: Etap 1: samo ostrzezenie.
    private function stage1Warning(array $proc): void
    {
        $this->db->prepare("
            UPDATE bailiff_proceedings
            SET stage = 2, next_action_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            WHERE id = :id
        ")->execute([':id' => $proc['id']]);
    }

 // Stage 2: seize 30% of player cash.
 // PL: Etap 2: zajmij 30% gotowki gracza.
    private function stage2SeizeCash(array $proc): void
    {
        $playerStmt = $this->db->prepare("SELECT cash FROM players WHERE id = :id");
        $playerStmt->execute([':id' => $proc['player_id']]);
        $player = $playerStmt->fetch();

        if ($player && (float)$player['cash'] > 0) {
            $seized = (int)round((float)$player['cash'] * 0.30);

            $this->db->prepare("UPDATE players SET cash = cash - :amt WHERE id = :id")
                ->execute([':amt' => $seized, ':id' => $proc['player_id']]);

            $this->db->prepare("UPDATE loans SET remaining_amount = remaining_amount - :amt WHERE id = :id")
                ->execute([':amt' => $seized, ':id' => $proc['loan_id']]);

            $this->db->prepare("UPDATE bailiff_proceedings SET cash_seized = cash_seized + :amt WHERE id = :id")
                ->execute([':amt' => $seized, ':id' => $proc['id']]);

            $this->logPayment((int)$proc['loan_id'], (int)$proc['player_id'], $seized, 'bailiff_seizure');
        }

        if ($this->isDebtCleared((int)$proc['loan_id'])) {
            $this->completeProceeding((int)$proc['id']);
            return;
        }

        $this->db->prepare("
            UPDATE bailiff_proceedings
            SET stage = 3, next_action_at = DATE_ADD(NOW(), INTERVAL 48 HOUR)
            WHERE id = :id
        ")->execute([':id' => $proc['id']]);
    }

 // Stage 3: seize 50% of stored oil and convert it using current market price.
 // PL: Etap 3: zajmij 50% ropy z magazynu i przelicz po biezacej cenie rynku.
    private function stage3SeizeOil(array $proc): void
    {
        $storStmt = $this->db->prepare("SELECT used FROM storage WHERE player_id = :id");
        $storStmt->execute([':id' => $proc['player_id']]);
        $storage = $storStmt->fetch();

        if ($storage && (float)$storage['used'] > 0) {
            $seizedOil = (int)round((float)$storage['used'] * 0.50);

            $priceRow = $this->db->query("SELECT current_price FROM market_state WHERE id = 1")->fetch();
            $price = $priceRow ? (float)$priceRow['current_price'] : 1.0;
            $cashValue = round($seizedOil * $price);

            $this->db->prepare("UPDATE storage SET used = used - :amt WHERE player_id = :id")
                ->execute([':amt' => $seizedOil, ':id' => $proc['player_id']]);

            $this->db->prepare("UPDATE loans SET remaining_amount = GREATEST(0, remaining_amount - :amt) WHERE id = :id")
                ->execute([':amt' => $cashValue, ':id' => $proc['loan_id']]);

            $this->db->prepare("UPDATE bailiff_proceedings SET oil_seized = oil_seized + :amt WHERE id = :id")
                ->execute([':amt' => $seizedOil, ':id' => $proc['id']]);

            $this->logPayment((int)$proc['loan_id'], (int)$proc['player_id'], $cashValue, 'bailiff_seizure');
        }

        if ($this->isDebtCleared((int)$proc['loan_id'])) {
            $this->completeProceeding((int)$proc['id']);
            return;
        }

        $this->db->prepare("
            UPDATE bailiff_proceedings
            SET stage = 4, next_action_at = DATE_ADD(NOW(), INTERVAL 72 HOUR)
            WHERE id = :id
        ")->execute([':id' => $proc['id']]);
    }

 // Stage 4: seize wells one by one until no assets remain, then declare bankruptcy.
 // PL: Etap 4: zajmuj odwierty po kolei, az skoncza sie aktywa i oglos bankructwo.
    private function stage4SeizeWells(array $proc): void
    {
        $wellStmt = $this->db->prepare("
            SELECT id FROM wells
            WHERE player_id = :pid AND status != 'seized'
            ORDER BY level DESC, base_production_per_hour DESC
            LIMIT 1
        ");
        $wellStmt->execute([':pid' => $proc['player_id']]);
        $well = $wellStmt->fetch();

        if ($well) {
            $this->db->prepare("UPDATE wells SET status = 'seized' WHERE id = :id")
                ->execute([':id' => $well['id']]);

            $this->db->prepare("UPDATE bailiff_proceedings SET wells_seized = wells_seized + 1 WHERE id = :id")
                ->execute([':id' => $proc['id']]);

            $remaining = $this->db->prepare("
                SELECT COUNT(*) FROM wells WHERE player_id = :pid AND status != 'seized'
            ");
            $remaining->execute([':pid' => $proc['player_id']]);

            if ((int)$remaining->fetchColumn() === 0) {
                $this->declareBankruptcy($proc);
                return;
            }

            if (!$this->isDebtCleared((int)$proc['loan_id'])) {
                $this->db->prepare("
                    UPDATE bailiff_proceedings
                    SET next_action_at = DATE_ADD(NOW(), INTERVAL 72 HOUR)
                    WHERE id = :id
                ")->execute([':id' => $proc['id']]);
            } else {
                $this->completeProceeding((int)$proc['id']);
            }
            return;
        }

        $this->declareBankruptcy($proc);
    }

 // Bankruptcy fallback when all enforcement options are exhausted.
 // PL: Bankructwo awaryjne, gdy komornik wyczerpal wszystkie opcje egzekucji.
    private function declareBankruptcy(array $proc): void
    {
        $this->db->prepare("
            UPDATE players
            SET status = 'bankrupt',
                bankruptcy_at = NOW(),
                recovery_mode = 1,
                bankruptcy_status = 'restructuring',
                credit_score = GREATEST(0, credit_score - 120)
            WHERE id = :id
        ")->execute([':id' => $proc['player_id']]);

        $this->db->prepare("
            UPDATE loans SET status = 'defaulted' WHERE id = :id
        ")->execute([':id' => $proc['loan_id']]);

        $this->db->prepare("
            UPDATE bailiff_proceedings SET status = 'bankruptcy', completed_at = NOW() WHERE id = :id
        ")->execute([':id' => $proc['id']]);

        $this->db->prepare("
            INSERT INTO technical_notifications (player_id, well_id, type, message)
            VALUES (:pid, NULL, 'task', :msg)
        ")->execute([
            ':pid' => $proc['player_id'],
            ':msg' => t('bailiff.bankruptcy_notification'),
        ]);

        $this->db->prepare("
            INSERT INTO bankruptcy_events (player_id, event_type, message, severity, is_critical, due_at, created_at)
            VALUES (:pid, 'bankruptcy_declared', :msg, 'critical', 1, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())
        ")->execute([
            ':pid' => $proc['player_id'],
            ':msg' => t('bailiff.bankruptcy_event'),
        ]);
    }

    private function completeProceeding(int $procId): void
    {
        $this->db->prepare("
            UPDATE bailiff_proceedings SET status = 'completed', completed_at = NOW() WHERE id = :id
        ")->execute([':id' => $procId]);
    }

    private function logPayment(int $loanId, int $playerId, float $amount, string $type): void
    {
        $this->db->prepare("
            INSERT INTO loan_payments (loan_id, player_id, amount, payment_type, created_at)
            VALUES (:lid, :pid, :amt, :type, NOW())
        ")->execute([
            ':lid' => $loanId,
            ':pid' => $playerId,
            ':amt' => $amount,
            ':type' => $type,
        ]);
    }
}
