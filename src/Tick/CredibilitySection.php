<?php

declare(strict_types=1);

/**
 * CredibilitySection - tick wiarygodnosci firmy.
 * CredibilitySection - company credibility tick.
 */
class CredibilitySection
{
    public int $playersChecked = 0;
    public int $cleanBonuses = 0;

    private PDO $db;
    private DateTimeInterface $now;

    public function __construct(PDO $db, DateTimeInterface $now)
    {
        $this->db = $db;
        $this->now = $now;
    }

    public function run(): void
    {
        try {
            $players = $this->db->query("SELECT id FROM players ORDER BY id ASC")
                ->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CredibilitySection', 'fetch players FAILED', $e);
            }
            return;
        }

        $service = new CompanyCredibilityService($this->db);

        foreach ($players as $playerId) {
            $pid = (int)$playerId;
            if ($pid <= 0) {
                continue;
            }

            $this->playersChecked++;
            try {
                if ($service->applyCleanOperationBonus($pid, CompanyCredibilityService::CLEAN_OPERATION_DAYS, $this->now)) {
                    $this->cleanBonuses++;
                }
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('CredibilitySection', 'clean operation bonus FAILED', $e, [
                        'player_id' => $pid,
                    ]);
                }
            }
        }
    }
}
