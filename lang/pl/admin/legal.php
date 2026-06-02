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
    'admin.legal.no_regions'     => 'Brak skonfigurowanych regionów. Uruchom seedRegionConfig() aby załadować regiony z mapy.',
    'admin.legal.col_region'     => 'Region',
    'admin.legal.col_risk'       => 'Ryzyko',
    'admin.legal.col_enabled'    => 'Włączony',
    'admin.legal.col_cost'       => 'Koszt (PLN)',
    'admin.legal.col_review_min' => 'Czas (min)',
    'admin.legal.col_delay_pct'  => 'Opóźn. %',
    'admin.legal.col_refusal_pct'=> 'Odmowa %',
    'admin.legal.col_nodec_pct'  => 'Brak dec. %',
    'admin.legal.col_cooldown'   => 'Cooldown (min)',
    'admin.legal.col_capital'    => 'Min. kapitał',
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

    // Przyciski akcji manualnych
    'admin.legal.action_grant'        => 'Przyznaj',
    'admin.legal.action_transitional' => 'Przejściowe',
    'admin.legal.action_no_decision'  => 'Brak dec.',
    'admin.legal.action_refuse'       => 'Odrzuć',
    'admin.legal.action_reset'        => 'Reset →pending',
    'admin.legal.confirm_action'      => 'Potwierdzasz wykonanie tej akcji?',

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
];
