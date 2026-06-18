<?php
/**
 * admin/bank.php
 * Panel administratora - zarzadzanie kontami bankowymi graczy.
 * Admin panel - managing player bank accounts.
 *
 * Akcje POST:
 *   action=admin_credit  - dodanie srodkow (admin_adjustment)
 *   action=admin_debit   - pobranie srodkow (admin_adjustment)
 *
 * POST actions:
 *   action=admin_credit  - add funds (admin_adjustment)
 *   action=admin_debit   - subtract funds (admin_adjustment)
 */

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/bank.php') : microtime(true);

try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/BankAccountService.php';
require_once __DIR__ . '/../src/FinancialTransactionService.php';
require_once __DIR__ . '/../src/DirectorNotificationService.php';

$db  = Database::getInstance()->getConnection();
$bas = new BankAccountService();
$fts = new FinancialTransactionService($db);

// Zapewnij schemat bankowy (idempotentne DDL).
// Ensure bank schema (idempotent DDL).
try { $bas->ensureSchema(); } catch (Throwable $e) {
    GameLog::error('admin/bank.php', 'ensureSchema failed', $e);
}

$flash     = [];
$selectedId = (int)($_GET['player_id'] ?? 0);

// ---- Obsluga POST (PRG) / Handle POST (PRG) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $flash = ['type' => 'error', 'msg' => 'Nieprawidlowy token CSRF. / Invalid CSRF token.'];
    } else {
        $action    = $_POST['action'] ?? '';
        $targetPid = (int)($_POST['player_id'] ?? 0);
        $note      = trim($_POST['note'] ?? '');

        // Sanityzacja kwoty (przecinek -> kropka, spacje).
        // Amount sanitization (comma -> dot, spaces).
        $rawAmt = preg_replace('/\s+/u', '', (string)($_POST['amount'] ?? '0'));
        if (strpos($rawAmt, '.') === false && strpos($rawAmt, ',') !== false) {
            $rawAmt = str_replace(',', '.', $rawAmt);
        }
        $amount = (float)$rawAmt;

        if ($targetPid <= 0) {
            $flash = ['type' => 'error', 'msg' => 'Brak ID gracza. / Missing player ID.'];
        } elseif ($amount <= 0) {
            $flash = ['type' => 'error', 'msg' => 'Kwota musi byc wieksza od zera. / Amount must be greater than zero.'];
        } elseif ($note === '') {
            $flash = ['type' => 'error', 'msg' => 'Opis korekty jest wymagany. / Note is required.'];
        } elseif ($action === 'admin_credit') {
            $res = $fts->credit($targetPid, $amount, FinancialTransactionService::TYPE_ADMIN_ADJUSTMENT, $note);
            if ($res['success']) {
                // Powiadom gracza / Notify player
                try {
                    $ns = new DirectorNotificationService();
                    $ns->create($targetPid, 'admin_adjustment', [
                        'direction' => 'dodano',
                        'amount'    => number_format($res['amount'], 2, ',', ' '),
                        'note'      => $note,
                    ], 168);
                } catch (Throwable $e) {
                    GameLog::error('admin/bank.php', 'Notification failed (credit)', $e);
                }
                $flash = ['type' => 'success', 'msg' => 'Dodano ' . number_format($res['amount'], 2, ',', ' ') . ' PLN graczowi #' . $targetPid . '.'];
                $selectedId = $targetPid;
            } else {
                $flash = ['type' => 'error', 'msg' => 'Blad: ' . ($res['error'] ?? 'nieznany') . ' / Error: ' . ($res['error'] ?? 'unknown')];
            }
        } elseif ($action === 'admin_debit') {
            $res = $fts->debit($targetPid, $amount, FinancialTransactionService::TYPE_ADMIN_ADJUSTMENT, $note);
            if ($res['success']) {
                // Powiadom gracza / Notify player
                try {
                    $ns = new DirectorNotificationService();
                    $ns->create($targetPid, 'admin_adjustment', [
                        'direction' => 'pobrano',
                        'amount'    => number_format($res['amount'], 2, ',', ' '),
                        'note'      => $note,
                    ], 168);
                } catch (Throwable $e) {
                    GameLog::error('admin/bank.php', 'Notification failed (debit)', $e);
                }
                $flash = ['type' => 'success', 'msg' => 'Pobrano ' . number_format($res['amount'], 2, ',', ' ') . ' PLN od gracza #' . $targetPid . '.'];
                $selectedId = $targetPid;
            } else {
                $flash = ['type' => 'error', 'msg' => 'Blad: ' . ($res['error'] ?? 'nieznany') . ' / Error: ' . ($res['error'] ?? 'unknown')];
            }
        } else {
            $flash = ['type' => 'error', 'msg' => 'Nieznana akcja. / Unknown action.'];
        }

        // PRG - przekieruj z komunikatem w sesji.
        // PRG - redirect with flash message in session.
        if (!isset($_SESSION)) { session_start(); }
        $_SESSION['admin_bank_flash'] = $flash;
        header('Location: /admin/bank.php' . ($selectedId ? '?player_id=' . $selectedId : ''));
        exit();
    }
}

