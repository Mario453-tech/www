<?php

require_once __DIR__ . '/TechnicalPage/ActionsTrait.php';
require_once __DIR__ . '/TechnicalPage/DataTrait.php';
require_once __DIR__ . '/TechnicalPage/RecruitmentViewTrait.php';
require_once __DIR__ . '/TechnicalPage/ViewDataTrait.php';

class TechnicalPageController
{
    use TechnicalPageActionsTrait;
    use TechnicalPageDataTrait;
    use TechnicalPageRecruitmentViewTrait;
    use TechnicalPageViewDataTrait;

    private int $playerId;
    private TechnicalTeamService $svc;
    private WellService $wellSvc;
    private ?IncidentService $incidentSvc = null;
    private ?HubIncidentService $hubIncidentSvc = null;

    public function __construct(int $playerId)
    {
        $this->playerId = $playerId;

        try {
            $this->svc = new TechnicalTeamService($playerId);
            GameLog::info('technical.php', 'TechnicalTeamService initialized OK');
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'Failed to create TechnicalTeamService', $e);
            throw new RuntimeException(t('technical.err_init_svc'), 0, $e);
        }

        try {
            $this->wellSvc = new WellService();
            GameLog::info('technical.php', 'WellService initialized OK');
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'Failed to create WellService', $e);
            throw new RuntimeException(t('technical.err_init_well'), 0, $e);
        }

        try {
            $this->incidentSvc = new IncidentService();
            GameLog::info('technical.php', 'IncidentService initialized OK');
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'IncidentService init failed — continuing without', $e);
            $this->incidentSvc = null;
        }

        if (class_exists('HubIncidentService')) {
            try {
                $db = Database::getInstance()->getConnection();
                $this->hubIncidentSvc = new HubIncidentService($db);
            } catch (Throwable $e) {
                GameLog::error('technical.php', 'HubIncidentService init failed — continuing without', $e);
                $this->hubIncidentSvc = null;
            }
        }
    }
}
