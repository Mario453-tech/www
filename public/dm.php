<?php
declare(strict_types=1);

// Legacy direct messages controller - permanent redirect to unified chat.
// Kontroler wiadomosci prywatnych (legacy) - trwale przekierowanie do zunifikowanego czatu.

require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();

$withId = isset($_GET['with']) ? (int) $_GET['with'] : null;
$target = '/chat' . ($withId > 0 ? '?with=' . $withId : '');

header('Location: ' . $target, true, 301);
exit;
