<?php
declare(strict_types=1);

/**
 * GET /api/v1/market
 *
 * Zwraca aktualna cene ropy, trend i oferty rynkowe gracza.
 * Returns current oil price, trend, and player's market offers.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];
$db       = Database::getInstance()->getConnection();

// Aktualna cena i trend / Current price and trend
$priceStmt = $db->query(
    "SELECT current_price, base_price, volatility, supply_index, demand_index,
            last_market_tick_at
       FROM market_state ORDER BY id DESC LIMIT 1"
);
$market = $priceStmt->fetch() ?: [];

// Aktywny trend / Active trend
$trendStmt = $db->query(
    "SELECT trend_name, category, price_modifier, activated_at
       FROM market_trends WHERE active = 1 ORDER BY activated_at DESC LIMIT 1"
);
$trend = $trendStmt->fetch() ?: null;

// Oferty gracza / Player's market offers
$offersStmt = $db->prepare("
    SELECT id, volume_bbl, price_per_bbl, status, created_at, expires_at
      FROM market_offers
     WHERE player_id = ? AND status = 'active'
     ORDER BY created_at DESC
     LIMIT 20
");
$offersStmt->execute([$playerId]);
$offers = [];
foreach ($offersStmt->fetchAll() as $o) {
    $offers[] = [
        'id'            => (int)$o['id'],
        'volume_bbl'    => round((float)$o['volume_bbl'], 2),
        'price_per_bbl' => round((float)$o['price_per_bbl'], 2),
        'status'        => $o['status'],
        'created_at'    => $o['created_at'],
        'expires_at'    => $o['expires_at'],
    ];
}

apiJson([
    'price' => [
        'current'         => round((float)($market['current_price'] ?? 0), 2),
        'base'            => round((float)($market['base_price']    ?? 0), 2),
        'volatility'      => round((float)($market['volatility']    ?? 0), 4),
        'supply_index'    => round((float)($market['supply_index']  ?? 1), 4),
        'demand_index'    => round((float)($market['demand_index']  ?? 1), 4),
        'last_updated_at' => $market['last_market_tick_at'] ?? null,
    ],
    'trend'  => $trend ? [
        'name'           => $trend['trend_name'],
        'category'       => $trend['category'],
        'price_modifier' => round((float)$trend['price_modifier'], 4),
        'activated_at'   => $trend['activated_at'],
    ] : null,
    'my_offers' => $offers,
]);
