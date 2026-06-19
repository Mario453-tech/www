<?php
// Recovery / panel ratunkowy - tlumaczenia PL
// Recovery / bankruptcy recovery panel - PL translations
return [
    'recovery.page_title'           => 'Panel ratunkowy',
    'recovery.heading'              => 'Firma w restrukturyzacji',
    'recovery.intro'                => 'Twoja firma jest niewypłacalna. Wybierz jedną z opcji ratunkowych poniżej, aby wyjść z kryzysu.',
    'recovery.cash'                 => 'Gotówka',
    'recovery.debt_active'          => 'Aktywny dług',
    'recovery.debt_late'            => 'Przeterminowany dług',
    'recovery.status'               => 'Status',
    'recovery.status_restructuring' => 'Restrukturyzacja',
    'recovery.status_liquidation'   => 'Likwidacja',
    'recovery.status_recovered'     => 'Odzyskana wypłacalność',
    'recovery.critical_events'      => 'Krytyczne zdarzenia',
    'recovery.critical_open'        => 'otwarte',
    'recovery.critical_none'        => 'Brak krytycznych zdarzeń',

    'recovery.events_heading'   => 'Zdarzenia kryzysowe',
    'recovery.event_type'       => 'Typ',
    'recovery.event_deadline'   => 'Termin',
    'recovery.event_open'       => 'Otwarte',
    'recovery.event_closed'     => 'Zamknięte',
    'recovery.event_resolution' => 'Rozwiązanie',

    'recovery.options_heading'    => 'Opcje ratunkowe',

    // Opcja 1: Sprzedaz aktywow / Option 1: Asset sale
    'recovery.opt1_title'         => 'Sprzedaż aktywów',
    'recovery.opt1_desc'          => 'Sprzedaj odwiert lub magazyn po obniżonej cenie, aby natychmiast pozyskać gotówkę i spłacić zobowiązania.',
    'recovery.opt1_asset_label'   => 'Co sprzedać',
    'recovery.opt1_asset_well'    => 'Odwiert',
    'recovery.opt1_asset_storage' => 'Magazyn',
    'recovery.opt1_storage_sold'  => 'Magazyn już sprzedany.',
    'recovery.opt1_well_label'    => 'Wybierz odwiert',
    'recovery.opt1_well_default'  => '— wybierz odwiert —',
    'recovery.opt1_btn'           => 'Sprzedaj wybrany zasób',

    // Opcja 2: Przejecie przez bank / Option 2: Bank takeover
    'recovery.opt2_title' => 'Przejęcie przez bank',
    'recovery.opt2_desc'  => 'Bank przejmuje odwiert jako zabezpieczenie i umarza część długu. Tracisz odwiert, ale redukujesz zobowiązania.',
    'recovery.opt2_btn'   => 'Oddaj odwiert bankowi',

    // Opcja 3: Kredyt awaryjny / Option 3: Emergency loan
    'recovery.opt3_title'       => 'Kredyt awaryjny',
    'recovery.opt3_desc'        => 'Pożyczka ratunkowa z podwyższonym oprocentowaniem — dostępna tylko raz w trakcie restrukturyzacji.',
    'recovery.opt3_available'   => 'Dostępne oprocentowanie roczne',
    'recovery.opt3_unavailable' => 'Opcja niedostępna — zbyt niski scoring kredytowy lub kredyt już aktywny.',
    'recovery.opt3_btn'         => 'Wnioskuj o kredyt awaryjny',

    // Opcja 4: Ciecie kosztow / Option 4: Cost cuts
    'recovery.opt4_title' => 'Cięcia kosztów',
    'recovery.opt4_desc'  => 'Wstrzymaj odwierty, zwolnij część pracowników i zawieś wynagrodzenia zarządu, aby zmniejszyć miesięczne wydatki.',
    'recovery.opt4_btn'   => 'Wprowadź cięcia kosztów',

    // Opcja 5: Inwestor ratunkowy / Option 5: Rescue investor
    'recovery.opt5_title'          => 'Inwestor ratunkowy',
    'recovery.opt5_desc'           => 'Zewnętrzny inwestor spłaca część długu i zasila firmę gotówką w zamian za udziały. Dostępny tylko raz.',
    'recovery.opt5_used'           => 'Inwestor ratunkowy już skorzystano w tej restrukturyzacji.',
    'recovery.opt5_used_btn'       => 'Inwestor już użyty',
    'recovery.opt5_debt'           => 'Dług do spłaty przez inwestora',
    'recovery.opt5_injection'      => 'Szacowana gotówka dla firmy',
    'recovery.opt5_injection_note' => 'Kwota zależy od aktualnej wyceny firmy i negocjacji.',
    'recovery.opt5_btn'            => 'Przyjmij inwestora ratunkowego',

    // Opcja 6: Nowy start / Option 6: New start (full reset)
    'recovery.opt6_title'   => 'Nowy start',
    'recovery.opt6_desc'    => 'Firma zostaje całkowicie zamknięta — długi, aktywa i historia zostają wyzerowane. Zaczynasz od nowa z minimalnym kapitałem.',
    'recovery.opt6_warning' => 'Tej operacji nie można cofnąć. Wszystkie odwierty, pracownicy i historia firmy zostaną skasowane.',
    'recovery.opt6_btn'     => 'Ogłoś upadłość i zacznij od nowa',

    'recovery.back_btn' => 'Wróć do pulpitu',
];
