<?php
require_once __DIR__ . '/PolicyRepository.php';
require_once __DIR__ . '/../../PrivacyPolicyService.php';

class PolicyFeature extends AbstractPrivacyFeature
{
    private PolicyRepository   $repo;
    private PrivacyPolicyService $policyService;

    public function __construct(PDO $db, PrivacySettingsService $settings, PrivacyAuditLogger $audit)
    {
        parent::__construct($db, $settings, $audit);
        $this->repo          = new PolicyRepository($db);
        $this->policyService = new PrivacyPolicyService($db);
    }

    public function getKey(): string   { return 'policy'; }
    public function getLabel(): string { return t('privacy.feature.policy_label'); }
    public function getIcon(): string  { return '📄'; }

    public function handlePost(array $post, int $adminId, string $ip, string $ua): ?array
    {
        $action = (string)($post['action'] ?? '');

        if ($action === 'policy_create') {
            $policyType = (string)($post['policy_type'] ?? '');
            $version    = trim((string)($post['version'] ?? ''));
            $title      = trim((string)($post['title']   ?? ''));
            $content    = trim((string)($post['content'] ?? ''));

            if (!in_array($policyType, ['cookies', 'privacy'], true)) {
                return ['success' => false, 'message' => t('privacy.policy.err_invalid_type')];
            }
            if ($version === '' || $title === '') {
                return ['success' => false, 'message' => t('privacy.policy.err_required_fields')];
            }
            $id = $this->policyService->create($policyType, $version, $title, $content);
            $this->audit->log($adminId, 'policy_create', 'privacy_policy_versions', $id,
                              null, compact('policyType','version','title'), $ip, $ua);
            return ['success' => true, 'message' => t('privacy.policy.msg_created')];
        }

        if ($action === 'policy_update') {
            $id      = (int)($post['id'] ?? 0);
            $title   = trim((string)($post['title']   ?? ''));
            $content = trim((string)($post['content'] ?? ''));
            if ($title === '') {
                return ['success' => false, 'message' => t('privacy.policy.err_required_fields')];
            }
            $old = $this->repo->getById($id);
            if (!$old) return ['success' => false, 'message' => t('privacy.policy.err_not_found')];

            if (!$this->policyService->update($id, $title, $content)) {
                return ['success' => false, 'message' => t('privacy.policy.err_cannot_edit_active')];
            }
            $this->audit->log($adminId, 'policy_update', 'privacy_policy_versions', $id, $old,
                              compact('title'), $ip, $ua);
            return ['success' => true, 'message' => t('privacy.policy.msg_updated')];
        }

        if ($action === 'policy_activate') {
            $id  = (int)($post['id'] ?? 0);
            $old = $this->repo->getById($id);
            if (!$old) return ['success' => false, 'message' => t('privacy.policy.err_not_found')];
            if (!$this->policyService->activate($id)) {
                return ['success' => false, 'message' => t('privacy.policy.err_not_found')];
            }
            $this->audit->log($adminId, 'policy_activate', 'privacy_policy_versions', $id,
                              ['is_active' => 0], ['is_active' => 1], $ip, $ua);
            return ['success' => true, 'message' => t('privacy.policy.msg_activated')];
        }

        return null;
    }

    public function getViewData(array $get): array
    {
        $editId  = (int)($get['edit_policy'] ?? 0);
        return [
            'policies'   => $this->repo->getAll(),
            'edit_policy'=> $editId ? $this->repo->getById($editId) : null,
        ];
    }

    public function getViewPath(): string
    {
        return __DIR__ . '/../../../../templates/views/admin/privacy/tab_policies.php';
    }
}
