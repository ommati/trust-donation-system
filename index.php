<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    // Use absolute path for live server
    redirect('/trust-donation-system/nitya-seva-members');
} else {
    redirect('/trust-donation-system/login');
}
