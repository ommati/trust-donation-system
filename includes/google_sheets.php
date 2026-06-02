<?php
// google_sheets.php - lightweight Google Sheets service account integration
// Designed to be non-blocking: failures are returned and logged but do not stop normal app flow.
require_once __DIR__ . '/functions.php';

function gs_log($message)
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $file = $logDir . '/google-sync.log';
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function gs_resolve_credentials_path()
{
    if (!defined('GSHEET_CREDENTIALS_PATH') || !GSHEET_CREDENTIALS_PATH) {
        return false;
    }

    $path = GSHEET_CREDENTIALS_PATH;
    if (file_exists($path)) {
        return $path;
    }

    $isAbsolute = preg_match('#^(?:[A-Za-z]:[\\/]|[\\/]{2}|/)#', $path);
    $baseDir = realpath(__DIR__ . '/../') ?: __DIR__ . '/../';
    $normalizedPath = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, ltrim($path, '/\\'));

    $candidates = [];
    if (!$isAbsolute) {
        $candidates[] = $baseDir . DIRECTORY_SEPARATOR . $normalizedPath;
    } else {
        $candidates[] = $baseDir . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    foreach ($candidates as $candidate) {
        if (file_exists($candidate)) {
            gs_log('Resolved credentials path from ' . $path . ' to ' . $candidate);
            return $candidate;
        }
    }

    return $path;
}

function gs_verify_config()
{
    if (!defined('GSHEET_SPREADSHEET_ID') || !GSHEET_SPREADSHEET_ID) {
        gs_log('Google Sheets Spreadsheet ID is not configured.');
        return false;
    }
    if (!defined('GSHEET_CREDENTIALS_PATH') || !GSHEET_CREDENTIALS_PATH) {
        gs_log('Google Sheets credentials path is not configured.');
        return false;
    }
    if (!defined('GSHEET_SHEET_NAME') || !GSHEET_SHEET_NAME) {
        gs_log('Google Sheets sheet name is not configured.');
        return false;
    }

    $path = gs_resolve_credentials_path();
    if (!file_exists($path)) {
        gs_log('Service account JSON not found at ' . $path);
        $resolved = realpath($path);
        gs_log('Resolved path: ' . ($resolved !== false ? $resolved : '[not resolvable]'));
        return false;
    }
    if ($path !== GSHEET_CREDENTIALS_PATH) {
        define('GSHEET_CREDENTIALS_PATH_RESOLVED', $path);
    }
    return true;
}

function gs_get_access_token()
{
    if (!empty($_SESSION['gs_access_token']) && !empty($_SESSION['gs_access_token_expires']) && $_SESSION['gs_access_token_expires'] > time() + 30) {
        return $_SESSION['gs_access_token'];
    }

    if (!gs_verify_config()) {
        return false;
    }

    $credentialsPath = defined('GSHEET_CREDENTIALS_PATH_RESOLVED') ? GSHEET_CREDENTIALS_PATH_RESOLVED : GSHEET_CREDENTIALS_PATH;
    $json = json_decode(file_get_contents($credentialsPath), true);
    if (!$json) {
        gs_log('Invalid service account JSON at ' . $credentialsPath);
        return false;
    }

    $client_email = $json['client_email'] ?? null;
    $private_key = $json['private_key'] ?? null;
    if (!$client_email || !$private_key) {
        gs_log('Service account JSON missing client_email or private_key');
        return false;
    }

    $now = time();
    $token_url = 'https://oauth2.googleapis.com/token';
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claim = [
        'iss' => $client_email,
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => $token_url,
        'exp' => $now + 3600,
        'iat' => $now,
    ];

    $b64 = function ($v) {
        return rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
    };

    $assertion = $b64($header) . '.' . $b64($claim);
    $signature = '';
    $pkey = openssl_pkey_get_private($private_key);
    if ($pkey === false || !openssl_sign($assertion, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
        gs_log('Failed to sign JWT for service account');
        return false;
    }
    openssl_pkey_free($pkey);
    $assertion .= '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    $post = http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]);
    $response = gs_http_post($token_url, $post, ['Content-Type: application/x-www-form-urlencoded']);
    if ($response === false) {
        gs_log('Failed to exchange JWT for access token');
        return false;
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        gs_log('Invalid JSON response from token endpoint: ' . json_last_error_msg() . ' response=' . $response);
        return false;
    }
    if (empty($data['access_token'])) {
        gs_log('Token response error: ' . $response);
        return false;
    }
    $_SESSION['gs_access_token'] = $data['access_token'];
    $_SESSION['gs_access_token_expires'] = time() + intval($data['expires_in'] ?? 3600);
    return $_SESSION['gs_access_token'];
}

function gs_http_post($url, $body, $headers = [])
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            gs_log('HTTP POST curl error: ' . $err);
            return false;
        }
        return $response;
    }

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $body,
            'timeout' => 20,
        ],
    ];
    $response = @file_get_contents($url, false, stream_context_create($opts));
    if ($response === false) {
        gs_log('HTTP POST file_get_contents failed for ' . $url);
        return false;
    }
    return $response;
}

function gs_sheets_request($method, $path, $body = null)
{
    $token = gs_get_access_token();
    if (!$token) {
        return ['ok' => false, 'message' => 'No access token'];
    }
    $url = 'https://sheets.googleapis.com/v4/spreadsheets/' . rawurlencode(GSHEET_SPREADSHEET_ID) . $path;
    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $token,
    ];
    if ($body !== null) {
        $payload = json_encode($body);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        gs_log('Sheets API curl error: ' . $err);
        return ['ok' => false, 'message' => $err];
    }
    $data = json_decode($resp, true);
    if ($code >= 400) {
        gs_log('Sheets API error ' . $code . ': ' . $resp);
        return ['ok' => false, 'message' => $resp];
    }
    return ['ok' => true, 'data' => $data];
}

