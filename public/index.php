<?php
require_once __DIR__ . '/../src/init.php';

$_pageStart = GameLog::pageStart('public/index.php');

Auth::requireLogin();

function pluralTimeWord(int $value, string $one, string $few, string $many): string
{
    if ($value === 1) {
        return $one;
    }

    $mod10 = $value % 10;
    $mod100 = $value % 100;

    if ($mod10 >= 2 && $mod10 <= 4 && !($mod100 >= 12 && $mod100 <= 14)) {
        return $few;
    }

    return $many;
}

function formatRemainingTime(DateInterval $diff): string
{
    $parts = [];
    $hoursTotal = ((int) $diff->days * 24) + (int) $diff->h;
    $minutes = (int) $diff->i;

    if ($hoursTotal > 0) {
        $parts[] = $hoursTotal . ' ' . pluralTimeWord($hoursTotal, 'godzina', 'godziny', 'godzin');
    }

    if ($minutes > 0 || !$parts) {
        $parts[] = $minutes . ' ' . pluralTimeWord($minutes, 'minuta', 'minuty', 'minut');
    }

    return implode(' ', $parts);
}

$playerId = Auth::getUserId();

// == SERWISY ==

$player      = new Player($playerId);
$well        = new Well($playerId);
$storage     = new Storage($playerId);
$market      = new Market();
$marketTrend = new MarketTrend();

// == POBIERANIE DANYCH ==

$playerData = $player->getData();

if (!$playerData) {
    $playerData = ['cash' => 0, 'status' => 'active', 'capacity' => 0, 'used' => 0];
}

// Dane finansowe gracza
$financialState  = 'normal';
$crisisTicks     = 0;
$crisisLimit     = 6;
$crisisTicksLeft = 0;
$creditScore     = 50;

try {
    $db     = Database::getInstance()->getConnection();
    $fsStmt = $db->prepare("
        SELECT financial_state, crisis_ticks, credit_score,
               COALESCE((SELECT value FROM well_config WHERE `key`='crisis_ticks_base'), 6)        AS base_limit,
               COALESCE((SELECT value FROM well_config WHERE `key`='score_bonus_threshold'), 1000)  AS bonus_thr,
               COALESCE((SELECT value FROM well_config WHERE `key`='score_penalty_threshold'), 300) AS penalty_thr
        FROM players WHERE id = ? LIMIT 1
    ");
    $fsStmt->execute([$playerId]);
    $fsRow = $fsStmt->fetch();
    if ($fsRow) {
        $financialState  = $fsRow['financial_state'] ?? 'normal';
        $crisisTicks     = (int)($fsRow['crisis_ticks'] ?? 0);
        $crisisLimit     = (int)($fsRow['base_limit'] ?? 6);
        $creditScore     = (int)($fsRow['credit_score'] ?? 50);
        if ($creditScore > (int)$fsRow['bonus_thr'])   $crisisLimit += 4;
        if ($creditScore < (int)$fsRow['penalty_thr']) $crisisLimit -= 2;
        $crisisLimit     = max(2, $crisisLimit);
        $crisisTicksLeft = max(0, $crisisLimit - $crisisTicks);
    }
} catch (Throwable $e) {
    GameLog::error('index.php', 'financial_state query FAILED', $e, ['player_id' => $playerId]);
}

$wells = $well->getWells();

// Alert wells (stan techniczny < 50%)
$alertWells = [];
try {
    $__wgForAlerts = WellGridData::prepare($wells, $playerData ?? [], $storage ?? null);
    foreach ($__wgForAlerts['groups'] ?? [] as $__group) {
        foreach ($__group['wells'] ?? [] as $__w) {
            if ((float)($__w['_cond'] ?? 100) < 50 && !in_array($__w['status'] ?? '', ['seized','blowout','sold'], true)) {
                $alertWells[] = $__w;
            }
        }
    }
} catch (Throwable $__e) {
    GameLog::error('index.php', 'alertWells compute FAILED', $__e);
}

if ($playerData && ($playerData['bankruptcy_status'] ?? 'none') === 'recovered') {
    $seizedCount = count(array_filter($wells, fn($w) => $w['status'] === 'seized'));
    if ($seizedCount > 0) {
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("DELETE FROM wells WHERE player_id = ? AND status = 'seized'")->execute([$playerId]);
            $db->prepare("UPDATE bailiff_proceedings SET status = 'completed' WHERE player_id = ? AND status = 'active'")->execute([$playerId]);
            $wells = $well->getWells();
            GameLog::info('index.php', "Cleared {$seizedCount} seized wells after recovery", ['player_id' => $playerId]);
        } catch (Throwable $e) {
            GameLog::error('index.php', 'Cleanup seized wells failed', $e);
        }
    }
}

