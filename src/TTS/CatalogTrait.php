<?php

/**
 * Builds the recruitable technical position catalog from admin-managed data.
 * Buduje katalog stanowisk technicznych z danych zarzadzanych przez administratora.
 */
trait TTSCatalogTrait
{
    /** @return array<string, array<string, mixed>> */
    public static function getSpecsCatalog(?PDO $connection = null): array
    {
        $defaults = self::SPECS;
        foreach ($defaults as $code => $spec) {
            $defaults[$code]['name'] = t($spec['name_key']);
            $defaults[$code]['description'] = t($spec['description_key']);
        }

        try {
            $db = $connection ?? Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT code, name, base_salary_min, base_salary_max
                   FROM hr_specializations
                  WHERE department = 'technical'
                  ORDER BY rarity DESC, name ASC"
            );
            $stmt->execute();
            $configured = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $specs = [];
            foreach ($configured as $row) {
                $code = trim((string)($row['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $definition = $defaults[$code] ?? [
                    'name_key' => '',
                    'icon' => self::dynamicSpecIcon($code),
                    'tasks' => [],
                    'salary_range' => [8000, 15000],
                    'description_key' => '',
                    'name' => (string)($row['name'] ?? $code),
                    'description' => t('technical.spec_desc.configured', [
                        'name' => (string)($row['name'] ?? $code),
                    ]),
                ];
                $definition['salary_range'] = [
                    max(1, (int)round((float)($row['base_salary_min'] ?? $definition['salary_range'][0]))),
                    max(1, (int)round((float)($row['base_salary_max'] ?? $definition['salary_range'][1]))),
                ];
                $specs[$code] = $definition;
            }

            return $specs;
        } catch (Throwable $e) {
            GameLog::error('TechnicalTeamService', 'technical specialization catalog load FAILED', $e);
            return $defaults;
        }
    }

    /** @return array<string, mixed>|null */
    public static function getSpecDefinition(string $code, ?PDO $connection = null): ?array
    {
        $specs = self::getSpecsCatalog($connection);
        return $specs[$code] ?? null;
    }

    private static function dynamicSpecIcon(string $code): string
    {
        $parts = array_values(array_filter(explode('_', $code)));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 2));
        }

        return strtoupper(substr($code, 0, 3));
    }
}
