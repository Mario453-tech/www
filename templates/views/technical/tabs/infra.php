<?php
$locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$currencyLabel = $locale === 'en' ? 'USD' : ($locale === 'de' ? 'EUR' : 'PLN');
?>
<?php if (empty($pipelines)): ?>
<div class="pipe-card">
    <div class="pipe-hdr">
        <div>
            <div class="pipe-label"><?= t('technical.infra_label') ?></div>
            <div class="pipe-name"><?= t('technical.pipe_none_title') ?></div>
        </div>
        <span class="badge b-idle"><?= t('technical.pipe_none_badge') ?></span>
    </div>
    <div class="pipe-footer"><?= t('technical.pipe_none_desc') ?></div>
</div>
<?php else: ?>
<div class="pipe-grid">
<?php foreach ($pipelines as $pipe):
    $status = (string)($pipe['status'] ?? 'active');
    $conditionPct = (float)($pipe['condition_pct'] ?? 100.0);
    $lossPct = max(0.0, min(100.0, (float)($pipe['transport_loss'] ?? 0.0)));
    $capacityBbl = (float)($pipe['capacity_bbl_h'] ?? $pipe['real_capacity_bph'] ?? 0.0);

    $tickCost = (float)($pipe['opex_per_tick'] ?? 0.0);
    $flowCost = (float)($pipe['opex_per_bbl'] ?? 0.0);
    // The game applies transport loss after limiting flow by real capacity.
    // Gra odejmuje straty po ograniczeniu przeplywu rzeczywista przepustowoscia.
    $estimatedAfterLoss = $capacityBbl * (1 - $lossPct / 100);
    $pipeType = (string)($pipe['pipeline_type'] ?? 'standard');

    $statusBadgeClass = match ($status) {
        'damaged', 'disabled' => 'b-broken',
        'critical', 'degraded', 'servicing', 'suspended' => 'b-paused',
        default => 'b-active',
    };
    $statusLabelKey = 'technical.pipe_status_' . $status;
    $pipeTasks = array_filter($activeTasks, static fn($task): bool =>
        (int)($task['pipeline_id'] ?? 0) === (int)$pipe['id']
        && in_array($task['status'] ?? '', ['in_progress', 'paused_strike'], true)
        && in_array($task['task_type'] ?? '', ['pipeline_repair', 'pipeline_maintenance'], true)
    );
?>
<details name="technical-pipelines" data-tech-accordion="pipelines" class="pipe-card">
    <summary class="pipe-card-summary">
    <div class="pipe-hdr">
        <div>
            <div class="pipe-label"><?= t('technical.infra_label') ?></div>
            <div class="pipe-name"><img class="tech-icon" src="/assets/icons/technical/candidates/rur.svg" alt=""> <?= htmlspecialchars((string)($pipe['name'] ?? t('logistics.pipeline.fallback_name', ['id' => (int)($pipe['id'] ?? 0)])), ENT_QUOTES, 'UTF-8') ?></div>
            <?php if ((int)($pipe['hub_id'] ?? 0) > 0): ?>
            <div class="pipe-sub"><?= t('technical.pipe_hub_id') ?>: #<?= (int)$pipe['hub_id'] ?></div>
            <?php endif ?>
            <?php if ((int)($pipe['source_well_id'] ?? 0) > 0): ?>
            <div class="pipe-sub"><?= t('technical.pipe_related_well') ?>: <?= htmlspecialchars((string)($pipe['well_name'] ?? ('#' . (int)$pipe['source_well_id'])), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif ?>
        </div>
        <span class="badge <?= $statusBadgeClass ?>"><?= t($statusLabelKey) ?></span>
    </div>

    <div class="pipe-stats">
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_capacity') ?></div>
            <div class="w-stat-val c-blue"><?= number_format($capacityBbl, 1, ',', ' ') ?> <?= t('common.bbl_h') ?></div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_losses') ?></div>
            <div class="w-stat-val c-bad"><?= number_format($lossPct, 2, ',', ' ') ?>%</div>
        </div>
        <div>
            <div class="w-stat-lbl"><?= t('technical.pipe_condition') ?></div>
            <div class="w-stat-val <?= $conditionPct < 40 ? 'c-bad' : ($conditionPct < 70 ? 'c-warn' : 'c-green') ?>"><?= number_format($conditionPct, 1, ',', ' ') ?>%</div>
        </div>
    </div>
    <?php if ($pipeTasks): ?>
    <div class="well-tasks-list">
        <div class="well-tasks-hdr"><img class="tech-icon" src="/assets/icons/technical/wells/task.svg" alt=""> <?= t('technical.active_tasks_label') ?></div>
        <?php foreach ($pipeTasks as $task):
            $taskStart = strtotime((string)($task['start_time'] ?? '')) ?: 0;
            $taskEnd = strtotime((string)($task['end_time'] ?? '')) ?: 0;
            $taskDuration = max(1, $taskEnd - $taskStart);
            $taskProgress = max(0, min(100, round((time() - $taskStart) / $taskDuration * 100)));
        ?>
        <div class="well-task-row">
            <span class="well-task-name"><?= htmlspecialchars((string)($task['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= $taskProgress ?>%</span>
            <span class="well-task-who"><?= htmlspecialchars(trim((string)($task['first_name'] ?? '') . ' ' . (string)($task['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="well-task-time"><?= t('technical.task_remaining') ?>: <span class="countdown" data-end="<?= (int)$taskEnd ?>"></span></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php endif ?>
    <div class="well-expand-hint"><?= t('technical.pipe_parameters_costs') ?></div>
    </summary>
    <div class="pipe-detail">
        <?php if (in_array($status, ['damaged', 'disabled', 'critical', 'degraded'], true) && !$pipeTasks): ?>
        <a class="tech-gold-link" href="<?= htmlspecialchars(url('technical', ['tab' => 'team', 'repair_pipeline' => (int)$pipe['id'], 'task_type' => 'pipeline_repair']), ENT_QUOTES, 'UTF-8') ?>"><?= t('technical.repair_hint_pipeline') ?></a>
        <?php endif ?>
        <div class="well-detail-grid">
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.pipe_type') ?></div><div class="well-detail-val"><?= t('logistics.pipeline.type_' . $pipeType) ?></div></div>
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.pipe_tick_cost') ?></div><div class="well-detail-val"><?= number_format($tickCost, 2, ',', ' ') ?> <?= $currencyLabel ?></div></div>
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.pipe_flow_cost') ?></div><div class="well-detail-val"><?= number_format($flowCost, 2, ',', ' ') ?> <?= $currencyLabel ?>/<?= t('common.bbl') ?></div></div>
            <div class="well-detail-stat"><div class="well-detail-lbl"><?= t('technical.pipe_estimated_after_loss') ?></div><div class="well-detail-val"><?= number_format($estimatedAfterLoss, 1, ',', ' ') ?> <?= t('common.bbl_h') ?></div></div>
        </div>
    </div>
</details>
<?php endforeach ?>
</div>
<?php endif ?>
