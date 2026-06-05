<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$pageTitle = 'Nitya Seva Pending Dues';
$duesRows = getNityaSevaDuesRows($pdo);
$totalDue = 0;
foreach ($duesRows as $row) {
    $totalDue += $row['outstandingDue'];
}

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>
<div class="page-stack">
    <div class="page-header">
        <div>
            <h1 class="page-title">Pending Dues</h1>
            <p class="page-subtitle"><?php echo count($duesRows); ?> members with outstanding seva dues.</p>
        </div>
        <a href="<?php echo url('nitya-seva-members?tab=record'); ?>" class="btn btn-primary">Record Payment</a>
    </div>

    <div class="row g-3 g-xl-4">
        <div class="col-12 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <h2 class="card-title">Members With Dues</h2>
                    <p class="display-6 mb-1"><?php echo count($duesRows); ?></p>
                    <small class="text-muted">Active members only</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <h2 class="card-title">Outstanding Total</h2>
                    <p class="display-6 mb-1"><?php echo formatCurrency($totalDue); ?></p>
                    <small class="text-muted">Expected minus received</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card section-card">
        <div class="card-header">
            <h2 class="section-title">Due Members</h2>
        </div>
        <div class="card-body p-0">
            <?php if (!$duesRows): ?>
                <div class="empty-state">No pending dues found.</div>
            <?php else: ?>
                <div class="table-responsive responsive-table-wrap">
                    <table class="table table-hover align-middle responsive-data-table">
                        <thead class="table-light">
                            <tr>
                                <th>Member</th>
                                <th>Phone</th>
                                <th class="text-end">Expected</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Due</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($duesRows as $row): ?>
                                <?php $member = $row['member']; ?>
                                <tr>
                                    <td data-label="Member">
                                        <strong><?php echo escape($member['name']); ?></strong><br>
                                        <small class="text-muted"><?php echo escape($member['member_id']); ?></small>
                                    </td>
                                    <td data-label="Phone"><?php echo escape($member['phone'] ?: '-'); ?></td>
                                    <td data-label="Expected" class="text-end"><?php echo formatCurrency($row['totalExpected']); ?></td>
                                    <td data-label="Paid" class="text-end"><?php echo formatCurrency($row['totalPaid']); ?></td>
                                    <td data-label="Due" class="text-end text-danger fw-bold"><?php echo formatCurrency($row['outstandingDue']); ?></td>
                                    <td data-label="Action" class="table-actions">
                                        <div class="action-group action-group-sm justify-content-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo url('nitya-seva-members?tab=record&member_id=' . urlencode($member['member_id'])); ?>">Pay</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($member['id']); ?>">View</a>
                                        </div>
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
<?php require_once __DIR__ . '/includes/nitya_seva_footer.php'; ?>
