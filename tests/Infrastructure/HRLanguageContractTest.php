<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HRLanguageContractTest extends TestCase
{
    /**
     * @dataProvider languagePairs
     */
    public function testPolishAndEnglishKeysAndPlaceholdersMatch(string $polishPath, string $englishPath): void
    {
        $polish = $this->loadLanguageFile($polishPath);
        $english = $this->loadLanguageFile($englishPath);

        $polishKeys = array_keys($polish);
        $englishKeys = array_keys($english);
        sort($polishKeys);
        sort($englishKeys);

        self::assertSame($polishKeys, $englishKeys, "Language keys differ: {$polishPath} / {$englishPath}");

        foreach ($polishKeys as $key) {
            self::assertSame(
                $this->placeholders((string)$polish[$key]),
                $this->placeholders((string)$english[$key]),
                "Language placeholders differ for {$key}"
            );
        }
    }

    public function testPlayerHrDynamicValuesHaveTranslations(): void
    {
        $this->assertRequiredKeys(
            $this->loadLanguageFile(__DIR__ . '/../../lang/pl/hr.php'),
            $this->loadLanguageFile(__DIR__ . '/../../lang/en/hr.php'),
            [
                'hr.rarity.common',
                'hr.rarity.uncommon',
                'hr.rarity.rare',
                'hr.rarity.very_rare',
                'hr.department.technical',
                'hr.department.logistics',
                'hr.department.hr',
                'hr.department.legal',
                'hr.department.finance',
                'hr.department.unknown',
                'hr.strike_status.threat',
                'hr.strike_status.active',
                'hr.strike_status.negotiating',
                'hr.raise_status.open',
                'hr.raise_status.pending',
                'hr.raise_status.postponed',
                'hr.training_status.in_progress',
                'hr.training_status.passed',
                'hr.training_status.failed',
                'hr.training_status.cancelled',
            ]
        );
    }

    public function testAdminHrDynamicValuesHaveTranslations(): void
    {
        $this->assertRequiredKeys(
            $this->loadLanguageFile(__DIR__ . '/../../lang/pl/admin/hr.php'),
            $this->loadLanguageFile(__DIR__ . '/../../lang/en/admin/hr.php'),
            [
                'admin.hr.department.hr',
                'admin.hr.department.technical',
                'admin.hr.department.finance',
                'admin.hr.department.legal',
                'admin.hr.department.logistics',
                'admin.hr.status.normal',
                'admin.hr.status.unhappy',
                'admin.hr.status.raise_requested',
                'admin.hr.status.dispute',
                'admin.hr.status.strike_threat',
                'admin.hr.status.on_strike',
                'admin.hr.status.leaving',
                'admin.hr.status.inactive',
                'admin.hr.status.active',
                'admin.hr.status.released',
                'admin.hr.status.open',
                'admin.hr.status.postponed',
                'admin.hr.status.accepted',
                'admin.hr.status.negotiated',
                'admin.hr.status.rejected',
                'admin.hr.status.expired',
                'admin.hr.status.threat',
                'admin.hr.status.negotiating',
                'admin.hr.status.resolved',
                'admin.hr.status.failed',
                'admin.hr.raise_reason.low_morale',
                'admin.hr.raise_reason.salary_gap',
                'admin.hr.raise_reason.other',
            ]
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function languagePairs(): iterable
    {
        yield 'player HR' => [
            __DIR__ . '/../../lang/pl/hr.php',
            __DIR__ . '/../../lang/en/hr.php',
        ];
        yield 'admin HR' => [
            __DIR__ . '/../../lang/pl/admin/hr.php',
            __DIR__ . '/../../lang/en/admin/hr.php',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadLanguageFile(string $path): array
    {
        $language = require $path;
        self::assertIsArray($language, "Language file must return an array: {$path}");

        return $language;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $value): array
    {
        preg_match_all('/:([a-z][a-z0-9_]*)/i', $value, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        sort($placeholders);

        return $placeholders;
    }

    /**
     * @param array<string, mixed> $polish
     * @param array<string, mixed> $english
     * @param list<string>         $requiredKeys
     */
    private function assertRequiredKeys(array $polish, array $english, array $requiredKeys): void
    {
        foreach ($requiredKeys as $key) {
            self::assertArrayHasKey($key, $polish, "Missing Polish translation: {$key}");
            self::assertArrayHasKey($key, $english, "Missing English translation: {$key}");
            self::assertNotSame('', trim((string)$polish[$key]), "Empty Polish translation: {$key}");
            self::assertNotSame('', trim((string)$english[$key]), "Empty English translation: {$key}");
            self::assertNotSame($key, $polish[$key], "Technical Polish label exposed: {$key}");
            self::assertNotSame($key, $english[$key], "Technical English label exposed: {$key}");
        }
    }
}
