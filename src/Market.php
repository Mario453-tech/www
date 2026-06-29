<?php

class Market
{
    private PDO $db;

    /**
     * Gwarantuje istnienie wiersza singleton market_state (id = 1).
     *
     * Cron (MarketTick), web (Market::getState) oraz API mobilne czytaja/zapisuja
     * WYLACZNIE wiersz id = 1. Schemat (ci-schema.sql / swiezy deploy / restore)
     * tworzy tabele, ale NIE wstawia tego wiersza — bez niego cena ropy zwraca 0
     * (fallback), a cron loguje "market_state row id=1 missing". Ta metoda wstawia
     * brakujacy wiersz idempotentnie (INSERT IGNORE — nie nadpisuje istniejacej ceny).
     *
     * Ensures the singleton market_state row (id = 1) exists. Cron, web and the
     * mobile API all operate solely on id = 1, but the schema never seeds it, so a
     * fresh/restored DB returns oil_price = 0. INSERT IGNORE never overwrites an
     * existing price; it only fills the gap. Runs once per process.
     */
    public static function ensureState(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $db = Database::getInstance()->getConnection();

            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                return;
            }

            // current_price = 70 to bezpieczna wartosc startowa uzywana tez jako
            // awaryjny fallback w cron/tick.php; cron zaktualizuje ja przy pierwszym ticku.
            // 70 mirrors the safe oil-price fallback in cron/tick.php; the tick overwrites it.
            $db->exec(
                "INSERT IGNORE INTO `market_state`
                    (`id`, `base_price`, `current_price`, `volatility`, `last_market_tick_at`)
                 VALUES (1, 100, 70, 1, NOW())"
            );
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Market', 'ensureState failed', $e);
            }
        }
    }

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Market', '__construct failed', $e);
            }
            throw $e;
        }
    }
    
 /** @return array<string, mixed>|false|null */
    public function getState(): array|false|null
    {
        try {
            $stmt = $this->db->query("SELECT * FROM market_state WHERE id = 1");
            return $stmt->fetch();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Market', 'getState failed', $e);
            }
            return null;
        }
    }
    
    public function getCurrentPrice(): float
    {
        try {
            $state = $this->getState();
            return (float)($state['current_price'] ?? 0);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Market', 'getCurrentPrice failed', $e);
            }
            return 0.0;
        }
    }
}
