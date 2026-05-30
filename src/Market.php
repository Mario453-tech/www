<?php

class Market
{
    private PDO $db;
    
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