// Odczyt flash z sesji (PRG).
// Read flash from session (PRG).
if (!isset($_SESSION)) { session_start(); }
if (isset($_SESSION['admin_bank_flash'])) {
    $flash = $_SESSION['admin_bank_flash'];
    unset($_SESSION['admin_bank_flash']);
}

// ---- Dane graczy (lista) / Player list ----
$players = [];
try {
    $players = $db->query("
        SELECT p.id, p.email, p.cash, p.status, p.bank_account_number
        FROM players p
        ORDER BY p.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    GameLog::error('admin/bank.php', 'fetchPlayers failed', $e);
}

// ---- Dane wybranego gracza / Selected player data ----
$selectedPlayer  = null;
$selectedHistory = [];
if ($selectedId > 0) {
    try {
        $stmt = $db->prepare("SELECT id, email, cash, status, bank_account_number FROM players WHERE id = :id");
        $stmt->execute([':id' => $selectedId]);
        $selectedPlayer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        GameLog::error('admin/bank.php', 'fetchSelectedPlayer failed', $e);
    }

    if ($selectedPlayer) {
        try {
            // Historia transakcji wybranego gracza (ostatnie 50).
            // Transaction history for selected player (last 50).
            $histStmt = $db->prepare("
                SELECT bt.*,
                       fp.email AS from_email,
                       tp.email AS to_email
                FROM bank_transactions bt
                LEFT JOIN players fp ON fp.id = bt.from_player_id
                LEFT JOIN players tp ON tp.id = bt.to_player_id
                WHERE bt.from_player_id = :pid OR bt.to_player_id = :pid2
                ORDER BY bt.created_at DESC
                LIMIT 50
            ");
            $histStmt->execute([':pid' => $selectedId, ':pid2' => $selectedId]);
            $rawHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawHistory as $row) {
                $isInflow = (int)($row['to_player_id'] ?? 0) === $selectedId;
                $signed   = $isInflow ? (float)$row['amount'] : -(float)$row['amount'];
                $row['is_inflow']     = $isInflow;
                $row['signed_amount'] = $signed;
                $row['created_at_fmt'] = date('d.m.Y H:i', strtotime($row['created_at']));
                // Oznacz strone / Label the counterparty
                if ($isInflow) {
                    $row['counterparty_label'] = $row['from_player_id'] === null
                        ? 'System'
                        : ('#' . $row['from_player_id'] . ' ' . ($row['from_email'] ?? ''));
                } else {
                    $row['counterparty_label'] = $row['to_player_id'] === null
                        ? 'System'
                        : ('#' . $row['to_player_id'] . ' ' . ($row['to_email'] ?? ''));
                }
                $selectedHistory[] = $row;
            }
        } catch (Throwable $e) {
            GameLog::error('admin/bank.php', 'fetchHistory failed', $e);
        }
    }
}

$viewData = [
    'players'        => $players,
    'selectedId'     => $selectedId,
    'selectedPlayer' => $selectedPlayer,
    'selectedHistory'=> $selectedHistory,
    'flash'          => $flash,
];

$pageTitle = 'Bank — panel administratora';
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/bank/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/bank.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Blad aplikacji. / Application error.';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/bank.php', $_codexGuardStart);
    }
}
