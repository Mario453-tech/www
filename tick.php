<?php
require_once __DIR__ . '/src/init.php';

$_codexGuardStart = GameLog::pageStart('tick.php');
try {
    require_once __DIR__ . '/cron/tick.php';
} catch (Throwable $e) {
    GameLog::error('tick.php', 'Delegated tick execution failed', $e);
    http_response_code(500);
    echo 'Tick error.';
} finally {
    GameLog::pageEnd('tick.php', $_codexGuardStart);
}

