<?php
declare(strict_types=1);

/**
 * Protection module translations.
 * Polski - tlumaczenia modulu ochrony.
 */

return [
    'protection.err_not_found'      => 'This protection option does not exist.',
    'protection.err_wrong_context'  => 'This protection option does not match the selected target.',
    'protection.err_disabled'       => 'This protection is currently unavailable.',
    'protection.err_req_credibility'=> 'Required company credibility: at least :min/100.',
    'protection.err_req_legal'      => 'Required legal department level: at least :min/10.',
    'protection.err_already_active' => 'This target already has active protection (until :ends).',
    'protection.err_already_active_generic' => 'This target already has active protection.',
    'protection.err_no_funds'       => 'Not enough cash for protection (:cost USD required).',
    'protection.err_generic'        => 'Protection could not be activated. Try again.',

    'protection.msg_activated'      => 'Protection ":name" is active. :cost USD charged.',
    'protection.tx_label'           => 'Protection - :name',
    'protection.log_activated'      => 'Purchased protection ":name".',

    'protection.notif.activated.title'   => 'Protection activated',
    'protection.notif.activated.message' => 'Protection ":name" is active until :ends. :target',

    'protection.effect.strong'      => 'Significantly reduces the risk of :what.',
    'protection.effect.medium'      => 'Reduces the risk of :what.',
    'protection.effect.light'       => 'Slightly reduces the risk of :what.',
    'protection.effect.disclaimer'  => 'Reduces risk, but does not eliminate it.',

    'protection.risk.theft_risk_mult'    => 'theft',
    'protection.risk.raid_risk_mult'     => 'raids',
    'protection.risk.sabotage_risk_mult' => 'sabotage',
    'protection.risk.equipment_damage_risk_mult'  => 'equipment damage',
    'protection.risk.local_leak_risk_mult'        => 'leaks',
    'protection.risk.critical_overload_risk_mult' => 'critical overload',
    'protection.risk.transfer_failure_risk_mult'  => 'transfer failure',
    'protection.risk.loading_error_risk_mult'     => 'loading error',
    'protection.risk.storage_jam_risk_mult'       => 'storage jam',
    'protection.risk.pipeline_incident_risk_mult' => 'pipeline failure',

    'protection.err_target_invalid'  => 'This target does not exist or does not belong to you.',
    'protection.err_target_not_road' => 'Road transport protection works only for wells using truck transport.',
    'protection.target_well'         => 'Well #:id',
    'protection.target_hub'          => 'Hub #:id',
    'protection.target_pipeline'     => 'Pipeline #:id',
    'protection.pipeline_target_leg'   => 'Pipeline #:id (:leg)',
    'protection.pipeline_leg_inbound'  => 'well -> hub',
    'protection.pipeline_leg_outbound' => 'hub -> storage',

    'protection.section_title_road'     => 'Road transport protection',
    'protection.section_desc_road'      => 'Protection reduces the risk of theft, raids, and sabotage during transport runs',
    'protection.section_title_hub'      => 'Hub protection',
    'protection.section_desc_hub'       => 'Protection reduces the risk of equipment damage, leaks, and hub overload',
    'protection.section_title_pipeline' => 'Pipeline protection',
    'protection.section_desc_pipeline'  => 'Protection reduces pipeline failure risk',
    'protection.col_well'         => 'Well',
    'protection.col_hub'          => 'Hub',
    'protection.col_pipeline'     => 'Pipeline',
    'protection.col_protection'   => 'Protection',
    'protection.col_until'        => 'Active until',
    'protection.col_action'       => 'Action',
    'protection.status_none'      => 'no protection',
    'protection.btn_add'          => 'Add protection',
    'protection.btn_renew'        => 'Renew',
    'protection.btn_buy'          => 'Buy protection',
    'protection.btn_cancel'       => 'Cancel',
    'protection.modal_title'      => 'Choose protection',
    'protection.label_cost'       => 'Cost:',
    'protection.label_duration'   => 'Duration:',
    'protection.label_payment'    => 'Payment: cash',
    'protection.duration_minutes' => ':min minutes',
    'protection.locked_credibility' => 'Requires company credibility :min/100.',
    'protection.locked_legal'       => 'Requires legal department level :min/10.',
    'protection.not_affordable'     => 'Not enough cash.',
    'protection.confirm_question'   => 'Buy protection ":name" for :cost USD?',
    'protection.confirm_renew'      => 'Renew protection ":name" for :cost USD? The current protection will be cancelled without refund.',
];
