<?php
// auth.php - Phone OTP authentication helpers
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn()
{
    return !empty($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function normalizePhone($phone)
{
    $phone = preg_replace('/\D/', '', (string)$phone);
    if (strlen($phone) > 10 && substr($phone, 0, 2) === '91') {
        $phone = substr($phone, -10);
    }
    return $phone;
}

function allowedOtpPhones()
{
    $phones = unserialize(OTP_ALLOWED_PHONES, ['allowed_classes' => false]);
    return array_map('normalizePhone', is_array($phones) ? $phones : []);
}

function isAllowedOtpPhone($phone)
{
    return in_array(normalizePhone($phone), allowedOtpPhones(), true);
}

function authColumnExists($table, $column)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensureAuthSchema()
{
    static $done = false;
    if ($done) {
        return;
    }

    global $pdo;
    $columns = [
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER username",
        'is_phone_verified' => "ALTER TABLE users ADD COLUMN is_phone_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER fullname",
        'status' => "ALTER TABLE users ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active' AFTER is_phone_verified",
        'login_otp_hash' => "ALTER TABLE users ADD COLUMN login_otp_hash VARCHAR(255) NULL AFTER status",
        'login_otp_expires_at' => "ALTER TABLE users ADD COLUMN login_otp_expires_at DATETIME NULL AFTER login_otp_hash",
        'login_otp_attempts' => "ALTER TABLE users ADD COLUMN login_otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER login_otp_expires_at",
        'login_otp_last_sent_at' => "ALTER TABLE users ADD COLUMN login_otp_last_sent_at DATETIME NULL AFTER login_otp_attempts",
        'last_login_at' => "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER login_otp_last_sent_at",
    ];

    foreach ($columns as $column => $sql) {
        if (!authColumnExists('users', $column)) {
            $pdo->exec($sql);
        }
    }

    try {
        $pdo->exec('ALTER TABLE users MODIFY username VARCHAR(190) NOT NULL');
    } catch (PDOException $exception) {
        // Some shared hosts restrict ALTER MODIFY; new installs use database.sql.
    }

    try {
        $pdo->exec('CREATE UNIQUE INDEX idx_users_phone ON users (phone)');
    } catch (PDOException $exception) {
        // Index already exists or legacy data prevents it; login still checks allowed phones.
    }

    $done = true;
}

function authFindUserByPhone($phone)
{
    global $pdo;
    ensureAuthSchema();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE phone = :phone OR username = :phone LIMIT 1');
    $stmt->execute(['phone' => normalizePhone($phone)]);
    $user = $stmt->fetch();
    if ($user && empty($user['phone'])) {
        $update = $pdo->prepare('UPDATE users SET phone = :phone, is_phone_verified = 1 WHERE id = :id');
        $update->execute(['phone' => normalizePhone($phone), 'id' => $user['id']]);
        $user['phone'] = normalizePhone($phone);
        $user['is_phone_verified'] = 1;
    }
    return $user;
}

function authCreateAllowedPhoneUser($phone)
{
    global $pdo;
    ensureAuthSchema();

    $phone = normalizePhone($phone);
    $existing = authFindUserByPhone($phone);
    if ($existing) {
        return $existing;
    }

    $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, phone, password, fullname, is_phone_verified, status) VALUES (:username, :phone, :password, :fullname, 1, 'active')");
    $stmt->execute([
        'username' => $phone,
        'phone' => $phone,
        'password' => $randomPassword,
        'fullname' => 'Authorized User',
    ]);

    return authFindUserByPhone($phone);
}

function authCanSendOtp($user)
{
    if (empty($user['login_otp_last_sent_at'])) {
        return true;
    }

    return strtotime($user['login_otp_last_sent_at']) <= time() - OTP_RESEND_SECONDS;
}

function authSendSms($phone, $message)
{
    if (SMS_OTP_DEBUG) {
        return true;
    }

    if (!defined('SMS_GATEWAY_URL') || SMS_GATEWAY_URL === '') {
        return false;
    }

    $url = str_replace(
        ['{phone}', '{message}'],
        [rawurlencode($phone), rawurlencode($message)],
        SMS_GATEWAY_URL
    );

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    return $response !== false;
}

function authIssueOtp($user)
{
    global $pdo;
    ensureAuthSchema();

    if (!authCanSendOtp($user)) {
        return ['ok' => false, 'message' => 'Please wait before requesting another OTP.'];
    }

    $otp = (string)random_int(100000, 999999);
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);

    $stmt = $pdo->prepare('UPDATE users SET login_otp_hash = :hash, login_otp_expires_at = :expires_at, login_otp_attempts = 0, login_otp_last_sent_at = NOW() WHERE id = :id');
    $stmt->execute([
        'hash' => $hash,
        'expires_at' => $expiresAt,
        'id' => $user['id'],
    ]);

    $message = 'Your ' . TRUST_NAME . ' login OTP is ' . $otp . '. It expires in ' . OTP_EXPIRY_MINUTES . ' minutes.';
    if (!authSendSms($user['phone'], $message)) {
        return ['ok' => false, 'message' => 'SMS gateway is not configured. Please contact the administrator.'];
    }

    $_SESSION['otp_phone'] = $user['phone'];
    if (SMS_OTP_DEBUG) {
        $_SESSION['debug_otp'] = $otp;
    }

    return ['ok' => true, 'message' => SMS_OTP_DEBUG ? 'Local test OTP: ' . $otp : 'OTP sent to your phone number.'];
}

function requestLoginOtp($phone)
{
    ensureAuthSchema();
    $phone = normalizePhone($phone);

    if (!preg_match('/^[6-9]\d{9}$/', $phone) || !isAllowedOtpPhone($phone)) {
        return ['ok' => false, 'message' => 'This phone number is not authorized for login.'];
    }

    $user = authCreateAllowedPhoneUser($phone);
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        return ['ok' => false, 'message' => 'This phone number is not authorized for login.'];
    }

    return authIssueOtp($user);
}

function verifyOtpAndLogin($phone, $otp)
{
    global $pdo;
    ensureAuthSchema();

    $phone = normalizePhone($phone);
    $otp = preg_replace('/\D/', '', (string)$otp);
    $user = authFindUserByPhone($phone);

    if (!$user || empty($user['login_otp_hash']) || !isAllowedOtpPhone($phone)) {
        return ['ok' => false, 'message' => 'Invalid or expired OTP.'];
    }

    if ((int)$user['login_otp_attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['ok' => false, 'message' => 'Too many invalid attempts. Request a new OTP.'];
    }

    if (empty($user['login_otp_expires_at']) || strtotime($user['login_otp_expires_at']) < time()) {
        return ['ok' => false, 'message' => 'OTP expired. Request a new OTP.'];
    }

    if (!password_verify($otp, $user['login_otp_hash'])) {
        $stmt = $pdo->prepare('UPDATE users SET login_otp_attempts = login_otp_attempts + 1 WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        return ['ok' => false, 'message' => 'Invalid OTP.'];
    }

    $stmt = $pdo->prepare('UPDATE users SET is_phone_verified = 1, login_otp_hash = NULL, login_otp_expires_at = NULL, login_otp_attempts = 0, last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['phone'] = $user['phone'];
    unset($_SESSION['otp_phone'], $_SESSION['debug_otp']);

    return ['ok' => true, 'message' => 'Login successful.'];
}

function logoutUser()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
