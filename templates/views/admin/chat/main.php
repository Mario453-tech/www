<?php
extract($viewData, EXTR_SKIP);
$defaultRoomTranslations = $defaultRoomTranslations ?? [
    ['locale' => 'pl', 'name' => '', 'description' => ''],
    ['locale' => 'en', 'name' => '', 'description' => ''],
    ['locale' => 'de', 'name' => '', 'description' => ''],
];
?>

<h1><?= t('admin.chat.title') ?></h1>

<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>
<?php if ($err): ?>
<div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
<?php endif ?>

<!-- Statystyki -->
<div class="admin-stats-row">
    <div class="admin-stat-card">
        <div class="admin-stat-label"><?= t('admin.chat.stats_total') ?></div>
        <div class="admin-stat-value"><?= number_format((int)($stats['total_msgs'] ?? 0)) ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label"><?= t('admin.chat.stats_players') ?></div>
        <div class="admin-stat-value"><?= (int)($stats['unique_players'] ?? 0) ?></div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-label"><?= t('admin.chat.stats_last') ?></div>
        <div class="admin-stat-value"><?= htmlspecialchars($stats['last_msg'] ?? '') ?></div>
    </div>
</div>

<?php if (!empty($topSenders)): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.chat.top_senders') ?></p>
    <div class="admin-top-list">
        <?php foreach ($topSenders as $s): ?>
        <div class="admin-top-item">
            <a href="?player=<?= urlencode($s['username']) ?>"><?= htmlspecialchars($s['username']) ?></a>
            <span class="badge"><?= (int)$s['cnt'] ?></span>
        </div>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<!-- Komunikat admina + wyczy czat -->
<div class="admin-row">
    <section class="panel">
        <p class="panel-title"><?= t('admin.chat.admin_msg_title') ?></p>
        <form method="post" id="admin-chat-message-form" class="admin-chat-message-form"
              data-error="<?= t('admin.chat.err_msg_empty_or_long') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="send_admin">
            <div class="form-group">
                <label for="admin-chat-message"><?= t('admin.chat.admin_msg_label') ?></label>
                <textarea id="admin-chat-message" name="admin_msg" rows="8" class="form-control"
                          aria-describedby="admin-chat-message-hint"><?= htmlspecialchars($adminDraft ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <p id="admin-chat-message-hint" class="muted"><?= t('admin.chat.editor_hint') ?></p>
                <p id="admin-chat-message-error" class="alert alert-error" role="alert" hidden></p>
            </div>
            <button type="submit" class="btn btn-primary"><?= t('admin.chat.admin_msg_send') ?></button>
        </form>
    </section>

    <section class="panel">
        <form method="post"
              data-confirm="<?= htmlspecialchars(t('admin.chat.delete_expired_confirm'), ENT_QUOTES) ?>"
              data-confirm-type="warning"
              data-confirm-title="<?= htmlspecialchars(t('admin.chat.delete_expired'), ENT_QUOTES) ?>"
              data-confirm-label="<?= htmlspecialchars(t('admin.chat.delete_expired'), ENT_QUOTES) ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="delete_expired">
            <button type="submit" class="btn btn-secondary"><?= t('admin.chat.delete_expired') ?></button>
        </form>
        <form method="post" class="mt-sm" data-confirm="<?= htmlspecialchars(t('admin.chat.clear_all'), ENT_QUOTES, 'UTF-8') ?>" data-confirm-type="danger">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="btn btn-danger">
                 <?= t('admin.chat.clear_all') ?>
            </button>
        </form>

        <!--  Auto-czyszczenie  -->
        <hr class="panel-divider mt-sm">
        <p class="panel-title mt-sm"><?= t('admin.chat.auto_clear_title') ?></p>

        <?php if ($autoClearLastAt): ?>
        <p class="muted font-xs mb-4"><?= t('admin.chat.last_cleared') ?>: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($autoClearLastAt))) ?></p>
        <?php endif ?>

        <form method="post" id="auto-clear-form"
              data-enabled-label="<?= htmlspecialchars(t('admin.chat.enabled'), ENT_QUOTES, 'UTF-8') ?>"
              data-disabled-label="<?= htmlspecialchars(t('admin.chat.disabled'), ENT_QUOTES, 'UTF-8') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_auto_clear">

            <div class="config-row">
                <div>
                    <div class="config-key-label"><?= t('admin.chat.auto_clear_label') ?></div>
                    <div class="config-key-code"><?= t('admin.chat.auto_clear_hint') ?></div>
                </div>
                <div class="config-row-value">
                    <label class="toggle-switch">
                        <input type="checkbox" name="auto_clear_enabled" value="1"
                               id="autoClearToggle"
                               <?= $autoClearEnabled ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <span id="autoClearStatus" class="badge ml-6 <?= $autoClearEnabled ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $autoClearEnabled ? t('admin.chat.enabled') : t('admin.chat.disabled') ?>
                    </span>
                </div>
            </div>

            <div class="config-row config-row--wide<?= !$autoClearEnabled ? ' is-disabled' : '' ?>" id="autoClearIntervalRow">
                <div>
                    <div class="config-key-label"><?= t('admin.chat.interval_label') ?></div>
                    <div class="config-key-code"><?= t('admin.chat.interval_hint') ?></div>
                </div>
                <div class="config-row-value flex-row-gap">
