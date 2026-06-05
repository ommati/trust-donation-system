<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$pageTitle = 'Nitya Seva Sync';
$notice = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['retry_syncs'])) {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $notice = 'Invalid request token.';
    } else {
        $result = retryPendingNityaSevaSyncs($pdo, 200);
        $notice = 'Sync retry completed. Processed ' . (int)$result['processed'] . ', succeeded ' . (int)$result['succeeded'] . ', failed ' . (int)$result['failed'] . '.';
    }
}

$stats = getNityaSevaSyncStats($pdo);
$pendingRows = getRecentNityaSevaSyncRows($pdo);
$membersSheet = nityaSevaSheetName('members');
$paymentsSheet = nityaSevaSheetName('payments');

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>
<div class="page-stack">
    <div class="page-header">
        <div>
            <h1 class="page-title">Sync</h1>
            <p class="page-subtitle">Track member and payment records waiting for Google Sheets synchronization.</p>
        </div>
        <form method="post">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
            <button type="submit" name="retry_syncs" value="1" class="btn btn-primary">Retry Pending Syncs</button>
        </form>
    </div>

    <?php if ($notice): ?>
        <?php echo showAlert($notice, 'info'); ?>
    <?php endif; ?>

    <div class="row g-3 g-xl-4">
        <div class="col-12 col-md-4">
            <div class="card stat-card h-100"><div class="card-body"><h2 class="card-title">Pending Members</h2><p class="display-6 mb-1"><?php echo escape($stats['members']['pending'] + $stats['members']['failed']); ?></p><small class="text-muted">Sheet: <?php echo escape($membersSheet); ?></small></div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card h-100"><div class="card-body"><h2 class="card-title">Pending Payments</h2><p class="display-6 mb-1"><?php echo escape($stats['payments']['pending'] + $stats['payments']['failed']); ?></p><small class="text-muted">Sheet: <?php echo escape($paymentsSheet); ?></small></div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card h-100"><div class="card-body"><h2 class="card-title">Synced Records</h2><p class="display-6 mb-1"><?php echo escape($stats['members']['synced'] + $stats['payments']['synced']); ?></p><small class="text-muted">Members and payments</small></div></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="card-header">
            <h2 class="section-title">Pending Or Failed Records</h2>
        </div>
        <div class="card-body p-0">
            <?php if (!$pendingRows): ?>
                <div class="empty-state">No records are waiting for synchronization.</div>
            <?php else: ?>
                <div class="table-responsive responsive-table-wrap">
                    <table class="table table-hover align-middle responsive-data-table">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Record</th>
                                <th>Status</th>
                                <th>Last Sync</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRows as $row): ?>
                                <tr>
                                    <td data-label="Type"><?php echo escape(ucfirst($row['record_type'])); ?></td>
                                    <td data-label="Record">
                                        <strong><?php echo escape($row['title']); ?></strong><br>
                                        <small class="text-muted"><?php echo escape($row['member_id']); ?></small>
                                    </td>
                                    <td data-label="Status"><span class="badge bg-secondary"><?php echo escape($row['sync_status']); ?></span></td>
                                    <td data-label="Last Sync"><?php echo escape($row['last_sync_at'] ?: '-'); ?></td>
                                    <td data-label="Error"><?php echo escape($row['sync_error'] ?: '-'); ?></td>
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
