<?php
require_once __DIR__ . '/Employee/TechnicalStaffProfile.php';
require_once __DIR__ . '/EmployeeSystemBootstrap.php';
require_once __DIR__ . '/Employee/EmployeeSystemConfigService.php';
require_once __DIR__ . '/HR/StrikeEffectService.php';
require_once __DIR__ . '/HR/EmployeeDeadlockRetry.php';

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
 * - RecruitmentTrait.php - recruitment start and ready processing
 * PL: start rekrutacji i przetwarzanie gotowych
 * - HiringTrait.php - candidate hiring flow and private helpers
 * PL: flow zatrudniania kandydatow i helpery prywatne
 * - EventsTrait.php - firing, events and expiring contracts
 * PL: zwalnianie, eventy i wygasajace kontrakty
 * - DataTrait.php - getters, reject/save candidate, renew contract
 * PL: gettery, odrzucanie i zapis kandydata, odnowienie kontraktu
 */
class HRService
{
    use HRRecruitmentTrait;
    use HRHiringTrait;
    use HREventsTrait;
    use HRDataTrait;

    private PDO $db;
    private CandidateGenerator $generator;
    private StrikeEffectService $strikeEffects;

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
        $this->strikeEffects = new StrikeEffectService(
            $this->db,
            new EmployeeSystemConfigService($this->db)
        );

    }
}
