<?php
// auth.php - Authentication helpers and session checks
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

function loginUser($username, $password)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, password, fullname FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        return true;
    }
    return false;
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
