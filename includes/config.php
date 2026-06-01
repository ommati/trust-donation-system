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
// IMPORTANT: Replace these via a local `.env.php` file. Do NOT commit real credentials.
$envFile = __DIR__ . '/../.env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

if (!defined('DB_HOST')) {
    define('DB_HOST', 'DB_HOST_PLACEHOLDER');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'DB_NAME_PLACEHOLDER');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'DB_USER_PLACEHOLDER');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'DB_PASS_PLACEHOLDER');
}

// Application settings
if (!defined('SITE_TITLE')) {
    define('SITE_TITLE', 'Trust Donation Management System');
}
if (!defined('TRUST_NAME')) {
    define('TRUST_NAME', 'Sri Sri Radha Madhav Naamhatta Sangha Charitable & Welfare Trust');
}
if (!defined('TRUST_REGISTRATION')) {
    define('TRUST_REGISTRATION', 'Registration No. 05010010423');
}
if (!defined('TRUST_ADDRESS')) {
    define('TRUST_ADDRESS', 'Pratappur Krishnabati, Panihati, West Bengal - 711410');
}
if (!defined('TRUST_CONTACT')) {
    define('TRUST_CONTACT', '7506316144');
}
if (!defined('AUTHORIZED_SIGNATORY')) {
    define('AUTHORIZED_SIGNATORY', 'Om Jagannath Das');
}
if (!defined('TRUST_LOGO')) {
    define('TRUST_LOGO', 'images/logo.png');
}
if (!defined('TRUST_SIGNATURE')) {
    define('TRUST_SIGNATURE', 'images/signature2.jpg');
}

if (!defined('OTP_ALLOWED_PHONES')) {
    define('OTP_ALLOWED_PHONES', serialize(['7506316144', '8583819442', '7003538078']));
}
if (!defined('SMS_OTP_DEBUG')) {
    define('SMS_OTP_DEBUG', true);
}
if (!defined('SMS_GATEWAY_URL')) {
    define('SMS_GATEWAY_URL', '');
}
if (!defined('OTP_EXPIRY_MINUTES')) {
    define('OTP_EXPIRY_MINUTES', 10);
}
if (!defined('OTP_RESEND_SECONDS')) {
    define('OTP_RESEND_SECONDS', 60);
}
if (!defined('OTP_MAX_ATTEMPTS')) {
    define('OTP_MAX_ATTEMPTS', 5);
}

if (!defined('RECEIPT_PREFIX')) {
    define('RECEIPT_PREFIX', 'SRMNS-2026-');
}

if (!defined('DEFAULT_PAYMENT_MODES')) {
    define('DEFAULT_PAYMENT_MODES', serialize(['Cash', 'UPI', 'Bank Transfer', 'Cheque']));
}

// CSRF token name
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

