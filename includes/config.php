<?php
// config.php - Application configuration and global constants

// Start secure session with cookie parameters
$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

date_default_timezone_set('Asia/Kolkata');

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'trust_donation');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('SITE_TITLE', 'Trust Donation Management System');
define('TRUST_NAME', 'Trust Donation Management System');
define('TRUST_REGISTRATION', 'Trust Registration No. [Your Number]');
define('TRUST_ADDRESS', "[Trust Address Line 1], [City], [State] - [ZIP]");
define('TRUST_LOGO', ''); // Leave blank to use text logo

define('RECEIPT_PREFIX', 'SRMNS-2026-');

define('DEFAULT_PAYMENT_MODES', serialize(['Cash', 'UPI', 'Bank Transfer', 'Cheque']));

// CSRF token name
define('CSRF_TOKEN_NAME', 'csrf_token');

// Helper to load environment override file if needed
$envFile = __DIR__ . '/../.env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}
