<?php
require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$who = AdminAuth::getAdminUsername();
$msg = (string)($_SESSION['admin_flash_msg'] ?? '');
if ($msg !== '') { unset($_SESSION['admin_flash_msg']); }
$err = (string)($_SESSION['admin_flash_error'] ?? '');
if ($err !== '') { unset($_SESSION['admin_flash_error']); }

TaskConfigService::createTableIfNeeded($db);

$taskCatalog = TechnicalTeamService::TASKS;
$dbOverrides = TaskConfigService::loadAll($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_flash_error'] = t('common.csrf_error');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    $action   = $_POST['action'] ?? '';
    $taskType = $_POST['task_type'] ?? '';

    if ($action === 'save_task' && isset($taskCatalog[$taskType])) {
        try {
            $def    = $taskCatalog[$taskType];
            $values = [
                'cost_min'  => max(0, (int)($_POST['cost_min']  ?? $def['cost_min'])),
                'cost_max'  => max(0, (int)($_POST['cost_max']  ?? $def['cost_max'])),
                'hours_min' => max(1, (int)($_POST['hours_min'] ?? $def['hours_min'])),
                'hours_max' => max(1, (int)($_POST['hours_max'] ?? $def['hours_max'])),
            ];
            if ($values['cost_max'] < $values['cost_min']) {
                $values['cost_max'] = $values['cost_min'];
            }
            if ($values['hours_max'] < $values['hours_min']) {
                $values['hours_max'] = $values['hours_min'];
            }
            TaskConfigService::save($db, $taskType, $values, $who);
            AdminLog::log('task_config_save', "Saved task config: {$taskType}");
            $_SESSION['admin_flash_msg'] = tPlain('admin.tasks.msg_saved');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Throwable $e) {
            $_SESSION['admin_flash_error'] = tPlain('admin.tasks.err_save') . $e->getMessage();
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    } elseif ($action === 'reset_task' && isset($taskCatalog[$taskType])) {
        try {
            TaskConfigService::resetToDefault($db, $taskType);
            AdminLog::log('task_config_reset', "Reset task config: {$taskType}");
            $_SESSION['admin_flash_msg'] = tPlain('admin.tasks.msg_reset');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Throwable $e) {
            $_SESSION['admin_flash_error'] = tPlain('admin.tasks.err_reset') . $e->getMessage();
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

$pageTitle = t('admin.tasks.page_title');
require_once __DIR__ . '/partials/header.php';
?>

<div class="admin-content">
    <div class="page-header">
        <h1><?= t('admin.tasks.page_title') ?></h1>
        <p class="muted"><?= t('admin.tasks.page_desc') ?></p>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif ?>
    <?php if ($err): ?>
    <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endif ?>

    <?php
    $groups = [
        t('admin.tasks.group_maintenance') => ['well_maintenance','well_repair','hub_maintenance','hub_repair'],
        t('admin.tasks.group_technical')   => ['reservoir_analysis','production_optimization','install_module','safety_audit','reservoir_rehabilitation'],
        t('admin.tasks.group_pipeline')    => ['pipeline_maintenance','pipeline_inspection','pipeline_repair'],
        t('admin.tasks.group_emergency')   => ['blowout_control'],
    ];
    $csrfToken = CSRF::generateToken();
    ?>

    <?php foreach ($groups as $groupLabel => $taskTypes): ?>
    <section class="admin-section">
        <h2><?= htmlspecialchars($groupLabel) ?></h2>
        <div class="task-config-grid">
            <div class="task-config-header">
                <span><?= t('admin.tasks.col_task') ?></span>
                <span><?= t('admin.tasks.col_cost_min') ?></span>
                <span><?= t('admin.tasks.col_cost_max') ?></span>
                <span><?= t('admin.tasks.col_hours_min') ?></span>
                <span><?= t('admin.tasks.col_hours_max') ?></span>
                <span></span>
            </div>
            <?php foreach ($taskTypes as $taskType):
                if (!isset($taskCatalog[$taskType])) continue;
                $def      = $taskCatalog[$taskType];
                $override = $dbOverrides[$taskType] ?? [];
                $cur = [
                    'cost_min'  => $override['cost_min']  ?? $def['cost_min'],
                    'cost_max'  => $override['cost_max']  ?? $def['cost_max'],
                    'hours_min' => $override['hours_min'] ?? $def['hours_min'],
                    'hours_max' => $override['hours_max'] ?? $def['hours_max'],
                ];
                $hasOverride = !empty($override);
            ?>
            <form method="post" class="task-config-row<?= $hasOverride ? ' task-config-row--modified' : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action"    value="save_task">
                <input type="hidden" name="task_type" value="<?= htmlspecialchars($taskType) ?>">
                <span class="task-config-name">
                    <strong><?= htmlspecialchars(t($def['label_key'])) ?></strong>
                    <span class="badge badge-inactive"><?= htmlspecialchars($def['icon']) ?></span>
                    <?php if ($hasOverride): ?><span class="badge badge-active"><?= t('admin.tasks.badge_custom') ?></span><?php endif ?>
                </span>
                <span>
                    <input type="number" name="cost_min" value="<?= (int)$cur['cost_min'] ?>"
                           min="0" step="1" class="input-sm input-inline"
                           title="<?= htmlspecialchars(t('admin.tasks.col_cost_min')) ?>">
                    <span class="muted font-xs"><?= t('admin.tasks.currency') ?></span>
                </span>
                <span>
                    <input type="number" name="cost_max" value="<?= (int)$cur['cost_max'] ?>"
                           min="0" step="1" class="input-sm input-inline"
                           title="<?= htmlspecialchars(t('admin.tasks.col_cost_max')) ?>">
                    <span class="muted font-xs"><?= t('admin.tasks.currency') ?></span>
                </span>
                <span>
                    <input type="number" name="hours_min" value="<?= (int)$cur['hours_min'] ?>"
                           min="1" step="1" class="input-sm input-inline"
                           title="<?= htmlspecialchars(t('admin.tasks.col_hours_min')) ?>">
                    <span class="muted font-xs">h</span>
                </span>
                <span>
                    <input type="number" name="hours_max" value="<?= (int)$cur['hours_max'] ?>"
                           min="1" step="1" class="input-sm input-inline"
                           title="<?= htmlspecialchars(t('admin.tasks.col_hours_max')) ?>">
                    <span class="muted font-xs">h</span>
                </span>
                <span class="task-config-actions">
                    <button type="submit" class="btn btn-sm btn-primary"><?= t('common.save') ?></button>
                    <?php if ($hasOverride): ?>
                    <button type="submit" name="action" value="reset_task"
                            class="btn btn-sm btn-secondary"
                            onclick='return confirm(<?= htmlspecialchars(json_encode(t('admin.tasks.confirm_reset')), ENT_QUOTES, 'UTF-8') ?>)'>
                        <?= t('admin.tasks.btn_reset') ?>
                    </button>
                    <?php endif ?>
                </span>
            </form>
            <?php endforeach ?>
        </div>
    </section>
    <?php endforeach ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
