<?php
declare(strict_types=1);

/**
 * Admin translations - company credibility.
 * Tlumaczenia admina - wiarygodnosc firmy.
 */

return [
    'admin.credibility.title' => 'Credibility of companies',
    'admin.credibility.subtitle' => 'Preview of the credibility of players\' companies and the history of changes. A manual correction is recorded in history.',

    // Statystyki / Stats
    'admin.credibility.stat_players' => 'Members',
    'admin.credibility.stat_avg' => 'Average Credibility',
    'admin.credibility.stat_critical' => 'Critical (0–19)',
    'admin.credibility.stat_high' => 'High (80–100)',

    // Lista graczy / Player list
    'admin.credibility.players_title' => 'Players',
    'admin.credibility.no_players' => 'No players.',
    'admin.credibility.col_player' => 'Industry',
    'admin.credibility.col_score' => 'Result',
    'admin.credibility.col_level' => 'Level',
    'admin.credibility.col_actions' => 'Shares',
    'admin.credibility.btn_history' => 'History',
    'admin.credibility.btn_adjust' => 'Proofreading',

    // Poziomy opisowe / Levels
    'admin.credibility.level_critical' => 'critical',
    'admin.credibility.level_low' => 'low',
    'admin.credibility.level_shaky' => 'Inconsistent.',
    'admin.credibility.level_stable' => 'Stable',
    'admin.credibility.level_high' => 'high',

    // Historia / History
    'admin.credibility.history_title' => 'Revision history —:player',
    'admin.credibility.history_back' => 'Back To List',
    'admin.credibility.no_history' => 'No saved changes for this player.',
    'admin.credibility.col_date' => 'Date',
    'admin.credibility.col_event' => 'Event',
    'admin.credibility.col_delta' => 'Shift',
    'admin.credibility.col_before' => 'Before',
    'admin.credibility.col_after' => 'After',
    'admin.credibility.col_note' => 'Note',

    // Reczna korekta — modal / Manual adjustment modal
    'admin.credibility.adjust_title' => 'Manual company credibility correction',
    'admin.credibility.adjust_intro' => 'Specify the change in the result and the reason for the correction. This operation will be saved in history.',
    'admin.credibility.adjust_delta_label' => 'Change in score (e.g. -10 or 5)',
    'admin.credibility.adjust_note_label' => 'Note / Reason',
    'admin.credibility.adjust_note_ph' => 'Reason for adjustment (required)',
    'admin.credibility.adjust_cancel' => 'Cancel',
    'admin.credibility.adjust_save' => 'Save adjustment',

    // Komunikaty / Messages
    'admin.credibility.msg_adjusted' => 'Player #__ TOK0__\'s credibility has been adjusted (:before →:after).',
    'admin.credibility.err_adjust' => 'Credibility correction error',
    'admin.credibility.err_need_note' => 'The correction requires a note / reason.',
    'admin.credibility.err_zero_delta' => 'The result change cannot be 0.',
    'admin.credibility.err_load' => 'Reliability data loading error',
];
