<?php
// Legacy entrypoint kept for backward compatibility
// Zachowany punkt wejscia dla kompatybilnosci wstecznej
try {
    GameLog::info('admin_wells', 'Legacy entrypoint redirect');
    require_once __DIR__ . '/../admin/wells.php';
} catch (Throwable $e) {
    GameLog::error('admin_wells', 'Legacy entrypoint failed', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo 'Wystapil blad aplikacji.';
}
