<?php

/**
 * Diagnostyka wdrozenia — chroniona tokenem. / Deploy self-check — token-guarded.
 *
 * Cel / Purpose:
 * Gdy strona zwraca 500, ten endpoint pokazuje DOKLADNIE, co jest nie tak,
 * bez ladowania calej aplikacji (wiec sam sie nie wywala).
 * When the site returns 500, this endpoint shows EXACTLY what is wrong,
 * without bootstrapping the whole app (so it will not fatal itself).
 *
 * Uzycie / Usage:
 *   GET /public/selfcheck.php?token=SECRET            — bezpieczne testy / safe checks
 *   GET /public/selfcheck.php?token=SECRET&boot=1     — sprobuj zaladowac init.php i zlap fatal
 *                                                        try to load init.php and capture the fatal
 *
 * Token = OPCACHE_RESET_TOKEN z .env (ten sam co reset OPcache).
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);

// ── Wczytaj token z .env bez bootstrapu / Read token from .env without bootstrap ──
$envFile  = $root . '/.env';
$envKeys  = [];
$token    = null;
$envVals  = [];
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        $envKeys[] = $k;
        $envVals[$k] = $v;
        if ($k === 'OPCACHE_RESET_TOKEN') {
            $token = $v;
        }
    }
}

$provided = (string)($_GET['token'] ?? $_SERVER['HTTP_X_RESET_TOKEN'] ?? '');
if ($token === null || $token === '' || !hash_equals($token, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden — zly lub brak tokenu']);
    exit;
}

// ── Tryb boot: sprobuj zaladowac init.php i przechwycic fatal ──
// ── boot mode: try to load init.php and capture any fatal error ──
if (isset($_GET['boot'])) {
    register_shutdown_function(static function () use ($root): void {
        $err = error_get_last();
        $fatal = ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true))
            ? $err
            : null;
        // Wyczysc ewentualny output z init.php / discard any output from init.php
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => $fatal === null,
            'mode'  => 'boot',
            'fatal' => $fatal ? [
                'message' => $fatal['message'],
                'file'    => $fatal['file'],
                'line'    => $fatal['line'],
            ] : null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    });
    ob_start();
    require $root . '/src/init.php';
    // Jesli dotrzemy tutaj — init.php zaladowal sie bez fatala.
    // If we reach here — init.php loaded without a fatal.
    exit;
}

// ── Bezpieczne testy komponentow / Safe component checks ──
$report = [
    'ok'          => true,
    'php_version' => PHP_VERSION,
    'time'        => date('c'),
];

// .env
$report['env'] = [
    'path'     => $envFile,
    'exists'   => file_exists($envFile),
    'readable' => is_readable($envFile),
    'keys'     => $envKeys, // tylko nazwy kluczy, bez wartosci / key names only, no values
];

// config/database.php
$cfgFile = $root . '/config/database.php';
$cfg = null;
$report['config_database'] = ['exists' => file_exists($cfgFile)];
try {
    if (file_exists($cfgFile)) {
        // Zaladuj zmienne z .env do srodowiska, by getenv() w configu zadzialal.
        // Load .env vars into the environment so getenv() in the config works.
        foreach ($envVals as $k => $v) {
            if (getenv($k) === false) {
                putenv("{$k}={$v}");
            }
        }
        $cfg = require $cfgFile;
        $report['config_database'] += [
            'host'         => $cfg['host']    ?? null,
            'dbname'       => $cfg['dbname']  ?? null,
            'user'         => $cfg['user']    ?? null,
            'charset'      => $cfg['charset'] ?? null,
            'password_set' => isset($cfg['password']) && $cfg['password'] !== '',
        ];
    }
} catch (Throwable $e) {
    $report['config_database']['error'] = $e->getMessage();
}

// src/Database.php — czy klasa i metoda loadEnv istnieja
// src/Database.php — does the class and the loadEnv method exist
$dbFile = $root . '/src/Database.php';
$report['database_class'] = ['file_exists' => file_exists($dbFile)];
try {
    if (file_exists($dbFile)) {
        require_once $dbFile;
        $report['database_class']['class_loaded']  = class_exists('Database', false);
        $report['database_class']['loadEnv_exists'] = method_exists('Database', 'loadEnv');
    }
} catch (Throwable $e) {
    $report['database_class']['error'] = $e->getMessage();
}

// Polaczenie z baza / Database connection (bezposrednio przez PDO)
$report['db_connect'] = ['ok' => false];
try {
    if (is_array($cfg)) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['dbname'], $cfg['charset']);
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $report['db_connect']['ok']            = true;
        $report['db_connect']['server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } else {
        $report['db_connect']['error'] = 'brak konfiguracji / no config loaded';
    }
} catch (Throwable $e) {
    $report['db_connect']['error'] = $e->getMessage();
}

// OPcache
$report['opcache'] = [
    'reset_available' => function_exists('opcache_reset'),
    'enabled'         => function_exists('opcache_get_status'),
];
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    $report['opcache']['running'] = is_array($st) ? ($st['opcache_enabled'] ?? null) : null;
}

// Traity BankNegotiation — najczestsza przyczyna 500 z wyscigu OPcache
// BankNegotiation traits — the most common OPcache-race 500 cause
$traits = [
    'BankNegotiationContextTrait'      => $root . '/src/BankNegotiation/ContextTrait.php',
    'BankNegotiationMessagesTrait'     => $root . '/src/BankNegotiation/MessagesTrait.php',
    'BankNegotiationRandomEventsTrait' => $root . '/src/BankNegotiation/RandomEventsTrait.php',
    'BankNegotiationRequestsTrait'     => $root . '/src/BankNegotiation/RequestsTrait.php',
    'BankNegotiationProcessorTrait'    => $root . '/src/BankNegotiation/ProcessorTrait.php',
];
$report['bank_traits'] = [];
foreach ($traits as $name => $file) {
    $entry = ['file_exists' => file_exists($file), 'size' => @filesize($file) ?: 0];
    try {
        if (file_exists($file)) {
            require_once $file;
            $entry['trait_defined'] = trait_exists($name, false);
        }
    } catch (Throwable $e) {
        $entry['error'] = $e->getMessage();
    }
    $report['bank_traits'][$name] = $entry;
}

// Podsumowanie ok / overall ok
$report['ok'] = ($report['db_connect']['ok'] ?? false)
    && ($report['database_class']['loadEnv_exists'] ?? false);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
