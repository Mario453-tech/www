<?php
declare(strict_types=1);

/**
 * Tlumaczenia modulu szkolen - strona gracza.
 * Training module translations - player side.
 */

return [
    // Strona / zakladka
    'training.page_title'        => 'Szkolenia pracowników',
    'training.tab_label'         => 'Szkolenia',
    'training.heading_available' => 'Dostępne kursy',
    'training.heading_active'    => 'W trakcie',
    'training.heading_history'   => 'Historia egzaminów',
    'training.heading_certificates' => 'Zdobyte certyfikaty',

    // Przyciski
    'training.btn_enroll'        => 'Zapisz na kurs',
    'training.btn_pick_staff'    => 'Wybierz pracownika',

    // Etykiety
    'training.label_duration'    => 'Czas trwania',
    'training.label_cost'        => 'Koszt',
    'training.label_pass_rate'   => 'Szansa zdania',
    'training.label_skill'       => 'Umiejętność',
    'training.label_finishes'    => 'Koniec',
    'training.label_score'       => 'Wynik',
    'training.label_hours'       => ':n h',

    // Umiejetnosci (kody)
    'training.skill.skill_drilling'     => 'Wiercenie',
    'training.skill.skill_maintenance'  => 'Utrzymanie ruchu',
    'training.skill.skill_safety'       => 'BHP',
    'training.skill.skill_analysis'     => 'Analiza',
    'training.skill.skill_negotiation'  => 'Negocjacje',
    'training.skill.skill_ethics'       => 'Etyka',
    'training.skill.skill_stress'       => 'Zarządzanie stresem',
    'training.skill.skill_organization' => 'Organizacja',

    // Statusy
    'training.status.in_progress' => 'W trakcie',
    'training.status.passed'      => 'Zaliczony',
    'training.status.failed'      => 'Oblany',
    'training.status.cancelled'   => 'Anulowany',

    // Wynik egzaminu
    'training.exam_queued'  => 'Egzamin w kolejce',
    'training.exam_result'  => 'Wynik: :score/100 (wymagane: :min)',
    'training.empty_active' => 'Żaden pracownik nie jest obecnie szkolony.',
    'training.empty_history'=> 'Brak historii szkoleń.',
    'training.empty_programs'=> 'Brak dostępnych kursów dla tego działu.',
    'training.empty_certificates'=> 'Brak zdobytych certyfikatów. Ukończ szkolenie z wynikiem pozytywnym, aby zdobyć certyfikat.',

    // Transakcja
    'training.tx_fee' => 'Opłata za szkolenie: :program',

    // Komunikaty sukcesu
    'training.msg.enrolled' => 'Zapisano pracownika na kurs: :program. Egzamin odbędzie się po zakończeniu szkolenia.',

    // Bledy
    'training.err.program_unavailable' => 'Ten kurs jest niedostępny.',
    'training.err.wrong_department'    => 'Ten kurs nie pasuje do działu pracownika.',
    'training.err.not_owner'           => 'To nie jest Twój pracownik.',
    'training.err.skill_maxed'         => 'Ta umiejętność jest już na maksymalnym poziomie (10).',
    'training.err.already_training'    => 'Ten pracownik już uczestniczy w szkoleniu.',
    'training.err.on_cooldown'         => 'Pracownik jest w blokadzie po oblanym egzaminie. Spróbuj później.',
    'training.err.insufficient_funds'  => 'Niewystarczające środki na pokrycie kosztu kursu.',
    'training.err.generic'             => 'Nie udało się zapisać na kurs. Spróbuj ponownie.',
];
