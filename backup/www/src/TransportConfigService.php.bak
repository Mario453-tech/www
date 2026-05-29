<?php

class TransportConfigService
{
    /** @return array<string, array<string, float>> */
    public static function getDefaults(): array
    {
        return [
            'nieustawiony' => ['incident' => 0.00, 'disaster' => 0.00, 'wear' => 1.00, 'spiral' => 1.00, 'capacity' => 0.0,   'opex' => 0.0,  'cost_per_bbl' => 0.00],
            'rurociag'   => ['incident' => 0.80, 'disaster' => 0.90, 'wear' => 0.95, 'spiral' => 0.95, 'capacity' => 120.0, 'opex' => 7.5,  'cost_per_bbl' => 0.50],
            'ciezarowki' => ['incident' => 1.30, 'disaster' => 1.25, 'wear' => 1.15, 'spiral' => 1.10, 'capacity' => 70.0,  'opex' => 20.0, 'cost_per_bbl' => 2.50],
            'tankowiec'  => ['incident' => 1.00, 'disaster' => 1.15, 'wear' => 1.05, 'spiral' => 1.05, 'capacity' => 110.0, 'opex' => 12.0, 'cost_per_bbl' => 1.50],
        ];
    }

    public static function tableExists(PDO $db): bool
    {
        try {
            $db->query("SELECT 1 FROM transport_config LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array<string, array<string, float>> */
    public static function load(PDO $db): array
    {
        $config = self::getDefaults();
        if (!self::tableExists($db)) {
            return $config;
        }

        try {
            $rows = $db->query("SELECT transport_type, config_key, config_value FROM transport_config")->fetchAll();
            foreach ($rows as $row) {
                $type = (string)($row['transport_type'] ?? '');
                $key  = (string)($row['config_key'] ?? '');
                if (!isset($config[$type]) || !array_key_exists($key, $config[$type])) {
                    continue;
                }
                $config[$type][$key] = (float)$row['config_value'];
            }
        } catch (Throwable $e) {
            GameLog::error('TransportConfigService', 'load FAILED - fallback defaults', $e);
        }

        return $config;
    }

    /** @return array<string, float> */
    public static function getTypeConfig(PDO $db, string $type): array
    {
        $config = self::load($db);
        return $config[$type] ?? self::getDefaults()['nieustawiony'];
    }

    /**
     * Ensures wells.transport_type enum includes 'nieustawiony'
     * and transport_config has rows for 'nieustawiony'.
     * Safe to call on every boot - checks before ALTER.
     */
    public static function ensureTransportSchema(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }

        try {
            // Check if 'nieustawiony' is already in the enum
            $stmt = $db->prepare("
                SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'wells'
                   AND COLUMN_NAME  = 'transport_type'
            ");
            $stmt->execute();
            $colType = (string)($stmt->fetchColumn() ?? '');

            if (strpos($colType, 'nieustawiony') === false) {
                $db->exec("ALTER TABLE wells MODIFY COLUMN transport_type
                    ENUM('rurociag','ciezarowki','tankowiec','nieustawiony')
                    NOT NULL DEFAULT 'nieustawiony'
                    COMMENT 'Typ transportu ropy z odwiertu'");
                GameLog::info('TransportConfigService', 'wells.transport_type enum extended with nieustawiony');
            }
        } catch (Throwable $e) {
            GameLog::error('TransportConfigService', 'ensureTransportSchema wells ALTER failed', $e);
        }

        try {
            // Check transport_config enum too
            $stmt = $db->prepare("
                SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'transport_config'
                   AND COLUMN_NAME  = 'transport_type'
            ");
            $stmt->execute();
            $colType = (string)($stmt->fetchColumn() ?? '');

            if (strpos($colType, 'nieustawiony') === false) {
                $db->exec("ALTER TABLE transport_config MODIFY COLUMN transport_type
                    ENUM('rurociag','ciezarowki','tankowiec','nieustawiony')
                    NOT NULL
                    COMMENT 'Typ transportu'");
                GameLog::info('TransportConfigService', 'transport_config.transport_type enum extended with nieustawiony');
            }
        } catch (Throwable $e) {
            GameLog::error('TransportConfigService', 'ensureTransportSchema transport_config ALTER failed', $e);
        }

        try {
            // Seed nieustawiony config rows if missing
            $existing = $db->query(
                "SELECT COUNT(*) FROM transport_config WHERE transport_type = 'nieustawiony'"
            )->fetchColumn();

            if ((int)$existing === 0) {
                $defaults = self::getDefaults()['nieustawiony'];
                $stmt = $db->prepare(
                    "INSERT IGNORE INTO transport_config (transport_type, config_key, config_value)
                     VALUES ('nieustawiony', ?, ?)"
                );
                foreach ($defaults as $key => $val) {
                    $stmt->execute([$key, $val]);
                }
                GameLog::info('TransportConfigService', 'nieustawiony config rows seeded');
            }
        } catch (Throwable $e) {
            GameLog::error('TransportConfigService', 'ensureTransportSchema seed nieustawiony failed', $e);
        }
    }
}
