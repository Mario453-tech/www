<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// No application bootstrap: this report must never migrate or repair data.
// Bez bootstrapu aplikacji: raport nigdy nie migruje ani nie naprawia danych.
$config = require dirname(__DIR__) . '/config/database.php';
$options = getopt('', ['limit:']);
$limit = max(1, min(1000, (int)($options['limit'] ?? 100)));
$queries = [
    'nontransactional_tables' => "SELECT TABLE_NAME AS table_name, ENGINE AS engine
        FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'",
    'duplicate_active_locations' => "SELECT location_id, COUNT(*) AS active_wells,
        GROUP_CONCAT(id ORDER BY id) AS well_ids, GROUP_CONCAT(DISTINCT player_id ORDER BY player_id) AS player_ids
        FROM wells WHERE location_id IS NOT NULL AND status NOT IN ('sold','seized')
        GROUP BY location_id HAVING COUNT(*) > 1",
    'negative_oil' => 'SELECT player_id, used, capacity FROM storage WHERE used < 0',
    'invalid_offer_escrow' => "SELECT id, player_id, status, amount, locked_amount FROM market_offers
        WHERE amount <= 0 OR locked_amount < 0 OR (status = 'pending' AND locked_amount != amount)",
    'duplicate_offer_credits' => "SELECT reference_id AS offer_id, to_player_id AS player_id,
        COUNT(*) AS credits, SUM(amount) AS credited
        FROM bank_transactions WHERE reference_type = 'market_offer' AND transaction_type = 'market_sale'
        GROUP BY reference_id, to_player_id HAVING COUNT(*) > 1",
    'offer_payment_mismatch' => "SELECT o.id AS offer_id, o.player_id, o.status,
        o.sold_amount * o.sold_price AS expected, COALESCE(t.credited, 0) AS credited,
        COALESCE(t.credits, 0) AS credits
        FROM market_offers o LEFT JOIN
        (SELECT reference_id, to_player_id, SUM(amount) AS credited, COUNT(*) AS credits
         FROM bank_transactions WHERE reference_type = 'market_offer' AND transaction_type = 'market_sale'
         GROUP BY reference_id, to_player_id) t ON t.reference_id = o.id AND t.to_player_id = o.player_id
        WHERE (o.status = 'completed' AND (COALESCE(t.credits, 0) != 1 OR ABS(COALESCE(t.credited, 0) - o.sold_amount * o.sold_price) > 0.01))
           OR (o.status != 'completed' AND COALESCE(t.credits, 0) > 0)",
    'invalid_loan_balances' => "SELECT id, player_id, status, principal_amount, remaining_amount
        FROM loans WHERE remaining_amount < 0 OR (status = 'paid_off' AND remaining_amount != 0)",
    'repeated_legal_fees_same_second_candidates' => "SELECT from_player_id AS player_id,
        reference_type, reference_id, created_at, COUNT(*) AS charges, SUM(amount) AS charged
        FROM bank_transactions WHERE transaction_type = 'legal_fee'
          AND reference_type IN ('legal_region','legal_hub_region')
        GROUP BY from_player_id, reference_type, reference_id, created_at HAVING COUNT(*) > 1",
    'invalid_financial_amounts' => 'SELECT id, from_player_id, to_player_id, amount, transaction_type
        FROM bank_transactions WHERE amount <= 0',
];

try {
    $db = new PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';charset=' . $config['charset'],
        $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $report = ['database' => $config['dbname'], 'generated_at_utc' => gmdate(DATE_ATOM), 'read_only' => true,
        'limit_per_check' => $limit,
        'limitations' => [
            'Findings are audit candidates, not proof of abuse. No repairs are performed.',
            'Historical ledger retention or manual adjustments can explain payment mismatches.',
            'Old instant sales did not retain an oil movement ledger; historic overselling cannot be reconstructed completely.',
            'Sold and seized wells are excluded from active-location duplicates; sold history is preserved.',
        ], 'checks' => []];
    $errors = false;
    foreach ($queries as $name => $sql) {
        try {
            $count = (int)$db->query('SELECT COUNT(*) FROM (' . $sql . ') audit_rows')->fetchColumn();
            $rows = $db->query($sql . ' LIMIT ' . $limit)->fetchAll();
            $report['checks'][$name] = ['count' => $count, 'rows' => $rows, 'truncated' => $count > $limit];
        } catch (Throwable $e) {
            $errors = true;
            $report['checks'][$name] = ['error' => $e->getMessage()];
        }
    }
    $db->rollBack();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($errors ? 1 : 0);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Audit failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
