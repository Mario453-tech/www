<?php
declare(strict_types=1);

/**
 * admin/contracts.php - panel admina kontraktow dlugoterminowych.
 * admin/contracts.php - admin panel for long-term contracts.
 *
 * Opcji kontraktow nie usuwamy fizycznie (is_active = 0). Warunki mozna usuwac.
 * Contract options are never physically deleted (is_active = 0). Terms can be deleted.
 */

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/contracts.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once __DIR__ . '/../src/ContractService.php';
require_once __DIR__ . '/../src/ContractReputationService.php';
require_once __DIR__ . '/../src/B2BContractService.php';

$db                = Database::getInstance()->getConnection();
$service           = new ContractService($db); // ensure() creates schema and seed.
$reputationService = new ContractReputationService($db);
$b2bService        = new B2BContractService($db);
$flash             = $_SESSION['admin_contracts_flash'] ?? [];
unset($_SESSION['admin_contracts_flash']);
$msg               = (string)($flash['msg'] ?? '');
$err               = (string)($flash['err'] ?? '');

$tabs = ['options', 'terms', 'active', 'deliveries', 'logs', 'reputation', 'b2b', 'help'];
$activeTab = (string)($_GET['tab'] ?? 'options');
if (!in_array($activeTab, $tabs, true)) {
    $activeTab = 'options';
}

$priceModes = ['fixed', 'market_multiplier', 'market_plus_bonus'];
$severities = ['low', 'medium', 'high', 'critical'];
$termTypes  = ['number', 'percent', 'minutes', 'text', 'bool'];

