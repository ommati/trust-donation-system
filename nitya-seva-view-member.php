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

$tab = trim($_GET['tab'] ?? 'view');
$tab = in_array($tab, ['view', 'record'], true) ? $tab : 'view';
$mode = trim($_GET['mode'] ?? 'view');
$mode = $mode === 'edit' ? 'edit' : 'view';

$errors = [];
$success = '';

$paymentValues = [
    'payment_date' => date('Y-m-d'),
    'amount_paid' => '',
    'payment_mode' => 'Cash',
    'remarks' => '',
];

$editValues = $member;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request token. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'edit_member') {
            $tab = 'view';
            $mode = 'edit';
            $editValues = [
                'name' => sanitizeInput($_POST['name'] ?? ''),
                'phone' => sanitizeInput($_POST['phone'] ?? ''),
                'gotra' => sanitizeInput($_POST['gotra'] ?? ''),
                'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
                'seva_start_date' => sanitizeInput($_POST['seva_start_date'] ?? ''),
                'address' => sanitizeInput($_POST['address'] ?? ''),
                'remarks' => sanitizeInput($_POST['remarks'] ?? ''),
                'monthly_seva_amount' => trim($_POST['monthly_seva_amount'] ?? ''),
                'status' => in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
            ];

            if ($editValues['name'] === '') {
                $errors[] = 'Full name is required.';
            }
            if ($editValues['seva_start_date'] === '') {
                $errors[] = 'Seva start date is required.';
            }
            if ($editValues['monthly_seva_amount'] === '' || !is_numeric($editValues['monthly_seva_amount']) || (float)$editValues['monthly_seva_amount'] <= 0) {
                $errors[] = 'Monthly seva amount must be a positive number.';
            }

            if (!$errors) {
                $stmt = $pdo->prepare(
                    "UPDATE nitya_seva_members
                     SET name = :name,
                         phone = :phone,
                         gotra = :gotra,
                         date_of_birth = :date_of_birth,
                         seva_start_date = :seva_start_date,
                         address = :address,
                         remarks = :remarks,
                         monthly_seva_amount = :monthly_seva_amount,
                         status = :status,
                         sync_status = 'pending',
                         sync_error = NULL
                     WHERE id = :id"
                );
                $stmt->execute([
                    'name' => $editValues['name'],
                    'phone' => $editValues['phone'],
                    'gotra' => $editValues['gotra'],
                    'date_of_birth' => $editValues['date_of_birth'] !== '' ? $editValues['date_of_birth'] : null,
                    'seva_start_date' => $editValues['seva_start_date'],
                    'address' => $editValues['address'],
                    'remarks' => $editValues['remarks'],
                    'monthly_seva_amount' => number_format((float)$editValues['monthly_seva_amount'], 2, '.', ''),
                    'status' => $editValues['status'],
                    'id' => $id,
                ]);

                recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'nitya_seva_member_updated', $id, json_encode([
                    'member_id' => $member['member_id'],
                    'name' => $editValues['name'],
                ]));

                $member = getNityaSevaMemberById($pdo, $id);
                $editValues = $member;
                $mode = 'view';
                $success = 'Member details updated successfully.';
            }
        } elseif ($action === 'record_payment') {
            $tab = 'record';
            $paymentValues = [
                'payment_date' => sanitizeInput($_POST['payment_date'] ?? ''),
                'amount_paid' => trim($_POST['amount_paid'] ?? ''),
                'payment_mode' => sanitizeInput($_POST['payment_mode'] ?? 'Cash'),
                'remarks' => sanitizeInput($_POST['remarks'] ?? ''),
            ];

            if ($paymentValues['payment_date'] === '') {
                $errors[] = 'Payment date is required.';
            }
            if ($paymentValues['amount_paid'] === '' || !is_numeric($paymentValues['amount_paid']) || (float)$paymentValues['amount_paid'] <= 0) {
                $errors[] = 'Please enter a valid amount.';
            }

            if (!$errors) {
                // Derive seva month/year from payment date
                try {
                    $dt = new DateTime($paymentValues['payment_date']);
                    $seva_month = (int)$dt->format('n');
                    $seva_year = (int)$dt->format('Y');
                } catch (Exception $e) {
                    $seva_month = (int)date('n');
                    $seva_year = (int)date('Y');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO nitya_seva_payments
                     (member_id, payment_date, seva_month, seva_year, amount_paid, payment_mode, remarks, created_by, sync_status)
                     VALUES
                     (:member_id, :payment_date, :seva_month, :seva_year, :amount_paid, :payment_mode, :remarks, :created_by, 'pending')"
                );
                $stmt->execute([
                    'member_id' => $member['member_id'],
                    'payment_date' => $paymentValues['payment_date'],
                    'seva_month' => $seva_month,
                    'seva_year' => $seva_year,
                    'amount_paid' => number_format((float)$paymentValues['amount_paid'], 2, '.', ''),
                    'payment_mode' => $paymentValues['payment_mode'],
                    'remarks' => $paymentValues['remarks'],
                    'created_by' => $_SESSION['user_id'] ?? null,
                ]);
                $paymentId = (int)$pdo->lastInsertId();

                recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'nitya_seva_payment_recorded', $paymentId, json_encode([
                    'member_id' => $member['member_id'],
                    'amount_paid' => $paymentValues['amount_paid'],
                    'seva_month' => $seva_month,
                    'seva_year' => $seva_year,
                ]));

                $stmt = $pdo->prepare(
                    "SELECT p.*, m.name AS member_name
                     FROM nitya_seva_payments p
                     LEFT JOIN nitya_seva_members m ON p.member_id = m.member_id
                     WHERE p.id = :id"
                );
                $stmt->execute(['id' => $paymentId]);
                $payment = $stmt->fetch();
                $sync = syncNityaSevaPayment($payment);
                if ($sync['ok']) {
                    $update = $pdo->prepare("UPDATE nitya_seva_payments SET sync_status = 'synced', last_sync_at = NOW(), sync_error = NULL WHERE id = :id");
                    $update->execute(['id' => $paymentId]);
                } else {
                    $update = $pdo->prepare("UPDATE nitya_seva_payments SET sync_status = 'failed', sync_error = :error WHERE id = :id");
                    $update->execute(['error' => substr($sync['message'] ?? 'Sync failed', 0, 65535), 'id' => $paymentId]);
                }

                $success = 'Payment recorded successfully.';
                $paymentValues = [
                    'payment_date' => date('Y-m-d'),
                    'amount_paid' => '',
                    'payment_mode' => 'Cash',
                    'remarks' => '',
                ];
                $payments = getNityaSevaPaymentsForMember($pdo, $member['member_id']);
            }
        } elseif ($action === 'edit_payment') {
            $payment_id = (int)($_POST['payment_id'] ?? 0);
            $verification_code = sanitizeInput($_POST['verification_code'] ?? '');
            if (!$payment_id) {
                $errors[] = 'Invalid payment ID.';
            } elseif ($verification_code !== '4039') {
                $errors[] = 'Invalid verification code. Please enter the correct 4-digit code.';
            } else {
                $paymentValues = [
                    'payment_date' => sanitizeInput($_POST['payment_date'] ?? ''),
                    'amount_paid' => trim($_POST['amount_paid'] ?? ''),
                    'payment_mode' => sanitizeInput($_POST['payment_mode'] ?? 'Cash'),
                    'remarks' => sanitizeInput($_POST['remarks'] ?? ''),
                ];

                if ($paymentValues['payment_date'] === '') {
                    $errors[] = 'Payment date is required.';
                }
                if ($paymentValues['amount_paid'] === '' || !is_numeric($paymentValues['amount_paid']) || (float)$paymentValues['amount_paid'] <= 0) {
                    $errors[] = 'Please enter a valid amount.';
                }

                if (!$errors) {
                    // Derive seva month/year from payment date
                    try {
                        $dt = new DateTime($paymentValues['payment_date']);
                        $seva_month = (int)$dt->format('n');
                        $seva_year = (int)$dt->format('Y');
                    } catch (Exception $e) {
                        $seva_month = (int)date('n');
                        $seva_year = (int)date('Y');
                    }

                    $stmt = $pdo->prepare(
                        "UPDATE nitya_seva_payments
                         SET payment_date = :payment_date,
                             seva_month = :seva_month,
                             seva_year = :seva_year,
                             amount_paid = :amount_paid,
                             payment_mode = :payment_mode,
                             remarks = :remarks,
                             sync_status = 'pending',
                             sync_error = NULL
                         WHERE id = :id"
                    );
                    $stmt->execute([
                        'payment_date' => $paymentValues['payment_date'],
                        'seva_month' => $seva_month,
                        'seva_year' => $seva_year,
                        'amount_paid' => number_format((float)$paymentValues['amount_paid'], 2, '.', ''),
                        'payment_mode' => $paymentValues['payment_mode'],
                        'remarks' => $paymentValues['remarks'],
                        'id' => $payment_id,
                    ]);

                    recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'nitya_seva_payment_updated', $payment_id, json_encode([
                        'member_id' => $member['member_id'],
                        'amount_paid' => $paymentValues['amount_paid'],
                    ]));

                    $success = 'Payment updated successfully.';
                    $tab = 'record';
                    $paymentValues = [
                        'payment_date' => date('Y-m-d'),
                        'amount_paid' => '',
                        'payment_mode' => 'Cash',
                        'remarks' => '',
                    ];
                    $payments = getNityaSevaPaymentsForMember($pdo, $member['member_id']);
                }
            }
        } elseif ($action === 'delete_payment') {
            $payment_id = (int)($_POST['payment_id'] ?? 0);
            $verification_code = sanitizeInput($_POST['verification_code'] ?? '');
            if (!$payment_id) {
                $errors[] = 'Invalid payment ID.';
            } elseif ($verification_code !== '4039') {
                $errors[] = 'Invalid verification code. Please enter the correct 4-digit code.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM nitya_seva_payments WHERE id = :id");
                $stmt->execute(['id' => $payment_id]);

                recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'nitya_seva_payment_deleted', $payment_id, json_encode([
                    'member_id' => $member['member_id'],
                ]));

                $success = 'Payment deleted successfully.';
                $tab = 'record';
                $payments = getNityaSevaPaymentsForMember($pdo, $member['member_id']);
            }
        }
    }
}

