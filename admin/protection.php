<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/protection.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/ProtectionService.php';

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

$protectionSvc = new ProtectionService($db);

$activeTab = (string)($_GET['tab'] ?? 'options');
if (!in_array($activeTab, ['options', 'effects', 'active', 'history', 'help'], true)) {
    $activeTab = 'options';
}

// == OBSLUGA FORMULARZY / FORM HANDLING ==

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_option') {
        $optionId = (int)($_POST['option_id'] ?? 0);
        $code = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['code'] ?? '')));
        $name = trim((string)($_POST['name'] ?? ''));
        $costType = (string)($_POST['cost_type'] ?? 'fixed');
        if (!in_array($costType, ['fixed', 'percent_reference', 'per_hour', 'per_bbl'], true)) {
            $costType = 'fixed';
        }
        $costCurrency = 'cash';
        $values = [
            trim((string)($_POST['target_type'] ?? 'road_transport')) ?: 'road_transport',
            trim((string)($_POST['context'] ?? 'road_transport_guard')) ?: 'road_transport_guard',
            isset($_POST['is_active']) ? 1 : 0,
            $costType,
            max(0.0, (float)($_POST['cost_value'] ?? 0)),
            $costCurrency,
            max(1, (int)($_POST['duration_minutes'] ?? 60)),
            max(0, min(100, (int)($_POST['min_company_credibility'] ?? 0))),
            max(0, min(10, (int)($_POST['min_legal_level'] ?? 0))),
            (int)($_POST['sort_order'] ?? 0),
            $name,
            trim((string)($_POST['description'] ?? '')),
        ];

        if ($code === '' || $name === '') {
            $err = t('admin.protection.err_option_required');
        } else {
            try {
                if ($optionId > 0) {
                    $db->prepare(
                        "UPDATE protection_options
                            SET target_type = ?, context = ?, is_active = ?, cost_type = ?,
                                cost_value = ?, cost_currency = ?, duration_minutes = ?,
                                min_company_credibility = ?, min_legal_level = ?, sort_order = ?,
                                name = ?, description = ?, code = ?
                          WHERE id = ?"
                    )->execute([...$values, $code, $optionId]);
                } else {
                    $db->prepare(
                        "INSERT INTO protection_options
                            (target_type, context, is_active, cost_type, cost_value, cost_currency,
                             duration_minutes, min_company_credibility, min_legal_level, sort_order,
                             name, description, code)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([...$values, $code]);
                }
                AdminLog::log('protection_option_save', 'Zapis opcji ochrony: ' . $code);
                $msg = t('admin.protection.msg_option_saved');
            } catch (Throwable $e) {
                GameLog::error('admin/protection.php', 'save_option FAILED', $e);
                $err = t('admin.protection.err_option_save');
            }
        }
    } elseif ($action === 'save_effect') {
        $effectId = (int)($_POST['effect_id'] ?? 0);
        $optionId = (int)($_POST['option_id'] ?? 0);
        $effectKey = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['effect_key'] ?? '')));
        $effectType = (string)($_POST['effect_type'] ?? 'mult') === 'delta' ? 'delta' : 'mult';
        $effectValue = (float)($_POST['effect_value'] ?? 1.0);
        // Zakres wartosci: mult w [0.05, 1.0] (jak klamp przy odczycie), delta w [-0.99, 0.99].
        // Value range: mult in [0.05, 1.0] (matches read-time clamp), delta in [-0.99, 0.99].
        if ($effectType === 'mult') {
            $effectValue = max(0.05, min(1.0, $effectValue));
        } else {
            $effectValue = max(-0.99, min(0.99, $effectValue));
        }

        if ($optionId <= 0 || $effectKey === '') {
            $err = t('admin.protection.err_effect_required');
        } else {
            try {
                if ($effectId > 0) {
                    $db->prepare(
                        "UPDATE protection_effects
                            SET protection_option_id = ?, effect_key = ?, effect_type = ?, effect_value = ?
                          WHERE id = ?"
                    )->execute([$optionId, $effectKey, $effectType, $effectValue, $effectId]);
                } else {
                    $isSqlite = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
                    $upsert = $isSqlite
                        ? "INSERT INTO protection_effects (protection_option_id, effect_key, effect_type, effect_value)
                             VALUES (?, ?, ?, ?)
                             ON CONFLICT(protection_option_id, effect_key)
                             DO UPDATE SET effect_type = excluded.effect_type, effect_value = excluded.effect_value"
                        : "INSERT INTO protection_effects (protection_option_id, effect_key, effect_type, effect_value)
                             VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE effect_type = VALUES(effect_type), effect_value = VALUES(effect_value)";
                    $db->prepare($upsert)->execute([$optionId, $effectKey, $effectType, $effectValue]);
                }
                AdminLog::log('protection_effect_save', "Zapis efektu ochrony: opcja {$optionId}, {$effectKey}={$effectValue}");
                $msg = t('admin.protection.msg_effect_saved');
            } catch (Throwable $e) {
                GameLog::error('admin/protection.php', 'save_effect FAILED', $e);
                $err = t('admin.protection.err_effect_save');
            }
        }
        $activeTab = 'effects';
    } elseif ($action === 'delete_effect') {
        $effectId = (int)($_POST['effect_id'] ?? 0);
        if ($effectId <= 0) {
            $err = t('admin.protection.err_effect_required');
        } else {
            try {
                $db->prepare("DELETE FROM protection_effects WHERE id = ?")->execute([$effectId]);
                AdminLog::log('protection_effect_delete', "Usuniecie efektu ochrony #{$effectId}");
                $msg = t('admin.protection.msg_effect_deleted');
            } catch (Throwable $e) {
                GameLog::error('admin/protection.php', 'delete_effect FAILED', $e);
                $err = t('admin.protection.err_effect_delete');
            }
        }
        $activeTab = 'effects';
    } elseif ($action === 'cancel_active') {
        $activeId = (int)($_POST['active_id'] ?? 0);
        $res = $protectionSvc->cancel($activeId);
        if ($res['success']) {
            AdminLog::log('protection_cancel', "Anulowanie aktywnej ochrony #{$activeId}");
            $msg = t('admin.protection.msg_cancelled');
        } else {
            $err = (string)($res['message'] ?? t('common.app_error'));
        }
        $activeTab = 'active';
    }
}

