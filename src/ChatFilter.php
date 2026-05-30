<?php

/**
 * ChatFilter - blocked words filter for global chat only.
 * PL: ChatFilter - filtr zablokowanych slow tylko dla czatu globalnego.
 */
class ChatFilter
{
 /** @var array<int, array{word: string, replacement: string}>|null */
    private static ?array $words = null;

 // Load active blocked words from DB and keep them for the current request.
 // PL: Laduje aktywne zablokowane slowa z bazy i trzyma je dla biezacego requestu.
    private static function load(PDO $db): void
    {
        if (self::$words !== null) {
            return;
        }

        try {
            $rows = $db->query(
                "SELECT word, replacement FROM chat_blocked_words WHERE active = 1 ORDER BY LENGTH(word) DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            self::$words = $rows ?: [];
        } catch (Throwable $e) {
            self::$words = [];
        }
    }

 // Check whether the message contains a blocked word.
 // PL: Sprawdza, czy wiadomosc zawiera zablokowane slowo.
    public static function contains(PDO $db, string $message): bool
    {
        self::load($db);
        $lower = mb_strtolower($message);
        foreach (self::$words as $entry) {
            if (mb_strpos($lower, mb_strtolower($entry['word'])) !== false) {
                return true;
            }
        }
        return false;
    }

 // Replace blocked words with their configured replacement strings.
 // PL: Zastepuje zablokowane slowa skonfigurowanymi zamiennikami.
    public static function filter(PDO $db, string $message): string
    {
        self::load($db);
        foreach (self::$words as $entry) {
            $pattern = '/' . preg_quote($entry['word'], '/') . '/iu';
            $message = preg_replace($pattern, $entry['replacement'], $message);
        }
        return $message;
    }

 // Clear in-request cache after admin updates the blocked words list.
 // PL: Czysci cache requestu po aktualizacji listy zablokowanych slow przez admina.
    public static function clearCache(): void
    {
        self::$words = null;
    }
}
