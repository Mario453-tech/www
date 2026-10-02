<?php
declare(strict_types=1);

/**
 * Company credibility translations (German).
 * Wiarygodnosc firmy — tlumaczenia (niemiecki).
 */

return [
    'credibility.card_title'   => 'Bonität des Unternehmens',
    'credibility.score_suffix' => '/ 100',
    'credibility.status_label' => 'Status',
    'credibility.hint'         => 'Die Bewertung hängt von finanzieller Stabilität, Bankbeziehungen, Regelverstößen und risikoreichen Aktivitäten ab.',

    'credibility.level_critical' => 'kritisch',
    'credibility.level_low'      => 'niedrig',
    'credibility.level_shaky'    => 'wackelig',
    'credibility.level_stable'   => 'stabil',
    'credibility.level_high'     => 'hoch',

    'credibility.level_desc_critical' => 'Das Unternehmen gilt als hochriskant. Manche Partner schränken die Zusammenarbeit ein.',
    'credibility.level_desc_low'      => 'Das Unternehmen hat eine geringe Bonität. Bestimmte Aktionen sind schwieriger oder teurer.',
    'credibility.level_desc_shaky'    => 'Das Unternehmen arbeitet, aber die Lage ist noch nicht gefestigt.',
    'credibility.level_desc_stable'   => 'Das Unternehmen gilt als vertrauenswürdig und berechenbar.',
    'credibility.level_desc_high'     => 'Das Unternehmen hat eine exzellente Position und erhält leichter Zugang zu anspruchsvollen Regionen, Verträgen und Partnern.',

    'credibility.notif.title_up'   => 'Unternehmensbonität gestiegen',
    'credibility.notif.title_down' => 'Unternehmensbonität gesunken',

    'credibility.notif.msg_black_market_detected' => 'Die Bonität ist nach der Aufdeckung riskanter Aktivitäten gesunken. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_bailiff_activated'     => 'Die Bonität ist nach Einschaltung des Gerichtsvollziehers stark gesunken. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_bankruptcy_entered'    => 'Die Bonität ist nach Eintritt der Insolvenz stark gesunken. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_recovery_plan_broken'  => 'Die Bonität ist nach Scheitern des Sanierungsplans gesunken. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_loan_fully_repaid'     => 'Die Bonität ist nach vollständiger Tilgung des Kredits gestiegen. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_loan_repaid_early'     => 'Die Bonität ist nach vorzeitiger Kredittilgung gestiegen. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_clean_operation_period' => 'Die Bonität ist nach einer Phase ohne negative Vorfälle gestiegen. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_admin_manual_adjustment' => 'Die Bonität des Unternehmens wurde manuell angepasst. Aktueller Stand: :score / 100 (:level).',

    'credibility.note_clean_operation_period' => 'Zeitraum ohne Vorfälle: :days Tage.',

    'credibility.notif.msg_generic_up'   => 'Die Bonität des Unternehmens ist gestiegen. Aktueller Stand: :score / 100 (:level).',
    'credibility.notif.msg_generic_down' => 'Die Bonität des Unternehmens ist gesunken. Aktueller Stand: :score / 100 (:level).',
];
