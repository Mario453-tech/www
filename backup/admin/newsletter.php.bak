<?php
/**
 * admin/newsletter.php — Newsletter to players via TinyMCE.
 *
 * GET   compose form
 * POST action=preview  render preview (same page)
 * POST action=send     send to all subscribers OR specific player
 */
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/newsletter.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db    = Database::getInstance()->getConnection();
$admin = AdminAuth::getAdminUsername();

// Ensure newsletter_log table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS newsletter_log (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        subject   VARCHAR(255)  NOT NULL,
        body_html MEDIUMTEXT    NOT NULL,
        sent_to   INT           NOT NULL DEFAULT 0,
        sent_by   VARCHAR(64)   NOT NULL DEFAULT '',
        sent_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status    ENUM('sent','failed','partial') NOT NULL DEFAULT 'sent',
        notes     VARCHAR(512)  NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    GameLog::error('admin/newsletter', 'CREATE TABLE failed', $e);
}

$msg         = '';
$err         = '';
$previewHtml = '';
$action      = '';
$subject     = '';
$bodyHtml    = '';
$sendTarget  = 'all'; // 'all' | 'single'
$singleEmail = '';

// Helper: build email footer HTML (avoids t() escaping HTML entities)
function nlFooterHtml(string $unsubUrl): string
{
    $esc = htmlspecialchars($unsubUrl, ENT_QUOTES);
    return 'Wiadomo&#347;&#263; wys&#322;ana przez OilCorp. '
         . 'Zaloguj si&#281; na <a href="https://oilempire.pl" style="color:#c8a84b">oilempire.pl</a>.'
         . '<br><a href="' . $esc . '" style="color:#7a7a99;font-size:11px">Wypisz si&#281; z newslettera</a>';
}

// Helper: get or generate newsletter_token for a player
function nlGetToken(PDO $db, int $playerId): string
{
    $row = $db->prepare("SELECT newsletter_token FROM players WHERE id = ? LIMIT 1");
    $row->execute([$playerId]);
    $tok = $row->fetchColumn();
    if (!$tok) {
        $tok = bin2hex(random_bytes(16));
        $db->prepare("UPDATE players SET newsletter_token = ? WHERE id = ?")->execute([$tok, $playerId]);
    }
    return $tok;
}

