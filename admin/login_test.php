<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/login_test.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (AdminAuth::login($username, $password)) {
        header('Location: /admin/index.php');
        exit();
    } else {
        $error = 'Błędne dane.';
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Login test</title></head>
<body style="background:#1a1a1a;color:#eee;font-family:monospace;display:flex;align-items:center;justify-content:center;min-height:100vh">
<form method="post" style="display:flex;flex-direction:column;gap:10px;width:280px">
    <h2 style="color:#f90">Login (test bez CSRF)</h2>
    <?php if (!empty($error)): ?>
        <p style="color:#e66"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <input type="text" name="username" value="hrypa23" style="background:#252525;border:1px solid #444;color:#eee;padding:8px;border-radius:3px">
    <input type="password" name="password" placeholder="hasło" style="background:#252525;border:1px solid #444;color:#eee;padding:8px;border-radius:3px">
    <button type="submit" style="background:#f90;color:#111;border:none;padding:10px;border-radius:3px;font-weight:bold;cursor:pointer">Zaloguj</button>
</form>
</body>
</html>
<?php
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/login_test.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Wystapil blad aplikacji.';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/login_test.php', $_codexGuardStart);
    }
}
