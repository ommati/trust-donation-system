<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$pageTitle = 'Record Nitya Seva Payment';
$errors = [];
$success = '';
$members = getAllNityaSevaMembers($pdo, true);
$selectedMemberId = sanitizeInput($_GET['member_id'] ?? '');
$values = [
    'member_id' => $selectedMemberId,
    'payment_date' => date('Y-m-d'),
    'seva_month' => date('n'),
    'seva_year' => date('Y'),
    'amount_paid' => '',
    'payment_mode' => 'Cash',
    'reference_no' => '',
    'remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request token. Please reload the form.';
    } else {
        $values = [
            'member_id' => sanitizeInput($_POST['member_id'] ?? ''),
            'payment_date' => sanitizeInput($_POST['payment_date'] ?? ''),
            'seva_month' => (int)($_POST['seva_month'] ?? 0),
            'seva_year' => (int)($_POST['seva_year'] ?? 0),
            'amount_paid' => trim($_POST['amount_paid'] ?? ''),
            'payment_mode' => sanitizeInput($_POST['payment_mode'] ?? 'Cash'),
            'reference_no' => sanitizeInput($_POST['reference_no'] ?? ''),
            'remarks' => sanitizeInput($_POST['remarks'] ?? ''),
        ];

        $member = null;
        foreach ($members as $candidate) {
            if ($candidate['member_id'] === $values['member_id']) {
                $member = $candidate;
                break;
            }
        }

        if (!$member) {
            $errors[] = 'Please select an active member.';
        }
        if ($values['payment_date'] === '') {
            $errors[] = 'Payment date is required.';
        }
        if ($values['seva_month'] < 1 || $values['seva_month'] > 12) {
            $errors[] = 'Please select a valid seva month.';
        }
        if ($values['seva_year'] < 2000 || $values['seva_year'] > 2100) {
            $errors[] = 'Please enter a valid seva year.';
        }
        if ($values['amount_paid'] === '' || !is_numeric($values['amount_paid']) || (float)$values['amount_paid'] <= 0) {
            $errors[] = 'Please enter a valid amount.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                "INSERT INTO nitya_seva_payments
                    (member_id, payment_date, seva_month, seva_year, amount_paid, payment_mode, reference_no, remarks, created_by, sync_status)
                 VALUES
                    (:member_id, :payment_date, :seva_month, :seva_year, :amount_paid, :payment_mode, :reference_no, :remarks, :created_by, 'pending')"
            );
            $stmt->execute([
                'member_id' => $values['member_id'],
                'payment_date' => $values['payment_date'],
                'seva_month' => $values['seva_month'],
                'seva_year' => $values['seva_year'],
                'amount_paid' => number_format((float)$values['amount_paid'], 2, '.', ''),
                'payment_mode' => $values['payment_mode'],
                'reference_no' => $values['reference_no'],
                'remarks' => $values['remarks'],
                'created_by' => $_SESSION['user_id'] ?? null,
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'nitya_seva_payment_recorded', $paymentId, json_encode([
                'member_id' => $values['member_id'],
                'amount_paid' => $values['amount_paid'],
                'seva_month' => $values['seva_month'],
                'seva_year' => $values['seva_year'],
            ]));

            $stmt = $pdo->prepare(
                "SELECT p.*, m.name AS member_name
                 FROM nitya_seva_payments p
                 LEFT JOIN nitya_seva_members m ON p.member_id = m.member_id
                 WHERE p.id = :id"
            );
            $stmt->execute(['id' => $paymentId]);
            $payment = $stmt->fetch();
            $sync = syncNityaSevaPayment($payment);
            if ($sync['ok']) {
                $update = $pdo->prepare("UPDATE nitya_seva_payments SET sync_status = 'synced', last_sync_at = NOW(), sync_error = NULL WHERE id = :id");
                $update->execute(['id' => $paymentId]);
            } else {
                $update = $pdo->prepare("UPDATE nitya_seva_payments SET sync_status = 'failed', sync_error = :error WHERE id = :id");
                $update->execute(['error' => substr($sync['message'] ?? 'Sync failed', 0, 65535), 'id' => $paymentId]);
            }

            $success = 'Payment recorded for ' . $member['name'] . '.';
            $values['amount_paid'] = '';
            $values['reference_no'] = '';
            $values['remarks'] = '';
        }
    }
}

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>
<div class="page-stack">
    <div class="page-header">
        <div>
            <h1 class="page-title">Record Payment</h1>
            <p class="page-subtitle">Add a monthly seva contribution against an active member.</p>
        </div>
        <a href="<?php echo url('nitya-seva-pending-dues'); ?>" class="btn btn-outline-secondary">View Pending Dues</a>
    </div>

    <?php foreach ($errors as $error): ?>
        <?php echo showAlert($error, 'danger'); ?>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <?php echo showAlert($success, 'success'); ?>
    <?php endif; ?>

    <div class="card section-card">
        <div class="card-body">
            <form method="post" action="<?php echo url('nitya-seva-record-payment'); ?>">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                <div class="row g-3 g-lg-4">
                    <div class="col-md-6">
                        <label class="form-label" for="member_id">Member</label>
                        <select class="form-select" id="member_id" name="member_id" required>
                            <option value="">Select member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo escape($member['member_id']); ?>" <?php echo $values['member_id'] === $member['member_id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($member['name'] . ' (' . $member['member_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payment_date">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo escape($values['payment_date']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="seva_month">Seva Month</label>
                        <select class="form-select" id="seva_month" name="seva_month" required>
                            <?php for ($month = 1; $month <= 12; $month++): ?>
                                <option value="<?php echo $month; ?>" <?php echo (int)$values['seva_month'] === $month ? 'selected' : ''; ?>><?php echo escape(getMonthName($month)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="seva_year">Seva Year</label>
                        <input type="number" min="2000" max="2100" class="form-control" id="seva_year" name="seva_year" value="<?php echo escape($values['seva_year']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="amount_paid">Amount Paid</label>
                        <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" value="<?php echo escape($values['amount_paid']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payment_mode">Payment Mode</label>
                        <select class="form-select" id="payment_mode" name="payment_mode">
                            <?php foreach (getPaymentModes() as $mode): ?>
                                <option value="<?php echo escape($mode); ?>" <?php echo $values['payment_mode'] === $mode ? 'selected' : ''; ?>><?php echo escape($mode); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="reference_no">Reference No.</label>
                        <input type="text" class="form-control" id="reference_no" name="reference_no" value="<?php echo escape($values['reference_no']); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2"><?php echo escape($values['remarks']); ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/nitya_seva_footer.php'; ?>
