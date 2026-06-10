<?php
// Brak require init.php czysta diagnostyka
error_reporting(E_ALL);
ini_set('display_errors', 0);

$avatarDir = __DIR__ . '/assets/img/avatars/';
$tmpDir    = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();

header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<title>Diag Upload</title>
<style>
body{font-family:monospace;background:#111;color:#eee;padding:20px;max-width:800px}
h2{color:#c8a84b}
table{border-collapse:collapse;width:100%;margin:10px 0}
td,th{border:1px solid #444;padding:7px 12px}
th{background:#222;color:#c8a84b;text-align:left}
.ok{color:#6fcf97;font-weight:bold}
.err{color:#eb5757;font-weight:bold}
</style>
</head>
<body>
<h2> Diagnostyka Upload</h2>
<p style="color:#eb5757"><strong>USUŃ TEN PLIK PO DIAGNOSTYCE!</strong></p>

<table>
<tr><th colspan="2">PHP</th></tr>
<tr><td>Wersja</td><td><?= PHP_VERSION ?></td></tr>
<tr><td>file_uploads</td><td><?= ini_get('file_uploads') ? '<span class="ok">ON</span>' : '<span class="err">OFF - upload zablokowany!</span>' ?></td></tr>
<tr><td>upload_max_filesize</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
<tr><td>post_max_size</td><td><?= ini_get('post_max_size') ?></td></tr>
<tr><td>upload_tmp_dir</td><td><?= htmlspecialchars(ini_get('upload_tmp_dir') ?: '(domyślny)') ?></td></tr>
<tr><td>sys_get_temp_dir()</td><td><?= htmlspecialchars($tmpDir) ?></td></tr>
<tr><td>open_basedir</td><td><?= htmlspecialchars(ini_get('open_basedir') ?: '<span class="ok">brak</span>') ?></td></tr>
</table>

<table>
<tr><th colspan="2">Katalog avatarów: <?= htmlspecialchars($avatarDir) ?></th></tr>
<tr><td>Istnieje</td><td><?= is_dir($avatarDir) ? '<span class="ok">TAK</span>' : '<span class="err">NIE</span>' ?></td></tr>
<tr><td>Zapisywalny</td><td><?= is_writable($avatarDir) ? '<span class="ok">TAK</span>' : '<span class="err">NIE - brak chmod!</span>' ?></td></tr>
</table>

<?php
// Test zapisu
$testFile = $avatarDir . 'test_' . time() . '.tmp';
$wrote = @file_put_contents($testFile, 'test');
$lastErr = error_get_last();
if ($wrote !== false) {
    @unlink($testFile);
    echo '<p class="ok"> Zapis do katalogu działa</p>';
} else {
    echo '<p class="err"> Zapis niemożliwy: ' . htmlspecialchars($lastErr['message'] ?? 'nieznany błąd') . '</p>';
}
?>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['f'])): ?>
<?php
$f    = $_FILES['f'];
$dest = $avatarDir . 'test_' . time() . '.' . pathinfo($f['name'], PATHINFO_EXTENSION);
echo '<table><tr><th colspan="2">Wynik uploadu</th></tr>';
echo '<tr><td>error</td><td>' . $f['error'] . ($f['error'] === 0 ? ' <span class="ok">(OK)</span>' : ' <span class="err">(BŁĄD)</span>') . '</td></tr>';
echo '<tr><td>tmp_name</td><td>' . htmlspecialchars($f['tmp_name']) . '</td></tr>';
echo '<tr><td>tmp istnieje</td><td>' . (file_exists($f['tmp_name']) ? '<span class="ok">TAK</span>' : '<span class="err">NIE</span>') . '</td></tr>';
echo '<tr><td>size</td><td>' . $f['size'] . ' B</td></tr>';
$ok = move_uploaded_file($f['tmp_name'], $dest);
$e  = error_get_last();
echo '<tr><td>move_uploaded_file</td><td>' . ($ok ? '<span class="ok"> SUKCES!</span>' : '<span class="err"> BŁĄD: ' . htmlspecialchars($e['message'] ?? '?') . '</span>') . '</td></tr>';
if ($ok) { @unlink($dest); echo '<tr><td>Plik testowy</td><td><span class="ok">usunięty</span></td></tr>'; }
echo '</table>';
?>
<?php endif; ?>

<h3>Test uploadu</h3>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="f" accept="image/*">
    <button type="submit" style="background:#c8a84b;border:none;padding:8px 16px;cursor:pointer;color:#111;font-weight:bold;margin-left:8px">Testuj</button>
</form>
</body>
</html>
