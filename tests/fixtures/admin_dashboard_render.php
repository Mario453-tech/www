<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/src/i18n.php';
$_SESSION = ['locale' => $argv[2] ?? 'pl'];
class CSRF {
    public static function field(): string { return '<input type="hidden" name="csrf_token" value="test">'; }
    public static function generateToken(): string { return 'test'; }
}
function url(string $path): string { return '/' . $path; }
$surface = $argv[1] ?? 'bank';
$msg = ''; $err = ''; $error = ''; $csrfToken = 'test';
$viewData = [];
switch ($surface) {
    case 'bank':
        $player = ['id' => 1, 'email' => 'long.player.address@example.test', 'cash' => 1234567.89, 'status' => 'active', 'bank_account_number' => '12345678901234567890123456'];
        $viewData = ['players' => [$player], 'selectedId' => 1, 'selectedPlayer' => $player, 'flash' => [], 'selectedHistory' => [
            ['is_inflow' => true, 'signed_amount' => 1234.56, 'transaction_type' => 'admin_adjustment', 'description' => str_repeat('Correction ', 15), 'counterparty_label' => 'System', 'created_at_fmt' => '23.09.2026 12:00'],
        ]];
        break;
    case 'help_editor':
        $page = ['id' => 1, 'slug' => 'start', 'icon' => '', 'title' => 'Start', 'title_en' => 'Start', 'content' => '<p>Help</p>', 'content_en' => '<p>Help</p>', 'active' => 1, 'sort_order' => 0, 'updated_by' => 'tester', 'updated_at' => '2026-09-23'];
        $viewData = ['msg' => '', 'err' => '', 'pages' => [$page], 'editId' => 1, 'editPage' => $page];
        break;
    case 'news':
        $editNews = null;
        $newsList = [['id' => 1, 'is_pinned' => 0, 'created_at' => '2026-09-23', 'title_html' => '<strong>Test</strong>', 'content_plain' => 'Test news', 'created_by' => 'tester']];
        break;
    case 'tech':
        $techNotifications = [['id' => 1, 'type' => 'failure', 'well_id' => 2, 'created_at' => date('Y-m-d H:i:s'), 'message' => 'Test notification'], ['id' => 2, 'type' => 'pressure', 'well_id' => 3, 'created_at' => date('Y-m-d H:i:s'), 'message' => 'Second notification']];
        require dirname(__DIR__, 2) . '/templates/components/tech_notifications.php';
        exit;
    default:
        throw new InvalidArgumentException('Unknown fixture');
}
require dirname(__DIR__, 2) . '/templates/views/admin/' . $surface . '/main.php';
