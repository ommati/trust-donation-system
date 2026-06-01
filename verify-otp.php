<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$phone = normalizePhone($_GET['phone'] ?? $_POST['phone'] ?? ($_SESSION['otp_phone'] ?? ''));
if (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    redirect('login.php');
}

$error = '';
$success = $_SESSION['otp_notice'] ?? '';
unset($_SESSION['otp_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request token. Please reload the page.';
    } elseif (isset($_POST['resend'])) {
        $user = authFindUserByPhone($phone);
        if ($user) {
            $result = authIssueOtp($user);
            if ($result['ok']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    } else {
        $otp = sanitizeInput($_POST['otp'] ?? '');
        $result = verifyOtpAndLogin($phone, $otp);
        if ($result['ok']) {
            redirect('dashboard.php');
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
                <h4 class="mb-0">Verify OTP</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <?php echo showAlert($error, 'danger'); ?>
                <?php endif; ?>
                <?php if ($success): ?>
                    <?php echo showAlert($success, SMS_OTP_DEBUG ? 'warning' : 'success'); ?>
                <?php endif; ?>
                <p class="text-muted small">Enter the 6-digit OTP sent to <strong><?php echo escape($phone); ?></strong>.</p>
                <form method="post" action="verify-otp.php" data-prevent-duplicate>
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <input type="hidden" name="phone" value="<?php echo escape($phone); ?>">
                    <div class="mb-3">
                        <label for="otp" class="form-label">OTP</label>
                        <input type="text" class="form-control text-center fs-4" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus autocomplete="one-time-code">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Verify & Continue</button>
                        <button type="submit" name="resend" value="1" class="btn btn-outline-secondary">Resend OTP</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
