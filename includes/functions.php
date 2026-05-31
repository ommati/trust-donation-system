<?php
// functions.php - Reusable helper functions
require_once __DIR__ . '/config.php';

function escape($value)
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect($url)
{
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
