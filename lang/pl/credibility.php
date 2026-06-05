<?php
declare(strict_types=1);

/**
 * Wiarygodnosc firmy — tlumaczenia dla gracza.
 * Company credibility — player-facing translations.
 */

return [
    // Karta na dashboardzie / Dashboard card (sekcja 7)
    'credibility.card_title'   => 'Wiarygodność firmy',
    'credibility.score_suffix' => '/ 100',
    'credibility.status_label' => 'Status',
    'credibility.hint'         => 'Wynik zależy od stabilności finansowej, współpracy z bankiem, naruszeń i działań ryzykownych.',

    // Poziomy opisowe / Descriptive levels (sekcja 2)
    'credibility.level_critical' => 'krytyczna',
    'credibility.level_low'      => 'niska',
    'credibility.level_shaky'    => 'chwiejna',
    'credibility.level_stable'   => 'stabilna',
    'credibility.level_high'     => 'wysoka',

    // Opisy poziomow dla gracza / Player-facing level descriptions
    'credibility.level_desc_critical' => 'Firma jest postrzegana jako bardzo ryzykowna. Część instytucji może ograniczać współpracę.',
    'credibility.level_desc_low'      => 'Firma ma słabą wiarygodność. Niektóre działania mogą być trudniejsze albo droższe.',
    'credibility.level_desc_shaky'    => 'Firma działa, ale jej sytuacja nie jest jeszcze stabilna.',
    'credibility.level_desc_stable'   => 'Firma jest postrzegana jako wiarygodna i przewidywalna.',
    'credibility.level_desc_high'     => 'Firma ma bardzo dobrą pozycję i może w przyszłości łatwiej uzyskiwać dostęp do trudniejszych regionów, umów i partnerów.',

    // Powiadomienia (sekcja 9) / Notifications
    'credibility.notif.title_up'   => 'Wiarygodność firmy wzrosła',
    'credibility.notif.title_down' => 'Wiarygodność firmy spadła',

    // Komunikaty per zdarzenie / Per-event messages
    'credibility.notif.msg_black_market_detected' => 'Wiarygodność firmy spadła po wykryciu ryzykownych działań. Aktualny poziom: :score / 100 (:level).',
    'credibility.notif.msg_bailiff_activated'     => 'Wiarygodność firmy mocno spadła po aktywacji komornika. Aktualny poziom: :score / 100 (:level).',
    'credibility.notif.msg_bankruptcy_entered'    => 'Wiarygodność firmy mocno spadła po wejściu w stan bankructwa. Aktualny poziom: :score / 100 (:level).',
    'credibility.notif.msg_recovery_plan_broken'  => 'Wiarygodność firmy spadła po złamaniu planu naprawczego. Aktualny poziom: :score / 100 (:level).',
    'credibility.notif.msg_loan_fully_repaid'     => 'Wiarygodność firmy wzrosła po pełnej spłacie kredytu. Aktualny poziom: :score / 100 (:level).',
    'credibility.notif.msg_loan_repaid_early'     => 'Wiarygodność firmy wzrosła po spłacie kredytu przed czasem. Aktualny poziom: :score / 100 (:level).',
    'credibility.notif.msg_admin_manual_adjustment' => 'Wiarygodność firmy została skorygowana. Aktualny poziom: :score / 100 (:level).',

    // Komunikaty ogolne (fallback) / Generic fallback messages
    'credibility.notif.msg_generic_up'   => 'Wiarygodność firmy wzrosła. Aktualny poziom: :score / 100 (:level).',
    'credibility.notif.msg_generic_down' => 'Wiarygodność firmy spadła. Aktualny poziom: :score / 100 (:level).',
];
