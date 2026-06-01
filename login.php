<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$phone = normalizePhone($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request token. Please reload the page.';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
        $error = 'Please enter a valid 10-digit phone number.';
    } else {
        $result = requestLoginOtp($phone);
        if ($result['ok']) {
            $_SESSION['otp_notice'] = $result['message'];
            redirect('verify-otp.php?phone=' . urlencode($phone));
        }
        $error = $result['message'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0">Login with Phone OTP</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <?php echo showAlert($error, 'danger'); ?>
                <?php endif; ?>
                <?php if ($success): ?>
                    <?php echo showAlert($success, 'info'); ?>
                <?php endif; ?>
                <form method="post" action="login.php" data-prevent-duplicate>
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo escape($phone); ?>" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required autofocus autocomplete="tel">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Send OTP</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-muted text-center small">
                Access is limited to authorized phone numbers only.
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