// == OBSLUGA FORMULARZY / FORM HANDLING ==

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'toggle_module') {
        $enabled = !empty($_POST['enabled']);
        $service->setModuleEnabled($enabled);
        AdminLog::log('contracts_module_toggle', 'Modul kontraktow: ' . ($enabled ? 'ON' : 'OFF'));
        $msg = $enabled ? t('admin.contracts.msg_module_on') : t('admin.contracts.msg_module_off');
    } elseif ($action === 'save_option') {
        $optionId  = (int)($_POST['option_id'] ?? 0);
        $code      = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['code'] ?? '')));
        $name      = trim((string)($_POST['name'] ?? ''));
        $priceMode = in_array((string)($_POST['price_mode'] ?? ''), $priceModes, true) ? (string)$_POST['price_mode'] : 'market_plus_bonus';
        $severity  = in_array((string)($_POST['severity'] ?? ''), $severities, true) ? (string)$_POST['severity'] : 'low';
        $fixedRaw  = trim((string)($_POST['fixed_price'] ?? ''));
        $fixedPrice = $fixedRaw === '' ? null : max(0.0, (float)$fixedRaw);

        $values = [
            $name,
            trim((string)($_POST['description'] ?? '')),
            trim((string)($_POST['buyer_name'] ?? '')) ?: 'Odbiorca kontraktowy',
            ContractService::TARGET_STORAGE,
            ContractService::CONTEXT_STORAGE_DELIVERY,
            isset($_POST['is_active']) ? 1 : 0,
            $priceMode,
            $fixedPrice,
            max(0.0, (float)($_POST['price_multiplier'] ?? 1.0)),
            $severity,
            max(0, min(100, (int)($_POST['min_credibility'] ?? 0))),
            max(0, min(10, (int)($_POST['requires_legal_level'] ?? 0))),
            max(0, (int)($_POST['max_active_per_player'] ?? 3)),
            (int)($_POST['sort_order'] ?? 0),
        ];

        if ($code === '' || $name === '') {
            $err = t('admin.contracts.err_option_required');
        } else {
            try {
                if ($optionId > 0) {
                    $db->prepare(
                        "UPDATE contract_options
                            SET name = ?, description = ?, buyer_name = ?, target_type = ?, context = ?,
                                is_active = ?, price_mode = ?, fixed_price = ?, price_multiplier = ?, severity = ?,
                                min_credibility = ?, requires_legal_level = ?, max_active_per_player = ?, sort_order = ?,
                                code = ?
                          WHERE id = ?"
                    )->execute([...$values, $code, $optionId]);
                    AdminLog::log('contracts_option_save', "Edycja opcji kontraktu #{$optionId}: {$code}");
                } else {
                    $db->prepare(
                        "INSERT INTO contract_options
                            (name, description, buyer_name, target_type, context, is_active, price_mode,
                             fixed_price, price_multiplier, severity, min_credibility, requires_legal_level,
                             max_active_per_player, sort_order, code)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([...$values, $code]);
                    AdminLog::log('contracts_option_save', "Nowa opcja kontraktu: {$code}");
                }
                $msg = t('admin.contracts.msg_option_saved');
            } catch (Throwable $e) {
                GameLog::error('admin/contracts.php', 'save_option FAILED', $e);
                $err = t('admin.contracts.err_option_save');
            }
        }
        $activeTab = 'options';
    } elseif ($action === 'toggle_option') {
        $optionId = (int)($_POST['option_id'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        if ($optionId > 0) {
            try {
                $db->prepare("UPDATE contract_options SET is_active = ? WHERE id = ?")->execute([$isActive, $optionId]);
                AdminLog::log('contracts_option_toggle', "Opcja kontraktu #{$optionId}: " . ($isActive ? 'ON' : 'OFF'));
                $msg = $isActive ? t('admin.contracts.msg_option_enabled') : t('admin.contracts.msg_option_disabled');
            } catch (Throwable $e) {
                GameLog::error('admin/contracts.php', 'toggle_option FAILED', $e);
                $err = t('admin.contracts.err_option_save');
            }
        }
        $activeTab = 'options';
    } elseif ($action === 'save_term') {
        $termId    = (int)($_POST['term_id'] ?? 0);
        $optionId  = (int)($_POST['option_id'] ?? 0);
        $termKey   = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['term_key'] ?? '')));
        $termType  = in_array((string)($_POST['term_type'] ?? ''), $termTypes, true) ? (string)$_POST['term_type'] : 'number';
        $valueRaw  = trim((string)($_POST['term_value'] ?? ''));
        $termValue = $valueRaw === '' ? null : (float)$valueRaw;
        $textRaw   = trim((string)($_POST['term_text'] ?? ''));
        $termText  = $textRaw === '' ? null : $textRaw;

        if ($optionId <= 0 || $termKey === '') {
            $err = t('admin.contracts.err_term_required');
        } else {
            try {
                if ($termId > 0) {
                    $db->prepare(
                        "UPDATE contract_terms
                            SET contract_option_id = ?, term_key = ?, term_type = ?, term_value = ?, term_text = ?
                          WHERE id = ?"
                    )->execute([$optionId, $termKey, $termType, $termValue, $termText, $termId]);
                } else {
                    $isSqlite = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
                    $upsert = $isSqlite
                        ? "INSERT INTO contract_terms (contract_option_id, term_key, term_type, term_value, term_text)
                             VALUES (?, ?, ?, ?, ?)
                             ON CONFLICT(contract_option_id, term_key)
                             DO UPDATE SET term_type = excluded.term_type, term_value = excluded.term_value, term_text = excluded.term_text"
                        : "INSERT INTO contract_terms (contract_option_id, term_key, term_type, term_value, term_text)
                             VALUES (?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE term_type = VALUES(term_type), term_value = VALUES(term_value), term_text = VALUES(term_text)";
                    $db->prepare($upsert)->execute([$optionId, $termKey, $termType, $termValue, $termText]);
                }
                AdminLog::log('contracts_term_save', "Zapis warunku kontraktu: opcja {$optionId}, {$termKey}");
                $msg = t('admin.contracts.msg_term_saved');
            } catch (Throwable $e) {
                GameLog::error('admin/contracts.php', 'save_term FAILED', $e);
                $err = t('admin.contracts.err_term_save');
            }
        }
        $activeTab = 'terms';
    } elseif ($action === 'delete_term') {
        $termId = (int)($_POST['term_id'] ?? 0);
        if ($termId > 0) {
            try {
                $db->prepare("DELETE FROM contract_terms WHERE id = ?")->execute([$termId]);
                AdminLog::log('contracts_term_delete', "Usuniecie warunku kontraktu #{$termId}");
                $msg = t('admin.contracts.msg_term_deleted');
            } catch (Throwable $e) {
                GameLog::error('admin/contracts.php', 'delete_term FAILED', $e);
                $err = t('admin.contracts.err_term_delete');
            }
        }
        $activeTab = 'terms';
    } elseif ($action === 'adjust_reputation') {
        $playerId = (int)($_POST['player_id'] ?? 0);
        $delta = (int)($_POST['delta'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));

        if ($playerId <= 0 || $delta === 0 || $delta < -100 || $delta > 100) {
            $err = t('admin.contracts.err_reputation_adjust');
        } else {
            try {
                $result = $reputationService->adminAdjustScore($playerId, $delta, $note);
                AdminLog::log(
                    'contracts_reputation_adjust',
                    "Contract reputation adjustment for player #{$playerId}: {$delta}, score {$result['score']}",
                    $playerId,
                    'player',
                    $playerId
                );
                $msg = t('admin.contracts.msg_reputation_adjusted', [
                    'score' => (string)$result['score'],
                ]);
            } catch (Throwable $e) {
                GameLog::error('admin/contracts.php', 'adjust_reputation FAILED', $e, [
                    'player_id' => $playerId,
                    'delta' => $delta,
                ]);
                $err = t('admin.contracts.err_reputation_adjust');
            }
        }
        $activeTab = 'reputation';
    } elseif ($action === 'b2b_save_config') {
        $b2bService->saveConfig([
            'module_enabled' => !empty($_POST['module_enabled']) ? 1 : 0,
            'min_price_market_pct' => max(1, (float)($_POST['min_price_market_pct'] ?? 70)),
            'max_price_market_pct' => max(1, (float)($_POST['max_price_market_pct'] ?? 130)),
            'min_bbl_per_offer' => max(1, (float)($_POST['min_bbl_per_offer'] ?? 100)),
            'max_bbl_per_offer' => max(1, (float)($_POST['max_bbl_per_offer'] ?? 50000)),
            'max_open_offers_per_player' => max(1, (int)($_POST['max_open_offers_per_player'] ?? 5)),
            'buyer_cancel_penalty_pct' => max(0, min(100, (float)($_POST['buyer_cancel_penalty_pct'] ?? 10))),
            'admin_review_threshold_value' => max(0, (float)($_POST['admin_review_threshold_value'] ?? 5000000)),
        ]);
        AdminLog::log('contracts_b2b_config_save', 'B2B contracts config saved');
        $msg = t('admin.contracts.b2b_msg_config_saved');
        $activeTab = 'b2b';
    } elseif ($action === 'b2b_flag_offer') {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'admin_review'));
        $res = $b2bService->adminFlagOffer((int)(AdminAuth::getAdminId() ?? 0), $offerId, $reason);
        $msg = !empty($res['success']) ? t('admin.contracts.b2b_msg_flagged') : '';
        $err = empty($res['success']) ? t('admin.contracts.b2b_err_action') : '';
        AdminLog::log('contracts_b2b_flag_offer', "B2B offer #{$offerId} flagged");
        $activeTab = 'b2b';
    } elseif ($action === 'b2b_unflag_offer') {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $res = $b2bService->adminUnflagOffer((int)(AdminAuth::getAdminId() ?? 0), $offerId);
        $msg = !empty($res['success']) ? t('admin.contracts.b2b_msg_unflagged') : '';
        $err = empty($res['success']) ? t('admin.contracts.b2b_err_action') : '';
        AdminLog::log('contracts_b2b_unflag_offer', "B2B offer #{$offerId} unflagged");
        $activeTab = 'b2b';
    } elseif ($action === 'b2b_cancel_offer') {
        $offerId = (int)($_POST['offer_id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'admin_cancelled'));
        $res = $b2bService->adminCancelOffer((int)(AdminAuth::getAdminId() ?? 0), $offerId, $reason);
        $msg = !empty($res['success']) ? t('admin.contracts.b2b_msg_cancelled') : '';
        $err = empty($res['success']) ? t('admin.contracts.b2b_err_action') : '';
        AdminLog::log('contracts_b2b_cancel_offer', "B2B offer #{$offerId} cancelled");
        $activeTab = 'b2b';
    }

    $_SESSION['admin_contracts_flash'] = ['msg' => $msg, 'err' => $err];
    header('Location: /admin/contracts.php?tab=' . urlencode($activeTab));
    exit;
}

// == DANE DLA WIDOKU / VIEW DATA ==

$moduleEnabled   = $service->isModuleEnabled();
$options         = [];
$termsByOption   = [];
$activeContracts = [];
$deliveries      = [];
$logs            = [];
$reputationRows  = [];
$reputationLogs  = [];
$b2bConfig        = [];
$b2bOffers        = [];
$b2bLogs          = [];
$b2bStats         = [];
$reputationSearch = trim((string)($_GET['q'] ?? ''));
$reputationPlayerId = max(0, (int)($_GET['player_id'] ?? 0));

try {
    $options = $db->query("SELECT * FROM contract_options ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($db->query("SELECT * FROM contract_terms ORDER BY contract_option_id, term_key")->fetchAll(PDO::FETCH_ASSOC) as $termRow) {
        $termsByOption[(int)$termRow['contract_option_id']][] = $termRow;
    }

    if ($activeTab === 'active') {
        $activeContracts = $db->query(
            "SELECT pc.*, p.username, p.company_name
               FROM player_contracts pc
               LEFT JOIN players p ON p.id = pc.player_id
              WHERE pc.status = 'active'
              ORDER BY pc.next_delivery_at ASC
              LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($activeTab === 'deliveries') {
        $deliveries = $db->query(
            "SELECT cd.*, p.username, p.company_name
               FROM contract_deliveries cd
               LEFT JOIN players p ON p.id = cd.player_id
              ORDER BY cd.id DESC
              LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($activeTab === 'logs') {
        $logs = $db->query(
            "SELECT cl.*, p.username, p.company_name
               FROM contract_logs cl
               LEFT JOIN players p ON p.id = cl.player_id
              ORDER BY cl.id DESC
              LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($activeTab === 'reputation') {
        $reputationRows = $reputationService->listScores($reputationSearch, 100);
        $reputationLogs = $reputationService->recentLogs($reputationPlayerId > 0 ? $reputationPlayerId : null, 100);
    } elseif ($activeTab === 'b2b') {
        $b2bConfig = $b2bService->getConfig();
        $b2bOffers = $db->query(
            "SELECT o.*,
                    COALESCE(pb.company_name, pb.username, CONCAT('#', o.buyer_player_id)) AS buyer_name,
                    COALESCE(ps.company_name, ps.username, '') AS seller_name
             FROM b2b_contract_offers o
             LEFT JOIN players pb ON pb.id = o.buyer_player_id
             LEFT JOIN players ps ON ps.id = o.seller_player_id
             ORDER BY o.id DESC
             LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
        $b2bLogs = $db->query(
            "SELECT l.*, COALESCE(p.company_name, p.username, '') AS player_name
             FROM b2b_contract_logs l
             LEFT JOIN players p ON p.id = l.player_id
             ORDER BY l.id DESC
             LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
        $b2bStats = [
            'open' => (int)$db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'open'")->fetchColumn(),
            'flagged' => (int)$db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE is_flagged = 1")->fetchColumn(),
            'completed' => (int)$db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'completed'")->fetchColumn(),
        ];
    }
} catch (Throwable $e) {
    GameLog::error('admin/contracts.php', 'view data load FAILED', $e);
}

$editOptionId = (int)($_GET['edit'] ?? 0);
$editOption = null;
foreach ($options as $optRow) {
    if ((int)$optRow['id'] === $editOptionId) {
        $editOption = $optRow;
        break;
    }
}

$editTermId = (int)($_GET['term_edit'] ?? 0);
$editTerm = null;
if ($editTermId > 0) {
    foreach ($termsByOption as $termRows) {
        foreach ($termRows as $termRow) {
            if ((int)$termRow['id'] === $editTermId) {
                $editTerm = $termRow;
                break 2;
            }
        }
    }
}

$viewData = compact(
    'moduleEnabled', 'options', 'termsByOption', 'activeContracts', 'deliveries', 'logs',
    'reputationRows', 'reputationLogs', 'reputationSearch', 'reputationPlayerId',
    'b2bConfig', 'b2bOffers', 'b2bLogs', 'b2bStats',
    'activeTab', 'tabs', 'editOption', 'editTerm', 'priceModes', 'severities', 'termTypes', 'msg', 'err'
);

$pageTitle = t('admin.contracts.title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/contracts/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/contracts.php', t('common.unhandled_exception'), $e);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/contracts.php', $_codexGuardStart);
    }
}
