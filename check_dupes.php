<?php
$c = file_get_contents(__DIR__ . '/lang/pl.php');
$keys = [
    'bank.action_err_csrf',
    'news.time_days_ago',
    'auth.email_verify_title',
    'board_access.denied',
    'bailiff.bankruptcy_notification',
];
foreach ($keys as $k) {
    $count = substr_count($c, "'$k'");
    echo "$k: $count wystapien" . ($count > 1 ? ' <-- DUPLIKAT' : '') . "\n";
}