function gs_find_row_by_receipt($receipt)
{
    $sheet = GSHEET_SHEET_NAME;
    $res = gs_sheets_request('GET', '/values/' . rawurlencode($sheet . '!A2:A'));
    if (!$res['ok']) {
        return ['ok' => false, 'message' => $res['message'] ?? 'Failed to read sheet'];
    }
    $rows = $res['data']['values'] ?? [];
    foreach ($rows as $i => $r) {
        if (isset($r[0]) && trim((string)$r[0]) === (string)$receipt) {
            $rowNumber = $i + 2; // because values start at A2
            return ['ok' => true, 'row' => $rowNumber];
        }
    }
    return ['ok' => true, 'row' => null];
}

function gs_append_row($values)
{
    $sheet = GSHEET_SHEET_NAME;
    $path = '/values/' . rawurlencode($sheet . '!A1') . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';
    $body = ['values' => [$values]];
    return gs_sheets_request('POST', $path, $body);
}

function gs_update_row($rowNumber, $values)
{
    $sheet = GSHEET_SHEET_NAME;
    $range = $sheet . '!A' . $rowNumber;
    $path = '/values/' . rawurlencode($range) . '?valueInputOption=RAW';
    $body = ['values' => [$values]];
    return gs_sheets_request('PUT', $path, $body);
}

function syncDonationCreate($donation)
{
    ensureDonationSyncSchema();
    try {
        $values = [
            $donation['receipt_number'],
            $donation['donation_date'],
            $donation['donor_name'],
            $donation['mobile'] ?? '',
            $donation['address'] ?? '',
            $donation['amount'],
            $donation['payment_mode'] ?? '',
            $donation['purpose'] ?? '',
            $donation['remarks'] ?? '',
            $donation['status'] ?? 'active',
            $donation['cancel_reason'] ?? '',
            !empty($donation['cancelled_at']) ? $donation['cancelled_at'] : '',
        ];
        $res = gs_append_row($values);
        if (!$res['ok']) {
            gs_log('Create sync failed for ' . $donation['receipt_number'] . ' : ' . ($res['message'] ?? 'unknown'));
            return ['ok' => false, 'message' => $res['message'] ?? 'Sheets append failed'];
        }
        gs_log('Create sync succeeded for ' . $donation['receipt_number']);
        return ['ok' => true];
    } catch (Exception $e) {
        gs_log('Exception in syncDonationCreate: ' . $e->getMessage());
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function syncDonationUpdate($donation)
{
    ensureDonationSyncSchema();
    try {
        $find = gs_find_row_by_receipt($donation['receipt_number']);
        if (!$find['ok']) {
            gs_log('Find failed for update ' . $donation['receipt_number']);
            return ['ok' => false, 'message' => $find['message'] ?? 'Find failed'];
        }
        if ($find['row'] === null) {
            // Not present in sheet - append instead
            return syncDonationCreate($donation);
        }
        $row = $find['row'];
        $values = [
            $donation['receipt_number'],
            $donation['donation_date'],
            $donation['donor_name'],
            $donation['mobile'] ?? '',
            $donation['address'] ?? '',
            $donation['amount'],
            $donation['payment_mode'] ?? '',
            $donation['purpose'] ?? '',
            $donation['remarks'] ?? '',
            $donation['status'] ?? 'active',
            $donation['cancel_reason'] ?? '',
            !empty($donation['cancelled_at']) ? $donation['cancelled_at'] : '',
        ];
        $res = gs_update_row($row, $values);
        if (!$res['ok']) {
            gs_log('Update sync failed for ' . $donation['receipt_number'] . ' : ' . ($res['message'] ?? 'unknown'));
            return ['ok' => false, 'message' => $res['message'] ?? 'Sheets update failed'];
        }
        gs_log('Update sync succeeded for ' . $donation['receipt_number']);
        return ['ok' => true];
    } catch (Exception $e) {
        gs_log('Exception in syncDonationUpdate: ' . $e->getMessage());
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function syncDonationCancel($donation)
{
    // set status to cancelled in the sheet
    $donation['status'] = 'cancelled';
    return syncDonationUpdate($donation);
}

function retryPendingSyncs($limit = 100)
{
    global $pdo;
    ensureDonationSyncSchema();
    $stmt = $pdo->prepare("SELECT * FROM donations WHERE sync_status IN ('pending','failed') ORDER BY id ASC LIMIT :limit");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $results = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];
    foreach ($rows as $r) {
        $results['processed']++;
        $res = syncDonationUpdate($r);
        if ($res['ok']) {
            $update = $pdo->prepare('UPDATE donations SET sync_status = :status, last_sync_at = NOW(), sync_error = NULL WHERE id = :id');
            $update->execute(['status' => 'synced', 'id' => $r['id']]);
            $results['succeeded']++;
        } else {
            $update = $pdo->prepare('UPDATE donations SET sync_status = :status, sync_error = :error WHERE id = :id');
            $update->execute(['status' => 'pending', 'error' => substr($res['message'] ?? 'unknown', 0, 65535), 'id' => $r['id']]);
            $results['failed']++;
            gs_log('Retry failed for ' . $r['receipt_number'] . ': ' . ($res['message'] ?? '')); 
        }
    }
    gs_log('RetryPendingSyncs processed=' . $results['processed'] . ' ok=' . $results['succeeded'] . ' failed=' . $results['failed']);
    return $results;
}
