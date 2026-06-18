<?php
declare(strict_types=1);

/**
 * Panel admina — modul lapowek (konfiguracja).
 * Admin panel — bribery module (configuration).
 */

return [
    'admin.bribery.title'    => 'Łapówki',
    'admin.bribery.subtitle' => 'Centralna konfiguracja modułu łapówek. Parametry działają wszędzie, gdzie łapówka jest podpięta (np. dział prawny).',

    'admin.bribery.msg_saved' => 'Zapisano konfigurację łapówek.',
    'admin.bribery.err_save'  => 'Nie udało się zapisać konfiguracji',

    // Sekcja globalna
    'admin.bribery.section_global'      => 'Ustawienia globalne',
    'admin.bribery.section_global_hint' => 'Wyłączenie modułu ukrywa łapówki we wszystkich miejscach gry.',
    'admin.bribery.field_enabled'         => 'Łapówki włączone',
    'admin.bribery.field_base_cost_pct'   => 'Bazowy koszt (% kosztu odniesienia)',
    'admin.bribery.field_penalty_success' => 'Kara reputacji przy sukcesie',
    'admin.bribery.field_penalty_caught'  => 'Kara reputacji przy wpadce',
    'admin.bribery.field_cooldown_extra'  => 'Dodatkowa blokada po wpadce (min)',

    // Sekcja per poziom
    'admin.bribery.section_levels'      => 'Cena i ryzyko wg reputacji firmy',
    'admin.bribery.section_levels_hint' => 'Im gorsza reputacja, tym wyższe ryzyko wpadki i drożsi urzędnicy. Mnożnik mnoży koszt bazowy.',
    'admin.bribery.field_catch_pct'  => 'Ryzyko wpadki (%)',
    'admin.bribery.field_price_mult' => 'Mnożnik ceny',
    'admin.bribery.level_critical' => 'Krytyczna (0–19)',
    'admin.bribery.level_low'      => 'Niska (20–39)',
    'admin.bribery.level_shaky'    => 'Chwiejna (40–59)',
    'admin.bribery.level_stable'   => 'Stabilna (60–79)',
    'admin.bribery.level_high'     => 'Wysoka (80–100)',

    'admin.bribery.btn_save' => 'Zapisz konfigurację',

    // Ostatnie zdarzenia
    'admin.bribery.section_recent' => 'Ostatnie łapówki',
    'admin.bribery.recent_empty'   => 'Brak zarejestrowanych prób łapówek.',
    'admin.bribery.event_caught'   => 'Wpadka',
    'admin.bribery.event_paid'     => 'Załatwione',
];
