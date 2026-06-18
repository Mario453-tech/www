<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$defaultConfig = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => '',
    'user' => '',
    'password' => '',
    'charset' => 'utf8mb4',
];

$configFile = dirname(__DIR__) . '/config/database.php';
if (is_file($configFile)) {
    $loadedConfig = require $configFile;
    if (is_array($loadedConfig)) {
        $defaultConfig['host'] = (string)($loadedConfig['host'] ?? $defaultConfig['host']);
        $defaultConfig['dbname'] = (string)($loadedConfig['dbname'] ?? $defaultConfig['dbname']);
        $defaultConfig['user'] = (string)($loadedConfig['user'] ?? $defaultConfig['user']);
        $defaultConfig['password'] = (string)($loadedConfig['password'] ?? $defaultConfig['password']);
        $defaultConfig['charset'] = (string)($loadedConfig['charset'] ?? $defaultConfig['charset']);
    }
}

$form = $defaultConfig;
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $form['host'] = trim((string)($_POST['host'] ?? ''));
        $form['port'] = trim((string)($_POST['port'] ?? '3306'));
        $form['dbname'] = trim((string)($_POST['dbname'] ?? ''));
        $form['user'] = trim((string)($_POST['user'] ?? ''));
        $form['password'] = (string)($_POST['password'] ?? '');
        $form['charset'] = trim((string)($_POST['charset'] ?? 'utf8mb4'));

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $form['host'],
                $form['port'] !== '' ? $form['port'] : '3306',
                $form['dbname'],
                $form['charset'] !== '' ? $form['charset'] : 'utf8mb4'
            );

            $pdo = new PDO($dsn, $form['user'], $form['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $result = [
                'server_version' => (string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'client_version' => (string)$pdo->getAttribute(PDO::ATTR_CLIENT_VERSION),
                'connection_status' => (string)$pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                'database' => (string)$pdo->query('SELECT DATABASE()')->fetchColumn(),
            ];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test polaczenia z baza</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #10141f;
            color: #f3f4f6;
        }

        .wrap {
            max-width: 760px;
            margin: 40px auto;
            padding: 24px;
        }

        .card {
            background: #1b2233;
            border: 1px solid #2f3b57;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        h1 {
            margin-top: 0;
            font-size: 28px;
        }

        p.note {
            color: #aeb8cc;
            margin-bottom: 24px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            color: #d2d9e6;
        }

        input {
            border: 1px solid #43506f;
            border-radius: 10px;
            background: #0f1522;
            color: #f3f4f6;
            padding: 12px 14px;
            font-size: 15px;
        }

        button {
            margin-top: 20px;
            border: 0;
            border-radius: 10px;
            background: #e2b84b;
            color: #1a1a1a;
            font-weight: 700;
            padding: 12px 18px;
            cursor: pointer;
        }

        .status {
            margin-top: 20px;
            border-radius: 10px;
            padding: 16px;
        }

        .status.ok {
            background: #133326;
            border: 1px solid #2d8a61;
        }

        .status.err {
            background: #3a171c;
            border: 1px solid #b34b5d;
        }

        .meta {
            margin: 12px 0 0;
            padding-left: 18px;
        }

        .meta li {
            margin-bottom: 8px;
        }

        code {
            background: rgba(255, 255, 255, 0.08);
            padding: 2px 6px;
            border-radius: 6px;
        }

        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Test polaczenia z baza danych</h1>
            <p class="note">Wpisz dane MySQL/MariaDB i kliknij sprawdzenie. Skrypt wykona test przez PDO i pokaze wynik polaczenia.</p>

            <form method="post">
                <?= CSRF::field() ?>
                <div class="grid">
                    <div class="field">
                        <label for="host">Host</label>
                        <input id="host" name="host" type="text" value="<?= h($form['host']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="port">Port</label>
                        <input id="port" name="port" type="text" value="<?= h($form['port']) ?>" required>
                    </div>

                    <div class="field full">
                        <label for="dbname">Nazwa bazy</label>
                        <input id="dbname" name="dbname" type="text" value="<?= h($form['dbname']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="user">Uzytkownik</label>
                        <input id="user" name="user" type="text" value="<?= h($form['user']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="password">Haslo</label>
                        <input id="password" name="password" type="password" value="">
                    </div>

                    <div class="field full">
                        <label for="charset">Charset</label>
                        <input id="charset" name="charset" type="text" value="<?= h($form['charset']) ?>" required>
                    </div>
                </div>

                <button type="submit">Sprawdz polaczenie</button>
            </form>

            <?php if ($result !== null): ?>
                <div class="status ok">
                    <strong>Polaczenie OK.</strong>
                    <ul class="meta">
                        <li>Baza: <code><?= h($result['database']) ?></code></li>
                        <li>Wersja serwera: <code><?= h($result['server_version']) ?></code></li>
                        <li>Wersja klienta: <code><?= h($result['client_version']) ?></code></li>
                        <li>Status: <code><?= h($result['connection_status']) ?></code></li>
                    </ul>
                </div>
            <?php elseif ($error !== null): ?>
                <div class="status err">
                    <strong>Polaczenie nieudane.</strong>
                    <div style="margin-top:10px;"><code><?= h($error) ?></code></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
