<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$pageTitle = 'Nitya Seva Reports';
$members = getAllNityaSevaMembers($pdo);
$filters = [
    'member_id' => sanitizeInput($_GET['member_id'] ?? ''),
    'from_date' => sanitizeInput($_GET['from_date'] ?? date('Y-m-01')),
    'to_date' => sanitizeInput($_GET['to_date'] ?? date('Y-m-d')),
    'seva_month' => (int)($_GET['seva_month'] ?? 0),
    'seva_year' => (int)($_GET['seva_year'] ?? date('Y')),
];
if ($filters['seva_month'] < 1 || $filters['seva_month'] > 12) {
    $filters['seva_month'] = 0;
}
if ($filters['seva_year'] < 2000 || $filters['seva_year'] > 2100) {
    $filters['seva_year'] = 0;
}

$report = getNityaSevaPaymentReport($pdo, $filters);
$duesRows = getNityaSevaDuesRows($pdo);
$totalOutstanding = 0;
foreach ($duesRows as $row) {
    $totalOutstanding += $row['outstandingDue'];
}

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>
<div class="page-stack">
    <div class="page-header">
        <div>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Review collections by member, payment date, and seva month.</p>
        </div>
        <a href="<?php echo url('nitya-seva-pending-dues'); ?>" class="btn btn-outline-secondary">Pending Dues</a>
    </div>

    <div class="card section-card">
        <div class="card-body">
            <form method="get" action="<?php echo url('nitya-seva-reports'); ?>" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="member_id">Member</label>
                    <select class="form-select" id="member_id" name="member_id">
                        <option value="">All members</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo escape($member['member_id']); ?>" <?php echo $filters['member_id'] === $member['member_id'] ? 'selected' : ''; ?>>
                                <?php echo escape($member['name'] . ' (' . $member['member_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="from_date">From</label>
                    <input type="date" class="form-control" id="from_date" name="from_date" value="<?php echo escape($filters['from_date']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="to_date">To</label>
                    <input type="date" class="form-control" id="to_date" name="to_date" value="<?php echo escape($filters['to_date']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="seva_month">Seva Month</label>
                    <select class="form-select" id="seva_month" name="seva_month">
                        <option value="0">Any</option>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <option value="<?php echo $month; ?>" <?php echo (int)$filters['seva_month'] === $month ? 'selected' : ''; ?>><?php echo escape(getMonthName($month)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="seva_year">Year</label>
                    <input type="number" min="2000" max="2100" class="form-control" id="seva_year" name="seva_year" value="<?php echo escape($filters['seva_year']); ?>">
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a href="<?php echo url('nitya-seva-reports'); ?>" class="btn btn-outline-secondary">Reset</a>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 g-xl-4">
        <div class="col-12 col-md-4">
            <div class="card stat-card h-100"><div class="card-body"><h2 class="card-title">Payments</h2><p class="display-6 mb-1"><?php echo escape($report['count']); ?></p><small class="text-muted">Matching records</small></div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card h-100"><div class="card-body"><h2 class="card-title">Collected</h2><p class="display-6 mb-1"><?php echo formatCurrency($report['total']); ?></p><small class="text-muted">For selected filters</small></div></div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card stat-card h-100"><div class="card-body"><h2 class="card-title">Outstanding</h2><p class="display-6 mb-1"><?php echo formatCurrency($totalOutstanding); ?></p><small class="text-muted">Across active members</small></div></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="card-header">
            <h2 class="section-title">Payment Records</h2>
        </div>
        <div class="card-body p-0">
            <?php if (!$report['rows']): ?>
                <div class="empty-state">No payment records found for the selected filters.</div>
            <?php else: ?>
                <div class="table-responsive responsive-table-wrap">
                    <table class="table table-hover align-middle responsive-data-table">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Seva Month</th>
                                <th>Mode</th>
                                <th>Reference</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report['rows'] as $payment): ?>
                                <tr>
                                    <td data-label="Date"><?php echo escape(date('d M, Y', strtotime($payment['payment_date']))); ?></td>
                                    <td data-label="Member">
                                        <?php if (!empty($payment['member_row_id'])): ?>
                                            <a href="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($payment['member_row_id']); ?>"><?php echo escape($payment['member_name']); ?></a>
                                        <?php else: ?>
                                            <?php echo escape($payment['member_id']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Seva Month"><?php echo escape(getMonthName((int)$payment['seva_month']) . ' ' . $payment['seva_year']); ?></td>
                                    <td data-label="Mode"><?php echo escape($payment['payment_mode'] ?: '-'); ?></td>
                                    <td data-label="Reference"><?php echo escape($payment['reference_no'] ?: '-'); ?></td>
                                    <td data-label="Amount" class="text-end"><?php echo formatCurrency($payment['amount_paid']); ?></td>
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