<?php
                    $intervalOpts = [
                        15  => t('admin.chat.interval_15m'),
                        30  => t('admin.chat.interval_30m'),
                        60  => t('admin.chat.interval_60m'),
                        90  => t('admin.chat.interval_90m'),
                        120 => t('admin.chat.interval_120m'),
                    ];
                    foreach ($intervalOpts as $val => $lbl): ?>
                    <label class="radio-pill <?= $autoClearInterval === $val ? 'radio-pill--active' : '' ?>">
                        <input type="radio" name="auto_clear_interval" value="<?= $val ?>"
                               <?= $autoClearInterval === $val ? 'checked' : '' ?>>
                        <?= $lbl ?>
                    </label>
                    <?php endforeach ?>
                </div>
            </div>

            <div class="form-row mt-4">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('admin.chat.save_settings') ?></button>
            </div>
        </form>
    </section>
</div>

<!-- Ban + aktywne blokady -->
<div class="admin-row">
    <section class="panel">
        <p class="panel-title"><?= t('admin.chat.ban_title') ?></p>
        <form method="post" class="form-inline"
              data-confirm="<?= t('admin.chat.ban_confirm') ?>"
              data-confirm-type="danger"
              data-confirm-title="<?= t('admin.chat.ban_submit') ?>"
              data-confirm-label="<?= t('admin.chat.ban_submit') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="ban_player">
            <div class="form-row">
                <select name="ban_player_id" required>
                    <option value=""><?= t('admin.chat.ban_player_label') ?></option>
                    <?php foreach ($playerList as $pl): ?>
                    <option value="<?= (int)$pl['id'] ?>"><?= htmlspecialchars($pl['username']) ?></option>
                    <?php endforeach ?>
                </select>
                <select name="ban_duration">
                    <?php foreach ($banDurations as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach ?>
                </select>
                <input type="text" name="ban_reason"
                       placeholder="<?= t('admin.chat.ban_reason_ph') ?>"
                       maxlength="255" class="form-control">
                <button type="submit" class="btn btn-danger"><?= t('admin.chat.ban_submit') ?></button>
            </div>
        </form>
    </section>

    <section class="panel">
        <p class="panel-title"><?= t('admin.chat.bans_title') ?> (<?= count($activeBans) ?>)</p>
        <?php if (empty($activeBans)): ?>
        <p class="muted"><?= t('admin.chat.ban_no_bans') ?></p>
        <?php else: ?>
        <div class="data-list">
            <div class="list-header">
                <span><?= t('admin.chat.ban_col_player') ?></span>
                <span><?= t('admin.chat.ban_col_reason') ?></span>
                <span><?= t('admin.chat.ban_col_by') ?></span>
                <span><?= t('admin.chat.ban_col_expires') ?></span>
                <span></span>
            </div>
            <?php foreach ($activeBans as $ban): ?>
            <article class="list-row">
                <span><strong><?= htmlspecialchars($ban['username']) ?></strong> <span class="muted">#<?= (int)$ban['player_id'] ?></span></span>
                <span><?= htmlspecialchars($ban['reason'] ?: '') ?></span>
                <span class="muted"><?= htmlspecialchars($ban['banned_by']) ?></span>
                <span class="muted<?= !$ban['expires_at'] ? ' text-danger' : '' ?>">
                    <?= $ban['expires_at'] ? date('d.m.Y H:i', strtotime($ban['expires_at'])) : t('admin.chat.ban_permanent') ?>
                </span>
                <span>
                    <form method="post"
                          data-confirm="<?= htmlspecialchars(t('admin.chat.unban_confirm'), ENT_QUOTES) ?>"
                          data-confirm-type="warning"
                          data-confirm-title="<?= htmlspecialchars(t('admin.chat.unban_submit'), ENT_QUOTES) ?>"
                          data-confirm-label="<?= htmlspecialchars(t('admin.chat.unban_submit'), ENT_QUOTES) ?>">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action"        value="unban_player">
                        <input type="hidden" name="ban_player_id" value="<?= (int)$ban['player_id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm"><?= t('admin.chat.unban_submit') ?></button>
                    </form>
                </span>
            </article>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>
</div>

<!-- Historia czatu -->
<section class="panel">
    <p class="panel-title"><?= t('admin.chat.history_title') ?></p>

    <form method="get" class="filter-form">
        <input type="text" name="player" value="<?= htmlspecialchars($filterPlayer) ?>"
               placeholder="<?= t('admin.chat.filter_placeholder') ?>">
        <button type="submit" class="btn btn-secondary"><?= t('admin.chat.filter_submit') ?></button>
        <?php if ($filterPlayer): ?>
        <a href="/admin/chat.php" class="btn btn-secondary"><?= t('admin.chat.filter_clear') ?></a>
        <?php endif ?>
    </form>

    <?php if (empty($messages)): ?>
    <p class="muted"><?= $filterPlayer ? t('admin.chat.no_messages_filter') : t('admin.chat.no_messages') ?></p>
    <?php else: ?>
    <div class="chat-list">
        <div class="chat-list-head">
            <span><?= t('admin.chat.col_id') ?></span>
            <span><?= t('admin.chat.col_time') ?></span>
            <span><?= t('admin.chat.col_player') ?></span>
            <span><?= t('admin.chat.col_message') ?></span>
            <span><?= t('admin.chat.col_actions') ?></span>
        </div>
        <?php foreach ($messages as $m):
            $isExpired = ($m['created_at'] < $expiredCutoff);
            $isAdmin   = (int)($m['is_admin']  ?? 0) === 1;
            $isPinned  = (int)($m['is_pinned'] ?? 0) === 1;
        ?>
        <div class="chat-list-row<?= $isAdmin ? ' chat-list-row--admin' : '' ?><?= $isExpired ? ' chat-list-row--expired' : '' ?>">
            <span class="muted"><?= (int)$m['id'] ?></span>
            <span class="muted">
                <?= htmlspecialchars($m['created_at']) ?>
                <?php if ($isExpired): ?><span class="badge badge-inactive" title="<?= htmlspecialchars(t('admin.chat.badge_expired_title'), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.chat.badge_expired') ?></span><?php endif ?>
                <?php if ($isAdmin): ?><span class="badge badge-admin" title="<?= htmlspecialchars(t('admin.chat.badge_admin_title'), ENT_QUOTES, 'UTF-8') ?>"><?= t('admin.chat.badge_admin') ?></span><?php endif ?>
                <?php if ($isPinned): ?><span class="badge badge-pinned" title="<?= htmlspecialchars(t('admin.chat.badge_pinned_title'), ENT_QUOTES, 'UTF-8') ?>"></span><?php endif ?>
            </span>
            <span class="chat-history-player">
                <span class="chat-history-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($m['username'], 0, 1))) ?></span>
                <strong><?= htmlspecialchars($m['username']) ?></strong>
                <?php if ($m['player_id'] > 0): ?>
                <span class="muted font-xs">#<?= (int)$m['player_id'] ?></span>
                <?php endif ?>
            </span>
            <div class="chat-msg-text"><?= $isAdmin ? ChatMessageHtml::sanitize($m['message']) : htmlspecialchars($m['message'], ENT_QUOTES, 'UTF-8') ?></div>
            <span class="chat-row-actions">
                <?php if ($isAdmin): ?>
                <?php if ($isPinned): ?>
                <form method="post">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="unpin_msg">
                    <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="btn btn-warning btn-sm" title="<?= t('admin.chat.unpin_title') ?>"> <?= t('admin.chat.unpin_btn') ?></button>
                </form>
                <?php else: ?>
                <form method="post">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="pin_msg">
                    <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm" title="<?= t('admin.chat.pin_title') ?>"> <?= t('admin.chat.pin_btn') ?></button>
                </form>
                <?php endif ?>
                <?php endif ?>
                <form method="post"
                      data-confirm="<?= htmlspecialchars(t('admin.chat.delete_msg_confirm'), ENT_QUOTES) ?>"
                      data-confirm-type="danger"
                      data-confirm-title="<?= htmlspecialchars(t('common.delete'), ENT_QUOTES) ?>"
                      data-confirm-label="<?= htmlspecialchars(t('common.delete'), ENT_QUOTES) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="delete_msg">
                    <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"></button>
                </form>
                <?php if ($m['player_id'] > 0): ?>
                <form method="post"
                      data-confirm="<?= htmlspecialchars(t('admin.chat.delete_all_confirm'), ENT_QUOTES) ?>"
                      data-confirm-type="danger"
                      data-confirm-title="<?= htmlspecialchars(t('admin.chat.delete_all_title'), ENT_QUOTES) ?>"
                      data-confirm-label="<?= htmlspecialchars(t('common.delete'), ENT_QUOTES) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"    value="delete_player_msgs">
                    <input type="hidden" name="player_id" value="<?= (int)$m['player_id'] ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"
                            title="<?= t('admin.chat.delete_all_title') ?>"></button>
                </form>
                <?php endif ?>
            </span>
        </div>
        <?php endforeach ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="pagination" aria-label="<?= htmlspecialchars(t('admin.chat.pagination_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?p=<?= $i ?><?= $filterPlayer ? '&player=' . urlencode($filterPlayer) : '' ?>"
           class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
        <?php endfor ?>
    </nav>
    <?php endif ?>
    <?php endif ?>
</section>

<?php if (!empty($reports)): ?>
<section class="panel">
    <p class="panel-title"><?= t('admin.chat.reports_title') ?> (<?= count($reports) ?>)</p>
    <div class="data-list">
        <?php foreach ($reports as $r): ?>
        <article class="list-row list-row--reports">
            <span class="muted font-xs">#<?= (int)$r['id'] ?></span>
            <span>
                <div class="report-meta"><?= t('admin.chat.report_by') ?> <b><?= htmlspecialchars($r['reporter_name'] ?? '?') ?></b> | <?= t('admin.chat.report_reason') ?> <b><?= htmlspecialchars($r['reason']) ?></b></div>
                <div class="report-msg"><?= htmlspecialchars($r['message'] ?? '') ?></div>
                <div class="report-author muted"><?= t('admin.chat.report_author') ?> <?= htmlspecialchars($r['msg_author'] ?? '?') ?></div>
            </span>
            <span>
                <form method="post">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="delete_msg">
                    <input type="hidden" name="msg_id" value="<?= (int)$r['message_id'] ?>">
                    <button class="btn btn-danger btn-sm"><?= t('admin.chat.delete_submit') ?></button>
                </form>
            </span>
            <span>
                <form method="post">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"    value="resolve_report">
                    <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-success btn-sm"><?= t('admin.chat.resolve_submit') ?></button>
                </form>
            </span>
            <span class="muted font-xs"><?= htmlspecialchars($r['created_at']) ?></span>
        </article>
        <?php endforeach ?>
    </div>
</section>
<?php endif ?>

<!-- Sowa blokowane -->
<details class="panel panel-collapsible">
    <summary class="panel-title panel-title--toggle">
        <?= t('admin.chat.words_title') ?>
        <span class="badge badge-active words-count-badge"><?= count($blockedWords) ?></span>
    </summary>

    <form method="post" class="word-add-form">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="add_blocked_word">
        <div class="form-row">
            <div class="form-group">
                <label><?= t('admin.chat.word_label') ?></label>
                <input type="text" name="word" maxlength="100" required
                       class="input-w-md"
                       placeholder="<?= t('admin.chat.word_placeholder') ?>">
            </div>
            <div class="form-group">
                <label><?= t('admin.chat.word_replacement_label') ?></label>
                <input type="text" name="replacement" maxlength="100"
                       class="input-w-xs" value="***">
            </div>
            <div class="form-group form-group--end">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('admin.chat.word_add_btn') ?></button>
            </div>
        </div>
    </form>

    <?php if (empty($blockedWords)): ?>
    <p class="muted mt-md"><?= t('admin.chat.words_empty') ?></p>
    <?php else:
        $activeWords   = array_filter($blockedWords, fn($w) => $w['active']);
        $inactiveWords = array_filter($blockedWords, fn($w) => !$w['active']);
    ?>

    <?php if (!empty($activeWords)): ?>
    <p class="words-group-label mt-md"><?= t('admin.chat.word_status_active') ?> (<?= count($activeWords) ?>)</p>
    <div class="word-tags">
        <?php foreach ($activeWords as $w): ?>
        <span class="word-tag">
            <span class="word-tag__text" title=" <?= htmlspecialchars($w['replacement']) ?>"><?= htmlspecialchars($w['word']) ?></span>
            <span class="word-tag__btns">
                <form method="post" class="word-tag__form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"  value="toggle_blocked_word">
                    <input type="hidden" name="word_id" value="<?= (int)$w['id'] ?>">
                    <button type="submit" class="word-tag__btn word-tag__btn--mute" title="<?= t('admin.chat.word_btn_disable') ?>"></button>
                </form>
                <form method="post" class="word-tag__form"
                      data-confirm="<?= htmlspecialchars(t('admin.chat.word_delete_confirm'), ENT_QUOTES) ?>"
                      data-confirm-type="danger"
                      data-confirm-title="<?= htmlspecialchars(t('common.delete'), ENT_QUOTES) ?>"
                      data-confirm-label="<?= htmlspecialchars(t('common.delete'), ENT_QUOTES) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"  value="delete_blocked_word">
                    <input type="hidden" name="word_id" value="<?= (int)$w['id'] ?>">
                    <button type="submit" class="word-tag__btn word-tag__btn--del" title="<?= t('common.delete') ?>"></button>
                </form>
            </span>
        </span>
        <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if (!empty($inactiveWords)): ?>
    <p class="words-group-label mt-md"><?= t('admin.chat.word_status_inactive') ?> (<?= count($inactiveWords) ?>)</p>
    <div class="word-tags word-tags--inactive">
        <?php foreach ($inactiveWords as $w): ?>
        <span class="word-tag word-tag--inactive">
            <span class="word-tag__text"><?= htmlspecialchars($w['word']) ?></span>
            <span class="word-tag__btns">
                <form method="post" class="word-tag__form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"  value="toggle_blocked_word">
                    <input type="hidden" name="word_id" value="<?= (int)$w['id'] ?>">
                    <button type="submit" class="word-tag__btn word-tag__btn--enable" title="<?= t('admin.chat.word_btn_enable') ?>"></button>
                </form>
                <form method="post" class="word-tag__form"
                      data-confirm="<?= htmlspecialchars(t('admin.chat.word_delete_confirm'), ENT_QUOTES) ?>"
                      data-confirm-type="danger"
                      data-confirm-title="<?= htmlspecialchars(t('common.delete'), ENT_QUOTES) ?>"
                      data-confirm-label="<?= htmlspecialchars(t('common.delete'), ENT_QUOTES) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"  value="delete_blocked_word">
                    <input type="hidden" name="word_id" value="<?= (int)$w['id'] ?>">
                    <button type="submit" class="word-tag__btn word-tag__btn--del" title="<?= t('common.delete') ?>"></button>
                </form>
            </span>
        </span>
        <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php endif ?>
</details>

<!-- Rooms Management / Zarzadzanie pokojami -->
<section class="panel mt-lg">
    <p class="panel-title"><?= t('admin.chat.rooms_title') ?></p>

    <!-- Rooms cards grid / Siatka kart pokoi -->
    <div class="admin-rooms-grid">
        <?php foreach (($allRooms ?? []) as $rm): ?>
        <div class="panel admin-room-card">
            <div class="admin-room-card-head">
                <strong class="admin-room-slug">#<?= htmlspecialchars($rm['slug']) ?></strong>
                <span class="badge"><?= htmlspecialchars($rm['status']) ?></span>
            </div>
            <div class="admin-room-title">
                <strong><?= htmlspecialchars($rm['name_pl']) ?></strong> / <?= htmlspecialchars($rm['name_en']) ?>
            </div>
            <p class="muted admin-room-desc">
                <?= htmlspecialchars($rm['description_pl'] ?? '') ?>
            </p>
            <div class="muted admin-room-meta">
                <span><?= t('admin.chat.room_messages_label') ?> <strong><?= (int)($rm['message_count'] ?? 0) ?></strong></span>
                <span><?= t('admin.chat.room_sort_order_label') ?> <strong><?= (int)$rm['sort_order'] ?></strong></span>
            </div>
            <?php if ($rm['status'] !== 'archived'): ?>
            <form method="post"
                  data-confirm="<?= htmlspecialchars(t('admin.chat.room_archive_confirm'), ENT_QUOTES, 'UTF-8') ?>"
                  data-confirm-type="danger"
                  data-confirm-title="<?= htmlspecialchars(t('admin.chat.room_archive_title'), ENT_QUOTES, 'UTF-8') ?>"
                  data-confirm-label="<?= htmlspecialchars(t('admin.chat.room_archive_btn'), ENT_QUOTES, 'UTF-8') ?>">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="archive_room">
                <input type="hidden" name="room_id" value="<?= (int)$rm['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"><?= t('admin.chat.room_archive_btn') ?></button>
            </form>
            <?php endif ?>
            <details class="admin-card-details">
                <summary><?= t('admin.chat.room_edit') ?></summary>
                <form method="post" class="admin-create-room-details">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="update_room">
                    <input type="hidden" name="room_id" value="<?= (int) $rm['id'] ?>">
                    <div class="admin-room-translations" data-translation-list data-next-index="<?= count($rm['translations']) ?>">
                        <?php foreach ($rm['translations'] as $translationIndex => $translation): ?>
                        <div class="admin-room-translation" data-translation-row>
                            <label class="form-label"><?= t('admin.chat.translation_locale') ?><input class="form-control" name="translations[<?= $translationIndex ?>][locale]" maxlength="20" pattern="[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*" required value="<?= htmlspecialchars($translation['locale'], ENT_QUOTES, 'UTF-8') ?>"></label>
                            <label class="form-label"><?= t('admin.chat.translation_name') ?><input class="form-control" name="translations[<?= $translationIndex ?>][name]" maxlength="100" required value="<?= htmlspecialchars($translation['name'], ENT_QUOTES, 'UTF-8') ?>"></label>
                            <label class="form-label"><?= t('admin.chat.translation_description') ?><textarea class="form-control" name="translations[<?= $translationIndex ?>][description]" maxlength="255"><?= htmlspecialchars($translation['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></label>
                            <button type="button" class="btn btn-sm btn-danger" data-remove-translation><?= t('admin.chat.translation_remove') ?></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-add-translation><?= t('admin.chat.translation_add') ?></button>
                    <label class="form-label"><?= t('admin.chat.room_locale_label') ?><input class="form-control" name="locale_code" maxlength="20" pattern="[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*" value="<?= htmlspecialchars($rm['locale_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
                    <label class="form-label"><?= t('admin.chat.room_type_label') ?><select name="type" class="form-control">
                        <?php foreach (['language', 'custom', 'system'] as $roomType): ?><option value="<?= $roomType ?>" <?= $rm['type'] === $roomType ? 'selected' : '' ?>><?= t('admin.chat.room_type_' . $roomType) ?></option><?php endforeach; ?>
                    </select></label>
                    <label class="form-label"><?= t('admin.chat.room_sort_label') ?><input class="form-control" type="number" name="sort_order" min="0" max="9999" value="<?= (int) $rm['sort_order'] ?>"></label>
                    <label class="form-label"><?= t('admin.chat.room_status_label') ?><select name="status" class="form-control">
                        <?php foreach (['active', 'read_only', 'archived'] as $roomStatus): ?><option value="<?= $roomStatus ?>" <?= $rm['status'] === $roomStatus ? 'selected' : '' ?>><?= t('admin.chat.room_status_' . $roomStatus) ?></option><?php endforeach; ?>
                    </select></label>
                    <button type="submit" class="btn btn-primary"><?= t('admin.chat.room_update') ?></button>
                </form>
            </details>
        </div>
        <?php endforeach ?>
    </div>

    <!-- Create Room Form / Formularz tworzenia pokoju -->
    <details class="admin-card-details admin-create-room-details">
        <summary class="btn btn-secondary btn-sm">+ <?= t('admin.chat.room_create_btn') ?></summary>
        <form method="post" class="admin-create-room-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="create_room">
            <div class="admin-create-room-row">
                <div class="admin-create-room-col">
                    <label class="form-label"><?= t('admin.chat.room_slug_label') ?></label>
                    <input type="text" name="slug" class="form-control" placeholder="<?= htmlspecialchars(t('admin.chat.room_slug_ph'), ENT_QUOTES, 'UTF-8') ?>" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                </div>
                <div class="admin-create-room-col">
                    <label class="form-label"><?= t('admin.chat.room_type_label') ?></label>
                    <select name="type" class="form-control">
                        <option value="language"><?= t('admin.chat.room_type_language') ?></option>
                        <option value="custom" selected><?= t('admin.chat.room_type_custom') ?></option>
                        <option value="system"><?= t('admin.chat.room_type_system') ?></option>
                    </select>
                </div>
                <div class="admin-create-room-col">
                    <label class="form-label"><?= t('admin.chat.room_locale_label') ?></label>
                    <input type="text" name="locale_code" class="form-control" placeholder="<?= htmlspecialchars(t('admin.chat.room_locale_ph'), ENT_QUOTES, 'UTF-8') ?>" maxlength="20" pattern="[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*">
                </div>
            </div>
            <div class="admin-room-translations" data-translation-list data-next-index="<?= count($defaultRoomTranslations) ?>">
                <?php foreach ($defaultRoomTranslations as $translationIndex => $translation): ?>
                <div class="admin-room-translation" data-translation-row>
                    <label class="form-label"><?= t('admin.chat.translation_locale') ?><input class="form-control" name="translations[<?= $translationIndex ?>][locale]" maxlength="20" pattern="[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*" required value="<?= htmlspecialchars($translation['locale'], ENT_QUOTES, 'UTF-8') ?>"></label>
                    <label class="form-label"><?= t('admin.chat.translation_name') ?><input class="form-control" name="translations[<?= $translationIndex ?>][name]" maxlength="100" required></label>
                    <label class="form-label"><?= t('admin.chat.translation_description') ?><textarea class="form-control" name="translations[<?= $translationIndex ?>][description]" maxlength="255"></textarea></label>
                    <button type="button" class="btn btn-sm btn-danger" data-remove-translation><?= t('admin.chat.translation_remove') ?></button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" data-add-translation><?= t('admin.chat.translation_add') ?></button>
            <div class="admin-create-room-row">
                <div class="admin-create-room-col">
                    <label class="form-label"><?= t('admin.chat.room_sort_label') ?></label>
                    <input type="number" name="sort_order" class="form-control" value="50">
                </div>
                <div class="admin-create-room-col">
                    <label class="form-label"><?= t('admin.chat.room_status_label') ?></label>
                    <select name="status" class="form-control">
                        <option value="active"><?= t('admin.chat.room_status_active') ?></option>
                        <option value="read_only"><?= t('admin.chat.room_status_read_only') ?></option>
                    </select>
                </div>
            </div>
            <div>
                <button type="submit" class="btn btn-primary"><?= t('admin.chat.room_save_btn') ?></button>
            </div>
        </form>
    </details>
    <template id="chat-room-translation-template">
        <div class="admin-room-translation" data-translation-row>
            <label class="form-label"><?= t('admin.chat.translation_locale') ?><input class="form-control" name="translations[__INDEX__][locale]" maxlength="20" pattern="[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*" required placeholder="ja"></label>
            <label class="form-label"><?= t('admin.chat.translation_name') ?><input class="form-control" name="translations[__INDEX__][name]" maxlength="100" required></label>
            <label class="form-label"><?= t('admin.chat.translation_description') ?><textarea class="form-control" name="translations[__INDEX__][description]" maxlength="255"></textarea></label>
            <button type="button" class="btn btn-sm btn-danger" data-remove-translation><?= t('admin.chat.translation_remove') ?></button>
        </div>
    </template>
</section>

<!-- Moderation Audit Log / Dziennik audytu moderacji -->
<?php if (!empty($moderationLog)): ?>
<section class="panel mt-lg">
    <p class="panel-title"><?= t('admin.chat.audit_title') ?></p>
    <div class="admin-audit-list">
        <?php foreach ($moderationLog as $log): ?>
        <div class="admin-audit-entry">
            <span class="muted admin-audit-date"><?= htmlspecialchars($log['created_at']) ?></span> &mdash;
            <strong><?= htmlspecialchars($log['actor_name']) ?></strong>:
            <code><?= htmlspecialchars($log['action']) ?></code>
            <?= t('admin.chat.audit_on') ?> <em><?= htmlspecialchars($log['target_type']) ?> #<?= (int)$log['target_id'] ?></em>
            <?php if (!empty($log['reason'])): ?>
                &mdash; <span class="muted"><?= htmlspecialchars($log['reason']) ?></span>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    </div>
    <?php if ($auditTotalPages > 1): ?>
    <nav class="pagination" aria-label="<?= htmlspecialchars(t('admin.chat.pagination_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($auditPage > 1): ?><a class="btn btn-secondary btn-sm" href="?audit_page=<?= $auditPage - 1 ?>"><?= t('common.prev') ?></a><?php endif ?>
        <span><?= $auditPage ?> / <?= $auditTotalPages ?></span>
        <?php if ($auditPage < $auditTotalPages): ?><a class="btn btn-secondary btn-sm" href="?audit_page=<?= $auditPage + 1 ?>"><?= t('common.next') ?></a><?php endif ?>
    </nav>
    <?php endif ?>
</section>
<?php endif ?>
