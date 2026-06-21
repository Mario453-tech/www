<?php /** Zakładka: Historia zgód */ ?>

<div class="privacy-section">
    <div class="section-header">
        <h2><?= t('privacy.consents.tab_heading') ?></h2>
    </div>

    <?php if ($consent_detail): ?>
    <div class="card">
        <h3><?= t('privacy.consents.detail_heading') ?> #<?= (int)$consent_detail['id'] ?></h3>
        <dl class="detail-list">
            <dt><?= t('privacy.consents.col_player') ?></dt>
            <dd><?= $consent_detail['player_id']
                ? htmlspecialchars($consent_detail['username'] ?? 'ID: ' . $consent_detail['player_id'])
                : t('privacy.consents.anonymous') ?></dd>
            <dt><?= t('privacy.consents.col_version') ?></dt>
            <dd><?= htmlspecialchars($consent_detail['consent_version']) ?> / baner: <?= htmlspecialchars($consent_detail['banner_version']) ?></dd>
            <dt><?= t('privacy.consents.col_accepted') ?></dt>
            <dd><?= htmlspecialchars($consent_detail['accepted_categories_json']) ?></dd>
            <dt><?= t('privacy.consents.col_withdrawn') ?></dt>
            <dd><?= $consent_detail['rejected_categories_json'] ?></dd>
            <dt><?= t('privacy.consents.col_source') ?></dt>
            <dd><?= htmlspecialchars($consent_detail['source']) ?></dd>
            <dt>IP</dt>
            <dd><?= htmlspecialchars($consent_detail['ip_address'] ?? '—') ?></dd>
            <dt><?= t('privacy.consents.col_date') ?></dt>
            <dd><?= htmlspecialchars($consent_detail['created_at']) ?></dd>
            <?php if ($consent_detail['withdrawn_at']): ?>
            <dt><?= t('privacy.consents.col_withdrawn') ?></dt>
            <dd><?= htmlspecialchars($consent_detail['withdrawn_at']) ?></dd>
            <?php endif ?>
        </dl>
        <a href="?tab=consents" class="btn btn-secondary btn-sm">← Wróć do listy</a>
    </div>
    <?php endif ?>

    <div class="filter-bar">
        <form method="get" class="filter-form">
            <input type="hidden" name="tab" value="consents">
            <input type="date" name="filter_date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>"
                   placeholder="<?= t('privacy.consents.filter_from') ?>">
            <input type="date" name="filter_date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>"
                   placeholder="<?= t('privacy.consents.filter_to') ?>">
            <select name="filter_consent_version">
                <option value=""><?= t('privacy.consents.filter_version') ?>: wszystkie</option>
                <?php foreach ($versions as $v): ?>
                    <option value="<?= htmlspecialchars($v) ?>"
                        <?= ($filters['consent_version'] ?? '') === $v ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <select name="filter_source">
                <option value=""><?= t('privacy.consents.filter_source') ?>: wszystkie</option>
                <option value="banner"   <?= ($filters['source'] ?? '') === 'banner'   ? 'selected' : '' ?>>banner</option>
                <option value="settings" <?= ($filters['source'] ?? '') === 'settings' ? 'selected' : '' ?>>settings</option>
                <option value="api"      <?= ($filters['source'] ?? '') === 'api'      ? 'selected' : '' ?>>api</option>
            </select>
            <input type="number" name="filter_player_id" min="1"
                   value="<?= (int)($filters['player_id'] ?? 0) ?: '' ?>"
                   placeholder="<?= t('privacy.consents.filter_player') ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><?= t('privacy.consents.btn_filter') ?></button>
        </form>

        <form method="post" style="display:inline">
            <?= CSRF::field() ?>
            <input type="hidden" name="tab" value="consents">
            <input type="hidden" name="action" value="export_csv">
            <?php foreach ($filters as $k => $v): if ($v === '' || $v == 0) continue; ?>
                <input type="hidden" name="filter_<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
            <?php endforeach ?>
            <button type="submit" class="btn btn-secondary btn-sm"><?= t('privacy.consents.btn_export') ?></button>
        </form>
    </div>

    <?php
    $rows  = $consents_data['rows']  ?? [];
    $total = $consents_data['total'] ?? 0;
    $pages = $consents_data['pages'] ?? 0;
    ?>

    <?php if (empty($rows)): ?>
        <p class="muted"><?= t('privacy.consents.empty') ?></p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('privacy.consents.col_id') ?></th>
                    <th><?= t('privacy.consents.col_player') ?></th>
                    <th><?= t('privacy.consents.col_version') ?></th>
                    <th><?= t('privacy.consents.col_accepted') ?></th>
                    <th><?= t('privacy.consents.col_source') ?></th>
                    <th><?= t('privacy.consents.col_date') ?></th>
                    <th><?= t('privacy.consents.col_withdrawn') ?></th>
                    <th><?= t('privacy.consents.col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr class="<?= $r['withdrawn_at'] ? 'row-muted' : '' ?>">
                    <td><?= (int)$r['id'] ?></td>
                    <td>
                        <?php if ($r['player_id']): ?>
                            <?= htmlspecialchars($r['username'] ?? '#' . $r['player_id']) ?>
                        <?php else: ?>
                            <span class="muted"><?= t('privacy.consents.anonymous') ?></span>
                        <?php endif ?>
                    </td>
                    <td><?= htmlspecialchars($r['consent_version']) ?></td>
                    <td>
                        <?php
                        $accepted = json_decode($r['accepted_categories_json'], true) ?? [];
                        echo htmlspecialchars(implode(', ', $accepted)) ?: '—';
                        ?>
                    </td>
                    <td><?= htmlspecialchars($r['source']) ?></td>
                    <td><?= htmlspecialchars($r['created_at']) ?></td>
                    <td><?= $r['withdrawn_at'] ? '✅ ' . htmlspecialchars($r['withdrawn_at']) : '—' ?></td>
                    <td>
                        <a href="?tab=consents&consent_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-secondary">
                            <?= t('privacy.consents.btn_details') ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="?tab=consents&page=<?= $p ?><?= http_build_query(array_filter($filters)) ? '&' . http_build_query(array_filter($filters)) : '' ?>"
               class="btn btn-sm <?= $page === $p ? 'btn-primary' : 'btn-secondary' ?>">
                <?= $p ?>
            </a>
        <?php endfor ?>
    </div>
    <?php endif ?>

    <p class="muted small">Łącznie zgód: <?= $total ?></p>
    <?php endif ?>
</div>
