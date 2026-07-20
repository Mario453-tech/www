<?php

/**
 * Morale and Strikes Schema Bootstrap
 * Handles the creation of tables and columns required for the Morale System.
 */

function ensureMoraleSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!class_exists('Database', false)) {
        return;
    }

    try {
        $db = Database::getInstance()->getConnection();
        if (!$db) {
            return;
        }

        // Tabela logow zmian morale
        $db->exec("
            CREATE TABLE IF NOT EXISTS staff_morale_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                technical_staff_id INT NOT NULL,
                change_amount INT NOT NULL,
                reason VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_staff_morale (technical_staff_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Tabela trwajacych i zakonczonych strajkow
        $db->exec("
            CREATE TABLE IF NOT EXISTS staff_strikes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                technical_staff_id INT NOT NULL,
                start_time TIMESTAMP NOT NULL,
                end_time TIMESTAMP NULL DEFAULT NULL,
                reason VARCHAR(255) NOT NULL,
                INDEX idx_staff_strike_active (technical_staff_id, end_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Dodanie kolumn morale do technical_staff
        try {
            $db->exec("ALTER TABLE technical_staff ADD COLUMN base_morale INT NOT NULL DEFAULT 100");
        } catch (PDOException $e) {
            // Ignorujemy blad jezeli kolumna juz istnieje (SQLSTATE 42S21)
        }

        try {
            $db->exec("ALTER TABLE technical_staff ADD COLUMN current_morale INT NOT NULL DEFAULT 100");
        } catch (PDOException $e) {
        }
    } catch (Throwable $e) {
        if (class_exists('GameLog', false)) {
            GameLog::error('init', 'Failed to ensure Morale schema', $e);
        }
    }
}
