<?php

declare(strict_types=1);

/**
 * BriberyConfig - centralna, edytowalna z panelu konfiguracja modulu lapowek.
 * BriberyConfig - central, admin-editable configuration for the bribery module.
 *
 * Wszystkie parametry (szanse wpadki, mnozniki ceny, kary reputacji) siedza w
 * tabeli bribery_config (klucz-wartosc), wiec admin zmienia je bez ruszania kodu.
 * All parameters (catch chances, price multipliers, reputation penalties) live in
 * the bribery_config table (key-value), so the admin edits them without code changes.
 *
 * Poziomy reputacji = poziomy z CompanyCredibilityService::getLevel():
 * critical / low / shaky / stable / high.
 */
class BriberyConfig
{
    /** Poziomy reputacji firmy (spojne z CompanyCredibilityService). */
    public const LEVELS = ['critical', 'low', 'shaky', 'stable', 'high'];

    /**
     * Domyslne wartosci (uzywane przy seedzie i jako fallback).
     * Default values (used for seeding and as a fallback).
     *
     * @var array<string,string>
     */
    public const DEFAULTS = [
        'enabled'                     => '1',
        // Bazowy udzial ceny lapowki w koszcie odniesienia modulu (w %).
        // Base share of the bribe price in the module reference cost (in %).
        'base_cost_pct'               => '50',
        // Szansa wpadki per poziom reputacji (w %). Gorsza reputacja = wieksze ryzyko.
        // Catch chance per reputation level (%). Worse reputation = higher risk.
        'catch_pct_critical'          => '55',
        'catch_pct_low'               => '40',
        'catch_pct_shaky'             => '28',
        'catch_pct_stable'            => '18',
        'catch_pct_high'              => '10',
        // Mnoznik ceny per poziom reputacji. Gorsza reputacja = drozsi urzednicy.
        // Price multiplier per reputation level. Worse reputation = greedier officials.
        'price_mult_critical'         => '2.0',
        'price_mult_low'              => '1.6',
        'price_mult_shaky'            => '1.3',
        'price_mult_stable'           => '1.1',
        'price_mult_high'             => '1.0',
        // Kara reputacji przy sukcesie (dales lapowke - i tak slad) oraz przy wpadce.
        // Reputation penalty on success (you still bribed) and on getting caught.
        'credibility_penalty_success' => '4',
        'credibility_penalty_caught'  => '15',
        // O ile minut wpadka wydluza blokade regionu (urzad sie zawzial).
        // How many minutes a catch extends the region block (officials get tougher).
        'cooldown_extra_minutes'      => '120',
    ];

    private PDO $db;

    /** @var array<string,string> Zaladowane wartosci (z fallbackiem do DEFAULTS). */
    private array $values = [];

    /** @var array<int,bool> Cache zapewnionego schematu per polaczenie. */
    private static array $schemaEnsured = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
        $this->load();
    }

    private function driver(): string
    {
        try {
            return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            return 'mysql';
        }
    }

    /**
     * Tworzy tabele konfiguracji i seeduje braki (idempotentnie). DDL pomijamy w
     * otwartej transakcji (MySQL robi wtedy niejawny commit).
     * Creates the config table and seeds missing keys (idempotent). DDL is skipped
     * inside an open transaction (MySQL would do an implicit commit).
     */
    public function ensureSchema(): void
    {
        $connId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$connId])) {
            return;
        }
        try {
            if ($this->db->inTransaction()) {
                return; // odroczone do konstrukcji poza transakcja / deferred to construction outside a tx
            }
        } catch (Throwable) {
        }
        self::$schemaEnsured[$connId] = true;

        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS bribery_config (
                    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
                    setting_value VARCHAR(64) NOT NULL
                )"
            );

            $ignore = $this->driver() === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
            $stmt = $this->db->prepare("{$ignore} INTO bribery_config (setting_key, setting_value) VALUES (?, ?)");
            foreach (self::DEFAULTS as $key => $value) {
                $stmt->execute([$key, $value]);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BriberyConfig', 'ensureSchema FAILED', $e);
            }
        }
    }

    private function load(): void
    {
        $this->values = self::DEFAULTS;
        try {
            $rows = $this->db->query("SELECT setting_key, setting_value FROM bribery_config")->fetchAll();
            foreach ($rows as $row) {
                $this->values[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BriberyConfig', 'load FAILED - uzywam DEFAULTS', $e);
            }
        }
    }

    private function get(string $key): string
    {
        return $this->values[$key] ?? (self::DEFAULTS[$key] ?? '');
    }

    public function isEnabled(): bool
    {
        return (int)$this->get('enabled') === 1;
    }

    public function baseCostFraction(): float
    {
        return max(0.0, (float)$this->get('base_cost_pct') / 100.0);
    }

    private function normalizeLevel(string $level): string
    {
        return in_array($level, self::LEVELS, true) ? $level : 'shaky';
    }

    public function catchChanceFor(string $level): int
    {
        return max(0, min(100, (int)$this->get('catch_pct_' . $this->normalizeLevel($level))));
    }

    public function priceMultFor(string $level): float
    {
        return max(0.0, (float)$this->get('price_mult_' . $this->normalizeLevel($level)));
    }

    public function penaltySuccess(): int
    {
        return max(0, (int)$this->get('credibility_penalty_success'));
    }

    public function penaltyCaught(): int
    {
        return max(0, (int)$this->get('credibility_penalty_caught'));
    }

    public function cooldownExtraMinutes(): int
    {
        return max(0, (int)$this->get('cooldown_extra_minutes'));
    }

    /**
     * Zwraca wszystkie wartosci (dla formularza panelu admina).
     * Returns all values (for the admin panel form).
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        $out = self::DEFAULTS;
        foreach ($this->values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    /**
     * Zapisuje konfiguracje z panelu admina. Akceptuje tylko znane klucze,
     * wartosci sa sanityzowane przez gettery przy kolejnym odczycie.
     * Persists configuration from the admin panel. Only known keys are accepted;
     * values are sanitised by the getters on the next read.
     *
     * @param array<string,string|int|float> $values
     */
    public function save(array $values): void
    {
        $upsert = $this->driver() === 'sqlite'
            ? "INSERT INTO bribery_config (setting_key, setting_value) VALUES (?, ?)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value"
            : "INSERT INTO bribery_config (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $this->db->prepare($upsert);
        foreach (self::DEFAULTS as $key => $default) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $stmt->execute([$key, (string)$values[$key]]);
        }
        $this->load();
    }
}
