<?php
declare(strict_types=1);

$CONFIG = require __DIR__ . '/../config/config.php';

date_default_timezone_set(
    $CONFIG['app']['timezone'] ?? 'Asia/Ho_Chi_Minh'
);

if (($CONFIG['app']['debug'] ?? false) === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

$secure = !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

$appUrl = rtrim((string)($CONFIG['app']['url'] ?? ''), '/');
if (PHP_SAPI !== 'cli' && !$secure && str_starts_with(strtolower($appUrl), 'https://')) {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($uri === '' || $uri[0] !== '/') $uri = '/';
    header('Location: ' . $appUrl . $uri, true, 308);
    exit;
}

/*
 * Low-cost security hardening for dynamic PHP responses.
 * Static assets are served directly by the web server.
 */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header('Cross-Origin-Opener-Policy: same-origin');
header(
    "Content-Security-Policy: "
    . "default-src 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'; "
    . "object-src 'none'; "
    . "script-src 'self'; "
    . "style-src 'self'; "
    . "img-src 'self' data:; "
    . "font-src 'self'; "
    . "connect-src 'self'"
);

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

if ($secure) {
    header('Strict-Transport-Security: max-age=31536000');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

if ($secure) {
    ini_set('session.cookie_secure', '1');
}

session_name(
    $CONFIG['app']['session_name'] ?? 'fixhome_session'
);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$now = time();
$idleSeconds = max(300, (int)($CONFIG['security']['session_idle_seconds'] ?? 7200));
$rotateSeconds = max(300, (int)($CONFIG['security']['session_regenerate_seconds'] ?? 1800));
$lastActivity = (int)($_SESSION['_last_activity'] ?? 0);
if ($lastActivity > 0 && ($now - $lastActivity) > $idleSeconds) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['_session_rotated_at'] = $now;
}
$_SESSION['_last_activity'] = $now;
$lastRotation = (int)($_SESSION['_session_rotated_at'] ?? 0);
if ($lastRotation === 0 || ($now - $lastRotation) > $rotateSeconds) {
    session_regenerate_id(true);
    $_SESSION['_session_rotated_at'] = $now;
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/PasswordPolicy.php';
require_once __DIR__ . '/PhonePolicy.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/OrderState.php';

require_once __DIR__ . '/Services/ServiceCatalog.php';
require_once __DIR__ . '/Services/DiagnosisService.php';
require_once __DIR__ . '/Services/UploadService.php';
require_once __DIR__ . '/Services/AccountMaintenanceService.php';
require_once __DIR__ . '/Services/MarketplaceService.php';
require_once __DIR__ . '/Services/OrderService.php';
require_once __DIR__ . '/Services/NotificationService.php';
require_once __DIR__ . '/Services/ProfileService.php';
require_once __DIR__ . '/Services/CustomerAddressService.php';
require_once __DIR__ . '/Services/FeedbackService.php';
require_once __DIR__ . '/Services/TechnicianService.php';
require_once __DIR__ . '/Services/RoleWorkView.php';
require_once __DIR__ . '/Services/OrderMediaService.php';

require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/PublicController.php';
require_once __DIR__ . '/Controllers/CustomerController.php';
require_once __DIR__ . '/Controllers/CompanyController.php';
require_once __DIR__ . '/Controllers/TechnicianController.php';
require_once __DIR__ . '/Controllers/AdminController.php';
require_once __DIR__ . '/Controllers/NotificationController.php';
require_once __DIR__ . '/Controllers/MediaController.php';
