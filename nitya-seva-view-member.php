<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('nitya-seva-members');
}

$member = getNityaSevaMemberById($pdo, $id);
if (!$member) {
    redirect('nitya-seva-members');
}

$dues = calculateMemberDues($pdo, $id);
$payments = getNityaSevaPaymentsForMember($pdo, $member['member_id']);
$pageTitle = 'Nitya Seva Member';

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?php echo escape($member['name']); ?></h1>
        <div class="text-muted"><?php echo escape($member['member_id']); ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo url('nitya-seva-members'); ?>" class="btn btn-outline-secondary">Back</a>
        <a href="<?php echo url('nitya-seva-edit-member') . '?id=' . urlencode($member['id']); ?>" class="btn btn-primary">Edit</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Monthly Seva</div>
                <div class="h4 mb-0"><?php echo formatCurrency($member['monthly_seva_amount']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Total Paid</div>
                <div class="h4 mb-0"><?php echo formatCurrency($dues['totalPaid']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small mb-1">Outstanding Due</div>
                <div class="h4 mb-0 <?php echo $dues['outstandingDue'] > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo formatCurrency($dues['outstandingDue']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Member Details</h2>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7">
                        <?php if ($member['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-5">Phone</dt>
                    <dd class="col-sm-7"><?php echo escape($member['phone'] ?: '-'); ?></dd>

                    <dt class="col-sm-5">Gotra</dt>
                    <dd class="col-sm-7"><?php echo escape($member['gotra'] ?: '-'); ?></dd>

                    <dt class="col-sm-5">Date of Birth</dt>
                    <dd class="col-sm-7"><?php echo !empty($member['date_of_birth']) ? escape(date('d M, Y', strtotime($member['date_of_birth']))) : '-'; ?></dd>

                    <dt class="col-sm-5">Seva Start</dt>
                    <dd class="col-sm-7"><?php echo escape(date('d M, Y', strtotime($member['seva_start_date']))); ?></dd>

                    <dt class="col-sm-5">Address</dt>
                    <dd class="col-sm-7"><?php echo $member['address'] ? nl2br(escape($member['address'])) : '-'; ?></dd>

                    <dt class="col-sm-5">Remarks</dt>
                    <dd class="col-sm-7"><?php echo $member['remarks'] ? nl2br(escape($member['remarks'])) : '-'; ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Payment History</h2>
                <a href="<?php echo url('nitya-seva-record-payment') . '?member_id=' . urlencode($member['member_id']); ?>" class="btn btn-sm btn-outline-primary">Record Payment</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payments)): ?>
                    <div class="p-4 text-center text-muted">No payments recorded for this member.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Seva Month</th>
                                    <th>Mode</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo escape(date('d M, Y', strtotime($payment['payment_date']))); ?></td>
                                        <td><?php echo escape(getMonthName((int)$payment['seva_month']) . ' ' . $payment['seva_year']); ?></td>
                                        <td><?php echo escape($payment['payment_mode'] ?: '-'); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($payment['amount_paid']); ?></td>
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

<?php require_once __DIR__ . '/includes/nitya_seva_footer.php'; ?>
