<?php
declare(strict_types=1);

final class TechnicalStaffProfile
{
    /**
     * @param array<string, mixed> $candidate
     * @return array{loyalty:int,corruption_risk:int,ambition:int}
     */
    public static function fromCandidate(array $candidate, int $skillLevel = 5): array
    {
        return [
            'loyalty' => self::clamp((int)($candidate['trait_loyalty'] ?? max(1, 10 - $skillLevel))),
            'corruption_risk' => self::clamp((int)($candidate['trait_corruption_risk'] ?? 3)),
            'ambition' => self::clamp((int)($candidate['trait_ambition'] ?? 5)),
        ];
    }

    /** @return array{loyalty:int,corruption_risk:int,ambition:int} */
    public static function deterministic(int $playerId, string $firstName, string $lastName, string $specCode, int $skillLevel): array
    {
        $seed = abs(crc32($playerId . '|' . $firstName . '|' . $lastName . '|' . $specCode));
        return [
            'loyalty' => self::clamp(4 + ($seed % 5) + ($skillLevel >= 7 ? 1 : 0)),
            'corruption_risk' => self::clamp(2 + (($seed >> 3) % 5) - ($skillLevel >= 8 ? 1 : 0)),
            'ambition' => self::clamp(3 + (($seed >> 6) % 6) + ($skillLevel >= 8 ? 1 : 0)),
        ];
    }

    private static function clamp(int $value): int
    {
        return max(1, min(10, $value));
    }
}