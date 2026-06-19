<?php
// Bankruptcy service - komunikaty dla gracza / player-facing messages
// Bankruptcy service - player-facing messages PL
return [
    // Bledy walidacji / Validation errors
    'bankruptcy.err_not_bankrupt'         => 'Twoja firma nie jest w stanie upadłości.',
    'bankruptcy.err_unknown_option'       => 'Nieznana opcja restrukturyzacyjna.',
    'bankruptcy.err_select_well'          => 'Wybierz odwiert do sprzedaży.',
    'bankruptcy.err_well_seized'          => 'Sprzedaż odwiertu nie powiodła się — zasób jest zablokowany.',
    'bankruptcy.err_storage_already_sold' => 'Magazyn został już sprzedany wcześniej.',
    'bankruptcy.err_no_storage'           => 'Brak magazynu do sprzedaży.',
    'bankruptcy.err_storage_at_min'       => 'Magazyn jest już na minimalnym poziomie.',
    'bankruptcy.err_storage_below_min'    => 'Sprzedaż obniżyłaby magazyn poniżej dozwolonego minimum.',
    'bankruptcy.err_no_assets_takeover'   => 'Brak aktywów, które bank może przejąć.',
    'bankruptcy.err_no_active_loan'       => 'Brak aktywnego kredytu do umorzenia.',
    'bankruptcy.err_loan_low_score'       => 'Scoring kredytowy zbyt niski, aby uzyskać kredyt awaryjny.',
    'bankruptcy.err_loan_already_active'  => 'Kredyt awaryjny jest już aktywny.',
    'bankruptcy.err_investor_already_used' => 'Inwestor ratunkowy został już wykorzystany w tej restrukturyzacji.',
    'bankruptcy.err_investor_no_debt'     => 'Brak długu — inwestor ratunkowy nie jest potrzebny.',
    'bankruptcy.err_new_start_failed'     => 'Nowy start nie powiódł się. Skontaktuj się z obsługą.',

    // Wiadomosci sukcesu / Success messages
    'bankruptcy.msg_sell_well'       => 'Odwiert sprzedany. Uzyskano :payout PLN.',
    'bankruptcy.msg_sell_storage'    => 'Magazyn sprzedany. Uzyskano :payout PLN.',
    'bankruptcy.msg_bank_takeover'   => 'Bank przejął odwiert. Dług zmniejszony o :amount PLN.',
    'bankruptcy.msg_emergency_loan'  => 'Kredyt awaryjny :amount PLN przyznany.',
    'bankruptcy.msg_cost_cuts'       => 'Cięcia kosztów: wstrzymano :wells odwiertów, zwolniono :tech pracowników technicznych, zawieszono :board dyrektorów. Ulga: :relief PLN.',
    'bankruptcy.msg_rescue_investor' => 'Inwestor ratunkowy zaakceptowany. Spłata długu: :debt PLN, zastrzyk gotówki: :cash PLN, udziały: :equity%.',
    'bankruptcy.msg_new_start_ok'    => 'Nowy start zainicjowany. Firma zostanie zresetowana.',

    // Wpisy logu zdarzen / Event log entries
    'bankruptcy.log_sell_well'       => 'Sprzedaż odwiertu #:id za :payout PLN',
    'bankruptcy.log_sell_storage'    => 'Sprzedaż pojemności magazynu w ramach restrukturyzacji',
    'bankruptcy.log_bank_takeover'   => 'Przejęcie odwiertu przez bank jako spłata długu',
    'bankruptcy.log_emergency_loan'  => 'Udzielono kredytu awaryjnego',
    'bankruptcy.log_cost_cuts'       => 'Wprowadzono cięcia kosztów operacyjnych',
    'bankruptcy.log_rescue_investor' => 'Przyjęto inwestora ratunkowego',
    'bankruptcy.log_recovered'       => 'Firma wyszła z restrukturyzacji',
    'bankruptcy.log_new_start'       => 'Zainicjowano nowy start — reset firmy',

    // Powiadomienia / Notifications
    'bankruptcy.notif_prefix'           => '[Restrukturyzacja] ',
    'bankruptcy.notif_sell_well'        => 'Sprzedano odwiert za :payout PLN.',
    'bankruptcy.notif_sell_storage'     => 'Sprzedano magazyn za :payout PLN.',
    'bankruptcy.notif_bank_takeover'    => 'Bank przejął odwiert. Dług zmniejszony o :amount PLN.',
    'bankruptcy.notif_emergency_loan'   => 'Kredyt awaryjny :amount PLN (oprocentowanie :rate% rocznie) przyznany.',
    'bankruptcy.notif_recovered'        => 'Twoja firma wyszła z restrukturyzacji. Gratulacje!',
    'bankruptcy.notif_new_start'        => 'Nowy start zatwierdzony. Firma zostanie zresetowana.',

    // Zdarzenia kryzysowe — opisy / Crisis event descriptions
    'bankruptcy.evt_overdue_default'        => 'Spłata kredytu jest przeterminowana. Grozi przejęcie aktywów.',
    'bankruptcy.evt_deadline_well_seized'   => 'Termin upłynął. Odwiert #:id został przejęty przez bank.',
    'bankruptcy.evt_deadline_no_assets'     => 'Termin upłynął, ale brak aktywów do przejęcia.',
    'bankruptcy.evt_competitor_buyout'      => 'Konkurent wykupił udział po preferencyjnej cenie. Kara: :amount PLN.',
    'bankruptcy.evt_investor_expired'       => 'Oferta inwestora wygasła. Reputacja kredytowa pogorszona.',

    // Wygenerowane zdarzenia / Spawned event messages
    'bankruptcy.spawn_debt_deadline'    => 'Zbliża się termin spłaty długu. Masz 24 godziny na działanie.',
    'bankruptcy.spawn_competitor_buyout' => 'Panie Dyrektorze, konkurent proponuje wykup udziałów firmy.',
    'bankruptcy.spawn_investor_offer'   => 'Panie Dyrektorze, inwestor proponuje przejęcie 40% firmy za natychmiastową gotówkę.',

    // Opis rozwiazania w historii / Resolution description in history
    'bankruptcy.resolution_new_start' => 'Firma zresetowana — nowy start',
];
