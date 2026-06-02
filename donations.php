<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
ensureDonationSyncSchema();
requireLogin();

$searchDonor = sanitizeInput($_GET['donor_name'] ?? '');
$searchReceipt = sanitizeInput($_GET['receipt_number'] ?? '');
$filterDate = $_GET['donation_date'] ?? '';
$filterStatus = $_GET['status'] ?? 'active';
if (!in_array($filterStatus, ['all', 'active', 'cancelled'], true)) {
    $filterStatus = 'active';
}
$params = [];
$where = [];

if ($searchDonor !== '') {
    $where[] = 'donor_name LIKE :donor_name';
    $params['donor_name'] = '%' . $searchDonor . '%';
}
if ($searchReceipt !== '') {
    $where[] = 'receipt_number LIKE :receipt_number';
    $params['receipt_number'] = '%' . $searchReceipt . '%';
}
if ($filterDate !== '') {
    $where[] = 'donation_date = :donation_date';
    $params['donation_date'] = $filterDate;
}
if ($filterStatus !== 'all') {
    $where[] = 'status = :status';
    $params['status'] = $filterStatus;
}

$sql = 'SELECT id, receipt_number, donation_date, donor_name, amount, payment_mode, status FROM donations';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY donation_date DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
$noticeMessage = '';
if (isset($_GET['notice']) && $_GET['notice'] === 'cancelled') {
    $noticeMessage = 'Donation record cancelled successfully.';
}
?>
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h1 class="h3 mb-0">Donations</h1>
                <p class="text-muted mb-0">Search, edit and manage donation receipts.</p>
            </div>
            <a href="add-donation.php" class="btn btn-success">New Donation</a>
        </div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <?php if ($noticeMessage): ?>
                    <?php echo showAlert($noticeMessage, 'success'); ?>
                <?php endif; ?>
                <form method="get" action="donations.php" class="row gy-3 gx-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Donor Name</label>
                        <input type="search" class="form-control" name="donor_name" value="<?php echo escape($searchDonor); ?>" placeholder="Search by donor name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Receipt Number</label>
                        <input type="search" class="form-control" name="receipt_number" value="<?php echo escape($searchReceipt); ?>" placeholder="Search by receipt">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="donation_date" value="<?php echo escape($filterDate); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Donation Records</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($donations)): ?>
                    <div class="p-4 text-center text-muted">No donations found for the selected filters.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Receipt</th>
                                <th>Date</th>
                                <th>Donor Name</th>
                                <th>Amount</th>
                                <th>Payment Mode</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($donations as $donation): ?>
                                <tr>
                                    <td><?php echo escape($donation['receipt_number']); ?></td>
                                    <td><?php echo escape($donation['donation_date']); ?></td>
                                    <td><?php echo escape($donation['donor_name']); ?></td>
                                    <td><?php echo formatCurrency($donation['amount']); ?></td>
                                    <td><?php echo escape($donation['payment_mode']); ?></td>
                                    <td>
                                        <?php if ($donation['status'] === 'cancelled'): ?>
                                            <span class="badge bg-secondary">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="view-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                        <?php if ($donation['status'] === 'cancelled'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary" disabled>Edit</button>
                                        <?php else: ?>
                                            <a href="edit-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger cancel-donation-btn" data-bs-toggle="modal" data-bs-target="#cancelDonationModal" data-id="<?php echo $donation['id']; ?>" data-receipt="<?php echo escape($donation['receipt_number']); ?>" data-donor="<?php echo escape($donation['donor_name']); ?>" <?php echo $donation['status'] !== 'active' ? 'disabled' : ''; ?>>Cancel Donation</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="modal fade" id="cancelDonationModal" tabindex="-1" aria-labelledby="cancelDonationModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="cancelDonationModalLabel">Cancel Donation Receipt</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="post" action="delete-donation.php" id="cancelDonationForm">
                                    <div class="modal-body">
                                        <p id="cancelDonationModalMessage">This action will cancel the donation record but will not permanently remove it.</p>
                                        <div class="mb-3">
                                            <label for="cancel_reason" class="form-label">Cancellation Reason</label>
                                            <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" required></textarea>
                                        </div>
                                        <input type="hidden" name="id" id="cancel_donation_id" value="">
                                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-danger">Confirm Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('cancelDonationModal');
        var donationIdInput = document.getElementById('cancel_donation_id');
        var message = document.getElementById('cancelDonationModalMessage');
        document.querySelectorAll('.cancel-donation-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var donationId = button.getAttribute('data-id');
                var receipt = button.getAttribute('data-receipt');
                var donor = button.getAttribute('data-donor');
                donationIdInput.value = donationId;
                message.textContent = 'This action will cancel the donation record but will not permanently remove it. Receipt ' + receipt + ' by ' + donor + '.';
            });
        });
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php';
