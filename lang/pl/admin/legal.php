<?php
declare(strict_types=1);

/**
 * Admin translations - legal department.
 * Tlumaczenia admina - dzial prawny.
 */

return [
    'admin.legal.title'    => 'Dzial prawny - zezwolenia',
    'admin.legal.subtitle' => 'Zarzadzaj konfiguracja regionow i wnioskami o zezwolenia na wiercenie.',

    // Zakladki
    'admin.legal.tab_regions'      => 'Konfiguracja regionow',
    'admin.legal.tab_applications' => 'Wnioski graczy',

    // Statystyki
    'admin.legal.stat_total'   => 'Wszystkich wnioskow',
    'admin.legal.stat_pending' => 'W toku',
    'admin.legal.stat_granted' => 'Przyznane',
    'admin.legal.stat_refused' => 'Odrzucone',
    'admin.legal.stat_regions' => 'Regionow',

    // Tabela regionow
    'admin.legal.regions_title'  => 'Parametry regionow',
    'admin.legal.regions_intro'  => 'Tutaj ustawiasz, jak trudne i kosztowne sa decyzje urzedowe w kazdym regionie. W skrocie: ile gracz placi, ile czeka, jakie ma ryzyko odmowy i czy potrzebuje dodatkowej zgody na hub logistyczny.',
    'admin.legal.no_regions'     => 'Brak skonfigurowanych regionow. Kliknij Seeduj regiony, aby zaladowac regiony z mapy swiata.',
    'admin.legal.group_region_state' => 'Stan regionu',
    'admin.legal.group_drilling_permit' => 'Wniosek o odwiert',
    'admin.legal.group_player_requirements' => 'Warunki wejscia',
    'admin.legal.group_hub_permit' => 'Hub logistyczny',
    'admin.legal.btn_show_advanced' => 'Pokaz zaawansowane kolumny',
    'admin.legal.col_region'     => 'Region',
    'admin.legal.col_risk'       => 'Ryzyko',
    'admin.legal.col_enabled'    => 'Wlaczony',
    'admin.legal.col_offshore'   => 'Offshore',
    'admin.legal.col_cost'       => 'Koszt (PLN)',
    'admin.legal.col_review_min' => 'Czas (min)',
    // Brief 10.2: pelne, jednoznaczne nazwy pol ryzyka (nie samo 'opoznienie').
    // Brief 10.2: full, unambiguous risk field names (not bare delay).
    'admin.legal.col_delay_pct'       => 'Ryzyko opozn. %',
    'admin.legal.col_delay_pct_hint'  => 'Ryzyko opoznienia decyzji',
    'admin.legal.col_delay_min'       => 'Min opozn. (min)',
    'admin.legal.col_delay_max'       => 'Max opozn. (min)',
    'admin.legal.col_refusal_pct'     => 'Ryzyko odmowy %',
    'admin.legal.col_refusal_pct_hint'=> 'Ryzyko odmowy wydania zezwolenia',
    'admin.legal.col_nodec_pct'       => 'Ryzyko braku dec. %',
    'admin.legal.col_nodec_pct_hint'  => 'Ryzyko braku decyzji urz?du',
    'admin.legal.col_cooldown'   => 'Cooldown (min)',
    'admin.legal.col_capital'    => 'Min. kapita?',
    'admin.legal.col_legal_level' => 'Poz. prawny',
    'admin.legal.col_actions_short' => 'Zapis',
    'admin.legal.btn_save'       => 'Zapisz',
    'admin.legal.guide_basics_title' => 'Koszt i czas',
    'admin.legal.guide_basics_text' => 'To podstawowe ustawienia wniosku. Okre?laj?, ile gracz p?aci za z?o?enie papier?w i jak d?ugo zwykle czeka na odpowied? urz?du.',
    'admin.legal.guide_risk_title' => 'Ryzyka decyzji',
    'admin.legal.guide_risk_text' => 'Te pola mówią, co może pójść nie po myśli gracza: urząd może się spóźnić, odmówić albo nie wydać decyzji wcale.',
    'admin.legal.guide_requirements_title' => 'Warunki wejścia',
    'admin.legal.guide_requirements_text' => 'To minimalne progi dla firmy. Jeśli gracz nie ma tyle kapitału albo za słaby dział prawny, nie złoży wniosku w tym regionie.',
    'admin.legal.guide_hub_title' => 'Hub logistyczny',
    'admin.legal.guide_hub_text' => 'To osobna zgoda na budowę hubu. Włącz ją tam, gdzie chcesz utrudnić rozwój logistyki i dodać drugi etap formalności.',

    // Applications table
    'admin.legal.applications_title' => 'Wnioski o zezwolenia (ostatnie 500)',
    'admin.legal.no_applications'    => 'Brak wniosków.',
    'admin.legal.col_player'         => 'Gracz',
    'admin.legal.col_region_app'     => 'Region',
    'admin.legal.col_status'         => 'Status',
    'admin.legal.col_submitted'      => 'Złożono',
    'admin.legal.col_due'            => 'Termin decyzji',
    'admin.legal.col_decided'        => 'Decyzja wydana',
    'admin.legal.col_actions'        => 'Akcje',
    'admin.legal.delay_count_label'  => 'opóźnień: :n',

    // Przyciski akcji manualnych
    'admin.legal.action_grant'        => 'Przyznaj',
    'admin.legal.action_transitional' => 'Przejściowe',
    'admin.legal.action_no_decision'  => 'Brak dec.',
    'admin.legal.action_refuse'       => 'Odrzuć',
    'admin.legal.action_reset'        => 'Reset do pending',
    'admin.legal.confirm_action'      => 'Potwierdzasz wykonanie tej akcji?',
    // Brief §16.3: confirmation modal naming the specific player and region.
    // Brief §16.3: confirmation modal naming the specific player and region.
    'admin.legal.confirm_manual'      => 'Akcja :action dla gracza :player (region: :region). Czy na pewno wykonać?',

    // Success and error messages
    'admin.legal.msg_region_saved'      => 'Konfiguracja regionu #:id została zapisana.',
    'admin.legal.msg_manual_grant'      => 'Zezwolenie przyznane ręcznie.',
    'admin.legal.msg_manual_transitional' => 'Status zmieniony na zezwolenie przejściowe.',
    'admin.legal.msg_manual_no_decision'=> 'Wniosek oznaczony jako brak decyzji.',
    'admin.legal.msg_manual_refuse'     => 'Wniosek odrzucony ręcznie.',
    'admin.legal.msg_manual_reset'      => 'Wniosek zresetowany do statusu pending.',

    'admin.legal.err_save'       => 'Błąd zapisu konfiguracji',
    'admin.legal.err_action' => 'Blad wykonania akcji',
    'admin.legal.err_load_apps' => 'Blad ladowania wnioskow',
    'admin.legal.err_migration' => 'Blad migracji',

    // Region configuration seed
    'admin.legal.seed_title' => 'Konfiguracja regionow - seed',
    'admin.legal.seed_hint' => 'Zaladuj konfiguracje dzialu prawnego dla wszystkich regionow z mapy swiata (`world_regions`). Poziom ryzyka zostanie zmapowany automatycznie z ryzyka politycznego regionu. Operacja jest idempotentna i nie nadpisuje istniejacych wpisow. Uruchom ja raz po wdrozeniu, aby gracze mogli skladac wnioski.',
    'admin.legal.btn_seed_regions' => 'Seeduj regiony',
    'admin.legal.seed_confirm' => 'Zaladowac konfiguracje regionow z mapy swiata? Operacja jest bezpieczna i idempotentna.',
    'admin.legal.msg_seed_done' => 'Seed zakonczony. Nowe regiony skonfigurowane: :n.',
    'admin.legal.err_seed' => 'Blad seedowania regionow',

    // Transitional permit migration
    'admin.legal.migration_title' => 'Migracja zezwolen przejsciowych',
    'admin.legal.migration_hint' => 'Uruchom raz po wdrozeniu P1. Kazdy gracz, ktory ma odwiert w regionie, ale nie ma jeszcze zezwolenia, otrzyma automatycznie zezwolenie przejsciowe. Operacja jest idempotentna.',
    'admin.legal.btn_run_migration' => 'Uruchom migracje',
    'admin.legal.migration_confirm' => 'Uruchomic migracje zezwolen przejsciowych? Operacja jest bezpieczna i idempotentna.',
    'admin.legal.msg_migration_done' => 'Migracja zakonczona. Nowe wpisy przejsciowe: :n.',

    // =========== P2a: Zezwolenia na huby / Hub permit admin ===========

    'admin.legal.hub.tab_applications'  => 'Wnioski na huby',
    'admin.legal.hub.applications_title'=> 'Wnioski o zezwolenia na huby (ostatnie 500)',
    'admin.legal.hub.no_applications'   => 'Brak wniosków o zezwolenia na huby.',

    'admin.legal.hub.stat_total'   => 'Wnioski na huby',
    'admin.legal.hub.stat_granted' => 'Przyznane (hub)',

    'admin.legal.hub.col_enabled'      => 'Hub wymagany',
    'admin.legal.hub.col_enabled_hint' => 'Czy zezwolenie na hub jest wymagane w tym regionie?',
    'admin.legal.hub.col_cost'         => 'Koszt (hub)',
    'admin.legal.hub.col_review_min'   => 'Czas (hub, min)',

    'admin.legal.hub.msg_manual_grant'       => 'Zezwolenie na hub przyznane ręcznie.',
    'admin.legal.hub.msg_manual_no_decision' => 'Wniosek (hub) oznaczony jako brak decyzji.',
    'admin.legal.hub.msg_manual_refuse'      => 'Wniosek (hub) odrzucony ręcznie.',
    'admin.legal.hub.msg_manual_reset'       => 'Wniosek (hub) zresetowany do statusu pending.',
];
