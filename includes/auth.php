<?php
// auth.php - Single administrator authentication helpers
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn()
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    return loginFromRememberCookie();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function authColumnExists($table, $column)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function authExecQuietly($sql)
{
    global $pdo;
    try {
        $pdo->exec($sql);
    } catch (PDOException $exception) {
        // Legacy/shared-host schemas may already have equivalent columns or indexes.
    }
}

function ensureAuthSchema()
{
    static $done = false;
    if ($done) {
        return;
    }

    $columns = [
        'email' => "ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER username",
        'email_verified_at' => "ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER fullname",
        'status' => "ALTER TABLE users ADD COLUMN status ENUM('active','disabled') NOT NULL DEFAULT 'active' AFTER email_verified_at",
        'remember_token_hash' => "ALTER TABLE users ADD COLUMN remember_token_hash VARCHAR(255) NULL AFTER status",
        'remember_token_expires_at' => "ALTER TABLE users ADD COLUMN remember_token_expires_at DATETIME NULL AFTER remember_token_hash",
        'last_login_at' => "ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER remember_token_expires_at",
    ];

    foreach ($columns as $column => $sql) {
        if (!authColumnExists('users', $column)) {
            authExecQuietly($sql);
        }
    }

    authExecQuietly('ALTER TABLE users MODIFY username VARCHAR(190) NOT NULL');
    authExecQuietly('CREATE UNIQUE INDEX idx_users_email ON users (email)');

    $done = true;
}

function ensureAdminUser()
{
    global $pdo;
    ensureAuthSchema();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username OR email = :email ORDER BY id ASC LIMIT 1');
    $stmt->execute([
        'username' => ADMIN_LOGIN_USER,
        'email' => ADMIN_LOGIN_EMAIL,
    ]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET username = :username, email = :email, password = :password, fullname = :fullname, email_verified_at = COALESCE(email_verified_at, NOW()), status = 'active' WHERE id = :id");
        $update->execute([
            'username' => ADMIN_LOGIN_USER,
            'email' => ADMIN_LOGIN_EMAIL,
            'password' => ADMIN_PASSWORD_HASH,
            'fullname' => 'Administrator',
            'id' => $user['id'],
        ]);
    } else {
        $insert = $pdo->prepare("INSERT INTO users (username, email, password, fullname, email_verified_at, status) VALUES (:username, :email, :password, :fullname, NOW(), 'active')");
        $insert->execute([
            'username' => ADMIN_LOGIN_USER,
            'email' => ADMIN_LOGIN_EMAIL,
            'password' => ADMIN_PASSWORD_HASH,
            'fullname' => 'Administrator',
        ]);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => ADMIN_LOGIN_USER]);
    return $stmt->fetch();
}

function loginUser($username, $password, $remember = false)
{
    global $pdo;
    $username = trim((string)$username);
    $password = (string)$password;

    if ($username === '' || $password === '') {
        return ['ok' => false, 'message' => 'Please enter your user ID and password.'];
    }

    if (!hash_equals(ADMIN_LOGIN_USER, $username)) {
        return ['ok' => false, 'message' => 'Invalid user ID or password.'];
    }

    $user = ensureAdminUser();
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        return ['ok' => false, 'message' => 'This account is disabled.'];
    }

    if (($user['email'] ?? '') !== ADMIN_LOGIN_EMAIL || empty($user['email_verified_at'])) {
        return ['ok' => false, 'message' => 'The admin email is not verified.'];
    }

    if (!password_verify($password, ADMIN_PASSWORD_HASH)) {
        return ['ok' => false, 'message' => 'Invalid user ID or password.'];
    }

    session_regenerate_id(true);
    setAuthSession($user);

    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);

    if ($remember) {
        setRememberToken((int)$user['id']);
    } else {
        clearRememberCookie();
    }

    return ['ok' => true, 'message' => 'Login successful.'];
}

function setAuthSession($user)
{
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['email'] = $user['email'] ?? ADMIN_LOGIN_EMAIL;
}

function rememberCookieOptions($expires)
{
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function setRememberToken($userId)
{
    global $pdo;

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = time() + (REMEMBER_DAYS * 86400);
    $expiresAt = date('Y-m-d H:i:s', $expires);

    $stmt = $pdo->prepare('UPDATE users SET remember_token_hash = :token_hash, remember_token_expires_at = :expires_at WHERE id = :id');
    $stmt->execute([
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'id' => $userId,
    ]);

    setcookie(REMEMBER_COOKIE_NAME, $userId . ':' . $token, rememberCookieOptions($expires));
}

function loginFromRememberCookie()
{
    global $pdo;
    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return false;
    }

    ensureAuthSchema();
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || $parts[1] === '') {
        clearRememberCookie();
        return false;
    }

    [$userId, $token] = $parts;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND username = :username AND email = :email AND status = 'active' LIMIT 1");
    $stmt->execute([
        'id' => (int)$userId,
        'username' => ADMIN_LOGIN_USER,
        'email' => ADMIN_LOGIN_EMAIL,
    ]);
    $user = $stmt->fetch();

    if (!$user || empty($user['remember_token_hash']) || empty($user['remember_token_expires_at'])) {
        clearRememberCookie();
        return false;
    }

    if (strtotime($user['remember_token_expires_at']) < time()) {
        clearRememberToken((int)$user['id']);
        return false;
    }

    if (!hash_equals($user['remember_token_hash'], hash('sha256', $token))) {
        clearRememberCookie();
        return false;
    }

    session_regenerate_id(true);
    setAuthSession($user);
    setRememberToken((int)$user['id']);
    return true;
}

function clearRememberCookie()
{
    setcookie(REMEMBER_COOKIE_NAME, '', rememberCookieOptions(time() - 3600));
}

function clearRememberToken($userId)
{
    global $pdo;
    $stmt = $pdo->prepare('UPDATE users SET remember_token_hash = NULL, remember_token_expires_at = NULL WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    clearRememberCookie();
}

function logoutUser()
{
    if (!empty($_SESSION['user_id'])) {
        clearRememberToken((int)$_SESSION['user_id']);
    } else {
        clearRememberCookie();
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
