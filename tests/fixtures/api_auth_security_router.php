<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Database.php';
$db = new PDO('sqlite:' . getenv('API_TEST_SQLITE'), null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$reflection = new ReflectionClass(Database::class);
$database = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('pdo')->setValue($database, $db);
$reflection->getProperty('instance')->setValue(null, $database);
require dirname(__DIR__, 2) . '/api/v1/auth/login.php';
