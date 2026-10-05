<?php
declare(strict_types=1);

// Complete Chat Service - business logic for rooms, direct threads, messages, and presence.
// Serwis kompletnego czatu - logika biznesowa dla pokoi, watkow prywatnych, wiadomosci i obecnosci.

require_once __DIR__ . '/ChatBootstrap.php';

class ChatService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        ensureChatSchema();
    }

    // -------------------------------------------------------------------------
    // Rooms Management & Queries
    // Zarzadzanie i zapytania o pokoje
    // -------------------------------------------------------------------------

    // Get list of active rooms with member count and unread count for player.
    // Pobierz liste aktywnych pokoi z liczba czlonkow i nieprzeczytanych wiadomosci dla gracza.
    public function getRooms(int $playerId, string $locale = 'pl'): array
    {
        $nameCol = in_array($locale, ['en', 'de'], true) ? "name_{$locale}" : 'name_pl';
        $descCol = in_array($locale, ['en', 'de'], true) ? "description_{$locale}" : 'description_pl';

        $sql = "
            SELECT 
                r.id,
                r.slug,
                r.type,
                r.locale_code,
                COALESCE(NULLIF(r.{$nameCol}, ''), r.name_pl) AS name,
                COALESCE(NULLIF(r.{$descCol}, ''), r.description_pl) AS description,
                r.sort_order,
                r.status,
                (
                    SELECT COUNT(*) 
                    FROM chat_messages m 
                    WHERE m.room_id = r.id AND m.is_deleted = 0
                ) AS message_count,
                (
                    SELECT COUNT(DISTINCT p.player_id)
                    FROM chat_presence p
                    WHERE p.current_room_slug = r.slug 
                      AND p.is_online = 1 
                      AND p.last_active_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ) AS member_count,
                (
                    SELECT COUNT(*)
                    FROM chat_messages cm
                    WHERE cm.room_id = r.id
                      AND cm.is_deleted = 0
                      AND cm.sender_id != :player_id
                      AND cm.id > COALESCE((
                          SELECT rs.last_read_message_id
                          FROM chat_read_states rs
                          WHERE rs.player_id = :player_id
                            AND rs.conversation_type = 'room'
                            AND rs.conversation_id = r.id
                          LIMIT 1
                      ), 0)
                ) AS unread_count
            FROM chat_rooms r
            WHERE r.status != 'archived'
            ORDER BY r.sort_order ASC, r.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['player_id' => $playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            $row['id'] = (int) $row['id'];
            $row['sort_order'] = (int) $row['sort_order'];
            $row['message_count'] = (int) $row['message_count'];
            $row['member_count'] = (int) $row['member_count'];
            $row['unread_count'] = (int) $row['unread_count'];
            return $row;
        }, $rows);
    }

    // Get room details by slug.
    // Pobierz szczegoly pokoju po slugu.
    public function getRoomBySlug(string $slug, string $locale = 'pl'): ?array
    {
        $nameCol = in_array($locale, ['en', 'de'], true) ? "name_{$locale}" : 'name_pl';
        $descCol = in_array($locale, ['en', 'de'], true) ? "description_{$locale}" : 'description_pl';

        $stmt = $this->db->prepare("
            SELECT 
                r.id,
                r.slug,
                r.type,
                r.locale_code,
                COALESCE(NULLIF(r.{$nameCol}, ''), r.name_pl) AS name,
                COALESCE(NULLIF(r.{$descCol}, ''), r.description_pl) AS description,
                r.sort_order,
                r.status,
                (
                    SELECT COUNT(*) 
                    FROM chat_messages m 
                    WHERE m.room_id = r.id AND m.is_deleted = 0
                ) AS message_count,
                (
                    SELECT COUNT(DISTINCT p.player_id)
                    FROM chat_presence p
                    WHERE p.current_room_slug = r.slug 
                      AND p.is_online = 1 
                      AND p.last_active_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ) AS member_count
            FROM chat_rooms r
            WHERE r.slug = ? AND r.status != 'archived'
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return null;
        }

        $room['id'] = (int) $room['id'];
        $room['sort_order'] = (int) $room['sort_order'];
        $room['message_count'] = (int) $room['message_count'];
        $room['member_count'] = (int) $room['member_count'];
        return $room;
    }

    // -------------------------------------------------------------------------
    // Room Messages
    // Wiadomosci w pokojach
    // -------------------------------------------------------------------------

    // Get messages for a given room.
    // Pobierz wiadomosci dla danego pokoju.
    public function getRoomMessages(int $roomId, int $afterId = 0, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $params = [$roomId];
        $whereAfter = '';

        if ($afterId > 0) {
            $whereAfter = 'AND m.id > ?';
            $params[] = $afterId;
        }

        $params[] = $limit;

        $sql = "
            SELECT 
                m.id,
                m.room_id,
                m.room_slug,
                m.sender_id,
                m.message,
                m.is_admin,
                m.is_pinned,
                m.pinned_at,
                m.attachment_path,
                m.attachment_name,
                m.attachment_type,
                m.created_at,
                COALESCE(NULLIF(p.company_name, ''), p.username, 'Gracz') AS sender_name,
                p.avatar_path
            FROM chat_messages m
            LEFT JOIN players p ON p.id = m.sender_id
            WHERE m.room_id = ? 
              AND m.is_deleted = 0
              {$whereAfter}
            ORDER BY m.id DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Reverse to chronological order (oldest to newest)
        // Odwroc do porzadku chronologicznego (od najstarszych do najnowszych)
        $messages = array_reverse($messages);

        return array_map([$this, 'formatMessage'], $messages);
    }

    // Send a message to a room.
    // Wyslij wiadomosc do pokoju.
    public function sendRoomMessage(int $playerId, int $roomId, string $text, bool $isAdmin = false): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('chat.err_empty_message');
        }
        if (mb_strlen($text, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('chat.err_message_too_long');
        }

        // Check if player is banned/muted
        // Sprawdz czy gracz jest wyciszony/zablokowany
        $banReason = $this->checkBanned($playerId);
        if ($banReason !== null) {
            throw new RuntimeException($banReason);
        }

        // Check rate limiting
        // Sprawdz ograniczenie czestotliwosci
        if (!$isAdmin && $this->isRateLimited($playerId)) {
            throw new RuntimeException('chat.err_rate_limited');
        }

        // Check room status
        // Sprawdz status pokoju
        $stmt = $this->db->prepare("SELECT slug, status FROM chat_rooms WHERE id = ? LIMIT 1");
        $stmt->execute([$roomId]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            throw new InvalidArgumentException('chat.err_room_not_found');
        }
        if ($room['status'] === 'archived') {
            throw new RuntimeException('chat.err_room_archived');
        }
        if ($room['status'] === 'read_only' && !$isAdmin) {
            throw new RuntimeException('chat.err_room_read_only');
        }

        // Insert message
        // Wstaw wiadomosc
        $insertStmt = $this->db->prepare("
            INSERT INTO chat_messages (
                sender_id, channel, room_id, room_slug, message, is_admin, created_at
            ) VALUES (
                ?, 'room', ?, ?, ?, ?, NOW()
            )
        ");
        $insertStmt->execute([
            $playerId,
            $roomId,
            $room['slug'],
            $text,
            $isAdmin ? 1 : 0
        ]);

        $messageId = (int) $this->db->lastInsertId();

        // Mark room as read for the sender
        // Oznacz pokoj jako przeczytany dla nadawcy
        $this->markAsRead($playerId, 'room', $roomId, $messageId);

        // Fetch formatted message
        // Pobierz sformatowana wiadomosc
        $fetchStmt = $this->db->prepare("
            SELECT 
                m.id,
                m.room_id,
                m.room_slug,
                m.sender_id,
                m.message,
                m.is_admin,
                m.is_pinned,
                m.pinned_at,
                m.attachment_path,
                m.attachment_name,
                m.attachment_type,
                m.created_at,
                COALESCE(NULLIF(p.company_name, ''), p.username, 'Gracz') AS sender_name,
                p.avatar_path
            FROM chat_messages m
            LEFT JOIN players p ON p.id = m.sender_id
            WHERE m.id = ?
            LIMIT 1
        ");
        $fetchStmt->execute([$messageId]);
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        return $this->formatMessage($row);
    }

    // -------------------------------------------------------------------------
    // Direct Threads & Messages (1:1)
    // Watki i wiadomosci prywatne (1:1)
    // -------------------------------------------------------------------------

    // Get list of direct conversation partners for player.
    // Pobierz liste rozmow prywatnych gracza z ostatnia wiadomoscia i licznikiem nieprzeczytanych.
    public function getDirectThreads(int $playerId): array
    {
        $sql = "
            SELECT 
                p.id AS partner_id,
                COALESCE(NULLIF(p.company_name, ''), p.username, 'Gracz') AS partner_name,
                p.avatar_path,
                cp.is_online,
                cp.last_active_at,
                last_msg.id AS last_message_id,
                last_msg.message AS last_message,
                last_msg.attachment_path AS last_attachment,
                last_msg.created_at AS last_message_at,
                last_msg.sender_id AS last_sender_id,
                (
                    SELECT COUNT(*)
                    FROM chat_messages unread
                    WHERE unread.channel = 'private'
                      AND unread.is_deleted = 0
                      AND unread.sender_id = p.id
                      AND unread.receiver_id = :player_id
                      AND unread.id > COALESCE((
                          SELECT rs.last_read_message_id
                          FROM chat_read_states rs
                          WHERE rs.player_id = :player_id
                            AND rs.conversation_type = 'direct'
                            AND rs.conversation_id = p.id
                          LIMIT 1
                      ), 0)
                ) AS unread_count
            FROM (
                SELECT DISTINCT 
                    CASE WHEN sender_id = :player_id THEN receiver_id ELSE sender_id END AS partner_id
                FROM chat_messages
                WHERE channel = 'private'
                  AND (sender_id = :player_id OR receiver_id = :player_id)
                  AND is_deleted = 0
            ) conv
            JOIN players p ON p.id = conv.partner_id
            LEFT JOIN chat_presence cp ON cp.player_id = p.id
            LEFT JOIN (
                SELECT m1.*, m2.pid
                FROM chat_messages m1
                JOIN (
                    SELECT 
                        CASE WHEN sender_id = :player_id THEN receiver_id ELSE sender_id END AS pid,
                        MAX(id) AS max_id
                    FROM chat_messages
                    WHERE channel = 'private'
                      AND (sender_id = :player_id OR receiver_id = :player_id)
                      AND is_deleted = 0
                    GROUP BY pid
                ) m2 ON m1.id = m2.max_id
            ) last_msg ON last_msg.pid = p.id
            ORDER BY COALESCE(last_msg.id, 0) DESC, p.id ASC
            LIMIT 50
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['player_id' => $playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $now = time();
        return array_map(function ($row) use ($now) {
            $isOnline = false;
            if (!empty($row['last_active_at'])) {
                $lastActiveTs = strtotime((string) $row['last_active_at']);
                if ($lastActiveTs !== false && ($now - $lastActiveTs) <= 300) {
                    $isOnline = true;
                }
            }

            $preview = (string) ($row['last_message'] ?? '');
            if ($preview === '' && !empty($row['last_attachment'])) {
                $preview = t('chat.attachment_photo');
            }

            return [
                'partner_id' => (int) $row['partner_id'],
                'partner_name' => (string) $row['partner_name'],
                'avatar_path' => $row['avatar_path'] ? (string) $row['avatar_path'] : null,
                'is_online' => $isOnline,
                'last_message_id' => (int) ($row['last_message_id'] ?? 0),
                'last_message' => $preview,
                'last_message_at' => $row['last_message_at'] ? (string) $row['last_message_at'] : null,
                'last_message_formatted' => $row['last_message_at'] ? $this->formatRelativeDate((string) $row['last_message_at']) : '',
                'unread_count' => (int) ($row['unread_count'] ?? 0),
            ];
        }, $rows);
    }

    // Get direct messages between two players.
    // Pobierz wiadomosci prywatne pomiedzy dwoma graczami.
    public function getDirectMessages(int $playerId, int $partnerId, int $afterId = 0, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $params = [$playerId, $partnerId, $partnerId, $playerId];
        $whereAfter = '';

        if ($afterId > 0) {
            $whereAfter = 'AND m.id > ?';
            $params[] = $afterId;
        }

        $params[] = $limit;

        $sql = "
            SELECT 
                m.id,
                m.sender_id,
                m.receiver_id,
                m.message,
                m.is_admin,
                m.attachment_path,
                m.attachment_name,
                m.attachment_type,
                m.created_at,
                COALESCE(NULLIF(p.company_name, ''), p.username, 'Gracz') AS sender_name,
                p.avatar_path
            FROM chat_messages m
            LEFT JOIN players p ON p.id = m.sender_id
            WHERE m.channel = 'private'
              AND m.is_deleted = 0
              AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
              {$whereAfter}
            ORDER BY m.id DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $messages = array_reverse($messages);
        return array_map([$this, 'formatMessage'], $messages);
    }

    // Send a direct message to a player.
    // Wyslij wiadomosc prywatna do gracza.
    public function sendDirectMessage(int $playerId, int $partnerId, string $text): array
    {
        if ($playerId === $partnerId) {
            throw new InvalidArgumentException('chat.err_cannot_message_self');
        }

        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('chat.err_empty_message');
        }
        if (mb_strlen($text, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('chat.err_message_too_long');
        }

        // Check if sender is banned
        // Sprawdz czy nadawca jest zablokowany
        $banReason = $this->checkBanned($playerId);
        if ($banReason !== null) {
            throw new RuntimeException($banReason);
        }

        // Check rate limiting
        // Sprawdz ograniczenie czestotliwosci
        if ($this->isRateLimited($playerId)) {
            throw new RuntimeException('chat.err_rate_limited');
        }

        // Check if partner exists
        // Sprawdz czy partner istnieje
        $stmt = $this->db->prepare("SELECT id FROM players WHERE id = ? LIMIT 1");
        $stmt->execute([$partnerId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('chat.err_partner_not_found');
        }

        // Insert message
        // Wstaw wiadomosc
        $insertStmt = $this->db->prepare("
            INSERT INTO chat_messages (
                sender_id, receiver_id, channel, message, created_at
            ) VALUES (
                ?, ?, 'private', ?, NOW()
            )
        ");
        $insertStmt->execute([$playerId, $partnerId, $text]);
        $messageId = (int) $this->db->lastInsertId();

        // Mark as read for the sender
        // Oznacz jako przeczytane dla nadawcy
        $this->markAsRead($playerId, 'direct', $partnerId, $messageId);

        // Fetch formatted message
        // Pobierz sformatowana wiadomosc
        $fetchStmt = $this->db->prepare("
            SELECT 
                m.id,
                m.sender_id,
                m.receiver_id,
                m.message,
                m.is_admin,
                m.attachment_path,
                m.attachment_name,
                m.attachment_type,
                m.created_at,
                COALESCE(NULLIF(p.company_name, ''), p.username, 'Gracz') AS sender_name,
                p.avatar_path
            FROM chat_messages m
            LEFT JOIN players p ON p.id = m.sender_id
            WHERE m.id = ?
            LIMIT 1
        ");
        $fetchStmt->execute([$messageId]);
        $row = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        return $this->formatMessage($row);
    }

    // -------------------------------------------------------------------------
    // Presence & Active Players
    // Obecnosc i aktywni gracze
    // -------------------------------------------------------------------------

    // Update presence for player in room.
    // Aktualizuj obecnosc gracza w pokoju.
    public function updatePresence(int $playerId, ?string $roomSlug = null): void
    {
        $roomSlug = $roomSlug ?: 'polski';
        $stmt = $this->db->prepare("
            INSERT INTO chat_presence (player_id, current_room_slug, last_active_at, is_online)
            VALUES (?, ?, NOW(), 1)
            ON DUPLICATE KEY UPDATE 
                current_room_slug = VALUES(current_room_slug),
                last_active_at = NOW(),
                is_online = 1
        ");
        $stmt->execute([$playerId, $roomSlug]);
    }

    // Get list of active players and recent players.
    // Pobierz liste aktywnych graczy oraz ostatnio aktywnych.
    public function getActivePlayers(int $currentUserId, int $limit = 20): array
    {
        $sql = "
            SELECT 
                p.id,
                COALESCE(NULLIF(p.company_name, ''), p.username, 'Gracz') AS name,
                p.avatar_path,
                cp.current_room_slug,
                cp.last_active_at,
                r.name_pl AS room_name_pl,
                r.name_en AS room_name_en,
                r.name_de AS room_name_de
            FROM chat_presence cp
            JOIN players p ON p.id = cp.player_id
            LEFT JOIN chat_rooms r ON r.slug = cp.current_room_slug
            WHERE p.status = 'active'
              AND cp.last_active_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY cp.last_active_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $now = time();
        $totalOnline = 0;

        $list = array_map(function ($row) use ($now, &$totalOnline) {
            $lastActiveTs = strtotime((string) $row['last_active_at']);
            $isOnline = ($lastActiveTs !== false && ($now - $lastActiveTs) <= 300);
            if ($isOnline) {
                $totalOnline++;
            }

            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'avatar_path' => $row['avatar_path'] ? (string) $row['avatar_path'] : null,
                'is_online' => $isOnline,
                'room_slug' => $row['current_room_slug'] ? (string) $row['current_room_slug'] : 'polski',
                'room_name' => $row['room_name_pl'] ?: ucfirst((string) ($row['current_room_slug'] ?? 'Polski')),
            ];
        }, $rows);

        return [
            'total_online' => $totalOnline,
            'players' => $list,
        ];
    }

    // -------------------------------------------------------------------------
    // Read States
    // Stany przeczytania
    // -------------------------------------------------------------------------

    // Mark room or direct conversation as read up to given message id.
    // Oznacz pokoj lub rozmowe prywatna jako przeczytana do danego ID wiadomosci.
    public function markAsRead(int $playerId, string $type, int $convId, int $lastId): void
    {
        if ($convId <= 0 || $lastId <= 0) {
            return;
        }

        $type = ($type === 'direct') ? 'direct' : 'room';

        $stmt = $this->db->prepare("
            INSERT INTO chat_read_states (
                player_id, conversation_type, conversation_id, last_read_message_id, last_read_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE 
                last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)),
                last_read_at = NOW(),
                updated_at = NOW()
        ");
        $stmt->execute([$playerId, $type, $convId, $lastId]);

        // Keep backward compatibility with chat_conversation_reads for legacy DM
        // Zachowaj kompatybilnosc wsteczna z chat_conversation_reads
        if ($type === 'direct') {
            $legacyStmt = $this->db->prepare("
                INSERT INTO chat_conversation_reads (player_id, partner_id, last_read_message_id)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id))
            ");
            $legacyStmt->execute([$playerId, $convId, $lastId]);
        }
    }

    // -------------------------------------------------------------------------
    // Moderation & Admin Operations
    // Moderacja i operacje administracyjne
    // -------------------------------------------------------------------------

    // Create a new chat room (admin only).
    // Utworz nowy pokoj czatu (tylko administrator).
    public function createRoom(
        int $adminId,
        string $slug,
        string $type,
        ?string $localeCode,
        string $namePl,
        string $nameEn,
        string $nameDe,
        ?string $descPl,
        ?string $descEn,
        ?string $descDe,
        int $sortOrder = 0,
        string $status = 'active'
    ): int {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($slug)));
        if ($slug === '') {
            throw new InvalidArgumentException('admin.chat.err_invalid_slug');
        }

        $stmt = $this->db->prepare("
            INSERT INTO chat_rooms (
                slug, type, locale_code, name_pl, name_en, name_de, 
                description_pl, description_en, description_de, 
                sort_order, status, created_by, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        $stmt->execute([
            $slug,
            in_array($type, ['language', 'custom', 'system'], true) ? $type : 'custom',
            $localeCode ?: null,
            trim($namePl),
            trim($nameEn),
            trim($nameDe),
            $descPl ? trim($descPl) : null,
            $descEn ? trim($descEn) : null,
            $descDe ? trim($descDe) : null,
            $sortOrder,
            in_array($status, ['active', 'read_only'], true) ? $status : 'active',
            $adminId,
        ]);

        $roomId = (int) $this->db->lastInsertId();

        $this->logModerationAction($adminId, 'room_created', 'room', $roomId, "Utworzono pokoj: {$slug}");
        return $roomId;
    }

    // Update existing room.
    // Zaktualizuj istniejacy pokoj.
    public function updateRoom(
        int $adminId,
        int $roomId,
        string $namePl,
        string $nameEn,
        string $nameDe,
        ?string $descPl,
        ?string $descEn,
        ?string $descDe,
        int $sortOrder,
        string $status
    ): void {
        $stmt = $this->db->prepare("
            UPDATE chat_rooms
            SET 
                name_pl = ?,
                name_en = ?,
                name_de = ?,
                description_pl = ?,
                description_en = ?,
                description_de = ?,
                sort_order = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            trim($namePl),
            trim($nameEn),
            trim($nameDe),
            $descPl ? trim($descPl) : null,
            $descEn ? trim($descEn) : null,
            $descDe ? trim($descDe) : null,
            $sortOrder,
            in_array($status, ['active', 'read_only', 'archived'], true) ? $status : 'active',
            $roomId,
        ]);

        $this->logModerationAction($adminId, 'room_updated', 'room', $roomId, "Zaktualizowano pokoj ID: {$roomId}");
    }

    // Archive room (soft delete).
    // Zarchiwizuj pokoj (miekkie usuniecie).
    public function archiveRoom(int $adminId, int $roomId, ?string $reason = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE chat_rooms 
            SET status = 'archived', archived_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$roomId]);

        $this->logModerationAction($adminId, 'room_archived', 'room', $roomId, $reason ?? 'Archiwizacja pokoju');
    }

    // Hide message (moderator action).
    // Ukryj wiadomosc (akcja moderatora).
    public function hideMessage(int $adminId, int $messageId, string $reason): void
    {
        $stmt = $this->db->prepare("
            UPDATE chat_messages 
            SET is_deleted = 1 
            WHERE id = ?
        ");
        $stmt->execute([$messageId]);

        $this->logModerationAction($adminId, 'message_hidden', 'room_message', $messageId, $reason);
    }

    // Mute player.
    // Wycisz gracza w czacie.
    public function mutePlayer(int $adminId, int $targetPlayerId, int $minutes, string $reason): void
    {
        $expiresAt = $minutes > 0 ? date('Y-m-d H:i:s', time() + ($minutes * 60)) : null;

        $stmt = $this->db->prepare("
            INSERT INTO chat_bans (player_id, reason, banned_by, expires_at)
            VALUES (?, ?, 'admin', ?)
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                banned_by = VALUES(banned_by),
                expires_at = VALUES(expires_at)
        ");
        $stmt->execute([$targetPlayerId, $reason, $expiresAt]);

        $this->logModerationAction($adminId, 'player_muted', 'player', $targetPlayerId, "Muted for {$minutes} min: {$reason}");
    }

    // Unmute player.
    // Odcisz gracza w czacie.
    public function unmutePlayer(int $adminId, int $targetPlayerId): void
    {
        $stmt = $this->db->prepare("DELETE FROM chat_bans WHERE player_id = ?");
        $stmt->execute([$targetPlayerId]);

        $this->logModerationAction($adminId, 'player_unmuted', 'player', $targetPlayerId, 'Unmuted player');
    }

    // Log moderation action.
    // Zaloguj akcje moderacyjna.
    private function logModerationAction(int $actorId, string $action, string $targetType, int $targetId, ?string $reason): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_moderation_actions (actor_id, action, target_type, target_id, reason, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$actorId, $action, $targetType, $targetId, $reason]);
        } catch (Throwable) {
            // Log fallback silently
            // Cichy fallback logowania
        }
    }

    // -------------------------------------------------------------------------
    // Utilities & Formatting
    // Narzedzia i formatowanie
    // -------------------------------------------------------------------------

    // Format single message row for API output.
    // Formatuj pojedynczy wiersz wiadomosci dla wyjscia API.
    private function formatMessage(array $m): array
    {
        $ts = strtotime((string) $m['created_at']);
        $timeStr = $ts ? date('H:i', $ts) : '';
        $dateStr = $ts ? date('Y-m-d', $ts) : '';

        return [
            'id' => (int) $m['id'],
            'room_id' => isset($m['room_id']) ? (int) $m['room_id'] : null,
            'room_slug' => $m['room_slug'] ?? null,
            'sender_id' => (int) $m['sender_id'],
            'sender_name' => (string) $m['sender_name'],
            'avatar_path' => $m['avatar_path'] ? (string) $m['avatar_path'] : null,
            'avatar_letter' => mb_strtoupper(mb_substr($m['sender_name'], 0, 1, 'UTF-8'), 'UTF-8'),
            'message' => (string) $m['message'],
            'is_admin' => (int) ($m['is_admin'] ?? 0) === 1,
            'is_pinned' => (int) ($m['is_pinned'] ?? 0) === 1,
            'attachment_path' => $m['attachment_path'] ?? null,
            'attachment_name' => $m['attachment_name'] ?? null,
            'attachment_type' => $m['attachment_type'] ?? null,
            'created_at' => (string) $m['created_at'],
            'time' => $timeStr,
            'date' => $dateStr,
            'date_label' => $this->formatDateSeparator($dateStr),
        ];
    }

    // Check if player is banned.
    // Sprawdz czy gracz jest zablokowany.
    public function checkBanned(int $playerId): ?string
    {
        try {
            $stmt = $this->db->prepare("SELECT reason, expires_at FROM chat_bans WHERE player_id = ? LIMIT 1");
            $stmt->execute([$playerId]);
            $ban = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ban) {
                return null;
            }

            if ($ban['expires_at'] !== null && strtotime((string) $ban['expires_at']) <= time()) {
                $this->db->prepare("DELETE FROM chat_bans WHERE player_id = ?")->execute([$playerId]);
                return null;
            }

            return $ban['reason'] ?: 'Blokada czatu';
        } catch (Throwable) {
            return null;
        }
    }

    // Check rate limit: max 5 messages in 10 seconds.
    // Sprawdz limit czestotliwosci: max 5 wiadomosci w 10 sekund.
    public function isRateLimited(int $playerId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM chat_messages 
            WHERE sender_id = ? 
              AND created_at > DATE_SUB(NOW(), INTERVAL 10 SECOND)
        ");
        $stmt->execute([$playerId]);
        return ((int) $stmt->fetchColumn()) >= 5;
    }

    // Format human-friendly relative date.
    // Formatuj czytelna date wzgledna.
    private function formatRelativeDate(string $datetime): string
    {
        $ts = strtotime($datetime);
        if (!$ts) {
            return '';
        }

        $now = time();
        $diff = $now - $ts;
        $today = date('Y-m-d', $now);
        $msgDate = date('Y-m-d', $ts);

        if ($msgDate === $today) {
            return date('H:i', $ts);
        }

        $yesterday = date('Y-m-d', $now - 86400);
        if ($msgDate === $yesterday) {
            return t('chat.yesterday_relative');
        }

        $days = (int) floor($diff / 86400);
        if ($days <= 7) {
            return t('chat.days_ago', ['count' => $days]);
        }

        return date('d.m', $ts);
    }

    // Format date separator label (DZISIAJ, WCZORAJ, etc.)
    // Formatuj etykiete separatora daty (DZISIAJ, WCZORAJ, itp.)
    private function formatDateSeparator(string $dateStr): string
    {
        $today = date('Y-m-d');
        if ($dateStr === $today) {
            return t('chat.date_today');
        }
        $yesterday = date('Y-m-d', time() - 86400);
        if ($dateStr === $yesterday) {
            return t('chat.date_yesterday');
        }

        return $dateStr;
    }
}
