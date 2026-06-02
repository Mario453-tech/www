<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('boardroom.php') : microtime(true);
try {

require_once __DIR__ . '/src/init.php';
Auth::requireLogin();
require_once __DIR__ . '/src/HRService.php';

$playerId = (int)($_SESSION['user_id'] ?? 0);
$db = Database::getInstance()->getConnection();

try {
    $hrBoardroom = new HRService();
    $hrBoardroom->processReadyRecruitments();
} catch (Throwable $e) {
    GameLog::error('boardroom.php', 'processReadyRecruitments failed', $e, ['player_id' => $playerId]);
}


// Pobierz aktywnych pracowników TEGO GRACZA
$stmt = $db->prepare("
    SELECT bm.*, br.code as role_code, br.name as role_name,
           TIMESTAMPDIFF(YEAR, bm.birth_date, CURDATE()) as age,
           DATEDIFF(CURDATE(), bm.hired_at) as days_employed
    FROM board_members bm
    JOIN board_roles br ON bm.role_id = br.id
    WHERE bm.status = 'active' AND bm.player_id = ?
    ORDER BY br.id
");
$stmt->execute([$playerId]);
$boardMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aktywne rekrutacje TEGO GRACZA
$stmt = $db->prepare("
    SELECT rr.*, br.code as role_code, br.name as role_name,
           TIMESTAMPDIFF(SECOND, NOW(), rr.ready_at) as seconds_remaining
    FROM recruitment_requests rr
    JOIN board_roles br ON rr.role_id = br.id
    WHERE rr.player_id = ?
      AND rr.initiated_by = 'director'
      AND COALESCE(rr.spec_code, '') = ''
      AND (
          rr.status = 'pending'
          OR (
              rr.status = 'ready'
              AND EXISTS (
                  SELECT 1
                  FROM candidates c
                  WHERE c.request_id = rr.id
                    AND c.expires_at > NOW()
              )
          )
      )
    ORDER BY rr.requested_at DESC
");
$stmt->execute([$playerId]);
$activeRecruitments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kandydaci TEGO GRACZA
$stmt = $db->prepare("
    SELECT role_id, COUNT(*) as count
    FROM candidates
    WHERE expires_at > NOW()
      AND (player_id = ? OR (player_id IS NULL AND request_id IN
          (SELECT id
           FROM recruitment_requests
           WHERE player_id = ?
             AND initiated_by = 'director'
             AND COALESCE(spec_code, '') = '')))
    GROUP BY role_id
");
$stmt->execute([$playerId, $playerId]);
$candidateCounts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $candidateCounts[$row['role_id']] = $row['count'];
}

// Mapowanie pracowników wed³ug roli
$membersByRole = [];
foreach ($boardMembers as $member) {
    $membersByRole[$member['role_code']] = $member;
}

// Gotowe rekrutacje (z kandydatami)
$readyRecruitments = [];
foreach ($activeRecruitments as $recruitment) {
    if ($recruitment['status'] === 'ready' && isset($candidateCounts[$recruitment['role_id']])) {
        $readyRecruitments[] = $recruitment;
    }
}

$occupiedSeats = count($boardMembers) + 1; // +1 za dyrektora
$totalSeats = 10;

// Boardroom config (header, footer)
$brConfig = [];
try {
    $cfgRows = $db->query("SELECT `key`, `value` FROM boardroom_config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $brConfig = $cfgRows;
} catch (Throwable $e) {
    GameLog::error('boardroom.php', 'boardroom_config query failed', $e);
}
$headerTitle    = htmlspecialchars($brConfig['header_title']    ?? 'Sala Zarz¹du');
$headerSubtitle = htmlspecialchars($brConfig['header_subtitle'] ?? '');
$headerImage    = $brConfig['header_image'] ?? '';
$footerText     = htmlspecialchars($brConfig['footer_text']     ?? '');
$footerLinks    = json_decode($brConfig['footer_links'] ?? '[]', true) ?: [];

// Extended roles for sidebar
$allRoles = [];
try {
    $allRoles = $db->query("SELECT * FROM board_roles ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    GameLog::error('boardroom.php', 'board_roles query failed', $e);
}

$brLang = [
    'tooltip_director'         => t('br_js.tooltip_director'),
    'tooltip_hr_required'      => t('br_js.tooltip_hr_required'),
    'tooltip_locked'           => t('br_js.tooltip_locked'),
    'tooltip_recruit'          => t('br_js.tooltip_recruit'),
    'tooltip_occupied'         => t('br_js.tooltip_occupied'),
    'tooltip_candidates'       => t('br_js.tooltip_candidates'),
    'tooltip_recruiting'       => t('br_js.tooltip_recruiting'),
    'tooltip_recruiting_no_hr' => t('br_js.tooltip_recruiting_no_hr'),
    'hr_panel_title'           => t('br_js.hr_panel_title'),
    'hr_panel_desc'            => t('br_js.hr_panel_desc'),
    'hr_open_btn'              => t('br_js.hr_open_btn'),
    'tech_panel_title'         => t('br_js.tech_panel_title'),
    'tech_panel_desc'          => t('br_js.tech_panel_desc'),
    'tech_open_btn'            => t('br_js.tech_open_btn'),
    'logistics_panel_title'    => t('br_js.logistics_panel_title'),
    'logistics_panel_desc'     => t('br_js.logistics_panel_desc'),
    'logistics_open_btn'       => t('br_js.logistics_open_btn'),
    'legal_panel_title'        => t('br_js.legal_panel_title'),
    'legal_panel_desc'         => t('br_js.legal_panel_desc'),
    'fire_btn'                 => t('br_js.fire_btn'),
    'close_btn'                => t('br_js.close_btn'),
    'cancel_btn'               => t('br_js.cancel_btn'),
    'hire_selected_btn'        => t('br_js.hire_selected_btn'),
    'recruit_title'            => t('br_js.recruit_title'),
    'recruit_hr_note'          => t('br_js.recruit_hr_note'),
    'recruit_director_note'    => t('br_js.recruit_director_note'),
    'recruit_region_label'     => t('br_js.recruit_region_label'),
    'seat_director'            => t('br_js.seat_director'),
    'seat_hr'                  => t('br_js.seat_hr'),
    'seat_technical'           => t('br_js.seat_technical'),
    'seat_finance'             => t('br_js.seat_finance'),
    'seat_legal'               => t('br_js.seat_legal'),
    'seat_logistics'           => t('br_js.seat_logistics'),
    'region_PL'                => t('br_js.region_PL'),
    'region_UK'                => t('br_js.region_UK'),
    'region_US'                => t('br_js.region_US'),
    'region_NO'                => t('br_js.region_NO'),
    'region_RU'                => t('br_js.region_RU'),
    'region_PH'                => t('br_js.region_PH'),
    'region_AE'                => t('br_js.region_AE'),
    'region_dur_1'             => t('br_js.region_dur_1'),
    'region_dur_2'             => t('br_js.region_dur_2'),
    'region_dur_4'             => t('br_js.region_dur_4'),
    'recruit_submit_btn'       => t('br_js.recruit_submit_btn'),
    'candidates_subtitle'      => t('br_js.candidates_subtitle'),
    'confirm_hire'             => t('br_js.confirm_hire'),
    'confirm_fire'             => t('br_js.confirm_fire'),
    'prompt_fire_reason'       => t('br_js.prompt_fire_reason'),
    'prompt_fire_default'      => t('br_js.prompt_fire_default'),
    'toast_recruited'          => t('br_js.toast_recruited'),
    'toast_hired'              => t('br_js.toast_hired'),
    'toast_err'                => t('br_js.toast_err'),
    'toast_fired'              => t('br_js.toast_fired'),
    'age'                      => t('br_js.age'),
    'experience'               => t('br_js.experience'),
    'experience_meta'          => t('br_js.experience_meta'),
    'salary'                   => t('br_js.salary'),
    'seniority'                => t('br_js.seniority'),
    'days_unit'                => t('br_js.days_unit'),
    'years_unit'               => t('br_js.years_unit'),
    'skills_header'            => t('br_js.skills_header'),
    'expected_salary'          => t('br_js.expected_salary'),
    'avg_score'                => t('br_js.avg_score'),
    'expires_in'               => t('br_js.expires_in'),
    'skill_org'                => t('br_js.skill_org'),
    'skill_neg'                => t('br_js.skill_neg'),
    'skill_ana'                => t('br_js.skill_ana'),
    'skill_str'                => t('br_js.skill_str'),
    'skill_eth'                => t('br_js.skill_eth'),
    'hr_badge_required'        => t('br_js.hr_badge_required'),
    'rec_badge'                => t('br_js.rec_badge'),
];

$recLang = [
    'modal_title'    => t('rec_js.modal_title'),
    'age'            => t('rec_js.age'),
    'waiting_title'  => t('rec_js.waiting_title'),
    'waiting_msg'    => t('rec_js.waiting_msg'),
    'close_btn'      => t('rec_js.close_btn'),
    'cancel_btn'     => t('rec_js.cancel_btn'),
    'hire_btn'       => t('rec_js.hire_btn'),
    'no_candidates'  => t('rec_js.no_candidates'),
    'confirm_hire'   => t('rec_js.confirm_hire'),
    'alert_hired'    => t('rec_js.alert_hired'),
    'alert_select'   => t('rec_js.alert_select'),
    'err_start'      => t('rec_js.err_start'),
    'err_hire'       => t('rec_js.err_hire'),
    'err_response'   => t('rec_js.err_response'),
    'exp_years'      => t('rec_js.exp_years'),
    'expected_salary'=> t('rec_js.expected_salary'),
    'avg_score'      => t('rec_js.avg_score'),
    'expires_in'     => t('rec_js.expires_in'),
    'skill_org'      => t('rec_js.skill_org'),
    'skill_neg'      => t('rec_js.skill_neg'),
    'skill_ana'      => t('rec_js.skill_ana'),
    'skill_str'      => t('rec_js.skill_str'),
    'skill_eth'      => t('rec_js.skill_eth'),
    'nat_PL'         => t('rec_js.nat_PL'),
    'nat_DE'         => t('rec_js.nat_DE'),
    'nat_CZ'         => t('rec_js.nat_CZ'),
    'nat_UK'         => t('rec_js.nat_UK'),
    'nat_US'         => t('rec_js.nat_US'),
];

$viewData = [
    'boardMembers'       => $boardMembers,
    'membersByRole'      => $membersByRole,
    'activeRecruitments' => $activeRecruitments,
    'readyRecruitments'  => $readyRecruitments,
    'candidateCounts'    => $candidateCounts,
    'roleIdByCode'       => array_column($allRoles, 'id', 'code'),
    'csrfToken'          => CSRF::generateToken(),
    'occupiedSeats'      => $occupiedSeats,
    'totalSeats'         => $totalSeats,
    'headerTitle'        => $headerTitle,
    'headerSubtitle'     => $headerSubtitle,
    'headerImage'        => $headerImage,
    'footerText'         => $footerText,
    'footerLinks'        => $footerLinks,
    'brLang'             => $brLang,
    'recLang'            => $recLang,
];
$viewData = array_merge($viewData, GameShell::data($playerId));

$pageTitle = t('boardroom.page_title');
$extraCss = [
    '/assets/css/boardroom.css',
    '/assets/css/recruitment.css',
];
$gameShellTitle = t('boardroom.page_title');
$gameShellView = __DIR__ . '/templates/views/boardroom/main.php';

require_once __DIR__ . '/templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/templates/components/game_shell.php';
require_once __DIR__ . '/templates/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('boardroom.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) http_response_code(500);
    echo t('boardroom.err_app');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('boardroom.php', $_codexGuardStart);
    }
}
