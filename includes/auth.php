<?php
// auth.php - Single administrator authentication helpers
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn()
{
    if (!empty($_SESSION['user_id'])) {
        return true;
    }
    
    // If an OTP is pending, the user is not fully logged in yet.
    if (!empty($_SESSION['pending_login_user_id'])) {
        return false;
    }

    return loginFromRememberCookie();
}

function requireLogin()
{
    if (!isLoggedIn()) {
        if (!empty($_SESSION['pending_login_user_id'])) {
            redirect('verify-otp.php');
        } else {
            redirect('login.php');
        }
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

    clearPendingLogin();
    if (!sendLoginOtp((int)$user['id'], $remember)) {
        return ['ok' => false, 'message' => 'Unable to send the verification code. Please try again later.'];
    }
    return ['ok' => 'pending_otp', 'message' => 'A verification code has been sent to your verified email. Please enter it to complete login.'];
}

function clearPendingLogin()
{
    unset(
        $_SESSION['pending_login_user_id'],
        $_SESSION['pending_login_remember'],
        $_SESSION['pending_login_otp_hash'],
        $_SESSION['pending_login_otp_expires_at'],
        $_SESSION['pending_login_otp_sent_at'],
        $_SESSION['pending_login_otp_attempts']
    );
}

function generateLoginOtp()
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendLoginOtp($userId, $remember)
{
    $otp = generateLoginOtp();
    $_SESSION['pending_login_user_id'] = $userId;
    $_SESSION['pending_login_remember'] = $remember ? 1 : 0;
    $_SESSION['pending_login_otp_hash'] = hash('sha256', $otp);
    $_SESSION['pending_login_otp_expires_at'] = time() + 120;
    $_SESSION['pending_login_otp_sent_at'] = time();
    $_SESSION['pending_login_otp_attempts'] = 0;

    $email = ADMIN_LOGIN_EMAIL;
    $subject = 'Your login verification code';
    $message = "Your one-time login code is: {$otp}\r\n\r\nThis code expires in 10 minutes.";
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Trust Donation System';

    if (defined('SMTP_HOST') && SMTP_HOST !== '') {
        $sent = smtpSendMail($email, $subject, $message, $fromEmail, $fromName);
    } else {
        $headers = 'From: ' . $fromEmail . "\r\n" .
                   'Content-Type: text/plain; charset=utf-8';
        $sent = @mail($email, $subject, $message, $headers);
    }

    if (!$sent) {
        error_log('Login OTP mail failed to send to ' . $email . '. SMTP=' . ini_get('SMTP') . ' port=' . ini_get('smtp_port'));
        return false;
    }

    return true;
}

function smtpSendMail($to, $subject, $message, $fromEmail, $fromName = '')
{
    $server = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USER;
    $password = SMTP_PASS;
    $secure = strtolower(SMTP_SECURE ?? '');
    $timeout = 30;

    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $server . ':' . $port;
    $socket = stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log('SMTP connect failed: ' . $errno . ' ' . $errstr);
        return false;
    }

    $response = smtpGetResponse($socket);
    if (!str_starts_with($response, '220')) {
        fclose($socket);
        error_log('SMTP error after connect: ' . $response);
        return false;
    }

    smtpSendCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    $response = smtpGetResponse($socket);
    if (!str_contains($response, '250')) {
        smtpSendCommand($socket, 'HELO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $response = smtpGetResponse($socket);
        if (!str_contains($response, '250')) {
            fclose($socket);
            error_log('SMTP HELO/EHLO failed: ' . $response);
            return false;
        }
    }

    if ($secure === 'tls') {
        smtpSendCommand($socket, 'STARTTLS');
        $response = smtpGetResponse($socket);
        if (!str_starts_with($response, '220')) {
            fclose($socket);
            error_log('SMTP STARTTLS failed: ' . $response);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            error_log('SMTP TLS enable failed');
            return false;
        }
        smtpSendCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        smtpGetResponse($socket);
    }

    smtpSendCommand($socket, 'AUTH LOGIN');
    smtpGetResponse($socket);
    smtpSendCommand($socket, base64_encode($username));
    smtpGetResponse($socket);
    smtpSendCommand($socket, base64_encode($password));
    $response = smtpGetResponse($socket);
    if (!str_starts_with($response, '235')) {
        fclose($socket);
        error_log('SMTP auth failed: ' . $response);
        return false;
    }

    smtpSendCommand($socket, 'MAIL FROM:<' . $fromEmail . '>');
    smtpGetResponse($socket);
    smtpSendCommand($socket, 'RCPT TO:<' . $to . '>');
    smtpGetResponse($socket);
    smtpSendCommand($socket, 'DATA');
    smtpGetResponse($socket);

    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . ($fromName ? ($fromName . ' <' . $fromEmail . '>') : $fromEmail);
    $headers[] = 'To: ' . $to;
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $data = implode("\r\n", $headers) . "\r\n\r\n" . wordwrap($message, 70, "\r\n") . "\r\n.";
    smtpSendCommand($socket, $data);

    $response = smtpGetResponse($socket);
    if (!str_starts_with($response, '250')) {
        fclose($socket);
        error_log('SMTP DATA failed: ' . $response);
        return false;
    }

    smtpSendCommand($socket, 'QUIT');
    fclose($socket);
    return true;
}

function smtpSendCommand($socket, $command)
{
    fwrite($socket, $command . "\r\n");
}

function smtpGetResponse($socket)
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= trim($line) . '\n';
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return trim($response);
}

function verifyLoginOtp($otp)
{
    global $pdo;
    if (empty($_SESSION['pending_login_user_id']) || empty($_SESSION['pending_login_otp_hash']) || empty($_SESSION['pending_login_otp_expires_at'])) {
        clearPendingLogin();
        return ['ok' => false, 'message' => 'Your login session expired. Please log in again.'];
    }

    if (time() > (int) $_SESSION['pending_login_otp_expires_at']) {
        clearPendingLogin();
        return ['ok' => false, 'message' => 'The verification code expired. Please log in again to request a new code.'];
    }

    $_SESSION['pending_login_otp_attempts'] = ($_SESSION['pending_login_otp_attempts'] ?? 0) + 1;
    if ($_SESSION['pending_login_otp_attempts'] > 5) {
        clearPendingLogin();
        return ['ok' => false, 'message' => 'Too many attempts. Please log in again.'];
    }

    if (!hash_equals($_SESSION['pending_login_otp_hash'], hash('sha256', trim((string) $otp)))) {
        return ['ok' => false, 'message' => 'Invalid verification code.'];
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_SESSION['pending_login_user_id']]);
    $user = $stmt->fetch();
    if (!$user || ($user['status'] ?? 'active') !== 'active' || ($user['email'] ?? '') !== ADMIN_LOGIN_EMAIL || empty($user['email_verified_at'])) {
        clearPendingLogin();
        return ['ok' => false, 'message' => 'Unable to complete login.'];
    }

    $remember = !empty($_SESSION['pending_login_remember']);
    clearPendingLogin();

    return finalizeLogin($user, $remember);
}

function finalizeLogin($user, $remember = false)
{
    global $pdo;
    session_regenerate_id(true);
    setAuthSession($user);

    $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);

    if ($remember) {
        setRememberToken((int) $user['id']);
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
