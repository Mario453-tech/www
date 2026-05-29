<?php

trait TechnicalPageRecruitmentViewTrait
{
 /**
 * Sync ready recruitments before rendering the page.
 * Synchronizuje gotowe rekrutacje przed renderem strony.
 */
    public function syncReadyRecruitments(): void
    {
        try {
            $hrUiSync = new HRService();
            $processed = $hrUiSync->processReadyRecruitments();
            if ($processed > 0) {
                GameLog::info('technical.php', 'processReadyRecruitments on pageview', ['processed' => $processed]);
            }
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'processReadyRecruitments pageview FAIL', $e);
        }
    }

 /**
 * Build recruitment UI data for the technical page.
 * Buduje dane UI rekrutacji dla strony technicznej.
 */
    private function prepareRecruitmentUiData(array $staff, array $wells, array $activeRecruitments): array
    {
        $pendingRecruitments = [];
        foreach ($activeRecruitments as $recruitment) {
            $recruitmentId = (int) ($recruitment['id'] ?? 0);
            $status = $recruitment['status'] ?? '';

            if ($status === 'ready') {
                try {
                    $this->svc->completeRecruitment($recruitmentId);
                    GameLog::info('technical.php', 'auto-completed ready recruitment banner', ['req_id' => $recruitmentId]);
                } catch (Throwable $e) {
                    GameLog::error('technical.php', 'auto-complete ready recruitment banner FAILED', $e, ['req_id' => $recruitmentId]);
                }
                continue;
            }

            if ($status === 'pending') {
                $pendingRecruitments[] = $recruitment;
            }
        }

        $wellCount = max(1, count($wells));
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
            $neededCount = max(1, (int) ceil($wellCount * $ratio));
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