// == DANE DLA WIDOKU / VIEW DATA ==

$options = [];
$effectsByOption = [];
$activeProtections = [];
$historyLogs = [];

try {
    $options = $db->query(
        "SELECT * FROM protection_options ORDER BY target_type, sort_order, id"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($db->query("SELECT * FROM protection_effects ORDER BY effect_key")->fetchAll(PDO::FETCH_ASSOC) as $effRow) {
        $effectsByOption[(int)$effRow['protection_option_id']][] = $effRow;
    }

    if ($activeTab === 'active') {
        $activeProtections = $db->query(
            "SELECT ap.*, po.name AS option_name, po.code AS option_code,
                    p.username, p.company_name
               FROM active_protections ap
               JOIN protection_options po ON po.id = ap.protection_option_id
               LEFT JOIN players p ON p.id = ap.player_id
              ORDER BY (ap.status = 'active') DESC, ap.ends_at DESC
              LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($activeTab === 'history') {
        $historyLogs = $db->query(
            "SELECT pl.*, po.name AS option_name, p.username, p.company_name
               FROM protection_logs pl
               LEFT JOIN protection_options po ON po.id = pl.protection_option_id
               LEFT JOIN players p ON p.id = pl.player_id
              ORDER BY pl.id DESC
              LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    GameLog::error('admin/protection.php', 'view data load FAILED', $e);
}

$editOptionId = (int)($_GET['edit'] ?? 0);
$editOption = null;
foreach ($options as $optRow) {
    if ((int)$optRow['id'] === $editOptionId) {
        $editOption = $optRow;
        break;
    }
}

$editEffectId = (int)($_GET['effect_edit'] ?? 0);
$editEffect = null;
if ($editEffectId > 0) {
    foreach ($effectsByOption as $effectRows) {
        foreach ($effectRows as $effectRow) {
            if ((int)$effectRow['id'] === $editEffectId) {
                $editEffect = $effectRow;
                break 2;
            }
        }
    }
}

// Wszystkie znane klucze efektow ze stalych serwisu / All known effect keys from service constants
$knownEffectKeys = array_merge(
    ProtectionService::EFFECT_KEYS_P1,
    ProtectionService::EFFECT_KEYS_P2_HUB,
    ProtectionService::EFFECT_KEYS_P2_PIPELINE,
);

// Mapa klucz efektu => modul (do dynamicznej tabeli w zakladce Pomoc) / Effect key => module map (for help tab)
$effectKeyModuleMap = array_fill_keys(ProtectionService::EFFECT_KEYS_P1,         'transport')
                    + array_fill_keys(ProtectionService::EFFECT_KEYS_P2_HUB,      'hub')
                    + array_fill_keys(ProtectionService::EFFECT_KEYS_P2_PIPELINE,  'pipeline');

$viewData = compact(
    'options', 'effectsByOption', 'activeProtections', 'historyLogs',
    'activeTab', 'editOption', 'editEffect', 'knownEffectKeys', 'effectKeyModuleMap', 'msg', 'err'
);

$pageTitle = t('admin.protection.title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/protection/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/protection.php', t('common.unhandled_exception'), $e);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/protection.php', $_codexGuardStart);
    }
}
