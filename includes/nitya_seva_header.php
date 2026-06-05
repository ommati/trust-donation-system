<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$nityaPages = [
    'nitya-seva-dashboard' => 'Dashboard',
    'nitya-seva-members' => 'Members',
    'nitya-seva-add-member' => 'Add Member',
    'nitya-seva-pending-dues' => 'Pending Dues',
    'nitya-seva-reports' => 'Reports',
    'nitya-seva-sync' => 'Sync',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo escape($pageTitle ?? 'Nitya Seva Portal'); ?> - <?php echo escape(TRUST_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body class="nitya-seva-portal">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm nitya-navbar" aria-label="Nitya Seva navigation">
    <div class="container-xxl">
        <a class="navbar-brand" href="<?php echo url('nitya-seva-dashboard'); ?>">
            <img class="navbar-brand-logo" src="images/logo 2.png" alt="Trust Logo">
            <span class="navbar-brand-text">Nitya Seva Portal</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nityaNavbar" aria-controls="nityaNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nityaNavbar">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nityaPages as $page => $label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === $page ? 'active' : ''; ?>" <?php echo $currentPage === $page ? 'aria-current="page"' : ''; ?> href="<?php echo url($page); ?>"><?php echo escape($label); ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <ul class="navbar-nav navbar-account ms-auto">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item"><a class="btn btn-outline-light" href="<?php echo url('dashboard'); ?>">Donation System</a></li>
                    <li class="nav-item"><a class="btn btn-outline-light" href="<?php echo url('logout'); ?>">Logout</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container-xxl app-main">
