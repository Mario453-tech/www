<?php
require_once __DIR__ . '/FinancePolicyService.php';
/**
 * CandidateGenerator — generuje kandydatow oil & gas
 *
 * Algorytm:
 *  1. Pobiera specjalizację z hr_specializations (rarity, widełki płacowe)
 *  2. Pobiera region z hr_regions (skill_modifier, salary_modifier)
 *  3. Generuje liczbę kandydatów wg rarity
 *  4. Dla każdego: wiek → doświadczenie → skills → traits → pensja → imię
 */
class CandidateGenerator
{
    private PDO $db;

    // Liczba kandydatow: 0–3 zaleznie od rarity i dostepnosci regionu
    /** @var array<string, array<int, int>> */
    private static array $countByRarity = [
        'common'    => [1, 3],
        'uncommon'  => [0, 2],
        'rare'      => [0, 2],
        'very_rare' => [0, 1],
    ];

    // Rozkład skill wg rynku pracy branzy naftowej
    // skill 3-4: 40%, 5-6: 20%, 7: 12%, 8: 6%, 9: 1.8%, 10: 0.2%
    /** @var array<int, array<int, int>> */
    private static array $skillDistribution = [
        [3, 4,  4],
        [5, 6,  20],
        [7, 7,  12],
        [8, 8,   6],
        [9, 9,   2],   // ~1.8% zaokraglone
        [10, 10, 0],   // obslugiwane osobno ponizej (0.2%)
    ];

    // Czas rekrutacji w sekundach
    // lokalna: 6–12 min (symuluje 6–12h), miedzynarodowa: 24–48 min
    const DURATION_LOCAL_MIN  =  6 * 60;
    const DURATION_LOCAL_MAX  = 12 * 60;
    const DURATION_INTL_MIN   = 24 * 60;
    const DURATION_INTL_MAX   = 48 * 60;

    // Skale doswiadczenia
    /** @var array<string, array<int|float, int|float>> */
    private static array $expLevels = [
        'junior' => [1,  5,  0.70],
        'mid'    => [6,  12, 1.00],
        'senior' => [13, 35, 1.40],
    ];

