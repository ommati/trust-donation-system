<?php
// auth.php - Firebase phone authentication helpers
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
        'firebase_uid' => "ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) NULL AFTER status",
        'last_login_at' => "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER firebase_uid",
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

function authCreateAllowedPhoneUser($phone, $firebaseUid = null)
{
    global $pdo;
    ensureAuthSchema();

    $phone = normalizePhone($phone);
    $existing = authFindUserByPhone($phone);
    if ($existing) {
        if ($firebaseUid && ($existing['firebase_uid'] ?? '') !== $firebaseUid) {
            $stmt = $pdo->prepare('UPDATE users SET firebase_uid = :firebase_uid, is_phone_verified = 1 WHERE id = :id');
            $stmt->execute(['firebase_uid' => $firebaseUid, 'id' => $existing['id']]);
            $existing['firebase_uid'] = $firebaseUid;
            $existing['is_phone_verified'] = 1;
        }
        return $existing;
    }

    $randomPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, phone, password, fullname, is_phone_verified, status, firebase_uid) VALUES (:username, :phone, :password, :fullname, 1, 'active', :firebase_uid)");
    $stmt->execute([
        'username' => $phone,
        'phone' => $phone,
        'password' => $randomPassword,
        'fullname' => 'Authorized User',
        'firebase_uid' => $firebaseUid,
    ]);

    return authFindUserByPhone($phone);
}

function firebaseLookupIdToken($idToken)
{
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode(FIREBASE_API_KEY);
    $payload = json_encode(['idToken' => $idToken]);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    }

    if ($response === false || $response === null) {
        return null;
    }

    $data = json_decode($response, true);
    if (empty($data['users'][0])) {
        return null;
    }

    return $data['users'][0];
}

function loginWithFirebaseToken($idToken)
{
    global $pdo;
    ensureAuthSchema();

    $idToken = trim((string)$idToken);
    if ($idToken === '') {
        return ['ok' => false, 'message' => 'Missing Firebase login token.'];
    }

    $firebaseUser = firebaseLookupIdToken($idToken);
    if (!$firebaseUser || empty($firebaseUser['phoneNumber'])) {
        return ['ok' => false, 'message' => 'Unable to verify Firebase phone login.'];
    }

    $phone = normalizePhone($firebaseUser['phoneNumber']);
    if (!isAllowedOtpPhone($phone)) {
        return ['ok' => false, 'message' => 'This phone number is not authorized for login.'];
    }

    $user = authCreateAllowedPhoneUser($phone, $firebaseUser['localId'] ?? null);
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        return ['ok' => false, 'message' => 'This account is disabled.'];
    }

    $stmt = $pdo->prepare('UPDATE users SET is_phone_verified = 1, last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['phone'] = $phone;

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
