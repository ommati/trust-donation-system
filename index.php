<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('nitya-seva-members');
}
redirect('login');
