<?php
declare(strict_types=1);

// Unified Chat controller - main player entrypoint for chat rooms and private messages.
// Kontroler zunifikowanego czatu - glowny punkt wejscia gracza do pokoi czatu i wiadomosci prywatnych.

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/ChatService.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
$db       = Database::getInstance()->getConnection();
$chat     = new ChatService($db);
$locale   = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';

// Check if a direct partner is requested via query string (?with=123)
// Sprawdz czy wskazano partnera do rozmowy prywatnej w parametrze (?with=123)
$withPartnerId = isset($_GET['with']) ? (int) $_GET['with'] : null;
$withPartnerName = '';
if ($withPartnerId && $withPartnerId !== $playerId) {
    $stmt = $db->prepare("SELECT COALESCE(NULLIF(company_name, ''), username) FROM players WHERE id = ? LIMIT 1");
    $stmt->execute([$withPartnerId]);
    $withPartnerName = (string) ($stmt->fetchColumn() ?: '');
}

// Requested room slug (default: 'polski')
// Zazadany slug pokoju (domyslnie: 'polski')
$requestedRoomSlug = trim((string) ($_GET['room'] ?? 'polski'));

$rooms         = $chat->getRooms($playerId, $locale);
$directThreads = $chat->getDirectThreads($playerId);
$presenceData  = $chat->getActivePlayers($playerId, 20);

// Find active room
// Znajdz aktywny pokoj
$activeRoom = null;
foreach ($rooms as $r) {
    if ($r['slug'] === $requestedRoomSlug) {
        $activeRoom = $r;
        break;
    }
}
if (!$activeRoom && !empty($rooms)) {
    $activeRoom = $rooms[0];
}

$pageTitle = t('chat.page_title');
$extraCss  = ['/assets/css/chat.css'];
$extraJs   = ['/assets/js/chat.js'];

$viewData = compact(
    'rooms',
    'activeRoom',
    'directThreads',
    'presenceData',
    'playerId',
    'withPartnerId',
    'withPartnerName'
);
$viewData = array_merge($viewData, GameShell::data($playerId));

$gameShellTitle = t('chat.page_title');
$gameShellView  = __DIR__ . '/../templates/views/chat/main.php';

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
