<?php
declare(strict_types=1);

/**
 * Company credibility translations.
 * Polski - tlumaczenia wiarygodnosci firmy.
 */

return [
    'credibility.card_title'   => 'Company credibility',
    'credibility.score_suffix' => '/ 100',
    'credibility.status_label' => 'Status',
    'credibility.hint'         => 'The score depends on financial stability, cooperation with the bank, violations, and risky actions.',

    'credibility.level_critical' => 'critical',
    'credibility.level_low'      => 'low',
    'credibility.level_shaky'    => 'shaky',
    'credibility.level_stable'   => 'stable',
    'credibility.level_high'     => 'high',

    'credibility.level_desc_critical' => 'The company is seen as highly risky. Some institutions may limit cooperation.',
    'credibility.level_desc_low'      => 'The company has weak credibility. Some actions may be harder or more expensive.',
    'credibility.level_desc_shaky'    => 'The company is operating, but its position is not stable yet.',
    'credibility.level_desc_stable'   => 'The company is seen as credible and predictable.',
    'credibility.level_desc_high'     => 'The company has a very strong standing and may gain easier access to harder regions, contracts, and partners in the future.',

    'credibility.notif.title_up'   => 'Company credibility increased',
    'credibility.notif.title_down' => 'Company credibility decreased',

    'credibility.notif.msg_black_market_detected' => 'Company credibility dropped after risky activity was detected. Current level: :score / 100 (:level).',
    'credibility.notif.msg_bailiff_activated'     => 'Company credibility dropped sharply after the bailiff was activated. Current level: :score / 100 (:level).',
    'credibility.notif.msg_bankruptcy_entered'    => 'Company credibility dropped sharply after entering bankruptcy. Current level: :score / 100 (:level).',
    'credibility.notif.msg_recovery_plan_broken'  => 'Company credibility dropped after breaking the recovery plan. Current level: :score / 100 (:level).',
    'credibility.notif.msg_loan_fully_repaid'     => 'Company credibility increased after full loan repayment. Current level: :score / 100 (:level).',
    'credibility.notif.msg_loan_repaid_early'     => 'Company credibility increased after early loan repayment. Current level: :score / 100 (:level).',
    'credibility.notif.msg_clean_operation_period' => 'Company credibility increased after a period without negative events. Current level: :score / 100 (:level).',
    'credibility.notif.msg_admin_manual_adjustment' => 'Company credibility was adjusted. Current level: :score / 100 (:level).',

    'credibility.note_clean_operation_period' => 'Period without negative events: :days days.',

    'credibility.notif.msg_generic_up'   => 'Company credibility increased. Current level: :score / 100 (:level).',
    'credibility.notif.msg_generic_down' => 'Company credibility decreased. Current level: :score / 100 (:level).',
];
