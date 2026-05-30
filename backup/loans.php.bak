<?php
require_once __DIR__ . '/init.php';
GameLog::info('loans.php', 'entry');
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

//  SPRAWD STRUKTURÊ TABEL 
$loansColumns = [];
try {
    $cols = $db->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_COLUMN);
    $loansColumns = array_flip($cols);
} catch (PDOException $e) {}

$needsMigration = !isset($loansColumns['remaining_amount']);
$tablesExist    = isset($loansColumns['id']);
try { $db->query("SELECT 1 FROM loan_applications LIMIT 1"); }
catch (PDOException $e) { $tablesExist = false; }

$settingsExist = true;
try { $db->query("SELECT 1 FROM bank_settings LIMIT 1"); }
catch (PDOException $e) { $settingsExist = false; }

//  AKCJE 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? ''))
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');

    $action = $_POST['action'] ?? '';

    // Globalne parametry banku
    if ($action === 'save_settings' && $settingsExist) {
        $settings = new BankSettings();
        $adminUser = $_SESSION['admin_user'] ?? 'admin';
        $keys = ['apr_multiplier', 'risk_tolerance_modifier', 'max_amount_multiplier'];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $val = round((float)$_POST[$key], 4);
                $val = max(0.1, min(5.0, $val)); // Zakres bezpieczeñstwa
                $old = BankSettings::get($key);
                $settings->set($key, $val, $adminUser);
                AdminLog::log(
                    'bank_settings_change',
                    BankSettings::label($key) . ": {$old}  {$val}",
                    null, 'system'
                );
            }
        }
        $msg = t('admin.loans.msg_settings_saved');
    }

    elseif (!$needsMigration && $tablesExist) {
        $appId  = (int)($_POST['app_id']  ?? 0);
        $procId = (int)($_POST['proc_id'] ?? 0);

        // Override: zatwierdŸ wniosek
        if ($action === 'admin_approve' && $appId) {
            $amount = (float)($_POST['override_amount'] ?? 0);
            $rate   = (float)($_POST['override_rate']   ?? 18.0);
            $reason = trim($_POST['override_reason']    ?? 'Decyzja administracyjna');
            if ($amount > 0) {
                $db->prepare("
                    UPDATE loan_applications
                    SET status='approved', approved_amount=:amt, interest_rate=:rate,
                        rejection_reason=:reason, decided_at=NOW(),
                        expires_at=DATE_ADD(NOW(), INTERVAL 48 HOUR)
                    WHERE id=:id AND status IN ('pending','rejected')
                ")->execute([':amt'=>$amount,':rate'=>$rate,':reason'=>$reason.' [ADMIN]',':id'=>$appId]);
                AdminLog::log('loan_admin_approve', "Override #{$appId}: {$amount}$ @ {$rate}% — {$reason}", null, 'system', $appId);
                $msg = t('admin.loans.msg_approved', ['id' => $appId]);
            } else { $err = t('admin.loans.err_amount_zero'); }
        }

        // Override: odrzuæ wniosek
        elseif ($action === 'admin_reject' && $appId) {
            $reason = trim($_POST['reject_reason'] ?? 'Decyzja administracyjna');
            $db->prepare("
                UPDATE loan_applications SET status='rejected',
                rejection_reason=:reason, decided_at=NOW()
                WHERE id=:id AND status IN ('pending','approved')
            ")->execute([':reason'=>$reason.' [ADMIN]',':id'=>$appId]);
            AdminLog::log('loan_admin_reject', "Override odrzucenia #{$appId}: {$reason}", null, 'system', $appId);
            $msg = t('admin.loans.msg_rejected', ['id' => $appId]);
        }

        // Zamknij postêpowanie komornicze
        elseif ($action === 'close_bailiff' && $procId) {
            $row = $db->prepare("SELECT loan_id, player_id FROM bailiff_proceedings WHERE id=:id");
            $row->execute([':id'=>$procId]);
            $proc = $row->fetch();
            if ($proc) {
                $db->prepare("UPDATE bailiff_proceedings SET status='completed', completed_at=NOW() WHERE id=:id")
                   ->execute([':id'=>$procId]);
                $db->prepare("UPDATE loans SET status='active', late_since=NULL WHERE id=:id")
                   ->execute([':id'=>$proc['loan_id']]);
                AdminLog::log('bailiff_closed', "Zamkniêto postêpowanie #{$procId}", $proc['player_id'], 'system', $procId);
                $msg = t('admin.loans.msg_bailiff_closed', ['id' => $procId]);
            }
        }

        // Przesuñ etap komornika
        elseif ($action === 'bailiff_advance' && $procId) {
            $row = $db->prepare("SELECT stage, player_id FROM bailiff_proceedings WHERE id=:id");
            $row->execute([':id'=>$procId]);
            $proc = $row->fetch();
            if ($proc && $proc['stage'] < 4) {
                $newStage = $proc['stage'] + 1;
                $db->prepare("UPDATE bailiff_proceedings SET stage=:s, next_action_at=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=:id")
                   ->execute([':s'=>$newStage,':id'=>$procId]);
                AdminLog::log('bailiff_advance', "Przesuniêto etap {$proc['stage']}{$newStage} dla postêpowania #{$procId}", $proc['player_id'], 'system', $procId);
                $msg = t('admin.loans.msg_bailiff_advanced', ['stage' => $newStage]);
            }
        }

        // Wymuœ bankructwo przez komornika
        elseif ($action === 'bailiff_bankruptcy' && $procId) {
            $row = $db->prepare("SELECT loan_id, player_id FROM bailiff_proceedings WHERE id=:id");
            $row->execute([':id'=>$procId]);
            $proc = $row->fetch();
            if ($proc) {
                $reason = trim($_POST['bankruptcy_reason'] ?? t('admin.loans.default_reason'));
                $db->prepare("UPDATE players SET status='bankrupt', bankruptcy_at=NOW() WHERE id=:id")
                   ->execute([':id'=>$proc['player_id']]);
                $db->prepare("UPDATE loans SET status='defaulted' WHERE id=:id")
                   ->execute([':id'=>$proc['loan_id']]);
                $db->prepare("UPDATE bailiff_proceedings SET status='bankruptcy', completed_at=NOW() WHERE id=:id")
                   ->execute([':id'=>$procId]);
                AdminLog::log('forced_bankruptcy', "Wymuszone bankructwo przez admina: {$reason}", $proc['player_id'], 'system', $procId);
                $msg = t('admin.loans.msg_bankruptcy', ['pid' => $proc['player_id']]);
            }
        }
    }
}

//  DANE 
$filter = $_GET['filter'] ?? 'pending';
if (!in_array($filter, ['pending','approved','rejected','accepted','expired','all'])) $filter = 'all';

$settings     = $settingsExist ? (new BankSettings())->getAll() : [];
$applications = $loans = $proceedings = [];
$stats        = ['pending'=>0,'approved'=>0,'accepted'=>0,'rejected'=>0,'total'=>0];
$loanStats    = ['active'=>0,'late'=>0,'defaulted'=>0,'total_debt'=>0,'avg_apr'=>0];
$bankruptcies = ['day'=>0,'week'=>0];
$sysStatus    = $settingsExist ? BankSettings::systemStatus() : 'unknown';

if ($tablesExist) {
    $where = $filter !== 'all' ? "WHERE la.status = :status" : "";
    $s = $db->prepare("
        SELECT la.*, p.email AS player_email
        FROM loan_applications la JOIN players p ON la.player_id = p.id
        {$where} ORDER BY la.created_at DESC LIMIT 200
    ");
    if ($filter !== 'all') $s->bindValue(':status', $filter);
    $s->execute();
    $applications = $s->fetchAll();

    $stats = $db->query("
        SELECT SUM(status='pending') AS pending, SUM(status='approved') AS approved,
               SUM(status='accepted') AS accepted, SUM(status='rejected') AS rejected,
               COUNT(*) AS total FROM loan_applications
    ")->fetch() ?: $stats;
}

if ($tablesExist && !$needsMigration) {
    $loans = $db->query("
        SELECT l.*, p.email AS player_email
        FROM loans l JOIN players p ON l.player_id = p.id
        WHERE l.status IN ('active','late')
        ORDER BY l.status DESC, l.created_at DESC
    ")->fetchAll();

    $proceedings = $db->query("
        SELECT bp.*, p.email AS player_email, l.remaining_amount AS debt, l.id AS loan_id_val
        FROM bailiff_proceedings bp
        JOIN players p ON bp.player_id = p.id
        JOIN loans l   ON bp.loan_id   = l.id
        WHERE bp.status = 'active'
        ORDER BY bp.next_action_at ASC
    ")->fetchAll();

    $loanStats = $db->query("
        SELECT SUM(status='active') AS active, SUM(status='late') AS late,
               SUM(status='defaulted') AS defaulted,
               SUM(remaining_amount) AS total_debt,
               ROUND(AVG(interest_rate),1) AS avg_apr
        FROM loans
    ")->fetch() ?: $loanStats;

    $bankruptcies['day']  = (int)$db->query("SELECT COUNT(*) FROM players WHERE bankruptcy_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    $bankruptcies['week'] = (int)$db->query("SELECT COUNT(*) FROM players WHERE bankruptcy_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
}

//  RENDER 
function aprBadge(float $r): string {
    if ($r >= 40) return "<span class='badge badge-bankrupt'>{$r}% APR</span>";
    if ($r >= 28) return "<span class='badge badge-paused'>{$r}% APR</span>";
    return                "<span class='badge badge-active'>{$r}% APR</span>";
}
function appBadge(string $s): string {
    return match($s) {
        'pending'  => "<span class='badge badge-paused'> " . t('admin.loans.filter_pending')  . "</span>",
        'approved' => "<span class='badge badge-active'> " . t('admin.loans.filter_approved') . "</span>",
        'rejected' => "<span class='badge badge-bankrupt'> " . t('admin.loans.filter_rejected') . "</span>",
        'accepted' => "<span class='badge badge-active'> " . t('admin.loans.filter_accepted') . "</span>",
        'expired'  => "<span class='badge badge-inactive'> " . t('admin.loans.filter_expired')  . "</span>",
        default    => "<span class='badge badge-inactive'>" . htmlspecialchars($s) . "</span>",
    };
}

$stageLabel = [
    '1' => t('admin.loans.bailiff_stage_1'),
    '2' => t('admin.loans.bailiff_stage_2'),
    '3' => t('admin.loans.bailiff_stage_3'),
    '4' => t('admin.loans.bailiff_stage_4'),
];
$sysStatusLabel = [
    'normal'  => t('admin.loans.sys_normal'),
    'tight'   => t('admin.loans.sys_tight'),
    'crisis'  => t('admin.loans.sys_crisis'),
    'loose'   => t('admin.loans.sys_loose'),
    'unknown' => t('admin.loans.sys_unknown'),
];
$sysStatusBadge = [
    'normal'  => 'badge-active',
    'tight'   => 'badge-paused',
    'crisis'  => 'badge-bankrupt',
    'loose'   => 'badge-active',
    'unknown' => 'badge-inactive',
];
$filterLabels = [
    'pending'  => t('admin.loans.filter_pending'),
    'approved' => t('admin.loans.filter_approved'),
    'rejected' => t('admin.loans.filter_rejected'),
    'accepted' => t('admin.loans.filter_accepted'),
    'expired'  => t('admin.loans.filter_expired'),
    'all'      => t('admin.loans.filter_all'),
];
$settingDescs = [
    'apr_multiplier'          => ['min'=>0.5,'max'=>3.0,'step'=>0.05,'hint'=>t('admin.loans.hint_apr')],
    'risk_tolerance_modifier' => ['min'=>0.3,'max'=>2.0,'step'=>0.05,'hint'=>t('admin.loans.hint_risk')],
    'max_amount_multiplier'   => ['min'=>0.3,'max'=>2.0,'step'=>0.05,'hint'=>t('admin.loans.hint_max')],
];

$viewData = [
    'msg'           => $msg,
    'err'           => $err,
    'liveScores'    => [],
    'needsMigration'=> $needsMigration,
    'tablesExist'   => $tablesExist,
    'settingsExist' => $settingsExist,
    'settings'      => $settings,
    'applications'  => $applications,
    'loans'         => $loans,
    'proceedings'   => $proceedings,
    'stats'         => $stats,
    'loanStats'     => $loanStats,
    'bankruptcies'  => $bankruptcies,
    'sysStatus'     => $sysStatus,
    'filter'        => $filter,
    'stageLabel'    => $stageLabel,
    'sysStatusLabel'=> $sysStatusLabel,
    'sysStatusBadge'=> $sysStatusBadge,
    'filterLabels'  => $filterLabels,
    'settingDescs'  => $settingDescs,
];

$pageTitle = t('admin.loans.page_title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/loans/main.php';
require_once __DIR__ . '/partials/footer.php';