// Helper: build unsubscribe URL — always uses production base_url from config
function nlUnsubUrl(string $token): string
{
    $cfg     = require __DIR__ . '/../config/mail.php';
    $baseUrl = rtrim($cfg['base_url'] ?? 'https://oilempire.pl', '/');
    return "{$baseUrl}/newsletter-unsubscribe?token={$token}";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action      = $_POST['action']       ?? '';

        //  History log management (no subject/body needed) 
        if ($action === 'delete_log') {
            $logId = (int)($_POST['log_id'] ?? 0);
            if ($logId > 0) {
                $db->prepare("DELETE FROM newsletter_log WHERE id = ?")->execute([$logId]);
                AdminLog::log('newsletter_log_delete', "Usuniêto wpis newsletter_log #{$logId}");
                GameLog::info('admin/newsletter', 'Newsletter log entry deleted', ['id' => $logId, 'by' => $admin]);
            }
            $msg = t('admin.newsletter.msg_log_deleted');

        } elseif ($action === 'clear_log') {
            $db->exec("DELETE FROM newsletter_log");
            AdminLog::log('newsletter_log_clear', 'Wyczyszczono ca³¹ historiê newsletter_log');
            GameLog::info('admin/newsletter', 'Newsletter log cleared', ['by' => $admin]);
            $msg = t('admin.newsletter.msg_log_cleared');

        } else {
        //  Compose / send flow 
        $subject     = trim($_POST['subject'] ?? '');
        $bodyHtml    = $_POST['body_html']    ?? '';
        $sendTarget  = ($_POST['send_target'] ?? 'all') === 'single' ? 'single' : 'all';
        $singleEmail = trim($_POST['single_email'] ?? '');

        if (empty($subject)) {
            $err = t('admin.newsletter.err_subject_empty');
        } elseif (empty(trim(strip_tags($bodyHtml)))) {
            $err = t('admin.newsletter.err_body_empty');
        } elseif ($sendTarget === 'single' && !filter_var($singleEmail, FILTER_VALIDATE_EMAIL)) {
            $err = t('admin.newsletter.err_single_email_invalid');

        } elseif ($action === 'preview') {
            require_once __DIR__ . '/../src/EmailTemplate.php';
            $previewHtml = EmailTemplate::build(
                htmlspecialchars($subject),
                t('admin.newsletter.preview_greeting'),
                $bodyHtml,
                null,
                null,
                t('admin.newsletter.preview_footer')
            );

        } elseif ($action === 'send') {

            require_once __DIR__ . '/../src/Mailer.php';
            require_once __DIR__ . '/../src/EmailTemplate.php';

            if ($sendTarget === 'single') {
                // Send to one specific player (bypass subscription check)
                $sStmt = $db->prepare("SELECT id, email, username FROM players WHERE email = ? LIMIT 1");
                $sStmt->execute([$singleEmail]);
                $singlePlayer = $sStmt->fetch();

                if (!$singlePlayer) {
                    $err = t('admin.newsletter.err_single_not_found');
                } else {
                    $tok  = nlGetToken($db, (int)$singlePlayer['id']);
                    $unsub = nlUnsubUrl($tok);
                    $greeting = 'Czeœæ <strong style="color:#c8a84b">'
                        . htmlspecialchars($singlePlayer['username']) . '</strong>,';
                    $footerHtml = nlFooterHtml($unsub);

                    $html = EmailTemplate::build(
                        htmlspecialchars($subject),
                        $greeting,
                        $bodyHtml,
                        null, null,
                        $footerHtml
                    );

                    $ok = Mailer::send($singlePlayer['email'], $subject, $html);
                    $db->prepare("INSERT INTO newsletter_log (subject,body_html,sent_to,sent_by,status,notes)
                                  VALUES (?,?,?,?,?,?)")
                       ->execute([$subject, $bodyHtml, $ok ? 1 : 0, $admin,
                                  $ok ? 'sent' : 'failed',
                                  "Single: {$singlePlayer['email']}"]);

                    AdminLog::log('newsletter_sent', "Test do: {$singlePlayer['email']} | Temat: '{$subject}'");
                    GameLog::info('admin/newsletter', 'Newsletter single sent', [
                        'to'     => $singlePlayer['email'],
                        'subject'=> $subject,
                        'ok'     => $ok,
                    ]);
                    $msg = t('admin.newsletter.msg_sent_single', ['email' => $singlePlayer['email']]);
                    $subject = '';
                    $bodyHtml = '';
                }

            } else {
                // Send to all subscribed, verified players
                $recipients = $db->query("
                    SELECT id, email, username, newsletter_token
                    FROM players
                    WHERE COALESCE(newsletter_subscribed, 1) = 1
                      AND COALESCE(email_verified, 1) = 1
                      AND status != 'suspended'
                      AND email IS NOT NULL AND email != ''
                    ORDER BY id ASC
                ")->fetchAll();

                if (empty($recipients)) {
                    $err = t('admin.newsletter.err_no_recipients');
                } else {
                    $sent = 0; $failed = 0;

                    foreach ($recipients as $r) {
                        $tok  = $r['newsletter_token'] ?: nlGetToken($db, (int)$r['id']);
                        $unsub = nlUnsubUrl($tok);
                        $greeting = 'Czeœæ <strong style="color:#c8a84b">'
                            . htmlspecialchars($r['username']) . '</strong>,';
                        $footerHtml = nlFooterHtml($unsub);

                        $html = EmailTemplate::build(
                            htmlspecialchars($subject),
                            $greeting,
                            $bodyHtml,
                            null, null,
                            $footerHtml
                        );

                        if (Mailer::send($r['email'], $subject, $html)) {
                            $sent++;
                        } else {
                            $failed++;
                        }
                    }

                    $status = ($failed === 0) ? 'sent' : (($sent === 0) ? 'failed' : 'partial');
                    $db->prepare("INSERT INTO newsletter_log (subject,body_html,sent_to,sent_by,status,notes)
                                  VALUES (?,?,?,?,?,?)")
                       ->execute([$subject, $bodyHtml, $sent, $admin, $status,
                                  $failed > 0 ? "B³êdy: {$failed}" : null]);

                    AdminLog::log('newsletter_sent', "Newsletter '{$subject}'  {$sent} graczy");
                    GameLog::info('admin/newsletter', 'Newsletter bulk sent', [
                        'subject' => $subject,
                        'sent'    => $sent,
                        'failed'  => $failed,
                        'by'      => $admin,
                    ]);

                    $msg      = t('admin.newsletter.msg_sent', ['sent' => $sent, 'failed' => $failed]);
                    $subject  = '';
                    $bodyHtml = '';
                }
            }
        }
        } // end else (compose/send flow)
    }
}

// History (last 20 campaigns)
$history = $db->query("SELECT * FROM newsletter_log ORDER BY sent_at DESC LIMIT 20")->fetchAll();

// Stats
$statsRow = $db->query("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN COALESCE(newsletter_subscribed,1)=1
                         AND COALESCE(email_verified,1)=1
                         AND status != 'suspended'
                    THEN 1 ELSE 0 END) AS eligible,
           SUM(CASE WHEN COALESCE(newsletter_subscribed,1)=0 THEN 1 ELSE 0 END) AS unsubscribed
    FROM players
")->fetch();

$pageTitle = t('admin.newsletter.page_title');
$viewData  = compact(
    'msg', 'err', 'previewHtml', 'action',
    'subject', 'bodyHtml', 'sendTarget', 'singleEmail',
    'history', 'statsRow'
);
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/newsletter/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/newsletter.php', 'Unhandled exception', $e);
    if (!headers_sent()) http_response_code(500);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/newsletter.php', $_codexGuardStart);
}
