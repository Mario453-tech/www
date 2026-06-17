<?php

/**
 * Global bank parameters service.
 * PL: Serwis globalnych parametrow banku.
 */
class BankSettings
{
 /** @var array<string, mixed>|null */
    private static ?array $cache = null;
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            GameLog::info('BankSettings', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('BankSettings', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public static function get(string $key, float $default = 1.0): float
    {
        try {
            if (self::$cache === null) {
                self::$cache = [];
                $rows = Database::getInstance()->getConnection()
                    ->query("SELECT setting_key, value FROM bank_settings")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
                self::$cache = $rows ?: [];
            }

            return isset(self::$cache[$key]) ? (float)self::$cache[$key] : $default;
        } catch (Throwable $e) {
            GameLog::error('BankSettings', 'Failed to load setting', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

 /** @return list<array<string, mixed>> */
    public function getAll(): array
    {
        try {
            return $this->db->query("SELECT * FROM bank_settings ORDER BY setting_key")->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('BankSettings', 'Failed to fetch all settings', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function set(string $key, float $value, string $adminUser = 'admin'): bool
    {
        try {
            $this->db->prepare("
                UPDATE bank_settings
                SET value = :val, updated_by = :by, updated_at = NOW()
                WHERE setting_key = :key
            ")->execute([':val' => $value, ':by' => $adminUser, ':key' => $key]);

            self::$cache = null;
            GameLog::info('BankSettings', 'Setting updated', [
                'key' => $key,
                'value' => $value,
                'admin' => $adminUser,
            ]);
            return true;
        } catch (Throwable $e) {
            GameLog::error('BankSettings', 'Failed to update setting', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public static function aprMultiplier(): float
    {
        return self::get('apr_multiplier', 1.0);
    }

    public static function riskTolerance(): float
    {
        return self::get('risk_tolerance_modifier', 1.0);
    }

    public static function maxAmountMultiplier(): float
    {
        return self::get('max_amount_multiplier', 1.0);
    }

 // Credit score thresholds for loan decisions.
 // PL: Progi score dla decyzji kredytowej.
    public static function scoreThresholdReject(): int
    {
        return (int)self::get('score_threshold_reject', 30);
    }

    public static function scoreThresholdCond(): int
    {
        return (int)self::get('score_threshold_cond', 50);
    }

    public static function scoreThresholdFull(): int
    {
        return (int)self::get('score_threshold_full', 75);
    }

 // APR rates per decision tier.
 // PL: Stawki APR per tier decyzji.
    public static function aprConditional(): float
    {
        return self::get('apr_tier_conditional', 40.0);
    }

    public static function aprPartial(): float
    {
        return self::get('apr_tier_partial', 28.0);
    }

    public static function aprFull(): float
    {
        return self::get('apr_tier_full', 18.0);
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'apr_multiplier'          => t('admin.loans.setting_label_apr_multiplier'),
            'risk_tolerance_modifier' => t('admin.loans.setting_label_risk_tolerance'),
            'max_amount_multiplier'   => t('admin.loans.setting_label_max_amount'),
            'score_threshold_reject'  => t('admin.loans.setting_label_score_reject'),
            'score_threshold_cond'    => t('admin.loans.setting_label_score_cond'),
            'score_threshold_full'    => t('admin.loans.setting_label_score_full'),
            'apr_tier_rejected'       => t('admin.loans.setting_label_apr_rejected'),
            'apr_tier_conditional'    => t('admin.loans.setting_label_apr_conditional'),
            'apr_tier_partial'        => t('admin.loans.setting_label_apr_partial'),
            'apr_tier_full'           => t('admin.loans.setting_label_apr_full'),
            default                   => $key,
        };
    }

    public static function systemStatus(): string
    {
        try {
            $apr = self::aprMultiplier();
            $risk = self::riskTolerance();
            $amt = self::maxAmountMultiplier();

            if ($apr >= 1.4 || $risk <= 0.7 || $amt <= 0.6) {
                return 'crisis';
            }
            if ($apr >= 1.2 || $risk <= 0.85 || $amt <= 0.8) {
                return 'tight';
            }
            if ($apr <= 0.85 && $risk >= 1.15 && $amt >= 1.15) {
                return 'loose';
            }
            return 'normal';
        } catch (Throwable $e) {
            GameLog::error('BankSettings', 'Failed to compute system status', ['error' => $e->getMessage()]);
            return 'normal';
        }
    }
}
