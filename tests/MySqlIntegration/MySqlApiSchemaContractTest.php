<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Kontrakt schematu dla endpointow api/v1/*.
 * Schema contract for api/v1/* endpoints.
 *
 * Pilnuje, ze kolumny czytane przez API faktycznie istnieja w bazie (ci-schema = produkcja).
 * Gdy ktos zmieni schemat albo doda modul z innym nazewnictwem kolumn, ten test wskaze
 * brakujaca kolumne ZANIM endpoint zwroci 500 i aplikacja pokaze "Dane rynku niedostepne".
 *
 * Asserts that columns read by the API really exist in the DB (ci-schema = production).
 * Catches schema/code drift before an endpoint 500s and the app shows an error banner.
 *
 * AKTUALIZACJA przy nowym module: dopisz `tabela => [kolumny]` uzywane przez nowy endpoint.
 * UPDATE for a new module: add `table => [columns]` used by the new endpoint.
 */
final class MySqlApiSchemaContractTest extends TestCase
{
    /** @var array<string,string[]> tabela => kolumny wymagane przez api/v1/* */
    private const CONTRACT = [
        // /api/v1/player/index.php
        'players' => [
            'id', 'username', 'company_name', 'cash', 'bank_balance', 'financial_state',
            'crisis_ticks', 'credit_score', 'offline_mode', 'offline_since', 'last_tick_at',
            'last_active_at', 'created_at', 'safety_procedures_level', 'procedure_integrity',
            'bankruptcy_status',
        ],
        'storage' => ['player_id', 'capacity', 'used'],

        // /api/v1/market/index.php
        'market_state' => [
            'id', 'current_price', 'base_price', 'volatility', 'supply_index',
            'demand_index', 'last_market_tick_at',
        ],
        'market_trends' => [
            'trend_name', 'category', 'price_modifier', 'duration_hours',
            'message_template', 'active', 'activated_at',
        ],
        'market_offers' => [
            'id', 'player_id', 'amount', 'limit_price', 'status', 'created_at', 'completed_at',
        ],

        // /api/v1/wells/index.php
        'wells' => [
            'id', 'player_id', 'name', 'well_name', 'location_name', 'status', 'well_type',
            'transport_type', 'base_production_per_hour', 'upkeep_cost_per_hour',
            'technical_condition', 'wear_level', 'equipment_tier', 'equipment_upgrade_level',
            'production_mode', 'reservoir_remaining', 'reservoir_max', 'risk_level',
            'risk_score', 'regional_tax_rate', 'last_production_at', 'created_at',
        ],

        // /api/v1/auth (token storage)
        'api_tokens' => ['player_id', 'token', 'device', 'created_at', 'last_used_at', 'expires_at'],

        // /api/v1/maps/index.php
        'world_regions' => [
            'id', 'code', 'name', 'political_risk', 'entry_cost', 'production_bonus',
            'tax_rate', 'opex_mult', 'color_hex',
        ],
        'world_locations' => [
            'id', 'region_id', 'name', 'latitude', 'longitude', 'oil_richness',
            'well_type', 'tier', 'available', 'entry_cost_override', 'tax_rate_override',
        ],
        'legal_region_config' => [
            'region_id', 'enabled', 'risk_level', 'application_cost',
            'base_review_minutes', 'required_capital', 'required_legal_level',
        ],

        // /api/v1/permits/apply.php
        'drilling_permit_applications' => [
            'id', 'player_id', 'region_id', 'status', 'cost',
            'submitted_at', 'decision_due_at', 'decided_at',
            'refusal_cooldown_until', 'delay_count',
        ],
    ];

    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();

        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];
        $this->db = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // api_tokens powstaje w runtime (ApiAuth::ensureSchema), nie w ci-schema.
        // legal_region_config + drilling_permit_applications istnieja w ci-schema,
        // ale LegalService::ensureSchema() dodaje brakujace kolumny (bribe_locked_until etc.).
        require_once dirname(__DIR__, 2) . '/src/GameLog.php';
        require_once dirname(__DIR__, 2) . '/src/Database.php';
        require_once dirname(__DIR__, 2) . '/src/ApiAuth.php';
        require_once dirname(__DIR__, 2) . '/src/i18n.php';
        require_once dirname(__DIR__, 2) . '/src/LegalService.php';
        GameLog::setEnabled(false);
        ApiAuth::ensureSchema();
        new LegalService(); // ensureSchema() + autoSeedIfEmpty() w konstruktorze
    }

    protected function tearDown(): void
    {
        unset($this->db);
        parent::tearDown();
    }

    public function testApiColumnsExistInSchema(): void
    {
        $dbName = (string)$this->db->query('SELECT DATABASE()')->fetchColumn();

        foreach (self::CONTRACT as $table => $required) {
            $stmt = $this->db->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
            );
            $stmt->execute([$dbName, $table]);
            $have = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $this->assertNotEmpty(
                $have,
                "Tabela `{$table}` nie istnieje w bazie testowej (ci-schema lub ensureSchema)."
            );

            $missing = array_values(array_diff($required, $have));
            $this->assertSame(
                [],
                $missing,
                "Tabela `{$table}` nie ma kolumn wymaganych przez api/v1: " . implode(', ', $missing)
            );
        }
    }
}
