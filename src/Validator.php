<?php

class Validator
{
    public static function sanitize(mixed $input): string
    {
        try {
            return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Validator', 'sanitize failed', $e);
            }
            return '';
        }
    }
    
    public static function validateEmail(mixed $email): bool
    {
        try {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Validator', 'validateEmail failed', $e);
            }
            return false;
        }
    }
    
    public static function validateInt(mixed $value, int $min = 0, int $max = PHP_INT_MAX): bool
    {
        try {
            $value = filter_var($value, FILTER_VALIDATE_INT);
            return $value !== false && $value >= $min && $value <= $max;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Validator', 'validateInt failed', $e, ['min' => $min, 'max' => $max]);
            }
            return false;
        }
    }
    
    public static function validateFloat(mixed $value, float $min = 0): bool
    {
        try {
            $value = filter_var($value, FILTER_VALIDATE_FLOAT);
            return $value !== false && $value >= $min;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Validator', 'validateFloat failed', $e, ['min' => $min]);
            }
            return false;
        }
    }
}
