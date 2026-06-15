<?php
declare(strict_types=1);

/**
 * Panel admina — modul lapowek (konfiguracja).
 * Admin panel — bribery module (configuration).
 */

return [
    'admin.bribery.title' => 'Bribes',
    'admin.bribery.subtitle' => 'Central configuration of the bribery module. Parameters work wherever a bribe is connected (e.g. legal department).',

    'admin.bribery.msg_saved' => 'Bribes configuration saved.',
    'admin.bribery.err_save' => 'Failed To Save Preset',

    // Sekcja globalna
    'admin.bribery.section_global' => 'Global Settings',
    'admin.bribery.section_global_hint' => 'Turning off the module hides bribes in all places of the game.',
    'admin.bribery.field_enabled' => 'Bribes enabled',
    'admin.bribery.field_base_cost_pct' => 'Base cost (% of reference cost)',
    'admin.bribery.field_penalty_success' => 'Reputation penalty for success',
    'admin.bribery.field_penalty_caught' => 'Reputation penalty in the event of a mishap',
    'admin.bribery.field_cooldown_extra' => 'Additional Lockout (mins)',

    // Sekcja per poziom
    'admin.bribery.section_levels' => 'Price and risk by company reputation',
    'admin.bribery.section_levels_hint' => 'The worse the reputation, the higher the risk of mishaps and more expensive officials. Multiplier multiplies the base cost.',
    'admin.bribery.field_catch_pct' => 'Risk of accident (%)',
    'admin.bribery.field_price_mult' => 'Price multiplier',
    'admin.bribery.level_critical' => 'Critical (0–19)',
    'admin.bribery.level_low' => 'Low (20–39)',
    'admin.bribery.level_shaky' => 'Shaky (40–59)',
    'admin.bribery.level_stable' => 'Stable (60–79)',
    'admin.bribery.level_high' => 'High (80–100)',

    'admin.bribery.btn_save' => 'Save the configuration',

    // Ostatnie zdarzenia
    'admin.bribery.section_recent' => 'Recent bribes',
    'admin.bribery.recent_empty' => 'There are no recorded bribery attempts.',
    'admin.bribery.event_caught' => 'Knocked Up',
    'admin.bribery.event_paid' => 'Done.',
];
