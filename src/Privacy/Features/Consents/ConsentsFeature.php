<?php
require_once __DIR__ . '/ConsentRepository.php';
require_once __DIR__ . '/ConsentService.php';

class ConsentsFeature extends AbstractPrivacyFeature
{
    private ConsentRepository $repo;
    private ConsentService    $service;

    public function __construct(PDO $db, PrivacySettingsService $settings, PrivacyAuditLogger $audit)
    {
        parent::__construct($db, $settings, $audit);
        $this->repo    = new ConsentRepository($db);
        $this->service = new ConsentService($this->repo, $audit);
    }

    public function getKey(): string   { return 'consents'; }
    public function getLabel(): string { return t('privacy.feature.consents_label'); }
    public function getIcon(): string  { return '📋'; }

    public function handlePost(array $post, int $adminId, string $ip, string $ua): ?array
    {
        $action = (string)($post['action'] ?? '');
        if ($action === 'export_csv') {
            $filters = [
                'date_from'       => (string)($post['filter_date_from']       ?? ''),
                'date_to'         => (string)($post['filter_date_to']         ?? ''),
                'consent_version' => (string)($post['filter_consent_version'] ?? ''),
                'source'          => (string)($post['filter_source']          ?? ''),
            ];
            $csv = $this->service->exportCsv($filters);
            $this->audit->log($adminId, 'consents_export_csv', 'cookie_consents', null, null, $filters, $ip, $ua);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="consents_' . date('Ymd_His') . '.csv"');
            echo $csv;
            exit;
        }
        return null;
    }

    public function getViewData(array $get): array
    {
        $filters = [
            'date_from'       => (string)($get['filter_date_from']       ?? ''),
            'date_to'         => (string)($get['filter_date_to']         ?? ''),
            'consent_version' => (string)($get['filter_consent_version'] ?? ''),
            'source'          => (string)($get['filter_source']          ?? ''),
            'player_id'       => (int)($get['filter_player_id']          ?? 0),
        ];
        $page   = max(1, (int)($get['page'] ?? 1));
        $detail = isset($get['consent_id']) ? $this->repo->getById((int)$get['consent_id']) : null;

        return [
            'consents_data' => $this->repo->getList($filters, $page),
            'filters'       => $filters,
            'page'          => $page,
            'versions'      => $this->repo->getVersions(),
            'consent_detail'=> $detail,
        ];
    }

    public function getViewPath(): string
    {
        return __DIR__ . '/../../../../templates/views/admin/privacy/tab_consents.php';
    }
}
