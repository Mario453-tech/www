<?php
declare(strict_types=1);

/**
 * Admin translations - training module (EN).
 */

return [
    'admin.training.page_title'            => 'Training System',

    'admin.training.tab_programs'          => 'Training Programs',
    'admin.training.tab_monitor'           => 'Monitor',

    'admin.training.programs.heading'      => 'Training Programs',
    'admin.training.programs.btn_add'      => 'Add Program',
    'admin.training.programs.btn_save'     => 'Save',
    'admin.training.programs.btn_cancel'   => 'Cancel',
    'admin.training.programs.btn_edit'     => 'Edit',
    'admin.training.programs.btn_enable'   => 'Enable',
    'admin.training.programs.btn_disable'  => 'Disable',
    'admin.training.programs.confirm_disable' => 'Are you sure you want to disable this program?',

    'admin.training.programs.col_code'       => 'Code',
    'admin.training.programs.col_name'       => 'Name',
    'admin.training.programs.col_dept'       => 'Department',
    'admin.training.programs.col_skill'      => 'Skill',
    'admin.training.programs.col_duration'   => 'Duration (h)',
    'admin.training.programs.col_cost'       => 'Cost',
    'admin.training.programs.col_pass_rate'  => 'Base %',
    'admin.training.programs.col_enabled'    => 'Active',
    'admin.training.programs.col_actions'    => 'Actions',

    'admin.training.programs.label_code'       => 'Program code (unique)',
    'admin.training.programs.label_dept'       => 'Department',
    'admin.training.programs.label_skill'      => 'Target skill',
    'admin.training.programs.label_name_pl'    => 'Name PL',
    'admin.training.programs.label_name_en'    => 'Name EN',
    'admin.training.programs.label_duration'   => 'Duration (hours)',
    'admin.training.programs.label_cost'       => 'Cost (from bank balance)',
    'admin.training.programs.label_pass_rate'  => 'Base exam pass rate (%)',
    'admin.training.programs.label_enabled'    => 'Program active',
    'admin.training.programs.hint_pass_rate'   => 'Modifiers (staff ambition, retry bonus) are applied automatically.',

    'admin.training.dept.technical' => 'Technical Department',
    'admin.training.dept.board'     => 'Board',

    'admin.training.skill.skill_drilling'    => 'Drilling',
    'admin.training.skill.skill_maintenance' => 'Maintenance',
    'admin.training.skill.skill_safety'      => 'Safety (HSE)',
    'admin.training.skill.skill_analysis'    => 'Analysis',
    'admin.training.skill.skill_negotiation' => 'Negotiation',
    'admin.training.skill.skill_ethics'      => 'Ethics',
    'admin.training.skill.skill_stress'      => 'Stress management',
    'admin.training.skill.skill_organization'=> 'Organization',

    'admin.training.msg.program_created'  => 'Training program has been added.',
    'admin.training.msg.program_updated'  => 'Training program has been updated.',
    'admin.training.msg.program_enabled'  => 'Program enabled.',
    'admin.training.msg.program_disabled' => 'Program disabled.',
    'admin.training.err.not_found'        => 'Program not found.',
    'admin.training.err.code_exists'      => 'A program with this code already exists.',
    'admin.training.err.invalid_rate'     => 'Base pass rate must be a number between 1 and 100.',
    'admin.training.err.invalid_cost'     => 'Cost must be a number >= 0.',
    'admin.training.err.invalid_hours'    => 'Duration must be a number >= 1.',
    'admin.training.err.invalid_skill'    => 'The selected skill does not match the department.',

    'admin.training.monitor.heading'       => 'Active and Recent Trainings',
    'admin.training.monitor.col_player'    => 'Player',
    'admin.training.monitor.col_staff'     => 'Employee',
    'admin.training.monitor.col_dept'      => 'Department',
    'admin.training.monitor.col_program'   => 'Program',
    'admin.training.monitor.col_status'    => 'Status',
    'admin.training.monitor.col_started'   => 'Started',
    'admin.training.monitor.col_finishes'  => 'Finishes',
    'admin.training.monitor.col_result'    => 'Result',
    'admin.training.monitor.filter_status' => 'Status',
    'admin.training.monitor.filter_dept'   => 'Department',
    'admin.training.monitor.empty'         => 'No trainings to display.',
    'admin.training.programs.empty'        => 'No training programs defined.',

    'admin.training.status.in_progress' => 'In progress',
    'admin.training.status.passed'      => 'Passed',
    'admin.training.status.failed'      => 'Failed',
    'admin.training.status.cancelled'   => 'Cancelled',
];
