<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('nitya-seva-members');
}

$loginError = '';
$successMessage = '';
if (!empty($_SESSION['login_notice'])) {
    $successMessage = $_SESSION['login_notice'];
    unset($_SESSION['login_notice']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $loginError = 'Invalid request token. Please try again.';
    } else {
        $result = loginUser($_POST['username'] ?? '', $_POST['password'] ?? '', !empty($_POST['remember']));
            error_log('LOGIN ATTEMPT: ' . json_encode(['username' => $_POST['username'] ?? '', 'result' => $result]));
                if ($result['ok'] === true) {
                    // Use app route so basePath/rewrite are handled correctly on live server
                    redirect('nitya-seva-members');
                }
        if ($result['ok'] === 'pending_otp') {
            $_SESSION['login_notice'] = $result['message'];
            redirect('verify-otp');
        }
        $loginError = $result['message'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">
        <div class="card auth-card">
            <div class="card-header bg-primary text-white text-center">
                <h1 class="h4 mb-0">Admin Login</h1>
            </div>
            <div class="card-body">
                <?php if ($successMessage): ?>
                    <?php echo showAlert($successMessage, 'success'); ?>
                <?php endif; ?>
                <?php if ($loginError): ?>
                    <?php echo showAlert($loginError, 'danger'); ?>
                <?php endif; ?>

                <form method="post" action="<?php echo url('login'); ?>" autocomplete="on">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">

                    <div class="mb-4">
                        <label for="username" class="form-label">User ID</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo escape($_POST['username'] ?? ''); ?>" required autofocus autocomplete="username">
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" <?php echo !empty($_POST['remember']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="remember">
                            Remember me on this device
                        </label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-muted text-center small bg-white">
                Verified admin email: <?php echo escape(ADMIN_LOGIN_EMAIL); ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
