<?php

require_once __DIR__ . '/Well/ConfigTrait.php';
require_once __DIR__ . '/Well/QueryTrait.php';
require_once __DIR__ . '/Well/CostsTrait.php';
require_once __DIR__ . '/Well/ActionsTrait.php';
require_once __DIR__ . '/Well/TickTrait.php';
require_once __DIR__ . '/Well/DisastersTrait.php';
require_once __DIR__ . '/Well/SellTrait.php';

/**
 * WellService — main well management class.
 * Logic split into traits in src/Well/:
 *   ConfigTrait    — configuration (well_config)
 *   QueryTrait     — data retrieval (getWell, getPlayerWells)
 *   CostsTrait     — cost and production calculators
 *   ActionsTrait   — player actions (upgrade, maintenance)
 *   TickTrait      — tick processors (degradation, wear, risk, spiral)
 *   DisastersTrait — disaster triggers (blowout, contamination, spill)
 *   SellTrait      — well sale
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

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->loadConfig();
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
}