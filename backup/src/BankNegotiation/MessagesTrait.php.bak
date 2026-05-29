<?php

/**
 * Builds bank-facing and player-facing negotiation messages.
 * Buduje komunikaty negocjacyjne dla banku i gracza.
 */
trait BankNegotiationMessagesTrait
{
    /**
     * Builds the opening bank message shown after request submission.
     * Buduje startowy komunikat banku po zlozeniu wniosku.
     */
    private function buildBankOpeningMessage(float $hours, array $messages, string $dueAt): string
    {
        $dueF = date('d.m.Y H:i', strtotime($dueAt));
        $hoursF = $this->formatHoursNom($hours);

        $ref = 'BNK/' . date('Y') . '/' . rand(1000, 9999);
        $openings = [
            t('bank_neg.opening_1', ['hours' => $hoursF, 'due' => $dueF]),
            t('bank_neg.opening_2', ['due' => $dueF, 'hours' => $hoursF]),
            t('bank_neg.opening_3', ['due' => $dueF]),
            t('bank_neg.opening_4', ['due' => $dueF]),
            t('bank_neg.opening_5', ['ref' => $ref, 'due' => $dueF]),
        ];
        $base = $openings[array_rand($openings)];

        if (!empty($messages)) {
            $base .= ' ' . t('bank_neg.opening_complexity_prefix') . ' ' . implode(' ', $messages);
        }

        return $base;
    }

    /**
     * Builds the optional CFO opening comment.
     * Buduje opcjonalny komentarz otwarcia od CFO.
     */
    private function buildCfoOpeningMessage(string $cfoName, int $skill, string $dueAt): string
    {
        $dueF = date('d.m.Y H:i', strtotime($dueAt));

        if ($skill >= 80) {
            $msgs = [
                t('bank_neg.cfo_open_high_1', ['name' => $cfoName, 'due' => $dueF]),
                t('bank_neg.cfo_open_high_2', ['name' => $cfoName, 'due' => $dueF]),
                t('bank_neg.cfo_open_high_3', ['name' => $cfoName]),
            ];
        } elseif ($skill >= 50) {
            $msgs = [
                t('bank_neg.cfo_open_mid_1', ['name' => $cfoName, 'due' => $dueF]),
                t('bank_neg.cfo_open_mid_2', ['name' => $cfoName]),
                t('bank_neg.cfo_open_mid_3', ['name' => $cfoName, 'due' => $dueF]),
            ];
        } else {
            $msgs = [
                t('bank_neg.cfo_open_low_1', ['name' => $cfoName]),
                t('bank_neg.cfo_open_low_2', ['name' => $cfoName, 'due' => $dueF]),
                t('bank_neg.cfo_open_low_3', ['name' => $cfoName]),
            ];
        }

        return $msgs[array_rand($msgs)];
    }

    /**
     * Builds the approval decision message.
     * Buduje komunikat decyzji pozytywnej.
     */
    private function buildApprovalMessage(array $neg, array $ctx): string
    {
        $fee = number_format((float)$neg['additional_fee']);
        $dueF = date('d.m.Y H:i', strtotime('+' . self::APPROVAL_VALID_HOURS . ' hours', time()));

        if ($neg['type'] === 'deferral') {
            $days = (int)$neg['requested_deferral_days'];
            $msgs = [
                t('bank_neg.approval_deferral_1', ['days' => $days, 'fee' => $fee, 'due' => $dueF]),
                t('bank_neg.approval_deferral_2', ['days' => $days, 'fee' => $fee, 'due' => $dueF]),
                t('bank_neg.approval_deferral_3', ['days' => $days, 'fee' => $fee]),
            ];
        } elseif ($neg['type'] === 'restructure') {
            $months = (int)$neg['requested_extension_months'];
            $msgs = [
                t('bank_neg.approval_restructure_1', ['months' => $months, 'fee' => $fee, 'due' => $dueF]),
                t('bank_neg.approval_restructure_2', ['months' => $months, 'fee' => $fee]),
            ];
        } else {
            $msgs = [
                t('bank_neg.approval_recovery_1', ['fee' => $fee]),
                t('bank_neg.approval_recovery_2', ['fee' => $fee]),
            ];
        }

        $base = $msgs[array_rand($msgs)];

        if ($ctx['cfoName'] && $ctx['cfoSkill'] >= 50) {
            $cfoComments = [
                t('bank_neg.cfo_approve_1', ['name' => $ctx['cfoName']]),
                t('bank_neg.cfo_approve_2', ['name' => $ctx['cfoName'], 'due' => $dueF]),
                t('bank_neg.cfo_approve_3', ['name' => $ctx['cfoName']]),
            ];
            $base .= ' ' . $cfoComments[array_rand($cfoComments)];
        }

        return $base;
    }

    /**
     * Builds both public and internal rejection reasons.
     * Buduje publiczny i wewnetrzny powod odrzucenia.
     *
     * @return array{public:string, internal:string}
     */
    private function buildRejectionMessage(array $neg, array $ctx): array
    {
        $trustScore = $ctx['trustScore'] ?? 50;
        $creditScore = (int)($neg['credit_score'] ?? 50);
        $loanStatus = $neg['loan_status'] ?? '';

        // Public reason excludes internal-only trust numbers.
        // Publiczny powod nie pokazuje wewnetrznych wartosci trust.
        if ($creditScore < 30) {
            $public = t('bank_neg.rejection_low_score');
            $internal = "credit_score={$creditScore}<30";
        } elseif ($trustScore < 25) {
            $public = t('bank_neg.rejection_low_trust');
            $internal = "trust_score={$trustScore}<25";
        } elseif ($loanStatus === 'late') {
            $public = t('bank_neg.rejection_late');
            $internal = 'loan_status=late';
        } elseif ($ctx['ltv'] > 0.90) {
            $public = t('bank_neg.rejection_high_ltv');
            $internal = 'ltv=' . round($ctx['ltv'], 2) . '>0.90';
        } elseif ($ctx['totalWells'] > 0 && $ctx['troubledWells'] === $ctx['totalWells']) {
            $public = t('bank_neg.rejection_all_troubled');
            $internal = 'all_wells_troubled';
        } else {
            $variations = [
                t('bank_neg.rejection_general_1'),
                t('bank_neg.rejection_general_2'),
                t('bank_neg.rejection_general_3'),
            ];
            $public = $variations[array_rand($variations)];
            $internal = 'general_risk_assessment_failed';
        }

        if ($ctx['cfoName']) {
            if ($ctx['cfoSkill'] >= 60) {
                $cfoAddons = [
                    t('bank_neg.cfo_reject_high_1', ['name' => $ctx['cfoName']]),
                    t('bank_neg.cfo_reject_high_2', ['name' => $ctx['cfoName']]),
                ];
            } else {
                $cfoAddons = [
                    t('bank_neg.cfo_reject_low_1', ['name' => $ctx['cfoName']]),
                    t('bank_neg.cfo_reject_low_2', ['name' => $ctx['cfoName']]),
                ];
            }
            $public .= ' ' . $cfoAddons[array_rand($cfoAddons)];
        }

        return ['public' => $public, 'internal' => $internal];
    }
}
