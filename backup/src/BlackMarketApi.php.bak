<?php
declare(strict_types=1);

/**
 * Black market AJAX endpoint.
 * Endpoint AJAX dla czarnego rynku.
 *
 * GET  ?action=offers         -> list active offers
 * GET  ?action=offers         -> lista aktywnych ofert
 * GET  ?action=history        -> transaction history
 * GET  ?action=history        -> historia transakcji
 * POST action=sell&offer_id=N -> execute transaction
 * POST action=sell&offer_id=N -> realizacja transakcji
 */

require_once __DIR__ . '/init.php';

ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    Auth::requireLogin();
} catch (Throwable $e) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['error' => t('black_market.api_login_required')]);
    exit;
}

$playerId = Auth::getUserId();
$bm = new BlackMarketService();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

ob_clean();

try {
    if ($method === 'GET') {
        if ($action === 'offers') {
            $db = Database::getInstance()->getConnection();

            // Run a lightweight table check before reading offers.
            // Zrob lekki check tabeli przed odczytem ofert.
            try {
                $db->query("SELECT COUNT(*) FROM black_market_offers")->fetchColumn();

                $chk = $db->prepare("SELECT COUNT(*) FROM black_market_offers WHERE player_id = :pid");
                $chk->execute([':pid' => $playerId]);
                $chk->fetchColumn();
            } catch (Throwable $e) {
                echo json_encode([
                    'error' => t('black_market.api_table_missing', ['msg' => $e->getMessage()]),
                    'debug' => true,
                ]);
                exit;
            }

            $offers = $bm->getActiveOffers($playerId);

            $scoreStmt = $db->prepare("SELECT black_market_score FROM players WHERE id = :pid");
            $scoreStmt->execute([':pid' => $playerId]);
            $blackScore = (float)$scoreStmt->fetchColumn();

            $buyerNames = [];
            for ($i = 1; $i <= 5; $i++) {
                $buyerNames[] = t("black_market.buyer_name_$i");
            }

            foreach ($offers as &$offer) {
                $offer['buyer_name'] = $buyerNames[array_rand($buyerNames)];
                $offer['effective_risk'] = min(95, round((float)$offer['base_risk_pct'] + ($blackScore * 0.5), 1));
                $offer['total_value'] = round($offer['bbl'] * $offer['price_per_bbl'], 2);
            }
            unset($offer);

            echo json_encode([
                'offers' => $offers,
                'black_score' => round($blackScore, 2),
            ]);
        } elseif ($action === 'history') {
            $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
            $transactions = $bm->getTransactions($playerId, $limit);
            echo json_encode(['transactions' => $transactions]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => t('black_market.api_unknown_action')]);
        }
    } elseif ($method === 'POST') {
        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => t('black_market.api_csrf_error')]);
            exit;
        }

        if (!RateLimiter::check('action')) {
            http_response_code(429);
            echo json_encode(['error' => t('black_market.api_rate_limit')]);
            exit;
        }

        if ($action === 'sell') {
            $offerId = (int)($_POST['offer_id'] ?? 0);
            if ($offerId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => t('black_market.api_missing_offer_id')]);
                exit;
            }

            $result = $bm->executeTransaction($playerId, $offerId);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => t('black_market.api_unknown_action')]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => t('black_market.api_invalid_method')]);
    }
} catch (Throwable $e) {
    GameLog::error('BlackMarketApi', 'Unhandled error', $e);
    http_response_code(500);
    echo json_encode(['error' => t('black_market.error')]);
}
