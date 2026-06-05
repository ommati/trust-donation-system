<?php
// functions.php - Reusable helper functions
require_once __DIR__ . '/config.php';

function escape($value)
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function basePath()
{
    if (!defined('BASE_PATH')) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = '/' . trim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($basePath === '/') {
            $basePath = '';
        }
        define('BASE_PATH', $basePath);
    }
    return BASE_PATH;
}

function url($path = '')
{
    $path = trim((string)$path);
    $base = basePath();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    $suffix = '';
    if (preg_match('/[?#]/', $path, $match, PREG_OFFSET_CAPTURE)) {
        $suffixPosition = $match[0][1];
        $suffix = substr($path, $suffixPosition);
        $path = substr($path, 0, $suffixPosition);
    }

    $path = trim($path, '/');
    if ($path !== '' && !preg_match('/\.[a-z0-9]+$/i', $path)) {
        // This line ensures all links correctly point to .php files.
        // It is required for the site to work without URL rewriting.
        $path .= '.php'; // DO NOT REMOVE OR COMMENT OUT THIS LINE.
    }

    return $base . '/' . $path . $suffix;
}

function redirect($url)
{
    if (!preg_match('#^(?:https?://|/|//|mailto:|javascript:)#i', $url)) {
        $url = url($url);
    }
    header('Location: ' . $url);
    exit;
}

function sanitizeInput($value)
{
    if ($value === null) {
        return '';
    }
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
    return trim($value);
}

function getCsrfToken()
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(24));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCsrfToken($token)
{
    return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token ?? '');
}

function showAlert($message, $type = 'success')
{
    return '<div class="alert alert-' . escape($type) . ' alert-dismissible fade show" role="alert">' . escape($message) .
        '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}

function formatCurrency($amount)
{
    return '₹ ' . number_format((float)$amount, 2);
}

function generateReceiptNumberFromId($id)
{
    return RECEIPT_PREFIX . str_pad(intval($id), 4, '0', STR_PAD_LEFT);
}

function getNextReceiptNumber($pdo)
{
    $stmt = $pdo->prepare("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donations'");
    $stmt->execute();
    $row = $stmt->fetch();
    $next = intval($row['AUTO_INCREMENT'] ?? 1);
    return generateReceiptNumberFromId($next);
}

function amountToWords($number)
{
    $words = [];
    $number = round($number, 2);
    $whole = floor($number);
    $fraction = round(($number - $whole) * 100);

    $units = [0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen',
        17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'];
    $tens = [2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'];
    $scales = ['', 'Thousand', 'Lakh', 'Crore'];

    if ($whole == 0) {
        $words[] = 'Zero';
    }

    $divider = 1000;
    $chunks = [];
    $dividers = [100, 1000, 100000, 10000000];

    while ($whole > 0) {
        if (count($chunks) == 0) {
            $chunks[] = $whole % 1000;
            $whole = intval($whole / 1000);
        } else {
            $chunks[] = $whole % 100;
            $whole = intval($whole / 100);
        }
    }

    for ($i = count($chunks) - 1; $i >= 0; $i--) {
        $chunk = $chunks[$i];
        if ($chunk === 0) {
            continue;
        }
        $hundreds = intval($chunk / 100);
        $rest = $chunk % 100;
        if ($hundreds) {
            $words[] = $units[$hundreds] . ' Hundred';
        }
        if ($rest) {
            if ($rest < 20) {
                $words[] = $units[$rest];
            } else {
                $words[] = $tens[intval($rest / 10)];
                if ($rest % 10) {
                    $words[] = $units[$rest % 10];
                }
            }
        }
        if (!empty($scales[$i])) {
            $words[] = $scales[$i];
        }
    }

    $result = implode(' ', $words);
    $result = preg_replace('/\s+/', ' ', trim($result));
    $result .= ' Rupees';
    if ($fraction > 0) {
        $result .= ' and ' . ($fraction < 20 ? $units[$fraction] : $tens[intval($fraction / 10)] . ($fraction % 10 ? ' ' . $units[$fraction % 10] : '')) . ' Paise';
    }
    $result .= ' Only';
    return $result;
}

function getPaymentModes()
{
    return unserialize(DEFAULT_PAYMENT_MODES, ['allowed_classes' => false]);
}

function recordAuditLog($pdo, $userId, $action, $donationId = null, $details = null)
{
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, donation_id, details, created_at) VALUES (:user_id, :action, :donation_id, :details, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'donation_id' => $donationId,
            'details' => $details,
        ]);
    } catch (PDOException $e) {
        // ignore logging failures
    }
}

function ensureDonationSyncSchema()
{
    global $pdo;
    try {
        $cols = [
            "ALTER TABLE donations ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending'",
            "ALTER TABLE donations ADD COLUMN last_sync_at DATETIME NULL",
            "ALTER TABLE donations ADD COLUMN sync_error TEXT NULL",
            "ALTER TABLE donations ADD COLUMN status ENUM('active','cancelled') NOT NULL DEFAULT 'active'",
            "ALTER TABLE donations ADD COLUMN cancel_reason TEXT NULL",
            "ALTER TABLE donations ADD COLUMN cancelled_at DATETIME NULL",
            "ALTER TABLE donations ADD COLUMN cancelled_by INT NULL",
        ];
        foreach ($cols as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // ignore existing columns or permission issues
            }
        }
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS audit_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    action VARCHAR(100) NOT NULL,
                    donation_id INT NULL,
                    details TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_audit_user_id (user_id),
                    INDEX idx_audit_donation_id (donation_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
            );
        } catch (PDOException $e) {
            // ignore existing table or permission issues
        }
    } catch (Exception $e) {
        // ignore
    }
}

function ensureNityaSevaSchema()
{
    global $pdo;
    try {
        // Create nitya_seva_members table
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `nitya_seva_members` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `member_id` varchar(20) NOT NULL,
                `name` varchar(255) NOT NULL,
                `address` text,
                `phone` varchar(20) DEFAULT NULL,
                `gotra` varchar(100) DEFAULT NULL,
                `date_of_birth` date DEFAULT NULL,
                `seva_start_date` date NOT NULL,
                `monthly_seva_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
                `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                `remarks` text,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `sync_status` enum('pending','synced','failed') NOT NULL DEFAULT 'pending',
                `last_sync_at` datetime DEFAULT NULL,
                `sync_error` text,
                PRIMARY KEY (`id`),
                UNIQUE KEY `member_id` (`member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );

        // Create nitya_seva_payments table
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `nitya_seva_payments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `member_id` varchar(20) NOT NULL,
                `payment_date` date NOT NULL,
                `seva_month` int(2) NOT NULL,
                `seva_year` int(4) NOT NULL,
                `amount_paid` decimal(10,2) NOT NULL,
                `payment_mode` varchar(50) DEFAULT NULL,
                `reference_no` varchar(100) DEFAULT NULL,
                `remarks` text,
                `created_by` int(11) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `sync_status` enum('pending','synced','failed') NOT NULL DEFAULT 'pending',
                `last_sync_at` datetime DEFAULT NULL,
                `sync_error` text,
                PRIMARY KEY (`id`),
                KEY `idx_member_id` (`member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        );

    } catch (Exception $e) {
        // ignore schema creation failures
    }
}
