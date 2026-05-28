<?php
declare(strict_types=1);

/**
 * Notifications, news, email templates, geology.
 * Keys: 28
 */

return [

    'player.status.active' => 'Aktywny',
    'player.status.financial_risk' => 'Ryzyko finansowe',
    'player.status.under_bailiff' => 'Pod komornikiem',
    'player.status.bankrupt' => 'Bankrut',
    'player.status.banned' => 'Zbanowany',
    'player.bankruptcy_block_msg' => 'Firma jest w restrukturyzacji. Wydatki inwestycyjne są zablokowane.',
    'geology.err_well_not_found' => 'Odwiert nie istnieje.',
    'geology.err_well_seized' => 'Odwiert jest zajęty przez komornika.',
    'geology.err_drilling_in_progress' => 'Odwiert jest w trakcie wiercenia. Pozostało ~:hoursh.',
    'geology.err_unknown_layer' => 'Nieznana warstwa geologiczna.',
    'geology.err_layer_already_active' => 'Ta warstwa jest już aktywna.',
    'geology.err_no_funds' => 'Brak środków. Koszt wiercenia: :cost zł.',
    'geology.err_server' => 'Błąd serwera. Spróbuj ponownie.',
    'geology.msg_drilling_started' => 'Wiercenie do warstwy ":layer" rozpoczęte.',
    'geology.msg_drilling_paused' => 'Odwiert wstrzymany na :hours h. Koszt: :cost zł.',
    'news.time_just_now' => 'przed chwilą',
    'news.time_minute_ago' => '1 minuta temu',
    'news.time_minutes_ago' => ':count min temu',
    'news.time_hour_ago' => '1 godzina temu',
    'news.time_hours_ago_few' => ':count godziny temu',
    'news.time_hours_ago_many' => ':count godzin temu',
    'news.time_day_ago' => '1 dzień temu',
    'news.time_days_ago' => ':count dni temu',
    'email_template.default_footer' => 'Wiadomość wysłana automatycznie przez system OilCorp. Nie odpowiadaj na ten e-mail.',
    'email_template.btn_fallback_hint' => 'Jeśli przycisk nie działa, wklej ten link w przeglądarce:',
    'email_template.brand_subtitle' => 'Strategiczna gra naftowa',
    'geology.fallback_name' => 'Płytka',
    'geology.fallback_description' => 'Startowa.',

];
