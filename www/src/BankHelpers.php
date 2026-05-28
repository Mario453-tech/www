<?php
declare(strict_types=1);

/**
 * Bank view helper functions for HTML badges and labels only.
 * PL: Pomocnicze funkcje widoku banku dla badge'y i etykiet HTML.
 */

function loanStatusBadge(string $status): string
{
    return match ($status) {
        'active' => '<span class="badge badge-success">' . t('bank.loan_status_active') . '</span>',
        'late'   => '<span class="badge badge-danger">' . t('bank.loan_status_late') . '</span>',
        default  => '<span class="badge badge-neutral">' . htmlspecialchars($status) . '</span>',
    };
}

function negStatusBadge(string $status): string
{
    return match ($status) {
        'pending'   => '<span class="badge badge-warning">' . t('bank.neg_status_pending') . '</span>',
        'approved'  => '<span class="badge badge-success">' . t('bank.neg_status_approved') . '</span>',
        'rejected'  => '<span class="badge badge-danger">' . t('bank.neg_status_rejected') . '</span>',
        'completed' => '<span class="badge badge-neutral">' . t('bank.neg_status_completed') . '</span>',
        'expired'   => '<span class="badge badge-neutral">' . t('bank.neg_status_expired') . '</span>',
        default     => '<span class="badge badge-neutral">' . htmlspecialchars($status) . '</span>',
    };
}

function negTypeLabel(string $type): string
{
    return match ($type) {
        'deferral'    => t('bank.neg_type_deferral'),
        'restructure' => t('bank.neg_type_restructure'),
        'recovery'    => t('bank.neg_type_recovery'),
        default       => htmlspecialchars($type),
    };
}

function negEventIcon(string $type): string
{
    return match ($type) {
        'delay', 'fee_increase', 'trust_penalty'    => '&#9888;&#65039;',
        'speedup', 'fee_decrease', 'approval_boost' => '&#9989;',
        default                                      => '&#8505;&#65039;',
    };
}
