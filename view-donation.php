<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
ensureDonationSyncSchema();
requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('donations');
}

$stmt = $pdo->prepare('SELECT * FROM donations WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$donation = $stmt->fetch();
if (!$donation) {
    redirect('donations');
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-11">
        <div class="card section-card">
            <div class="card-header card-header-responsive">
                <div>
                    <h1 class="section-title">Donation Details</h1>
                    <p class="section-subtitle">Receipt number <?php echo escape($donation['receipt_number']); ?></p>
                </div>
                <div class="action-group">
                    <?php if ($donation['status'] === 'cancelled'): ?>
                        <button type="button" class="btn btn-secondary" disabled>Receipt unavailable</button>
                        <button type="button" class="btn btn-outline-secondary" disabled>PDF unavailable</button>
                        <button type="button" class="btn btn-outline-secondary" disabled>Edit</button>
                    <?php else: ?>
                        <a href="<?php echo url('receipt') . '?id=' . urlencode($donation['id']); ?>" class="btn btn-primary">Receipt</a>
                        <a href="<?php echo url('download-pdf') . '?id=' . urlencode($donation['id']); ?>" class="btn btn-outline-primary">PDF</a>
                        <a href="<?php echo url('edit-donation') . '?id=' . urlencode($donation['id']); ?>" class="btn btn-outline-secondary">Edit</a>
                        <a href="<?php echo url('delete-donation') . '?id=' . urlencode($donation['id']); ?>" class="btn btn-outline-danger">Cancel Donation</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Receipt Number</strong><p><?php echo escape($donation['receipt_number']); ?></p></div></div>
                    <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Date</strong><p><?php echo escape($donation['donation_date']); ?></p></div></div>
                    <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Payment Mode</strong><p><?php echo escape($donation['payment_mode']); ?></p></div></div>
                    <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Status</strong><p><?php echo $donation['status'] === 'cancelled' ? '<span class="badge bg-secondary">Cancelled</span>' : '<span class="badge bg-success">Active</span>'; ?></p></div></div>
                    <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Donor Name</strong><p><?php echo escape($donation['donor_name']); ?></p></div></div>
                    <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Mobile Number</strong><p><?php echo escape($donation['mobile']); ?></p></div></div>
                    <div class="col-12"><div class="detail-item"><strong>Address</strong><p><?php echo nl2br(escape($donation['address'])); ?></p></div></div>
                    <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Amount</strong><p><?php echo formatCurrency($donation['amount']); ?></p></div></div>
                    <div class="col-12 col-sm-6 col-lg-8"><div class="detail-item"><strong>Purpose</strong><p><?php echo escape($donation['purpose']); ?></p></div></div>
                    <div class="col-12"><div class="detail-item"><strong>Remarks</strong><p><?php echo nl2br(escape($donation['remarks'])); ?></p></div></div>
                    <?php if ($donation['status'] === 'cancelled'): ?>
                        <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Cancelled At</strong><p><?php echo escape($donation['cancelled_at']); ?></p></div></div>
                        <div class="col-12 col-sm-6 col-lg-4"><div class="detail-item"><strong>Cancelled By</strong><p><?php echo escape($donation['cancelled_by']); ?></p></div></div>
                        <div class="col-12 col-lg-4"><div class="detail-item"><strong>Cancel Reason</strong><p><?php echo nl2br(escape($donation['cancel_reason'])); ?></p></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
