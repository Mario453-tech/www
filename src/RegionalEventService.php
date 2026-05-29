<?php

require_once __DIR__ . '/RegionalEvent/EventsTrait.php';
require_once __DIR__ . '/RegionalEvent/ModifiersTrait.php';

/**
 * RegionalEventService region-specific geopolitical events.
 *
 * Event types:
 * production_stop production halt (Africa: local conflicts, supply failures)
 * conflict_tax war-tax surcharge (Middle East, Russia)
 * pipeline_block pipeline blockage (Russia, Middle East)
 *
 * Called from tick.php once per player (checks regions of their active wells).
 *
 * Logic split into traits in src/RegionalEvent/:
 * - EventsTrait.php processTick, triggerEvent, resolveExpired
 * - ModifiersTrait.php getActiveEvents, getWellModifiers, getStopPct, getBonusPct, getTaxExtra
 */
class RegionalEventService
{
    use RegionalEventsTrait;
    use RegionalModifiersTrait;

    private PDO $db;

 // Event probability per region per 24h (fraction); checked each tick, scaled by deltaHours
    private const EVENT_CHANCE = [
        'middle_east'    => 0.12,  // 12%/24h � conflicts, embargoes
        'russia'         => 0.10,  // 10%/24h � sanctions, blockades
        'africa'         => 0.15,  // 15%/24h � outages, infrastructure failures
        'usa_canada'     => 0.02,  // 2%/24h  � near-zero risk
        'north_europe'   => 0.03,  // 3%/24h  � weather conditions
        'southeast_asia' => 0.07,  // 7%/24h
        'latam'          => 0.09,  // 9%/24h  � strikes, nationalisation
    ];

 // Event definitions per region (msg keys resolved via t() at display time)
    private const EVENTS = [
        'middle_east' => [
            ['type' => 'conflict_tax',    'severity' => 2, 'duration_h' => 24, 'tax_extra' => 0.05,
             'msg' => 'regional_event.msg.middle_east_0'],
            ['type' => 'production_stop', 'severity' => 3, 'duration_h' => 12, 'stop_pct' => 0.40,
             'msg' => 'regional_event.msg.middle_east_1'],
            ['type' => 'pipeline_block',  'severity' => 2, 'duration_h' => 18, 'stop_pct' => 0.25,
             'msg' => 'regional_event.msg.middle_east_2'],
        ],
        'russia' => [
            ['type' => 'conflict_tax',    'severity' => 2, 'duration_h' => 48, 'tax_extra' => 0.08,
             'msg' => 'regional_event.msg.russia_0'],
            ['type' => 'pipeline_block',  'severity' => 2, 'duration_h' => 24, 'stop_pct' => 0.30,
             'msg' => 'regional_event.msg.russia_1'],
        ],
        'africa' => [
            ['type' => 'production_stop', 'severity' => 2, 'duration_h' => 18, 'stop_pct' => 0.50,
             'msg' => 'regional_event.msg.africa_0'],
            ['type' => 'production_stop', 'severity' => 1, 'duration_h' => 8,  'stop_pct' => 0.25,
             'msg' => 'regional_event.msg.africa_1'],
            ['type' => 'conflict_tax',    'severity' => 1, 'duration_h' => 36, 'tax_extra' => 0.03,
             'msg' => 'regional_event.msg.africa_2'],
            ['type' => 'production_bonus','severity' => 1, 'duration_h' => 16, 'bonus_pct' => 0.12,
             'msg' => 'regional_event.msg.africa_3'],
        ],
        'north_europe' => [
            ['type' => 'production_stop', 'severity' => 1, 'duration_h' => 12, 'stop_pct' => 0.30,
             'msg' => 'regional_event.msg.north_europe_0'],
        ],
        'southeast_asia' => [
            ['type' => 'production_stop', 'severity' => 1, 'duration_h' => 10, 'stop_pct' => 0.20,
             'msg' => 'regional_event.msg.southeast_asia_0'],
            ['type' => 'conflict_tax',    'severity' => 1, 'duration_h' => 24, 'tax_extra' => 0.02,
             'msg' => 'regional_event.msg.southeast_asia_1'],
        ],
        'latam' => [
            ['type' => 'production_stop', 'severity' => 2, 'duration_h' => 24, 'stop_pct' => 0.35,
             'msg' => 'regional_event.msg.latam_0'],
            ['type' => 'conflict_tax',    'severity' => 2, 'duration_h' => 48, 'tax_extra' => 0.06,
             'msg' => 'regional_event.msg.latam_1'],
        ],
        'usa_canada' => [
            ['type' => 'production_stop', 'severity' => 1, 'duration_h' => 6, 'stop_pct' => 0.10,
             'msg' => 'regional_event.msg.usa_canada_0'],
        ],
    ];

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            GameLog::error('RegionalEventService', '__construct FAILED', $e);
            throw $e;
        }
    }
}
