<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "START\n";

$configFile = __DIR__ . '/config/database.php';
echo "CONFIG: " . $configFile . "\n";

if (!file_exists($configFile)) {
    echo "BRAK PLIKU CONFIG\n";
    exit;
}

$config = require $configFile;

echo "HOST: " . ($config['host'] ?? '[brak]') . "\n";
echo "DB: " . ($config['dbname'] ?? '[brak]') . "\n";
echo "USER: " . ($config['user'] ?? '[brak]') . "\n";

try {
    $pdo = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';charset=' . $config['charset'],
        $config['user'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "PDO: OK\n";
} catch (Throwable $e) {
    echo "PDO ERROR: " . $e->getMessage() . "\n";
}