    public function __construct(PDO $db)
    {
        try {
            $this->db = $db;
            GameLog::info('CandidateGenerator', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('CandidateGenerator', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    //  GLOWNA METODA 

    /**
     * Generuje kandydatow dla danej roli i regionu
     *
     * @param int    $roleId       ID z board_roles
     * @param int    $requestId    ID z recruitment_requests
     * @param string $regionCode   Kod regionu (PL, US, NO...)
     * @param string $specCode     Kod specjalizacji (drilling_engineer...) lub null = wybierz wg dzialu
     */
    /**
     * Generuje kandydatow dla rekrutacji
     *
     * @param string $recruitType  'local' | 'international'
     * @return list<int>
     */
    public function generateForRequest(int $roleId, int $requestId, string $regionCode = 'PL',
                                       ?string $specCode = null, string $recruitType = 'local',
                                       float $bankruptPenalty = 1.0, string $initiatedBy = 'director'): array
    {
        $playerId = $this->getRequestPlayerId($requestId);
        $region = $this->fetchRegion($regionCode);
        $spec   = $specCode
            ? $this->fetchSpecByCode($specCode)
            : $this->fetchSpecByRole($roleId);

        if (!$region || !$spec) {
            $region = ['code'=>'PL','skill_modifier'=>1.0,'salary_modifier'=>1.0,'availability'=>60];
            $spec   = ['id'=>null,'rarity'=>'common','base_salary_min'=>4000,'base_salary_max'=>8000,
                       'min_age'=>25,'max_age'=>55,'name'=>'Pracownik'];
        }

        // Liczba kandydatow: 0–3, zredukowana przez dostepnosc regionu
        // bankruptPenalty < 1.0 gdy firma jest w restrukturyzacji — mniej chetnych
        $range = self::$countByRarity[$spec['rarity']] ?? [1, 3];
        $avail = ($region['availability'] ?? 60) / 100;
        $count = (int)round(rand($range[0], $range[1]) * $avail * $bankruptPenalty);
        $count = max(0, min(3, $count));

        if ($initiatedBy === 'technical') {
            $count = 1;
            if ($specCode !== null) {
                $playerId = $this->getRequestPlayerId($requestId);
                if ($playerId !== null) {
                    $totalStmt = $this->db->prepare("
                        SELECT COUNT(*)
                        FROM candidates c
                        JOIN recruitment_requests rr ON rr.id = c.request_id
                        JOIN board_roles br ON br.id = c.role_id
                        WHERE rr.player_id = ?
                          AND rr.initiated_by = 'technical'
                          AND br.code = 'technical'
                          AND c.expires_at > NOW()
                    ");
                    $totalStmt->execute([$playerId]);
                    $existingTotalCandidates = (int)$totalStmt->fetchColumn();

                    $specStmt = $this->db->prepare("
                        SELECT COUNT(*)
                        FROM candidates c
                        JOIN recruitment_requests rr ON rr.id = c.request_id
                        JOIN hr_specializations hs ON hs.id = c.specialization_id
                        JOIN board_roles br ON br.id = c.role_id
                        WHERE rr.player_id = ?
                          AND rr.initiated_by = 'technical'
                          AND rr.spec_code = ?
                          AND hs.code = ?
                          AND br.code = 'technical'
                          AND c.expires_at > NOW()
                    ");
                    $specStmt->execute([$playerId, $specCode, $specCode]);
                    $existingSpecCandidates = (int)$specStmt->fetchColumn();

                    if ($existingSpecCandidates >= 2 || $existingTotalCandidates >= 6) {
                        $count = 0;
                    }
                }
            }
        }

        // Rekrutacja miedzynarodowa daje nieco lepszych kandydatow
            // bankruptPenalty obniża też skuteczność boost (slabsi kandydaci chcą iść do firmy w tarapatach)
        $intlBoost = ($recruitType === 'international') ? 1.1 * $bankruptPenalty : $bankruptPenalty;

        $hrQualityMult = 1.0;
        if ($playerId !== null && class_exists('FinancePolicyService')) {
            try {
                $finPolicySvc = new FinancePolicyService($this->db);
                $hrMods = $finPolicySvc->getHRModifiers($playerId);
                $hrQualityMult = (float)($hrMods['quality_mult'] ?? 1.0);
            } catch (Throwable $e) {
                GameLog::error('CandidateGenerator', 'generateForRequest finance policy FAILED', $e, [
                    'player_id' => $playerId,
                    'request_id' => $requestId,
                ]);
            }
        }

        $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));
        $generated = [];

        for ($i = 0; $i < $count; $i++) {
            $id = $this->generateOne($roleId, $requestId, $region, $spec, $expiresAt, $intlBoost, $hrQualityMult);
            $generated[] = $id;
        }

        // Powiadomienie gdy brak kandydatow
        if ($count === 0 && $initiatedBy === 'technical') {
            $this->db->prepare("
                INSERT INTO technical_notifications (player_id, well_id, type, message)
                SELECT rr.player_id, NULL, 'hr_recruitment',
                       CONCAT(?, ' (', ?, '). ', ?)
                FROM recruitment_requests rr
                WHERE rr.id = ?
            ")->execute([
                t('hr.no_candidates_intro'),
                $spec['name'],
                t('hr.no_candidates_hint'),
                $requestId,
            ]);
            GameLog::info('CandidateGenerator', 'No candidates found', [
                'request_id' => $requestId,
                'spec'       => $spec['name'],
                'region'     => $regionCode,
                'initiated_by' => $initiatedBy,
            ]);
        }

        return $generated;
    }

    //  GENERATOR POJEDYNCZEGO KANDYDATA 

    /**
     * @param array<string, mixed> $region
     * @param array<string, mixed> $spec
     */
    private function generateOne(int $roleId, int $requestId, array $region, array $spec,
                                    string $expiresAt, float $intlBoost = 1.0, float $hrQualityMult = 1.0): int
    {
        // Wiek w zakresie specjalizacji
        $age       = rand($spec['min_age'], $spec['max_age']);
        $birthDate = date('Y-m-d', mktime(0, 0, 0, rand(1, 12), rand(1, 28), date('Y') - $age));

        // Doswiadczenie: max = wiek - 22, min = 1
        $maxExp = max(1, $age - 22);
        $minExp = max(1, (int)floor($maxExp * 0.2));
        $exp    = rand($minExp, $maxExp);

        // Poziom doswiadczenia
        $expMod = $this->getExpModifier($exp);

        // Płec
        $gender = rand(0, 9) < 8 ? 'M' : 'F'; // 80% M w oil&gas (realistycznie)

        // Narodowosc z regionu
        $nationality = $region['code'];

        // Imie i nazwisko
        $firstName = $this->randomName('first_name', $nationality, $gender);
        $lastName  = $this->randomName('last_name',  $nationality, 'N');

        // Skills: rozklad rynku pracy + modyfikator regionu + boost rekrutacji miedzynarodowej
        $skillMod  = (float)$region['skill_modifier'] * $intlBoost * $hrQualityMult;
        $baseSkill = $this->rollSkillFromDistribution($skillMod);
        $skills    = $this->generateSkills($baseSkill, 1.0);

        // Cechy ukryte
        $traits = $this->generateTraits($skills['ethics'], $age, $exp);

        // Pensja: base_salary * region_modifier * exp_modifier + losowy jitter
        $salaryBase = rand((int)$spec['base_salary_min'], (int)$spec['base_salary_max']);
        $salary     = round($salaryBase * (float)$region['salary_modifier'] * $expMod / 100) * 100;

        // Certyfikat (10% szans)
        $cert = rand(1, 10) === 1 ? $this->randomCertificate($spec) : null;

        // Pobierz player_id z recruitment_requests
        $playerIdForCand = null;
        try {
            $pidStmt = $this->db->prepare("SELECT player_id FROM recruitment_requests WHERE id = ? LIMIT 1");
            $pidStmt->execute([$requestId]);
            $pidRow = $pidStmt->fetch();
            $playerIdForCand = $pidRow ? $pidRow['player_id'] : null;
        } catch (\Throwable $e) { /* fallback null */ }

        return $this->save([
            'player_id'           => $playerIdForCand,
            'role_id'             => $roleId,
            'request_id'          => $requestId,
            'first_name'          => $firstName,
            'last_name'           => $lastName,
            'gender'              => $gender,
            'birth_date'          => $birthDate,
            'nationality'         => $nationality,
            'region_code'         => $region['code'],
            'specialization_id'   => $spec['id'] ?? null,
            'experience_years'    => $exp,
            'skill_organization'  => $skills['organization'],
            'skill_negotiation'   => $skills['negotiation'],
            'skill_analysis'      => $skills['analysis'],
            'skill_stress'        => $skills['stress'],
            'skill_ethics'        => $skills['ethics'],
            'trait_loyalty'       => $traits['loyalty'],
            'trait_corruption_risk' => $traits['corruption_risk'],
            'trait_ambition'      => $traits['ambition'],
            'expected_salary'     => $salary,
            'expires_at'          => $expiresAt,
        ]);
    }

    private function getRequestPlayerId(int $requestId): ?int
    {
        $stmt = $this->db->prepare("SELECT player_id FROM recruitment_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    //  SKILLS 

    /**
     * Losuje skill według rozkladu rynku pracy branzy naftowej
     * Modyfikator regionu przesuwa rozklad w gore/dol
     */
    private function rollSkillFromDistribution(float $regionMod): int
    {
        $roll = mt_rand(1, 1000);

        // 0.2% szans na skill 10 (1000 * 0.002 = 2)
        if ($roll <= 2) {
            $base = 10;
        } else {
            $roll100 = mt_rand(1, 100);
            $cumulative = 0;
            $base = 5;
            foreach (self::$skillDistribution as [$min, $max, $weight]) {
                $cumulative += $weight;
                if ($roll100 <= $cumulative) {
                    $base = rand($min, $max);
                    break;
                }
            }
        }

        // Modyfikator regionu: NO/US przesuwa w gore, PL/RU w dol
        if ($regionMod >= 1.1) {
            // Lepszy region — 30% szans na +1 skill
            if (mt_rand(1, 100) <= 30) $base = min(10, $base + 1);
        } elseif ($regionMod <= 0.85) {
            // Słabszy region — 30% szans na -1 skill
            if (mt_rand(1, 100) <= 30) $base = max(1, $base - 1);
        }

        return max(1, min(10, $base));
    }

    /**
     * @return array<string, int>
     */
    private function generateSkills(int $base, float $regionMod): array
    {
        $names = ['organization', 'negotiation', 'analysis', 'stress', 'ethics'];
        $skills = [];
        foreach ($names as $n) {
            $raw = round($base * $regionMod) + rand(-2, 2);
            $skills[$n] = max(1, min(10, (int)$raw));
        }
        return $skills;
    }

    //  TRAITS 

    /**
     * @return array<string, int>
     */
    private function generateTraits(int $ethics, int $age, int $exp): array
    {
        // Lojalnosc rośnie z wiekiem i stazem
        $loyaltyBase = min(9, max(1, (int)round(($age - 22) / 4) + rand(-1, 1)));

        // Korupcja — odwrotna do etyki, ale z losowoscia
        $corrBase = max(1, min(10, 11 - $ethics + rand(-2, 2)));

        // Ambicja — maleje z wiekiem i doswiadczeniem
        $ambBase = max(1, min(10, (int)round((60 - $age) / 5) + rand(-1, 2)));

        return [
            'loyalty'          => $loyaltyBase,
            'corruption_risk'  => $corrBase,
            'ambition'         => $ambBase,
        ];
    }

    //  DOSWIADCZENIE 

    private function getExpModifier(int $exp): float
    {
        foreach (self::$expLevels as [$min, $max, $mod]) {
            if ($exp >= $min && $exp <= $max) return $mod;
        }
        return 1.4; // senior+
    }

    public static function getExpLevel(int $exp): string
    {
        if ($exp <= 5)  return 'Junior';
        if ($exp <= 12) return 'Mid';
        return 'Senior';
    }

    //  CERTYFIKATY 

    /**
     * @param array<string, mixed> $spec
     */
    private function randomCertificate(array $spec): ?string
    {
        $certs = [
            'drilling'          => 'Drilling Safety Certificate',
            'offshore_survival' => 'Offshore Survival Training (BOSIET)',
            'equipment'         => 'Equipment Specialist Certificate',
            'hse'               => 'HSE Management Certificate',
            'project_mgmt'      => 'Project Management Professional (PMP)',
        ];
        $key = array_rand($certs);
        return $certs[$key];
    }

    //  IMIONA 

    private function randomName(string $type, string $nationality, string $gender): string
    {
        // Spróbuj pobrać z bazy dla danej narodowości
        $stmt = $this->db->prepare("
            SELECT value FROM name_pool
            WHERE type = ? AND nationality = ? AND (gender = ? OR gender = 'N')
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute([$type, $nationality, $gender]);
        $row = $stmt->fetch();
        if ($row) return $row['value'];

        // Fallback na PL
        $stmt->execute([$type, 'PL', $gender]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : ($type === 'first_name' ? 'Jan' : 'Kowalski');
    }

    //  FETCH HELPERS 

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRegion(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM hr_regions WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSpecByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM hr_specializations WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSpecByRole(int $roleId): ?array
    {
        // Mapuj role zarządu → departament specjalizacji
        $deptMap = [1 => 'hr', 2 => 'technical', 3 => 'finance', 4 => 'legal', 5 => 'logistics'];

        $stmt = $this->db->prepare("SELECT code FROM board_roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();
        if (!$role) {
            return null;
        }

        $dept = trim((string)($role['code'] ?? ''));
        if ($dept === '') {
            return null;
        }

        // Losuj specjalizację z odpowiedniego działu
        $stmt = $this->db->prepare("
            SELECT * FROM hr_specializations
            WHERE department = ?
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute([$dept]);
        return $stmt->fetch() ?: null;
    }

    //  ZAPIS 

    /**
     * @param array<string, mixed> $data
     */
    private function save(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO candidates (
                player_id, director_status,
                role_id, request_id, first_name, last_name, gender,
                birth_date, nationality, region_code, specialization_id,
                experience_years,
                skill_organization, skill_negotiation, skill_analysis,
                skill_stress, skill_ethics,
                trait_loyalty, trait_corruption_risk, trait_ambition,
                expected_salary, expires_at
            ) VALUES (
                :player_id, 'pending',
                :role_id, :request_id, :first_name, :last_name, :gender,
                :birth_date, :nationality, :region_code, :specialization_id,
                :experience_years,
                :skill_organization, :skill_negotiation, :skill_analysis,
                :skill_stress, :skill_ethics,
                :trait_loyalty, :trait_corruption_risk, :trait_ambition,
                :expected_salary, :expires_at
            )
        ");
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    //  CLEANUP 

    public function cleanupExpired(): int
    {
        $stmt = $this->db->prepare("DELETE FROM candidates WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
