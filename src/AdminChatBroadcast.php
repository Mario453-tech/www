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
        $db->prepare("INSERT INTO chat_messages (sender_id, username, message, channel, is_admin) VALUES (NULL, '[ADMIN]', ?, 'global', 1)")
            ->execute([$html]);
        return true;
    }
}
