<?php
declare(strict_types=1);

/**
 * Training module translations - player side (German).
 * Tlumaczenia modulu szkolen - strona gracza (niemiecki).
 */

return [
    // Strona / zakladka
    'training.page_title'        => 'Mitarbeiterschulungen',
    'training.tab_label'         => 'Schulungen',
    'training.heading_available' => 'Verfügbare Kurse',
    'training.heading_active'    => 'Laufend',
    'training.heading_history'   => 'Prüfungshistorie',
    'training.heading_certificates' => 'Erhaltende Zertifikate',

    // Przyciski
    'training.btn_enroll'        => 'Zum Kurs anmelden',
    'training.btn_pick_staff'    => 'Mitarbeiter auswählen',

    // Etykiety
    'training.label_duration'    => 'Dauer',
    'training.label_cost'        => 'Kosten',
    'training.label_pass_rate'   => 'Bestehensquote',
    'training.label_skill'       => 'Fähigkeit',
    'training.label_finishes'    => 'Endet',
    'training.label_score'       => 'Ergebnis',
    'training.label_hours'       => ':n h',

    // Umiejetnosci (kody)
    'training.skill.skill_drilling'     => 'Bohren',
    'training.skill.skill_maintenance'  => 'Instandhaltung',
    'training.skill.skill_safety'       => 'Arbeitsschutz',
    'training.skill.skill_analysis'     => 'Analyse',
    'training.skill.skill_negotiation'  => 'Verhandlungsführung',
    'training.skill.skill_ethics'       => 'Ethik',
    'training.skill.skill_stress'       => 'Stressbewältigung',
    'training.skill.skill_organization' => 'Organisation',

    // Statusy
    'training.status.in_progress' => 'In Bearbeitung',
    'training.status.passed'      => 'Bestanden',
    'training.status.failed'      => 'Nicht bestanden',
    'training.status.cancelled'   => 'Abgebrochen',

    // Wynik egzaminu
    'training.exam_queued'  => 'Prüfung in der Warteschlange',
    'training.exam_result'  => 'Ergebnis: :score/100 (erforderlich: :min)',
    'training.empty_active' => 'Derzeit befindet sich kein Mitarbeiter in einer Schulung.',
    'training.empty_history'=> 'Keine Schulungshistorie vorhanden.',
    'training.empty_programs'=> 'Keine Kurse für diese Abteilung verfügbar.',
    'training.empty_certificates'=> 'Keine Zertifikate erworben. Schließe eine Schulung erfolgreich ab, um ein Zertifikat zu erhalten.',

    // Transakcja
    'training.tx_fee' => 'Schulungsgebühr: :program',

    // Komunikaty sukcesu
    'training.msg.enrolled' => 'Mitarbeiter zum Kurs angemeldet: :program. Die Prüfung findet nach Abschluss der Schulung statt.',

    // Bledy
    'training.err.program_unavailable' => 'Dieser Kurs ist nicht verfügbar.',
    'training.err.wrong_department'    => 'Dieser Kurs passt nicht zur Abteilung des Mitarbeiters.',
    'training.err.not_owner'           => 'Dieser Mitarbeiter gehört nicht zu deinem Unternehmen.',
    'training.err.skill_maxed'         => 'Diese Fähigkeit hat bereits die Maximalstufe (10) erreicht.',
    'training.err.already_training'    => 'Dieser Mitarbeiter nimmt bereits an einer Schulung teil.',
    'training.err.on_cooldown'         => 'Der Mitarbeiter ist nach einer nicht bestandenen Prüfung gesperrt. Bitte später versuchen.',
    'training.err.insufficient_funds'  => 'Nicht genügend Guthaben zur Deckung der Kurskosten.',
    'training.err.generic'             => 'Anmeldung zum Kurs fehlgeschlagen. Bitte versuche es erneut.',
];
