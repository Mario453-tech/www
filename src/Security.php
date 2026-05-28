<?php

class Security
{
    public static function setHeaders(): void
    {
        try {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Security', 'setHeaders failed', $e);
            }
        }
    }
    
    public static function escape(mixed $string): string
    {
        try {
            return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Security', 'escape failed', $e);
            }
            return (string)$string;
        }
    }
}