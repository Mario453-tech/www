<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/legal.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/LegalService.php';

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

$tab   = (string)($_GET['tab'] ?? 'regions');
if (!in_array($tab, ['regions', 'applications'], true)) {
    $tab = 'regions';
}

$legal = new LegalService($db);

// == OBSŁUGA FORMULARZY ==

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? ''))
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');

    $action   = (string)($_POST['action'] ?? '');
    $regionId = (int)($_POST['region_id'] ?? 0);
    $appId    = (int)($_POST['app_id'] ?? 0);

    // Seed konfiguracji regionów z mapy (world_regions -> legal_region_config)
    if ($action === 'seed_regions') {
        try {
            $seeded = $legal->seedRegionConfig();
            AdminLog::log('legal_seed_regions', "Seed regionów: {$seeded} nowych wpisów");
            $msg = t('admin.legal.msg_seed_done', ['n' => $seeded]);
        } catch (Throwable $e) {
            $err = t('admin.legal.err_seed') . ': ' . $e->getMessage();
        }
        $tab = 'regions';
    }

    // Migracja przejściowa
    elseif ($action === 'run_migration') {
        try {
            $migrated = $legal->migrateTransitionalPermits();
            AdminLog::log('legal_migration_run', "Migracja: {$migrated} wpisów transitional");
            $msg = t('admin.legal.msg_migration_done', ['n' => $migrated]);
        } catch (Throwable $e) {
            $err = t('admin.legal.err_migration') . ': ' . $e->getMessage();
        }
        $tab = 'regions';
    }

    // Zapis konfiguracji regionu
    elseif ($action === 'save_region_config' && $regionId > 0) {
        $fields = [
            'enabled'                => max(0, min(1, (int)($_POST['enabled'] ?? 1))),
            'is_offshore'            => max(0, min(1, (int)($_POST['is_offshore'] ?? 0))),
            'risk_level'             => in_array($_POST['risk_level'] ?? '', LegalService::RISK_LEVELS, true)
                                            ? $_POST['risk_level'] : 'low',
            'application_cost'       => max(0.0, (float)($_POST['application_cost'] ?? 0)),
            'base_review_minutes'    => max(1, (int)($_POST['base_review_minutes'] ?? 60)),
            'delay_risk_pct'         => max(0.0, min(100.0, (float)($_POST['delay_risk_pct'] ?? 0))),
            'delay_min_minutes'      => max(1, (int)($_POST['delay_min_minutes'] ?? 10)),
            'delay_max_minutes'      => max(1, (int)($_POST['delay_max_minutes'] ?? 30)),
            'no_decision_risk_pct'   => max(0.0, min(100.0, (float)($_POST['no_decision_risk_pct'] ?? 0))),
            'refusal_risk_pct'       => max(0.0, min(100.0, (float)($_POST['refusal_risk_pct'] ?? 0))),
            'refusal_cooldown_minutes' => max(0, (int)($_POST['refusal_cooldown_minutes'] ?? 120)),
            'required_capital'       => max(0.0, (float)($_POST['required_capital'] ?? 0)),
            'required_legal_level'   => max(0, min(10, (int)($_POST['required_legal_level'] ?? 0))),
        ];
        try {
            $db->prepare(
                "UPDATE legal_region_config
                    SET enabled = ?, is_offshore = ?, risk_level = ?,
                        application_cost = ?, base_review_minutes = ?,
                        delay_risk_pct = ?, delay_min_minutes = ?, delay_max_minutes = ?,
                        no_decision_risk_pct = ?, refusal_risk_pct = ?,
                        refusal_cooldown_minutes = ?, required_capital = ?,
                        required_legal_level = ?
                  WHERE region_id = ?"
            )->execute([
                $fields['enabled'], $fields['is_offshore'], $fields['risk_level'],
                $fields['application_cost'], $fields['base_review_minutes'],
                $fields['delay_risk_pct'], $fields['delay_min_minutes'], $fields['delay_max_minutes'],
                $fields['no_decision_risk_pct'], $fields['refusal_risk_pct'],
                $fields['refusal_cooldown_minutes'], $fields['required_capital'],
                $fields['required_legal_level'],
                $regionId,
            ]);
            AdminLog::log('legal_region_config_save', "Region {$regionId}: " . json_encode($fields));
            $msg = t('admin.legal.msg_region_saved', ['id' => $regionId]);
        } catch (Throwable $e) {
            $err = t('admin.legal.err_save') . ': ' . $e->getMessage();
        }
        $tab = 'regions';
    }

    // Ręczna decyzja nad wnioskiem
    elseif (str_starts_with($action, 'manual_') && $appId > 0) {
        $nowStr = date('Y-m-d H:i:s');
        try {
            if ($action === 'manual_grant') {
                $db->prepare(
                    "UPDATE drilling_permit_applications
                        SET status = 'granted', decided_at = ?, refusal_cooldown_until = NULL
                      WHERE id = ?"
                )->execute([$nowStr, $appId]);
                AdminLog::log('legal_manual_grant', "App {$appId} granted manually");
                $msg = t('admin.legal.msg_manual_grant');
            } elseif ($action === 'manual_transitional') {
                $db->prepare(
                    "UPDATE drilling_permit_applications
                        SET status = 'transitional', decided_at = ?, refusal_cooldown_until = NULL
                      WHERE id = ?"
                )->execute([$nowStr, $appId]);
                AdminLog::log('legal_manual_transitional', "App {$appId} set transitional manually");
                $msg = t('admin.legal.msg_manual_transitional');
            } elseif ($action === 'manual_no_decision') {
                $db->prepare(
                    "UPDATE drilling_permit_applications
                        SET status = 'no_decision', decided_at = ?
                      WHERE id = ?"
                )->execute([$nowStr, $appId]);
                AdminLog::log('legal_manual_no_decision', "App {$appId} set no_decision manually");
                $msg = t('admin.legal.msg_manual_no_decision');
            } elseif ($action === 'manual_refuse') {
                $appRow = $db->prepare("SELECT region_id FROM drilling_permit_applications WHERE id = ?");
                $appRow->execute([$appId]);
                $ar = $appRow->fetch();
                $cooldownMin = 120;
                if ($ar) {
                    $cfg = $db->prepare("SELECT refusal_cooldown_minutes FROM legal_region_config WHERE region_id = ?");
                    $cfg->execute([(int)$ar['region_id']]);
                    $cfgRow = $cfg->fetch();
                    if ($cfgRow) $cooldownMin = (int)$cfgRow['refusal_cooldown_minutes'];
                }
                $cooldownUntil = date('Y-m-d H:i:s', strtotime("+{$cooldownMin} minutes"));
                $db->prepare(
                    "UPDATE drilling_permit_applications
                        SET status = 'refused', decided_at = ?, refusal_cooldown_until = ?
                      WHERE id = ?"
                )->execute([$nowStr, $cooldownUntil, $appId]);
                AdminLog::log('legal_manual_refuse', "App {$appId} refused manually, cooldown {$cooldownMin}min");
                $msg = t('admin.legal.msg_manual_refuse');
            } elseif ($action === 'manual_reset_pending') {
                $appRow = $db->prepare("SELECT region_id FROM drilling_permit_applications WHERE id = ?");
                $appRow->execute([$appId]);
                $ar = $appRow->fetch();
                $reviewMin = 60;
                if ($ar) {
                    $cfg = $db->prepare("SELECT base_review_minutes FROM legal_region_config WHERE region_id = ?");
                    $cfg->execute([(int)$ar['region_id']]);
                    $cfgRow = $cfg->fetch();
                    if ($cfgRow) $reviewMin = (int)$cfgRow['base_review_minutes'];
                }
                $newDueAt = date('Y-m-d H:i:s', strtotime("+{$reviewMin} minutes"));
                $db->prepare(
                    "UPDATE drilling_permit_applications
                        SET status = 'pending', decided_at = NULL, refusal_cooldown_until = NULL,
                            delay_count = 0, decision_due_at = ?
                      WHERE id = ?"
                )->execute([$newDueAt, $appId]);
                AdminLog::log('legal_manual_reset', "App {$appId} reset to pending manually");
                $msg = t('admin.legal.msg_manual_reset');
            }
        } catch (Throwable $e) {
            $err = t('admin.legal.err_action') . ': ' . $e->getMessage();
        }
        $tab = 'applications';
    }
}

