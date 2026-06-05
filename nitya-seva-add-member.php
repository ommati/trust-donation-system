<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$pageTitle = 'Add Nitya Seva Member';

$nextMemberId = getNextNityaSevaMemberId($pdo);
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $name = sanitizeInput($_POST['name'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $gotra = sanitizeInput($_POST['gotra'] ?? '');
        $dob = sanitizeInput($_POST['date_of_birth'] ?? '');
        $startDate = sanitizeInput($_POST['seva_start_date'] ?? '');
        $monthlyAmount = filter_var($_POST['monthly_seva_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $remarks = sanitizeInput($_POST['remarks'] ?? '');
        $memberId = sanitizeInput($_POST['member_id'] ?? '');

        if (empty($name)) $errors[] = 'Full Name is required.';
        if (empty($startDate)) $errors[] = 'Seva Start Date is required.';
        if ($monthlyAmount <= 0) $errors[] = 'Monthly Seva Amount must be a positive number.';
        if (empty($memberId)) $errors[] = 'Member ID is required.';

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO nitya_seva_members (member_id, name, address, phone, gotra, date_of_birth, seva_start_date, monthly_seva_amount, status, remarks, sync_status) 
                     VALUES (:member_id, :name, :address, :phone, :gotra, :date_of_birth, :seva_start_date, :monthly_seva_amount, :status, :remarks, 'pending')"
                );
                $stmt->execute([
                    ':member_id' => $memberId,
                    ':name' => $name,
                    ':address' => $address,
                    ':phone' => $phone,
                    ':gotra' => $gotra,
                    ':date_of_birth' => !empty($dob) ? $dob : null,
                    ':seva_start_date' => $startDate,
                    ':monthly_seva_amount' => $monthlyAmount,
                    ':status' => $status,
                    ':remarks' => $remarks
                ]);
                $newId = $pdo->lastInsertId();

                recordAuditLog($pdo, $_SESSION['user_id'], 'nitya_seva_member_added', $newId, json_encode(['member_id' => $memberId, 'name' => $name]));

                $stmt = $pdo->prepare('SELECT * FROM nitya_seva_members WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $newId]);
                $memberRow = $stmt->fetch();
                $syncRes = syncNityaSevaMember($memberRow);
                if ($syncRes['ok']) {
                    $update = $pdo->prepare("UPDATE nitya_seva_members SET sync_status = 'synced', last_sync_at = NOW(), sync_error = NULL WHERE id = :id");
                    $update->execute(['id' => $newId]);
                } else {
                    $update = $pdo->prepare("UPDATE nitya_seva_members SET sync_status = 'failed', sync_error = :error WHERE id = :id");
                    $update->execute(['error' => substr($syncRes['message'] ?? 'Sync failed', 0, 65535), 'id' => $newId]);
                }

                $successMessage = "Successfully added new member: $name ($memberId).";
                $nextMemberId = getNextNityaSevaMemberId($pdo);
                $_POST = [];

            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $errors[] = "A member with ID '$memberId' already exists.";
                } else {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $pageTitle; ?></h1>
    <a href="<?php echo url('nitya-seva-members'); ?>" class="btn btn-secondary">View All Members</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?><p class="mb-0"><?php echo escape($error); ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?php echo escape($successMessage); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="<?php echo url('nitya-seva-add-member'); ?>">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
            
            <div class="row">
                <div class="col-md-6 mb-3"><label for="member_id" class="form-label">Member ID</label><input type="text" class="form-control" id="member_id" name="member_id" value="<?php echo escape($nextMemberId); ?>" readonly></div>
                <div class="col-md-6 mb-3"><label for="name" class="form-label">Full Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="name" name="name" value="<?php echo escape($_POST['name'] ?? ''); ?>" required></div>
            </div>

            <div class="mb-3"><label for="address" class="form-label">Address</label><textarea class="form-control" id="address" name="address" rows="3"><?php echo escape($_POST['address'] ?? ''); ?></textarea></div>

            <div class="row">
                <div class="col-md-6 mb-3"><label for="phone" class="form-label">Phone Number</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo escape($_POST['phone'] ?? ''); ?>"></div>
                <div class="col-md-6 mb-3"><label for="gotra" class="form-label">Gotra</label><input type="text" class="form-control" id="gotra" name="gotra" value="<?php echo escape($_POST['gotra'] ?? ''); ?>"></div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3"><label for="date_of_birth" class="form-label">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo escape($_POST['date_of_birth'] ?? ''); ?>"></div>
                <div class="col-md-6 mb-3"><label for="seva_start_date" class="form-label">Seva Start Date <span class="text-danger">*</span></label><input type="date" class="form-control" id="seva_start_date" name="seva_start_date" value="<?php echo escape($_POST['seva_start_date'] ?? date('Y-m-d')); ?>" required></div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="monthly_seva_amount" class="form-label">Monthly Seva Amount <span class="text-danger">*</span></label>
                    <div class="input-group"><span class="input-group-text">₹</span><input type="number" step="0.01" class="form-control" id="monthly_seva_amount" name="monthly_seva_amount" value="<?php echo escape($_POST['monthly_seva_amount'] ?? ''); ?>" required></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?php echo ($_POST['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($_POST['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mb-3"><label for="remarks" class="form-label">Remarks</label><textarea class="form-control" id="remarks" name="remarks" rows="2"><?php echo escape($_POST['remarks'] ?? ''); ?></textarea></div>

            <div class="d-flex justify-content-end"><button type="submit" class="btn btn-primary">Add Member</button></div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/nitya_seva_footer.php';
?>
