<?php

require_once __DIR__ . '/Well/ConfigTrait.php';
require_once __DIR__ . '/Well/QueryTrait.php';
require_once __DIR__ . '/Well/CostsTrait.php';
require_once __DIR__ . '/Well/ActionsTrait.php';
require_once __DIR__ . '/Well/TickTrait.php';
require_once __DIR__ . '/Well/DisastersTrait.php';
require_once __DIR__ . '/Well/SellTrait.php';

/**
 * WellService main well management class.
 * Logic split into traits in src/Well/:
 * ConfigTrait configuration (well_config)
 * QueryTrait data retrieval (getWell, getPlayerWells)
 * CostsTrait cost and production calculators
 * ActionsTrait player actions (upgrade, maintenance)
 * TickTrait tick processors (degradation, wear, risk, spiral)
 * DisastersTrait disaster triggers (blowout, contamination, spill)
 * SellTrait well sale
 */
class WellService
{
    use WellConfigTrait;
    use WellQueryTrait;
    use WellCostsTrait;
    use WellActionsTrait;
    use WellTickTrait;
    use WellDisastersTrait;
    use WellSellTrait;

 // Required by traits
    private PDO $db;

 /** @var array<int,bool> strażnik schematu per połączenie (raz na proces, ponownie dla nowego PDO w testach) */
    private static array $schemaEnsured = [];

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->loadConfig();
            $this->ensureSchema();
            if (class_exists('GameLog', false)) {
                GameLog::step('WellService', 'init', 1, 'DB + config loaded');
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', '__construct FAILED', $e);
            }
            throw $e;
        }
    }

 /**
 * Upewnia się, że ENUM wells.status zawiera 'equipment_swap'.
 * Ensures the wells.status ENUM contains 'equipment_swap'.
 *
 * Bez tej wartości zapis statusu przy wymianie sprzętu (ActionsTrait)
 * na MySQL w trybie nie-strict cicho zamieniał się w pusty string '',
 * przez co odwiert pokazywał surowy klucz "WELL.STATUS." i przestawał
 * produkować ropę (tick nie rozpoznawał stanu equipment_swap). Przy
 * pierwszym uruchomieniu po wdrożeniu naprawiamy też odwierty, które
 * utknęły z pustym statusem.
 *
 * Without that value the equipment-swap status write (ActionsTrait) on
 * non-strict MySQL silently became an empty string '', so the well
 * displayed the raw key "WELL.STATUS." and stopped producing oil (the
 * tick never recognised the equipment_swap state). On the first run after
 * deploy we also repair wells stuck with an empty status.
 */
    private function ensureSchema(): void
    {
        $connId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$connId])) {
            return;
        }
        self::$schemaEnsured[$connId] = true;

        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `wells` LIKE 'status'");
            $col  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$col || !isset($col['Type']) || !preg_match('/^enum\((.*)\)$/i', (string)$col['Type'], $m)) {
                return;
            }
            $existing = array_map(
                static fn ($v) => trim($v, "'"),
                str_getcsv($m[1], ',', "'")
            );
            if (in_array('equipment_swap', $existing, true)) {
                return; // enum już poprawny / enum already correct
            }

            $allValues = array_merge($existing, ['equipment_swap']);
            $quoted    = implode(',', array_map(fn ($v) => $this->db->quote($v), $allValues));
            $this->db->exec(
                "ALTER TABLE `wells` MODIFY COLUMN `status` ENUM({$quoted}) NOT NULL DEFAULT 'active'"
            );

 // Napraw odwierty, które utknęły z pustym statusem przez wcześniejsze
 // nieudane zapisy. / Repair wells stuck with an empty status from prior failed writes.
            $this->db->exec("
                UPDATE wells
                SET status = CASE
                        WHEN equipment_swap_until IS NOT NULL AND equipment_swap_until > NOW() THEN 'equipment_swap'
                        WHEN equipment_swap_prev_status IS NOT NULL AND equipment_swap_prev_status <> '' THEN equipment_swap_prev_status
                        ELSE 'active'
                    END,
                    equipment_swap_until = CASE
                        WHEN equipment_swap_until IS NOT NULL AND equipment_swap_until > NOW() THEN equipment_swap_until
                        ELSE NULL
                    END
                WHERE status = ''
            ");

            if (class_exists('GameLog', false)) {
                GameLog::info('WellService', 'wells.status enum extended with equipment_swap + stuck wells repaired');
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'ensureSchema failed', $e);
            }
        }
    }
}
