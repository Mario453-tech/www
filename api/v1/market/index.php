<?php
declare(strict_types=1);

/**
 * GET /api/v1/market
 *
 * Zwraca aktualna cene ropy, aktywny trend/event (z odliczaniem liczonym na
 * serwerze) oraz oferty rynkowe gracza. Wszystko to wynik ticka (cron) —
 * aplikacja jedynie czyta, niczego nie liczy lokalnie.
 *
 * Returns current oil price, the active market trend/event (with a server-side
 * countdown) and the player's market offers. All of this is the tick's output;
 * the app only reads it.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];
$db       = Database::getInstance()->getConnection();

// Aktualna cena + czas ostatniego ticka rynku / Current price + last market tick
// Wiersz singleton id=1 — spojny z cron (MarketTick) i web (Market::getState).
// The id=1 singleton — consistent with the cron and the web reader.
$priceStmt = $db->query(
    "SELECT current_price, base_price, volatility, supply_index, demand_index,
            last_market_tick_at,
            DATE_ADD(last_market_tick_at, INTERVAL 5 MINUTE) AS next_tick_estimated
       FROM market_state WHERE id = 1 LIMIT 1"
);
$market = $priceStmt->fetch() ?: [];

// Aktywny trend/event + odliczanie liczone po stronie serwera (bez zegara telefonu).
// Active trend/event with a server-computed countdown (no reliance on the phone clock).
$trendStmt = $db->query(
    "SELECT trend_name, category, price_modifier, duration_hours, message_template,
            activated_at,
            GREATEST(0, TIMESTAMPDIFF(
                SECOND, NOW(),
                DATE_ADD(activated_at, INTERVAL duration_hours HOUR)
            )) AS remaining_seconds
       FROM market_trends
      WHERE active = 1
      ORDER BY activated_at DESC
      LIMIT 1"
);
$trend = $trendStmt->fetch() ?: null;

$trendOut = null;
if ($trend) {
    $pct = (int)round(((float)$trend['price_modifier'] - 1) * 100);
    // Podstaw {name}/{percent} w szablonie komunikatu (jak w UI web).
    $message = strtr((string)($trend['message_template'] ?? ''), [
        '{name}'    => (string)$trend['trend_name'],
        '{percent}' => ($pct >= 0 ? '+' : '') . $pct,
    ]);
    $trendOut = [
        'name'              => $trend['trend_name'],
        'category'          => $trend['category'],
        'price_modifier'    => round((float)$trend['price_modifier'], 4),
        'price_pct'         => $pct,
        'duration_hours'    => (int)$trend['duration_hours'],
        'remaining_seconds' => (int)$trend['remaining_seconds'],
        'message'           => $message !== '' ? $message : $trend['trend_name'],
        'activated_at'      => $trend['activated_at'],
    ];
}

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
    'tick' => [
        'last_at'           => $market['last_market_tick_at'] ?? null,
        'next_at_estimated' => $market['next_tick_estimated'] ?? null,
        'interval_seconds'  => 300,
    ],
    'trend'     => $trendOut,
    'my_offers' => $offers,
]);
