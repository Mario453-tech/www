<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/sabotage.php') : microtime(true);
try {
    require_once __DIR__ . '/init.php';
    AdminAuth::requireLogin();
    require_once __DIR__ . '/../src/SabotageService.php';

    $db = Database::getInstance()->getConnection();
    $svc = new SabotageService($db);
    $msg = '';
    $err = '';

    $activeTab = (string)($_GET['tab'] ?? 'options');
    if (!in_array($activeTab, ['options', 'effects', 'attempts', 'logs', 'help'], true)) {
        $activeTab = 'options';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');
        }

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'toggle_module') {
            try {
                $enabled = isset($_POST['module_enabled']);
                $svc->setModuleEnabled($enabled);
                AdminLog::log('sabotage_module_toggle', 'Set sabotage module enabled=' . ($enabled ? '1' : '0'));
                $msg = t($enabled ? 'admin.sabotage.msg_module_enabled' : 'admin.sabotage.msg_module_disabled');
            } catch (Throwable $e) {
                GameLog::error('admin/sabotage.php', 'toggle_module FAILED', $e);
                $err = t('admin.sabotage.err_module_toggle');
            }
        } elseif ($action === 'save_option') {
            $optionId = (int)($_POST['option_id'] ?? 0);
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['code'] ?? '')));
            $name = trim((string)($_POST['name'] ?? ''));
            $targetType = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['target_type'] ?? 'road_transport')));
            $context = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['context'] ?? 'road_transport_sabotage')));
            $costType = (string)($_POST['cost_type'] ?? 'fixed');
            $costCurrency = (string)($_POST['cost_currency'] ?? 'cash');
            $severity = (string)($_POST['severity'] ?? 'medium');

            if (!in_array($costType, ['fixed', 'percent_reference', 'per_bbl'], true)) {
                $costType = 'fixed';
            }
            if (!in_array($costCurrency, ['cash', 'bank', 'black_market'], true)) {
                $costCurrency = 'cash';
            }
            if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
                $severity = 'medium';
            }

            if ($code === '' || $name === '' || $targetType === '' || $context === '') {
                $err = t('admin.sabotage.err_option_required');
            } else {
                try {
                    $values = [
                        $code,
                        $name,
                        trim((string)($_POST['description'] ?? '')),
                        $targetType,
                        $context,
                        isset($_POST['is_active']) ? 1 : 0,
                        max(0.0, min(100.0, (float)($_POST['base_chance_pct'] ?? 0))),
                        $costType,
                        max(0.0, (float)($_POST['cost_value'] ?? 0)),
                        $costCurrency,
                        $severity,
                        max(0, (int)($_POST['cooldown_minutes'] ?? 0)),
                        max(0, min(10, (int)($_POST['min_region_risk'] ?? 0))),
                        isset($_POST['requires_black_market']) ? 1 : 0,
                        (int)($_POST['sort_order'] ?? 0),
                    ];

                    if ($optionId > 0) {
                        $db->prepare(
                            "UPDATE sabotage_options
                                SET code = ?, name = ?, description = ?, target_type = ?, context = ?,
                                    is_active = ?, base_chance_pct = ?, cost_type = ?, cost_value = ?,
                                    cost_currency = ?, severity = ?, cooldown_minutes = ?,
                                    min_region_risk = ?, requires_black_market = ?, sort_order = ?
                              WHERE id = ?"
                        )->execute([...$values, $optionId]);
                    } else {
                        $db->prepare(
                            "INSERT INTO sabotage_options
                                (code, name, description, target_type, context, is_active, base_chance_pct,
                                 cost_type, cost_value, cost_currency, severity, cooldown_minutes,
                                 min_region_risk, requires_black_market, sort_order)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        )->execute($values);
                    }
                    AdminLog::log('sabotage_option_save', 'Save sabotage option: ' . $code);
                    $msg = t('admin.sabotage.msg_option_saved');
                } catch (Throwable $e) {
                    GameLog::error('admin/sabotage.php', 'save_option FAILED', $e);
                    $err = t('admin.sabotage.err_option_save');
                }
            }
            $activeTab = 'options';
        } elseif ($action === 'save_effect') {
            $effectId = (int)($_POST['effect_id'] ?? 0);
            $optionId = (int)($_POST['option_id'] ?? 0);
            $effectKey = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['effect_key'] ?? '')));
            $effectType = (string)($_POST['effect_type'] ?? 'delta');
            if (!in_array($effectType, ['mult', 'delta', 'set'], true)) {
                $effectType = 'delta';
            }
            $effectValue = (float)($_POST['effect_value'] ?? 0);

            if ($optionId <= 0 || $effectKey === '') {
                $err = t('admin.sabotage.err_effect_required');
            } else {
                try {
                    if ($effectId > 0) {
                        $db->prepare(
                            "UPDATE sabotage_effects
                                SET sabotage_option_id = ?, effect_key = ?, effect_type = ?, effect_value = ?
                              WHERE id = ?"
                        )->execute([$optionId, $effectKey, $effectType, $effectValue, $effectId]);
                    } else {
                        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
                        $upsert = $driver === 'sqlite'
                            ? "INSERT INTO sabotage_effects (sabotage_option_id, effect_key, effect_type, effect_value)
                                 VALUES (?, ?, ?, ?)
                                 ON CONFLICT(sabotage_option_id, effect_key)
                                 DO UPDATE SET effect_type = excluded.effect_type, effect_value = excluded.effect_value"
                            : "INSERT INTO sabotage_effects (sabotage_option_id, effect_key, effect_type, effect_value)
                                 VALUES (?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE effect_type = VALUES(effect_type), effect_value = VALUES(effect_value)";
                        $db->prepare($upsert)->execute([$optionId, $effectKey, $effectType, $effectValue]);
                    }
                    AdminLog::log('sabotage_effect_save', "Save sabotage effect: option {$optionId}, {$effectKey}");
                    $msg = t('admin.sabotage.msg_effect_saved');
                } catch (Throwable $e) {
                    GameLog::error('admin/sabotage.php', 'save_effect FAILED', $e);
                    $err = t('admin.sabotage.err_effect_save');
                }
            }
            $activeTab = 'effects';
        } elseif ($action === 'delete_effect') {
            $effectId = (int)($_POST['effect_id'] ?? 0);
            if ($effectId > 0) {
                try {
                    $db->prepare("DELETE FROM sabotage_effects WHERE id = ?")->execute([$effectId]);
                    AdminLog::log('sabotage_effect_delete', "Delete sabotage effect #{$effectId}");
                    $msg = t('admin.sabotage.msg_effect_deleted');
                } catch (Throwable $e) {
                    GameLog::error('admin/sabotage.php', 'delete_effect FAILED', $e);
                    $err = t('admin.sabotage.err_effect_delete');
                }
            }
            $activeTab = 'effects';
        }
    }

    $options = $svc->listOptions();
    $effectsByOption = $svc->listEffectsByOption();
    $attempts = $activeTab === 'attempts' ? $svc->listAttempts(100) : [];
    $logs = $activeTab === 'logs' ? $svc->listLogs(100) : [];
    $moduleEnabled = $svc->isModuleEnabled();

    $editOption = null;
    $editOptionId = (int)($_GET['edit'] ?? 0);
    foreach ($options as $row) {
        if ((int)$row['id'] === $editOptionId) {
            $editOption = $row;
            break;
        }
    }

    $editEffect = null;
    $editEffectId = (int)($_GET['effect_edit'] ?? 0);
    if ($editEffectId > 0) {
        foreach ($effectsByOption as $rows) {
            foreach ($rows as $row) {
                if ((int)$row['id'] === $editEffectId) {
                    $editEffect = $row;
                    break 2;
                }
            }
        }
    }

    $knownEffectKeys = [
        'transport_loss_pct',
        'oil_loss_pct',
        'oil_loss_fixed',
        'delay_minutes',
        'damage_pct',
        'condition_loss',
        'capacity_loss_pct',
        'production_stop_minutes',
        'repair_cost_pct',
        'pipeline_flow_loss_pct',
        'hub_buffer_loss_pct',
        'company_credibility_delta',
        'legal_case_risk_pct',
    ];

    $viewData = compact('options', 'effectsByOption', 'attempts', 'logs', 'activeTab',
        'editOption', 'editEffect', 'knownEffectKeys', 'msg', 'err', 'moduleEnabled');

    $pageTitle = t('admin.sabotage.title');
    $extraJs = ['/assets/js/admin_protection.js'];
    require_once __DIR__ . '/partials/header.php';
    require __DIR__ . '/../templates/views/admin/sabotage/main.php';
    require_once __DIR__ . '/partials/footer.php';
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/sabotage.php', 'Unhandled exception', $e);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/sabotage.php', $_codexGuardStart);
    }
}
