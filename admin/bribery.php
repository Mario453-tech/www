<?php
declare(strict_types=1);

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/bribery.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/BriberyService.php';

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

$config = new BriberyConfig($db);

// == OBSLUGA FORMULARZA / FORM HANDLING ==

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_bribery_config') {
        // Sanityzacja na granicy: zakresy procentowe 0-100, mnozniki i kary >= 0.
        // Boundary sanitisation: percentage ranges 0-100, multipliers and penalties >= 0.
        $clampPct  = static fn($v) => max(0, min(100, (int)$v));
        $clampMult = static fn($v) => max(0.0, min(20.0, (float)$v));

        $values = [
            'enabled'                     => isset($_POST['enabled']) ? '1' : '0',
            'base_cost_pct'               => (string)$clampPct($_POST['base_cost_pct'] ?? 50),
            'credibility_penalty_success' => (string)max(0, min(100, (int)($_POST['credibility_penalty_success'] ?? 4))),
            'credibility_penalty_caught'  => (string)max(0, min(100, (int)($_POST['credibility_penalty_caught'] ?? 15))),
            'cooldown_extra_minutes'      => (string)max(0, (int)($_POST['cooldown_extra_minutes'] ?? 120)),
        ];
        foreach (BriberyConfig::LEVELS as $level) {
            $values['catch_pct_' . $level]  = (string)$clampPct($_POST['catch_pct_' . $level] ?? 0);
            $values['price_mult_' . $level] = (string)$clampMult($_POST['price_mult_' . $level] ?? 1.0);
        }

        try {
            $config->save($values);
            AdminLog::log('bribery_config_save', 'Zapis konfiguracji lapowek: ' . json_encode($values));
            $msg = t('admin.bribery.msg_saved');
        } catch (Throwable $e) {
            $err = t('admin.bribery.err_save') . ': ' . $e->getMessage();
        }
    }
}

// == DANE DLA WIDOKU / VIEW DATA ==

$settings = $config->all();

// Ostatnie zdarzenia lapowkowe z historii reputacji (podglad skutkow).
// Recent bribery events from the reputation history (effect preview).
$recentEvents = [];
try {
    $recentEvents = $db->query(
        "SELECT l.player_id, l.event_key, l.delta, l.score_after, l.note, l.created_at,
                p.username, p.company_name
           FROM company_credibility_log l
           LEFT JOIN players p ON p.id = l.player_id
          WHERE l.event_key IN ('bribe_paid', 'bribe_caught')
          ORDER BY l.id DESC
          LIMIT 50"
    )->fetchAll();
} catch (Throwable $e) {
    // Brak tabeli historii nie przerywa panelu / missing history table never breaks the panel
}

$viewData = compact('settings', 'recentEvents', 'msg', 'err');

$pageTitle = t('admin.bribery.title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/bribery/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/bribery.php', t('common.unhandled_exception'), $e);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/bribery.php', $_codexGuardStart);
    }
}
