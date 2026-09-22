<?php
declare(strict_types=1);

final class ApiErrorHandler
{
    private static bool $api = false;
    public static function message(string $key): string
    {
        $locale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? null;
        if (!in_array($locale, ['pl', 'en'], true)) {
            $locale = preg_match('/^en\b/i', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) ? 'en' : 'pl';
        }
        $messages = require __DIR__ . '/../lang/' . $locale . '/api.php';
        return $messages[$key] ?? $messages['error'];
    }

    /** @param array<string, mixed> $context */
    public static function log(string $module, string $event, ?Throwable $error = null, array $context = []): void
    {
        // Never log exception text or arguments: they may contain credentials.
        // Nie loguj tresci wyjatkow ani argumentow: moga zawierac dane logowania.
        if ($error !== null) {
            $context += ['exception_class' => get_class($error), 'file' => basename($error->getFile()), 'line' => $error->getLine()];
        }
        error_log('[' . $module . '] ' . $event . ' ' . json_encode($context));
        if (class_exists('GameLog', false)) {
            if (self::$api) {
                GameLog::setEnabled(true);
            }
            GameLog::error($module, $event, null, $context);
            if (self::$api) {
                GameLog::setEnabled(false);
            }
        }
    }

    public static function respond(bool $json = false): void
    {
        foreach (headers_list() as $header) {
            $json = $json || stripos($header, 'Content-Type: application/json') === 0;
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=utf-8');
        }
        $message = self::message('error');
        echo $json ? json_encode(['error' => $message], JSON_UNESCAPED_UNICODE)
            : '<p role="alert">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    public static function install(bool $json): void
    {
        self::$api = $json;
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');
        set_exception_handler(static function (Throwable $error) use ($json): void {
            self::log($json ? 'API' : 'init', 'Unhandled exception', $error);
            self::respond($json);
        });
        set_error_handler(static function (int $type, string $message, string $file, int $line): bool {
            if (error_reporting() & $type) {
                self::log('PHP', 'Runtime error', null, ['type' => $type, 'file' => basename($file), 'line' => $line]);
            }
            if (in_array($type, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                throw new ErrorException('Runtime error', 0, $type, $file, $line);
            }
            return true;
        });
        register_shutdown_function(static function () use ($json): void {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::log($json ? 'API' : 'init', 'Fatal shutdown error', null, [
                    'type' => $error['type'], 'file' => basename($error['file']), 'line' => $error['line'],
                ]);
                self::respond($json);
            }
        });
    }
}
