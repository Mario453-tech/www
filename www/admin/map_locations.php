<?php
require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db    = Database::getInstance()->getConnection();
$msg   = '';
$error = '';

// == POST ==

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_location') {
            try {
                $db->prepare("
                    INSERT INTO world_locations
                        (region_id, name, country_code, latitude, longitude, oil_richness, well_type, description)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    (int)$_POST['region_id'],
                    trim($_POST['name']),
                    strtoupper(trim($_POST['country_code'])),
                    (float)$_POST['latitude'],
                    (float)$_POST['longitude'],
                    (float)$_POST['oil_richness'],
                    $_POST['well_type'] === 'offshore' ? 'offshore' : 'onshore',
                    trim($_POST['description'] ?? ''),
                ]);
                GameLog::info('admin/map_locations', 'Dodano lokalizacje', ['name' => trim($_POST['name'])]);
                $msg = t('admin.map.msg_added');
            } catch (Throwable $e) {
                GameLog::error('admin/map_locations', 'add_location FAILED', $e);
                $error = t('common.err_retry') . ' ' . $e->getMessage();
            }

        } elseif ($action === 'edit_location') {
            $locId = (int)($_POST['loc_id'] ?? 0);
            try {
                $db->prepare("
                    UPDATE world_locations
                    SET region_id=?, name=?, country_code=?, latitude=?,
                        longitude=?, oil_richness=?, well_type=?, description=?
                    WHERE id=?
                ")->execute([
                    (int)$_POST['region_id'],
                    trim($_POST['name']),
                    strtoupper(trim($_POST['country_code'])),
                    (float)$_POST['latitude'],
                    (float)$_POST['longitude'],
                    (float)$_POST['oil_richness'],
                    $_POST['well_type'] === 'offshore' ? 'offshore' : 'onshore',
                    trim($_POST['description'] ?? ''),
                    $locId,
                ]);
                GameLog::info('admin/map_locations', 'Edytowano lokalizacje', ['loc_id' => $locId]);
                $msg = t('admin.map.msg_updated');
            } catch (Throwable $e) {
                GameLog::error('admin/map_locations', 'edit_location FAILED', $e, ['loc_id' => $locId]);
                $error = t('common.err_retry') . ' ' . $e->getMessage();
            }

        } elseif ($action === 'delete_location') {
            $locId = (int)($_POST['loc_id'] ?? 0);
            try {
                $used = $db->prepare("SELECT COUNT(*) FROM wells WHERE location_id = ? AND status != 'seized'");
                $used->execute([$locId]);
                if ((int)$used->fetchColumn() > 0) {
                    $error = t('admin.map.err_has_well');
                } else {
                    $db->prepare("DELETE FROM world_locations WHERE id = ?")->execute([$locId]);
                    GameLog::info('admin/map_locations', 'Usunieto lokalizacje', ['loc_id' => $locId]);
                    $msg = t('admin.map.msg_deleted');
                }
            } catch (Throwable $e) {
                GameLog::error('admin/map_locations', 'delete_location FAILED', $e, ['loc_id' => $locId]);
                $error = t('common.err_retry') . ' ' . $e->getMessage();
            }

        } elseif ($action === 'toggle_location') {
            $locId = (int)($_POST['loc_id'] ?? 0);
            try {
                $db->prepare("UPDATE world_locations SET available = NOT available WHERE id = ?")->execute([$locId]);
                $msg = t('admin.map.msg_toggled');
            } catch (Throwable $e) {
                GameLog::error('admin/map_locations', 'toggle FAILED', $e, ['loc_id' => $locId]);
                $error = t('common.err_retry') . ' ' . $e->getMessage();
            }

        } elseif ($action === 'update_region') {
            $rId = (int)($_POST['region_id'] ?? 0);
            try {
                $db->prepare("
                    UPDATE world_regions
                    SET entry_cost=?, production_bonus=?, tax_rate=?, political_risk=?
                    WHERE id=?
                ")->execute([
                    (float)$_POST['entry_cost'],
                    round((float)$_POST['production_bonus'] / 100, 4),
                    round((float)$_POST['tax_rate']         / 100, 4),
                    min(5, max(1, (int)$_POST['political_risk'])),
                    $rId,
                ]);
                $msg = t('admin.map.msg_region_updated');
            } catch (Throwable $e) {
                GameLog::error('admin/map_locations', 'update_region FAILED', $e, ['region_id' => $rId]);
                $error = t('common.err_retry') . ' ' . $e->getMessage();
            }
        }
    }
}

// == DANE ==

$regions = [];
try {
    $regions = $db->query("SELECT * FROM world_regions ORDER BY id")->fetchAll();
} catch (Throwable $e) {
    GameLog::error('admin/map_locations', 'fetch regions FAILED', $e);
}

$locations = [];
try {
    $locations = $db->query("
        SELECT wl.*, wr.name AS region_name, wr.color_hex
        FROM world_locations wl
        JOIN world_regions wr ON wr.id = wl.region_id
        ORDER BY wl.region_id, wl.name
    ")->fetchAll();
} catch (Throwable $e) {
    GameLog::error('admin/map_locations', 'fetch locations FAILED', $e);
}

$wellCounts = [];
try {
    $wc = $db->query("SELECT location_id, COUNT(*) AS cnt FROM wells WHERE location_id IS NOT NULL GROUP BY location_id");
    foreach ($wc->fetchAll() as $row) {
        $wellCounts[(int)$row['location_id']] = (int)$row['cnt'];
    }
} catch (Throwable $e) {
    GameLog::error('admin/map_locations', 'fetch wellCounts FAILED', $e);
}

$countActive  = count(array_filter($locations, fn($l) => $l['available']));
$locsPerPage  = 10;
$totalLocs    = count($locations);
$totalLocPgs  = max(1, (int)ceil($totalLocs / $locsPerPage));
$currentLocPg = max(1, min($totalLocPgs, (int)($_GET['page'] ?? 1)));
$locOffset    = ($currentLocPg - 1) * $locsPerPage;
$pagedLocs    = array_slice($locations, $locOffset, $locsPerPage);

// == WIDOK ==

$viewData = compact(
    'msg', 'error', 'regions', 'locations', 'wellCounts',
    'countActive', 'locsPerPage', 'totalLocs', 'totalLocPgs',
    'currentLocPg', 'locOffset', 'pagedLocs'
);

$pageTitle = t('admin.map.heading');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/map_locations/main.php';
require_once __DIR__ . '/partials/footer.php';