<?php
// Reset gra1 database and reimport DELETE after use
declare(strict_types=1);

$host   = '127.0.0.1';
$user   = 'root';
$pass   = '';
$dbname = 'gra1';
$sqlFile = __DIR__ . '/vh15188_oil.sql';

try {
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "<style>body{font:13px monospace;background:#111;color:#ccc;padding:20px}
    .ok{color:lightgreen}.err{color:red}.warn{color:orange}b{color:#f90}</style>";
    echo "<h2 style='color:#f90'>DB Reset & Import</h2>";

 // Drop and recreate database
    $pdo->exec("DROP DATABASE IF EXISTS `{$dbname}`");
    echo "<p class='ok'> Dropped database {$dbname}</p>";

    $pdo->exec("CREATE DATABASE `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "<p class='ok'> Created fresh database {$dbname}</p>";

    $pdo->exec("USE `{$dbname}`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

 // Read and execute SQL file
    if (!file_exists($sqlFile)) {
        die("<p class='err'>SQL file not found: {$sqlFile}</p>");
    }

    $sql = file_get_contents($sqlFile);
    echo "<p class='ok'> Loaded SQL file (" . round(strlen($sql)/1024) . " KB)</p>";

 // Check for players_old
    if (strpos($sql, 'players_old') !== false) {
        echo "<p class='warn'> Found 'players_old' in SQL � fixing...</p>";
        $sql = str_replace('`players_old`', '`players`', $sql);
        echo "<p class='ok'> Fixed players_old  players</p>";
    } else {
        echo "<p class='ok'> No players_old found � file is clean</p>";
    }

 // Increase packet size for large imports
    $pdo->exec("SET GLOBAL max_allowed_packet=67108864"); // 64MB
    $pdo->exec("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
    $pdo->exec("SET time_zone='+00:00'");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("SET UNIQUE_CHECKS=0");

 // Split into individual statements and execute one by one
 // Removes comments and splits on semicolons
    $sql = preg_replace('/^--.*$/m', '', $sql);         // strip -- comments
    $sql = preg_replace('/^\/\*.*?\*\//ms', '', $sql);  // strip /* */ comments
    $statements = array_filter(
        array_map('trim', explode(";\n", $sql)),
        fn($s) => strlen($s) > 3
    );

    $total = count($statements);
    $done  = 0;
    $errors = [];

    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $done++;
        } catch (PDOException $e) {
            $errors[] = htmlspecialchars(substr($stmt, 0, 80)) . '  ' . $e->getMessage();
        }
    }

    echo "<p class='ok'><b> Executed {$done}/{$total} statements</b></p>";
    if ($errors) {
        echo "<p class='warn'> " . count($errors) . " errors:</p><ul>";
        foreach (array_slice($errors, 0, 10) as $err) {
            echo "<li class='err'>{$err}</li>";
        }
        echo "</ul>";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $pdo->exec("SET UNIQUE_CHECKS=1");

 // Verify key tables
    echo "<p><b>Verifying tables:</b></p>";
    foreach (['players','wells','loans','finance_logs','bank_negotiations','bailiff_proceedings'] as $t) {
        $n = $pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        echo "<p class='ok'> {$t}: {$n} rows</p>";
    }

    echo "<p style='color:red;margin-top:20px'><b>DELETE this file: reset_db.php</b></p>";

} catch (PDOException $e) {
    echo "<p class='err'><b>Error:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
}
