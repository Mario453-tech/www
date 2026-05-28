<?php
require_once __DIR__ . '/src/init.php';
Auth::requireLogin();

$db       = Database::getInstance()->getConnection();
$playerId = Auth::getUserId();

$playersStmt = $db->prepare("
    SELECT id, COALESCE(NULLIF(company_name,''), username) AS name
    FROM players WHERE status='active' AND id != ?
    ORDER BY name ASC LIMIT 50
");
$playersStmt->execute([$playerId]);
$otherPlayers = $playersStmt->fetchAll(PDO::FETCH_ASSOC);

$withId   = isset($_GET['with']) ? (int)$_GET['with'] : null;
$withName = '';
if ($withId) {
    $s = $db->prepare("SELECT COALESCE(NULLIF(company_name,''), username) FROM players WHERE id=? LIMIT 1");
    $s->execute([$withId]);
    $withName = $s->fetchColumn() ?: '?';
}

$myId     = $playerId;
$pageTitle= t('dm.page_title');
$extraCss = ['/assets/css/chat.css'];
$extraJs  = ['/assets/js/emoji.js', '/assets/js/dm.js'];

$viewData = compact('otherPlayers', 'withId', 'withName', 'myId');
$viewData = array_merge($viewData, GameShell::data($playerId));
$gameShellTitle = t('dm.page_title');
$gameShellView = __DIR__ . '/templates/views/dm/main.php';

require_once __DIR__ . '/templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/templates/components/game_shell.php';
require_once __DIR__ . '/templates/footer.php';
