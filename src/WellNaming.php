<?php
declare(strict_types=1);

final class WellNaming
{
    private const PREFIXES = [
        'north_europe' => 'E', 'europe' => 'E',
        'southeast_asia' => 'AP', 'asia_pacific' => 'AP',
        'middle_east' => 'ME', 'me' => 'ME',
        'russia' => 'R', 'africa' => 'AF',
        'usa_canada' => 'NA', 'north_america' => 'NA',
        'latam' => 'SA', 'south_america' => 'SA',
    ];

    public static function prefix(?string $regionCode): string
    {
        return self::PREFIXES[strtolower(trim((string)$regionCode))] ?? 'W';
    }

    public static function name(?string $regionCode, int $number): string
    {
        if ($number < 1) {
            throw new InvalidArgumentException('Well number must be positive.');
        }
        return self::prefix($regionCode) . '-' . $number;
    }

    public static function allocate(PDO $db, int $playerId, int $regionId, ?string $regionCode): string
    {
        if (!$db->inTransaction()) {
            throw new LogicException('Well name allocation requires a transaction.');
        }
        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $db->prepare('SELECT last_number FROM well_name_counters WHERE player_id = ? AND region_id = ?' . $lock);
        $stmt->execute([$playerId, $regionId]);
        $previous = $stmt->fetchColumn();
        $next = $previous === false ? 1 : (int)$previous + 1;
        if ($previous === false) {
            $db->prepare('INSERT INTO well_name_counters (player_id, region_id, last_number) VALUES (?, ?, ?)')
                ->execute([$playerId, $regionId, $next]);
        } else {
            $db->prepare('UPDATE well_name_counters SET last_number = ? WHERE player_id = ? AND region_id = ?')
                ->execute([$next, $playerId, $regionId]);
        }
        return self::name($regionCode, $next);
    }
}
