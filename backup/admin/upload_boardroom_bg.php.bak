<?php
/**
 * Dedykowany endpoint do uploadu ta boardroom (chunked base64).
 * Minimalny kod  adnego HTML, adnego bootstrap SQL.
 */

// Wyczy wszystkie bufory zanim cokolwiek wylemy
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/init.php';
    AdminAuth::requireLogin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['ok' => false, 'err' => 'Tylko POST.']);
    }

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonOut(['ok' => false, 'err' => 'Bd CSRF  odwie stron.']);
    }

    $bgName      = preg_replace('/[^a-z0-9_]/', '', $_POST['bg_name'] ?? '');
    $mime        = trim($_POST['bg_file_mime'] ?? '');
    $chunkIdx    = (int)($_POST['chunk_index']  ?? 0);
    $totalChunks = (int)($_POST['total_chunks'] ?? 1);
    $chunkData   = $_POST['chunk_data'] ?? '';
    $sessId      = session_id() ?: md5(uniqid('', true));

    if (!$bgName)    jsonOut(['ok' => false, 'err' => 'Brak nazwy pliku (wybierz rol).']);
    if (!$chunkData) jsonOut(['ok' => false, 'err' => 'Brak danych chunka.']);

    // Katalog tymczasowy dla chunkw  uywamy assets/images/boardroom/
    $chunkDir = __DIR__ . '/../assets/images/boardroom/';
    if (!is_dir($chunkDir)) {
        if (!mkdir($chunkDir, 0755, true)) {
            jsonOut(['ok' => false, 'err' => 'Nie mona utworzy katalogu. Sprawd uprawnienia /assets/images/boardroom/.']);
        }
    }

    // Zapisz chunk
    $chunkFile = $chunkDir . '.ub_' . $sessId . '_' . $bgName . '_' . $chunkIdx;
    if (file_put_contents($chunkFile, $chunkData) === false) {
        jsonOut(['ok' => false, 'err' => 'Bd zapisu chunka #' . $chunkIdx . '. Sprawd uprawnienia katalogu.']);
    }

    // Ostatni chunk  skadamy plik
    if ($chunkIdx + 1 >= $totalChunks) {
        $fullB64 = '';
        for ($i = 0; $i < $totalChunks; $i++) {
            $cf = $chunkDir . '.ub_' . $sessId . '_' . $bgName . '_' . $i;
            if (!file_exists($cf)) {
                jsonOut(['ok' => false, 'err' => 'Brakuje chunka #' . $i . '. Sprbuj od nowa.']);
            }
            $fullB64 .= file_get_contents($cf);
            unlink($cf);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowedMimes)) {
            jsonOut(['ok' => false, 'err' => 'Niedozwolony format: ' . $mime]);
        }

        $decoded = base64_decode($fullB64, true);
        if ($decoded === false) {
            jsonOut(['ok' => false, 'err' => 'Bd dekodowania base64.']);
        }
        if (strlen($decoded) > 20 * 1024 * 1024) {
            jsonOut(['ok' => false, 'err' => 'Plik za duy (max 20 MB).']);
        }

        $dest = __DIR__ . '/../assets/images/boardroom_bg_' . $bgName . '.png';
        if (file_put_contents($dest, $decoded) !== false) {
            GameLog::info('admin/upload_boardroom_bg', 'bg uploaded', ['name' => $bgName]);
            jsonOut(['ok' => true, 'done' => true, 'msg' => 'Zapisano: boardroom_bg_' . $bgName . '.png']);
        } else {
            jsonOut(['ok' => false, 'err' => 'Bd zapisu pliku docelowego. Sprawd uprawnienia /assets/images/.']);
        }
    }

    // Chunk poredni  czekamy na reszt
    jsonOut(['ok' => true, 'done' => false, 'chunk' => $chunkIdx]);

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/upload_boardroom_bg', 'error', $e);
    }
    jsonOut(['ok' => false, 'err' => 'Bd serwera: ' . $e->getMessage()]);
}
