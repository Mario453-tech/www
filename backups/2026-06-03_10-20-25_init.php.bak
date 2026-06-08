<?php

// ERROR LOGGING zbiera WSZYSTKIE bdy do error_log / collects ALL errors to error_log
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
        2048 => 'STRICT', // E_STRICT (deprecated in PHP 8.1+, constant value = 2048)
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

// KLASY CORE 
require_once __DIR__ . '/GameLog.php';

GameLog::init(__DIR__ . '/../game_debug.log');

// Automatyczne logowanie requestw w plikach bez rcznego pageStart/pageEnd
// Automatic request logging in files without manual pageStart/pageEnd
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

// SERWISY 
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/CSRF.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Validator.php';

// WANE: Auth.php zawiera klas AdminAuth (panel admina)
// IMPORTANT: Auth.php contains the AdminAuth class (admin panel)
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/EmailTemplate.php';

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

 // Load bank negotiation traits, then the service, with OPcache self-healing.
 // PL: Laduj traity negocjacji, potem serwis, z samonaprawą OPcache.
 // FTP+OPcache race: an upload can leave OPcache holding an EMPTY cached
 // version of a trait file, so require_once "succeeds" but defines nothing,
 // and the later "use TraitName" fatals (500 on every page via init.php).
 // PL: Wyscig FTP+OPcache moze zostawic w cache pusty plik traitu, przez co
 // require_once nie definiuje nic, a pozniejsze "use" wywala 500 na kazdej stronie.
$bankNegFiles = [
    'BankNegotiationContextTrait'      => __DIR__ . '/BankNegotiation/ContextTrait.php',
    'BankNegotiationMessagesTrait'     => __DIR__ . '/BankNegotiation/MessagesTrait.php',
    'BankNegotiationRandomEventsTrait' => __DIR__ . '/BankNegotiation/RandomEventsTrait.php',
    'BankNegotiationRequestsTrait'     => __DIR__ . '/BankNegotiation/RequestsTrait.php',
    'BankNegotiationProcessorTrait'    => __DIR__ . '/BankNegotiation/ProcessorTrait.php',
];

$bankNegReady = true;
foreach ($bankNegFiles as $traitName => $traitFile) {
    require_once $traitFile;
    if (!trait_exists($traitName, false)) {
        $bankNegReady = false;
 // Force a fresh recompile on the NEXT request to self-heal the stale cache.
 // PL: Wymus swieza rekompilacje przy nastepnym zadaniu, by naprawic cache.
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($traitFile, true);
        }
        if (class_exists('GameLog', false)) {
            GameLog::error('init', 'Bank negotiation trait missing after require (OPcache stale?)', null, [
                'trait' => $traitName,
                'file'  => $traitFile,
            ]);
        }
    }
}

 // Only load the service if all its traits are defined — otherwise skip it for
 // this one request so the rest of the site keeps working (bank.php guards with
 // class_exists). Next request, post-invalidation, loads cleanly.
 // PL: Laduj serwis tylko gdy wszystkie traity sa zdefiniowane; w przeciwnym
 // razie pomin go na to jedno zadanie (reszta strony dziala, bank.php sprawdza
 // class_exists). Kolejne zadanie, po unieważnieniu cache, zaladuje sie poprawnie.
if ($bankNegReady) {
    require_once __DIR__ . '/BankNegotiationService.php';
}
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

// SESJA I NAGWKI / SESSION AND HEADERS
if (session_status() === PHP_SESSION_NONE) {
 // Na shared hostingu /tmp moe by niedostpny uywamy katalogu sessions/ w projekcie.
 // On shared hosting /tmp may be unavailable use sessions/ directory within the project.
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

// BANKRUPTCY BOOTSTRAP
require_once __DIR__ . '/BankruptcyBootstrap.php';

ensureBankruptcyRecoverySchema();
enforceBankruptcyPostGuards();

// TRANSPORT SCHEMA - ensures 'nieustawiony' enum value exists in wells and transport_config
// Run once per boot; safe no-op if columns already correct.
if (PHP_SAPI !== 'cli') {
    try {
        TransportConfigService::ensureTransportSchema(Database::getInstance()->getConnection());
    } catch (Throwable $__tsEx) {
 // Non-fatal - game runs without this migration
    }
}

// ROUTING 
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
    'logistics'       => '/logistics',
    'boardroom'       => '/boardroom',
    'loans'           => '/loans',
    'dashboard'       => '/dashboard',
    'help'            => '/help',
    'legal'           => '/legal',
];

/** @param array<string, mixed> $query */
function url(string $name, array $query = []): string
{
    $path = ROUTES[$name] ?? ('/' . $name);
    return $query ? $path . '?' . http_build_query($query) : $path;
}

/**
 * Zwraca URL assetu z automatycznym cache-bustingiem opartym na filemtime().
 * Returns asset URL with automatic cache-busting based on filemtime().
 * Gdy plik zmienisz przegldarka automatycznie pobierze now wersj.
 * When the file changes, the browser automatically fetches the new version.
 * Usuwa ewentualne stare ?v=X przed wyliczeniem nowego timestampu.
 * Strips any old ?v=X before computing the new timestamp.
 */
function asset(string $path): string
{
    if (preg_match('~^(https?:)?//~i', $path) === 1 || str_starts_with($path, 'data:')) {
        return $path;
    }
    $cleanPath = (string) strtok($path, '?');          // strip old ?v=X
    $docRoot   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $fullPath  = $docRoot . '/' . ltrim($cleanPath, '/');
    $ts        = is_file($fullPath) ? (int) filemtime($fullPath) : time();
    return $cleanPath . '?v=' . $ts;
}
