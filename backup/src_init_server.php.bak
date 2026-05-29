<?php

//  ERROR LOGGING — zbiera WSZYSTKIE b³êdy do error_log 
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error_log');

set_exception_handler(function (Throwable $e) {
    $msg  = '[UNCAUGHT EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage();
    $msg .= ' | file: ' . $e->getFile() . ':' . $e->getLine();
    $msg .= ' | trace: ' . str_replace("\n", ' -> ', $e->getTraceAsString());
    error_log($msg);
    if (class_exists('GameLog', false)) {
        GameLog::error('init', 'Uncaught exception', $e);
    }
    http_response_code(500);
    echo '<!-- PHP ERROR: ' . htmlspecialchars($e->getMessage()) . ' -->';
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('[FATAL] ' . $err['message'] . ' | ' . $err['file'] . ':' . $err['line']);
        if (class_exists('GameLog', false)) {
            GameLog::error('init', 'Fatal shutdown error', null, [
                'type'    => $err['type'],
                'message' => $err['message'],
                'file'    => $err['file'],
                'line'    => $err['line'],
            ]);
        }
    }
});

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    $types = [
        E_WARNING           => 'WARNING',
        E_NOTICE            => 'NOTICE',
        E_DEPRECATED        => 'DEPRECATED',
        E_USER_ERROR        => 'USER_ERROR',
        E_USER_WARNING      => 'USER_WARNING',
        E_USER_NOTICE       => 'USER_NOTICE',
        (defined('E_STRICT') ? E_STRICT : 2048) => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE',
    ];
    $type  = $types[$errno] ?? "ERR#$errno";
    $bt    = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1, 6);
    $trace = implode(' -> ', array_map(
        fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?'),
        $bt
    ));
    error_log("[$type] $errstr | $errfile:$errline | trace: $trace");
    return false;
});

//  KLASY CORE 
require_once __DIR__ . '/GameLog.php';

GameLog::init(__DIR__ . '/../game_debug.log');

// Automatyczne logowanie requestów w plikach bez rêcznego pageStart/pageEnd
if (PHP_SAPI !== 'cli') {
    $autoPage = basename($_SERVER['SCRIPT_NAME'] ?? 'request');
    $GLOBALS['__autoPageName']  = $autoPage;
    $GLOBALS['__autoPageStart'] = GameLog::pageStart($autoPage);

    register_shutdown_function(function () {
        $name  = $GLOBALS['__autoPageName']  ?? null;
        $start = $GLOBALS['__autoPageStart'] ?? null;
        if (is_string($name) && is_float($start)) {
            GameLog::pageEnd($name, $start);
        }
    });
}

//  SERWISY 
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/CSRF.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Validator.php';

// WA¯NE: Auth.php zawiera klasê AdminAuth (panel admina)
require_once __DIR__ . '/Auth.php';

if (!class_exists('Auth', false)) {
    class_alias('AdminAuth', 'Auth');
}

require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/Market.php';
require_once __DIR__ . '/MarketTrend.php';
require_once __DIR__ . '/MarketOffer.php';
require_once __DIR__ . '/Player.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Well.php';
require_once __DIR__ . '/WellShop.php';
require_once __DIR__ . '/TechnicalTeamService.php';
require_once __DIR__ . '/CandidateGenerator.php';
require_once __DIR__ . '/HRService.php';
require_once __DIR__ . '/WellStaffService.php';
require_once __DIR__ . '/HeadhunterService.php';
require_once __DIR__ . '/DirectorNotificationService.php';
require_once __DIR__ . '/BankNegotiationService.php';
require_once __DIR__ . '/WorldMap.php';
require_once __DIR__ . '/RegionalEventService.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/i18n.php';

spl_autoload_register(function ($class) {
    $candidates = [
        __DIR__ . '/' . $class . '.php',
        __DIR__ . '/Tick/' . $class . '.php',
        __DIR__ . '/Well/' . $class . '.php',
        __DIR__ . '/Incident/' . $class . '.php',
    ];
    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

//  SESJA I NAG£ÓWKI 
if (session_status() === PHP_SESSION_NONE) {
    // Na shared hostingu /tmp mo¿e byæ niedostêpny — u¿ywamy katalogu sessions/ w projekcie.
    // On shared hosting /tmp may be unavailable — use sessions/ directory within the project.
    $sessionPath = __DIR__ . '/../sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_start();
}

Security::setHeaders();

//  BANKRUPTCY BOOTSTRAP 
require_once __DIR__ . '/BankruptcyBootstrap.php';

ensureBankruptcyRecoverySchema();
enforceBankruptcyPostGuards();

//  ROUTING 
const ROUTES = [
    'home'            => '/',
    'login'           => '/login',
    'logout'          => '/logout',
    'register'        => '/register',
    'forgot-password' => '/forgot-password',
    'reset-password'  => '/reset-password',
    'market'          => '/market',
    'market-offers'   => '/market-offers',
    'sell'            => '/sell',
    'bank'            => '/bank',
    'map'             => '/map',
    'recovery'        => '/recovery',
    'upgrade-storage' => '/upgrade-storage',
    'upgrade-well'    => '/upgrade-well',
    'hr'              => '/hr',
    'technical'       => '/technical',
    'boardroom'       => '/boardroom',
    'loans'           => '/loans',
    'dashboard'       => '/dashboard',
    'help'            => '/help',
];

/** @param array<string, mixed> $query */
function url(string $name, array $query = []): string
{
    $path = ROUTES[$name] ?? ('/' . $name);
    return $query ? $path . '?' . http_build_query($query) : $path;
}
