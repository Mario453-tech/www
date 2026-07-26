<?php extract($viewData, EXTR_SKIP); ?>

<div class="admin-content">
    <div class="page-header">
        <h1><?= t('admin.hr.page_title') ?></h1>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif ?>
    <?php if ($err): ?>
    <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endif ?>

    <nav class="hr-tabs">
        <a href="?tab=candidates"      class="hr-tab <?= $tab === 'candidates'      ? 'active' : '' ?>"><?= t('admin.hr.tab_candidates') ?></a>
        <a href="?tab=history"         class="hr-tab <?= $tab === 'history'         ? 'active' : '' ?>"><?= t('admin.hr.tab_history') ?></a>
        <a href="?tab=stats"           class="hr-tab <?= $tab === 'stats'           ? 'active' : '' ?>"><?= t('admin.hr.tab_stats') ?></a>
        <a href="?tab=specializations" class="hr-tab <?= $tab === 'specializations' ? 'active' : '' ?>"><?= t('admin.hr.tab_specializations') ?></a>
        <a href="?tab=raises" class="hr-tab <?= $tab === 'raises' ? 'active' : '' ?>"><?= t('admin.hr.tab_raises') ?></a>
        <a href="?tab=tests" class="hr-tab <?= $tab === 'tests' ? 'active' : '' ?>"><?= t('admin.hr.tab_tests') ?></a>
    </nav>

    <?php if ($tab === 'candidates'): ?>
    <!--  KANDYDACI  -->
    <section class="hr-section">
        <div class="section-toolbar">
            <h2><?= t('admin.hr.section_candidates') ?> <span class="badge badge-inactive"><?= count($candidates) ?></span></h2>
            <form method="post" data-confirm-submit
                  data-confirm-message="<?= htmlspecialchars(tPlain('admin.hr.confirm_cleanup'), ENT_QUOTES, 'UTF-8') ?>"
                  data-confirm-label="<?= htmlspecialchars(tPlain('common.delete'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="cleanup_candidates" value="1">
                <button type="submit" class="btn btn-sm btn-danger"><?= t('admin.hr.btn_cleanup_candidates') ?></button>
            </form>
        </div>

        <?php if (empty($candidates)): ?>
        <p class="muted"><?= t('admin.hr.empty_candidates') ?></p>
        <?php else: ?>
        <div class="data-list hr-candidates-grid">
            <div class="list-header" role="row">
                <span><?= t('admin.hr.col_name') ?></span>
                <span><?= t('admin.hr.col_role') ?></span>
                <span><?= t('admin.hr.col_spec') ?></span>
                <span><?= t('admin.hr.col_region') ?></span>
                <span><?= t('admin.hr.col_player') ?></span>
                <span><?= t('admin.hr.col_skill_avg') ?></span>
                <span><?= t('admin.hr.col_expires') ?></span>
            </div>
            <?php foreach ($candidates as $c): ?>
            <article class="list-row" role="row">
                <span><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></span>
                <span><?= htmlspecialchars($c['role_name'] ?? '-') ?></span>
                <span>
                    <?php if ($c['spec_name']): ?>
                    <span class="badge badge-<?= $c['rarity'] === 'rare' ? 'active' : ($c['rarity'] === 'uncommon' ? 'paused' : 'inactive') ?>">
                        <?= htmlspecialchars($c['spec_name']) ?>
                    </span>
                    <?php else: ?>
                    <span class="muted">Brak specjalizacji</span>
                    <?php endif ?>
                </span>
                <span><?= htmlspecialchars($c['region_name'] ?? $c['region_code'] ?? '-') ?></span>
                <span><?= $c['player_email'] ? '<a href="/admin/player.php?id=' . (int)$c['player_id'] . '">' . htmlspecialchars($c['player_email']) . '</a>' : '<span class="muted">rynek</span>' ?></span>
                <span>
                    <?php
                    $skills = [$c['skill_organization'] ?? 0, $c['skill_negotiation'] ?? 0, $c['skill_analysis'] ?? 0, $c['skill_stress'] ?? 0, $c['skill_ethics'] ?? 0];
                    $avg = count($skills) ? round(array_sum($skills) / count($skills), 1) : 0;
                    ?>
                    <?= $avg ?>/10
                </span>
                <span class="<?= ($c['hours_remaining'] ?? 99) < 6 ? 'text-danger' : 'muted' ?>">
                    <?= (int)($c['hours_remaining'] ?? 0) ?>h
                </span>
            </article>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>

    <?php elseif ($tab === 'history'): ?>
    <!--  HISTORIA ZATRUDNIENIA  -->
    <section class="hr-section">
        <div class="section-toolbar">
            <h2><?= t('admin.hr.section_history') ?> <span class="badge badge-inactive"><?= number_format($histTotal) ?></span></h2>
        </div>

        <?php if (empty($history)): ?>
        <p class="muted"><?= t('admin.hr.empty_history') ?></p>
        <?php else: ?>
        <div class="data-list hr-history-grid">
            <div class="list-header" role="row">
                <span><?= t('admin.hr.col_date') ?></span>
                <span><?= t('admin.hr.col_player') ?></span>
                <span><?= t('admin.hr.col_name') ?></span>
                <span><?= t('admin.hr.col_role') ?></span>
                <span><?= t('admin.hr.col_action') ?></span>
                <span><?= t('admin.hr.col_reason') ?></span>
            </div>
            <?php foreach ($history as $h): ?>
            <article class="list-row" role="row">
                <span class="muted font-xs"><?= htmlspecialchars(substr($h['created_at'], 0, 16)) ?></span>
                <span>
                    <?php if ($h['player_email'] ?? null): ?>
                    <a href="/admin/player.php?id=<?= (int)($h['player_id'] ?? 0) ?>"><?= htmlspecialchars($h['player_email']) ?></a>
                    <?php else: ?>
                    <span class="muted">Brak adresu e-mail</span>
                    <?php endif ?>
                </span>
                <span><?= htmlspecialchars(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? '')) ?></span>
                <span><?= htmlspecialchars($h['role_name'] ?? 'Brak roli') ?></span>
                <span>
                    <span class="badge badge-<?= match($h['action'] ?? '') { 'hired' => 'active', 'fired' => 'bankrupt', 'resigned' => 'inactive', default => 'paused' } ?>">
                        <?= htmlspecialchars($h['action'] ?? 'Nieznana akcja') ?>
                    </span>
                </span>
                <span class="muted font-xs"><?= htmlspecialchars($h['reason'] ?? 'Brak powodu') ?></span>
            </article>
            <?php endforeach ?>
        </div>

        <?php endif ?>
        <?php if ($histPages > 1): ?>
        <nav class="pagination" aria-label="<?= t('admin.hr.pagination_label') ?>">
            <?php if ($histPage > 1): ?>
            <a href="?tab=history&hpage=<?= $histPage - 1 ?>" class="btn btn-sm"><?= t('common.prev') ?></a>
            <?php endif ?>
            <span class="muted"><?= t('admin.tick_log.page_of', ['page' => $histPage, 'total' => $histPages]) ?></span>
            <?php if ($histPage < $histPages): ?>
            <a href="?tab=history&hpage=<?= $histPage + 1 ?>" class="btn btn-sm"><?= t('common.next') ?></a>
            <?php endif ?>
        </nav>
        <?php endif ?>
    </section>

    <?php elseif ($tab === 'stats'): ?>
    <!--  STATYSTYKI HR GRACZY  -->
    <section class="hr-section">
        <div class="section-toolbar">
            <h2><?= t('admin.hr.section_stats') ?></h2>
        </div>

        <?php if (empty($stats)): ?>
        <p class="muted"><?= t('admin.hr.empty_stats') ?></p>
        <?php else: ?>
        <div class="data-list hr-stats-grid">
            <div class="list-header" role="row">
                <span><?= t('admin.hr.col_player') ?></span>
                <span><?= t('admin.hr.col_staff_count') ?></span>
                <span><?= t('admin.hr.col_avg_skill') ?></span>
                <span><?= t('admin.hr.col_active') ?></span>
                <span><?= t('admin.hr.col_busy') ?></span>
                <span><?= t('admin.hr.col_salary_hour') ?></span>
            </div>
            <?php foreach ($stats as $s): ?>
            <article class="list-row" role="row">
                <span><a href="/admin/player.php?id=<?= (int)$s['player_id'] ?>"><?= htmlspecialchars($s['player_email']) ?></a></span>
                <span><?= (int)$s['staff_count'] ?></span>
                <span><?= htmlspecialchars((string)($s['avg_skill'] ?? '-')) ?></span>
                <span class="badge badge-active"><?= (int)$s['active_count'] ?></span>
                <span class="badge badge-paused"><?= (int)$s['busy_count'] ?></span>
                <span class="muted"><?= number_format((float)$s['salary_per_hour'], 2) ?> PLN/h</span>
            </article>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>

    <?php elseif ($tab === 'raises'): ?>
    <section class="hr-section">
        <div class="section-toolbar">
            <div>
                <h2><?= t('admin.hr.section_raise_config') ?></h2>
                <p class="muted font-xs"><?= t('admin.hr.raise_config_desc') ?></p>
            </div>
        </div>
        <form method="post" class="hr-raise-config-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="save_raise_config" value="1">
            <div class="hr-raise-config-grid">
                <?php foreach ($raiseConfigDefinitions as $key => $definition): ?>
                <label class="hr-raise-config-field">
                    <span><?= t((string)$definition['label_key']) ?></span>
                    <input type="number"
                           name="raise_config[<?= htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') ?>]"
                           value="<?= htmlspecialchars((string)($raiseConfigValues[$key] ?? $definition['default']), ENT_QUOTES, 'UTF-8') ?>"
                           min="<?= (float)$definition['min'] ?>"
                           max="<?= (float)$definition['max'] ?>"
                           step="<?= (float)$definition['step'] ?>"
                           required>
                    <small><?= t((string)$definition['description_key']) ?></small>
                </label>
                <?php endforeach ?>
            </div>
            <button type="submit" class="btn btn-primary"><?= t('admin.hr.btn_save_raise_config') ?></button>
        </form>
    </section>
    <?php elseif ($tab === 'tests'): ?>
    <section class="hr-section">
        <div class="section-toolbar">
            <div>
                <h2><?= t('admin.hr.section_tests') ?></h2>
                <p class="muted font-xs"><?= t('admin.hr.tests_desc') ?></p>
            </div>
        </div>
        <form method="post" class="hr-test-strike-form" data-confirm-submit
              data-confirm-message="<?= htmlspecialchars(tPlain('admin.hr.confirm_test_strike'), ENT_QUOTES, 'UTF-8') ?>"
              data-confirm-label="<?= htmlspecialchars(tPlain('admin.hr.btn_force_test_strike'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="force_test_strike" value="1">
            <div class="hr-test-grid">
                <label class="hr-raise-config-field">
                    <span><?= t('admin.hr.field_test_strike_player') ?></span>
                    <input type="number" name="test_strike_player_id" min="1" step="1" required>
                    <small><?= t('admin.hr.field_test_strike_player_desc') ?></small>
                </label>
                <label class="hr-raise-config-field">
                    <span><?= t('admin.hr.field_test_strike_department') ?></span>
                    <select name="test_strike_department" required>
                        <?php foreach (($validDepartments ?? ['hr','technical','finance','legal','logistics']) as $department): ?>
                        <option value="<?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($department, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach ?>
                    </select>
                    <small><?= t('admin.hr.field_test_strike_department_desc') ?></small>
                </label>
            </div>
            <button type="submit" class="btn btn-danger"><?= t('admin.hr.btn_force_test_strike') ?></button>
        </form>

        <h3 class="spec-group-title"><?= t('admin.hr.section_test_targets') ?></h3>
        <?php if (empty($testStrikeTargets)): ?>
        <p class="muted"><?= t('admin.hr.empty_test_targets') ?></p>
        <?php else: ?>
        <div class="data-list hr-test-targets-grid">
            <div class="list-header" role="row">
                <span><?= t('admin.hr.col_player') ?></span>
                <span><?= t('admin.hr.col_department') ?></span>
                <span><?= t('admin.hr.col_staff_count') ?></span>
                <span><?= t('admin.hr.col_test_striking') ?></span>
                <span><?= t('admin.hr.col_action') ?></span>
            </div>
            <?php foreach ($testStrikeTargets as $target): ?>
            <article class="list-row" role="row">
                <span>
                    <a href="/admin/player.php?id=<?= (int)$target['player_id'] ?>">
                        <?= htmlspecialchars((string)($target['player_email'] ?? $target['player_id']), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </span>
                <span><?= htmlspecialchars((string)$target['department_code'], ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= (int)$target['employee_count'] ?></span>
                <span><?= (int)$target['striking_count'] ?></span>
                <span>
                    <form method="post" data-confirm-submit
                          data-confirm-message="<?= htmlspecialchars(tPlain('admin.hr.confirm_test_strike'), ENT_QUOTES, 'UTF-8') ?>"
                          data-confirm-label="<?= htmlspecialchars(tPlain('admin.hr.btn_force_test_strike'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="force_test_strike" value="1">
                        <input type="hidden" name="test_strike_player_id" value="<?= (int)$target['player_id'] ?>">
                        <input type="hidden" name="test_strike_department" value="<?= htmlspecialchars((string)$target['department_code'], ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-sm btn-danger"><?= t('admin.hr.btn_force_test_strike') ?></button>
                    </form>
                </span>
            </article>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>
    <?php elseif ($tab === 'specializations'): ?>
    <!--  SPECJALIZACJE  -->
    <section class="hr-section">
        <h2><?= t('admin.hr.section_staff_specs') ?></h2>
        <p class="muted font-xs"><?= t('admin.hr.staff_specs_desc') ?></p>

        <form method="post" class="add-spec-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="add_spec"   value="1">
            <div class="add-spec-fields">
                <div class="spec-field">
                    <label for="new_spec_code"><?= t('admin.hr.field_code') ?></label>
                    <input type="text" id="new_spec_code" name="new_spec_code" placeholder="np. geologist" pattern="[a-z0-9_]+" class="input-sm" required>
                </div>
                <div class="spec-field">
                    <label for="new_spec_name"><?= t('admin.hr.field_name_pl') ?></label>
                    <input type="text" id="new_spec_name" name="new_spec_name" placeholder="np. Geolog" class="input-sm" required>
                </div>
                <div class="spec-field">
                    <label for="new_spec_role"><?= t('admin.hr.field_role') ?></label>
                    <select id="new_spec_role" name="new_spec_role" class="input-sm">
                        <option value="operator">operator</option>
                        <option value="technician">technician</option>
                    </select>
                </div>
                <div class="spec-field">
                    <label for="new_spec_rarity"><?= t('admin.hr.col_rarity') ?></label>
                    <select id="new_spec_rarity" name="new_spec_rarity" class="input-sm">
                        <?php foreach (($validRarities ?? ['common','uncommon','rare','very_rare']) as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="spec-field spec-field--action">
                    <button type="submit" class="btn btn-sm btn-primary"><?= t('admin.hr.btn_add_spec') ?></button>
                </div>
            </div>
            <p class="muted font-xs"><?= t('admin.hr.add_spec_hint') ?></p>
        </form>

        <?php
        $roleLabels = [
            'operator' => t('admin.hr.role_operator'),
            'technician' => t('admin.hr.role_technician'),
            'inne' => t('admin.hr.role_other'),
        ];
        $numFields = [
            'prod_bonus'                => t('admin.hr.field_prod_bonus'),
            'wear_reduction'            => t('admin.hr.field_wear_reduction'),
            'incident_reduction'        => t('admin.hr.field_incident_reduction'),
            'spiral_reduction'          => t('admin.hr.field_spiral_reduction'),
            'repair_speed'              => t('admin.hr.field_repair_speed'),
            'incident_return_reduction' => t('admin.hr.field_incident_return'),
            'catastrophe_reduction'     => t('admin.hr.field_catastrophe_reduction'),
        ];
        foreach ($staffSpecs as $roleKey => $specs): ?>
        <div class="spec-group">
            <h3 class="spec-group-title"><?= htmlspecialchars($roleLabels[$roleKey] ?? $roleKey) ?> <span class="badge badge-inactive"><?= count($specs) ?></span></h3>
            <?php foreach ($specs as $spec): ?>
            <details class="spec-card">
                <summary class="spec-card-summary">
                    <strong><?= htmlspecialchars($spec['name'] ?? $spec['code']) ?></strong>
                    <span class="muted font-xs"><?= htmlspecialchars($spec['code']) ?></span>
                    <?php $rarityKey = 'hr.rarity.' . ($spec['rarity'] ?? 'common'); $rarityLabel = t($rarityKey); ?>
                    <span class="badge badge-<?= match($spec['rarity'] ?? 'common') { 'rare' => 'active', 'uncommon' => 'paused', default => 'inactive' } ?>"><?= $rarityLabel === $rarityKey ? htmlspecialchars($spec['rarity'] ?? 'common') : $rarityLabel ?></span>
                </summary>
                <form method="post" class="spec-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="save_spec"  value="1">
                    <input type="hidden" name="code"       value="<?= htmlspecialchars($spec['code']) ?>">
                    <div class="spec-fields">
                        <div class="spec-field spec-field--full">
                            <label for="spec_<?= $spec['code'] ?>_name"><?= t('admin.hr.field_name_pl') ?></label>
                            <input type="text" id="spec_<?= $spec['code'] ?>_name" name="spec_name" value="<?= htmlspecialchars($spec['name'] ?? '') ?>" class="input-sm" required>
                        </div>
                        <?php foreach ($numFields as $field => $label): ?>
                        <div class="spec-field">
                            <label for="spec_<?= $spec['code'] ?>_<?= $field ?>"><?= $label ?></label>
                            <input type="number" id="spec_<?= $spec['code'] ?>_<?= $field ?>" name="<?= $field ?>" value="<?= htmlspecialchars($spec[$field] ?? '0') ?>" step="0.001" min="0" max="1" class="input-sm">
                        </div>
                        <?php endforeach ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><?= t('common.save') ?></button>
                </form>
                <form method="post" id="del-spec-<?= htmlspecialchars($spec['code']) ?>" class="spec-delete-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="delete_spec" value="1">
                    <input type="hidden" name="code" value="<?= htmlspecialchars($spec['code']) ?>">
                    <button type="button" class="btn btn-sm btn-danger"
                        data-confirm-form="del-spec-<?= htmlspecialchars($spec['code'], ENT_QUOTES, 'UTF-8') ?>"
                        data-confirm-message="<?= htmlspecialchars(t("admin.hr.confirm_delete_spec") . " (" . $spec["code"] . ")?", ENT_QUOTES, 'UTF-8') ?>"
                        data-confirm-label="<?= htmlspecialchars(t("common.delete"), ENT_QUOTES, 'UTF-8') ?>">
                        <?= t('common.delete') ?>
                    </button>
                </form>
            </details>
            <?php endforeach ?>
        </div>
        <?php endforeach ?>

        <hr class="hr-divider">
        <h2><?= t('admin.hr.section_hr_specs') ?></h2>
        <p class="muted font-xs"><?= t('admin.hr.hr_specs_desc') ?></p>

        <form method="post" class="add-spec-form">
            <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="add_hr_spec" value="1">
            <div class="add-spec-fields">
                <div class="spec-field">
                    <label for="new_hr_code"><?= t('admin.hr.field_code') ?></label>
                    <input type="text" id="new_hr_code" name="new_hr_code" placeholder="<?= t('admin.hr.placeholder_hr_code') ?>" pattern="[a-z0-9_]+" class="input-sm" required>
                </div>
                <div class="spec-field">
                    <label for="new_hr_name"><?= t('admin.hr.field_name_pl') ?></label>
                    <input type="text" id="new_hr_name" name="new_hr_name" placeholder="<?= t('admin.hr.placeholder_hr_name') ?>" class="input-sm" required>
                </div>
                <div class="spec-field">
                    <label for="new_hr_dept"><?= t('admin.hr.col_department') ?></label>
                    <select id="new_hr_dept" name="new_hr_dept" class="input-sm" required>
                        <?php foreach (($validDepartments ?? ['hr','technical','finance','legal','logistics']) as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="spec-field">
                    <label for="new_hr_rarity"><?= t('admin.hr.col_rarity') ?></label>
                    <select id="new_hr_rarity" name="new_hr_rarity" class="input-sm">
                        <option value="common"><?= t('hr.rarity.common') ?></option>
                        <option value="uncommon"><?= t('hr.rarity.uncommon') ?></option>
                        <option value="rare"><?= t('hr.rarity.rare') ?></option>
                        <option value="very_rare">very_rare</option>
                    </select>
                </div>
                <div class="spec-field">
                    <label for="new_hr_salary_min">Min</label>
                    <input type="number" id="new_hr_salary_min" name="new_hr_salary_min" value="8000" min="100" step="100" class="input-sm">
                </div>
                <div class="spec-field">
                    <label for="new_hr_salary_max">Max</label>
                    <input type="number" id="new_hr_salary_max" name="new_hr_salary_max" value="15000" min="100" step="100" class="input-sm">
                </div>
                <div class="spec-field spec-field--action">
                    <button type="submit" class="btn btn-sm btn-primary"><?= t('admin.hr.btn_add_spec') ?></button>
                </div>
            </div>
        </form>

        <div class="data-list hr-hrspecs-grid">
            <div class="list-header" role="row">
                <span><?= t('admin.hr.col_spec_name') ?></span>
                <span><?= t('admin.hr.col_department') ?></span>
                <span><?= t('admin.hr.col_rarity') ?></span>
                <span>Salary</span>
                <span></span>
                <span></span>
            </div>
            <?php foreach ($hrSpecs as $deptKey => $deptSpecs): ?>
            <div class="spec-group-inline">
                <h4 class="spec-group-subtitle"> <?= htmlspecialchars($deptKey) ?> <span class="badge badge-inactive"><?= count($deptSpecs) ?></span></h4>
                <?php foreach ($deptSpecs as $hs): ?>
                <article class="list-row" role="row">
                    <form method="post" class="inline-form">
                        <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="save_hr_spec"  value="1">
                        <input type="hidden" name="hr_spec_id"    value="<?= (int)$hs['id'] ?>">
                        <span><input type="text" name="hr_spec_name" value="<?= htmlspecialchars($hs['name']) ?>" class="input-sm input-inline"></span>
                        <span>
                            <select name="hr_spec_dept" class="input-sm input-inline">
                                <?php foreach (($validDepartments ?? ['hr','technical','finance','legal','logistics']) as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= ($hs['department'] ?? '') === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach ?>
                            </select>
                        </span>
                        <span>
                            <select name="hr_spec_rarity" class="input-sm">
                                <?php foreach (['common' => t('hr.rarity.common'), 'uncommon' => t('hr.rarity.uncommon'), 'rare' => t('hr.rarity.rare'), 'very_rare' => 'very_rare'] as $r => $rl): ?>
                                <option value="<?= $r ?>" <?= ($hs['rarity'] ?? 'common') === $r ? 'selected' : '' ?>><?= $rl ?></option>
                                <?php endforeach ?>
                            </select>
                        </span>
                        <span>
                            <input type="number" name="hr_salary_min" value="<?= htmlspecialchars((string)($hs['base_salary_min'] ?? 0)) ?>" min="100" step="100" class="input-sm input-inline">
                            <input type="number" name="hr_salary_max" value="<?= htmlspecialchars((string)($hs['base_salary_max'] ?? 0)) ?>" min="100" step="100" class="input-sm input-inline">
                        </span>
                        <span><button type="submit" class="btn btn-sm btn-primary"><?= t('common.save') ?></button></span>
                    </form>
                    <form method="post" id="del-hs-<?= (int)$hs['id'] ?>" class="hidden">
                        <input type="hidden" name="csrf_token"     value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="delete_hr_spec" value="1">
                        <input type="hidden" name="hr_spec_id"     value="<?= (int)$hs['id'] ?>">
                    </form>
                    <span>
                        <button type="button" class="btn btn-sm btn-danger"
                            data-confirm-form="del-hs-<?= (int)$hs['id'] ?>"
                            data-confirm-message="<?= htmlspecialchars(t("admin.hr.confirm_delete_spec"), ENT_QUOTES, 'UTF-8') ?>"
                            data-confirm-label="<?= htmlspecialchars(t("common.delete"), ENT_QUOTES, 'UTF-8') ?>">
                            <?= t('common.delete') ?>
                        </button>
                    </span>
                </article>
                <?php endforeach ?>
            </div>
            <?php endforeach ?>
        </div>
    </section>
    <?php endif ?>
</div>
