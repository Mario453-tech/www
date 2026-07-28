<?php
extract($viewData, EXTR_SKIP);
$esc = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$label = static function (string $key, string $fallback): string {
    $translated = tPlain($key);
    return $translated === $key ? $fallback : $translated;
};
$tabs = [
    'dashboard' => 'admin.hr.tab_dashboard',
    'employees' => 'admin.hr.tab_employees',
    'roles' => 'admin.hr.tab_roles',
    'effects' => 'admin.hr.tab_effects',
    'assignments' => 'admin.hr.tab_assignments',
    'morale' => 'admin.hr.tab_morale',
    'raises' => 'admin.hr.tab_raises',
    'strikes' => 'admin.hr.tab_strikes',
    'settings' => 'admin.hr.tab_settings',
    'dialogues' => 'admin.hr.tab_dialogues',
    'logs' => 'admin.hr.tab_logs',
];
$departments = [
    'hr' => t('admin.hr.department.hr'),
    'technical' => t('admin.hr.department.technical'),
    'finance' => t('admin.hr.department.finance'),
    'legal' => t('admin.hr.department.legal'),
    'logistics' => t('admin.hr.department.logistics'),
];
$relationStatuses = [
    'normal' => t('admin.hr.status.normal'),
    'unhappy' => t('admin.hr.status.unhappy'),
    'raise_requested' => t('admin.hr.status.raise_requested'),
    'dispute' => t('admin.hr.status.dispute'),
    'strike_threat' => t('admin.hr.status.strike_threat'),
    'on_strike' => t('admin.hr.status.on_strike'),
    'leaving' => t('admin.hr.status.leaving'),
    'inactive' => t('admin.hr.status.inactive'),
];
?>
<div class="admin-content admin-hr">
    <div class="page-header">
        <h1><?= t('admin.hr.page_title') ?></h1>
    </div>

    <?php if ($msg !== ''): ?>
    <div class="alert alert-success admin-hr-flash"><?= $esc($msg) ?></div>
    <?php endif ?>
    <?php if ($err !== ''): ?>
    <div class="alert alert-error admin-hr-flash"><?= $esc($err) ?></div>
    <?php endif ?>

    <nav class="hr-tabs" aria-label="<?= $esc(tPlain('admin.hr.tabs_label')) ?>">
        <?php foreach ($tabs as $tabCode => $labelKey): ?>
        <a href="?tab=<?= $esc($tabCode) ?>" class="hr-tab<?= $tab === $tabCode ? ' active' : '' ?>">
            <?= t($labelKey) ?>
        </a>
        <?php endforeach ?>
    </nav>

    <?php require __DIR__ . '/' . $tab . '.php'; ?>
</div>
