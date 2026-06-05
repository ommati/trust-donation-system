<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$pageTitle = 'Nitya Seva Members';

// Get filters from GET request
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 20;

$filters = [
    'search' => $search,
    'status' => $status,
];

$data = getNityaSevaMembers($pdo, $filters, $page, $perPage);
$members = $data['members'];
$total = $data['total'];
$totalPages = ceil($total / $perPage);

require_once __DIR__ . '/includes/nitya_seva_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3"><?php echo $pageTitle; ?> <span class="badge bg-secondary rounded-pill"><?php echo $total; ?></span></h1>
    <a href="<?php echo url('nitya-seva-add-member'); ?>" class="btn btn-primary">Add New Member</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?php echo url('nitya-seva-members'); ?>" class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search by ID, Name, Phone, Gotra..." value="<?php echo escape($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($members)): ?>
            <div class="p-4 text-center text-muted">No members found matching your criteria.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Member ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Monthly Seva</th>
                            <th>Seva Start Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><strong><?php echo escape($member['member_id']); ?></strong></td>
                                <td><a href="<?php echo url('nitya-seva-view-member') . '?id=' . $member['id']; ?>" class="text-decoration-none"><?php echo escape($member['name']); ?></a></td>
                                <td><?php echo escape($member['phone']); ?></td>
                                <td><?php echo formatCurrency($member['monthly_seva_amount']); ?></td>
                                <td><?php echo date('d M, Y', strtotime($member['seva_start_date'])); ?></td>
                                <td>
                                    <?php if ($member['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?php echo url('nitya-seva-view-member') . '?id=' . $member['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="card-footer">
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center mb-0">
                <?php
                $queryString = http_build_query(array_merge($_GET, ['page' => '']));
                ?>
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo $queryString . ($page - 1); ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo $queryString . $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo $queryString . ($page + 1); ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/nitya_seva_footer.php'; ?>
