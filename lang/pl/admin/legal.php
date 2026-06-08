<?php
declare(strict_types=1);

/**
 * Admin translations - legal department.
 * Tłumaczenia admina - dział prawny.
 */

return [
    'admin.legal.title'    => 'Dział prawny — zezwolenia',
    'admin.legal.subtitle' => 'Zarządzaj konfiguracją regionów i wnioskami o zezwolenia na wiercenie.',

    // Zakładki
    'admin.legal.tab_regions'      => 'Konfiguracja regionów',
    'admin.legal.tab_applications' => 'Wnioski graczy',

    // Statystyki
    'admin.legal.stat_total'   => 'Wszystkich wniosków',
    'admin.legal.stat_pending' => 'W toku',
    'admin.legal.stat_granted' => 'Przyznane',
    'admin.legal.stat_refused' => 'Odrzucone',
    'admin.legal.stat_regions' => 'Regionów',

    // Tabela regionów
    'admin.legal.regions_title'  => 'Parametry regionów',
    'admin.legal.no_regions'     => 'Brak skonfigurowanych regionów. Kliknij „Seeduj regiony", aby załadować regiony z mapy świata.',
    'admin.legal.col_region'     => 'Region',
    'admin.legal.col_risk'       => 'Ryzyko',
    'admin.legal.col_enabled'    => 'Włączony',
    'admin.legal.col_offshore'   => 'Offshore',
    'admin.legal.col_cost'       => 'Koszt (PLN)',
    'admin.legal.col_review_min' => 'Czas (min)',
    // Brief §10.2: pełne, jednoznaczne nazwy pól ryzyka (nie samo „opóźnienie").
    // Brief §10.2: full, unambiguous risk field names (not bare "delay").
    'admin.legal.col_delay_pct'       => 'Ryzyko opóźn. %',
    'admin.legal.col_delay_pct_hint'  => 'Ryzyko opóźnienia decyzji',
    'admin.legal.col_delay_min'       => 'Min opóźn. (min)',
    'admin.legal.col_delay_max'       => 'Max opóźn. (min)',
    'admin.legal.col_refusal_pct'     => 'Ryzyko odmowy %',
    'admin.legal.col_refusal_pct_hint'=> 'Ryzyko odmowy wydania zezwolenia',
    'admin.legal.col_nodec_pct'       => 'Ryzyko braku dec. %',
    'admin.legal.col_nodec_pct_hint'  => 'Ryzyko braku decyzji urzędu',
    'admin.legal.col_cooldown'   => 'Cooldown (min)',
    'admin.legal.col_capital'    => 'Min. kapitał',
    'admin.legal.col_legal_level' => 'Poz. prawny',
    'admin.legal.btn_save'       => 'Zapisz',

    // Tabela wniosków
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
    // Brief §16.3: modal potwierdzenia z konkretnym graczem i regionem.
    // Brief §16.3: confirmation modal naming the specific player and region.
    'admin.legal.confirm_manual'      => 'Akcja „:action" dla gracza :player (region: :region). Czy na pewno wykonać?',

    // Komunikaty sukcesu / błędu
    'admin.legal.msg_region_saved'      => 'Konfiguracja regionu #:id została zapisana.',
    'admin.legal.msg_manual_grant'      => 'Zezwolenie przyznane ręcznie.',
    'admin.legal.msg_manual_transitional' => 'Status zmieniony na zezwolenie przejściowe.',
    'admin.legal.msg_manual_no_decision'=> 'Wniosek oznaczony jako brak decyzji.',
    'admin.legal.msg_manual_refuse'     => 'Wniosek odrzucony ręcznie.',
    'admin.legal.msg_manual_reset'      => 'Wniosek zresetowany do statusu pending.',

    'admin.legal.err_save'       => 'Błąd zapisu konfiguracji',
    'admin.legal.err_action'     => 'Błąd wykonania akcji',
    'admin.legal.err_load_apps'  => 'Błąd ładowania wniosków',
    'admin.legal.err_migration'  => 'Błąd migracji',

    // Seed konfiguracji regionów
    'admin.legal.seed_title'       => 'Konfiguracja regionów — seed',
    'admin.legal.seed_hint'        => 'Załaduj konfigurację działu prawnego dla wszystkich regionów z mapy świata (world_regions). Poziom ryzyka mapowany automatycznie z ryzyka politycznego regionu. Operacja jest idempotentna — nie nadpisuje istniejących wpisów. Uruchom raz po wdrożeniu, aby gracze mogli składać wnioski.',
    'admin.legal.btn_seed_regions' => 'Seeduj regiony',
    'admin.legal.seed_confirm'     => 'Załadować konfigurację regionów z mapy świata? Operacja jest bezpieczna i idempotentna.',
    'admin.legal.msg_seed_done'    => 'Seed zakończony. Nowe regiony skonfigurowane: :n.',
    'admin.legal.err_seed'         => 'Błąd seedowania regionów',

    // Migracja przejściowa
    'admin.legal.migration_title'   => 'Migracja — zezwolenia przejściowe',
    'admin.legal.migration_hint'    => 'Uruchom raz po wdrożeniu P1. Dla każdego gracza który ma odwiert w regionie lecz nie ma jeszcze zezwolenia zostanie automatycznie przyznane zezwolenie przejściowe (transitional). Operacja jest idempotentna.',
    'admin.legal.btn_run_migration' => 'Uruchom migrację',
    'admin.legal.migration_confirm' => 'Uruchomić migrację zezwoleń przejściowych? Operacja jest bezpieczna i idempotentna.',
    'admin.legal.msg_migration_done'=> 'Migracja zakończona. Nowe wpisy przejściowe: :n.',

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
