<?php
declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';
require_once $root . '/src/GameLog.php';
require_once $root . '/src/i18n.php';
require_once $root . '/tests/MySqlIntegration/MySqlIntegrationTestCase.php';

GameLog::setEnabled(false);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'testing';
