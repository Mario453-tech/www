<?php
require_once __DIR__ . '/TrainingSkillRegistry.php';
require_once __DIR__ . '/../FinancialTransactionService.php';
require_once __DIR__ . '/../DirectorNotificationService.php';

/**
 * TrainingService - logika systemu szkolen pracownikow.
 * TrainingService - employee training system logic.
 *
 * Odpowiada za: zapis na kurs (z pobraniem oplaty), przeprowadzenie egzaminu
 * po zakonczeniu kursu oraz zapytania dla widoku gracza.
 * Handles: enrollment (with fee), exam roll at completion, and player-view queries.
 */
class TrainingService
{
    /** Blokada ponownego podejscia po oblaniu (godziny). */
    private const COOLDOWN_HOURS = 12;

    /** Klamry szansy zdania - nigdy 100% (za latwo) ani 0% (bez sensu). */
    private const MIN_PASS_CHANCE = 5;
    private const MAX_PASS_CHANCE = 95;

    private TrainingSkillRegistry $skills;

    public function __construct(private readonly PDO $db)
    {
        $this->skills = TrainingSkillRegistry::build();
        GameLog::info('TrainingService', 'init');
    }

    /**
     * Zapisuje pracownika na kurs: walidacja, pobranie oplaty, utworzenie wpisu.
     * Enrolls a staff member: validation, fee debit, record creation.
     *
     * @return array{success:bool, message:string, training_id?:int}
     */
    public function enroll(int $playerId, string $staffType, int $staffId, int $programId): array
    {
        try {
            $program = $this->loadProgram($programId);
            if ($program === null || (int)$program['enabled'] !== 1) {
                return ['success' => false, 'message' => tPlain('training.err.program_unavailable')];
            }
            if ($program['department'] !== $staffType) {
                return ['success' => false, 'message' => tPlain('training.err.wrong_department')];
            }

            $skill = $this->skills->get($staffType, (string)$program['target_skill']);
            if ($skill === null) {
                return ['success' => false, 'message' => tPlain('training.err.program_unavailable')];
            }

            // Poziom umiejetnosci 0 = pracownik nie nalezy do gracza (izolacja).
            $level = $skill->getCurrentLevel($this->db, $playerId, $staffId);
            if ($level === 0) {
                return ['success' => false, 'message' => tPlain('training.err.not_owner')];
            }
            if ($level >= $skill->getMaxLevel()) {
                return ['success' => false, 'message' => tPlain('training.err.skill_maxed')];
            }

            if ($this->hasActiveTraining($playerId, $staffType, $staffId)) {
                return ['success' => false, 'message' => tPlain('training.err.already_training')];
            }

            $cooldownUntil = $this->cooldownUntil($playerId, $staffType, $staffId, $programId);
            if ($cooldownUntil !== null) {
                return ['success' => false, 'message' => tPlain('training.err.on_cooldown')];
            }

            $cost        = (float)$program['cost'];
            $retryCount  = $this->countPreviousFails($playerId, $staffType, $staffId, $programId);
            $finishesAt  = (new DateTime())
                ->modify('+' . (int)$program['duration_hours'] . ' hours')
                ->format('Y-m-d H:i:s');

            // Atomowo: oplata + wpis. debitCombined dolacza do tej transakcji (nie domyka jej).
            // Atomic: fee + record. debitCombined joins this tx (does not commit it).
            $this->db->beginTransaction();
            try {
                $fts = new FinancialTransactionService($this->db);
                $payment = $fts->debitCombined(
                    $playerId,
                    $cost,
                    FinancialTransactionService::TYPE_TRAINING_FEE,
                    tPlain('training.tx_fee', ['program' => (string)$program['name_pl']]),
                    'training_program',
                    $programId
                );
                if (empty($payment['success'])) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => tPlain('training.err.insufficient_funds')];
                }

                $stmt = $this->db->prepare(
                    "INSERT INTO staff_trainings
                        (player_id, staff_type, staff_id, program_id, status,
                         started_at, finishes_at, retry_count, skill_before, cost_paid)
                     VALUES (?, ?, ?, ?, 'in_progress', NOW(), ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $playerId, $staffType, $staffId, $programId,
                    $finishesAt, $retryCount, $level, (int)round($cost),
                ]);
                $trainingId = (int)$this->db->lastInsertId();

                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }

            GameLog::info('TrainingService', 'enrolled', [
                'player_id' => $playerId, 'staff_type' => $staffType,
                'staff_id' => $staffId, 'program_id' => $programId, 'training_id' => $trainingId,
            ]);

