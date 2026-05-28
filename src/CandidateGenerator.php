<?php
require_once __DIR__ . '/FinancePolicyService.php';

/**
 * Generates oil and gas candidates for HR recruitment flows.
 * PL: Generuje kandydatow oil and gas dla procesow rekrutacji HR.
 *
 * Algorithm:
 * PL: Algorytm:
 *   1. Load specialization from hr_specializations.
 *      PL: Pobierz specjalizacje z hr_specializations.
 *   2. Load region from hr_regions.
 *      PL: Pobierz region z hr_regions.
 *   3. Determine candidate count using rarity and availability.
 *      PL: Ustal liczbe kandydatow wg rarity i dostepnosci.
 *   4. Generate age, experience, skills, traits, salary and name.
 *      PL: Wygeneruj wiek, doswiadczenie, skille, cechy, pensje i imie.
 */
class CandidateGenerator
{
    private PDO $db;

    // Candidate count by rarity and regional availability.
    // PL: Liczba kandydatow wg rarity i dostepnosci regionu.
    /** @var array<string, array<int, int>> */
    private static array $countByRarity = [
        'common'    => [1, 3],
        'uncommon'  => [0, 2],
        'rare'      => [0, 2],
        'very_rare' => [0, 1],
    ];

    // Base skill distribution for the labor market.
    // PL: Bazowy rozklad skilli na rynku pracy.
    /** @var array<int, array<int, int>> */
    private static array $skillDistribution = [
        [3, 4, 4],
        [5, 6, 20],
        [7, 7, 12],
        [8, 8, 6],
        [9, 9, 2],
        [10, 10, 0],
    ];

    // Recruitment duration in seconds.
    // PL: Czas rekrutacji w sekundach.
    const DURATION_LOCAL_MIN = 6 * 60;
    const DURATION_LOCAL_MAX = 12 * 60;
    const DURATION_INTL_MIN = 24 * 60;
    const DURATION_INTL_MAX = 48 * 60;