// == DANE DLA WIDOKU ==

$regions = $legal->getAllRegionConfigs();

$applications = [];
try {
    $applications = $db->query(
        "SELECT a.*, p.company_name, p.username,
                r.name AS region_name
           FROM drilling_permit_applications a
           LEFT JOIN players p ON p.id = a.player_id
           LEFT JOIN world_regions r ON r.id = a.region_id
          ORDER BY a.submitted_at DESC
          LIMIT 500"
    )->fetchAll();
} catch (Throwable $e) {
    $err = t('admin.legal.err_load_apps') . ': ' . $e->getMessage();
}

// Statystyki
$stats = ['total' => 0, 'pending' => 0, 'granted' => 0, 'refused' => 0, 'delayed' => 0, 'other' => 0];
foreach ($applications as $a) {
    $stats['total']++;
    $s = (string)$a['status'];
    if (isset($stats[$s])) $stats[$s]++;
    elseif (!in_array($s, ['total'], true)) $stats['other']++;
}

$viewData = compact('regions', 'applications', 'stats', 'tab', 'msg', 'err');

$pageTitle = t('admin.legal.title');
$extraJs = ['/assets/js/admin_legal.js'];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/legal/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/legal.php', t('common.unhandled_exception'), $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/legal.php', $_codexGuardStart);
}