            return [
                'success'     => true,
                'message'     => tPlain('training.msg.enrolled', ['program' => (string)$program['name_pl']]),
                'training_id' => $trainingId,
            ];
        } catch (Throwable $e) {
            GameLog::error('TrainingService', 'enroll failed', $e, [
                'player_id' => $playerId, 'staff_id' => $staffId, 'program_id' => $programId,
            ]);
            return ['success' => false, 'message' => tPlain('training.err.generic')];
        }
    }

    /**
     * Przeprowadza egzaminy dla zakonczonych szkolen danego gracza.
     * Runs exams for the player's finished trainings. Returns count processed.
     */
    public function processFinishedExams(int $playerId): int
    {
        $processed = 0;
        try {
            $stmt = $this->db->prepare(
                "SELECT st.*, tp.target_skill, tp.base_pass_rate, tp.name_pl, tp.name_en, tp.code
                   FROM staff_trainings st
                   JOIN training_programs tp ON tp.id = st.program_id
                  WHERE st.player_id = ? AND st.status = 'in_progress'
                        AND st.finishes_at <= NOW()"
            );
            $stmt->execute([$playerId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if ($this->runExam($playerId, $row)) {
                    $processed++;
                }
            }
        } catch (Throwable $e) {
            GameLog::error('TrainingService', 'processFinishedExams failed', $e, ['player_id' => $playerId]);
        }
        return $processed;
    }

    /**
     * Pojedynczy egzamin: losowanie, aktualizacja stanu, powiadomienie.
     * Single exam: roll, state update, notification.
     */
    private function runExam(int $playerId, array $row): bool
    {
        $staffType = (string)$row['staff_type'];
        $staffId   = (int)$row['staff_id'];
        $skill     = $this->skills->get($staffType, (string)$row['target_skill']);
        if ($skill === null) {
            GameLog::warn('TrainingService', 'unknown skill in exam', ['training_id' => $row['id']]);
            return false;
        }

        $staffData   = $this->loadStaffData($playerId, $staffType, $staffId);
        $levelBefore = $skill->getCurrentLevel($this->db, $playerId, $staffId);
        $retryCount  = (int)$row['retry_count'];

        $passChance = (int)$row['base_pass_rate']
            + $skill->passRateModifier($staffData, $levelBefore)
            + $this->ambitionOf($staffData)
            + min($retryCount * 10, 30);
        $passChance = max(self::MIN_PASS_CHANCE, min(self::MAX_PASS_CHANCE, $passChance));

        $passMin = 100 - $passChance;
        $score   = random_int(1, 100);
        $passed  = $score >= $passMin;

        try {
            $this->db->beginTransaction();

            if ($passed) {
                $levelAfter = $skill->applyIncrement($this->db, $playerId, $staffId);
                $this->db->prepare(
                    "UPDATE staff_trainings
                        SET status='passed', exam_score=?, exam_pass_min=?,
                            skill_before=?, skill_after=?
                      WHERE id=? AND player_id=?"
                )->execute([$score, $passMin, $levelBefore, $levelAfter, $row['id'], $playerId]);

                $this->issueCertificate($playerId, $staffType, $staffId, (int)$row['id'], $row, $score, $levelAfter);
            } else {
                $cooldown = (new DateTime())
                    ->modify('+' . self::COOLDOWN_HOURS . ' hours')
                    ->format('Y-m-d H:i:s');
                $this->db->prepare(
                    "UPDATE staff_trainings
                        SET status='failed', exam_score=?, exam_pass_min=?,
                            skill_before=?, cooldown_until=?
                      WHERE id=? AND player_id=?"
                )->execute([$score, $passMin, $levelBefore, $cooldown, $row['id'], $playerId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('TrainingService', 'runExam failed', $e, ['training_id' => $row['id']]);
            return false;
        }

        $this->notifyExamResult($playerId, $staffData, $row, $passed, $score, $passMin);
        return true;
    }

    /**
     * Wystawia certyfikat po zdanym egzaminie (technik lub czlonek zarzadu).
     * Issues a certificate after a passed exam (technical staff or board member).
     */
    private function issueCertificate(
        int $playerId, string $staffType, int $staffId, int $trainingId,
        array $row, int $score, int $levelAfter
    ): void {
        $this->db->prepare(
            "INSERT INTO training_certificates
                (player_id, staff_type, staff_id, training_id, program_code,
                 program_name, skill_code, score, level_after, issued_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([
            $playerId, $staffType, $staffId, $trainingId,
            (string)$row['code'], (string)$row['name_pl'], (string)$row['target_skill'],
            $score, $levelAfter,
        ]);
    }

    /** Powiadomienie o wyniku egzaminu - nigdy nie przerywa glownego przeplywu. */
    private function notifyExamResult(int $playerId, array $staffData, array $row, bool $passed, int $score, int $passMin): void
    {
        try {
            $name = trim(($staffData['first_name'] ?? '') . ' ' . ($staffData['last_name'] ?? ''));
            $notif = new DirectorNotificationService($this->db);
            if ($passed) {
                $notif->create($playerId, 'training_passed', [
                    'name'    => $name,
                    'program' => (string)$row['name_pl'],
                    'score'   => $score,
                    'level'   => (int)($row['skill_before'] ?? 0) + 1,
                ]);
            } else {
                $notif->create($playerId, 'training_failed', [
                    'name'    => $name,
                    'program' => (string)$row['name_pl'],
                    'score'   => $score,
                    'min'     => $passMin,
                    'hours'   => self::COOLDOWN_HOURS,
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('TrainingService', 'notifyExamResult failed', $e, ['player_id' => $playerId]);
        }
    }

    // ============================================================
    // Zapytania pomocnicze / Helper queries
    // ============================================================

    /** @return array<int,array<string,mixed>> dostepne kursy dla dzialu (tylko wlaczone). */
    public function getAvailablePrograms(string $department): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM training_programs
              WHERE department = ? AND enabled = 1
              ORDER BY target_skill, base_pass_rate DESC"
        );
        $stmt->execute([$department]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Aktywne szkolenia gracza dla danego dzialu. */
    public function getActiveTrainings(int $playerId, string $department): array
    {
        $stmt = $this->db->prepare(
            "SELECT st.*, tp.name_pl, tp.name_en, tp.target_skill, tp.duration_hours
               FROM staff_trainings st
               JOIN training_programs tp ON tp.id = st.program_id
              WHERE st.player_id = ? AND st.staff_type = ? AND st.status = 'in_progress'
              ORDER BY st.finishes_at ASC"
        );
        $stmt->execute([$playerId, $department]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Zdobyte certyfikaty gracza dla danego dzialu (najnowsze pierwsze). */
    public function getCertificates(int $playerId, string $department, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare(
            "SELECT * FROM training_certificates
              WHERE player_id = ? AND staff_type = ?
              ORDER BY issued_at DESC
              LIMIT {$limit}"
        );
        $stmt->execute([$playerId, $department]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Historia zakonczonych szkolen gracza (passed/failed/cancelled). */
    public function getHistory(int $playerId, string $department, int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            "SELECT st.*, tp.name_pl, tp.name_en, tp.target_skill
               FROM staff_trainings st
               JOIN training_programs tp ON tp.id = st.program_id
              WHERE st.player_id = ? AND st.staff_type = ? AND st.status <> 'in_progress'
              ORDER BY st.started_at DESC
              LIMIT {$limit}"
        );
        $stmt->execute([$playerId, $department]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // Wewnetrzne / Internal
    // ============================================================

    private function loadProgram(int $programId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM training_programs WHERE id = ? LIMIT 1");
        $stmt->execute([$programId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function hasActiveTraining(int $playerId, string $staffType, int $staffId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM staff_trainings
              WHERE player_id = ? AND staff_type = ? AND staff_id = ? AND status = 'in_progress'
              LIMIT 1"
        );
        $stmt->execute([$playerId, $staffType, $staffId]);
        return (bool)$stmt->fetchColumn();
    }

    private function cooldownUntil(int $playerId, string $staffType, int $staffId, int $programId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT cooldown_until FROM staff_trainings
              WHERE player_id = ? AND staff_type = ? AND staff_id = ? AND program_id = ?
                    AND status = 'failed' AND cooldown_until IS NOT NULL AND cooldown_until > NOW()
              ORDER BY cooldown_until DESC LIMIT 1"
        );
        $stmt->execute([$playerId, $staffType, $staffId, $programId]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (string)$val;
    }

    private function countPreviousFails(int $playerId, string $staffType, int $staffId, int $programId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM staff_trainings
              WHERE player_id = ? AND staff_type = ? AND staff_id = ? AND program_id = ?
                    AND status = 'failed'"
        );
        $stmt->execute([$playerId, $staffType, $staffId, $programId]);
        return (int)$stmt->fetchColumn();
    }

    private function loadStaffData(int $playerId, string $staffType, int $staffId): array
    {
        if ($staffType === 'board') {
            $stmt = $this->db->prepare(
                "SELECT first_name, last_name, trait_ambition
                   FROM board_members WHERE id = ? AND player_id = ? LIMIT 1"
            );
        } else {
            $stmt = $this->db->prepare(
                "SELECT first_name, last_name
                   FROM technical_staff WHERE id = ? AND player_id = ? LIMIT 1"
            );
        }
        $stmt->execute([$staffId, $playerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Modyfikator ambicji (tylko zarzad ma trait_ambition). */
    private function ambitionOf(array $staffData): int
    {
        if (!isset($staffData['trait_ambition'])) {
            return 0;
        }
        return ((int)$staffData['trait_ambition'] - 5) * 2;
    }
}
