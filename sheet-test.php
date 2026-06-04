<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/google_sheets.php';
requireLogin();

$errors = [];
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        if (!gs_verify_config()) {
            $errors[] = 'Google Sheets configuration is incomplete. Check logs for details.';
        } else {
            $results[] = 'GSHEET_SPREADSHEET_ID=' . GSHEET_SPREADSHEET_ID;
            $results[] = 'GSHEET_CREDENTIALS_PATH=' . GSHEET_CREDENTIALS_PATH;
            if (defined('GSHEET_CREDENTIALS_PATH_RESOLVED')) {
                $results[] = 'GSHEET_CREDENTIALS_PATH_RESOLVED=' . GSHEET_CREDENTIALS_PATH_RESOLVED;
            }
            $results[] = 'GSHEET_SHEET_NAME=' . GSHEET_SHEET_NAME;
            $token = gs_get_access_token();
            if ($token) {
                $results[] = 'Access token acquired successfully.';
                $response = gs_sheets_request('GET', '/values/' . rawurlencode(GSHEET_SHEET_NAME . '!A1:J1'));
                if ($response['ok']) {
                    $results[] = 'Sheet read successful.';
                    $rows = $response['data']['values'] ?? [];
                    $results[] = 'Header row: ' . json_encode($rows[0] ?? []);
                } else {
                    $errors[] = 'Sheets API error: ' . ($response['message'] ?? 'unknown');
                }
            } else {
                $errors[] = 'Failed to get access token. Check logs/google-sync.log for details.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="card section-card">
            <div class="card-header">
                <h1 class="section-title">Google Sheets Connection Test</h1>
            </div>
            <div class="card-body">
                <?php foreach ($errors as $error): ?>
                    <?php echo showAlert($error, 'danger'); ?>
                <?php endforeach; ?>
                <?php foreach ($results as $result): ?>
                    <?php echo showAlert($result, 'success'); ?>
                <?php endforeach; ?>
                <form method="post">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo escape(getCsrfToken()); ?>">
                    <div class="d-grid d-sm-block">
                        <button type="submit" class="btn btn-primary">Run Sheets Test</button>
                    </div>
                </form>
                <p class="mt-3 text-muted">If this test fails, check <code>logs/google-sync.log</code> and verify your service account JSON and spreadsheet sharing.</p>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
