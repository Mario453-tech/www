<?php
$activityChatReady = is_array($dashboardChatViewData ?? null);
$activityDefault = $activityChatReady ? 'chat' : 'messages';
$chatUnread = 0;
if ($activityChatReady) {
    foreach (array_merge($dashboardChatViewData['rooms'] ?? [], $dashboardChatViewData['directThreads'] ?? []) as $thread) {
        $chatUnread += (int)($thread['unread_count'] ?? 0);
    }
}
$activityAlerts = array_slice($alertWells ?? [], 0, 5);
?>
<section class="oe-activity" id="activity-center">
    <header class="oe-panel-head"><div><span class="oe-kicker"><?= t('home_dashboard.activity_kicker') ?></span><h2><?= t('home_dashboard.activity_title') ?></h2></div>
        <?php if ($activityChatReady): ?><span class="oe-online"><?= t('home_dashboard.online', ['count' => (int)($dashboardChatViewData['presenceData']['total_online'] ?? 0)]) ?></span><?php endif; ?>
    </header>
    <div class="oe-tabs" role="tablist" aria-label="<?= t('home_dashboard.activity_kicker') ?>" data-tab-set="activity">
        <?php foreach (['messages', 'alerts', 'news', 'chat'] as $tab): ?>
        <button id="oe-activity-tab-<?= $tab ?>" role="tab" type="button" aria-controls="oe-activity-panel-<?= $tab ?>"
                aria-selected="<?= $activityDefault === $tab ? 'true' : 'false' ?>" tabindex="<?= $activityDefault === $tab ? '0' : '-1' ?>">
            <?= t('home_dashboard.activity_' . $tab) ?>
            <?php $count = match($tab) { 'messages' => count($notifications ?? []), 'alerts' => count($alertWells ?? []) + count($techNotifications ?? []), 'chat' => $chatUnread, default => 0 }; ?>
            <?php if ($count > 0): ?><span class="oe-tab-count"><?= (int)$count ?></span><?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="oe-activity-panel" id="oe-activity-panel-messages" role="tabpanel" aria-labelledby="oe-activity-tab-messages"<?= $activityDefault === 'messages' ? '' : ' hidden' ?>>
        <?php if (empty($notifications)): ?><p class="oe-state"><?= t('home_dashboard.no_messages') ?></p><?php else: ?>
        <div id="director-notifications" data-csrf="<?= htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8') ?>">
            <span class="notifications-count visually-hidden"><?= count($notifications) ?></span>
            <?php if (count($notifications) > 1): ?><button type="button" class="btn-mark-all-read oe-mark-all" data-notifications-mark-all><?= t('director.btn_mark_all_read') ?></button><?php endif; ?>
            <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
            <article class="oe-activity-row notification-item" data-notification-id="<?= (int)$notification['id'] ?>">
                <div><h3><?= htmlspecialchars((string)$notification['title'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars((string)$notification['message'], ENT_QUOTES, 'UTF-8') ?></p></div>
                <time datetime="<?= htmlspecialchars((string)$notification['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= date('d.m H:i', strtotime((string)$notification['created_at'])) ?></time>
                <?php if (!empty($notification['action_url'])): ?><a href="<?= htmlspecialchars((string)$notification['action_url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)(!empty($notification['action_label']) ? $notification['action_label'] : tPlain('home_dashboard.view_details')), ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
                <button type="button" class="oe-mark-read" data-notification-mark-read="<?= (int)$notification['id'] ?>"><?= t('director.btn_mark_read') ?></button>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="oe-activity-panel" id="oe-activity-panel-alerts" role="tabpanel" aria-labelledby="oe-activity-tab-alerts" hidden>
        <?php if (!$activityAlerts && empty($techNotifications)): ?><p class="oe-state"><?= t('home_dashboard.no_alerts') ?></p><?php endif; ?>
        <?php foreach ($activityAlerts as $alertWell): ?>
        <article class="oe-activity-row"><div><h3><?= htmlspecialchars((string)(!empty($alertWell['well_name']) ? $alertWell['well_name'] : ($alertWell['location_name'] ?? ('#' . $alertWell['id']))), ENT_QUOTES, 'UTF-8') ?></h3><p><?= t('home_dashboard.condition') ?>: <?= round((float)($alertWell['_cond'] ?? 0)) ?>%</p></div><a href="<?= url('home') ?>#wg-card-<?= (int)$alertWell['id'] ?>"><?= t('home_dashboard.view_details') ?></a></article>
        <?php endforeach; ?>
        <?php foreach (array_slice($techNotifications ?? [], 0, max(0, 5 - count($activityAlerts))) as $technicalNotice): ?>
        <article class="oe-activity-row"><div><h3><?= t('nav.technical') ?></h3><p><?= htmlspecialchars((string)$technicalNotice['message'], ENT_QUOTES, 'UTF-8') ?></p></div><time datetime="<?= htmlspecialchars((string)$technicalNotice['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= date('d.m H:i', strtotime((string)$technicalNotice['created_at'])) ?></time><a href="<?= url('technical') ?>"><?= t('home_dashboard.view_details') ?></a></article>
        <?php endforeach; ?>
    </div>

    <div class="oe-activity-panel" id="oe-activity-panel-news" role="tabpanel" aria-labelledby="oe-activity-tab-news" hidden>
        <div class="news-list oe-news-grid" id="newsList" data-empty="<?= t('news.empty') ?>" data-error="<?= t('news.load_error') ?>"><p class="news-loading"><?= t('common.loading') ?></p></div>
    </div>

    <div class="oe-activity-panel oe-activity-panel--chat" id="oe-activity-panel-chat" role="tabpanel" aria-labelledby="oe-activity-tab-chat"<?= $activityDefault === 'chat' ? '' : ' hidden' ?>>
        <?php require __DIR__ . '/chat.php'; ?>
    </div>
</section>
