<?php
require_once __DIR__ . '/src/init.php';
AdminAuth::requireLogin();
$db = Database::getInstance()->getConnection();

$rows = $db->query("SELECT player_id, COUNT(*) as cnt, MIN(created_at) as first, MAX(created_at) as last FROM well_incidents GROUP BY player_id ORDER BY cnt DESC")->fetchAll();
echo "<pre>well_incidents per player:\n";
print_r($rows);

$total = $db->query("SELECT COUNT(*) FROM well_incidents")->fetchColumn();
echo "\nTotal rows: $total\n";

$playerId = Auth::getUserId();
echo "Current player_id (logged in): $playerId\n";
echo "</pre>";
