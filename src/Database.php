<?php

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;
    
    private function __construct()
    {
        self::loadEnv(__DIR__ . '/../.env');
        $configFile = __DIR__ . '/../config/database.php';
        
        if (!file_exists($configFile)) {
            $msg = "Database config file not found: {$configFile}";
            error_log("[DB_INIT_FATAL] {$msg}");
            if (class_exists('GameLog', false)) GameLog::error('Database', $msg);
            throw new RuntimeException($msg);
        }
        
        $config = require $configFile;
        
        if (class_exists('GameLog', false)) {
            GameLog::info('Database', 'Connecting to database', [
                'host'    => $config['host'] ?? '?',
                'dbname'  => $config['dbname'] ?? '?',
                'charset' => $config['charset'] ?? '?',
                'user'    => $config['user'] ?? '?',
            ]);
        }
        
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $config['host'],
            $config['dbname'],
            $config['charset']
        );
        
        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true
            ]);
            
            if (class_exists('GameLog', false)) {
                $ver = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                GameLog::info('Database', "Connected OK — MySQL {$ver}");
            }
        } catch (PDOException $e) {
            $msg = "Database connection failed: {$e->getMessage()}";
            error_log("[DB_CONNECT_FATAL] {$msg} | dsn={$dsn}");
            if (class_exists('GameLog', false)) GameLog::error('Database', 'Connection failed', $e);
            throw $e;
        }
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Dodaje kolumne do tabeli jesli nie istnieje - kompatybilne z MySQL 8.0.
     * Adds a column to a table if it does not exist - compatible with MySQL 8.0.
     *
     * Uzycie / Usage:
     *   Database::addColumnIfMissing('wells', 'sold_at', 'DATETIME NULL DEFAULT NULL');
     *   Database::addColumnIfMissing('players', 'recovery_mode', "TINYINT(1) NOT NULL DEFAULT 0 AFTER bankruptcy_status");
     */
    private static function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key   = trim($key);
            $value = trim($value, " \t\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }

    public static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $db     = self::getInstance()->getConnection();
        $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
        $stmt   = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$dbName, $table, $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
}