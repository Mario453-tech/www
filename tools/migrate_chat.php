<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/ChatBootstrap.php';

if (!in_array('--apply', $argv, true)) {
    fwrite(STDOUT, "Chat migration: adds room translations/schema/indexes, widens message storage, preserves existing room settings.\nRun with --apply after a database backup.\n");
    exit;
}
try {
    ensureChatSchema();
    fwrite(STDOUT, "Chat migration completed.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Chat migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
