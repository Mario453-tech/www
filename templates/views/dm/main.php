<?php extract($viewData, EXTR_SKIP); ?>

<div class="dm-wrap">
    <div class="dm-layout card">
        <aside class="dm-sidebar">
            <div class="dm-sidebar-header">
                <span><?= t('dm.conversations') ?></span>
                <div class="dm-sidebar-actions">
                    <div class="dm-unread-pill" id="dmUnreadSidebar" hidden>0</div>
                    <div class="dm-sidebar-new">
                        <button class="btn btn-sm btn-secondary dm-new-btn" type="button" onclick="togglePlayerSelect()">
                            <?= t('dm.new_btn') ?>
                        </button>
                        <div class="dm-select-modal" id="dmPlayerSelect">
                            <?php foreach ($otherPlayers as $p): ?>
                            <div class="dm-select-item" onclick="startDm(<?= (int) $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">
                                <?= htmlspecialchars($p['name']) ?>
                            </div>
                            <?php endforeach ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dm-conv-list" id="dmConvList">
                <div class="dm-empty"><?= t('dm.loading') ?></div>
            </div>
        </aside>

        <main class="dm-main">
            <?php if ($withId): ?>
            <div class="dm-main-header">
                <div class="dm-main-header__identity">
                    <span class="dm-main-header__icon">&#9993;</span>
                    <span><?= htmlspecialchars($withName) ?></span>
                </div>
                <div class="dm-main-header__meta" id="dmHeaderMeta"></div>
            </div>

            <div class="dm-messages" id="dmMessages">
                <div class="chat-loading"><?= t('dm.loading') ?></div>
            </div>

            <div class="dm-composer">
                <div class="dm-attachment-preview" id="dmAttachmentPreview" hidden></div>
                <div class="dm-form">
                    <div class="dm-form-main">
                        <textarea
                            id="dmInput"
                            class="chat-input dm-input"
                            placeholder="<?= t('dm.msg_placeholder') ?>"
                            maxlength="500"
                            rows="1"
                            autocomplete="off"
                        ></textarea>
                        <div class="dm-toolbar">
                            <button type="button" class="dm-tool-btn" id="dmAttachBtn" title="<?= t('dm.attach_btn') ?>" onclick="document.getElementById('dmFileInput').click()">
                                &#128206;
                            </button>
                            <button type="button" class="dm-tool-btn" id="dmEmojiBtn" title="<?= t('dm.emoji_btn') ?>" onclick="toggleDmEmojiMenu()">
                                &#128515;
                            </button>
                            <button type="button" class="dm-tool-btn" id="dmSettingsBtn" title="<?= t('dm.more_btn') ?>" onclick="toggleDmSettingsMenu()">
                                &#8942;
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-primary dm-send-btn" type="button" onclick="dmSend()"><?= t('dm.send_btn') ?></button>
                </div>

                <input type="file" id="dmFileInput" class="visually-hidden" accept="image/jpeg,image/png,image/gif,image/webp" onchange="handleDmAttachment(this)">

                <div class="dm-popover dm-popover--emoji" id="dmEmojiMenu" hidden></div>
                <div class="dm-popover dm-popover--settings" id="dmSettingsMenu" hidden>
                    <div class="dm-popover__title"><?= t('dm.settings_title') ?></div>
                    <label class="dm-setting">
                        <input type="checkbox" id="dmEnterToggle">
                        <span>
                            <strong><?= t('dm.setting_enter_title') ?></strong>
                            <small><?= t('dm.setting_enter_desc') ?></small>
                        </span>
                    </label>
                    <label class="dm-setting">
                        <input type="checkbox" id="dmSoundToggle">
                        <span>
                            <strong><?= t('dm.setting_sound_title') ?></strong>
                            <small><?= t('dm.setting_sound_desc') ?></small>
                        </span>
                    </label>
                </div>
            </div>
            <?php else: ?>
            <div class="dm-empty dm-empty--centered"><?= t('dm.select_prompt') ?></div>
            <?php endif ?>
        </main>
    </div>
</div>

<script>
window.DM_CONFIG = {
    api: '/src/ChatApi.php',
    myId: <?= (int) $myId ?>,
    withId: <?= $withId ? (int) $withId : 'null' ?>,
    strings: <?= json_encode([
        'noConversations' => t('dm.no_conversations'),
        'loading' => t('dm.loading'),
        'attachmentPreview' => t('dm.attachment_preview_label'),
        'uploading' => t('dm.uploading'),
        'uploadProgress' => t('dm.upload_progress'),
        'reporting' => t('chat.report_success'),
        'deleteAttachment' => t('dm.delete_attachment_btn'),
        'deleteAttachmentConfirm' => t('dm.delete_attachment_confirm'),
        'attachmentDeleted' => t('dm.attachment_deleted'),
        'uploadInvalid' => t('dm.err_upload_invalid'),
        'uploadLimit' => t('dm.err_upload_limit'),
        'sendError' => t('common.app_error'),
        'imageAlt' => t('dm.attachment_image_alt'),
        'attachmentRemove' => t('dm.attachment_remove'),
    ], JSON_UNESCAPED_UNICODE) ?>
};
</script>
