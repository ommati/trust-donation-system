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

$pageTitle = 'Edit Nitya Seva Member';
$errors = [];
$success = '';
$values = $member;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request token. Please reload the form.';
    } else {
        $values = [
            'member_id' => $member['member_id'],
            'name' => sanitizeInput($_POST['name'] ?? ''),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'gotra' => sanitizeInput($_POST['gotra'] ?? ''),
            'date_of_birth' => sanitizeInput($_POST['date_of_birth'] ?? ''),
            'seva_start_date' => sanitizeInput($_POST['seva_start_date'] ?? ''),
            'monthly_seva_amount' => trim($_POST['monthly_seva_amount'] ?? ''),
            'status' => in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active',
            'remarks' => sanitizeInput($_POST['remarks'] ?? ''),
        ];

        if ($values['name'] === '') {
            $errors[] = 'Full name is required.';
        }
        if ($values['seva_start_date'] === '') {
            $errors[] = 'Seva start date is required.';
        }
        if ($values['monthly_seva_amount'] === '' || !is_numeric($values['monthly_seva_amount']) || (float)$values['monthly_seva_amount'] <= 0) {
            $errors[] = 'Monthly seva amount must be a positive number.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                "UPDATE nitya_seva_members
                 SET name = :name, address = :address, phone = :phone, gotra = :gotra,
                     date_of_birth = :date_of_birth, seva_start_date = :seva_start_date,
                     monthly_seva_amount = :monthly_seva_amount, status = :status,
                     remarks = :remarks, sync_status = 'pending', sync_error = NULL
                 WHERE id = :id"
            );
            $stmt->execute([
                'name' => $values['name'],
                'address' => $values['address'],
                'phone' => $values['phone'],
                'gotra' => $values['gotra'],
                'date_of_birth' => $values['date_of_birth'] !== '' ? $values['date_of_birth'] : null,
                'seva_start_date' => $values['seva_start_date'],
                'monthly_seva_amount' => number_format((float)$values['monthly_seva_amount'], 2, '.', ''),
                'status' => $values['status'],
                'remarks' => $values['remarks'],
                'id' => $id,
            ]);

            recordAuditLog($pdo, $_SESSION['user_id'] ?? null, 'nitya_seva_member_updated', $id, json_encode([
                'member_id' => $member['member_id'],
                'name' => $values['name'],
            ]));

            $member = getNityaSevaMemberById($pdo, $id);
            $values = $member;
            $sync = syncNityaSevaMember($member);
            if ($sync['ok']) {
                $update = $pdo->prepare("UPDATE nitya_seva_members SET sync_status = 'synced', last_sync_at = NOW(), sync_error = NULL WHERE id = :id");
                $update->execute(['id' => $id]);
            } else {
                $update = $pdo->prepare("UPDATE nitya_seva_members SET sync_status = 'failed', sync_error = :error WHERE id = :id");
                $update->execute(['error' => substr($sync['message'] ?? 'Sync failed', 0, 65535), 'id' => $id]);
            }
            $success = 'Member details updated.';
        }
    }
}

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>
<div class="page-stack">
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Member</h1>
            <p class="page-subtitle"><?php echo escape($member['member_id']); ?> - <?php echo escape($member['name']); ?></p>
        </div>
        <div class="action-group">
            <a href="<?php echo url('nitya-seva-view-member') . '?id=' . urlencode($member['id']); ?>" class="btn btn-outline-secondary">View Member</a>
            <a href="<?php echo url('nitya-seva-members'); ?>" class="btn btn-outline-secondary">All Members</a>
        </div>
    </div>

    <?php foreach ($errors as $error): ?>
        <?php echo showAlert($error, 'danger'); ?>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <?php echo showAlert($success, 'success'); ?>
    <?php endif; ?>

    <div class="card section-card">
        <div class="card-body">
            <form method="post" action="<?php echo url('nitya-seva-edit-member') . '?id=' . urlencode($id); ?>">
                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                <div class="row g-3 g-lg-4">
                    <div class="col-md-6">
                        <label class="form-label" for="member_id">Member ID</label>
                        <input type="text" class="form-control" id="member_id" value="<?php echo escape($member['member_id']); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="name">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo escape($values['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo escape($values['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="gotra">Gotra</label>
                        <input type="text" class="form-control" id="gotra" name="gotra" value="<?php echo escape($values['gotra'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="date_of_birth">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo escape($values['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="seva_start_date">Seva Start Date</label>
                        <input type="date" class="form-control" id="seva_start_date" name="seva_start_date" value="<?php echo escape($values['seva_start_date'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="monthly_seva_amount">Monthly Seva Amount</label>
                        <input type="number" step="0.01" class="form-control" id="monthly_seva_amount" name="monthly_seva_amount" value="<?php echo escape($values['monthly_seva_amount'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo ($values['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($values['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo escape($values['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2"><?php echo escape($values['remarks'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/nitya_seva_footer.php'; ?>
