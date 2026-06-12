<?php
declare(strict_types=1);

/**
 * Panel admina — moduł ochrony.
 * Admin panel — protection module.
 */

return [
    'admin.protection.title'    => 'Ochrona',
    'admin.protection.subtitle' => 'Opcje ochrony, ich efekty, aktywne wykupy i historia. Opcje i efekty są definiowane tutaj — bez zmian w kodzie.',

    // Zakładki / Tabs
    'admin.protection.tab_options' => 'Opcje ochrony',
    'admin.protection.tab_effects' => 'Efekty',
    'admin.protection.tab_active'  => 'Aktywne ochrony',
    'admin.protection.tab_history' => 'Historia',

    // Lista opcji / Options list
    'admin.protection.options_list_title' => 'Zdefiniowane opcje ochrony',
    'admin.protection.options_empty'      => 'Brak opcji ochrony.',
    'admin.protection.col_code'           => 'Kod',
    'admin.protection.col_name'           => 'Nazwa',
    'admin.protection.col_target'         => 'Cel / kontekst',
    'admin.protection.col_cost'           => 'Koszt',
    'admin.protection.col_duration'       => 'Czas',
    'admin.protection.col_requirements'   => 'Wymagania',
    'admin.protection.col_status'         => 'Status',
    'admin.protection.cost_type_fixed'             => 'kwota stała',
    'admin.protection.cost_type_percent_reference' => '% wartości odniesienia',
    'admin.protection.cost_type_per_hour'          => 'za godzinę',
    'admin.protection.cost_type_per_bbl'           => 'za baryłkę',
    'admin.protection.req_credibility' => 'wiarygodność :min+',
    'admin.protection.req_legal'       => 'dział prawny :min+',
    'admin.protection.status_on'  => 'włączona',
    'admin.protection.status_off' => 'wyłączona',
    'admin.protection.btn_edit'   => 'Edytuj',

    // Formularz opcji / Option form
    'admin.protection.option_form_add'  => 'Dodaj nową opcję ochrony',
    'admin.protection.option_form_edit' => 'Edycja opcji: :name',
    'admin.protection.field_code'            => 'Kod techniczny (a-z, 0-9, _)',
    'admin.protection.field_name'            => 'Nazwa dla gracza',
    'admin.protection.field_sort'            => 'Kolejność',
    'admin.protection.field_description'     => 'Opis dla gracza',
    'admin.protection.field_target_type'     => 'Typ celu (np. road_transport)',
    'admin.protection.field_context'         => 'Kontekst (np. road_transport_guard)',
    'admin.protection.field_cost_type'       => 'Sposób liczenia kosztu',
    'admin.protection.field_cost_value'      => 'Wartość kosztu',
    'admin.protection.field_cost_currency'   => 'Źródło płatności',
    'admin.protection.field_duration'        => 'Czas działania (minuty)',
    'admin.protection.field_min_credibility' => 'Min. wiarygodność firmy (0 = brak)',
    'admin.protection.field_min_legal'       => 'Min. poziom działu prawnego (0 = brak)',
    'admin.protection.field_is_active'       => 'Opcja włączona (widoczna dla graczy)',
    'admin.protection.currency_cash' => 'gotówka',
    'admin.protection.currency_bank' => 'konto bankowe (przyszłość)',
    'admin.protection.currency_both' => 'gotówka lub konto (przyszłość)',
    'admin.protection.cash_only_note' => 'P1: ochrona jest zawsze opłacana gotówką przez centralne API finansowe.',
    'admin.protection.btn_save_option' => 'Zapisz opcję',
    'admin.protection.btn_cancel_edit' => 'Anuluj edycję',

    // Efekty / Effects
    'admin.protection.effects_empty'      => 'Ta opcja nie ma jeszcze efektów.',
    'admin.protection.field_effect_key'   => 'Klucz efektu',
    'admin.protection.field_effect_type'  => 'Typ',
    'admin.protection.field_effect_value' => 'Wartość',
    'admin.protection.btn_save_effect'    => 'Zapisz efekt',
    'admin.protection.btn_delete'         => 'Usuń',
    'admin.protection.confirm_delete_effect' => 'Usunąć ten efekt ochrony?',

    // Aktywne / Active
    'admin.protection.active_title'  => 'Wykupione ochrony (ostatnie 100)',
    'admin.protection.active_empty'  => 'Brak wykupionych ochron.',
    'admin.protection.col_player'    => 'Gracz',
    'admin.protection.col_option'    => 'Ochrona',
    'admin.protection.col_period'    => 'Start / koniec',
    'admin.protection.ap_status_active'    => 'aktywna',
    'admin.protection.ap_status_expired'   => 'wygasła',
    'admin.protection.ap_status_cancelled' => 'anulowana',
    'admin.protection.ap_status_failed'    => 'nieudana',
    'admin.protection.btn_cancel_active'     => 'Anuluj',
    'admin.protection.confirm_cancel_active' => 'Anulować tę aktywną ochronę? Gracz nie dostanie zwrotu środków.',

    // Historia / History
    'admin.protection.history_title' => 'Historia ochrony (ostatnie 100)',
    'admin.protection.history_empty' => 'Brak wpisów historii.',
    'admin.protection.col_date'      => 'Data',
    'admin.protection.col_event'     => 'Zdarzenie',
    'admin.protection.col_amount'    => 'Kwota / wolumen',
    'admin.protection.col_message'   => 'Komunikat',

    // Komunikaty / Messages
    'admin.protection.msg_option_saved'   => 'Opcja ochrony zapisana.',
    'admin.protection.err_option_save'    => 'Nie udało się zapisać opcji ochrony.',
    'admin.protection.err_option_required'=> 'Kod i nazwa opcji są wymagane.',
    'admin.protection.msg_effect_saved'   => 'Efekt zapisany.',
    'admin.protection.err_effect_save'    => 'Nie udało się zapisać efektu.',
    'admin.protection.err_effect_required'=> 'Opcja i klucz efektu są wymagane.',
    'admin.protection.msg_effect_deleted' => 'Efekt usunięty.',
    'admin.protection.err_effect_delete'  => 'Nie udało się usunąć efektu.',
    'admin.protection.msg_cancelled'      => 'Ochrona anulowana.',
];
