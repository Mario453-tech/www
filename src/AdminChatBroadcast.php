<?php
declare(strict_types=1);

/** Validate and save a broadcast. / Waliduj i zapisz komunikat. */
final class AdminChatBroadcast
{
    public static function send(PDO $db, string $input): bool
    {
        if (strlen($input) > 16000) {
            return false;
        }
        $html = ChatMessageHtml::sanitize($input);
        $text = ChatMessageHtml::plainText($html);
        if ($text === '' || mb_strlen($html) > 500) {
            return false;
        }
        $stmt = $db->prepare("INSERT INTO chat_messages (sender_id, username, message, channel, is_admin, room_id, room_slug)
            SELECT NULL, '[ADMIN]', ?, 'global', 1, id, slug FROM chat_rooms WHERE slug = 'polski' AND status != 'archived'");
        $stmt->execute([$html]);
        return $stmt->rowCount() === 1;
    }
}
