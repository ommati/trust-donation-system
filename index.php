<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    // Redirect using app route so basePath is respected on live servers
    redirect('nitya-seva-members');
} else {
    redirect('login');
}