$dues = calculateMemberDues($pdo, $id);
$payments = $payments ?? getNityaSevaPaymentsForMember($pdo, $member['member_id']);
$monthlyStatus = getNityaSevaMonthlyPaymentStatus($pdo, $member['member_id']);
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
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <?php echo showAlert($error, 'danger'); ?>
<?php endforeach; ?>
<?php if ($success): ?>
    <?php echo showAlert($success, 'success'); ?>
<?php endif; ?>

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

<div class="card mb-4">
    <div class="card-body p-0">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'view' ? 'active' : ''; ?>" href="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($id) . '&tab=view'; ?>">View Details</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $tab === 'record' ? 'active' : ''; ?>" href="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($id) . '&tab=record'; ?>">Entry</a>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content">
    <div class="tab-pane fade <?php echo $tab === 'view' ? 'show active' : ''; ?>" id="view-tab">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">Member Details</h2>
                        <?php if ($tab === 'view'): ?>
                            <a href="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($id) . '&tab=view&mode=edit'; ?>" class="btn btn-sm btn-primary">Edit</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($mode === 'edit'): ?>
                            <form method="post" action="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($id) . '&tab=view&mode=edit'; ?>">
                                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                                <input type="hidden" name="action" value="edit_member">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label" for="name">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo escape($editValues['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="phone">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo escape($editValues['phone']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="gotra">Gotra</label>
                                        <input type="text" class="form-control" id="gotra" name="gotra" value="<?php echo escape($editValues['gotra']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo escape($editValues['date_of_birth']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="seva_start_date">Seva Start Date</label>
                                        <input type="date" class="form-control" id="seva_start_date" name="seva_start_date" value="<?php echo escape($editValues['seva_start_date']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="monthly_seva_amount">Monthly Seva Amount</label>
                                        <input type="number" step="0.01" class="form-control" id="monthly_seva_amount" name="monthly_seva_amount" value="<?php echo escape($editValues['monthly_seva_amount']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="status">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php echo $editValues['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $editValues['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="address">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo escape($editValues['address']); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="remarks">Remarks</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="2"><?php echo escape($editValues['remarks']); ?></textarea>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <a href="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($id) . '&tab=view'; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                                </div>
                            </form>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Recent Payment History</h2>
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
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo escape(date('d M, Y', strtotime($payment['payment_date']))); ?></td>
                                                <td><?php echo escape(getMonthName((int)$payment['seva_month']) . ' ' . $payment['seva_year']); ?></td>
                                                <td><?php echo escape($payment['payment_mode'] ?: '-'); ?></td>
                                                <td class="text-end"><?php echo formatCurrency($payment['amount_paid']); ?></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPaymentModal" onclick="populateEditPaymentForm(<?php echo $payment['id']; ?>, '<?php echo $payment['payment_date']; ?>', <?php echo $payment['amount_paid']; ?>, '<?php echo escape($payment['payment_mode']); ?>', '<?php echo escape(addslashes($payment['remarks'])); ?>')">Edit</button>
                                                    <form method="post" style="display:inline;" onsubmit="return deletePaymentWithVerification(this, <?php echo $payment['id']; ?>);">
                                                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                                                        <input type="hidden" name="action" value="delete_payment">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="verification_code" id="delete_verification_code_<?php echo $payment['id']; ?>" value="">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($monthlyStatus)): ?>
                <div class="card mt-3">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Monthly Payment Status</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th>Status</th>
                                        <th class="text-end">Amount Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyStatus as $statusRow): ?>
                                        <tr>
                                            <td><?php echo escape($statusRow['monthName'] . ' ' . $statusRow['year']); ?></td>
                                            <td>
                                                <?php if ($statusRow['isPaid']): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Due</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php $remaining = $statusRow['amount_due'] - ($statusRow['amount_paid'] ?? 0); ?>
                                            <td class="text-end"><?php echo $remaining > 0 ? formatCurrency($remaining) : formatCurrency(0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?php echo $tab === 'record' ? 'show active' : ''; ?>" id="record-tab">
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Record Payment</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($id) . '&tab=record'; ?>">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                            <input type="hidden" name="action" value="record_payment">
                            <div class="mb-3">
                                <label class="form-label" for="payment_date">Payment Date</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo escape($paymentValues['payment_date']); ?>" required>
                            </div>
                            <!-- Seva month/year are derived from payment date; fields removed -->
                            <div class="mb-3 mt-3">
                                <label class="form-label" for="amount_paid">Amount Paid</label>
                                <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" value="<?php echo escape($paymentValues['amount_paid']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="payment_mode">Payment Mode</label>
                                <select class="form-select" id="payment_mode" name="payment_mode">
                                    <?php foreach (getPaymentModes() as $mode): ?>
                                        <option value="<?php echo escape($mode); ?>" <?php echo $paymentValues['payment_mode'] === $mode ? 'selected' : ''; ?>><?php echo escape($mode); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Reference number removed; not required -->
                            <div class="mb-3">
                                <label class="form-label" for="remarks">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo escape($paymentValues['remarks']); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Record Payment</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Payment History</h2>
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
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?php echo escape(date('d M, Y', strtotime($payment['payment_date']))); ?></td>
                                                <td><?php echo escape(getMonthName((int)$payment['seva_month']) . ' ' . $payment['seva_year']); ?></td>
                                                <td><?php echo escape($payment['payment_mode'] ?: '-'); ?></td>
                                                <td class="text-end"><?php echo formatCurrency($payment['amount_paid']); ?></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPaymentModal" onclick="populateEditPaymentForm(<?php echo $payment['id']; ?>, '<?php echo $payment['payment_date']; ?>', <?php echo $payment['amount_paid']; ?>, '<?php echo escape($payment['payment_mode']); ?>', '<?php echo escape(addslashes($payment['remarks'])); ?>')">Edit</button>
                                                    <form method="post" style="display:inline;" onsubmit="return deletePaymentWithVerification(this, <?php echo $payment['id']; ?>);">
                                                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                                                        <input type="hidden" name="action" value="delete_payment">
                                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                                        <input type="hidden" name="verification_code" id="delete_verification_code_<?php echo $payment['id']; ?>" value="">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($monthlyStatus)): ?>
                <div class="card mt-3">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Monthly Payment Status</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Month</th>
                                        <th>Status</th>
                                        <th class="text-end">Amount Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyStatus as $statusRow): ?>
                                        <tr>
                                            <td><?php echo escape($statusRow['monthName'] . ' ' . $statusRow['year']); ?></td>
                                            <td>
                                                <?php if ($statusRow['isPaid']): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Due</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?php echo formatCurrency($statusRow['amount_due']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($id) . '&tab=record'; ?>">
                <div class="modal-body">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="edit_payment">
                    <input type="hidden" id="edit_payment_id" name="payment_id" value="">
                    <div class="mb-3">
                        <label class="form-label" for="edit_payment_date">Payment Date</label>
                        <input type="date" class="form-control" id="edit_payment_date" name="payment_date" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_amount_paid">Amount Paid</label>
                        <input type="number" step="0.01" class="form-control" id="edit_amount_paid" name="amount_paid" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_payment_mode">Payment Mode</label>
                        <select class="form-select" id="edit_payment_mode" name="payment_mode">
                            <?php foreach (getPaymentModes() as $mode): ?>
                                <option value="<?php echo escape($mode); ?>"><?php echo escape($mode); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_remarks">Remarks</label>
                        <textarea class="form-control" id="edit_remarks" name="remarks" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_verification_code">Verification Code</label>
                        <input type="text" class="form-control" id="edit_verification_code" name="verification_code" placeholder="Enter 4-digit code" maxlength="4" pattern="[0-9]{4}" required>
                        <small class="text-muted">Enter the 4-digit verification code to confirm edit</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function populateEditPaymentForm(paymentId, paymentDate, amountPaid, paymentMode, remarks) {
    document.getElementById('edit_payment_id').value = paymentId;
    document.getElementById('edit_payment_date').value = paymentDate;
    document.getElementById('edit_amount_paid').value = amountPaid;
    document.getElementById('edit_payment_mode').value = paymentMode;
    document.getElementById('edit_remarks').value = remarks;
    document.getElementById('edit_verification_code').value = ''; // Clear the verification code field
}

function deletePaymentWithVerification(form, paymentId) {
    const code = prompt('Enter 4-digit verification code to confirm deletion:');
    if (code === null) {
        return false; // User cancelled
    }
    
    if (code !== '4039') {
        alert('Invalid verification code. Deletion cancelled.');
        return false;
    }
    
    document.getElementById('delete_verification_code_' + paymentId).value = code;
    form.submit();
    return false;
}
</script>

<?php require_once __DIR__ . '/includes/nitya_seva_footer.php'; ?>