    // Experience bands with salary modifiers.
    // PL: Skale doswiadczenia z mnoznikami pensji.
    /** @var array<string, array<int|float, int|float>> */
    private static array $expLevels = [
        'junior' => [1, 5, 0.70],
        'mid'    => [6, 12, 1.00],
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

    // Main generation entrypoint.
    // PL: Glowne wejscie generatora.
    /**
     * Generates candidates for a recruitment request.
     * PL: Generuje kandydatow dla zgloszenia rekrutacyjnego.
     *
     * @param string $recruitType 'local' | 'international'
     * @return list<int>
     */
    public function generateForRequest(
        int $roleId,
        int $requestId,
        string $regionCode = 'PL',
        ?string $specCode = null,
        string $recruitType = 'local',
        float $bankruptPenalty = 1.0,
        string $initiatedBy = 'director'
    ): array {
        $playerId = $this->getRequestPlayerId($requestId);
        $region = $this->fetchRegion($regionCode);
        $spec = $specCode
            ? $this->fetchSpecByCode($specCode)
            : $this->fetchSpecByRole($roleId);

        if (!$region || !$spec) {
            $region = [
                'code' => 'PL',
                'skill_modifier' => 1.0,
                'salary_modifier' => 1.0,
                'availability' => 60,
            ];
            $spec = [
                'id' => null,
                'rarity' => 'common',
                'base_salary_min' => 4000,
                'base_salary_max' => 8000,
                'min_age' => 25,
                'max_age' => 55,
                'name' => t('hr.default_candidate_label'),
            ];
        }

        // Candidate count is reduced by region availability and bankruptcy penalty.
        // PL: Liczba kandydatow maleje przez dostepnosc regionu i kare restrukturyzacji.
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

        // International recruitment gives a modest quality boost.
        // PL: Rekrutacja miedzynarodowa daje lekki boost jakosci.
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

        // Send info notification when technical recruitment finds nobody.
        // PL: Wyslij notyfikacje, gdy techniczny nie znajdzie zadnego kandydata.
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
                'spec' => $spec['name'],
                'region' => $regionCode,
                'initiated_by' => $initiatedBy,
            ]);
        }

        return $generated;
    }

    // Single-candidate generator.
    // PL: Generator pojedynczego kandydata.
    /**
     * @param array<string, mixed> $region
     * @param array<string, mixed> $spec
     */
    private function generateOne(
        int $roleId,
        int $requestId,
        array $region,
        array $spec,
        string $expiresAt,
        float $intlBoost = 1.0,
        float $hrQualityMult = 1.0
    ): int {
        $age = rand($spec['min_age'], $spec['max_age']);
        $birthDate = date('Y-m-d', mktime(0, 0, 0, rand(1, 12), rand(1, 28), date('Y') - $age));

        // Experience range depends on age.
        // PL: Zakres doswiadczenia zalezy od wieku.
        $maxExp = max(1, $age - 22);
        $minExp = max(1, (int)floor($maxExp * 0.2));
        $exp = rand($minExp, $maxExp);

        $expMod = $this->getExpModifier($exp);
        $gender = rand(0, 9) < 8 ? 'M' : 'F';
        $nationality = $region['code'];
        $firstName = $this->randomName('first_name', $nationality, $gender);
        $lastName = $this->randomName('last_name', $nationality, 'N');

        // Skills depend on market distribution, region and recruitment boost.
        // PL: Skille zaleza od rozkladu rynku, regionu i boostu rekrutacji.
        $skillMod = (float)$region['skill_modifier'] * $intlBoost * $hrQualityMult;
        $baseSkill = $this->rollSkillFromDistribution($skillMod);
        $skills = $this->generateSkills($baseSkill, 1.0);

        $traits = $this->generateTraits($skills['ethics'], $age, $exp);
        $salaryBase = rand((int)$spec['base_salary_min'], (int)$spec['base_salary_max']);
        $salary = round($salaryBase * (float)$region['salary_modifier'] * $expMod / 100) * 100;
        $cert = rand(1, 10) === 1 ? $this->randomCertificate($spec) : null;

        $playerIdForCand = null;
        try {
            $pidStmt = $this->db->prepare("SELECT player_id FROM recruitment_requests WHERE id = ? LIMIT 1");
            $pidStmt->execute([$requestId]);
            $pidRow = $pidStmt->fetch();
            $playerIdForCand = $pidRow ? $pidRow['player_id'] : null;
        } catch (Throwable $e) {
        }

        return $this->save([
            'player_id' => $playerIdForCand,
            'role_id' => $roleId,
            'request_id' => $requestId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'nationality' => $nationality,
            'region_code' => $region['code'],
            'specialization_id' => $spec['id'] ?? null,
            'experience_years' => $exp,
            'skill_organization' => $skills['organization'],
            'skill_negotiation' => $skills['negotiation'],
            'skill_analysis' => $skills['analysis'],
            'skill_stress' => $skills['stress'],
            'skill_ethics' => $skills['ethics'],
            'trait_loyalty' => $traits['loyalty'],
            'trait_corruption_risk' => $traits['corruption_risk'],
            'trait_ambition' => $traits['ambition'],
            'expected_salary' => $salary,
            'expires_at' => $expiresAt,
        ]);
    }

    private function getRequestPlayerId(int $requestId): ?int
    {
        $stmt = $this->db->prepare("SELECT player_id FROM recruitment_requests WHERE id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (int)$value : null;
    }

    // Skill generation.
    // PL: Generowanie skilli.
    /**
     * Rolls a base skill from labor-market distribution.
     * PL: Losuje bazowy skill z rozkladu rynku pracy.
     */
    private function rollSkillFromDistribution(float $regionMod): int
    {
        $roll = mt_rand(1, 1000);

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

        // Region shifts the skill distribution up or down.
        // PL: Region przesuwa rozklad skilli w gore lub w dol.
        if ($regionMod >= 1.1) {
            if (mt_rand(1, 100) <= 30) {
                $base = min(10, $base + 1);
            }
        } elseif ($regionMod <= 0.85) {
            if (mt_rand(1, 100) <= 30) {
                $base = max(1, $base - 1);
            }
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
        foreach ($names as $name) {
            $raw = round($base * $regionMod) + rand(-2, 2);
            $skills[$name] = max(1, min(10, (int)$raw));
        }
        return $skills;
    }

    // Trait generation.
    // PL: Generowanie cech.
    /**
     * @return array<string, int>
     */
    private function generateTraits(int $ethics, int $age, int $exp): array
    {
        $loyaltyBase = min(9, max(1, (int)round(($age - 22) / 4) + rand(-1, 1)));
        $corrBase = max(1, min(10, 11 - $ethics + rand(-2, 2)));
        $ambBase = max(1, min(10, (int)round((60 - $age) / 5) + rand(-1, 2)));

        return [
            'loyalty' => $loyaltyBase,
            'corruption_risk' => $corrBase,
            'ambition' => $ambBase,
        ];
    }

    // Experience helpers.
    // PL: Helpery doswiadczenia.
    private function getExpModifier(int $exp): float
    {
        foreach (self::$expLevels as [$min, $max, $mod]) {
            if ($exp >= $min && $exp <= $max) {
                return $mod;
            }
        }
        return 1.4;
    }

    public static function getExpLevel(int $exp): string
    {
        if ($exp <= 5) {
            return 'Junior';
        }
        if ($exp <= 12) {
            return 'Mid';
        }
        return 'Senior';
    }

    // Certificates.
    // PL: Certyfikaty.
    /**
     * @param array<string, mixed> $spec
     */
    private function randomCertificate(array $spec): ?string
    {
        $certs = [
            'drilling'          => t('candidate.certificate.drilling'),
            'offshore_survival' => t('candidate.certificate.offshore_survival'),
            'equipment'         => t('candidate.certificate.equipment'),
            'hse'               => t('candidate.certificate.hse'),
            'project_mgmt'      => t('candidate.certificate.project_mgmt'),
        ];
        $key = array_rand($certs);
        return $certs[$key];
    }

    // Name generation.
    // PL: Generowanie imion i nazwisk.
    private function randomName(string $type, string $nationality, string $gender): string
    {
        $stmt = $this->db->prepare("
            SELECT value FROM name_pool
            WHERE type = ? AND nationality = ? AND (gender = ? OR gender = 'N')
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute([$type, $nationality, $gender]);
        $row = $stmt->fetch();
        if ($row) {
            return $row['value'];
        }

        $stmt->execute([$type, 'PL', $gender]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : ($type === 'first_name' ? t('hr.default_first_name') : t('hr.default_last_name'));
    }

    // Fetch helpers.
    // PL: Helpery pobierania danych.
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

        $stmt = $this->db->prepare("
            SELECT * FROM hr_specializations
            WHERE department = ?
            ORDER BY RAND() LIMIT 1
        ");
        $stmt->execute([$dept]);
        return $stmt->fetch() ?: null;
    }

    // Persistence helpers.
    // PL: Helpery zapisu.
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

    // Cleanup helpers.
    // PL: Helpery cleanupu.
    public function cleanupExpired(): int
    {
        $stmt = $this->db->prepare("DELETE FROM candidates WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
