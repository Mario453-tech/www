<?php

require_once __DIR__ . '/HR/RecruitmentTrait.php';
require_once __DIR__ . '/HR/HiringTrait.php';
require_once __DIR__ . '/HR/EventsTrait.php';
require_once __DIR__ . '/HR/DataTrait.php';

/**
 * Facade for the HR module.
 * PL: Fasada modulu HR.
 *
 * Logic is split into traits in src/HR/.
 * PL: Logika jest podzielona na traity w src/HR/.
 *   - RecruitmentTrait.php - recruitment start and ready processing
 *      PL: start rekrutacji i przetwarzanie gotowych
 *   - HiringTrait.php - candidate hiring flow and private helpers
 *      PL: flow zatrudniania kandydatow i helpery prywatne
 *   - EventsTrait.php - firing, events and expiring contracts
 *      PL: zwalnianie, eventy i wygasajace kontrakty
 *   - DataTrait.php - getters, reject/save candidate, renew contract
 *      PL: gettery, odrzucanie i zapis kandydata, odnowienie kontraktu
 */
class HRService
{
    use HRRecruitmentTrait;
    use HRHiringTrait;
    use HREventsTrait;
    use HRDataTrait;

    private PDO $db;
    private CandidateGenerator $generator;

    // Recruitment duration in seconds.
    // PL: Czas rekrutacji w sekundach.
    /** @var array<string, array<int, int>> */
    private static array $recruitDuration = [
        'local'         => [120, 240],
        'international' => [180, 300],
    ];

    // Informational mapping of region scope.
    // PL: Informacyjne mapowanie zakresu regionow.
    /** @var array<string, string> */
    private static array $regionScope = [
        'PL'    => 'local',
        'EU'    => 'local',
        'NO'    => 'international',
        'US_CA' => 'international',
        'ME'    => 'international',
        'RU'    => 'local',
        'ASIA'  => 'international',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->generator = new CandidateGenerator($this->db);

        try {
            Database::addColumnIfMissing('board_members', 'member_type', "ENUM('director','staff') NOT NULL DEFAULT 'director' AFTER player_id");
        } catch (Throwable $e) {
            GameLog::error('HRService', 'member_type schema guard failed', $e);
        }
    }
}
