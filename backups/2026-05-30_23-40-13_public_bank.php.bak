<?php
require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/BankHelpers.php';
require_once __DIR__ . '/../src/Bank/ActionsHandler.php';
require_once __DIR__ . '/../src/Bank/DataLoader.php';

Auth::requireLogin();

$playerId        = Auth::getUserId();
$bankruptcyState = ['is_bankrupt' => false];

try {
    $bankruptcyService = new BankruptcyService($playerId);
    $bankruptcyState   = $bankruptcyService->getState();
} catch (Throwable $e) {
    GameLog::error('bank', 'BankruptcyService init FAILED', $e, ['player' => $playerId]);
}

if (!empty($bankruptcyState['is_bankrupt']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $blockedActions = ['submit_application', 'accept_offer'];
    if (in_array((string)($_POST['action'] ?? ''), $blockedActions, true)) {
        $_SESSION['bankruptcy_notice'] = t('bank.bankruptcy_notice', ['url' => url('recovery')]);
        header('Location: /recovery');
        exit();
    }
}

GameLog::step('bank', 'init', 1, 'start', ['player' => $playerId, 'bankrupt_mode' => !empty($bankruptcyState['is_bankrupt'])]);

// == INICJALIZACJA SERWISOW ==

$bankService = null;
try {
    $bankService = new BankService();
    GameLog::step('bank', 'init', 2, 'BankService OK');
} catch (Throwable $e) {
    GameLog::error('bank', 'BankService init FAILED', $e, ['player' => $playerId]);
}

$bankNeg = null;
if (class_exists('BankNegotiationService')) {
    try {
        $bankNeg = new BankNegotiationService();
        GameLog::step('bank', 'init', 3, 'BankNegotiationService OK');
    } catch (Throwable $e) {
        GameLog::error('bank', 'BankNegotiationService init FAILED', $e, ['player' => $playerId]);
    }
}

// == OBSLUGA FORMULARZY ==

$actions = new BankActionsHandler($playerId, $bankService, $bankNeg);
$actions->handle();
$error   = $actions->error;
$success = $actions->success;

// == POBIERANIE DANYCH ==

$data       = (new BankDataLoader($playerId, $bankService, $bankNeg))->load();
$isBankrupt = !empty($bankruptcyState['is_bankrupt']);

// == CALLABLES HELPEROW ==

$loanStatusBadge = 'loanStatusBadge';
$negStatusBadge  = 'negStatusBadge';
$negTypeLabel    = 'negTypeLabel';
$negEventIcon    = 'negEventIcon';

// == PRZEKAZ DO WIDOKU ==

$viewData = array_merge($data, compact(
    'bankService', 'bankNeg', 'isBankrupt',
    'error', 'success',
    'loanStatusBadge', 'negStatusBadge', 'negTypeLabel', 'negEventIcon'
));
$viewData = array_merge($viewData, GameShell::data($playerId));

$bankTitlePlain = html_entity_decode(strip_tags(tPlain('bank.title')), ENT_QUOTES, 'UTF-8');
$pageTitle = $bankTitlePlain . ' - Oil Empire';
$extraCss  = ['/assets/css/bank.css'];
require_once __DIR__ . '/../templates/header.php';
extract($viewData);
$gameShellTitle = tPlain('bank.title');
$gameShellView  = __DIR__ . '/../templates/views/bank/main.php';
require __DIR__ . '/../templates/components/game_shell.php';

GameLog::info('bank', 'render end', ['player' => $playerId]);
require_once __DIR__ . '/../templates/footer.php';


