<?php
trait WellConfigTrait
{
 /** @var array<string, float> */
    private array $config = [];

    private function loadConfig(): void
    {
        try {
            $rows = $this->db->query("SELECT `key`, `value` FROM well_config")->fetchAll();
            foreach ($rows as $r) {
                $this->config[$r['key']] = (float)$r['value'];
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'loadConfig FAILED', $e);
            }
        }
    }

 /**
 * Zwraca tablice mnoznikow dla danego tieru sprzetu i poziomu upgrade.
 * Returns multiplier array for the given equipment tier and upgrade level.
 * Klucze: prod, wear, incident, spiral.
 * Keys: prod, wear, incident, spiral.
 *
 * @return array{prod:float, wear:float, incident:float, spiral:float}
 */
    public static function getEquipmentMultipliers(string $tier, int $upgradeLevel): array
    {
        $base = match ($tier) {
            'premium'      => ['prod' => 1.20, 'wear' => 0.75, 'incident' => 0.80, 'spiral' => 0.85],
            'black_market' => ['prod' => 1.10, 'wear' => 1.30, 'incident' => 1.25, 'spiral' => 1.20],
            default        => ['prod' => 1.00, 'wear' => 1.00, 'incident' => 1.00, 'spiral' => 1.00],
        };

        if ($upgradeLevel > 0) {
            $lvl = min($upgradeLevel, 3);
            $base['prod']     *= 1.0 + $lvl * 0.05;
            $base['wear']     *= max(0.5, 1.0 - $lvl * 0.05);
            $base['incident'] *= max(0.5, 1.0 - $lvl * 0.04);
            $base['spiral']   *= max(0.5, 1.0 - $lvl * 0.04);
        }

        return $base;
    }

    public function cfg(string $key, float $default = 0): float
    {
        return $this->config[$key] ?? $default;
    }

 /** @return list<array<string, mixed>> */
    public function getAllConfig(): array
    {
        return $this->db->query("SELECT * FROM well_config ORDER BY category, `key`")->fetchAll();
    }

    public function updateConfig(string $key, float $value): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE well_config SET value = ? WHERE `key` = ?");
            $ok   = $stmt->execute([$value, $key]);
            if (class_exists('GameLog', false)) {
                GameLog::info('WellService', 'updateConfig', ['key' => $key, 'value' => $value]);
            }
            return $ok;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'updateConfig FAILED', $e, ['key' => $key]);
            }
            return false;
        }
    }
}
