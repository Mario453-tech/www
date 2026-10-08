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
    var configNode = document.getElementById('chatConfig');
    if (configNode || window.CHAT_CONFIG) {
        var cfg = configNode ? JSON.parse(configNode.dataset.config) : window.CHAT_CONFIG;
        var API = cfg.api || '/api/internal/ChatApi.php';
        var myId = parseInt(cfg.playerId || 0, 10);
        var strings = cfg.strings || {};

        var state = {
            mode: cfg.withPartnerId ? 'direct' : 'room',
            roomSlug: cfg.activeRoomSlug || 'polski',
            roomId: parseInt(cfg.activeRoomId || 1, 10),
            roomName: cfg.activeRoomName || '',
            partnerId: cfg.withPartnerId ? parseInt(cfg.withPartnerId, 10) : null,
            partnerName: cfg.withPartnerName || '',
            lastMessageId: 0,
            lastDateLabel: '',
            messages: [],
            rooms: cfg.rooms || [],
            directThreads: cfg.directThreads || [],
            pollTimer: null,
            presenceTimer: null,
            isSending: false,
            epoch: 0,
            loading: false,
            polling: false,
            firstId: 0,
            readId: 0,
            threadPages: 1
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
            var previous = state.previousMessage;
            if (previous && previous.sender_id === m.sender_id && previous.date === m.date
                && Math.abs(Date.parse(m.created_at.replace(' ', 'T')) - Date.parse(previous.created_at.replace(' ', 'T'))) < 300000) {
                rowCls += ' chat-msg-row--grouped';
            }
            state.previousMessage = m;

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
            state.previousMessage = null;
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
            var follow = nearBottom();
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
                if (follow) {
                    scrollToBottom();
                    acknowledge();
                } else {
                    document.getElementById('chatNewMessages').hidden = false;
                }
            }
        }

        // Update room header and placeholder
        // Aktualizuj naglowek pokoju i tekst zastepczy
        function updateConversationHeader() {
            var active = state.rooms.find(function (room) { return room.id === state.roomId; });
            if (active) state.roomName = active.name;
            var readOnly = state.mode === 'room' && (!active || active.status !== 'active') && !cfg.isAdmin;
            dom.form.hidden = readOnly;
            document.getElementById('chatReadOnly').hidden = !readOnly;
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
            var epoch = ++state.epoch;
            state.loading = true;
            state.readId = 0;
            state.firstId = 0;
            state.messages = [];
            document.getElementById('chatNewMessages').hidden = true;
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
                    if (epoch !== state.epoch) return;
                    state.loading = false;
                    if (data && data.ok) {
                        var msgs = data.messages || [];
                        state.messages = msgs;
                        state.firstId = msgs.length ? Number(msgs[0].id) : 0;
                        document.getElementById('chatOlder').hidden = msgs.length < 50;
                        if (msgs.length > 0) {
                            state.lastMessageId = parseInt(msgs[msgs.length - 1].id, 10);
                            var lastMsg = msgs[msgs.length - 1];
                            var lastTpl = strings.lastMessageAt || '';
                            dom.convLastMsg.textContent = lastTpl.replace(':time', lastMsg.time || '');
                        } else {
                            dom.convLastMsg.textContent = '';
                        }
                        renderMessagesTimeline(msgs);
                        acknowledge();
                    } else {
                        dom.messagesArea.innerHTML = '<div class="chat-empty-room-hint">' + escHtml((data && data.error) || strings.loadError || '') + '</div>';
                    }
                })
                .catch(function () {
                    if (epoch !== state.epoch) return;
                    state.loading = false;
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
            if (document.hidden || state.loading || state.polling) return;
            var epoch = state.epoch;
            state.polling = true;

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
                    if (epoch !== state.epoch) return;
                    if (data && data.ok && data.messages && data.messages.length > 0) {
                        state.messages = state.messages.concat(data.messages.filter(function (m) { return Number(m.id) > state.lastMessageId; }));
                        appendNewMessages(data.messages);
                        var lastMsg = data.messages[data.messages.length - 1];
                        var lastTpl = strings.lastMessageAt || '';
                        dom.convLastMsg.textContent = lastTpl.replace(':time', lastMsg.time || '');
                    }
                })
                .catch(function () {})
                .finally(function () { state.polling = false; });
        }

        // Polling loop for presence and active players
        // Petla odpytywania o obecnosc i aktywnych graczy
        function pollPresence() {
            if (document.hidden) return;
            post({ action: 'heartbeat', room_slug: state.mode === 'room' ? state.roomSlug : null }).catch(function () {});
            fetch(API + '?action=presence&room_slug=' + encodeURIComponent(state.roomSlug), { credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        var online = parseInt(data.total_online || 0, 10);
                        if (dom.globalOnlineCount) dom.globalOnlineCount.textContent = online;
                        if (dom.rightOnlineCount) dom.rightOnlineCount.textContent = online + ' ' + strings.online;
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
            var epoch = state.epoch;

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
                        if (epoch === state.epoch) {
                            if (dom.input.value.trim() === text) dom.input.value = '';
                            pollMessages();
                        }
                        refreshLists();
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

        function post(payload) {
            payload.csrf_token = cfg.csrfToken;
            return fetch(API, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
            }).then(function (response) { return response.json(); });
        }

        function nearBottom() {
            return dom.messagesArea.scrollHeight - dom.messagesArea.clientHeight - dom.messagesArea.scrollTop < 60;
        }

        function acknowledge() {
            if (document.hidden || state.loading || state.acknowledging || document.getElementById('chatDrawer').open || !nearBottom() || state.lastMessageId <= state.readId) return;
            state.acknowledging = true;
            var epoch = state.epoch;
            var lastId = state.lastMessageId;
            post({ action: 'mark_read', type: state.mode, id: state.mode === 'room' ? state.roomId : state.partnerId, last_id: lastId })
                .then(function (data) {
                    if (data.ok && epoch === state.epoch) {
                        state.readId = lastId;
                        document.getElementById('chatNewMessages').hidden = true;
                        refreshLists();
                    }
                }).catch(function () {}).finally(function () { state.acknowledging = false; });
        }

        function refreshLists() {
            if (document.hidden || state.refreshing) return;
            state.refreshing = true;
            var requests = [fetch(API + '?action=rooms', { credentials: 'same-origin' }).then(function (r) { return r.json(); })];
            for (var page = 1; page <= state.threadPages; page++) {
                requests.push(fetch(API + '?action=direct_threads&page=' + page, { credentials: 'same-origin' }).then(function (r) { return r.json(); }));
            }
            Promise.all(requests).then(function (data) {
                if (!data[0].ok || !data[1].ok) return;
                state.rooms = data[0].rooms;
                state.directThreads = data.slice(1).flatMap(function (page) { return page.threads || []; });
                document.getElementById('chatMoreThreads').hidden = data[data.length - 1].threads.length < 50;
                dom.roomsList.innerHTML = state.rooms.map(function (room) {
                    return '<button type="button" class="chat-room-item' + (state.mode === 'room' && room.id === state.roomId ? ' chat-room-item--active' : '') + '" data-room-slug="' + escHtml(room.slug) + '">' +
                        '<span class="room-item-text"><span class="room-item-name">' + escHtml(room.name) + '</span></span>' +
                        '<span class="room-unread-dot' + (room.unread_count ? '' : ' room-unread-dot--hidden') + '" aria-label="' + escHtml(strings.unreadBadgeTitle.replace(':count', room.unread_count)) + '"></span></button>';
                }).join('');
                var unread = 0;
                dom.directList.innerHTML = state.directThreads.map(function (thread) {
                    unread += Number(thread.unread_count);
                    return '<button type="button" class="chat-thread-item' + (state.mode === 'direct' && thread.partner_id === state.partnerId ? ' chat-thread-item--active' : '') + '" data-partner-id="' + thread.partner_id + '" data-partner-name="' + escHtml(thread.partner_name) + '">' +
                        '<span class="thread-item-text"><span class="thread-item-name">' + escHtml(thread.partner_name) + '</span><span class="thread-item-sub">' + escHtml(thread.last_message) + '</span></span>' +
                        (thread.unread_count ? '<span class="thread-badge-gold">' + thread.unread_count + '</span>' : '') + '</button>';
                }).join('');
                unread = Number(data[1].unread_total || unread);
                dom.mobDirectUnread.textContent = unread;
                dom.mobDirectUnread.classList.toggle('chat-badge-count--hidden', !unread);
                updateConversationHeader();
            }).catch(function () {}).finally(function () { state.refreshing = false; });
        }

        function loadOlder() {
            if (state.loading || !state.firstId) return;
            var epoch = state.epoch;
            var button = document.getElementById('chatOlder');
            button.disabled = true;
            var url = state.mode === 'room' ? '?action=room_messages&room_id=' + state.roomId : '?action=direct_messages&partner_id=' + state.partnerId;
            fetch(API + url + '&limit=50&before_id=' + state.firstId, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); }).then(function (data) {
                    if (epoch !== state.epoch || !data.ok) return;
                    var offset = dom.messagesArea.scrollHeight - dom.messagesArea.scrollTop;
                    state.messages = data.messages.concat(state.messages);
                    if (data.messages.length) state.firstId = Number(data.messages[0].id);
                    renderMessagesTimeline(state.messages);
                    dom.messagesArea.scrollTop = dom.messagesArea.scrollHeight - offset;
                    button.hidden = data.messages.length < 50;
                }).catch(function () { showToast(strings.connectionError, 'error'); })
                .finally(function () { button.disabled = false; });
        }

        // Mobile tabs switcher: 'rooms', 'direct', 'active', 'center'
        // Przelacznik widokow mobilnych
        function showMobileSection(section) {
            var drawer = document.getElementById('chatDrawer');
            if (drawer.open) drawer.close();
            if (window.innerWidth > 1024 || section === 'center') return;
            var column = section === 'active' ? dom.colRight : dom.colLeft;
            var anchor = document.createComment('chat-column');
            column.before(anchor);
            drawer.appendChild(column);
            document.getElementById('chatDrawerTitle').textContent = section === 'active' ? strings.activePlayers : (section === 'direct' ? strings.private : strings.rooms);
            drawer.addEventListener('close', function restore() {
                anchor.replaceWith(column);
                drawer.removeEventListener('close', restore);
            });
            drawer.showModal();
            if (section === 'direct') dom.directList.scrollIntoView({ block: 'start' });
        }

        // Start polling timers
        // Uruchomienie timerow odpytywania
        function startPollingTimers() {
            stopPollingTimers();
            state.pollTimer = setInterval(function () { pollMessages(); refreshLists(); }, 9000);
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
        document.getElementById('chatOlder').addEventListener('click', loadOlder);
        document.getElementById('chatMoreThreads').addEventListener('click', function () {
            if (!state.refreshing) { state.threadPages++; refreshLists(); }
        });
        document.getElementById('chatNewMessages').addEventListener('click', function () { scrollToBottom(); acknowledge(); });
        document.getElementById('chatDrawerClose').addEventListener('click', function () { document.getElementById('chatDrawer').close(); });
        dom.messagesArea.addEventListener('scroll', acknowledge, { passive: true });
        window.addEventListener('resize', function () { if (window.innerWidth > 1024) showMobileSection('center'); });

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
                acknowledge();
                pollPresence();
                startPollingTimers();
            }
        });

        // Initialize view
        // Inicjalizacja widoku
        updateConversationHeader();
        loadCurrentMessages();
        startPollingTimers();
        pollPresence();
        dom.colCenter.classList.add('chat-col--active-mob');

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

    var widgetApi = '/api/internal/ChatApi.php';
    var wLastId = 0;
    var wMyId = 0;
    var wRoomId = 0;
    var wInput = document.getElementById('chatInput');
    var wInterval = null;

    function widgetResponse(response) {
        return response.json().then(function (data) {
            if (!response.ok || data.error) throw new Error('Chat request failed');
            return data;
        });
    }

    function showWidgetState(text) {
        var stateNode = widgetBox.querySelector('.chat-loading');
        if (!stateNode) {
            stateNode = document.createElement('p');
            stateNode.className = 'chat-loading';
            widgetBox.appendChild(stateNode);
        }
        stateNode.textContent = text;
    }

    function renderWidgetMsg(m) {
        var senderId = parseInt(m.sender_id || 0, 10);
        var isMine = senderId === wMyId;
        var isAdmin = parseInt(m.is_admin || 0, 10) === 1;
        var cls = 'chat-msg' + (isMine ? ' chat-msg--mine' : '') + (isAdmin ? ' chat-msg--admin' : '');
        var author = m.sender_name || m.username || (window.CHAT_CONFIG && window.CHAT_CONFIG.strings && window.CHAT_CONFIG.strings.defaultPlayerName) || '';
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
        if (!wRoomId) return;
        fetch(widgetApi + '?action=room_messages&room_id=' + wRoomId + '&after_id=' + wLastId + '&limit=50', { credentials: 'same-origin' })
            .then(widgetResponse)
            .then(function (data) {
                if (data.my_id) wMyId = parseInt(data.my_id, 10);
                appendWidgetMessages(data.messages || []);
            })
            .catch(function () {
                if (wLastId === 0) showWidgetState(widgetBox.dataset.error || '');
            });
    }

    function loadWidget() {
        fetch(widgetApi + '?action=init&room=polski', { credentials: 'same-origin' })
            .then(widgetResponse)
            .then(function (data) {
                wMyId = parseInt(data.my_id || 0, 10);
                wRoomId = parseInt(data.active_room && data.active_room.id || 0, 10);
                var loading = widgetBox.querySelector('.chat-loading');
                if (loading) loading.remove();
                appendWidgetMessages(data.messages || []);
                if (!data.messages || !data.messages.length) showWidgetState(widgetBox.dataset.empty || '');
                wInterval = setInterval(pollWidget, 8000);
            })
            .catch(function () { showWidgetState(widgetBox.dataset.error || ''); });
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
            body: JSON.stringify({
                action: 'send_room',
                room_id: wRoomId,
                message: msg,
                csrf_token: widgetForm.elements.csrf_token.value
            })
        })
            .then(widgetResponse)
            .then(function (data) {
                if (btn) btn.disabled = false;
                if (data && data.ok) {
                    wInput.value = '';
                    appendWidgetMessages(data.message ? [data.message] : []);
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
