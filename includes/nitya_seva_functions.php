<?php
// nitya_seva_functions.php - Helper functions for Nitya Seva module
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Generates the next unique Nitya Seva member ID.
 * Format: NS-00001
 *
 * @param PDO $pdo The PDO database connection object.
 * @return string The next available member ID.
 */
function getNextNityaSevaMemberId($pdo)
{
    $stmt = $pdo->prepare("SELECT member_id FROM nitya_seva_members ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $lastId = $stmt->fetchColumn();

    if ($lastId && preg_match('/^NS-(\d+)$/', $lastId, $matches)) {
        $nextNumber = (int)$matches[1] + 1;
    } else {
        $stmt = $pdo->prepare("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nitya_seva_members'");
        $stmt->execute();
        $nextNumber = (int)($stmt->fetchColumn() ?? 1);
        if ($nextNumber === 0) $nextNumber = 1;
    }

    return 'NS-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
}

/**
 * Fetches statistics for the Nitya Seva dashboard.
 *
 * @param PDO $pdo
 * @return array
 */
function getNityaSevaDashboardStats($pdo)
{
    $stats = [
        'totalActiveMembers' => 0,
        'monthlyExpectedCollection' => 0,
        'currentMonthCollection' => 0,
        'totalOutstandingDues' => 0,
        'membersWithDues' => 0,
    ];

    $stmt = $pdo->query("SELECT COUNT(*) as count, SUM(monthly_seva_amount) as total_monthly FROM nitya_seva_members WHERE status = 'active'");
    $activeMembersData = $stmt->fetch();
    if ($activeMembersData) {
        $stats['totalActiveMembers'] = (int)$activeMembersData['count'];
        $stats['monthlyExpectedCollection'] = (float)$activeMembersData['total_monthly'];
    }

    $currentMonth = date('n');
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT SUM(amount_paid) as total FROM nitya_seva_payments WHERE seva_month = :month AND seva_year = :year");
    $stmt->execute([':month' => $currentMonth, ':year' => $currentYear]);
    $stats['currentMonthCollection'] = (float)$stmt->fetchColumn();

    $duesData = calculateAllDues($pdo);
    $stats['totalOutstandingDues'] = $duesData['totalOutstanding'];
    $stats['membersWithDues'] = $duesData['membersWithDues'];

    return $stats;
}

/**
 * Calculates dues for all active members.
 *
 * @param PDO $pdo
 * @return array
 */
function calculateAllDues($pdo)
{
    $totalOutstanding = 0;
    $membersWithDues = 0;

    $stmt = $pdo->query("SELECT id FROM nitya_seva_members WHERE status = 'active'");
    $memberIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($memberIds as $memberId) {
        $dueInfo = calculateMemberDues($pdo, $memberId);
        if ($dueInfo['outstandingDue'] > 0) {
            $totalOutstanding += $dueInfo['outstandingDue'];
            $membersWithDues++;
        }
    }

    return [
        'totalOutstanding' => $totalOutstanding,
        'membersWithDues' => $membersWithDues,
    ];
}

/**
 * Calculates the financial summary for a single member.
 *
 * @param PDO $pdo
 * @param int $memberRowId The internal integer ID of the member.
 * @return array
 */
function calculateMemberDues($pdo, $memberRowId)
{
    $stmt = $pdo->prepare("SELECT member_id, seva_start_date, monthly_seva_amount FROM nitya_seva_members WHERE id = :id");
    $stmt->execute([':id' => $memberRowId]);
    $member = $stmt->fetch();

    if (!$member) {
        return ['totalExpected' => 0, 'totalPaid' => 0, 'outstandingDue' => 0];
    }

    $startDate = new DateTime($member['seva_start_date']);
    $endDate = new DateTime('now');
    $endDate->modify('first day of this month');
    $startDate->modify('first day of this month');

    $totalExpected = 0;
    if ($endDate >= $startDate) {
        $interval = $startDate->diff($endDate);
        $months = $interval->y * 12 + $interval->m + 1;
        $totalExpected = $months * (float)$member['monthly_seva_amount'];
    }

    $stmt = $pdo->prepare("SELECT SUM(amount_paid) as total FROM nitya_seva_payments WHERE member_id = :member_id");
    $stmt->execute([':member_id' => $member['member_id']]);
    $totalPaid = (float)$stmt->fetchColumn();

    $outstandingDue = $totalExpected - $totalPaid;

    return [
        'totalExpected' => $totalExpected,
        'totalPaid' => $totalPaid,
        'outstandingDue' => $outstandingDue > 0 ? $outstandingDue : 0,
    ];
}

function getRecentNityaSevaPayments($pdo, $limit = 5)
{
    $stmt = $pdo->prepare("SELECT p.*, m.name as member_name FROM nitya_seva_payments p JOIN nitya_seva_members m ON p.member_id = m.member_id ORDER BY p.payment_date DESC, p.id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getRecentNityaSevaMembers($pdo, $limit = 5)
{
    $stmt = $pdo->prepare("SELECT * FROM nitya_seva_members ORDER BY id DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getNityaSevaMembers($pdo, $filters = [], $page = 1, $perPage = 20)
{
    $sql = "SELECT SQL_CALC_FOUND_ROWS * FROM nitya_seva_members";
    $where = [];
    $params = [];

    if (!empty($filters['search'])) {
        $searchValue = '%' . $filters['search'] . '%';
        $where[] = "(member_id LIKE ? OR name LIKE ? OR phone LIKE ? OR gotra LIKE ?)";
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }

    if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive'])) {
        $where[] = "status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY id DESC";
    
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT " . intval($perPage) . " OFFSET " . intval($offset);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();

    // Get total count for pagination
    $total = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

    return [
        'members' => $members,
        'total' => (int)$total,
        'page' => $page,
        'perPage' => $perPage,
    ];
}

function getNityaSevaMemberById($pdo, $id)
{
    $stmt = $pdo->prepare("SELECT * FROM nitya_seva_members WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function getNityaSevaPaymentsForMember($pdo, $member_id)
{
    $stmt = $pdo->prepare("SELECT * FROM nitya_seva_payments WHERE member_id = :member_id ORDER BY seva_year DESC, seva_month DESC, payment_date DESC");
    $stmt->execute([':member_id' => $member_id]);
    return $stmt->fetchAll();
}

function getNityaSevaMonthlyPaymentStatus($pdo, $member_id)
{
    // Get member info
    $stmt = $pdo->prepare("SELECT id, seva_start_date, monthly_seva_amount FROM nitya_seva_members WHERE member_id = :member_id");
    $stmt->execute([':member_id' => $member_id]);
    $member = $stmt->fetch();

    if (!$member) {
        return [];
    }

    $monthlyDue = (float)$member['monthly_seva_amount'];

    // Fetch payments in chronological order so we can allocate them to months
    $stmt = $pdo->prepare("SELECT payment_date, amount_paid FROM nitya_seva_payments WHERE member_id = :member_id ORDER BY payment_date ASC, id ASC");
    $stmt->execute([':member_id' => $member_id]);
    $payments = $stmt->fetchAll();

    // Build months list starting from seva_start_date up to current month
    $startDate = new DateTime($member['seva_start_date']);
    $startDate->modify('first day of this month');
    $endDate = new DateTime('now');
    $endDate->modify('first day of this month');

    $months = [];
    $current = clone $startDate;
    while ($current <= $endDate) {
        $months[] = [
            'year' => (int)$current->format('Y'),
            'month' => (int)$current->format('n'),
            'monthName' => getMonthName((int)$current->format('n')),
            'amount_due' => $monthlyDue,
            'amount_paid' => 0.0,
        ];
        $current->modify('+1 month');
    }

    // Allocate payments to months chronologically. Extra payment is kept as advance credit.
    foreach ($payments as $p) {
        $remaining = (float)$p['amount_paid'];
        // Fill existing months from the earliest
        for ($i = 0; $remaining > 0 && $i < count($months); $i++) {
            $need = $months[$i]['amount_due'] - $months[$i]['amount_paid'];
            if ($need <= 0) continue;
            $alloc = min($need, $remaining);
            $months[$i]['amount_paid'] += $alloc;
            $remaining -= $alloc;
        }
        // Any remaining payment is kept as advance credit but not shown as future months
    }

    // Build status array and determine paid flag
    $status = [];
    foreach ($months as $m) {
        $status[] = [
            'month' => $m['month'],
            'year' => $m['year'],
            'monthName' => $m['monthName'],
            'amount_due' => $m['amount_due'],
            'amount_paid' => $m['amount_paid'],
            'isPaid' => $m['amount_paid'] >= $m['amount_due'],
        ];
    }

    // Return in reverse order (most recent first)
    return array_reverse($status);
}


function getMonthName($monthNum) {
    return DateTime::createFromFormat('!m', $monthNum)->format('F');
}

function getAllNityaSevaMembers($pdo, $activeOnly = false)
{
    $sql = 'SELECT * FROM nitya_seva_members';
    if ($activeOnly) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= ' ORDER BY name ASC, member_id ASC';
    return $pdo->query($sql)->fetchAll();
}

function getNityaSevaDuesRows($pdo)
{
    $rows = [];
    foreach (getAllNityaSevaMembers($pdo, true) as $member) {
        $dues = calculateMemberDues($pdo, (int)$member['id']);
        if ($dues['outstandingDue'] > 0) {
            $rows[] = [
                'member' => $member,
                'totalExpected' => $dues['totalExpected'],
                'totalPaid' => $dues['totalPaid'],
                'outstandingDue' => $dues['outstandingDue'],
            ];
        }
    }
    usort($rows, function ($a, $b) {
        return $b['outstandingDue'] <=> $a['outstandingDue'];
    });
    return $rows;
}

function getNityaSevaPaymentReport($pdo, $filters = [])
{
    $sql = "SELECT p.*, m.id AS member_row_id, m.name AS member_name
            FROM nitya_seva_payments p
            LEFT JOIN nitya_seva_members m ON p.member_id = m.member_id";
    $where = [];
    $params = [];

    if (!empty($filters['member_id'])) {
        $where[] = 'p.member_id = :member_id';
        $params['member_id'] = $filters['member_id'];
    }
    if (!empty($filters['from_date'])) {
        $where[] = 'p.payment_date >= :from_date';
        $params['from_date'] = $filters['from_date'];
    }
    if (!empty($filters['to_date'])) {
        $where[] = 'p.payment_date <= :to_date';
        $params['to_date'] = $filters['to_date'];
    }
    if (!empty($filters['seva_month'])) {
        $where[] = 'p.seva_month = :seva_month';
        $params['seva_month'] = (int)$filters['seva_month'];
    }
    if (!empty($filters['seva_year'])) {
        $where[] = 'p.seva_year = :seva_year';
        $params['seva_year'] = (int)$filters['seva_year'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.payment_date DESC, p.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $total = 0;
    foreach ($rows as $row) {
        $total += (float)$row['amount_paid'];
    }

    return ['rows' => $rows, 'total' => $total, 'count' => count($rows)];
}

function getNityaSevaSyncStats($pdo)
{
    $stats = [
        'members' => ['pending' => 0, 'synced' => 0, 'failed' => 0],
        'payments' => ['pending' => 0, 'synced' => 0, 'failed' => 0],
    ];

    foreach ($pdo->query("SELECT sync_status, COUNT(*) AS total FROM nitya_seva_members GROUP BY sync_status")->fetchAll() as $row) {
        $stats['members'][$row['sync_status']] = (int)$row['total'];
    }
    foreach ($pdo->query("SELECT sync_status, COUNT(*) AS total FROM nitya_seva_payments GROUP BY sync_status")->fetchAll() as $row) {
        $stats['payments'][$row['sync_status']] = (int)$row['total'];
    }

    return $stats;
}

function getRecentNityaSevaSyncRows($pdo, $limit = 20)
{
    $stmt = $pdo->prepare(
        "SELECT 'member' AS record_type, id, member_id, name AS title, sync_status, last_sync_at, sync_error, updated_at AS changed_at
         FROM nitya_seva_members
         WHERE sync_status IN ('pending','failed')
         UNION ALL
         SELECT 'payment' AS record_type, p.id, p.member_id, CONCAT(COALESCE(m.name, p.member_id), ' - ', p.payment_date) AS title, p.sync_status, p.last_sync_at, p.sync_error, p.created_at AS changed_at
         FROM nitya_seva_payments p
         LEFT JOIN nitya_seva_members m ON p.member_id = m.member_id
         WHERE p.sync_status IN ('pending','failed')
         ORDER BY changed_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function nityaSevaSheetName($type)
{
    if ($type === 'payments' && defined('GSHEET_NITYA_PAYMENTS_SHEET_NAME')) {
        return GSHEET_NITYA_PAYMENTS_SHEET_NAME;
    }
    if ($type === 'members' && defined('GSHEET_NITYA_MEMBERS_SHEET_NAME')) {
        return GSHEET_NITYA_MEMBERS_SHEET_NAME;
    }
    return $type === 'payments' ? 'Nitya Seva Payments' : 'Nitya Seva Members';
}

function nityaSevaSheetAppendRow($sheet, $values)
{
    $path = '/values/' . rawurlencode($sheet . '!A1') . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';
    return gs_sheets_request('POST', $path, ['values' => [$values]]);
}

function nityaSevaSheetUpdateRow($sheet, $rowNumber, $values)
{
    $path = '/values/' . rawurlencode($sheet . '!A' . $rowNumber) . '?valueInputOption=RAW';
    return gs_sheets_request('PUT', $path, ['values' => [$values]]);
}

function nityaSevaSheetFindRow($sheet, $key)
{
    $res = gs_sheets_request('GET', '/values/' . rawurlencode($sheet . '!A2:A'));
    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['message'] ?? 'Failed to read sheet'];
    }
    foreach (($res['data']['values'] ?? []) as $index => $row) {
        if (isset($row[0]) && trim((string)$row[0]) === (string)$key) {
            return ['ok' => true, 'row' => $index + 2];
        }
    }
    return ['ok' => true, 'row' => null];
}

function syncNityaSevaMember($member)
{
    require_once __DIR__ . '/google_sheets.php';
    if (!gs_verify_config()) {
        return ['ok' => false, 'message' => 'Google Sheets is not configured.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'The PHP cURL extension is required for Google Sheets sync.'];
    }

    $sheet = nityaSevaSheetName('members');
    $values = [
        $member['member_id'],
        $member['name'],
        $member['phone'] ?? '',
        $member['gotra'] ?? '',
        $member['address'] ?? '',
        $member['date_of_birth'] ?? '',
        $member['seva_start_date'],
        $member['monthly_seva_amount'],
        $member['status'],
        $member['remarks'] ?? '',
    ];

    $find = nityaSevaSheetFindRow($sheet, $member['member_id']);
    if (!$find['ok']) {
        return $find;
    }
    return $find['row'] ? nityaSevaSheetUpdateRow($sheet, $find['row'], $values) : nityaSevaSheetAppendRow($sheet, $values);
}

function syncNityaSevaPayment($payment)
{
    require_once __DIR__ . '/google_sheets.php';
    if (!gs_verify_config()) {
        return ['ok' => false, 'message' => 'Google Sheets is not configured.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'The PHP cURL extension is required for Google Sheets sync.'];
    }

    $sheet = nityaSevaSheetName('payments');
    $syncKey = 'NSP-' . $payment['id'];
    $values = [
        $syncKey,
        $payment['member_id'],
        $payment['member_name'] ?? '',
        $payment['payment_date'],
        $payment['seva_month'],
        $payment['seva_year'],
        $payment['amount_paid'],
        $payment['payment_mode'] ?? '',
        $payment['reference_no'] ?? '',
        $payment['remarks'] ?? '',
    ];

    $find = nityaSevaSheetFindRow($sheet, $syncKey);
    if (!$find['ok']) {
        return $find;
    }
    return $find['row'] ? nityaSevaSheetUpdateRow($sheet, $find['row'], $values) : nityaSevaSheetAppendRow($sheet, $values);
}

function retryPendingNityaSevaSyncs($pdo, $limit = 100)
{
    $results = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

    $stmt = $pdo->prepare("SELECT * FROM nitya_seva_members WHERE sync_status IN ('pending','failed') ORDER BY id ASC LIMIT :limit");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    foreach ($stmt->fetchAll() as $member) {
        $results['processed']++;
        $res = syncNityaSevaMember($member);
        if ($res['ok']) {
            $update = $pdo->prepare("UPDATE nitya_seva_members SET sync_status = 'synced', last_sync_at = NOW(), sync_error = NULL WHERE id = :id");
            $update->execute(['id' => $member['id']]);
            $results['succeeded']++;
        } else {
            $update = $pdo->prepare("UPDATE nitya_seva_members SET sync_status = 'failed', sync_error = :error WHERE id = :id");
            $update->execute(['error' => substr($res['message'] ?? 'Sync failed', 0, 65535), 'id' => $member['id']]);
            $results['failed']++;
        }
    }

    $remaining = max(0, (int)$limit - $results['processed']);
    if ($remaining > 0) {
        $stmt = $pdo->prepare(
            "SELECT p.*, m.name AS member_name
             FROM nitya_seva_payments p
             LEFT JOIN nitya_seva_members m ON p.member_id = m.member_id
             WHERE p.sync_status IN ('pending','failed')
             ORDER BY p.id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $remaining, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $payment) {
            $results['processed']++;
            $res = syncNityaSevaPayment($payment);
            if ($res['ok']) {
                $update = $pdo->prepare("UPDATE nitya_seva_payments SET sync_status = 'synced', last_sync_at = NOW(), sync_error = NULL WHERE id = :id");
                $update->execute(['id' => $payment['id']]);
                $results['succeeded']++;
            } else {
                $update = $pdo->prepare("UPDATE nitya_seva_payments SET sync_status = 'failed', sync_error = :error WHERE id = :id");
                $update->execute(['error' => substr($res['message'] ?? 'Sync failed', 0, 65535), 'id' => $payment['id']]);
                $results['failed']++;
            }
        }
    }

    return $results;
}
