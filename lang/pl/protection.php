<?php
declare(strict_types=1);

/**
 * Modul ochrony — uniwersalne komunikaty silnika + UI gracza.
 * Protection module — universal engine messages + player UI.
 */

return [
    // Bledy / Errors
    'protection.err_not_found'      => 'Ta opcja ochrony nie istnieje.',
    'protection.err_disabled'       => 'Ta ochrona jest obecnie niedostępna.',
    'protection.err_req_credibility'=> 'Wymagana wiarygodność firmy: co najmniej :min/100.',
    'protection.err_req_legal'      => 'Wymagany poziom działu prawnego: co najmniej :min/10.',
    'protection.err_already_active' => 'Ten cel ma już aktywną ochronę (do :ends).',
    'protection.err_no_funds'       => 'Brakuje gotówki na ochronę (potrzeba :cost PLN).',
    'protection.err_generic'        => 'Nie udało się aktywować ochrony. Spróbuj ponownie.',

    // Wynik / Outcome
    'protection.msg_activated'      => 'Ochrona ":name" aktywna. Pobrano :cost PLN.',

    // Opis transakcji w historii / Transaction description in history
    'protection.tx_label'           => 'Ochrona — :name',

    // Wpisy historii ochrony / Protection history entries
    'protection.log_activated'      => 'Wykupiono ochronę ":name".',

    // Powiadomienie dyrektora / Director notification
    'protection.notif.activated.title'   => 'Ochrona aktywowana',
    'protection.notif.activated.message' => 'Ochrona ":name" działa do :ends. :target',

    // Opisy sily efektu dla gracza (bez mnoznikow) / Effect strength text (no multipliers)
    'protection.effect.strong'      => 'Znacznie zmniejsza ryzyko :what.',
    'protection.effect.medium'      => 'Zmniejsza ryzyko :what.',
    'protection.effect.light'       => 'Lekko zmniejsza ryzyko :what.',
    'protection.effect.disclaimer'  => 'Nie chroni przed awarią pojazdu, pogodą ani korkami.',

    // Nazwy ryzyk do opisow / Risk names for descriptions
    'protection.risk.theft_risk_mult'    => 'kradzieży',
    'protection.risk.raid_risk_mult'     => 'napadu',
    'protection.risk.sabotage_risk_mult' => 'sabotażu',

    // Walidacja celu (endpoint) / Target validation (endpoint)
    'protection.err_target_invalid'  => 'Ten odwiert nie istnieje lub nie należy do Ciebie.',
    'protection.err_target_not_road' => 'Ochrona kursów działa tylko dla odwiertów z transportem ciężarówkami.',
    'protection.target_well'         => 'Odwiert #:id',

    // Sekcja w logistyce / Logistics section
    'protection.section_title'    => 'Ochrona transportu drogowego',
    'protection.section_desc'     => 'Ochrona zmniejsza ryzyko kradzieży, napadu i sabotażu kursów',
    'protection.col_well'         => 'Odwiert',
    'protection.col_protection'   => 'Ochrona',
    'protection.col_until'        => 'Działa do',
    'protection.col_action'       => 'Akcja',
    'protection.status_none'      => 'brak ochrony',
    'protection.btn_add'          => 'Dodaj ochronę',
    'protection.btn_buy'          => 'Wykup ochronę',
    'protection.btn_cancel'       => 'Anuluj',
    'protection.modal_title'      => 'Wybierz ochronę',
    'protection.label_cost'       => 'Koszt:',
    'protection.label_duration'   => 'Czas działania:',
    'protection.label_payment'    => 'Płatność: gotówką',
    'protection.duration_minutes' => ':min minut',
    'protection.locked_credibility' => 'Wymaga wiarygodności firmy :min/100.',
    'protection.locked_legal'       => 'Wymaga działu prawnego na poziomie :min/10.',
    'protection.not_affordable'     => 'Brak gotówki.',
    'protection.confirm_question'   => 'Wykupić ochronę ":name" za :cost PLN?',
];
