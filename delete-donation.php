<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
ensureDonationSyncSchema();
require_once __DIR__ . '/includes/google_sheets.php';
requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: $id;
}
if (!$id) {
    redirect('donations');
}

$stmt = $pdo->prepare('SELECT receipt_number, donor_name, status FROM donations WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$donation = $stmt->fetch();
if (!$donation) {
    redirect('donations');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $cancelReason = sanitizeInput($_POST['cancel_reason'] ?? '');
        if ($cancelReason === '') {
            $error = 'Cancellation reason is required.';
        } elseif ($donation['status'] === 'cancelled') {
            $error = 'This donation has already been cancelled.';
        } else {
            $stmt = $pdo->prepare('UPDATE donations SET status = :status, cancel_reason = :cancel_reason, cancelled_at = NOW(), cancelled_by = :cancelled_by, updated_at = NOW() WHERE id = :id');
            $stmt->execute([
                'status' => 'cancelled',
                'cancel_reason' => $cancelReason,
                'cancelled_by' => $_SESSION['user_id'] ?? null,
                'id' => $id,
            ]);

            recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'Donation Cancelled', $id, json_encode(['reason' => $cancelReason]));

            $stmt2 = $pdo->prepare('SELECT * FROM donations WHERE id = :id LIMIT 1');
            $stmt2->execute(['id' => $id]);
            $donationRow = $stmt2->fetch();
            $syncRes = syncDonationCancel($donationRow);
            if ($syncRes['ok']) {
                $stmt3 = $pdo->prepare('UPDATE donations SET sync_status = :s, last_sync_at = NOW(), sync_error = NULL WHERE id = :id');
                $stmt3->execute(['s' => 'synced', 'id' => $id]);
            } else {
                $stmt3 = $pdo->prepare('UPDATE donations SET sync_status = :s, sync_error = :e WHERE id = :id');
                $stmt3->execute(['s' => 'pending', 'e' => substr($syncRes['message'] ?? 'Sheets sync failed', 0, 65535), 'id' => $id]);
            }

            redirect('donations.php?notice=cancelled');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Cancel Donation Receipt</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <?php echo showAlert($error, 'danger'); ?>
                <?php endif; ?>
                <p>This action will cancel the donation record but will not permanently remove it. You are cancelling receipt <strong><?php echo escape($donation['receipt_number']); ?></strong> for <strong><?php echo escape($donation['donor_name']); ?></strong>.</p>
                <form method="post" action="<?php echo url('delete-donation') . '?id=' . urlencode($id); ?>">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="cancel_reason">Cancellation Reason</label>
                        <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" required><?php echo escape($_POST['cancel_reason'] ?? ''); ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?php echo url('donations'); ?>" class="btn btn-secondary">Close</a>
                        <button type="submit" class="btn btn-danger">Confirm Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
