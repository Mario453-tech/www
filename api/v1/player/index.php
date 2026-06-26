<?php
declare(strict_types=1);

/**
 * GET /api/v1/player
 *
 * Zwraca dane gracza: gotowka, stan finansowy, magazyn, statystyki.
 * Returns player data: cash, financial state, storage, statistics.
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
    SELECT id, username, cash, financial_state, crisis_ticks, credit_score,
           offline_mode, offline_since, last_tick_at, last_active_at,
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
    'cash'             => round((float)$row['cash'], 2),
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
