<?php
/**
 * admin/privacy.php - panel zarzadzania modulem prywatnosci i cookies.
 */

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

// Ladowanie klas modulu prywatnosci
require_once __DIR__ . '/../src/Privacy/PrivacyFeatureInterface.php';
require_once __DIR__ . '/../src/Privacy/AbstractPrivacyFeature.php';
require_once __DIR__ . '/../src/Privacy/PrivacySettingsService.php';
require_once __DIR__ . '/../src/Privacy/PrivacyAuditLogger.php';
require_once __DIR__ . '/../src/Privacy/PrivacyPolicyService.php';
require_once __DIR__ . '/../src/Privacy/Features/Cookies/CookiesFeature.php';
require_once __DIR__ . '/../src/Privacy/Features/Consents/ConsentsFeature.php';
require_once __DIR__ . '/../src/Privacy/Features/Policy/PolicyFeature.php';
require_once __DIR__ . '/../src/Privacy/Features/Banner/BannerSettingsFeature.php';
require_once __DIR__ . '/../src/Privacy/PrivacyFeatureRegistry.php';

$db       = Database::getInstance()->getConnection();
$adminId  = AdminAuth::getAdminId();
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';
$ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';

$registry = PrivacyFeatureRegistry::build($db);
$features = $registry->getEnabled();

// Aktywna zakladka
$validTabs = array_keys($features);
$tab       = (string)($_GET['tab'] ?? ($validTabs[0] ?? 'cookies'));
if (!in_array($tab, $validTabs, true)) {
    $tab = $validTabs[0] ?? 'cookies';
}

$msg = '';
$err = '';

// Obsluga POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $tab = (string)($_POST['tab'] ?? $tab);
        if (!in_array($tab, $validTabs, true)) {
            $tab = $validTabs[0] ?? 'cookies';
        }
        $feature = $registry->get($tab);
        if ($feature) {
            $result = $feature->handlePost($_POST, $adminId, $ip, $ua);
            if ($result !== null) {
                if ($result['success']) {
                    $msg = $result['message'];
                } else {
                    $err = $result['message'];
                }
            }
        }
    }
}

// Dane dla aktywnego featurea
$activeFeature = $registry->get($tab);
$viewData      = $activeFeature ? $activeFeature->getViewData($_GET) : [];
$viewData['tab']          = $tab;
$viewData['features']     = $features;
$viewData['msg']          = $msg;
$viewData['err']          = $err;

$pageTitle = t('privacy.admin.page_title');
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../templates/views/admin/privacy/main.php';
require_once __DIR__ . '/partials/footer.php';
