<div class="t-section">
    <h2 class="t-section-title"><?= t('technical.well_staff_title') ?></h2>
    <p class="t-section-sub"><?= t('technical.well_staff_desc') ?></p>

    <?php if (empty($wellsStaffStatus)): ?>
    <div class="msg-bar msg-warn"><?= t('technical.no_wells_warn') ?></div>
    <?php else: ?>

    <div class="ws-grid">
    <?php foreach ($wellsStaffStatus as $ws):
        $wsId      = $ws['well_id'];
        $hasOp     = $ws['has_operator'];
        $hasTe     = $ws['has_technician'];
        $warnClass = (!$hasOp || !$hasTe) ? 'ws-card--warn' : '';
    ?>
    <div class="ws-card <?= $warnClass ?>" id="ws-card-<?= $wsId ?>">
        <div class="ws-card-header">
            <div class="ws-well-name"><?= htmlspecialchars($ws['well_name']) ?></div>
            <div class="ws-status-badge ws-status-<?= htmlspecialchars($ws['status']) ?>">
                <?= match($ws['status']) {
                    'active'        => '&#10003; ' . t('technical.ws_active'),
                    'no_operator'   => '&#9888; ' . t('technical.ws_no_operator'),
                    'no_technician' => '&#128295; ' . t('technical.ws_no_technician'),
                    'paused_staff'  => '&#9208; ' . t('technical.ws_paused_staff'),
                    'paused_cash'   => '&#128184; ' . t('technical.ws_paused_cash'),
                    default         => htmlspecialchars($ws['status'])
                } ?>
            </div>
        </div>

        <div class="ws-role-row">
            <div class="ws-role-label">
                <div class="ws-role-label-inner">
                    <span class="ws-role-icon">&#128105;</span>
                    <span><?= t('technical.role_operator') ?></span>
                </div>
                <?php if (!$hasOp): ?>
                <span class="ws-role-req"><?= t('technical.role_required') ?></span>
                <?php endif ?>
            </div>
            <?php if ($hasOp): ?>
            <div class="ws-assigned">
                <span class="ws-person-name"><?= htmlspecialchars($ws['operator']['name']) ?></span>
                <span class="ws-skill-badge"><?= t('technical.skill_short') ?> <?= $ws['operator']['skill'] ?>/10</span>
                <button type="button" class="btn-ws-remove"
                    onclick="wsUnassign(<?= $wsId ?>, 'operator', this)">&times;</button>
            </div>
            <?php else: ?>
            <div class="ws-empty">
                <span class="ws-empty-label">&mdash; <?= t('technical.not_assigned') ?> &mdash;</span>
                <span class="ws-req-specs"><?= t('technical.req_specs_operator') ?></span>
                <button type="button" class="btn-ws-assign"
                    onclick="wsOpenAssign(<?= $wsId ?>, 'operator', '<?= htmlspecialchars($ws['well_name'], ENT_QUOTES) ?>')">
                    + <?= t('technical.btn_assign_role') ?>
                </button>
            </div>
            <?php endif ?>
        </div>

        <div class="ws-role-row">
            <div class="ws-role-label">
                <div class="ws-role-label-inner">
                    <span class="ws-role-icon">&#128295;</span>
                    <span><?= t('technical.role_technician') ?></span>
                </div>
                <?php if (!$hasTe): ?>
                <span class="ws-role-req ws-role-req--warn"><?= t('technical.role_degradation') ?></span>
                <?php endif ?>
            </div>
            <?php if ($hasTe): ?>
            <div class="ws-assigned">
                <span class="ws-person-name"><?= htmlspecialchars($ws['technician']['name']) ?></span>
                <span class="ws-skill-badge"><?= t('technical.skill_short') ?> <?= $ws['technician']['skill'] ?>/10</span>
                <button type="button" class="btn-ws-remove"
                    onclick="wsUnassign(<?= $wsId ?>, 'technician', this)">&times;</button>
            </div>
            <?php else: ?>
            <div class="ws-empty">
                <span class="ws-empty-label">&mdash; <?= t('technical.not_assigned') ?> &mdash;</span>
                <span class="ws-req-specs"><?= t('technical.req_specs_technician') ?></span>
                <button type="button" class="btn-ws-assign"
                    onclick="wsOpenAssign(<?= $wsId ?>, 'technician', '<?= htmlspecialchars($ws['well_name'], ENT_QUOTES) ?>')">
                    + <?= t('technical.btn_assign_role') ?>
                </button>
            </div>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>
    </div>

    <?php endif ?>
</div>

<div id="ws-modal" class="ws-modal-backdrop" onclick="if(event.target===this)wsCloseModal()">
    <div class="ws-modal">
        <div class="ws-modal-header">
            <div>
                <div class="ws-modal-title" id="ws-modal-title"><?= t('technical.ws_modal_title') ?></div>
                <div class="ws-modal-sub" id="ws-modal-sub"></div>
            </div>
            <button type="button" class="ws-modal-close" onclick="wsCloseModal()">&times;</button>
        </div>
        <div class="ws-modal-body" id="ws-modal-body">
            <div class="ws-loading"><?= t('technical.loading') ?>...</div>
        </div>
    </div>
</div>
