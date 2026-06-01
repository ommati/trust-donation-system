<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $_SESSION['login_error'] = 'Invalid request token. Please try again.';
    redirect('login.php');
}

$result = loginWithFirebaseToken($_POST['firebase_id_token'] ?? '');
if ($result['ok']) {
    redirect('dashboard.php');
}

$_SESSION['login_error'] = $result['message'];
redirect('login.php');
