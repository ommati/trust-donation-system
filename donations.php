<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$searchDonor = sanitizeInput($_GET['donor_name'] ?? '');
$searchReceipt = sanitizeInput($_GET['receipt_number'] ?? '');
$filterDate = $_GET['donation_date'] ?? '';
$params = [];
$where = [];

if ($searchDonor !== '') {
    $where[] = 'donor_name LIKE :donor_name';
    $params['donor_name'] = '%' . $searchDonor . '%';
}
if ($searchReceipt !== '') {
    $where[] = 'receipt_number LIKE :receipt_number';
    $params['receipt_number'] = '%' . $searchReceipt . '%';
}
if ($filterDate !== '') {
    $where[] = 'donation_date = :donation_date';
    $params['donation_date'] = $filterDate;
}

$sql = 'SELECT id, receipt_number, donation_date, donor_name, amount, payment_mode FROM donations';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY donation_date DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h1 class="h3 mb-0">Donations</h1>
                <p class="text-muted mb-0">Search, edit and manage donation receipts.</p>
            </div>
            <a href="add-donation.php" class="btn btn-success">New Donation</a>
        </div>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="get" action="donations.php" class="row gy-3 gx-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Donor Name</label>
                        <input type="search" class="form-control" name="donor_name" value="<?php echo escape($searchDonor); ?>" placeholder="Search by donor name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Receipt Number</label>
                        <input type="search" class="form-control" name="receipt_number" value="<?php echo escape($searchReceipt); ?>" placeholder="Search by receipt">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="donation_date" value="<?php echo escape($filterDate); ?>">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Donation Records</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($donations)): ?>
                    <div class="p-4 text-center text-muted">No donations found for the selected filters.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                            <tr>
                                <th>Receipt</th>
                                <th>Date</th>
                                <th>Donor Name</th>
                                <th>Amount</th>
                                <th>Payment Mode</th>
                                <th class="text-end">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($donations as $donation): ?>
                                <tr>
                                    <td><?php echo escape($donation['receipt_number']); ?></td>
                                    <td><?php echo escape($donation['donation_date']); ?></td>
                                    <td><?php echo escape($donation['donor_name']); ?></td>
                                    <td><?php echo formatCurrency($donation['amount']); ?></td>
                                    <td><?php echo escape($donation['payment_mode']); ?></td>
                                    <td class="text-end">
                                        <a href="view-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                        <a href="edit-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="delete-donation.php?id=<?php echo $donation['id']; ?>" class="btn btn-sm btn-outline-danger">Delete</a>
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
