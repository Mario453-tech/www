<?php
require_once __DIR__ . '/init.php';
GameLog::info('admin/erorr.php', 'entry');
$db = Database::getInstance()->getConnection();

echo "<pre>";
foreach (['players', 'market_trends', 'market_state', 'wells', 'storage', 'admin_logs', 'event_logs'] as $table) {
    echo "\n=== $table ===\n";
    try {
        $cols = $db->query("SHOW COLUMNS FROM $table")->fetchAll();
        foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
    } catch (Throwable $e) {
        echo "  BŁĄD: " . $e->getMessage() . "\n";
    }
}
echo "</pre>";


