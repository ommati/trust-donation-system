<?php
// nitya_seva_functions.php - Helper functions for Nitya Seva module
require_once __DIR__ . '/db.php';

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
        $where[] = "(member_id LIKE :search OR name LIKE :search OR phone LIKE :search OR gotra LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive'])) {
        $where[] = "status = :status";
        $params[':status'] = $filters['status'];
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY id DESC";
    
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
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

function getMonthName($monthNum) {
    return DateTime::createFromFormat('!m', $monthNum)->format('F');
}