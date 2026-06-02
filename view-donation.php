<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
ensureDonationSyncSchema();
requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('donations.php');
}

$stmt = $pdo->prepare('SELECT * FROM donations WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$donation = $stmt->fetch();
if (!$donation) {
    redirect('donations.php');
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Donation Details</h5>
                    <small class="text-muted">Receipt number <?php echo escape($donation['receipt_number']); ?></small>
                </div>
                <div class="btn-group">
                    <a href="receipt.php?id=<?php echo $donation['id']; ?>" class="btn btn-primary">Receipt</a>
                    <a href="download-pdf.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline-primary">PDF</a>
                    <a href="edit-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline-secondary">Edit</a>
                    <?php if ($donation['status'] === 'active'): ?>
                        <a href="delete-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-outline-danger">Cancel Donation</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row gy-3">
                    <div class="col-md-4"><strong>Receipt Number</strong><p><?php echo escape($donation['receipt_number']); ?></p></div>
                    <div class="col-md-4"><strong>Date</strong><p><?php echo escape($donation['donation_date']); ?></p></div>
                    <div class="col-md-4"><strong>Payment Mode</strong><p><?php echo escape($donation['payment_mode']); ?></p></div>
                    <div class="col-md-4"><strong>Status</strong><p><?php echo $donation['status'] === 'cancelled' ? '<span class="badge bg-secondary">Cancelled</span>' : '<span class="badge bg-success">Active</span>'; ?></p></div>
                    <div class="col-md-4"><strong>Donor Name</strong><p><?php echo escape($donation['donor_name']); ?></p></div>
                    <div class="col-md-6"><strong>Mobile Number</strong><p><?php echo escape($donation['mobile']); ?></p></div>
                    <div class="col-12"><strong>Address</strong><p><?php echo nl2br(escape($donation['address'])); ?></p></div>
                    <div class="col-md-4"><strong>Amount</strong><p><?php echo formatCurrency($donation['amount']); ?></p></div>
                    <div class="col-md-8"><strong>Purpose</strong><p><?php echo escape($donation['purpose']); ?></p></div>
                    <div class="col-12"><strong>Remarks</strong><p><?php echo nl2br(escape($donation['remarks'])); ?></p></div>
                    <?php if ($donation['status'] === 'cancelled'): ?>
                        <div class="col-md-4"><strong>Cancelled At</strong><p><?php echo escape($donation['cancelled_at']); ?></p></div>
                        <div class="col-md-4"><strong>Cancelled By</strong><p><?php echo escape($donation['cancelled_by']); ?></p></div>
                        <div class="col-md-4"><strong>Cancel Reason</strong><p><?php echo nl2br(escape($donation['cancel_reason'])); ?></p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
