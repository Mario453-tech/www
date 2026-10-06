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
    }

    // -------------------------------------------------------------------------
    // Rooms Management & Queries
    // Zarzadzanie i zapytania o pokoje
    // -------------------------------------------------------------------------

    // Get list of active rooms with member count and unread count for player.
    // Pobierz liste aktywnych pokoi z liczba czlonkow i nieprzeczytanych wiadomosci dla gracza.
    /** @return list<array<string, mixed>> */
    public function getRooms(int $playerId, string $locale = 'pl'): array
    {
        $locale = $this->normalizeLocale($locale);

        $sql = "
            SELECT 
                r.id,
                r.slug,
                r.type,
                r.locale_code,
                COALESCE(MAX(CASE WHEN rt.locale = :locale_name THEN rt.name END),
                    MAX(CASE WHEN rt.locale = 'en' THEN rt.name END), r.name_pl) AS name,
                COALESCE(MAX(CASE WHEN rt.locale = :locale_desc THEN rt.description END),
                    MAX(CASE WHEN rt.locale = 'en' THEN rt.description END), r.description_pl) AS description,
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
                      AND (cm.sender_id IS NULL OR cm.sender_id != :player_id)
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
            LEFT JOIN chat_room_translations rt ON rt.room_id = r.id
            WHERE r.status != 'archived'
            GROUP BY r.id
            ORDER BY r.sort_order ASC, r.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['player_id' => $playerId, 'locale_name' => $locale, 'locale_desc' => $locale]);
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
    /** @return array<string, mixed>|null */
    public function getRoomBySlug(string $slug, string $locale = 'pl'): ?array
    {
        $locale = $this->normalizeLocale($locale);

        $stmt = $this->db->prepare("
            SELECT 
                r.id,
                r.slug,
                r.type,
                r.locale_code,
                COALESCE(MAX(CASE WHEN rt.locale = :locale_name THEN rt.name END),
                    MAX(CASE WHEN rt.locale = 'en' THEN rt.name END), r.name_pl) AS name,
                COALESCE(MAX(CASE WHEN rt.locale = :locale_desc THEN rt.description END),
                    MAX(CASE WHEN rt.locale = 'en' THEN rt.description END), r.description_pl) AS description,
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
            LEFT JOIN chat_room_translations rt ON rt.room_id = r.id
            WHERE r.slug = :slug AND r.status != 'archived'
            GROUP BY r.id
            LIMIT 1
        ");
        $stmt->execute(['locale_name' => $locale, 'locale_desc' => $locale, 'slug' => $slug]);
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
    /** @return list<array<string, mixed>> */
    public function getRoomMessages(int $roomId, int $afterId = 0, int $limit = 50, int $beforeId = 0): array
    {
        $limit = max(1, min(100, $limit));
        $params = [$roomId];
        $whereAfter = '';

        if ($afterId > 0) {
            $whereAfter = 'AND m.id > ?';
            $params[] = $afterId;
        }
        if ($beforeId > 0) {
            $whereAfter .= ' AND m.id < ?';
            $params[] = $beforeId;
        }
        $order = $afterId > 0 ? 'ASC' : 'DESC';

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
            JOIN chat_rooms r ON r.id = m.room_id AND r.status != 'archived'
            LEFT JOIN players p ON p.id = m.sender_id
            WHERE m.room_id = ? 
              AND m.is_deleted = 0
              {$whereAfter}
            ORDER BY m.id {$order}
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Reverse to chronological order (oldest to newest)
        // Odwroc do porzadku chronologicznego (od najstarszych do najnowszych)
        $messages = $afterId > 0 ? $messages : array_reverse($messages);

        return array_map([$this, 'formatMessage'], $messages);
    }

    // Send a message to a room.
    // Wyslij wiadomosc do pokoju.
    /** @return array<string, mixed> */
    public function sendRoomMessage(int $playerId, int $roomId, string $text, bool $isAdmin = false): array
    {
        return $this->withSenderLock($playerId, fn () => $this->insertRoomMessage($playerId, $roomId, $text, $isAdmin));
    }

    /** @return array<string, mixed> */
    private function insertRoomMessage(int $playerId, int $roomId, string $text, bool $isAdmin): array
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
            throw new RuntimeException('chat.err_banned');
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
                sender_id, username, channel, room_id, room_slug, message, is_admin, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");
        $insertStmt->execute([
            $playerId,
            $this->senderUsername($playerId),
            $room['slug'] === 'polski' ? 'global' : 'room',
            $roomId,
            $room['slug'],
            $text,
            $isAdmin ? 1 : 0
        ]);

        $messageId = (int) $this->db->lastInsertId();

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
    /** @return list<array<string, mixed>> */
    public function getDirectThreads(int $playerId, int $page = 1): array
    {
        $offset = (max(1, min(10000, $page)) - 1) * 50;
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
            LIMIT 50 OFFSET {$offset}
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

    public function getDirectUnreadCount(int $playerId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM chat_messages m
            LEFT JOIN chat_read_states r ON r.player_id = m.receiver_id AND r.conversation_type = 'direct'
                AND r.conversation_id = m.sender_id
            WHERE m.channel = 'private' AND m.receiver_id = ? AND m.is_deleted = 0
                AND m.id > COALESCE(r.last_read_message_id, 0)");
        $stmt->execute([$playerId]);
        return (int) $stmt->fetchColumn();
    }

    // Get direct messages between two players.
    // Pobierz wiadomosci prywatne pomiedzy dwoma graczami.
    /** @return list<array<string, mixed>> */
    public function getDirectMessages(int $playerId, int $partnerId, int $afterId = 0, int $limit = 50, int $beforeId = 0): array
    {
        $limit = max(1, min(100, $limit));
        $params = [$playerId, $partnerId, $partnerId, $playerId];
        $whereAfter = '';

        if ($afterId > 0) {
            $whereAfter = 'AND m.id > ?';
            $params[] = $afterId;
        }
        if ($beforeId > 0) {
            $whereAfter .= ' AND m.id < ?';
            $params[] = $beforeId;
        }
        $order = $afterId > 0 ? 'ASC' : 'DESC';

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
            ORDER BY m.id {$order}
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $messages = $afterId > 0 ? $messages : array_reverse($messages);
        return array_map([$this, 'formatMessage'], $messages);
    }

    // Send a direct message to a player.
    // Wyslij wiadomosc prywatna do gracza.
    /** @return array<string, mixed> */
    public function sendDirectMessage(int $playerId, int $partnerId, string $text): array
    {
        return $this->withSenderLock($playerId, fn () => $this->insertDirectMessage($playerId, $partnerId, $text));
    }

    /** @param array<string, mixed> $attachment
     * @return array<string, mixed>
     */
    public function sendDirectAttachment(int $playerId, int $partnerId, string $text, array $attachment): array
    {
        return $this->withSenderLock($playerId, function () use ($playerId, $partnerId, $text, $attachment): array {
            $message = $this->insertDirectMessage($playerId, $partnerId, $text !== '' ? $text : t('chat.attachment_photo'));
            $stmt = $this->db->prepare("UPDATE chat_messages SET message = ?, attachment_path = ?, attachment_name = ?,
                attachment_type = ?, attachment_size = ? WHERE id = ? AND sender_id = ? AND receiver_id = ? AND channel = 'private'");
            $stmt->execute([$text, $attachment['attachment_path'], $attachment['attachment_name'], $attachment['attachment_type'],
                $attachment['attachment_size'], $message['id'], $playerId, $partnerId]);
            return $message;
        });
    }

    /** @return array<string, mixed> */
    private function insertDirectMessage(int $playerId, int $partnerId, string $text): array
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
            throw new RuntimeException('chat.err_banned');
        }

        // Check rate limiting
        // Sprawdz ograniczenie czestotliwosci
        if ($this->isRateLimited($playerId)) {
            throw new RuntimeException('chat.err_rate_limited');
        }

        // Check if partner exists
        // Sprawdz czy partner istnieje
        $stmt = $this->db->prepare("SELECT id FROM players WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$partnerId]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('chat.err_partner_not_found');
        }

        // Insert message
        // Wstaw wiadomosc
        $insertStmt = $this->db->prepare("
            INSERT INTO chat_messages (
                sender_id, receiver_id, username, channel, message, created_at
            ) VALUES (
                ?, ?, ?, 'private', ?, NOW()
            )
        ");
        $existing = $this->db->prepare("SELECT id FROM chat_messages WHERE channel = 'private'
            AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) LIMIT 1");
        $existing->execute([$playerId, $partnerId, $partnerId, $playerId]);
        if (!$existing->fetchColumn()) {
            $count = $this->db->prepare("SELECT COUNT(DISTINCT receiver_id) FROM chat_messages
                WHERE sender_id = ? AND channel = 'private' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $count->execute([$playerId]);
            if ((int) $count->fetchColumn() >= 10) {
                throw new RuntimeException('chat.err_thread_limit');
            }
        }
        $insertStmt->execute([$playerId, $partnerId, $this->senderUsername($playerId), $text]);
        $messageId = (int) $this->db->lastInsertId();

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
        if ($roomSlug !== null && $this->getRoomBySlug($roomSlug) === null) {
            throw new InvalidArgumentException('chat.err_room_not_found');
        }
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
    /** @return array{total_online:int,players:list<array<string,mixed>>} */
    public function getActivePlayers(int $currentUserId, int $limit = 20, string $locale = 'pl'): array
    {
        $limit = max(1, min(100, $limit));
        $locale = $this->normalizeLocale($locale);
        $sql = "
            SELECT 
                p.id,
                COALESCE(NULLIF(p.company_name, ''), p.username, 'Gracz') AS name,
                p.avatar_path,
                cp.current_room_slug,
                cp.is_online,
                cp.last_active_at,
                COALESCE(MAX(CASE WHEN rt.locale = :locale THEN rt.name END),
                    MAX(CASE WHEN rt.locale = 'en' THEN rt.name END), r.name_pl, '') AS room_name
            FROM chat_presence cp
            JOIN players p ON p.id = cp.player_id
            LEFT JOIN chat_rooms r ON r.slug = cp.current_room_slug
            LEFT JOIN chat_room_translations rt ON rt.room_id = r.id
            WHERE p.status = 'active'
              AND cp.last_active_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY p.id, p.company_name, p.username, p.avatar_path, cp.current_room_slug,
                cp.is_online, cp.last_active_at, r.name_pl
            ORDER BY cp.last_active_at DESC
            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['locale' => $locale]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $now = time();
        $totalOnline = (int) $this->db->query("SELECT COUNT(*) FROM chat_presence cp
            JOIN players p ON p.id = cp.player_id WHERE p.status = 'active' AND cp.is_online = 1
            AND cp.last_active_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();

        $list = array_map(function ($row) use ($now) {
            $lastActiveTs = strtotime((string) $row['last_active_at']);
            $isOnline = (bool) $row['is_online'] && ($lastActiveTs !== false && ($now - $lastActiveTs) <= 300);

            return [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'avatar_path' => $row['avatar_path'] ? (string) $row['avatar_path'] : null,
                'is_online' => $isOnline,
                'room_slug' => $row['current_room_slug'],
                'room_name' => (string) ($row['room_name'] ?? ''),
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
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            if ($convId <= 0 || $lastId <= 0 || !in_array($type, ['room', 'direct'], true)) {
                throw new InvalidArgumentException('chat.err_invalid_read');
            }
            if ($type === 'direct') {
                $check = $this->db->prepare("SELECT id FROM chat_messages WHERE id = ? AND channel = 'private'
                    AND is_deleted = 0 AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))");
                $check->execute([$lastId, $playerId, $convId, $convId, $playerId]);
            } else {
                $check = $this->db->prepare("SELECT m.id FROM chat_messages m JOIN chat_rooms r ON r.id = m.room_id
                    WHERE m.id = ? AND m.room_id = ? AND m.is_deleted = 0 AND r.status != 'archived'");
                $check->execute([$lastId, $convId]);
            }
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('chat.err_invalid_read');
            }

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

            // Keep backward compatibility with chat_conversation_reads for legacy DM.
            // Zachowaj kompatybilnosc wsteczna z chat_conversation_reads.
            if ($type === 'direct') {
                $legacyStmt = $this->db->prepare("
                    INSERT INTO chat_conversation_reads (player_id, partner_id, last_read_message_id)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id))
                ");
                $legacyStmt->execute([$playerId, $convId, $lastId]);
            }
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Moderation & Admin Operations
    // Moderacja i operacje administracyjne
    // -------------------------------------------------------------------------

    // Create a new chat room (admin only).
    // Utworz nowy pokoj czatu (tylko administrator).
    /** @param list<array{locale:mixed,name:mixed,description?:mixed}> $translations */
    public function createRoom(
        int $adminId,
        string $slug,
        string $type,
        ?string $localeCode,
        array $translations,
        int $sortOrder = 0,
        string $status = 'active'
    ): int {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 50) {
            throw new InvalidArgumentException('admin.chat.err_invalid_slug');
        }
        if ($sortOrder < 0 || $sortOrder > 9999 || !in_array($status, ['active', 'read_only'], true)) {
            throw new InvalidArgumentException('admin.chat.err_room_fields');
        }
        $localeCode = $localeCode !== null && trim($localeCode) !== ''
            ? strtolower(str_replace('_', '-', trim($localeCode))) : null;
        $this->validateRoomMetadata($type, $localeCode, $status);
        $translations = $this->normalizeTranslations($translations);
        $legacy = $this->legacyTranslations($translations);
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_rooms (
                    slug, type, locale_code, name_pl, name_en, name_de,
                    description_pl, description_en, description_de,
                    sort_order, status, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $slug, $type, $localeCode,
                $legacy['pl']['name'], $legacy['en']['name'], $legacy['de']['name'],
                $legacy['pl']['description'], $legacy['en']['description'], $legacy['de']['description'],
                $sortOrder, $status, $adminId,
            ]);
            $roomId = (int) $this->db->lastInsertId();
            $this->saveRoomTranslations($roomId, $translations, false);
            $this->logModerationAction($adminId, 'room_created', 'room', $roomId, "Created room: {$slug}");
            if ($ownsTransaction) $this->db->commit();
            return $roomId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // Update existing room.
    // Zaktualizuj istniejacy pokoj.
    /** @param list<array{locale:mixed,name:mixed,description?:mixed}> $translations */
    public function updateRoom(
        int $adminId,
        int $roomId,
        array $translations,
        int $sortOrder,
        string $status,
        string $type = 'custom',
        ?string $localeCode = null
    ): void {
        if ($sortOrder < 0 || $sortOrder > 9999) throw new InvalidArgumentException('admin.chat.err_room_fields');
        $localeCode = $localeCode !== null && trim($localeCode) !== ''
            ? strtolower(str_replace('_', '-', trim($localeCode))) : null;
        $this->validateRoomMetadata($type, $localeCode, $status);
        $translations = $this->normalizeTranslations($translations);
        $legacy = $this->legacyTranslations($translations);
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $exists = $this->db->prepare('SELECT id FROM chat_rooms WHERE id = ? FOR UPDATE');
            $exists->execute([$roomId]);
            if (!$exists->fetchColumn()) throw new InvalidArgumentException('chat.err_room_not_found');
            $stmt = $this->db->prepare("
                UPDATE chat_rooms SET
                    name_pl = ?, name_en = ?, name_de = ?,
                    description_pl = ?, description_en = ?, description_de = ?, sort_order = ?,
                    archived_at = CASE WHEN ? = 'archived' THEN COALESCE(archived_at, NOW()) ELSE NULL END,
                    status = ?, type = ?, locale_code = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $legacy['pl']['name'], $legacy['en']['name'], $legacy['de']['name'],
                $legacy['pl']['description'], $legacy['en']['description'], $legacy['de']['description'],
                $sortOrder, $status, $status, $type, $localeCode, $roomId,
            ]);
            $this->saveRoomTranslations($roomId, $translations, true);
            $this->logModerationAction($adminId, 'room_updated', 'room', $roomId, "Updated room #{$roomId}");
            if ($ownsTransaction) $this->db->commit();
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function validateRoomMetadata(string $type, ?string $locale, string $status): void
    {
        if (!in_array($type, ['language', 'custom', 'system'], true)
            || !in_array($status, ['active', 'read_only', 'archived'], true)
            || ($locale && !preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $locale))) {
            throw new InvalidArgumentException('admin.chat.err_room_fields');
        }
    }

    /**
     * @param list<int> $roomIds
     * @return array<int, list<array{locale:string,name:string,description:?string}>>
     */
    public function getRoomTranslations(array $roomIds): array
    {
        $roomIds = array_values(array_unique(array_filter(array_map('intval', $roomIds), static fn (int $id): bool => $id > 0)));
        if ($roomIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));
        $stmt = $this->db->prepare("SELECT room_id, locale, name, description
            FROM chat_room_translations WHERE room_id IN ({$placeholders}) ORDER BY locale ASC");
        $stmt->execute($roomIds);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int) $row['room_id']][] = [
                'locale' => (string) $row['locale'],
                'name' => (string) $row['name'],
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
            ];
        }
        return $result;
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        return preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $locale) ? $locale : 'en';
    }

    /**
     * @param list<array{locale:mixed,name:mixed,description?:mixed}> $rows
     * @return array<string, array{name:string,description:?string}>
     */
    private function normalizeTranslations(array $rows): array
    {
        $translations = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new InvalidArgumentException('admin.chat.err_room_fields');
            $rawLocale = trim((string) ($row['locale'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            if ($rawLocale === '' && $name === '' && $description === '') continue;
            $locale = strtolower(str_replace('_', '-', $rawLocale));
            if (!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $locale)
                || isset($translations[$locale]) || $name === '' || mb_strlen($name) > 100
                || mb_strlen($description) > 255) {
                throw new InvalidArgumentException('admin.chat.err_room_fields');
            }
            $translations[$locale] = ['name' => $name, 'description' => $description !== '' ? $description : null];
        }
        if ($translations === []) throw new InvalidArgumentException('admin.chat.err_room_fields');
        return $translations;
    }

    /**
     * @param array<string, array{name:string,description:?string}> $translations
     * @return array{pl:array{name:string,description:?string},en:array{name:string,description:?string},de:array{name:string,description:?string}}
     */
    private function legacyTranslations(array $translations): array
    {
        $first = reset($translations);
        $fallback = $translations['en'] ?? $translations['pl'] ?? $first;
        return [
            'pl' => $translations['pl'] ?? $fallback,
            'en' => $translations['en'] ?? $fallback,
            'de' => $translations['de'] ?? $fallback,
        ];
    }

    /** @param array<string, array{name:string,description:?string}> $translations */
    private function saveRoomTranslations(int $roomId, array $translations, bool $replace): void
    {
        $stmt = $this->db->prepare("INSERT INTO chat_room_translations
            (room_id, locale, name, description, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), updated_at = NOW()");
        foreach ($translations as $locale => $translation) {
            $stmt->execute([$roomId, $locale, $translation['name'], $translation['description']]);
        }
        if ($replace) {
            $placeholders = implode(',', array_fill(0, count($translations), '?'));
            $delete = $this->db->prepare("DELETE FROM chat_room_translations
                WHERE room_id = ? AND locale NOT IN ({$placeholders})");
            $delete->execute(array_merge([$roomId], array_keys($translations)));
        }
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

        $this->logModerationAction($adminId, 'room_archived', 'room', $roomId, $reason ?? 'Archived room');
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
        $stmt = $this->db->prepare("
                INSERT INTO chat_moderation_actions (actor_id, action, target_type, target_id, reason, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$actorId, $action, $targetType, $targetId, $reason]);
    }

    public function recordAdminAction(int $adminId, string $action, int $targetId): void
    {
        $targetType = str_contains($action, 'room') ? 'room' : 'player';
        if (in_array($action, ['delete_msg', 'pin_msg', 'unpin_msg'], true)) {
            $stmt = $this->db->prepare('SELECT channel FROM chat_messages WHERE id = ?');
            $stmt->execute([$targetId]);
            $targetType = $stmt->fetchColumn() === 'private' ? 'direct_message' : 'room_message';
        }
        $this->logModerationAction($adminId, $action, $targetType, $targetId, 'Applied via admin panel');
    }

    // -------------------------------------------------------------------------
    // Utilities & Formatting
    // Narzedzia i formatowanie
    // -------------------------------------------------------------------------

    // Format single message row for API output.
    // Formatuj pojedynczy wiersz wiadomosci dla wyjscia API.
    /** @param array<string, mixed> $m
     * @return array<string, mixed>
     */
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
            $stmt = $this->db->prepare("SELECT reason, expires_at FROM chat_bans WHERE player_id = ? LIMIT 1");
            $stmt->execute([$playerId]);
            $ban = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ban) {
                return null;
            }

            if ($ban['expires_at'] !== null && strtotime((string) $ban['expires_at']) <= time()) {
                return null;
            }

            return $ban['reason'] ?: 'chat.err_banned';
    }

    /** @return array<string, mixed> */
    private function withSenderLock(int $playerId, callable $operation): array
    {
        $owns = !$this->db->inTransaction();
        if ($owns) {
            $this->db->beginTransaction();
        }
        try {
            // Serialize rate checks with writes. / Serializuj limit z zapisem.
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $lock = $this->db->prepare("SELECT id FROM players WHERE id = ? AND status = 'active'" . $suffix);
            $lock->execute([$playerId]);
            if (!$lock->fetchColumn()) {
                throw new InvalidArgumentException('chat.err_partner_not_found');
            }
            $result = $operation();
            if ($owns) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($owns && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function senderUsername(int $playerId): string
    {
        $stmt = $this->db->prepare('SELECT username FROM players WHERE id = ?');
        $stmt->execute([$playerId]);
        return (string) $stmt->fetchColumn();
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
