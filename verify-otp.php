<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

if (empty($_SESSION['pending_login_user_id']) || empty($_SESSION['pending_login_otp_hash']) || empty($_SESSION['pending_login_otp_expires_at'])) {
    redirect('login.php');
}

$loginError = '';
$noticeMessage = $_SESSION['login_notice'] ?? '';
unset($_SESSION['login_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $loginError = 'Invalid request token. Please try again.';
    } else {
        $result = verifyLoginOtp($_POST['otp'] ?? '');
        if ($result['ok']) {
            redirect('dashboard.php');
        }
        $loginError = $result['message'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">Verify Login Code</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($noticeMessage): ?>
                    <?php echo showAlert($noticeMessage, 'success'); ?>
                <?php endif; ?>
                <?php if ($loginError): ?>
                    <?php echo showAlert($loginError, 'danger'); ?>
                <?php endif; ?>

                <p>Please enter the 6-digit code sent to your verified admin email address.</p>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">

                    <div class="mb-3">
                        <label for="otp" class="form-label">One-time code</label>
                        <input type="text" class="form-control" id="otp" name="otp" maxlength="6" required pattern="\d{6}" autocomplete="one-time-code" autofocus>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Verify Code</button>
                    </div>
                </form>
                <div class="mt-3 text-center small">
                    <a href="login.php">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
