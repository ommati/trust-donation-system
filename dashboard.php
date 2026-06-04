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

$stmt = $pdo->query('SELECT id, receipt_number, donor_name, amount, payment_mode, donation_date, status FROM donations ORDER BY donation_date DESC, id DESC LIMIT 7');
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
<div class="page-stack">
    <div class="page-header">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Overview of donations and recent activity.</p>
        </div>
        <div class="action-group">
            <a href="<?php echo url('add-donation'); ?>" class="btn btn-success">Add Donation</a>
            <a href="<?php echo url('donations'); ?>" class="btn btn-outline-primary">View Donations</a>
            <form method="post">
                <div class="d-grid">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <button type="submit" name="retry_syncs" value="1" class="btn btn-outline-warning">Retry Failed Syncs</button>
                </div>
            </form>
        </div>
    </div>
    <div class="row g-3 g-xl-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <h2 class="card-title">Active Donations</h2>
                    <p class="display-6 mb-1"><?php echo escape($stats['active_count']); ?></p>
                    <small class="text-muted">Active total: <?php echo formatCurrency($stats['active_total']); ?></small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <h2 class="card-title">Active Donors</h2>
                    <p class="display-6 mb-1"><?php echo escape($stats['active_donor_count']); ?></p>
                    <small class="text-muted">Unique active donor names</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <h2 class="card-title">Cancelled Donations</h2>
                    <p class="display-6 mb-1"><?php echo escape($stats['cancelled_count']); ?></p>
                    <small class="text-muted">Cancelled receipt records</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <h2 class="card-title">Pending Sync Records</h2>
                    <p class="display-6 mb-1"><?php echo escape($pendingCount); ?></p>
                    <small class="text-muted">Records awaiting synchronization</small>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($retryNotice)): ?>
        <div><?php echo showAlert($retryNotice, 'info'); ?></div>
    <?php endif; ?>
    <div class="card section-card">
        <div class="card-header">
            <h2 class="section-title">Recent Donations</h2>
        </div>
        <div class="card-body p-0">
            <?php if (count($recentDonations) === 0): ?>
                <div class="empty-state">No donation records found.</div>
            <?php else: ?>
                <div class="table-responsive responsive-table-wrap">
                    <table class="table table-hover align-middle responsive-data-table">
                        <thead class="table-light">
                        <tr>
                            <th>Receipt</th>
                            <th>Date</th>
                            <th>Donor</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentDonations as $donation): ?>
                            <tr class="<?php echo $donation['status'] === 'cancelled' ? 'table-danger' : ''; ?>">
                                <td data-label="Receipt"><?php echo escape($donation['receipt_number']); ?></td>
                                <td data-label="Date"><?php echo escape($donation['donation_date']); ?></td>
                                <td data-label="Donor"><?php echo escape($donation['donor_name']); ?></td>
                                <td data-label="Amount"><?php echo formatCurrency($donation['amount']); ?></td>
                                <td data-label="Payment"><?php echo escape($donation['payment_mode']); ?></td>
                                <td data-label="Status">
                                    <?php if ($donation['status'] === 'cancelled'): ?>
                                        <span class="badge bg-secondary">Cancelled</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Action" class="table-actions">
                                    <a href="<?php echo url('view-donation') . '?id=' . urlencode($donation['id']); ?>" class="btn btn-sm btn-outline-primary">View</a>
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
<?php require_once __DIR__ . '/includes/footer.php';
