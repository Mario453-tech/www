<?php

trait TechnicalPageRecruitmentViewTrait
{
 /**
 * Build recruitment UI data for the technical page.
 * Buduje dane UI rekrutacji dla strony technicznej.
 */
    private function prepareRecruitmentUiData(array $staff, array $wells, array $activeRecruitments): array
    {
        $pendingRecruitments = [];
        foreach ($activeRecruitments as $recruitment) {
            $status = $recruitment['status'] ?? '';

            if ($status === 'ready') {
                continue;
            }

            if ($status === 'pending') {
                $pendingRecruitments[] = $recruitment;
            }
        }

        $wellCount = count($wells);
        $staffCountsBySpec = [];
        foreach ($staff as $staffMember) {
            $specCode = $staffMember['spec_code'] ?? '';
            $staffCountsBySpec[$specCode] = ($staffCountsBySpec[$specCode] ?? 0) + 1;
        }

        $staffRatios = [
            'drilling_engineer' => 2,
            'reservoir_engineer' => 0.5,
            'production_engineer' => 1,
            'maintenance_engineer' => 1,
            'pipeline_engineer' => 1,
            'safety_officer' => 0.5,
            'safety_engineer' => 0.5,
        ];

        $specRecruitmentCards = [];
        foreach (TechnicalTeamService::getSpecsCatalog() as $specCode => $spec) {
            $hiredCount = (int) ($staffCountsBySpec[$specCode] ?? 0);
            $ratio = $staffRatios[$specCode] ?? 1;
            $neededCount = $wellCount > 0 ? max(1, (int) ceil($wellCount * $ratio)) : 0;
            $remainingSlots = $this->svc->getRecruitmentCapacityForSpec($specCode);
            $hiredLabel = $hiredCount === 1 ? t('technical.hired_single') : t('technical.hired_plural');

            $specRecruitmentCards[] = [
                'spec_code' => $specCode,
                'name' => $spec['name'],
                'icon' => $spec['icon'],
                'hired_count' => $hiredCount,
                'needed_count' => $neededCount,
                'remaining_slots' => $remainingSlots,
                'card_class' => $hiredCount > 0 ? ($hiredCount < $neededCount ? 'spec-partial' : 'spec-have') : 'spec-missing',
                'count_class' => $hiredCount >= $neededCount ? 'c-green' : ($hiredCount > 0 ? 'c-warn' : 'c-muted2'),
                'count_text' => t('technical.spec_hired', ['cnt' => $hiredCount, 'label' => $hiredLabel]),
                'count_options' => $remainingSlots > 0 ? range(1, $remainingSlots) : [],
            ];
        }

        return [
            'pendingRecruitments' => $pendingRecruitments,
            'specRecruitmentCards' => $specRecruitmentCards,
        ];
    }
}
