<?php
// Recovery / panel ratunkowy + komunikaty BankruptcyService - tlumaczenia PL
// Recovery panel + BankruptcyService player-facing messages - PL translations
return [
    'recovery.page_title'           => 'Plan naprawczy',
    'recovery.heading'              => 'Tryb naprawczy firmy',
    'recovery.intro'                => 'Firma jest w stanie bankructwa. Wybierz jedną z opcji naprawczych, aby ograniczyć straty i spróbować odzyskać płynność.',
    'recovery.cash'                 => 'Gotówka',
    'recovery.debt_active'          => 'Aktywne zadłużenie',
    'recovery.debt_late'            => 'Zaległe zadłużenie',
    'recovery.status'               => 'Status',
    'recovery.status_restructuring' => 'Restrukturyzacja',
    'recovery.status_liquidation'   => 'Likwidacja',
    'recovery.status_recovered'     => 'Odzyskana płynność',
    'recovery.critical_events'      => 'Zdarzenia krytyczne',
    'recovery.critical_open'        => 'otwarte',
    'recovery.critical_none'        => 'brak',
    'recovery.events_heading'       => 'Zdarzenia kryzysowe',
    'recovery.event_type'           => 'Typ',
    'recovery.event_deadline'       => 'Termin',
    'recovery.event_open'           => 'Otwarte',
    'recovery.event_closed'         => 'Zamknięte',
    'recovery.event_resolution'     => 'Rozwiązanie',
    'recovery.options_heading'      => 'Opcje naprawcze',
    'recovery.success_default'      => 'Operacja wykonana poprawnie.',

    // Opcja 1: Sprzedaz aktywow / Option 1: Asset sale
    'recovery.opt1_title'         => 'Sprzedaż aktywów',
    'recovery.opt1_desc'          => 'Sprzedaj odwiert albo część magazynu, aby szybko odzyskać gotówkę.',
    'recovery.opt1_asset_label'   => 'Aktywo',
    'recovery.opt1_asset_well'    => 'Odwiert',
    'recovery.opt1_asset_storage' => 'Magazyn',
    'recovery.opt1_storage_sold'  => 'Część magazynu została już sprzedana w tym trybie naprawczym.',
    'recovery.opt1_well_label'    => 'Odwiert do sprzedaży',
    'recovery.opt1_well_default'  => 'Wybierz odwiert',
    'recovery.opt1_well_level'    => 'poziom',
    'recovery.opt1_btn'           => 'Sprzedaj aktywo',

    // Opcja 2: Przejecie przez bank / Option 2: Bank takeover
    'recovery.opt2_title' => 'Przejęcie przez bank',
    'recovery.opt2_desc'  => 'Oddaj bankowi jeden z aktywów w zamian za obniżenie zadłużenia.',
    'recovery.opt2_btn'   => 'Przekaż aktywo bankowi',

    // Opcja 3: Pozyczka awaryjna / Option 3: Emergency loan
    'recovery.opt3_title'       => 'Pożyczka awaryjna',
    'recovery.opt3_desc'        => 'Zaciągnij wysoko oprocentowaną pożyczkę ratunkową.',
    'recovery.opt3_available'   => 'Dostępne oprocentowanie',
    'recovery.opt3_unavailable' => 'Pożyczka awaryjna jest teraz niedostępna.',
    'recovery.opt3_btn'         => 'Weź pożyczkę awaryjną',

    // Opcja 4: Ciecia kosztow / Option 4: Cost cuts
    'recovery.opt4_title' => 'Cięcia kosztów',
    'recovery.opt4_desc'  => 'Wstrzymaj część operacji i ogranicz koszty, kosztem produkcji oraz personelu.',
    'recovery.opt4_btn'   => 'Wprowadź cięcia',

    // Opcja 5: Inwestor ratunkowy / Option 5: Rescue investor
    'recovery.opt5_title'          => 'Inwestor ratunkowy',
    'recovery.opt5_desc'           => 'Pozyskaj inwestora, który spłaci część długu i zasili konto firmy.',
    'recovery.opt5_used'           => 'Inwestor ratunkowy został już wykorzystany.',
    'recovery.opt5_used_btn'       => 'Inwestor wykorzystany',
    'recovery.opt5_debt'           => 'Dług do restrukturyzacji',
    'recovery.opt5_injection'      => 'Szacowany zastrzyk gotówki',
    'recovery.opt5_injection_note' => 'Kwota zależy od aktualnego zadłużenia i stanu firmy.',
    'recovery.opt5_btn'            => 'Przyjmij inwestora',

    // Opcja 6: Nowy start / Option 6: New start (full reset)
    'recovery.opt6_title'   => 'Nowy start',
    'recovery.opt6_desc'    => 'Zamknij dotychczasową firmę i rozpocznij grę od nowa z minimalnym kapitałem.',
    'recovery.opt6_warning' => 'Ta decyzja jest ostateczna i usuwa obecne aktywa firmy.',
    'recovery.opt6_btn'     => 'Rozpocznij od nowa',

    'recovery.back_btn' => 'Wróć do gry',

    // Etykiety typow zdarzen / Event type labels
    'recovery.etype_sell_asset'         => 'Sprzedaż aktywów',
    'recovery.etype_sell_asset_storage' => 'Sprzedaż magazynu',
    'recovery.etype_bank_takeover'      => 'Przejęcie przez bank',
    'recovery.etype_emergency_loan'     => 'Pożyczka awaryjna',
    'recovery.etype_cost_cuts'          => 'Cięcia kosztów',
    'recovery.etype_rescue_investor'    => 'Inwestor ratunkowy',
    'recovery.etype_new_start'          => 'Nowy start',
    'recovery.etype_debt_deadline_24h'  => 'Termin spłaty długu',
    'recovery.etype_competitor_buyout'  => 'Przejęcie przez konkurencję',
    'recovery.etype_investor_offer_40'  => 'Oferta inwestora (40%)',

    // Potwierdzenia akcji / Action confirmations
    'recovery.confirm_sell_asset'      => 'Czy na pewno chcesz sprzedać ten zasób?',
    'recovery.confirm_bank_takeover'   => 'Czy na pewno chcesz przekazać zasób bankowi? Operacja jest nieodwracalna.',
    'recovery.confirm_cost_cuts'       => 'Czy na pewno chcesz wprowadzić cięcia kosztów? Wstrzyma to odwierty i zwolni pracowników.',
    'recovery.confirm_rescue_investor' => 'Inwestor przejmie udział w firmie. Operacja jest nieodwracalna.',
    'recovery.confirm_new_start'       => 'UWAGA: Nowy start trwale usuwa wszystkie aktywa firmy. Tej operacji nie można cofnąć.',

    // --- BankruptcyService: bledy walidacji / validation errors ---
    'bankruptcy.err_not_bankrupt'          => 'Firma nie jest w trybie bankructwa.',
    'bankruptcy.err_unknown_option'        => 'Nieznana opcja naprawcza.',
    'bankruptcy.err_select_well'           => 'Wybierz odwiert do sprzedaży.',
    'bankruptcy.err_well_seized'           => 'Ten odwiert został już zajęty albo nie jest dostępny.',
    'bankruptcy.err_storage_already_sold'  => 'Magazyn został już sprzedany w tym trybie naprawczym.',
    'bankruptcy.err_no_storage'            => 'Brak magazynu do sprzedaży.',
    'bankruptcy.err_storage_at_min'        => 'Magazyn jest już na minimalnym poziomie.',
    'bankruptcy.err_storage_below_min'     => 'Nie można zejść poniżej minimalnej pojemności magazynu.',
    'bankruptcy.err_no_assets_takeover'    => 'Brak aktywów, które bank może przejąć.',
    'bankruptcy.err_no_active_loan'        => 'Brak aktywnego kredytu do rozliczenia.',
    'bankruptcy.err_loan_low_score'        => 'Wiarygodność firmy jest zbyt niska na pożyczkę awaryjną.',
    'bankruptcy.err_loan_already_active'   => 'Pożyczka awaryjna została już uruchomiona.',
    'bankruptcy.err_investor_already_used' => 'Inwestor ratunkowy został już wykorzystany.',
    'bankruptcy.err_investor_no_debt'      => 'Brak długu, który inwestor mógłby restrukturyzować.',
    'bankruptcy.err_new_start_failed'      => 'Nie udało się uruchomić nowego startu.',

    // Komunikaty sukcesu / Success messages
    'bankruptcy.msg_sell_well'       => 'Sprzedano odwiert. Firma otrzymała :payout.',
    'bankruptcy.msg_sell_storage'    => 'Sprzedano część magazynu. Firma otrzymała :payout.',
    'bankruptcy.msg_bank_takeover'   => 'Bank przejął aktywo i obniżył zadłużenie o :amount.',
    'bankruptcy.msg_emergency_loan'  => 'Uruchomiono pożyczkę awaryjną na :amount.',
    'bankruptcy.msg_cost_cuts'       => 'Wprowadzono cięcia kosztów. Wstrzymane odwierty: :wells, zwolnieni techniczni: :tech, zawieszeni dyrektorzy: :board. Ulga: :relief.',
    'bankruptcy.msg_rescue_investor' => 'Inwestor spłacił :debt długu i przekazał firmie :cash gotówki za :equity% udziałów.',
    'bankruptcy.msg_new_start_ok'    => 'Nowy start został uruchomiony.',

    // Wpisy logu / Log entries
    'bankruptcy.log_sell_well'       => 'Sprzedaż odwiertu #:id za :payout',
    'bankruptcy.log_sell_storage'    => 'Sprzedaż części magazynu',
    'bankruptcy.log_bank_takeover'   => 'Przejęcie aktywa przez bank',
    'bankruptcy.log_emergency_loan'  => 'Pożyczka awaryjna',
    'bankruptcy.log_cost_cuts'       => 'Cięcia kosztów',
    'bankruptcy.log_rescue_investor' => 'Inwestor ratunkowy',
    'bankruptcy.log_new_start'       => 'Nowy start po bankructwie',
    'bankruptcy.log_recovered'       => 'Firma odzyskała płynność',

    // Powiadomienia / Notifications
    'bankruptcy.notif_prefix'         => 'Plan naprawczy: ',
    'bankruptcy.notif_sell_well'      => 'Sprzedano odwiert. Wpływ: :payout.',
    'bankruptcy.notif_sell_storage'   => 'Sprzedano część magazynu. Wpływ: :payout.',
    'bankruptcy.notif_bank_takeover'  => 'Bank obniżył zadłużenie o :amount.',
    'bankruptcy.notif_emergency_loan' => 'Uruchomiono pożyczkę awaryjną: :amount przy :rate%.',
    'bankruptcy.notif_new_start'      => 'Rozpoczęto nowy start firmy.',
    'bankruptcy.notif_recovered'      => 'Firma wyszła z bankructwa.',

    // Zdarzenia spawned / Spawned event messages
    'bankruptcy.spawn_debt_deadline'     => 'Bank wyznaczył 24 godziny na pokazanie planu spłaty.',
    'bankruptcy.spawn_competitor_buyout' => 'Konkurencja próbuje przejąć osłabione aktywa firmy.',
    'bankruptcy.spawn_investor_offer'    => 'Panie Dyrektorze, inwestor proponuje przejęcie 40% firmy za natychmiastową gotówkę.',

    // Opis zdarzen / Event resolution descriptions
    'bankruptcy.evt_overdue_default'      => 'Termin zdarzenia kryzysowego minął bez reakcji.',
    'bankruptcy.evt_deadline_well_seized' => 'Bank zajął odwiert #:id za przekroczenie terminu.',
    'bankruptcy.evt_deadline_no_assets'   => 'Bank nie znalazł aktywów do zajęcia.',
    'bankruptcy.evt_competitor_buyout'    => 'Konkurencja wykorzystała kryzys. Strata: :amount.',
    'bankruptcy.evt_investor_expired'     => 'Oferta inwestora wygasła. Reputacja kredytowa pogorszona.',
    'bankruptcy.resolution_new_start'     => 'Zamknięto zdarzenie przez nowy start.',
];
