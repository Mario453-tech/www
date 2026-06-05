<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/credibility.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/CompanyCredibilityService.php';

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

$service = new CompanyCredibilityService($db);

// Widok: lista graczy lub historia jednego gracza / View: player list or single-player history
$viewPlayerId = (int)($_GET['player'] ?? 0);

// == OBSLUGA FORMULARZY / FORM HANDLING ==

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? ''))
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');

    $action = (string)($_POST['action'] ?? '');

    // Reczna korekta wiarygodnosci / Manual credibility adjustment (sekcja 8)
    if ($action === 'manual_adjust') {
        $targetId = (int)($_POST['player_id'] ?? 0);
        $delta    = (int)($_POST['delta'] ?? 0);
        $note     = trim((string)($_POST['note'] ?? ''));

        if ($note === '') {
            $err = t('admin.credibility.err_need_note');
        } elseif ($delta === 0) {
            $err = t('admin.credibility.err_zero_delta');
        } else {
            try {
                $before = $service->getScore($targetId);
                $after  = $service->changeScore($targetId, $delta, 'admin_manual_adjustment', $note);
                AdminLog::log('credibility_manual_adjust',
                    "Gracz {$targetId}: {$before} -> {$after} (delta {$delta}); {$note}");
                $msg = t('admin.credibility.msg_adjusted', [
                    'id' => $targetId, 'before' => $before, 'after' => $after,
                ]);
            } catch (Throwable $e) {
                $err = t('admin.credibility.err_adjust') . ': ' . $e->getMessage();
            }
        }
    }
}

// == DANE DLA WIDOKU / VIEW DATA ==

$players  = [];
$history  = [];
$historyPlayer = null;

try {
    if ($viewPlayerId > 0) {
        // Tryb historii jednego gracza / Single-player history mode
        $pStmt = $db->prepare(
            "SELECT id, username, company_name, company_credibility
               FROM players WHERE id = ? LIMIT 1"
        );
        $pStmt->execute([$viewPlayerId]);
        $historyPlayer = $pStmt->fetch() ?: null;
        if ($historyPlayer) {
            $historyPlayer['score'] = $service->getScore($viewPlayerId);
            $historyPlayer['level'] = $service->getLevel((int)$historyPlayer['score']);
            $history = $service->getHistory($viewPlayerId, 200);
        }
    } else {
        // Lista graczy / Player list
        $players = $db->query(
            "SELECT id, username, company_name,
                    COALESCE(company_credibility, " . CompanyCredibilityService::DEFAULT_SCORE . ") AS score
               FROM players
              ORDER BY score ASC, id ASC
              LIMIT 500"
        )->fetchAll();
        foreach ($players as &$p) {
            $p['level'] = $service->getLevel((int)$p['score']);
        }
        unset($p);
    }
} catch (Throwable $e) {
    $err = t('admin.credibility.err_load') . ': ' . $e->getMessage();
}

// Statystyki / Stats (tylko w trybie listy / list mode only)
$stats = ['players' => 0, 'avg' => 0, 'critical' => 0, 'high' => 0];
if ($viewPlayerId === 0) {
    $stats['players'] = count($players);
    if ($players) {
        $sum = 0;
        foreach ($players as $p) {
            $s = (int)$p['score'];
            $sum += $s;
            if ($s <= 19)  $stats['critical']++;
            if ($s >= 80)  $stats['high']++;
        }
        $stats['avg'] = (int)round($sum / max(1, count($players)));
    }
}

$viewData = compact('players', 'history', 'historyPlayer', 'viewPlayerId', 'stats', 'msg', 'err');

$pageTitle = t('admin.credibility.title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/credibility/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/credibility.php', t('common.unhandled_exception'), $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/credibility.php', $_codexGuardStart);
}
