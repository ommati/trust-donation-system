<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$firebaseConfig = [
    'apiKey' => FIREBASE_API_KEY,
    'authDomain' => FIREBASE_AUTH_DOMAIN,
    'projectId' => FIREBASE_PROJECT_ID,
    'storageBucket' => FIREBASE_STORAGE_BUCKET,
    'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
    'appId' => FIREBASE_APP_ID,
];
$allowedPhones = allowedOtpPhones();
$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header text-center">
                <h4 class="mb-0">Login with Phone OTP</h4>
            </div>
            <div class="card-body">
                <div id="authAlert" class="alert d-none" role="alert"></div>
                <?php if ($loginError): ?>
                    <?php echo showAlert($loginError, 'danger'); ?>
                <?php endif; ?>

                <div id="phoneStep">
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text">+91</span>
                            <input type="tel" class="form-control" id="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required autofocus autocomplete="tel">
                        </div>
                    </div>
                    <div id="recaptcha-container" class="mb-3"></div>
                    <div class="d-grid">
                        <button type="button" id="sendOtpBtn" class="btn btn-primary">Send OTP</button>
                    </div>
                </div>

                <div id="otpStep" class="d-none">
                    <p class="text-muted small">Enter the 6-digit OTP sent by Firebase to <strong id="otpPhoneLabel"></strong>.</p>
                    <div class="mb-3">
                        <label for="otp" class="form-label">OTP</label>
                        <input type="text" class="form-control text-center fs-4" id="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" id="verifyOtpBtn" class="btn btn-primary">Verify & Continue</button>
                        <button type="button" id="changePhoneBtn" class="btn btn-outline-secondary">Change Phone Number</button>
                    </div>
                </div>

                <form method="post" action="verify-otp.php" id="firebaseLoginForm" class="d-none">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <input type="hidden" name="firebase_id_token" id="firebaseIdToken">
                </form>
            </div>
            <div class="card-footer text-muted text-center small">
                Access is limited to authorized phone numbers only.
            </div>
        </div>
    </div>
</div>

<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/12.14.0/firebase-app.js";
    import { getAuth, RecaptchaVerifier, signInWithPhoneNumber } from "https://www.gstatic.com/firebasejs/12.14.0/firebase-auth.js";

    const firebaseConfig = <?php echo json_encode($firebaseConfig, JSON_UNESCAPED_SLASHES); ?>;
    const allowedPhones = <?php echo json_encode($allowedPhones); ?>;

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    auth.languageCode = "en";

    const phoneInput = document.getElementById("phone");
    const otpInput = document.getElementById("otp");
    const sendOtpBtn = document.getElementById("sendOtpBtn");
    const verifyOtpBtn = document.getElementById("verifyOtpBtn");
    const changePhoneBtn = document.getElementById("changePhoneBtn");
    const phoneStep = document.getElementById("phoneStep");
    const otpStep = document.getElementById("otpStep");
    const otpPhoneLabel = document.getElementById("otpPhoneLabel");
    const alertBox = document.getElementById("authAlert");
    const firebaseIdToken = document.getElementById("firebaseIdToken");
    const firebaseLoginForm = document.getElementById("firebaseLoginForm");

    let confirmationResult = null;
    let recaptchaVerifier = null;

    function showAlert(message, type = "danger") {
        alertBox.className = "alert alert-" + type;
        alertBox.textContent = message;
    }

    function clearAlert() {
        alertBox.className = "alert d-none";
        alertBox.textContent = "";
    }

    function normalizePhone(value) {
        let phone = String(value || "").replace(/\D/g, "");
        if (phone.length > 10 && phone.startsWith("91")) {
            phone = phone.slice(-10);
        }
        return phone;
    }

    function setBusy(button, busy, text) {
        button.disabled = busy;
        if (text) {
            button.textContent = text;
        }
    }

    async function ensureRecaptcha() {
        if (recaptchaVerifier) {
            return recaptchaVerifier;
        }

        recaptchaVerifier = new RecaptchaVerifier(auth, "recaptcha-container", {
            size: "normal",
            callback: () => clearAlert(),
        });
        await recaptchaVerifier.render();
        return recaptchaVerifier;
    }

    sendOtpBtn.addEventListener("click", async () => {
        clearAlert();
        const phone = normalizePhone(phoneInput.value);

        if (!/^[6-9]\d{9}$/.test(phone)) {
            showAlert("Please enter a valid 10-digit phone number.");
            return;
        }

        if (!allowedPhones.includes(phone)) {
            showAlert("This phone number is not authorized for login.");
            return;
        }

        try {
            setBusy(sendOtpBtn, true, "Sending...");
            const verifier = await ensureRecaptcha();
            confirmationResult = await signInWithPhoneNumber(auth, "+91" + phone, verifier);
            otpPhoneLabel.textContent = "+91 " + phone;
            phoneStep.classList.add("d-none");
            otpStep.classList.remove("d-none");
            otpInput.focus();
            showAlert("OTP sent successfully.", "success");
        } catch (error) {
            console.error(error);
            showAlert(error.message || "Unable to send OTP. Check Firebase phone authentication settings.");
            if (recaptchaVerifier) {
                recaptchaVerifier.clear();
                recaptchaVerifier = null;
                document.getElementById("recaptcha-container").innerHTML = "";
            }
        } finally {
            setBusy(sendOtpBtn, false, "Send OTP");
        }
    });

    verifyOtpBtn.addEventListener("click", async () => {
        clearAlert();
        const otp = normalizePhone(otpInput.value);

        if (!confirmationResult) {
            showAlert("Please request an OTP first.");
            return;
        }

        if (!/^\d{6}$/.test(otp)) {
            showAlert("Please enter the 6-digit OTP.");
            return;
        }

        try {
            setBusy(verifyOtpBtn, true, "Verifying...");
            const credential = await confirmationResult.confirm(otp);
            firebaseIdToken.value = await credential.user.getIdToken();
            firebaseLoginForm.submit();
        } catch (error) {
            console.error(error);
            showAlert(error.message || "Invalid OTP.");
            setBusy(verifyOtpBtn, false, "Verify & Continue");
        }
    });

    changePhoneBtn.addEventListener("click", () => {
        confirmationResult = null;
        otpInput.value = "";
        otpStep.classList.add("d-none");
        phoneStep.classList.remove("d-none");
        clearAlert();
        phoneInput.focus();
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php';
