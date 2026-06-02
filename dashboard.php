<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
ensureDonationSyncSchema();

$stmt = $pdo->query("SELECT
    COALESCE(SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END), 0) AS active_total,
    COALESCE(SUM(status = 'active'), 0) AS active_count,
    COALESCE(SUM(status = 'cancelled'), 0) AS cancelled_count,
    COUNT(DISTINCT CASE WHEN status = 'active' THEN donor_name END) AS active_donor_count
FROM donations");
$stats = $stmt->fetch();

$stmt = $pdo->query('SELECT id, receipt_number, donor_name, amount, payment_mode, donation_date FROM donations ORDER BY donation_date DESC, id DESC LIMIT 7');
$recentDonations = $stmt->fetchAll();

$pendingStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM donations WHERE sync_status IN ('pending','failed')");
$pendingCount = (int)($pendingStmt->fetchColumn() ?? 0);

// Handle retry action
$retryNotice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['retry_syncs'])) {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $retryNotice = 'Invalid request token.';
    } else {
        require_once __DIR__ . '/includes/google_sheets.php';
        $res = retryPendingSyncs(200);
        $retryNotice = 'Retry completed. Processed ' . intval($res['processed']) . ', succeeded ' . intval($res['succeeded']) . ', failed ' . intval($res['failed']) . '.';
        $pendingStmt = $pdo->query("SELECT COUNT(*) AS cnt FROM donations WHERE sync_status IN ('pending','failed')");
        $pendingCount = (int)($pendingStmt->fetchColumn() ?? 0);
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row gy-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h1 class="h3 mb-1">Dashboard</h1>
                <p class="text-muted">Overview of donations and recent activity.</p>
            </div>
            <div>
                <a href="add-donation.php" class="btn btn-success me-2">Add Donation</a>
                <a href="donations.php" class="btn btn-outline-primary">View Donations</a>
                <form method="post" class="d-inline ms-2">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <button type="submit" name="retry_syncs" value="1" class="btn btn-outline-warning">Retry Failed Syncs</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Active Donations</h5>
                <p class="display-6 mb-0"><?php echo escape($stats['active_count']); ?></p>
                <small class="text-muted">Active total: <?php echo formatCurrency($stats['active_total']); ?></small>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Cancelled Donations</h5>
                <p class="display-6 mb-0"><?php echo escape($stats['cancelled_count']); ?></p>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Pending Sync Records</h5>
                <p class="display-6 mb-0"><?php echo escape($pendingCount); ?></p>
                <?php if (!empty($retryNotice)): ?>
                    <div class="mt-3 small"><?php echo showAlert($retryNotice, 'info'); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Recent Donations</h5>
            </div>
            <div class="card-body p-0">
                <?php if (count($recentDonations) === 0): ?>
                    <div class="p-4 text-center text-muted">No donation records found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Receipt</th>
                                <th>Date</th>
                                <th>Donor</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th class="text-end">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentDonations as $donation): ?>
                                <tr>
                                    <td><?php echo escape($donation['receipt_number']); ?></td>
                                    <td><?php echo escape($donation['donation_date']); ?></td>
                                    <td><?php echo escape($donation['donor_name']); ?></td>
                                    <td><?php echo formatCurrency($donation['amount']); ?></td>
                                    <td><?php echo escape($donation['payment_mode']); ?></td>
                                    <td class="text-end">
                                        <a href="view-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
