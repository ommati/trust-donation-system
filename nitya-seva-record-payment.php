<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nitya_seva_functions.php';

requireLogin();
ensureNityaSevaSchema();

$selectedMemberId = sanitizeInput($_GET['member_id'] ?? '');
$redirectUrl = 'nitya-seva-members?tab=record';
if ($selectedMemberId !== '') {
    $redirectUrl .= '&member_id=' . urlencode($selectedMemberId);
}
redirect($redirectUrl);

