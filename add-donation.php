<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
ensureDonationSyncSchema();
require_once __DIR__ . '/includes/google_sheets.php';
requireLogin();

$errors = [];
$success = '';

$receiptNumber = getNextReceiptNumber($pdo);

$values = [
    'donation_date' => date('Y-m-d'),
    'donor_name' => '',
    'mobile' => '',
    'address' => '',
    'amount' => '',
    'payment_mode' => 'Cash',
    'purpose' => '',
    'remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request token. Please reload the form.';
    } else {
        $values = [
            'donation_date' => $_POST['donation_date'] ?? date('Y-m-d'),
            'donor_name' => sanitizeInput($_POST['donor_name'] ?? ''),
            'mobile' => sanitizeInput($_POST['mobile'] ?? ''),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'amount' => trim($_POST['amount'] ?? ''),
            'payment_mode' => sanitizeInput($_POST['payment_mode'] ?? 'Cash'),
            'purpose' => sanitizeInput($_POST['purpose'] ?? ''),
            'remarks' => sanitizeInput($_POST['remarks'] ?? ''),
        ];

        if ($values['donor_name'] === '') {
            $errors[] = 'Donor name is required.';
        }
        if ($values['amount'] === '' || !is_numeric($values['amount']) || floatval($values['amount']) <= 0) {
            $errors[] = 'Please enter a valid donation amount.';
        }
        $paymentModes = getPaymentModes();
        if (!in_array($values['payment_mode'], $paymentModes, true)) {
            $values['payment_mode'] = 'Cash';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO donations (receipt_number, donation_date, donor_name, mobile, address, amount, payment_mode, purpose, remarks) VALUES (:receipt_number, :donation_date, :donor_name, :mobile, :address, :amount, :payment_mode, :purpose, :remarks)');
                $stmt->execute([
                    'receipt_number' => uniqid('TMP_', true),
                    'donation_date' => $values['donation_date'],
                    'donor_name' => $values['donor_name'],
                    'mobile' => $values['mobile'],
                    'address' => $values['address'],
                    'amount' => number_format((float)$values['amount'], 2, '.', ''),
                    'payment_mode' => $values['payment_mode'],
                    'purpose' => $values['purpose'],
                    'remarks' => $values['remarks'],
                ]);

                $donationId = $pdo->lastInsertId();
                $receiptNumber = generateReceiptNumberFromId($donationId);

                $update = $pdo->prepare('UPDATE donations SET receipt_number = :receipt_number WHERE id = :id');
                $update->execute([
                    'receipt_number' => $receiptNumber,
                    'id' => $donationId,
                ]);

                $pdo->commit();

                recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'Donation Created', $donationId, json_encode([
                    'receipt_number' => $receiptNumber,
                    'donor_name' => $values['donor_name'],
                    'amount' => $values['amount'],
                ]));

                // Fetch the saved donation and attempt Google Sheets sync (do not block user)
                $stmt2 = $pdo->prepare('SELECT * FROM donations WHERE id = :id LIMIT 1');
                $stmt2->execute(['id' => $donationId]);
                $donationRow = $stmt2->fetch();
                $syncRes = syncDonationCreate($donationRow);
                if ($syncRes['ok']) {
                    $stmt3 = $pdo->prepare('UPDATE donations SET sync_status = :s, last_sync_at = NOW(), sync_error = NULL WHERE id = :id');
                    $stmt3->execute(['s' => 'synced', 'id' => $donationId]);
                } else {
                    $stmt3 = $pdo->prepare('UPDATE donations SET sync_status = :s, sync_error = :e WHERE id = :id');
                    $stmt3->execute(['s' => 'pending', 'e' => substr($syncRes['message'] ?? 'Sheets sync failed', 0, 65535), 'id' => $donationId]);
                }

                $success = 'Donation recorded successfully with receipt number ' . escape($receiptNumber) . '.';
                $values = [
                    'donation_date' => date('Y-m-d'),
                    'donor_name' => '',
                    'mobile' => '',
                    'address' => '',
                    'amount' => '',
                    'payment_mode' => 'Cash',
                    'purpose' => '',
                    'remarks' => '',
                ];
                $receiptNumber = getNextReceiptNumber($pdo);
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Unable to save donation. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Add Donation</h5>
                    <small class="text-muted">Record a new donation and generate a receipt number.</small>
                </div>
                <a href="donations.php" class="btn btn-outline-secondary btn-sm">Back to Donations</a>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <?php echo showAlert($success, 'success'); ?>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <?php foreach ($errors as $error): ?>
                        <?php echo showAlert($error, 'danger'); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <form method="post" action="add-donation.php" data-prevent-duplicate>
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <div class="row gy-3">
                        <div class="col-md-4">
                            <label class="form-label">Receipt Number</label>
                            <input type="text" class="form-control" value="<?php echo escape($receiptNumber); ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="donation_date">Date</label>
                            <input type="date" class="form-control" id="donation_date" name="donation_date" value="<?php echo escape($values['donation_date']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="donor_name">Donor Name</label>
                            <input type="text" class="form-control" id="donor_name" name="donor_name" value="<?php echo escape($values['donor_name']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mobile">Mobile Number</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo escape($values['mobile']); ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo escape($values['address']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="amount">Donation Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" value="<?php echo escape($values['amount']); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="payment_mode">Payment Mode</label>
                            <select class="form-select" id="payment_mode" name="payment_mode">
                                <?php foreach (getPaymentModes() as $mode): ?>
                                    <option value="<?php echo escape($mode); ?>" <?php echo $values['payment_mode'] === $mode ? 'selected' : ''; ?>><?php echo escape($mode); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="purpose">Donation Purpose</label>
                            <input type="text" class="form-control" id="purpose" name="purpose" value="<?php echo escape($values['purpose']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="remarks">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo escape($values['remarks']); ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Donation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
