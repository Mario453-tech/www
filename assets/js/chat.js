/**
 * OilEmpire - Unified Chat & Widget Controller
 * Kontroler zunifikowanego czatu oraz widgetu dashboardu
 */
(function () {
    'use strict';

    // Helper: Escape HTML special characters
    // Pomocnik: Bezpieczne uciekanie znakow specjalnych HTML
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Helper: Show notification toast or alert
    // Pomocnik: Pokaz toast lub komunikat bledu
    function showToast(text, type) {
        if (typeof window.showGameToast === 'function') {
            window.showGameToast(text, type || 'info');
            return;
        }
        var existing = document.getElementById('chatToast');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.id = 'chatToast';
        el.className = 'dm-toast dm-toast--' + (type || 'info');
        el.textContent = text;
        document.body.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('dm-toast--show'); });
        setTimeout(function () {
            el.classList.remove('dm-toast--show');
            setTimeout(function () { el.remove(); }, 180);
        }, 3000);
    }

    // =========================================================================
    // 1. Unified Complete Chat Application (/chat)
    // 1. Zunifikowana aplikacja kompletnego czatu (/chat)
    // =========================================================================
    if (window.CHAT_CONFIG) {
        var cfg = window.CHAT_CONFIG;
        var API = cfg.api || '/src/ChatApi.php';
        var myId = parseInt(cfg.playerId || 0, 10);
        var strings = cfg.strings || {};

        var state = {
            mode: cfg.withPartnerId ? 'direct' : 'room',
            roomSlug: cfg.activeRoomSlug || 'polski',
            roomId: parseInt(cfg.activeRoomId || 1, 10),
            roomName: 'Polski',
            partnerId: cfg.withPartnerId ? parseInt(cfg.withPartnerId, 10) : null,
            partnerName: cfg.withPartnerName || '',
            lastMessageId: 0,
            lastDateLabel: '',
            messages: [],
            rooms: cfg.rooms || [],
            directThreads: cfg.directThreads || [],
            pollTimer: null,
            presenceTimer: null,
            isSending: false
        };

        // DOM elements cache.
        // Pamiec podreczna elementow DOM.
        var dom = {
            shell: document.getElementById('chatShell'),
            colLeft: document.getElementById('chatColLeft'),
            colCenter: document.getElementById('chatColCenter'),
            colRight: document.getElementById('chatColRight'),
            roomsList: document.getElementById('chatRoomsList'),
            directList: document.getElementById('chatDirectList'),
            playersList: document.getElementById('chatPlayersList'),
            messagesArea: document.getElementById('chatMessagesArea'),
            convTitle: document.getElementById('chatConvTitle'),
            convDesc: document.getElementById('chatConvDesc'),
            convLastMsg: document.getElementById('chatConvLastMsg'),
            form: document.getElementById('chatComposer'),
            input: document.getElementById('chatMsgInput'),
            sendBtn: document.getElementById('chatSendBtn'),
            globalOnlineCount: document.getElementById('chatGlobalOnlineCount'),
            rightOnlineCount: document.getElementById('chatRightOnlineCount'),
            mobRoomsBtn: document.getElementById('chatMobRoomsBtn'),
            mobDirectBtn: document.getElementById('chatMobDirectBtn'),
            mobActiveBtn: document.getElementById('chatMobActiveBtn'),
            mobOnlineBadge: document.getElementById('chatMobOnlineBadge'),
            mobDirectUnread: document.getElementById('chatMobDirectUnread'),
            loadingIndicator: document.getElementById('chatLoadingIndicator')
        };

        // Format avatar circle
        // Formatuj kolko avatara
        function renderAvatar(name, avatarPath, isAdmin, isMine) {
            var letter = (name || '?').charAt(0).toUpperCase();
            var cls = 'chat-msg-avatar';
            if (isAdmin) cls += ' chat-msg-avatar--admin';
            else if (isMine) cls += ' chat-msg-avatar--mine';

            if (avatarPath) {
                var src = avatarPath.charAt(0) === '/' ? avatarPath : '/' + avatarPath;
                return '<div class="' + cls + '"><img src="' + escHtml(src) + '" alt=""></div>';
            }
            return '<div class="' + cls + '">' + escHtml(letter) + '</div>';
        }

        // Render message item HTML
        // Renderuj kod HTML pojedynczej wiadomosci
        function renderMessageHtml(m) {
            var senderId = parseInt(m.sender_id || 0, 10);
            var isMine = (senderId === myId);
            var isAdmin = Boolean(m.is_admin);
            var authorName = m.sender_name || strings.defaultPlayerName || '';
            var timeStr = m.time || '';

            var authorHtml = escHtml(authorName);
            var authorCls = 'chat-msg-author';
            if (isAdmin) {
                authorCls += ' chat-msg-author--admin';
                authorHtml = (strings.adminBadge || '') + ' ' + authorHtml;
            } else if (isMine) {
                authorCls += ' chat-msg-author--mine';
            }

            var avatarHtml = renderAvatar(authorName, m.avatar_path, isAdmin, isMine);
            var rowCls = 'chat-msg-row ' + (isMine ? 'chat-msg-row--mine' : 'chat-msg-row--other');

            var html = '';
            // Insert date separator if date changed
            // Wstaw separator daty, jesli data ulegla zmianie
            if (m.date_label && m.date_label !== state.lastDateLabel) {
                state.lastDateLabel = m.date_label;
                html += '<div class="chat-date-separator">' + escHtml(m.date_label) + '</div>';
            }

            html += '<div class="' + rowCls + '" data-id="' + parseInt(m.id, 10) + '">';
            html += avatarHtml;
            html += '<div class="chat-msg-body-wrapper">';
            html += '<div class="chat-msg-header">';
            html += '<span class="' + authorCls + '">' + authorHtml + '</span>';
            html += '<span class="chat-msg-time">' + escHtml(timeStr) + '</span>';
            html += '</div>';
            html += '<div class="chat-msg-bubble">' + escHtml(m.message) + '</div>';
            html += '</div>';
            html += '</div>';

            return html;
        }

        // Scroll messages container to bottom
        // Przewin kontener wiadomosci na sam dol
        function scrollToBottom() {
            if (!dom.messagesArea) return;
            dom.messagesArea.scrollTop = dom.messagesArea.scrollHeight;
        }

        // Render entire messages timeline
        // Renderuj cala os czasu wiadomosci
        function renderMessagesTimeline(messages) {
            state.lastDateLabel = '';
            if (!messages || messages.length === 0) {
                var emptyText = strings.emptyMessages || '';
                dom.messagesArea.innerHTML = '<div class="chat-empty-room-hint">' + escHtml(emptyText) + '</div>';
                return;
            }

            var html = '';
            for (var i = 0; i < messages.length; i++) {
                html += renderMessageHtml(messages[i]);
            }
            dom.messagesArea.innerHTML = html;
            scrollToBottom();
        }

        // Append new messages (used in polling & after send)
        // Dolacz nowe wiadomosci (uzywane przy pollingu i po wyslaniu)
        function appendNewMessages(newMessages) {
            if (!newMessages || newMessages.length === 0) return;
            var emptyHint = dom.messagesArea.querySelector('.chat-empty-room-hint');
            if (emptyHint) emptyHint.remove();

            var added = false;
            for (var i = 0; i < newMessages.length; i++) {
                var m = newMessages[i];
                var id = parseInt(m.id, 10);
                if (id > state.lastMessageId) {
                    state.lastMessageId = id;
                    dom.messagesArea.insertAdjacentHTML('beforeend', renderMessageHtml(m));
                    added = true;
                }
            }

            if (added) {
                scrollToBottom();
            }
        }

        // Update room header and placeholder
        // Aktualizuj naglowek pokoju i tekst zastepczy
        function updateConversationHeader() {
            if (state.mode === 'room') {
                dom.convTitle.textContent = '# ' + state.roomName;
                var roomObj = null;
                for (var i = 0; i < state.rooms.length; i++) {
                    if (state.rooms[i].slug === state.roomSlug) {
                        roomObj = state.rooms[i];
                        break;
                    }
                }
                var memberCount = roomObj ? roomObj.member_count : 0;
                var descTpl = strings.roomLangDesc || '';
                dom.convDesc.textContent = descTpl.replace(':count', memberCount);

                var phTpl = strings.placeholderRoom || '';
                dom.input.placeholder = phTpl.replace(':room', state.roomName);
            } else {
                dom.convTitle.textContent = state.partnerName;
                dom.convDesc.textContent = strings.private || '';
                var phDirectTpl = strings.placeholderDirect || '';
                dom.input.placeholder = phDirectTpl.replace(':player', state.partnerName);
            }
        }

        // Load messages for current active room or direct partner
        // Zaladuj wiadomosci dla biezacego pokoju lub rozmowcy prywatnego
        function loadCurrentMessages() {
            dom.messagesArea.innerHTML = '<div class="chat-loading-indicator"><span>' + escHtml(strings.loadingMessages || '') + '</span></div>';
            state.lastMessageId = 0;

            var url = '';
            if (state.mode === 'room') {
                url = API + '?action=room_messages&room_id=' + state.roomId + '&limit=50';
            } else {
                url = API + '?action=direct_messages&partner_id=' + state.partnerId + '&limit=50';
            }

            fetch(url, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        var msgs = data.messages || [];
                        if (msgs.length > 0) {
                            state.lastMessageId = parseInt(msgs[msgs.length - 1].id, 10);
                            var lastMsg = msgs[msgs.length - 1];
                            var lastTpl = strings.lastMessageAt || '';
                            dom.convLastMsg.textContent = lastTpl.replace(':time', lastMsg.time || '');
                        } else {
                            dom.convLastMsg.textContent = '';
                        }
                        renderMessagesTimeline(msgs);
                    } else {
                        dom.messagesArea.innerHTML = '<div class="chat-empty-room-hint">' + escHtml((data && data.error) || strings.loadError || '') + '</div>';
                    }
                })
                .catch(function () {
                    dom.messagesArea.innerHTML = '<div class="chat-empty-room-hint">' + escHtml(strings.connectionError || '') + '</div>';
                });
        }

        // Select Room action
        // Akcja wyboru pokoju
        function selectRoom(slug) {
            var room = null;
            for (var i = 0; i < state.rooms.length; i++) {
                if (state.rooms[i].slug === slug) {
                    room = state.rooms[i];
                    break;
                }
            }
            if (!room) return;

            state.mode = 'room';
            state.roomSlug = room.slug;
            state.roomId = parseInt(room.id, 10);
            state.roomName = room.name;
            state.partnerId = null;
            state.partnerName = '';

            // Update active room classes in UI
            // Aktualizuj klasy aktywnego pokoju w interfejsie
            var roomItems = dom.roomsList.querySelectorAll('.chat-room-item');
            roomItems.forEach(function (btn) {
                if (btn.getAttribute('data-room-slug') === slug) {
                    btn.classList.add('chat-room-item--active');
                    var unread = btn.querySelector('.room-unread-dot');
                    if (unread) unread.classList.add('room-unread-dot--hidden');
                } else {
                    btn.classList.remove('chat-room-item--active');
                }
            });

            // Remove active class from direct threads
            // Usun klase aktywna z watkow prywatnych
            var threadItems = dom.directList.querySelectorAll('.chat-thread-item');
            threadItems.forEach(function (btn) {
                btn.classList.remove('chat-thread-item--active');
            });

            updateConversationHeader();
            loadCurrentMessages();

            // Mobile: switch back to center conversation view
            // Mobile: przelacz z powrotem na srodkowy widok rozmowy
            showMobileSection('center');
        }

        // Select Direct Conversation action
        // Akcja wyboru rozmowy prywatnej
        function selectDirect(partnerId, partnerName) {
            state.mode = 'direct';
            state.partnerId = parseInt(partnerId, 10);
            state.partnerName = partnerName || strings.defaultPlayerName || '';

            // Remove active class from rooms
            // Usun klase aktywna z pokoi
            var roomItems = dom.roomsList.querySelectorAll('.chat-room-item');
            roomItems.forEach(function (btn) {
                btn.classList.remove('chat-room-item--active');
            });

            // Update active class in direct threads list
            // Aktualizuj klase aktywna na liscie watkow prywatnych
            var threadItems = dom.directList.querySelectorAll('.chat-thread-item');
            threadItems.forEach(function (btn) {
                if (parseInt(btn.getAttribute('data-partner-id'), 10) === state.partnerId) {
                    btn.classList.add('chat-thread-item--active');
                    var badge = btn.querySelector('.thread-badge-gold');
                    if (badge) badge.remove();
                } else {
                    btn.classList.remove('chat-thread-item--active');
                }
            });

            updateConversationHeader();
            loadCurrentMessages();
            showMobileSection('center');
        }

        // Polling loop for active messages
        // Petla odpytywania o nowe wiadomosci
        function pollMessages() {
            if (document.hidden) return;

            var url = '';
            if (state.mode === 'room') {
                url = API + '?action=room_messages&room_id=' + state.roomId + '&after_id=' + state.lastMessageId;
            } else if (state.mode === 'direct' && state.partnerId) {
                url = API + '?action=direct_messages&partner_id=' + state.partnerId + '&after_id=' + state.lastMessageId;
            } else {
                return;
            }

            fetch(url, { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok && data.messages && data.messages.length > 0) {
                        appendNewMessages(data.messages);
                        var lastMsg = data.messages[data.messages.length - 1];
                        var lastTpl = strings.lastMessageAt || '';
                        dom.convLastMsg.textContent = lastTpl.replace(':time', lastMsg.time || '');
                    }
                })
                .catch(function () {});
        }

        // Polling loop for presence and active players
        // Petla odpytywania o obecnosc i aktywnych graczy
        function pollPresence() {
            if (document.hidden) return;
            fetch(API + '?action=presence&room_slug=' + encodeURIComponent(state.roomSlug), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        var online = parseInt(data.total_online || 0, 10);
                        if (dom.globalOnlineCount) dom.globalOnlineCount.textContent = online;
                        if (dom.rightOnlineCount) dom.rightOnlineCount.textContent = online + ' online';
                        if (dom.mobOnlineBadge) dom.mobOnlineBadge.textContent = online;
                        if (data.players) renderPlayersList(data.players);
                    }
                })
                .catch(function () {});
        }

        // Render active players list
        // Renderuj liste aktywnych graczy
        function renderPlayersList(players) {
            if (!dom.playersList) return;
            var html = '';
            for (var i = 0; i < players.length; i++) {
                var p = players[i];
                var dotCls = p.is_online ? 'presence-dot--online' : 'presence-dot--offline';
                var subText = p.is_online ? p.room_name : (strings.offline || '');
                var pid = parseInt(p.id, 10);

                html += '<div class="chat-player-item" data-player-id="' + pid + '">';
                html += '<span class="presence-dot ' + dotCls + '"></span>';
                html += '<div class="player-item-text">';
                html += '<span class="player-item-name">' + escHtml(p.name) + '</span>';
                html += '<span class="player-item-sub">' + escHtml(subText) + '</span>';
                html += '</div>';

                if (pid !== myId) {
                    var actionLabelTpl = strings.dmActionLabel || ':player';
                    var actionLabel = actionLabelTpl.replace(':player', escHtml(p.name));
                    html += '<button type="button" class="player-direct-btn" data-player-id="' + pid + '" data-player-name="' + escHtml(p.name) + '" title="' + actionLabel + '" aria-label="' + actionLabel + '">&rarr;</button>';
                }
                html += '</div>';
            }
            dom.playersList.innerHTML = html;
        }

        // Send message form submit
        // Wyslanie formularza wiadomosci
        function handleFormSubmit(e) {
            e.preventDefault();
            var text = dom.input.value.trim();
            if (!text || state.isSending) return;

            state.isSending = true;
            dom.sendBtn.disabled = true;
            dom.sendBtn.textContent = strings.sending || '';

            var payload = {};
            if (state.mode === 'room') {
                payload = {
                    action: 'send_room',
                    csrf_token: cfg.csrfToken || '',
                    room_id: state.roomId,
                    room_slug: state.roomSlug,
                    message: text
                };
            } else {
                payload = {
                    action: 'send_direct',
                    csrf_token: cfg.csrfToken || '',
                    partner_id: state.partnerId,
                    message: text
                };
            }

            fetch(API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': cfg.csrfToken || ''
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    state.isSending = false;
                    dom.sendBtn.disabled = false;
                    dom.sendBtn.textContent = strings.send || '';

                    if (data && data.ok && data.message) {
                        dom.input.value = '';
                        appendNewMessages([data.message]);
                        var lastTpl = strings.lastMessageAt || '';
                        dom.convLastMsg.textContent = lastTpl.replace(':time', data.message.time || '');
                    } else {
                        var err = (data && data.error) || strings.sendFailed || '';
                        showToast(err, 'error');
                    }
                })
                .catch(function () {
                    state.isSending = false;
                    dom.sendBtn.disabled = false;
                    dom.sendBtn.textContent = strings.send || '';
                    showToast(strings.sendConnectionError || '', 'error');
                });
        }

        // Mobile tabs switcher: 'rooms', 'direct', 'active', 'center'
        // Przelacznik widokow mobilnych
        function showMobileSection(section) {
            if (window.innerWidth > 768) return;

            dom.colLeft.classList.remove('chat-col--active-mob');
            dom.colCenter.classList.remove('chat-col--active-mob');
            dom.colRight.classList.remove('chat-col--active-mob');

            if (dom.mobRoomsBtn) dom.mobRoomsBtn.classList.remove('chat-mob-btn--active');
            if (dom.mobDirectBtn) dom.mobDirectBtn.classList.remove('chat-mob-btn--active');
            if (dom.mobActiveBtn) dom.mobActiveBtn.classList.remove('chat-mob-btn--active');

            if (section === 'rooms') {
                dom.colLeft.classList.add('chat-col--active-mob');
                if (dom.mobRoomsBtn) dom.mobRoomsBtn.classList.add('chat-mob-btn--active');
            } else if (section === 'direct') {
                dom.colLeft.classList.add('chat-col--active-mob');
                if (dom.mobDirectBtn) dom.mobDirectBtn.classList.add('chat-mob-btn--active');
            } else if (section === 'active') {
                dom.colRight.classList.add('chat-col--active-mob');
                if (dom.mobActiveBtn) dom.mobActiveBtn.classList.add('chat-mob-btn--active');
            } else {
                dom.colCenter.classList.add('chat-col--active-mob');
            }
        }

        // Start polling timers
        // Uruchomienie timerow odpytywania
        function startPollingTimers() {
            stopPollingTimers();
            state.pollTimer = setInterval(pollMessages, 9000);
            state.presenceTimer = setInterval(pollPresence, 30000);
        }

        function stopPollingTimers() {
            if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
            if (state.presenceTimer) { clearInterval(state.presenceTimer); state.presenceTimer = null; }
        }

        // Event listeners registration
        // Rejestracja nasluchiwaczy zdarzen
        if (dom.form) {
            dom.form.addEventListener('submit', handleFormSubmit);
        }

        if (dom.roomsList) {
            dom.roomsList.addEventListener('click', function (e) {
                var btn = e.target.closest('.chat-room-item');
                if (btn) {
                    var slug = btn.getAttribute('data-room-slug');
                    if (slug) selectRoom(slug);
                }
            });
        }

        if (dom.directList) {
            dom.directList.addEventListener('click', function (e) {
                var btn = e.target.closest('.chat-thread-item');
                if (btn) {
                    var pid = btn.getAttribute('data-partner-id');
                    var name = btn.getAttribute('data-partner-name');
                    if (pid) selectDirect(parseInt(pid, 10), name || '');
                }
            });
        }

        if (dom.playersList) {
            dom.playersList.addEventListener('click', function (e) {
                var btn = e.target.closest('.player-direct-btn');
                if (btn) {
                    var pid = btn.getAttribute('data-player-id');
                    var name = btn.getAttribute('data-player-name');
                    if (pid) selectDirect(parseInt(pid, 10), name || '');
                }
            });
        }

        if (dom.mobRoomsBtn) {
            dom.mobRoomsBtn.addEventListener('click', function () { showMobileSection('rooms'); });
        }
        if (dom.mobDirectBtn) {
            dom.mobDirectBtn.addEventListener('click', function () { showMobileSection('direct'); });
        }
        if (dom.mobActiveBtn) {
            dom.mobActiveBtn.addEventListener('click', function () { showMobileSection('active'); });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPollingTimers();
            } else {
                pollMessages();
                pollPresence();
                startPollingTimers();
            }
        });

        // Initialize view
        // Inicjalizacja widoku
        updateConversationHeader();
        loadCurrentMessages();
        startPollingTimers();

        // On mobile, default to center conversation
        // Na urzadzeniach mobilnych domyslnie pokaz srodkowa rozmowe
        if (window.innerWidth <= 768) {
            showMobileSection('center');
        }

        // Expose public API on window.ChatApp
        // Udostepnienie publicznego interfejsu w window.ChatApp
        window.ChatApp = {
            selectRoom: selectRoom,
            selectDirect: selectDirect,
            refreshPresence: pollPresence
        };
        return;
    }

    // =========================================================================
    // 2. Compact Dashboard Widget Fallback (#chatForm)
    // 2. Fallback kompaktowego widgetu dashboardu (#chatForm)
    // =========================================================================
    var widgetForm = document.getElementById('chatForm');
    var widgetBox = document.getElementById('chatMessages');
    if (!widgetForm || !widgetBox) return;

    var widgetApi = '/src/ChatApi.php';
    var wLastId = 0;
    var wMyId = 0;
    var wInput = document.getElementById('chatInput');
    var wInterval = null;

    function renderWidgetMsg(m) {
        var senderId = parseInt(m.sender_id || 0, 10);
        var isMine = senderId === wMyId;
        var isAdmin = parseInt(m.is_admin || 0, 10) === 1;
        var cls = 'chat-msg' + (isMine ? ' chat-msg--mine' : '') + (isAdmin ? ' chat-msg--admin' : '');
        var author = m.username || (window.CHAT_CONFIG && window.CHAT_CONFIG.strings && window.CHAT_CONFIG.strings.defaultPlayerName) || '';
        var time = m.time || '';

        return '<div class="' + cls + '" data-id="' + parseInt(m.id, 10) + '">' +
            '<div class="chat-msg-header"><span class="chat-msg-author">' + escHtml(author) + '</span><span class="chat-msg-time">' + escHtml(time) + '</span></div>' +
            '<div class="chat-msg-bubble">' + escHtml(m.message) + '</div>' +
            '</div>';
    }

    function appendWidgetMessages(messages) {
        if (!messages || !messages.length) return;
        var html = '';
        for (var i = 0; i < messages.length; i++) {
            var m = messages[i];
            var id = parseInt(m.id, 10);
            if (id > wLastId) {
                wLastId = id;
                html += renderWidgetMsg(m);
            }
        }
        if (!html) return;
        var loading = widgetBox.querySelector('.chat-loading');
        if (loading) loading.remove();
        widgetBox.insertAdjacentHTML('beforeend', html);
        widgetBox.scrollTop = widgetBox.scrollHeight;
    }

    function pollWidget() {
        if (document.hidden) return;
        fetch(widgetApi + '?since=' + wLastId, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.my_id) wMyId = parseInt(data.my_id, 10);
                appendWidgetMessages(data.messages || []);
            })
            .catch(function () {});
    }

    function loadWidget() {
        fetch(widgetApi, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                wMyId = parseInt(data.my_id || 0, 10);
                var loading = widgetBox.querySelector('.chat-loading');
                if (loading) loading.remove();
                appendWidgetMessages(data.messages || []);
                wInterval = setInterval(pollWidget, 8000);
            })
            .catch(function () {});
    }

    widgetForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = wInput.value.trim();
        if (!msg) return;

        var btn = widgetForm.querySelector('.chat-send');
        if (btn) btn.disabled = true;

        fetch(widgetApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ message: msg })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (btn) btn.disabled = false;
                if (data && data.ok) {
                    wInput.value = '';
                    pollWidget();
                } else if (data && data.error) {
                    showToast(data.error, 'error');
                }
            })
            .catch(function () {
                if (btn) btn.disabled = false;
                var errStr = (window.CHAT_CONFIG && window.CHAT_CONFIG.strings && window.CHAT_CONFIG.strings.sendConnectionError) || '';
                if (errStr) showToast(errStr, 'error');
            });
    });

    loadWidget();
})();
