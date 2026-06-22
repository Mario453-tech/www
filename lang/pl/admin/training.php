<?php
declare(strict_types=1);

/**
 * Admin translations - training module (PL).
 * Tlumaczenia admina - modul szkolen (PL).
 */

return [
    'admin.training.page_title'            => 'System szkoleń',

    // Zakladki
    'admin.training.tab_programs'          => 'Programy szkoleń',
    'admin.training.tab_monitor'           => 'Monitoring',

    // Programy — naglowki i przyciski
    'admin.training.programs.heading'      => 'Programy szkoleniowe',
    'admin.training.programs.btn_add'      => 'Dodaj program',
    'admin.training.programs.btn_save'     => 'Zapisz',
    'admin.training.programs.btn_cancel'   => 'Anuluj',
    'admin.training.programs.btn_edit'     => 'Edytuj',
    'admin.training.programs.btn_enable'   => 'Włącz',
    'admin.training.programs.btn_disable'  => 'Wyłącz',
    'admin.training.programs.confirm_disable' => 'Na pewno wyłączyć ten program?',

    // Programy — kolumny tabeli
    'admin.training.programs.col_code'       => 'Kod',
    'admin.training.programs.col_name'       => 'Nazwa',
    'admin.training.programs.col_dept'       => 'Dział',
    'admin.training.programs.col_skill'      => 'Skill',
    'admin.training.programs.col_duration'   => 'Czas (h)',
    'admin.training.programs.col_cost'       => 'Koszt',
    'admin.training.programs.col_pass_rate'  => 'Baza %',
    'admin.training.programs.col_enabled'    => 'Aktywny',
    'admin.training.programs.col_actions'    => 'Akcje',

    // Programy — pola formularza
    'admin.training.programs.label_code'       => 'Kod programu (unikalny)',
    'admin.training.programs.label_dept'       => 'Dział',
    'admin.training.programs.label_skill'      => 'Docelowy skill',
    'admin.training.programs.label_name_pl'    => 'Nazwa PL',
    'admin.training.programs.label_name_en'    => 'Nazwa EN',
    'admin.training.programs.label_duration'   => 'Czas trwania (godziny)',
    'admin.training.programs.label_cost'       => 'Koszt (z konta bankowego)',
    'admin.training.programs.label_pass_rate'  => 'Bazowe szanse zdania egzaminu (%)',
    'admin.training.programs.label_enabled'    => 'Program aktywny',
    'admin.training.programs.hint_pass_rate'   => 'Modyfikatory (ambicja pracownika, retry bonus) doliczane są automatycznie.',

    // Dzialy
    'admin.training.dept.technical' => 'Dział techniczny',
    'admin.training.dept.board'     => 'Zarząd',

    // Skille
    'admin.training.skill.skill_drilling'    => 'Wiercenie',
    'admin.training.skill.skill_maintenance' => 'Utrzymanie ruchu',
    'admin.training.skill.skill_safety'      => 'BHP',
    'admin.training.skill.skill_analysis'    => 'Analiza',
    'admin.training.skill.skill_negotiation' => 'Negocjacje',
    'admin.training.skill.skill_ethics'      => 'Etyka',
    'admin.training.skill.skill_stress'      => 'Zarządzanie stresem',
    'admin.training.skill.skill_organization'=> 'Organizacja',

    // Komunikaty
    'admin.training.msg.program_created'  => 'Program szkoleniowy został dodany.',
    'admin.training.msg.program_updated'  => 'Program szkoleniowy został zaktualizowany.',
    'admin.training.msg.program_enabled'  => 'Program włączony.',
    'admin.training.msg.program_disabled' => 'Program wyłączony.',
    'admin.training.err.not_found'        => 'Nie znaleziono programu.',
    'admin.training.err.code_exists'      => 'Program o tym kodzie już istnieje.',
    'admin.training.err.invalid_rate'     => 'Bazowa szansa zdania musi być liczbą 1–100.',
    'admin.training.err.invalid_cost'     => 'Koszt musi być liczbą >= 0.',
    'admin.training.err.invalid_hours'    => 'Czas trwania musi być liczbą >= 1.',

    // Monitor
    'admin.training.monitor.heading'       => 'Aktywne i ostatnie szkolenia',
    'admin.training.monitor.col_player'    => 'Gracz',
    'admin.training.monitor.col_staff'     => 'Pracownik',
    'admin.training.monitor.col_dept'      => 'Dział',
    'admin.training.monitor.col_program'   => 'Program',
    'admin.training.monitor.col_status'    => 'Status',
    'admin.training.monitor.col_started'   => 'Rozpoczęto',
    'admin.training.monitor.col_finishes'  => 'Koniec',
    'admin.training.monitor.col_result'    => 'Wynik',
    'admin.training.monitor.filter_status' => 'Status',
    'admin.training.monitor.filter_dept'   => 'Dział',
    'admin.training.monitor.empty'         => 'Brak szkoleń do wyświetlenia.',

    // Statusy szkolen
    'admin.training.status.in_progress' => 'W trakcie',
    'admin.training.status.passed'      => 'Zaliczony',
    'admin.training.status.failed'      => 'Oblany',
    'admin.training.status.cancelled'   => 'Anulowany',
];
