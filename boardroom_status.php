<?php
/**
 * boardroom_status.php
 * Lekki endpoint JSON - zwraca czy boardroom wymaga przeladowania
 * (nowe CV gotowe, zmiana skladu zarzadu).
 */
require_once __DIR__ . '/src/init.php';
GameLog::info('boardroom_status.php', 'entry');
Auth::requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance()->getConnection();
$playerId = Auth::getUserId();

try {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM recruitment_requests
        WHERE player_id = ?
          AND status = 'pending'
          AND ready_at <= NOW()
    ");
    $stmt->execute([$playerId]);
    $pendingReady = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM board_members
        WHERE player_id = ?
          AND status = 'active'
    ");
    $stmt->execute([$playerId]);
    $memberCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM candidates
        WHERE expires_at > NOW()
          AND (
              player_id = ?
              OR (player_id IS NULL AND request_id IN (
                  SELECT id FROM recruitment_requests WHERE player_id = ?
              ))
          )
    ");
    $stmt->execute([$playerId, $playerId]);
    $candidateCount = (int)$stmt->fetchColumn();

    echo json_encode([
        'reload' => $pendingReady > 0,
        'member_count' => $memberCount,
        'candidate_count' => $candidateCount,
        'ts' => time(),
    ]);
} catch (Throwable $e) {
    echo json_encode(['reload' => false, 'error' => 'db_error']);
}
