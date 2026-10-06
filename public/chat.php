<?php
declare(strict_types=1);

// Unified Chat controller - main player entrypoint for chat rooms and private messages.
// Kontroler zunifikowanego czatu - glowny punkt wejscia gracza do pokoi czatu i wiadomosci prywatnych.

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/ChatService.php';
require_once __DIR__ . '/../src/ChatRequestPolicy.php';
require_once __DIR__ . '/../src/ChatRequestLimiter.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
$db       = Database::getInstance()->getConnection();
$chat     = new ChatService($db);
$locale   = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';
$flash = $_SESSION['chat_flash'] ?? '';
unset($_SESSION['chat_flash']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $partner = max(0, (int) ($_POST['partner_id'] ?? 0));
    $slug = (string) ($_POST['room_slug'] ?? 'polski');
    try {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['chat_flash'] = t('common.csrf_error');
        } elseif ((new ChatRequestLimiter($db))->consume('chat:send:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 60) > 0) {
            $_SESSION['chat_flash'] = t('chat.err_rate_limited');
        } elseif ($partner > 0) {
            $chat->sendDirectMessage($playerId, $partner, (string) ($_POST['message'] ?? ''));
        } else {
            $room = $chat->getRoomBySlug($slug);
            $chat->sendRoomMessage($playerId, (int) ($room['id'] ?? 0), (string) ($_POST['message'] ?? ''));
        }
    } catch (Throwable $e) {
        $error = ChatRequestPolicy::error($e);
        $_SESSION['chat_flash'] = t($error['key']);
        if ($error['status'] === 500) {
            GameLog::error('Chat', 'Form submission failed', ['exception_class' => get_class($e)]);
        }
    }
    header('Location: /chat?' . ($partner ? 'with=' . $partner : 'room=' . rawurlencode($slug)), true, 303);
    exit;
}

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
$presenceData  = $chat->getActivePlayers($playerId, 20, $locale);

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
$history = $withPartnerId ? $chat->getDirectMessages($playerId, $withPartnerId, 0, 50, (int) ($_GET['before_id'] ?? 0))
    : $chat->getRoomMessages((int) ($activeRoom['id'] ?? 0), 0, 50, (int) ($_GET['before_id'] ?? 0));

$viewData = compact(
    'rooms',
    'activeRoom',
    'directThreads',
    'presenceData',
    'playerId',
    'withPartnerId',
    'withPartnerName',
    'history',
    'flash'
);
$viewData = array_merge($viewData, GameShell::data($playerId));

$gameShellTitle = t('chat.page_title');
$gameShellView  = __DIR__ . '/../templates/views/chat/main.php';

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
