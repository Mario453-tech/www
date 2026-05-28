<?php

class CSRF
{
    public static function generateToken(): string
    {
        try {
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CSRF', 'generateToken failed', $e);
            }
            $fallback = hash('sha256', uniqid((string)mt_rand(), true));
            $_SESSION['csrf_token'] = $fallback;
            return $fallback;
        }
    }
    
    public static function validateToken(mixed $token): bool
    {
        try {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CSRF', 'validateToken failed', $e);
            }
            return false;
        }
    }
    
    public static function field(): string
    {
        try {
            return '<input type="hidden" name="csrf_token" value="' . self::generateToken() . '">';
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('CSRF', 'field generation failed', $e);
            }
            return '<input type="hidden" name="csrf_token" value="">';
        }
    }
}
