<?php
declare(strict_types=1);

/**
 * GET /api/v1/player
 *
 * Zwraca dane gracza: gotowka, saldo konta, cena ropy, magazyn, statystyki.
 * Returns player data: cash, bank balance, oil price, storage, statistics.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];
$db       = Database::getInstance()->getConnection();

// Dane gracza / Player data
$stmt = $db->prepare("
    SELECT id, username, company_name, cash, bank_balance, financial_state,
           crisis_ticks, credit_score, offline_mode, offline_since,
           last_tick_at, last_active_at, created_at,
           DATEDIFF(NOW(), created_at) AS company_age_days,
           safety_procedures_level, procedure_integrity,
           bankruptcy_status
      FROM players WHERE id = ? LIMIT 1
");
$stmt->execute([$playerId]);
$row = $stmt->fetch();
if (!$row) {
    apiError(404, 'Player not found');
}

// Magazyn / Storage
$storageStmt = $db->prepare(
    "SELECT capacity AS max_bbl, used AS current_bbl FROM storage WHERE player_id = ? LIMIT 1"
);
$storageStmt->execute([$playerId]);
$storage = $storageStmt->fetch() ?: ['max_bbl' => 0, 'current_bbl' => 0];

// Aktualna cena ropy / Current oil price
// Czytamy wiersz singleton id=1 — ten sam, ktory aktualizuje cron i czyta web
// (src/Market.php, src/MarketTick.php). ORDER BY id DESC moglby trafic w inny wiersz.
// Read the id=1 singleton — same row the cron writes and the web reads.
$priceStmt = $db->query(
    "SELECT current_price FROM market_state WHERE id = 1 LIMIT 1"
);
$oilPrice = (float)($priceStmt->fetchColumn() ?: 0);

// Liczba aktywnych studni / Active well count
$wellsStmt = $db->prepare(
    "SELECT COUNT(*) AS cnt FROM wells WHERE player_id = ? AND status = 'active'"
);
$wellsStmt->execute([$playerId]);
$activeWells = (int)($wellsStmt->fetchColumn() ?: 0);

// Aktywne pozyczki / Active loans
$loansStmt = $db->prepare(
    "SELECT COUNT(*) AS cnt FROM loans WHERE player_id = ? AND status = 'active'"
);
$loansStmt->execute([$playerId]);
$activeLoans = (int)($loansStmt->fetchColumn() ?: 0);

apiJson([
    'id'               => (int)$row['id'],
    'username'         => $row['username'],
    'company_name'     => $row['company_name'] ?? $row['username'],
    'cash'             => round((float)$row['cash'], 2),
    'bank_balance'     => round((float)($row['bank_balance'] ?? 0), 2),
    'oil_price'        => round($oilPrice, 2),
    'company_age_days' => (int)($row['company_age_days'] ?? 0),
    'financial_state'  => $row['financial_state'] ?? 'normal',
    'crisis_ticks'     => (int)($row['crisis_ticks'] ?? 0),
    'credit_score'     => (int)($row['credit_score'] ?? 50),
    'offline_mode'     => (bool)$row['offline_mode'],
    'offline_since'    => $row['offline_since'],
    'last_tick_at'     => $row['last_tick_at'],
    'last_active_at'   => $row['last_active_at'],
    'safety_level'     => (int)($row['safety_procedures_level'] ?? 0),
    'procedure_integrity' => (int)($row['procedure_integrity'] ?? 100),
    'bankruptcy_status'=> $row['bankruptcy_status'] ?? null,
    'storage' => [
        'current_bbl' => round((float)$storage['current_bbl'], 2),
        'max_bbl'     => round((float)$storage['max_bbl'], 2),
        'pct_used'    => $storage['max_bbl'] > 0
            ? round((float)$storage['current_bbl'] / (float)$storage['max_bbl'] * 100, 1)
            : 0.0,
    ],
    'stats' => [
        'active_wells' => $activeWells,
        'active_loans' => $activeLoans,
    ],
]);
