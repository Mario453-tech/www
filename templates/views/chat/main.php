<?php
declare(strict_types=1);

// Main unified chat view (3-column desktop layout, mobile drawer).
// Glowny widok zunifikowanego czatu (uklad 3-kolumnowy na desktopie, drawer mobilny).

extract($viewData, EXTR_SKIP);

$activeRoomSlug = (string) ($activeRoom['slug'] ?? 'polski');
$activeRoomName = (string) ($activeRoom['name'] ?? 'Polski');
$activeRoomId   = (int) ($activeRoom['id'] ?? 1);
$totalOnline    = (int) ($presenceData['total_online'] ?? 0);
?>

<div class="chat-container">
    <?php if ($flash !== ''): ?><p role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <!-- Page Header bar matching vector mockup -->
    <!-- Pasek naglowka strony zgodny z makieta wektorowa -->
    <header class="chat-header-bar">
        <div class="chat-header-info">
            <span class="chat-kicker"><?= t('chat.kicker') ?></span>
            <h1 class="chat-title"><?= t('chat.title') ?></h1>
            <p class="chat-subtitle"><?= t('chat.subtitle') ?></p>
        </div>
        <div class="chat-header-status">
            <span class="chat-presence-online" id="chatGlobalOnline">
                <span class="presence-dot presence-dot--online"></span>
                <span id="chatGlobalOnlineCount"><?= $totalOnline ?></span> <?= t('chat.online') ?>
            </span>
        </div>
    </header>

    <!-- Mobile Navigation Switcher for narrow screens (< 1024px) -->
    <!-- Przelacznik nawigacji mobilnej dla waskich ekranow (< 1024px) -->
    <div class="chat-mobile-nav" aria-label="<?= t('chat.page_title') ?>">
        <button type="button" class="chat-mob-btn" id="chatMobRoomsBtn" aria-haspopup="dialog" aria-controls="chatDrawer">
            <?= t('chat.btn_rooms') ?>
        </button>
        <button type="button" class="chat-mob-btn" id="chatMobDirectBtn" aria-haspopup="dialog" aria-controls="chatDrawer">
            <?= t('chat.btn_private') ?>
            <span class="chat-badge-count chat-badge-count--hidden" id="chatMobDirectUnread">0</span>
        </button>
        <button type="button" class="chat-mob-btn" id="chatMobActiveBtn" aria-haspopup="dialog" aria-controls="chatDrawer">
            <?= t('chat.btn_active') ?>
            <span class="chat-badge-count" id="chatMobOnlineBadge"><?= $totalOnline ?></span>
        </button>
    </div>

    <!-- Main 3-column Shell -->
    <!-- Glowny panel 3-kolumnowy -->
    <div class="chat-shell" id="chatShell">
        <!-- LEFT COLUMN: Rooms & Private Threads -->
        <!-- LEWA KOLUMNA: Pokoje i watki prywatne -->
        <aside class="chat-col chat-col--left" id="chatColLeft">
            <div class="chat-col-inner">
                <!-- POKOJE section -->
                <div class="chat-section-header">
                    <span class="chat-section-title"><?= t('chat.rooms') ?></span>
                    <span class="chat-section-count" id="chatRoomsCount"><?= count($rooms) ?></span>
                </div>
                <nav class="chat-rooms-list" id="chatRoomsList" aria-label="<?= t('chat.rooms') ?>">
                    <?php foreach ($rooms as $r): 
                        $isActive = ($r['slug'] === $activeRoomSlug && !$withPartnerId);
                        $itemCls = 'chat-room-item' . ($isActive ? ' chat-room-item--active' : '');
                        $badgeCount = (int) ($r['unread_count'] ?? 0);
                        $subtitle = $r['message_count'] > 0 
                            ? t('chat.messages_count', ['count' => $r['message_count']])
                            : t('chat.participants_count', ['count' => $r['member_count']]);
                    ?>
                    <button type="button"
                            class="<?= $itemCls ?>"
                            data-room-id="<?= (int) $r['id'] ?>"
                            data-room-slug="<?= htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8') ?>"
                            data-room-name="<?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-member-count="<?= (int) $r['member_count'] ?>">
                        <span class="room-indicator-dot"></span>
                        <div class="room-item-text">
                            <span class="room-item-name"><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="room-item-sub"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <span class="room-unread-dot <?= $badgeCount > 0 ? '' : 'room-unread-dot--hidden' ?>" title="<?= htmlspecialchars(t('chat.unread_badge_title', ['count' => $badgeCount]), ENT_QUOTES, 'UTF-8') ?>"></span>
                    </button>
                    <?php endforeach; ?>
                </nav>

                <div class="chat-divider"></div>

                <!-- PRYWATNE section -->
                <div class="chat-section-header">
                    <span class="chat-section-title"><?= t('chat.private') ?></span>
                </div>
                <div class="chat-direct-list" id="chatDirectList" aria-label="<?= t('chat.private') ?>">
                    <?php if (empty($directThreads)): ?>
                        <div class="chat-empty-threads"><?= t('chat.empty_messages') ?></div>
                    <?php else: ?>
                        <?php foreach ($directThreads as $t): 
                            $isThreadActive = ($withPartnerId && (int)$withPartnerId === (int)$t['partner_id']);
                            $thCls = 'chat-thread-item' . ($isThreadActive ? ' chat-thread-item--active' : '');
                            $unread = (int) ($t['unread_count'] ?? 0);
                        ?>
                        <button type="button"
                                class="<?= $thCls ?>"
                                data-partner-id="<?= (int) $t['partner_id'] ?>"
                                data-partner-name="<?= htmlspecialchars($t['partner_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="presence-dot <?= $t['is_online'] ? 'presence-dot--online' : 'presence-dot--offline' ?>"></span>
                            <div class="thread-item-text">
                                <span class="thread-item-name"><?= htmlspecialchars($t['partner_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="thread-item-sub"><?= htmlspecialchars($t['last_message_formatted'] ?: $t['last_message'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php if ($unread > 0): ?>
                            <span class="thread-badge-gold"><?= $unread ?></span>
                            <?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Admin info box at bottom of left column -->
                <button type="button" id="chatMoreThreads" class="chat-history-button" <?= count($directThreads) < 50 ? 'hidden' : '' ?>><?= t('chat.more_threads') ?></button>
                <div class="chat-admin-box">
                    <span class="chat-admin-box__label"><?= t('chat.admin_box_title') ?></span>
                    <p class="chat-admin-box__desc"><?= t('chat.admin_box_desc') ?></p>
                </div>
            </div>
        </aside>

        <!-- CENTER COLUMN: Active Room / Direct Conversation & Message Timeline -->
        <!-- SRODKOWA KOLUMNA: Aktywny pokoj / rozmowa prywatna i os czasu wiadomosci -->
        <main class="chat-col chat-col--center" id="chatColCenter">
            <!-- Center Header -->
            <div class="chat-conversation-header" id="chatConvHeader">
                <div class="chat-conv-identity">
                    <h2 class="chat-conv-title" id="chatConvTitle">
                        <?= $withPartnerId ? htmlspecialchars($withPartnerName) : '# ' . htmlspecialchars($activeRoomName) ?>
                    </h2>
                    <p class="chat-conv-desc" id="chatConvDesc">
                        <?= $withPartnerId ? t('chat.private') : t('chat.room_lang_desc', ['count' => (int)($activeRoom['member_count'] ?? 0)]) ?>
                    </p>
                </div>
                <div class="chat-conv-meta" id="chatConvMeta">
                    <span class="chat-conv-last-msg" id="chatConvLastMsg"></span>
                </div>
            </div>

            <!-- Messages Timeline Area -->
            <button type="button" id="chatOlder" class="chat-history-button" hidden><?= t('chat.load_older') ?></button>
            <section class="chat-messages-area" id="chatMessagesArea" role="log" aria-live="polite" aria-label="<?= t('chat.title') ?>">
                <?php foreach ($history as $message): ?>
                <article class="chat-msg-row <?= $message['sender_id'] === $playerId ? 'chat-msg-row--mine' : 'chat-msg-row--other' ?>">
                    <div class="chat-msg-body-wrapper">
                        <header class="chat-msg-header"><span><?= htmlspecialchars($message['sender_name'], ENT_QUOTES, 'UTF-8') ?></span> <time><?= htmlspecialchars($message['time'], ENT_QUOTES, 'UTF-8') ?></time></header>
                        <div class="chat-msg-bubble"><?= htmlspecialchars($message['message'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </article>
                <?php endforeach; ?>
            </section>
            <button type="button" id="chatNewMessages" class="chat-history-button" hidden><?= t('chat.new_messages') ?></button>
            <p id="chatReadOnly" <?= ($activeRoom['status'] ?? '') === 'read_only' && !$withPartnerId ? '' : 'hidden' ?>><?= t('chat.read_only_room') ?></p>
            <noscript>
                <?php if (count($history) === 50): ?>
                <a href="?<?= htmlspecialchars(http_build_query(['room' => $activeRoomSlug, 'with' => $withPartnerId, 'before_id' => $history[0]['id']]), ENT_QUOTES, 'UTF-8') ?>"><?= t('chat.load_older') ?></a>
                <?php endif; ?>
            </noscript>

            <!-- Bottom Floating / Sticky Input Bar -->
            <form class="chat-composer" id="chatComposer" method="post" action="/chat" autocomplete="off" <?= ($activeRoom['status'] ?? '') === 'read_only' && !$withPartnerId ? 'hidden' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="room_slug" value="<?= htmlspecialchars($activeRoomSlug, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="partner_id" value="<?= (int) $withPartnerId ?>">
                <label for="chatMsgInput" class="chat-input-label"><?= t('chat.input_aria') ?></label>
                <div class="chat-composer-row">
                    <textarea name="message" rows="2"
                           id="chatMsgInput"
                           class="chat-composer-input"
                           maxlength="1000"
                           placeholder="<?= $withPartnerId ? t('chat.placeholder_direct', ['player' => $withPartnerName]) : t('chat.placeholder_room', ['room' => $activeRoomName]) ?>"
                           required></textarea>
                    <button type="submit" class="chat-composer-btn" id="chatSendBtn">
                        <?= t('chat.send') ?>
                    </button>
                </div>
            </form>
        </main>

        <!-- RIGHT COLUMN: Active Players & Information -->
        <!-- PRAWA KOLUMNA: Aktywni gracze i informacje -->
        <aside class="chat-col chat-col--right" id="chatColRight">
            <div class="chat-col-inner">
                <div class="chat-section-header">
                    <span class="chat-section-title"><?= t('chat.active_players') ?></span>
                    <span class="chat-status-green" id="chatRightOnlineCount"><?= t('chat.online_count', ['count' => $totalOnline]) ?></span>
                </div>
                <div class="chat-players-list" id="chatPlayersList" aria-label="<?= t('chat.active_players') ?>">
                    <?php foreach ($presenceData['players'] as $p): 
                        $dotClass = $p['is_online'] ? 'presence-dot--online' : 'presence-dot--offline';
                        $sub = $p['is_online'] ? htmlspecialchars($p['room_name'], ENT_QUOTES, 'UTF-8') : t('chat.offline');
                    ?>
                    <div class="chat-player-item" data-player-id="<?= (int) $p['id'] ?>">
                        <span class="presence-dot <?= $dotClass ?>"></span>
                        <div class="player-item-text">
                            <span class="player-item-name"><?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="player-item-sub"><?= $sub ?></span>
                        </div>
                        <?php if ((int)$p['id'] !== (int)$playerId): ?>
                        <button type="button"
                                class="player-direct-btn"
                                data-player-id="<?= (int) $p['id'] ?>"
                                data-player-name="<?= htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') ?>"
                                aria-label="<?= t('chat.dm_action_label', ['player' => $p['name']]) ?>"
                                title="<?= t('chat.dm_action_label', ['player' => $p['name']]) ?>">
                            &rarr;
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="chat-divider"></div>

                <div class="chat-info-box">
                    <span class="chat-section-title"><?= t('chat.info_title') ?></span>
                    <p class="chat-info-desc"><?= t('chat.info_desc') ?></p>
                </div>
            </div>
        </aside>
    </div>
    <dialog id="chatDrawer" class="chat-drawer" aria-labelledby="chatDrawerTitle">
        <header class="chat-drawer-heading"><h2 id="chatDrawerTitle"><?= t('chat.rooms') ?></h2>
            <button type="button" id="chatDrawerClose" aria-label="<?= t('chat.close') ?>">&#215;</button>
        </header>
    </dialog>
</div>

<div id="chatConfig" hidden data-config="<?= htmlspecialchars(json_encode([
    'api' => '/src/ChatApi.php',
    'playerId' => (int) $playerId,
    'csrfToken' => CSRF::generateToken(),
    'locale' => (string) $locale,
    'activeRoomSlug' => $activeRoomSlug,
    'activeRoomId' => $activeRoomId,
    'activeRoomName' => $activeRoomName,
    'isAdmin' => false,
    'withPartnerId' => $withPartnerId,
    'withPartnerName' => $withPartnerName,
    'rooms' => $rooms,
    'directThreads' => $directThreads,
    'presence' => $presenceData,
    'strings' => [
        'send' => t('chat.send'),
        'rooms' => t('chat.rooms'),
        'activePlayers' => t('chat.active_players'),
        'sending' => t('chat.sending'),
        'emptyMessages' => t('chat.empty_messages'),
        'readOnlyRoom' => t('chat.read_only_room'),
        'dateToday' => t('chat.date_today'),
        'dateYesterday' => t('chat.date_yesterday'),
        'placeholderRoom' => t('chat.placeholder_room', ['room' => ':room']),
        'placeholderDirect' => t('chat.placeholder_direct', ['player' => ':player']),
        'roomLangDesc' => t('chat.room_lang_desc', ['count' => ':count']),
        'lastMessageAt' => t('chat.last_message_at', ['time' => ':time']),
        'messagesCount' => t('chat.messages_count', ['count' => ':count']),
        'participantsCount' => t('chat.participants_count', ['count' => ':count']),
        'online' => t('chat.online'),
        'offline' => t('chat.offline'),
        'dmActionLabel' => t('chat.dm_action_label', ['player' => ':player']),
        'adminBadge' => t('chat.admin_badge'),
        'connectionError' => t('chat.connection_error'),
        'sendConnectionError' => t('chat.send_connection_error'),
        'attachmentPhoto' => t('chat.attachment_photo'),
        'yesterdayRelative' => t('chat.yesterday_relative'),
        'daysAgo' => t('chat.days_ago', ['count' => ':count']),
        'loadingMessages' => t('chat.loading_messages'),
        'loadError' => t('chat.load_error'),
        'sendFailed' => t('chat.send_failed'),
        'private' => t('chat.private'),
        'unreadBadgeTitle' => t('chat.unread_badge_title', ['count' => ':count']),
        'defaultPlayerName' => t('chat.default_player_name'),
    ]
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>"></div>
