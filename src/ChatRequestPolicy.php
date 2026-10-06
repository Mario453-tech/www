<?php
declare(strict_types=1);

final class ChatRequestPolicy
{
    public static function allows(string $permission, bool $administrator): bool
    {
        // Existing admins own chat capabilities. / Obecni administratorzy maja uprawnienia czatu.
        return $administrator && in_array($permission, ['chat.rooms.manage', 'chat.moderate'], true);
    }

    public static function status(string $method, string $action, bool $authenticated, bool $csrf): int
    {
        if (!$authenticated) {
            return 401;
        }
        $read = ['', 'init', 'rooms', 'room_messages', 'direct_threads', 'direct_messages', 'presence', 'conversations', 'players', 'dm_status'];
        $write = ['', 'send', 'send_room', 'send_direct', 'mark_read', 'heartbeat', 'delete_attachment', 'report', 'dm_upload_chunk'];
        if (($method === 'GET' && !in_array($action, $read, true))
            || ($method === 'POST' && !in_array($action, $write, true))
            || !in_array($method, ['GET', 'POST'], true)) {
            return 405;
        }
        return $method === 'POST' && !$csrf ? 419 : 200;
    }

    /** @return array{key:string,status:int} */
    public static function error(Throwable $e): array
    {
        $key = $e->getMessage();
        $allowed = ['chat.err_empty_message', 'chat.err_message_too_long', 'chat.err_room_not_found',
            'chat.err_room_archived', 'chat.err_room_read_only', 'chat.err_cannot_message_self',
            'chat.err_partner_not_found', 'chat.err_invalid_read', 'chat.err_banned',
            'chat.err_rate_limited', 'chat.err_thread_limit'];
        if ($e instanceof PDOException || !in_array($key, $allowed, true)) {
            return ['key' => 'common.app_error', 'status' => 500];
        }
        return ['key' => $key, 'status' => in_array($key, ['chat.err_rate_limited', 'chat.err_thread_limit'], true) ? 429
            : (in_array($key, ['chat.err_banned', 'chat.err_room_archived', 'chat.err_room_read_only'], true) ? 403 : 422)];
    }
}
