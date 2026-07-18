<?php
/**
 * Hub staffing diagnostics and configuration view.
 * Widok diagnostyki i konfiguracji obsady hubow.
 */
$staffSummary = $staffingDiagnostics['summary'];
$staffPage = (int)$staffingDiagnostics['page'];
$staffTotalPages = (int)$staffingDiagnostics['total_pages'];
$staffPageQuery = array_filter([
    'staff_player_id' => $staffingFilters['player_id'] ?: null,
    'staff_hub_id' => $staffingFilters['hub_id'] ?: null,
    'staff_employee' => $staffingFilters['employee'] ?: null,
    'staff_status' => $staffingFilters['status'] ?: null,
]);
$staffPageUrl = static fn(int $targetPage): string => '/admin/logistics_hubs.php?'
    . http_build_query($staffPageQuery + ['staff_page' => $targetPage])
    . '#hub-staffing-section';
?>
<details id="hub-staffing-section" class="admin-details admin-details--staffing" open>
    <summary><?= t('admin.logistics.staffing_title') ?></summary>
    <p class="c-muted"><?= t('admin.logistics.staffing_desc') ?></p>

    <form method="post" action="/admin/logistics_hubs.php#hub-staffing-section" class="staffing-config-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="save_staffing_config">
        <label class="staffing-runtime-toggle">
            <input type="checkbox" name="staffing_enabled" value="1" <?= $staffingConfig['enabled'] ? 'checked' : '' ?>>
            <span><?= t('admin.logistics.staffing_runtime_enabled') ?></span>
            <strong class="<?= $staffingConfig['enabled'] ? 'c-good' : 'c-muted' ?>">
                <?= t($staffingConfig['enabled']
                    ? 'admin.logistics.staffing_runtime_on'
                    : 'admin.logistics.staffing_runtime_off') ?>
            </strong>
        </label>
        <fieldset class="staffing-config-fields">
            <legend><?= t('admin.logistics.staffing_config_title') ?></legend>
            <?php foreach (['small', 'medium', 'large'] as $hubType): ?>
                <label>
                    <span><?= t('admin.logistics.staffing_required_' . $hubType) ?></span>
                    <span class="staffing-config-input">
                        <input type="number" min="1" max="10" step="1"
                               name="required_<?= $hubType ?>"
                               value="<?= (int)$staffingConfig[$hubType] ?>"
                               class="admin-input admin-input-dark">
                        <small><?= t('admin.logistics.staffing_people') ?></small>
                    </span>
                </label>
            <?php endforeach ?>
        </fieldset>
        <button type="submit" class="btn btn-sm btn-primary"><?= t('admin.logistics.staffing_config_save') ?></button>
    </form>

    <div class="staffing-summary-grid">
        <?php foreach ([
            'controlled_hubs' => 'staffing_controlled_hubs',
            'fully_staffed' => 'staffing_fully_staffed',
            'understaffed' => 'staffing_understaffed',
            'unstaffed' => 'staffing_unstaffed',
        ] as $summaryKey => $labelKey): ?>
            <div class="verify-card">
                <div class="verify-label"><?= t('admin.logistics.' . $labelKey) ?></div>
                <div class="verify-value"><?= (int)$staffSummary[$summaryKey] ?></div>
            </div>
        <?php endforeach ?>
        <div class="verify-card">
            <div class="verify-label"><?= t('admin.logistics.staffing_average_coverage') ?></div>
            <div class="verify-value"><?= number_format((float)$staffSummary['average_coverage'], 1) ?>%</div>
        </div>
    </div>

    <h4><?= t('admin.logistics.staffing_attention_title') ?></h4>
    <?php if ($staffingDiagnostics['coverage_rows'] === []): ?>
        <p class="c-muted"><?= t('admin.logistics.staffing_attention_empty') ?></p>
    <?php else: ?>
        <div class="staffing-coverage-list">
            <?php foreach ($staffingDiagnostics['coverage_rows'] as $row):
                $coverage = (float)$row['coverage_pct'];
                $coverageClass = $coverage >= 100.0 ? 'c-good' : ($coverage > 0.0 ? 'c-warn' : 'c-bad');
            ?>
                <article class="staffing-coverage-row">
                    <div class="staffing-coverage-name">
                        <strong><?= htmlspecialchars((string)$row['hub_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small>#<?= (int)$row['hub_id'] ?> · <?= htmlspecialchars((string)$row['region_name'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                    <div>
                        <span><?= t('admin.logistics.staffing_coverage') ?></span>
                        <strong class="<?= $coverageClass ?>"><?= number_format($coverage, 1) ?>%</strong>
                    </div>
                    <div>
                        <span><?= t('admin.logistics.staffing_assigned') ?></span>
                        <strong><?= (int)$row['assigned_count'] ?> / <?= (int)$row['required_count'] ?></strong>
                    </div>
                    <div>
                        <span><?= t('admin.logistics.staffing_skill') ?></span>
                        <strong><?= number_format((float)$row['average_skill'], 1) ?>/10</strong>
                    </div>
                    <div>
                        <span><?= t('admin.logistics.staffing_morale') ?></span>
                        <strong><?= number_format((float)$row['average_morale'], 1) ?>%</strong>
                    </div>
                </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <h4><?= t('admin.logistics.staffing_filter_title') ?></h4>
    <form method="get" action="/admin/logistics_hubs.php#hub-staffing-section" class="staffing-filter-form">
        <label>
            <span><?= t('admin.logistics.staffing_filter_player') ?></span>
            <input type="number" min="1" name="staff_player_id" value="<?= $staffingFilters['player_id'] ?: '' ?>" class="admin-input admin-input-dark">
        </label>
        <label>
            <span><?= t('admin.logistics.staffing_filter_hub') ?></span>
            <input type="number" min="1" name="staff_hub_id" value="<?= $staffingFilters['hub_id'] ?: '' ?>" class="admin-input admin-input-dark">
        </label>
        <label>
            <span><?= t('admin.logistics.staffing_filter_employee') ?></span>
            <input type="search" name="staff_employee" value="<?= htmlspecialchars($staffingFilters['employee'], ENT_QUOTES, 'UTF-8') ?>" class="admin-input admin-input-dark">
        </label>
        <label>
            <span><?= t('admin.logistics.staffing_filter_status') ?></span>
            <select name="staff_status" class="admin-input admin-select-dark">
                <option value=""><?= t('admin.logistics.staffing_filter_all') ?></option>
                <option value="active" <?= $staffingFilters['status'] === 'active' ? 'selected' : '' ?>><?= t('admin.logistics.staffing_status_active') ?></option>
                <option value="released" <?= $staffingFilters['status'] === 'released' ? 'selected' : '' ?>><?= t('admin.logistics.staffing_status_released') ?></option>
            </select>
        </label>
        <div class="staffing-filter-actions">
            <button type="submit" class="btn btn-sm btn-secondary"><?= t('admin.logistics.staffing_filter_submit') ?></button>
            <a href="/admin/logistics_hubs.php#hub-staffing-section" class="btn btn-sm btn-secondary"><?= t('admin.logistics.staffing_filter_reset') ?></a>
        </div>
    </form>

    <div class="staffing-assignment-list">
        <div class="staffing-assignment-row staffing-assignment-row--head">
            <span><?= t('admin.logistics.staffing_assignment_employee') ?></span>
            <span><?= t('admin.logistics.staffing_assignment_player') ?></span>
            <span><?= t('admin.logistics.staffing_assignment_hub') ?></span>
            <span><?= t('admin.logistics.staffing_assignment_allocation') ?></span>
            <span><?= t('admin.logistics.staffing_assignment_status') ?></span>
            <span><?= t('admin.logistics.staffing_assignment_updated') ?></span>
        </div>
        <?php foreach ($staffingDiagnostics['assignments'] as $assignment): ?>
            <div class="staffing-assignment-row">
                <span><?= htmlspecialchars((string)$assignment['employee_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span>#<?= (int)$assignment['player_id'] ?> <?= htmlspecialchars((string)($assignment['company_name'] ?: $assignment['username']), ENT_QUOTES, 'UTF-8') ?></span>
                <span>#<?= (int)$assignment['target_id'] ?> <?= htmlspecialchars((string)$assignment['hub_name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= number_format((float)$assignment['allocation_pct'], 1) ?>%</span>
                <span><?= t('admin.logistics.staffing_status_' . $assignment['status']) ?></span>
                <span><?= htmlspecialchars((string)$assignment['updated_at'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endforeach ?>
    </div>
    <?php if ($staffingDiagnostics['assignments'] === []): ?>
        <p class="c-muted"><?= t('admin.logistics.staffing_assignments_empty') ?></p>
    <?php endif ?>

    <?php if ($staffTotalPages > 1): ?>
        <nav class="hub-pagination" aria-label="<?= t('admin.logistics.staffing_filter_title') ?>">
            <?php if ($staffPage > 1): ?>
                <a href="<?= htmlspecialchars($staffPageUrl($staffPage - 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-secondary"><?= t('admin.logistics.page_prev') ?></a>
            <?php endif ?>
            <span class="hub-pagination-info"><?= t('admin.logistics.page_info', ['page' => $staffPage, 'total' => $staffTotalPages]) ?></span>
            <?php if ($staffPage < $staffTotalPages): ?>
                <a href="<?= htmlspecialchars($staffPageUrl($staffPage + 1), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-secondary"><?= t('admin.logistics.page_next') ?></a>
            <?php endif ?>
        </nav>
    <?php endif ?>
</details>
