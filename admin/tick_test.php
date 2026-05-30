<?php
/**
 * tick_test.php diagnostyczny test tiku i logw AdminHub.
 * tick_test.php diagnostic test for tick execution and AdminHub logs.
 */
require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db = Database::getInstance()->getConnection();

// 1. Ostatni tick w tick_stats 
$lastTickStat = null;
$tickStatsExists = false;
try {
    $tickStatsExists = (bool)$db->query("SHOW TABLES LIKE 'tick_stats'")->fetchColumn();
    if ($tickStatsExists) {
        $lastTickStat = $db->query(
            "SELECT * FROM tick_stats ORDER BY id DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) { /* ignore */ }

// 2. Ostatni tick w players.last_tick_at 
$lastPlayerTick = null;
try {
    $lastPlayerTick = $db->query(
        "SELECT MAX(last_tick_at) as lt, COUNT(*) as total FROM players"
    )->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// 3. Ostatni wpis tick_log 
$lastTickLog = null;
$tickLogExists = false;
try {
    $tickLogExists = (bool)$db->query("SHOW TABLES LIKE 'tick_log'")->fetchColumn();
    if ($tickLogExists) {
        $lastTickLog = $db->query(
            "SELECT * FROM tick_log ORDER BY id DESC LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) { /* ignore */ }

// 4. Status tabel logistics 
$logTables = [];
foreach (['logistics_hubs', 'logistics_hub_assignments', 'logistics_hub_config'] as $tbl) {
    try {
        $cnt = $db->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
        $logTables[$tbl] = ['exists' => true, 'count' => (int)$cnt];
    } catch (Throwable $e) {
        $logTables[$tbl] = ['exists' => false, 'count' => 0, 'err' => $e->getMessage()];
    }
}

// 5. Test: czy GameLog::info dziaa i zapisuje 
$logFile = __DIR__ . '/../game_debug.log';
$logSizeBefore = file_exists($logFile) ? filesize($logFile) : 0;
GameLog::info('AdminHub', 'TICK_TEST manual probe', [
    'time'   => date('Y-m-d H:i:s'),
    'source' => 'tick_test.php',
]);
$logSizeAfter = file_exists($logFile) ? filesize($logFile) : 0;
$logWritten = $logSizeAfter > $logSizeBefore;

// 6. Ostatnie 10 linii z logu 
$lastLogLines = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lastLogLines = array_slice($lines, -20);
}

// 7. Czas od ostatniego ticku 
$tickAgoMin = null;
if (!empty($lastPlayerTick['lt'])) {
    $tickAgoMin = round((time() - strtotime($lastPlayerTick['lt'])) / 60, 1);
}
$tickStatAgoMin = null;
if ($tickStatsExists && !empty($lastTickStat['created_at'])) {
    $tickStatAgoMin = round((time() - strtotime($lastTickStat['created_at'])) / 60, 1);
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Tick Diagnostics</title>
<style>
body { font-family: monospace; background: #08080f; color: #e8e8f0; padding: 24px; margin: 0; }
h1 { color: #c8a84b; font-size: 18px; margin-bottom: 4px; }
h2 { color: #c8a84b; font-size: 13px; letter-spacing: 2px; text-transform: uppercase; margin: 24px 0 8px; border-bottom: 1px solid rgba(200,168,75,.2); padding-bottom: 6px; }
.card { background: #0f0f18; border: 1px solid rgba(255,255,255,.07); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.ok   { color: #4ec97a; }
.warn { color: #f0a050; }
.err  { color: #e05555; }
.row  { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,.04); font-size: 13px; }
.row:last-child { border-bottom: none; }
.lbl { color: rgba(232,232,240,.5); }
.val { font-weight: 600; }
.log-box { background: #050508; border: 1px solid rgba(255,255,255,.06); border-radius: 6px; padding: 12px; font-size: 11px; line-height: 1.6; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
.badge-ok   { background: rgba(78,201,122,.15); color: #4ec97a; border: 1px solid rgba(78,201,122,.3); }
.badge-warn { background: rgba(240,160,80,.15);  color: #f0a050; border: 1px solid rgba(240,160,80,.3); }
.badge-err  { background: rgba(224,85,85,.15);   color: #e05555; border: 1px solid rgba(224,85,85,.3); }
form { margin: 0; }
.btn { background: rgba(200,168,75,.15); border: 1px solid rgba(200,168,75,.4); color: #c8a84b; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-family: monospace; font-size: 13px; }
.btn:hover { background: rgba(200,168,75,.25); }
.btn-red { background: rgba(224,85,85,.15); border-color: rgba(224,85,85,.4); color: #e05555; }
.sub { font-size: 11px; color: rgba(232,232,240,.35); margin-top: 2px; }
</style>
</head>
<body>
<h1> Tick Diagnostics  OilCorp</h1>
<p class="sub">Strona diagnostyczna  tylko dla admina. <a href="/admin" style="color:#c8a84b"> Powrt</a></p>

<h2>1. Ostatni Tick</h2>
<div class="card">
    <div class="row">
        <span class="lbl">players.last_tick_at (MAX)</span>
        <span class="val">
            <?php if ($lastPlayerTick['lt']): ?>
                <?= htmlspecialchars($lastPlayerTick['lt']) ?>
                 <span class="<?= $tickAgoMin > 15 ? 'err' : ($tickAgoMin > 8 ? 'warn' : 'ok') ?>">
                    <?= $tickAgoMin ?> min temu
                </span>
                <span class="badge <?= $tickAgoMin > 15 ? 'badge-err' : ($tickAgoMin > 8 ? 'badge-warn' : 'badge-ok') ?>">
                    <?= $tickAgoMin > 15 ? 'CRON ZATRZYMANY' : ($tickAgoMin > 8 ? 'OPNIENIE' : 'OK') ?>
                </span>
            <?php else: ?>
                <span class="err">brak danych</span>
            <?php endif ?>
        </span>
    </div>
    <div class="row">
        <span class="lbl">Liczba graczy w tabeli</span>
        <span class="val"><?= (int)($lastPlayerTick['total'] ?? 0) ?></span>
    </div>
    <?php if ($tickStatsExists && $lastTickStat): ?>
    <div class="row">
        <span class="lbl">tick_stats  ostatni wpis</span>
        <span class="val">
            <?= htmlspecialchars($lastTickStat['created_at'] ?? '') ?>
             <span class="<?= $tickStatAgoMin > 15 ? 'err' : 'ok' ?>"><?= $tickStatAgoMin ?> min temu</span>
        </span>
    </div>
    <div class="row">
        <span class="lbl">rdo / trend / cena ropy</span>
        <span class="val">
            <?= htmlspecialchars($lastTickStat['source'] ?? '') ?>
            / <?= htmlspecialchars($lastTickStat['trend_name'] ?? 'brak trendu') ?>
            / $<?= number_format((float)($lastTickStat['oil_price'] ?? 0), 2) ?>
        </span>
    </div>
    <div class="row">
        <span class="lbl">Gracze przetworzeni</span>
        <span class="val"><?= (int)($lastTickStat['players_processed'] ?? 0) ?></span>
    </div>
    <?php elseif (!$tickStatsExists): ?>
    <div class="row"><span class="lbl">tick_stats</span><span class="val warn">tabela nie istnieje</span></div>
    <?php endif ?>
</div>

<h2>2. Tabele Logistics</h2>
<div class="card">
    <?php foreach ($logTables as $tbl => $info): ?>
    <div class="row">
        <span class="lbl"><?= $tbl ?></span>
        <span class="val">
            <?php if ($info['exists']): ?>
                <span class="ok"> istnieje</span>  <?= $info['count'] ?> wierszy
            <?php else: ?>
                <span class="err"> brak tabeli</span>
                <?php if (!empty($info['err'])): ?>
                    <span class="sub"><?= htmlspecialchars($info['err']) ?></span>
                <?php endif ?>
            <?php endif ?>
        </span>
    </div>
    <?php endforeach ?>
</div>

<h2>3. Test zapisu GameLog</h2>
<div class="card">
    <div class="row">
        <span class="lbl">Plik logu</span>
        <span class="val"><?= htmlspecialchars($logFile) ?></span>
    </div>
    <div class="row">
        <span class="lbl">Rozmiar przed / po</span>
        <span class="val"><?= $logSizeBefore ?> / <?= $logSizeAfter ?> bajtw</span>
    </div>
    <div class="row">
        <span class="lbl">Zapis dziaa?</span>
        <span class="val">
            <?php if ($logWritten): ?>
                <span class="badge badge-ok"> TAK  GameLog zapisuje</span>
            <?php else: ?>
                <span class="badge badge-err"> NIE  sprawd uprawnienia pliku lub ciek</span>
            <?php endif ?>
        </span>
    </div>
</div>

<h2>4. Ostatnie 20 linii game_debug.log</h2>
<div class="log-box"><?php
    if (empty($lastLogLines)) {
        echo '(brak wpisw lub plik nie istnieje)';
    } else {
        foreach ($lastLogLines as $line) {
            $color = '#e8e8f0';
            if (strpos($line, '[ERROR]') !== false || strpos($line, 'ERROR') !== false) $color = '#e05555';
            elseif (strpos($line, '[WARN]') !== false)  $color = '#f0a050';
            elseif (strpos($line, 'AdminHub') !== false) $color = '#c8a84b';
            elseif (strpos($line, '[tick]') !== false || strpos($line, "'tick'") !== false) $color = '#4ec97a';
            echo '<span style="color:' . $color . '">' . htmlspecialchars($line) . '</span>' . "\n";
        }
    }
?></div>

<h2>5. Diagnostyka Logistics</h2>
<div class="card">
<?php
// Przykadowe huby
try {
    $hubs = $db->query("SELECT id, name, region_id, player_id, status, slot_limit FROM logistics_hubs LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    if ($hubs) {
        echo '<p style="font-size:11px;color:rgba(232,232,240,.5);margin-bottom:8px">Przykadowe huby (max 5):</p>';
        foreach ($hubs as $h) {
            $isSys = ($h['player_id'] == 0) ? '<span style="color:#c8a84b">systemowy</span>' : 'gracz #'.(int)$h['player_id'];
            echo '<div class="row"><span class="lbl">Hub #'.(int)$h['id'].'  '.htmlspecialchars($h['name']).'</span>'
               . '<span class="val">status: '.htmlspecialchars($h['status']).' | sloty: '.(int)$h['slot_limit'].' | '.$isSys.'</span></div>';
        }
    }
} catch (Throwable $e) {
    echo '<p class="err">Bd: '.htmlspecialchars($e->getMessage()).'</p>';
}

// Odwierty gracza i czy maj przypisania
try {
    $wellsTotal = $db->query("SELECT COUNT(*) FROM wells WHERE status NOT IN ('sold','abandoned')")->fetchColumn();
    $wellsAssigned = $db->query("SELECT COUNT(DISTINCT well_id) FROM logistics_hub_assignments WHERE status='active'")->fetchColumn();
    echo '<div class="row"><span class="lbl">Aktywne odwierty cznie</span><span class="val">'.(int)$wellsTotal.'</span></div>';
    echo '<div class="row"><span class="lbl">Odwierty z aktywnym przypisaniem</span><span class="val '.((int)$wellsAssigned > 0 ? 'ok' : 'warn').'">'.(int)$wellsAssigned.'</span></div>';
    $unassigned = (int)$wellsTotal - (int)$wellsAssigned;
    echo '<div class="row"><span class="lbl">Odwierty BEZ przypisania</span><span class="val '.($unassigned > 0 ? 'warn' : 'ok').'">'.$unassigned.'</span></div>';
} catch (Throwable $e) {
    echo '<p class="err">Bd: '.htmlspecialchars($e->getMessage()).'</p>';
}
?>
    <p class="sub" style="margin-top:10px">Jeli "Odwierty z przypisaniem = 0"  gracze nie przypisali odwiertw do hubw. Przypisania robi gracz rcznie lub tick logistyczny.</p>
</div>

<h2>6. Wymu Tick (test)</h2>
<div class="card">
    <p class="sub" style="margin-bottom:12px">Uruchomi cron/tick.php z flag FORCE_TICK_INTERNAL. Sprawd logi po wykonaniu.</p>
    <form method="post" action="/admin/force_tick.php" data-confirm="Wymusi tick?" data-confirm-type="warning" data-confirm-title="Wymu tick" data-confirm-label="Wymu tick">
        <input type="hidden" name="csrf_token" value="<?= CSRF::generateToken() ?>">
        <button type="submit" class="btn"> Wymu Tick teraz</button>
    </form>
    <p class="sub" style="margin-top:8px">Po klikniciu odwie t stron za ~3 sekundy i sprawd sekcj 1 i 4.</p>
</div>

</body>
</html>
