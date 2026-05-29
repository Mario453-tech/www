<?php

/**
 * GameLog — detailed game logging for Oil Empire
 *
 * Writes structured log entries to game_debug.log with labels:
 *   [INFO]          — informational events
 *   [WARN]          — warnings
 *   [ERROR]         — critical errors
 *   [DB]            — SQL queries
 *   [DB_ERROR]      — SQL errors
 *   [DB_RESULT]     — query results
 *   [STEP]          — steps in multi-stage processes
 *   [PERF]          — performance measurements
 *   [PAGE]          — page load start/stop
 *   [TABLE_OK]      — table exists
 *   [TABLE_MISSING] — table missing
 *
 * Usage:
 *   GameLog::info('hr.php', 'Loaded 5 employees');
 *   GameLog::db('HRService', 'hire', 'INSERT INTO board_members...', [1,'Jan']);
 *   GameLog::error('technical.php', 'PDO failed', $exception);
 */
class GameLog
{
    private static string $logFile    = '';
    private static bool   $enabled    = true;
    /** @var array<string, float> */
    private static array  $activePages = [];

    public static function init(string $logFile = ''): void
    {
        self::$logFile = $logFile ?: (__DIR__ . '/../game_debug.log');
    }

    public static function setEnabled(bool $e): void { self::$enabled = $e; }

    /** @param array<string, mixed>|null $context */
    private static function write(string $level, string $module, string $message, ?array $context = null): void
    {
        if (!self::$enabled) return;
        if (empty(self::$logFile)) self::init();

        $ts     = date('Y-m-d H:i:s');
        $mem    = round(memory_get_usage(true) / 1024 / 1024, 2);
        $ctxStr = $context !== null
            ? ' | ctx=' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
            : '';
        $line   = "[{$ts}] [{$level}] [{$module}] {$message}{$ctxStr} [mem={$mem}MB]\n";

        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    // Log levels
    /** @param array<string, mixed>|null $ctx */
    public static function info(string $m, string $msg, ?array $ctx = null): void { self::write('INFO',  $m, $msg, $ctx); }
    /** @param array<string, mixed>|null $ctx */
    public static function warn(string $m, string $msg, ?array $ctx = null): void { self::write('WARN',  $m, $msg, $ctx); }

    /**
     * @param string $m       Module name
     * @param string $msg     Error message
     * @param Throwable|array<string,mixed>|null $e  Exception or context array
     * @param array<string,mixed>|null $ctx           Extra context (when $e is Throwable)
     */
    public static function error(string $m, string $msg, $e = null, ?array $ctx = null): void
    {
        // Support both error('m','msg', $exception, $ctx) and error('m','msg', $ctxArray)
        if (is_array($e)) {
            $extra = $e;
            $e     = null;
        } else {
            $extra = $ctx ?? [];
        }
        if ($e instanceof Throwable) {
            $extra['exception_class']   = get_class($e);
            $extra['exception_message'] = $e->getMessage();
            $extra['exception_file']    = $e->getFile() . ':' . $e->getLine();
            $extra['exception_trace']   = array_slice(
                array_map(
                    fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?')
                        . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''),
                    $e->getTrace()
                ), 0, 8
            );
        }
        self::write('ERROR', $m, $msg, $extra);
    }

    // Database logging
    /** @param array<string, mixed>|null $params @param array<string, mixed>|null $extra */
    public static function db(string $m, string $method, string $sql, ?array $params = null, ?array $extra = null): void
    {
        $clean = preg_replace('/\s+/', ' ', trim($sql));
        $ctx   = ['sql' => mb_substr($clean, 0, 250)];
        if ($params !== null) $ctx['params'] = $params;
        if ($extra  !== null) $ctx = array_merge($ctx, $extra);
        self::write('DB', $m, "{$method}()", $ctx);
    }

    public static function dbResult(string $m, string $method, int $rows, ?string $note = null): void
    {
        $msg = "{$method}()  {$rows} rows" . ($note ? " ({$note})" : '');
        self::write('DB_RESULT', $m, $msg);
    }

