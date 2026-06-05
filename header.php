<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo escape(SITE_TITLE); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<!--
    DEBUGGING NOTE:
    If Nitya Seva pages show "Not Found", please ensure the following files exist in the main 'admin-panel' directory, NOT in the 'includes' directory:
    - nitya-seva-dashboard.php (Exists: <?php echo file_exists(__DIR__ . '/../nitya-seva-dashboard.php') ? 'Yes' : 'No'; ?>)
    - nitya-seva-members.php (Exists: <?php echo file_exists(__DIR__ . '/../nitya-seva-members.php') ? 'Yes' : 'No'; ?>)
-->
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm" aria-label="Main navigation">
    <div class="container-xxl">
        <a class="navbar-brand" href="<?php echo url('dashboard'); ?>">
            <img class="navbar-brand-logo" src="images/logo 2.png" alt="Trust Logo">
            <span class="navbar-brand-text"><?php echo escape(TRUST_NAME); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" <?php echo $currentPage === 'dashboard' ? 'aria-current="page"' : ''; ?> href="<?php echo url('dashboard'); ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'add-donation' ? 'active' : ''; ?>" <?php echo $currentPage === 'add-donation' ? 'aria-current="page"' : ''; ?> href="<?php echo url('add-donation'); ?>">Add Donation</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo in_array($currentPage, ['donations', 'view-donation', 'edit-donation', 'delete-donation', 'receipt'], true) ? 'active' : ''; ?>" <?php echo in_array($currentPage, ['donations', 'view-donation', 'edit-donation', 'delete-donation', 'receipt'], true) ? 'aria-current="page"' : ''; ?> href="<?php echo url('donations'); ?>">Donations</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo str_starts_with($currentPage, 'nitya-seva') ? 'active' : ''; ?>" href="#" id="nityaSevaDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Nitya Seva
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="nityaSevaDropdown">
                            <li><a class="dropdown-item" href="<?php echo url('nitya-seva-dashboard'); ?>">Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?php echo url('nitya-seva-members'); ?>">Members</a></li>
                            <li><a class="dropdown-item" href="<?php echo url('nitya-seva-add-member'); ?>">Add Member</a></li>
                            <li><a class="dropdown-item" href="<?php echo url('nitya-seva-record-payment'); ?>">Record Payment</a></li>
                            <li><a class="dropdown-item" href="<?php echo url('nitya-seva-pending-dues'); ?>">Pending Dues</a></li>
                            <li><a class="dropdown-item" href="<?php echo url('nitya-seva-reports'); ?>">Reports</a></li>
                            <li><a class="dropdown-item" href="<?php echo url('nitya-seva-sync'); ?>">Google Sheet Sync</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav navbar-account ms-auto">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item"><span class="navbar-text">Welcome, <?php echo escape($_SESSION['fullname'] ?? $_SESSION['username']); ?></span></li>
                    <li class="nav-item"><a class="btn btn-outline-light" href="<?php echo url('logout'); ?>">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?php echo $currentPage === 'login' ? 'active' : ''; ?>" <?php echo $currentPage === 'login' ? 'aria-current="page"' : ''; ?> href="<?php echo url('login'); ?>">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container-xxl app-main">
