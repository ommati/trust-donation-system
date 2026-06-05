<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema(); // Ensure tables exist

$pageTitle = 'Nitya Seva Dashboard';

$stats = getNityaSevaDashboardStats($pdo);
$recentPayments = getRecentNityaSevaPayments($pdo);
$recentMembers = getRecentNityaSevaMembers($pdo);

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>

<h1 class="h3 mb-4"><?php echo $pageTitle; ?></h1>

<!-- Stats Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Active Members</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['totalActiveMembers']; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Monthly Expected Collection</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['monthlyExpectedCollection']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-rupee-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Current Month Collection</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['currentMonthCollection']); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Outstanding Dues</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($stats['totalOutstandingDues']); ?></div>
                        <div class="text-xs text-muted">(<?php echo $stats['membersWithDues']; ?> members)</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Payments</h6>
                <a href="<?php echo url('nitya-seva-reports'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead><tr><th>Member</th><th>Amount</th><th>Payment Date</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentPayments)): ?>
                                <tr><td colspan="3" class="text-center">No recent payments found.</td></tr>
                            <?php else: foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo escape($payment['member_name']); ?> (<?php echo escape($payment['member_id']); ?>)</td>
                                    <td><?php echo formatCurrency($payment['amount_paid']); ?></td>
                                    <td><?php echo date('d M, Y', strtotime($payment['payment_date'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Members</h6>
                <a href="<?php echo url('nitya-seva-members'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <?php if (empty($recentMembers)): ?>
                                <tr><td class="text-center">No recent members found.</td></tr>
                            <?php else: foreach ($recentMembers as $member): ?>
                                <tr>
                                    <td><strong><?php echo escape($member['name']); ?></strong> (<?php echo escape($member['member_id']); ?>)<br><small class="text-muted">Joined: <?php echo date('d M, Y', strtotime($member['created_at'])); ?></small></td>
                                    <td class="text-end"><?php echo formatCurrency($member['monthly_seva_amount']); ?>/mo</td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/nitya_seva_footer.php';
?>
