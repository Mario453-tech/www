<?php
// DmApi.php - endpoint wiadomosci prywatnych 
ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// Deleguj do ChatApi - ten sam endpoint, inne parametry
$_GET['with'] = $_GET['with'] ?? null;
require_once __DIR__ . '/ChatApi.php';