$marketData  = $market->getState();
$activeTrend = $marketTrend->getActiveTrend();

// Dane trendu rynkowego
$trendClass     = '';
$trendMessage   = '';
$remainingTime  = '';

if ($activeTrend) {
    $trendClass = match($activeTrend['category']) {
        'economic'      => $activeTrend['price_modifier'] > 1.2 ? 'boom'      : 'crisis',
        'political'     => $activeTrend['price_modifier'] > 1.2 ? 'war'       : 'discovery',
        'environmental' => $activeTrend['price_modifier'] > 1.2 ? 'winter'    : 'discovery',
        'technological' => $activeTrend['price_modifier'] > 1.2 ? 'boom'      : 'discovery',
        'social'        => $activeTrend['price_modifier'] > 1.2 ? 'boom'      : 'crisis',
        'military'      => $activeTrend['price_modifier'] > 1.2 ? 'war'       : 'discovery',
        default         => 'crisis',
    };
    $trendMessage  = $marketTrend->getTrendMessage($activeTrend);
    $endTime       = new DateTime($activeTrend['activated_at']);
    $endTime->add(new DateInterval('PT' . $activeTrend['duration_hours'] . 'H'));
    $now           = new DateTime();
    $remainingTime = $endTime > $now
        ? formatRemainingTime($now->diff($endTime))
        : 'konczy sie teraz';
}

// Dane dla redesignu bannera eventu
$eventImpactPerHour  = 0;
$eventRemainingSeconds = 0;
$trendPricePct       = 0;
if ($activeTrend) {
    $endTime2 = new DateTime($activeTrend['activated_at']);
    $endTime2->add(new DateInterval('PT' . (int)$activeTrend['duration_hours'] . 'H'));
    $now2 = new DateTime();
    $eventRemainingSeconds = max(0, $endTime2->getTimestamp() - $now2->getTimestamp());
    $trendPricePct = round(((float)$activeTrend['price_modifier'] - 1) * 100, 0);
    $baseRevH = 0.0;
    foreach ($wells as $__bw) {
        if (($__bw['status'] ?? '') === 'active') {
            $baseRevH += (float)($__bw['base_production_per_hour'] ?? 0) * (float)($marketData['current_price'] ?? 0);
        }
    }
    $eventImpactPerHour = (int)round($baseRevH * ((float)$activeTrend['price_modifier'] - 1));
}

// Status bar
$statusItems = GameShell::statusItems($playerId);
if (isset($statusItems[2])) {
    $statusItems[2]['sub'] = $activeTrend ? (($trendPricePct > 0 ? ' +' : ' ') . $trendPricePct . '% (event)') : '';
    $statusItems[2]['class'] = $activeTrend ? ($trendPricePct < 0 ? 'money cv-bad' : 'money cv-good') : 'money';
}
// Oferty rynkowe
$marketOffer = new MarketOffer();
$myOffers    = $marketOffer->getPlayerOffers($playerId);

// Kredyty
$bankService   = new BankService();
$activeLoans   = $bankService->getActiveLoans($playerId);
$loanAppStatus = $bankService->getLoanApplicationStatus($playerId);
$hasLateLoan   = !empty(array_filter($activeLoans, fn($l) => $l['status'] === 'late'));

