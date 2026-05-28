<?php extract($viewData, EXTR_SKIP); ?>

<h1> <?= t('admin.newsletter.heading') ?></h1>
<p class="muted"><?= t('admin.newsletter.desc') ?></p>

<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>
<?php if ($err): ?>
<div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
<?php endif ?>

<div class="nl-stats">
    <span> <?= t('admin.newsletter.stats_total',        ['n' => (int)$statsRow['total']])        ?></span>
    <span> <?= t('admin.newsletter.stats_eligible',     ['n' => (int)$statsRow['eligible']])     ?></span>
    <span> <?= t('admin.newsletter.stats_unsubscribed', ['n' => (int)$statsRow['unsubscribed']]) ?></span>
</div>

<form method="post" id="nl-form" class="nl-compose">
    <?= CSRF::field() ?>
    <input type="hidden" name="action" id="nl-action" value="send">

    <div class="form-group">
        <label class="form-label" for="nl-subject"><?= t('admin.newsletter.label_subject') ?></label>
        <input type="text" id="nl-subject" name="subject" class="form-input"
               required maxlength="255"
               placeholder="<?= t('admin.newsletter.placeholder_subject') ?>"
               value="<?= htmlspecialchars($subject) ?>">
    </div>

    <div class="form-group">
        <label class="form-label"><?= t('admin.newsletter.label_recipients') ?></label>
        <div class="nl-target-radios">
            <label class="nl-radio-opt">
                <input type="radio" name="send_target" value="all" <?= $sendTarget !== 'single' ? 'checked' : '' ?>>
                <span> <?= t('admin.newsletter.target_all', ['n' => (int)$statsRow['eligible']]) ?></span>
            </label>
            <label class="nl-radio-opt">
                <input type="radio" name="send_target" value="single" <?= $sendTarget === 'single' ? 'checked' : '' ?>>
                <span> <?= t('admin.newsletter.target_single') ?></span>
            </label>
        </div>
        <div id="nl-single-wrap" class="nl-single-wrap"<?= $sendTarget !== 'single' ? ' style="display:none"' : '' ?>>
            <input type="email" id="nl-single-email" name="single_email" class="form-input"
                   placeholder="<?= t('admin.newsletter.placeholder_single_email') ?>"
                   value="<?= htmlspecialchars($singleEmail) ?>">
            <small class="muted"><?= t('admin.newsletter.single_email_note') ?></small>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label"><?= t('admin.newsletter.label_body') ?></label>
        <textarea id="nl-content" name="body_html"><?= htmlspecialchars($bodyHtml) ?></textarea>
    </div>

    <div class="nl-actions">
        <button type="button" id="nl-btn-preview" class="btn btn-secondary">
             <?= t('admin.newsletter.btn_preview') ?>
        </button>
        <button type="button" id="nl-send-btn" class="btn btn-danger">
             <?= t('admin.newsletter.btn_send', ['n' => (int)$statsRow['eligible']]) ?>
        </button>
    </div>
</form>

<?php if ($previewHtml && $action === 'preview'): ?>
<div class="nl-preview-wrap">
    <h2 class="nl-preview-heading"><?= t('admin.newsletter.preview_heading') ?></h2>
    <div class="nl-preview-frame">
        <iframe class="nl-preview-iframe" srcdoc="<?= htmlspecialchars($previewHtml) ?>"></iframe>
    </div>
    <p class="nl-preview-note"> <?= t('admin.newsletter.preview_note') ?></p>
</div>
<?php endif ?>

<div class="nl-history">
    <div class="nl-history-header">
        <h2><?= t('admin.newsletter.history_heading') ?> <span class="muted">(<?= count($history) ?>)</span></h2>
        <?php if (!empty($history)): ?>
        <form method="post" class="inline">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="clear_log">
            <button type="submit" class="btn btn-sm btn-danger"
                    onclick="confirmSubmit(this, <?= htmlspecialchars(json_encode(t('admin.newsletter.confirm_clear_log')), ENT_QUOTES) ?>, {type:'danger'}); return false;">
                 <?= t('admin.newsletter.btn_clear_log') ?>
            </button>
        </form>
        <?php endif ?>
    </div>

    <?php if (empty($history)): ?>
    <p class="empty-state"><?= t('admin.newsletter.history_empty') ?></p>
    <?php else: ?>
    <div class="data-list nl-log-grid">
        <div class="list-header" role="row">
            <span><?= t('admin.newsletter.col_date') ?></span>
            <span><?= t('admin.newsletter.col_subject') ?></span>
            <span><?= t('admin.newsletter.col_sent_to') ?></span>
            <span><?= t('admin.newsletter.col_by') ?></span>
            <span><?= t('admin.newsletter.col_status') ?></span>
            <span></span>
        </div>
        <?php
        $statusMap = [
            'sent'    => '<span class="badge badge-green">WYS£ANY</span>',
            'failed'  => '<span class="badge badge-red">B£¥D</span>',
            'partial' => '<span class="badge badge-yellow">CZÊŒCIOWY</span>',
        ];
        foreach ($history as $h):
        ?>
        <article class="list-row <?= $h['status'] === 'failed' ? 'row--error' : '' ?>" role="row">
            <time class="log-time"><?= htmlspecialchars(substr($h['sent_at'], 0, 16)) ?></time>
            <span class="nl-log-subject" title="<?= htmlspecialchars($h['subject']) ?>"><?= htmlspecialchars($h['subject']) ?></span>
            <span class="<?= (int)$h['sent_to'] > 0 ? 'text-green' : 'muted' ?>"><?= (int)$h['sent_to'] ?></span>
            <span class="muted font-sm"><?= htmlspecialchars($h['sent_by']) ?></span>
            <span>
                <?= $statusMap[$h['status']] ?? htmlspecialchars($h['status']) ?>
                <?php if ($h['notes']): ?>
                <small class="muted"> — <?= htmlspecialchars($h['notes']) ?></small>
                <?php endif ?>
            </span>
            <span>
                <form method="post" class="inline">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"  value="delete_log">
                    <input type="hidden" name="log_id"  value="<?= (int)$h['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-danger" title="Usuñ wpis"
                            onclick="confirmSubmit(this, <?= htmlspecialchars(json_encode(t('admin.newsletter.confirm_delete_log')), ENT_QUOTES) ?>, {type:'danger'}); return false;"></button>
                </form>
            </span>
        </article>
        <?php endforeach ?>
    </div>
    <?php endif ?>
</div>

<script>
window.NL_CONFIG = {
    countAll:      <?= (int)$statsRow['eligible'] ?>,
    confirmAll:    <?= json_encode(t('admin.newsletter.confirm_send'),        JSON_UNESCAPED_UNICODE) ?>,
    confirmSingle: <?= json_encode(t('admin.newsletter.confirm_send_single'), JSON_UNESCAPED_UNICODE) ?>,
    btnSendAll:    <?= json_encode(t('admin.newsletter.btn_send',        ['n' => (int)$statsRow['eligible']]), JSON_UNESCAPED_UNICODE) ?>,
    btnSendSingle: <?= json_encode(t('admin.newsletter.btn_send_single'), JSON_UNESCAPED_UNICODE) ?>,
    warnNoEmail:   <?= json_encode(t('admin.newsletter.err_single_email_invalid'), JSON_UNESCAPED_UNICODE) ?>,
};
</script>
<script src="https://cdn.tiny.cloud/1/n2m8igiixgfiasr4l4gha8fjz6hxp12sudqgnecovtt6y2nq/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/assets/js/newsletter_editor.js"></script>
