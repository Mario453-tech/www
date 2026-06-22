<?php
declare(strict_types=1);

/**
 * Training module translations - player side.
 */

return [
    'training.page_title'        => 'Employee Training',
    'training.tab_label'         => 'Training',
    'training.heading_available' => 'Available courses',
    'training.heading_active'    => 'In progress',
    'training.heading_history'   => 'Exam history',

    'training.btn_enroll'        => 'Enroll',
    'training.btn_pick_staff'    => 'Pick employee',

    'training.label_duration'    => 'Duration',
    'training.label_cost'        => 'Cost',
    'training.label_pass_rate'   => 'Pass chance',
    'training.label_skill'       => 'Skill',
    'training.label_finishes'    => 'Finishes',
    'training.label_score'       => 'Score',
    'training.label_hours'       => ':n h',

    'training.skill.skill_drilling'     => 'Drilling',
    'training.skill.skill_maintenance'  => 'Maintenance',
    'training.skill.skill_safety'       => 'Safety (HSE)',
    'training.skill.skill_analysis'     => 'Analysis',
    'training.skill.skill_negotiation'  => 'Negotiation',
    'training.skill.skill_ethics'       => 'Ethics',
    'training.skill.skill_stress'       => 'Stress management',
    'training.skill.skill_organization' => 'Organization',

    'training.status.in_progress' => 'In progress',
    'training.status.passed'      => 'Passed',
    'training.status.failed'      => 'Failed',
    'training.status.cancelled'   => 'Cancelled',

    'training.exam_result'  => 'Score: :score/100 (required: :min)',
    'training.empty_active' => 'No employee is currently in training.',
    'training.empty_history'=> 'No training history.',
    'training.empty_programs'=> 'No courses available for this department.',

    'training.tx_fee' => 'Training fee: :program',

    'training.msg.enrolled' => 'Employee enrolled in course: :program. The exam will take place when the course ends.',

    'training.err.program_unavailable' => 'This course is unavailable.',
    'training.err.wrong_department'    => 'This course does not match the employee department.',
    'training.err.not_owner'           => 'This is not your employee.',
    'training.err.skill_maxed'         => 'This skill is already at the maximum level (10).',
    'training.err.already_training'    => 'This employee is already in a training course.',
    'training.err.on_cooldown'         => 'The employee is on cooldown after a failed exam. Try again later.',
    'training.err.insufficient_funds'  => 'Insufficient funds to cover the course cost.',
    'training.err.generic'             => 'Could not enroll in the course. Please try again.',
];
