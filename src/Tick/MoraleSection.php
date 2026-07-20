<?php

class MoraleSection
{
    private PDO $db;
    public int $moraleUpdates = 0;
    public int $strikesStarted = 0;
    public int $strikesEnded = 0;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function run(): void
    {
        $this->updateMoraleTowardsBase();
        $this->handleStrikes();
    }

    private function updateMoraleTowardsBase(): void
    {
        // current_morale wzrasta gdy < base_morale
        $stmtUp = $this->db->prepare("UPDATE technical_staff SET current_morale = current_morale + 1 WHERE current_morale < base_morale AND status != 'fired'");
        $stmtUp->execute();
        $this->moraleUpdates += $stmtUp->rowCount();

        // current_morale spada gdy > base_morale
        $stmtDown = $this->db->prepare("UPDATE technical_staff SET current_morale = current_morale - 1 WHERE current_morale > base_morale AND status != 'fired'");
        $stmtDown->execute();
        $this->moraleUpdates += $stmtDown->rowCount();
    }

    private function handleStrikes(): void
    {
        // Sprawdz potencjalne nowe strajki (morale < 15, brak aktywnego strajku)
        $potentialStrikers = $this->db->query("
            SELECT ts.id 
            FROM technical_staff ts
            LEFT JOIN staff_strikes ss ON ss.technical_staff_id = ts.id AND ss.end_time IS NULL
            WHERE ts.current_morale < 15 AND ss.id IS NULL AND ts.status != 'fired'
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($potentialStrikers as $staffId) {
            // 5% szans na wybuch strajku w kazdym ticku
            if (mt_rand(1, 100) <= 5) {
                StrikeService::startStrike((int)$staffId, 'hr.strike.reason.low_morale');
                $this->strikesStarted++;
            }
        }

        // Sprawdz potencjalne zakonczenia strajku (morale > 30, trwajacy strajk)
        $potentialEndings = $this->db->query("
            SELECT ts.id 
            FROM technical_staff ts
            JOIN staff_strikes ss ON ss.technical_staff_id = ts.id AND ss.end_time IS NULL
            WHERE ts.current_morale > 30 AND ts.status != 'fired'
        ")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($potentialEndings as $staffId) {
            // 50% szans na automatyczne zakonczenie strajku gdy morale podrosnie
            if (mt_rand(1, 100) <= 50) {
                StrikeService::resolveStrike((int)$staffId);
                $this->strikesEnded++;
            }
        }
    }
}
