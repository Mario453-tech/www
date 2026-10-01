<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$locale = $argv[1] ?? 'pl';
if (!in_array($locale, ['pl', 'en'], true)) {
    $locale = 'pl';
}
$_SESSION = ['locale' => $locale];
$_COOKIE = [];
$_SERVER['REQUEST_URI'] = '/';
$translations = array_replace(
    require $root . '/lang/' . $locale . '/common.php',
    require $root . '/lang/' . $locale . '/home.php'
);

function t(string $key, array $replace = []): string
{
    global $translations;
    $value = $translations[$key] ?? $key;
    foreach ($replace as $name => $replacement) {
        $value = str_replace([':' . $name, '{' . $name . '}'], (string) $replacement, $value);
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function asset(string $path): string
{
    return $path . '?v=fixture';
}

function url(string $name): string
{
    return match ($name) {
        'login' => '/login',
        'register' => '/register',
        'language' => '/language',
        default => '/' . $name,
    };
}

final class CSRF
{
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="fixture">';
    }
}

require $root . '/templates/views/public/home/main.php';
