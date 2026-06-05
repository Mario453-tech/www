<?php
declare(strict_types=1);

/**
 * Admin translations - company credibility.
 * Tlumaczenia admina - wiarygodnosc firmy.
 */

return [
    'admin.credibility.title'    => 'Wiarygodność firm',
    'admin.credibility.subtitle' => 'Podgląd wiarygodności firm graczy oraz historia zmian. Ręczna korekta zapisuje się w historii.',

    // Statystyki / Stats
    'admin.credibility.stat_players'  => 'Graczy',
    'admin.credibility.stat_avg'      => 'Średnia wiarygodność',
    'admin.credibility.stat_critical' => 'Krytyczna (0–19)',
    'admin.credibility.stat_high'     => 'Wysoka (80–100)',

    // Lista graczy / Player list
    'admin.credibility.players_title' => 'Gracze',
    'admin.credibility.no_players'    => 'Brak graczy.',
    'admin.credibility.col_player'    => 'Gracz',
    'admin.credibility.col_score'     => 'Wynik',
    'admin.credibility.col_level'     => 'Poziom',
    'admin.credibility.col_actions'   => 'Akcje',
    'admin.credibility.btn_history'   => 'Historia',
    'admin.credibility.btn_adjust'    => 'Korekta',

    // Poziomy opisowe / Levels
    'admin.credibility.level_critical' => 'krytyczna',
    'admin.credibility.level_low'      => 'niska',
    'admin.credibility.level_shaky'    => 'chwiejna',
    'admin.credibility.level_stable'   => 'stabilna',
    'admin.credibility.level_high'     => 'wysoka',

    // Historia / History
    'admin.credibility.history_title'   => 'Historia zmian — :player',
    'admin.credibility.history_back'    => 'Wróć do listy',
    'admin.credibility.no_history'      => 'Brak zapisanych zmian dla tego gracza.',
    'admin.credibility.col_date'        => 'Data',
    'admin.credibility.col_event'       => 'Zdarzenie',
    'admin.credibility.col_delta'       => 'Zmiana',
    'admin.credibility.col_before'      => 'Przed',
    'admin.credibility.col_after'       => 'Po',
    'admin.credibility.col_note'        => 'Notatka',

    // Reczna korekta — modal / Manual adjustment modal
    'admin.credibility.adjust_title'        => 'Ręczna korekta wiarygodności firmy',
    'admin.credibility.adjust_intro'        => 'Podaj zmianę wyniku oraz powód korekty. Ta operacja zostanie zapisana w historii.',
    'admin.credibility.adjust_delta_label'  => 'Zmiana wyniku (np. -10 lub 5)',
    'admin.credibility.adjust_note_label'   => 'Notatka / powód',
    'admin.credibility.adjust_note_ph'      => 'Powód korekty (wymagany)',
    'admin.credibility.adjust_cancel'       => 'Anuluj',
    'admin.credibility.adjust_save'         => 'Zapisz korektę',

    // Komunikaty / Messages
    'admin.credibility.msg_adjusted'   => 'Wiarygodność firmy gracza #:id została skorygowana (:before → :after).',
    'admin.credibility.err_adjust'     => 'Błąd korekty wiarygodności',
    'admin.credibility.err_need_note'  => 'Korekta wymaga podania notatki / powodu.',
    'admin.credibility.err_zero_delta' => 'Zmiana wyniku nie może wynosić 0.',
    'admin.credibility.err_load'       => 'Błąd ładowania danych wiarygodności',
];
