<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/google_sheets.php';
requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('donations.php');
}

$stmt = $pdo->prepare('SELECT receipt_number, donor_name FROM donations WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$donation = $stmt->fetch();
if (!$donation) {
    redirect('donations.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Soft-cancel the donation and sync status to Google Sheets
        $stmt = $pdo->prepare('UPDATE donations SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => 'cancelled', 'id' => $id]);
        // Attempt to sync cancellation (non-blocking)
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
        redirect('donations.php');
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Confirm Delete</h5>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <?php echo showAlert($error, 'danger'); ?>
                <?php endif; ?>
                <p>Are you sure you want to permanently delete donation record <strong><?php echo escape($donation['receipt_number']); ?></strong> by <strong><?php echo escape($donation['donor_name']); ?></strong>?</p>
                <form method="post" action="delete-donation.php?id=<?php echo $id; ?>">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <div class="d-flex gap-2">
                        <a href="donations.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">Delete Donation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