// Komornik
$activeBailiff  = null;
$bailiffHoursLeft = 0;
$bailiffStageName = '';
try {
    $db          = Database::getInstance()->getConnection();
    $bailiffStmt = $db->prepare("
        SELECT bp.*, l.remaining_amount AS debt
        FROM bailiff_proceedings bp
        JOIN loans l ON bp.loan_id = l.id
        WHERE bp.player_id = :pid AND bp.status = 'active'
        LIMIT 1
    ");
    $bailiffStmt->execute([':pid' => $playerId]);
    $activeBailiff = $bailiffStmt->fetch() ?: null;
    GameLog::info('index.php', 'bailiff query', ['result' => $activeBailiff ? 'active' : 'none', 'player_id' => $playerId]);

    if ($activeBailiff) {
        $stageNames = [
            1 => t('index.bailiff_stage_1'),
            2 => t('index.bailiff_stage_2'),
            3 => t('index.bailiff_stage_3'),
            4 => t('index.bailiff_stage_4'),
        ];
        $bailiffStageName = $stageNames[$activeBailiff['stage']] ?? '';
        $nextAction       = new DateTime($activeBailiff['next_action_at']);
        $bailiffHoursLeft = max(0, (int)ceil(($nextAction->getTimestamp() - time()) / 3600));
    }
} catch (Throwable $e) {
    GameLog::error('index.php', 'Bailiff query failed', $e, ['player_id' => $playerId]);
}

$seizedWells = [];
try {
    $seizedStmt = $db->prepare("
        SELECT id, location_name AS name, level, base_production_per_hour
        FROM wells
        WHERE player_id = :pid AND status = 'seized'
        ORDER BY level DESC
    ");
    $seizedStmt->execute([':pid' => $playerId]);
    $seizedWells = $seizedStmt->fetchAll();
    GameLog::info('index.php', 'seized wells query', ['count' => count($seizedWells), 'player_id' => $playerId]);
} catch (Throwable $e) {
    GameLog::error('index.php', 'Seized wells query failed', $e, ['player_id' => $playerId]);
}

// Powiadomienia dyrektora
$notifications = [];
try {
    $notificationService = new DirectorNotificationService();
    $notifications       = $notificationService->getUnread($playerId);
} catch (Throwable $e) {
    GameLog::error('index.php', 'DirectorNotificationService failed', $e, ['player_id' => $playerId]);
}

// Powiadomienia techniczne (awarie, incydenty odwiertow) — tylko nieprzeczytane, bez rutynowych zadan.
// Technical notifications (well failures, incidents) — unread only, excluding routine tasks.
$techNotifications = [];
try {
    $tnStmt = Database::getInstance()->getConnection()->prepare(
        "SELECT id, well_id, type, message, created_at
           FROM technical_notifications
          WHERE player_id = ? AND is_read = 0
            AND type != 'task'
          ORDER BY created_at DESC
          LIMIT 10"
    );
    $tnStmt->execute([$playerId]);
    $techNotifications = $tnStmt->fetchAll();
} catch (Throwable $e) {
    GameLog::error('index.php', 'techNotifications query FAILED', $e, ['player_id' => $playerId]);
}

// Akcje
$actions = [
    [
        'type'    => 'link',
        'url'     => url('map'),
        'label'   => ' ' . t('index.action_buy_well'),
        'class'   => 'btn-primary btn-primary--gold',
        'primary' => true,
    ],
    [
        'type'    => 'link',
        'url'     => url('market'),
        'label'   => ' ' . t('index.action_market'),
        'class'   => 'btn-secondary',
        'primary' => false,
    ],
    [
        'type'    => 'link',
        'url'     => url('hr'),
        'label'   => ' ' . t('index.action_hr'),
        'class'   => 'btn-secondary',
        'primary' => false,
    ],
    [
        'type'    => 'link',
        'url'     => url('bank'),
        'label'   => ' ' . t('index.action_bank')
                     . (!empty($activeLoans) ? ' (' . count($activeLoans) . ')' : '')
                     . ($hasLateLoan ? ' ' : ''),
        'class'   => $hasLateLoan ? 'btn-danger' : 'btn-secondary',
        'primary' => false,
    ],
];


$viewData = compact(
    'playerData', 'wells', 'marketData', 'activeTrend',
    'trendClass', 'trendMessage', 'remainingTime',
    'statusItems', 'myOffers', 'activeLoans', 'loanAppStatus', 'hasLateLoan',
    'activeBailiff', 'bailiffHoursLeft', 'bailiffStageName', 'seizedWells',
    'financialState', 'crisisTicks', 'crisisLimit', 'crisisTicksLeft', 'creditScore',
    'notifications', 'actions',
    'alertWells', 'eventImpactPerHour', 'eventRemainingSeconds', 'trendPricePct',
    'techNotifications'
);

$pageTitle  = t('index.title');
$extraCss   = [
    '/assets/css/well-grid.css',
    '/assets/css/home.css',
    '/assets/css/chat.css',
    '/assets/css/director.css',
];
$extraJs    = ['/assets/js/emoji.js', '/assets/js/chat.js'];
require_once __DIR__ . '/../templates/header.php';
require __DIR__ . '/../templates/views/index/main.php';

GameLog::pageEnd('public/index.php', $_pageStart);
require_once __DIR__ . '/../templates/footer.php';