    /** @param array<string, mixed>|null $params */
    public static function dbError(string $m, string $method, string $sql, Throwable $e, ?array $params = null): void
    {
        $clean = preg_replace('/\s+/', ' ', trim($sql));
        $ctx   = [
            'sql'   => mb_substr($clean, 0, 300),
            'error' => $e->getMessage(),
            'code'  => $e->getCode(),
            'file'  => $e->getFile() . ':' . $e->getLine(),
        ];
        if ($params !== null) $ctx['params'] = $params;
        self::write('DB_ERROR', $m, "{$method}() FAILED", $ctx);
    }

    // Process steps
    /** @param array<string, mixed>|null $ctx */
    public static function step(string $m, string $proc, int $n, string $desc, ?array $ctx = null): void
    {
        self::write('STEP', $m, "[{$proc}] step {$n}: {$desc}", $ctx);
    }

    // Performance
    /** @param array<string, mixed>|null $ctx */
    public static function perf(string $m, string $op, float $start, ?array $ctx = null): void
    {
        $ms = round((microtime(true) - $start) * 1000, 2);
        self::write('PERF', $m, "{$op} {$ms}ms", $ctx);
    }

    // Page loading
    public static function pageStart(string $page): float
    {
        if (isset(self::$activePages[$page])) {
            return self::$activePages[$page];
        }

        $start = microtime(true);
        self::$activePages[$page] = $start;
        self::write('PAGE', $page, '=== START ===', [
            'method'  => $_SERVER['REQUEST_METHOD'] ?? '?',
            'query'   => $_SERVER['QUERY_STRING']   ?? '',
            'session' => session_id() ?: 'none',
        ]);
        return $start;
    }

    public static function pageEnd(string $page, float $start): void
    {
        if (!isset(self::$activePages[$page])) {
            return;
        }
        unset(self::$activePages[$page]);

        $ms = round((microtime(true) - $start) * 1000, 1);
        self::write('PAGE', $page, "=== END ({$ms}ms) ===");
    }

    // Table validation
    public static function tableCheck(PDO $db, string $m, string $table): bool
    {
        try {
            $r      = $db->query("SHOW TABLES LIKE " . $db->quote($table))->fetch();
            $exists = (bool)$r;
            self::write(
                $exists ? 'TABLE_OK' : 'TABLE_MISSING',
                $m,
                $exists ? "Table '{$table}' OK" : "Table '{$table}' MISSING!"
            );
            return $exists;
        } catch (Throwable $e) {
            self::write('TABLE_CHECK_FAIL', $m, "Error checking table '{$table}'", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * @param list<string> $tables
     * @return array<string, bool>
     */
    public static function tablesCheck(PDO $db, string $m, array $tables): array
    {
        $results = [];
        foreach ($tables as $t) $results[$t] = self::tableCheck($db, $m, $t);
        $missing = array_keys(array_filter($results, fn($v) => !$v));
        if (!empty($missing)) {
            self::write('TABLES_SUMMARY', $m, count($missing) . ' missing tables: ' . implode(', ', $missing));
        } else {
            self::write('TABLES_SUMMARY', $m, 'All ' . count($tables) . ' tables OK.');
        }
        return $results;
    }

    // Safe query helpers
    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>|null
     */
    public static function safePrepare(PDO $db, string $m, string $method, string $sql, array $params = []): ?array
    {
        try {
            self::db($m, $method, $sql, $params);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            self::dbResult($m, $method, count($rows));
            return $rows;
        } catch (Throwable $e) {
            self::dbError($m, $method, $sql, $e, $params);
            return null;
        }
    }

    /** Safe execute for INSERT/UPDATE/DELETE — does not fetch results */
    /** @param array<string, mixed> $params */
    public static function safeExecute(PDO $db, string $m, string $method, string $sql, array $params = []): bool
    {
        try {
            self::db($m, $method, $sql, $params);
            $stmt   = $db->prepare($sql);
            $result = $stmt->execute($params);
            self::dbResult($m, $method, $stmt->rowCount(), $result ? 'OK' : 'execute=false');
            return $result;
        } catch (Throwable $e) {
            self::dbError($m, $method, $sql, $e, $params);
            return false;
        }
    }
}
