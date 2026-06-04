<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard');
}

if (empty($_SESSION['pending_login_user_id']) || empty($_SESSION['pending_login_otp_hash']) || empty($_SESSION['pending_login_otp_expires_at'])) {
    redirect('login');
}

$loginError = '';
$noticeMessage = $_SESSION['login_notice'] ?? '';
unset($_SESSION['login_notice']);

$resendDelay = 59;
$resendWait = 0;
if (!empty($_SESSION['pending_login_otp_sent_at'])) {
    $elapsed = time() - (int)$_SESSION['pending_login_otp_sent_at'];
    $resendWait = max(0, $resendDelay - $elapsed);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $loginError = 'Invalid request token. Please try again.';
    } elseif (!empty($_POST['resend'])) {
        if (empty($_SESSION['pending_login_user_id'])) {
            redirect('login');
        }
        if ($resendWait > 0) {
            $loginError = 'Please wait ' . $resendWait . ' seconds before resending the code.';
        } else {
            $remember = !empty($_SESSION['pending_login_remember']);
            if (sendLoginOtp((int)$_SESSION['pending_login_user_id'], $remember)) {
                $noticeMessage = 'A new verification code has been sent to your email.';
                $resendWait = $resendDelay;
            } else {
                $loginError = 'Unable to resend the verification code. Please try again later.';
            }
        }
    } else {
        $result = verifyLoginOtp($_POST['otp'] ?? '');
        if ($result['ok']) {
            redirect('dashboard');
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
                <h1 class="h4 mb-0">Verify Login Code</h1>
            </div>
            <div class="card-body">
                <?php if ($noticeMessage): ?>
                    <?php echo showAlert($noticeMessage, 'success'); ?>
                <?php endif; ?>
                <?php if ($loginError): ?>
                    <?php echo showAlert($loginError, 'danger'); ?>
                <?php endif; ?>

                <p class="mb-4">Please enter the 6-digit code sent to your verified admin email address.</p>

                <form method="post" autocomplete="off">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">

                    <div class="mb-4">
                        <label for="otp" class="form-label">One-time code</label>
                        <input type="text" class="form-control otp-input" id="otp" name="otp" maxlength="6" required pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" autofocus>
                    </div>

                    <div class="d-grid mb-2">
                        <button type="submit" class="btn btn-primary">Verify Code</button>
                    </div>
                </form>
                <form method="post" autocomplete="off" class="d-grid mb-3">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <button type="submit" id="resend-button" name="resend" value="1" class="btn btn-outline-secondary" <?php echo $resendWait > 0 ? 'disabled' : ''; ?>>Resend Code</button>
                </form>
                <div id="resend-timer" class="text-center small mb-2">
                    <?php if ($resendWait > 0): ?>
                        Please wait <strong><?php echo $resendWait; ?></strong> seconds to resend.
                    <?php endif; ?>
                </div>
                <div class="text-center small">
                    <a href="<?php echo url('login'); ?>">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function() {
        var wait = <?php echo (int)$resendWait; ?>;
        var button = document.getElementById('resend-button');
        var timer = document.getElementById('resend-timer');

        function updateTimer() {
            if (wait <= 0) {
                if (button) {
                    button.disabled = false;
                }
                if (timer) {
                    timer.innerHTML = '';
                }
                return;
            }
            if (button) {
                button.disabled = true;
            }
            if (timer) {
                timer.innerHTML = 'Please wait <strong>' + wait + '</strong> seconds to resend.';
            }
            wait -= 1;
            setTimeout(updateTimer, 1000);
        }

        if (button && wait > 0) {
            updateTimer();
        }
    })();
</script>
<?php require_once __DIR__ . '/includes/footer.php';
