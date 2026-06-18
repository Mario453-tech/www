<?php
$cfg = require 'config/database.php';
$pdo = new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['dbname'].';charset=utf8mb4', $cfg['user'], $cfg['password']);
$rows = $pdo->query("SELECT `key`, value FROM site_config WHERE `key` LIKE 'log%'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo $r['key'].'='.$r['value'].PHP_EOL;
