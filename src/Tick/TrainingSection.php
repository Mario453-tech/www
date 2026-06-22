<?php

declare(strict_types=1);

require_once __DIR__ . '/../Training/TrainingService.php';

/**
 * TrainingSection - tick przeprowadzajacy egzaminy zakonczonych szkolen.
 * TrainingSection - tick running exams for finished trainings.
 *
 * PL: Raz per tick pobiera graczy z zakonczonymi szkoleniami (finishes_at <= NOW)
 * i dla kazdego uruchamia egzaminy przez TrainingService.
 * EN: Once per tick, finds players with finished trainings and runs their exams.
 */
class TrainingSection
{
    public int $examined = 0;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function run(): void
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT player_id FROM staff_trainings
                  WHERE status = 'in_progress' AND finishes_at <= NOW()"
            );
            $stmt->execute();
            $playerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('TrainingSection', 'fetch finished trainings FAILED', $e);
            }
            return;
        }

        if (empty($playerIds)) {
            return;
        }

        $service = new TrainingService($this->db);
        foreach ($playerIds as $pid) {
            try {
                $this->examined += $service->processFinishedExams((int)$pid);
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('TrainingSection', 'processFinishedExams FAILED', $e, ['player_id' => $pid]);
                }
            }
        }
    }
}
