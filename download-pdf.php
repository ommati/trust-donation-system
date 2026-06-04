<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';
requireLogin();
session_write_close();

function pdfEscape($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pdfClean($value)
{
    $value = str_replace(["\r", "\t"], [' ', ' '], (string)($value ?? ''));
    return trim(preg_replace('/[ ]+/', ' ', $value));
}

function pdfAmount($amount)
{
    return 'Rs. ' . number_format((float)$amount, 2);
}

function pdfDate($date)
{
    $timestamp = strtotime((string)$date);
    return $timestamp ? date('d M Y', $timestamp) : (string)$date;
}

function assetDataUri($relativePath)
{
    $path = __DIR__ . '/' . ltrim((string)$relativePath, '/\\');
    if (!is_file($path)) {
        return '';
    }

    $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';
    if ($mime === 'image/png' && !extension_loaded('gd')) {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}

function buildReceiptHtml($donation)
{
    $receiptNo = pdfClean($donation['receipt_number'] ?? '');
    $receiptDate = pdfDate($donation['donation_date'] ?? '');
    $paymentMode = pdfClean($donation['payment_mode'] ?? '') ?: '-';
    $donorName = pdfClean($donation['donor_name'] ?? '');
    $mobile = pdfClean($donation['mobile'] ?? '');
    $address = pdfClean($donation['address'] ?? '');
    $purpose = pdfClean($donation['purpose'] ?? '') ?: 'General Donation';
    $remarks = pdfClean($donation['remarks'] ?? '') ?: '-';
    $amount = (float)($donation['amount'] ?? 0);
    $amountFormatted = pdfAmount($amount);
    $contact = defined('TRUST_CONTACT') ? pdfClean(TRUST_CONTACT) : '-';
    $logo = assetDataUri(TRUST_LOGO);
    $signature = assetDataUri(TRUST_SIGNATURE);

    ob_start();
    ?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm 15mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #1a0e00;
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12.5px;
            line-height: 1.42;
            background: #fff;
        }

        .receipt {
            width: 100%;
        }

        .logo {
            height: 160px;
            width: 160px;
        }

        .title {
            color: #291901;
            font-size: 31px;
            font-weight: 700;
            letter-spacing: 1.4px;
            line-height: 1;
            margin: 0;
            text-align: center;
            text-transform: uppercase;
        }

        .ornament {
            color: #9a690f;
            font-size: 11px;
            letter-spacing: 4px;
            margin: 7px 0 10px;
            text-align: center;
        }

        .trust-name {
            color: #291901;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.22;
            margin: 0 0 15px;
            text-align: center;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo-cell {
            width: 170px;
            vertical-align: top;
        }

        .header-text-cell {
            vertical-align: top;
            padding-left: 10px;
        }

        .top-table,
        .section-table,
        .donation-table,
        .bottom-table,
        .footer-table {
            border-collapse: collapse;
            width: 100%;
        }

        .top-table td {
            vertical-align: top;
        }

        .trust-meta {
            color: #291901;
            font-size: 12.5px;
            padding: 4px 26px 0 0;
            width: 52%;
        }

        .trust-meta strong {
            color: #1a0e00;
            font-weight: 700;
        }

        .info-box {
            background: #fff7f0;
            border: 1px solid #9a690f;
            border-radius: 3px;
            padding: 13px 15px;
            width: 48%;
        }

        .info-box table {
            border-collapse: collapse;
            width: 100%;
        }

        .info-box td {
            font-size: 12.5px;
            padding: 5px 0;
            white-space: nowrap;
        }

        .info-label {
            color: #9a690f;
            font-weight: 700;
            width: 43%;
        }

        .colon {
            color: #291901;
            text-align: center;
            width: 8%;
        }

        .divider {
            border-top: 2px solid #9a690f;
            margin: 18px 0;
        }

        .section-table td {
            vertical-align: top;
            width: 50%;
        }

        .section-left {
            padding-right: 22px;
        }

        .section-right {
            padding-left: 22px;
        }

        .section-heading {
            color: #9a690f;
            font-size: 13.5px;
            font-weight: 700;
            margin: 0 0 13px;
            text-transform: uppercase;
        }

        .icon {
            background: #291901;
            border-radius: 50%;
            color: #fff7f0;
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            height: 22px;
            line-height: 22px;
            margin-right: 10px;
            text-align: center;
            width: 22px;
        }

        .details-table {
            border-collapse: collapse;
            width: 100%;
        }

        .details-table td {
            padding: 5.5px 0;
            vertical-align: top;
        }

        .field-label {
            color: #1a0e00;
            font-weight: 700;
            width: 34%;
        }

        .field-colon {
            color: #291901;
            text-align: center;
            width: 8%;
        }

        .field-value {
            color: #1a0e00;
            width: 58%;
            word-wrap: break-word;
        }

        .donation-table {
            margin-top: 20px;
        }

        .donation-table th {
            background: #291901;
            border-right: 1px solid #9a690f;
            color: #fff7f0;
            font-size: 11.5px;
            font-weight: 700;
            padding: 10px 9px;
            text-align: center;
            text-transform: uppercase;
        }

        .donation-table th:last-child {
            border-right: 0;
        }

        .donation-table td {
            border-bottom: 2px solid #9a690f;
            color: #1a0e00;
            font-size: 12.5px;
            padding: 11px 9px;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .bottom-table {
            margin-top: 18px;
        }

        .bottom-table td {
            vertical-align: top;
        }

        .amount-words {
            padding: 0 20px 0 0;
            width: 55%;
        }

        .summary-wrap {
            width: 45%;
        }

        .small-heading {
            border-bottom: 1px dashed #9a690f;
            color: #9a690f;
            font-size: 12.5px;
            font-weight: 700;
            margin: 0 0 8px;
            padding-bottom: 5px;
            text-transform: uppercase;
        }

        .words-text {
            color: #1a0e00;
            font-size: 13.5px;
            line-height: 1.55;
            margin: 0;
        }

        .summary {
            border-collapse: collapse;
            margin-left: auto;
            width: 100%;
        }

        .summary td {
            border: 1px solid #9a690f;
            font-size: 13px;
            padding: 9px 13px;
        }

        .summary td:first-child {
            color: #9a690f;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary .amount-cell {
            background: #291901;
            border-color: #291901;
            color: #fff7f0;
            font-size: 15px;
            font-weight: 700;
        }

        .thank-you {
            font-style: italic;
            margin: 20px 0 0;
        }

        .signature-area {
            margin-top: 15px;
            text-align: right;
        }

        .signature-box {
            display: inline-block;
            text-align: center;
            width: 245px;
        }

        .signature-title {
            color: #9a690f;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .signature-img {
            height: 48px;
            margin-bottom: 8px;
            object-fit: contain;
            width: 165px;
        }

        .signature-line {
            border-top: 1.5px solid #9a690f;
            margin: 0 auto 5px;
            width: 210px;
        }

        .signatory-name {
            font-size: 12.5px;
            font-weight: 700;
        }

        .signatory-role {
            color: #291901;
            font-size: 11.5px;
            margin-top: 2px;
        }

        .footer {
            border-top: 1.5px solid #9a690f;
            color: #291901;
            font-size: 11.5px;
            margin-top: 18px;
            padding-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <?php if ($logo): ?>
                        <img class="logo" src="<?php echo pdfEscape($logo); ?>" alt="Trust logo">
                    <?php endif; ?>
                </td>
                <td class="header-text-cell">
                    <h1 class="title">Donation Receipt</h1>
                    <div class="ornament">&mdash;&mdash;&mdash;&mdash; &bull; &mdash;&mdash;&mdash;&mdash;</div>
                    <div class="trust-name"><?php echo pdfEscape(TRUST_NAME); ?></div>
                </td>
            </tr>
        </table>

        <table class="top-table">
            <tr>
                <td class="trust-meta">
                    <strong>Address:</strong><br>
                    <?php echo pdfEscape(TRUST_ADDRESS); ?><br>
                    <strong>Registration No.:</strong> <?php echo pdfEscape(preg_replace('/^Registration No\.\s*/i', '', TRUST_REGISTRATION)); ?><br>
                  <!--  <?php if (defined('ISKCON_REGISTRATION') && ISKCON_REGISTRATION): ?> -->
                    <strong>ISKCON Naamhatta Reg. No.:</strong>
                        <?php echo pdfEscape(preg_replace('/^ISKCON Naamhatta Reg No\.\s*/i', '', ISKCON_REGISTRATION)); ?><br>
                    <?php endif; ?>
                    <strong>Contact No.:</strong> <?php echo pdfEscape($contact ?: '-'); ?>
                </td>
                <td class="info-box">
                    <table>
                        <tr>
                            <td class="info-label">Receipt Number</td>
                            <td class="colon">:</td>
                            <td><?php echo pdfEscape($receiptNo); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Receipt Date</td>
                            <td class="colon">:</td>
                            <td><?php echo pdfEscape($receiptDate); ?></td>
                        </tr>
                        <tr>
                            <td class="info-label">Payment Mode</td>
                            <td class="colon">:</td>
                            <td><?php echo pdfEscape($paymentMode); ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <table class="section-table">
            <tr>
                <td class="section-left">
                    <div class="section-heading"><span class="icon">R</span>Received From</div>
                    <table class="details-table">
                        <?php if ($donorName !== ''): ?>
                        <tr>
                            <td class="field-label">Donor Name</td>
                            <td class="field-colon">:</td>
                            <td class="field-value"><?php echo pdfEscape($donorName); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($mobile !== ''): ?>
                        <tr>
                            <td class="field-label">Mobile Number</td>
                            <td class="field-colon">:</td>
                            <td class="field-value"><?php echo pdfEscape($mobile); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($address !== ''): ?>
                        <tr>
                            <td class="field-label">Address</td>
                            <td class="field-colon">:</td>
                            <td class="field-value"><?php echo nl2br(pdfEscape($address)); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </td>
                <td class="section-right">
                    <div class="section-heading"><span class="icon">D</span>Donation Details</div>
                    <table class="details-table">
                        <tr>
                            <td class="field-label">Donation Purpose</td>
                            <td class="field-colon">:</td>
                            <td class="field-value"><?php echo pdfEscape($purpose); ?></td>
                        </tr>
                        <tr>
                            <td class="field-label">Remarks</td>
                            <td class="field-colon">:</td>
                            <td class="field-value"><?php echo nl2br(pdfEscape($remarks)); ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="donation-table">
            <thead>
                <tr>
                    <th style="width: 12%;">SL No</th>
                    <th style="width: 58%;">Description</th>
                    <th style="width: 12%;">Qty</th>
                    <th style="width: 18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>Donation</td>
                    <td class="text-center">1</td>
                    <td class="text-right"><?php echo pdfEscape($amountFormatted); ?></td>
                </tr>
            </tbody>
        </table>

        <table class="bottom-table">
            <tr>
                <td class="amount-words">
                    <div class="small-heading">Amount in Words</div>
                    <p class="words-text"><?php echo pdfEscape(amountToWords($amount)); ?></p>
                    <p class="thank-you">Thank you for your generous contribution.</p>
                </td>
                <td class="summary-wrap">
                    <table class="summary">
                        <tr>
                            <td><strong>Total Amount</strong></td>
                            <td class="text-right amount-cell"><?php echo pdfEscape($amountFormatted); ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="signature-area">
            <div class="signature-box">
                <div class="signature-title">Authorized Signatory</div>
                <?php if ($signature): ?>
                    <img class="signature-img" src="<?php echo pdfEscape($signature); ?>" alt="Signature">
                <?php else: ?>
                    <div style="height: 42px;"></div>
                <?php endif; ?>
                <div class="signature-line"></div>
                <div class="signatory-name"><?php echo pdfEscape(AUTHORIZED_SIGNATORY); ?></div>
                <div class="signatory-role">Treasurer</div>
            </div>
        </div>

        <div class="footer">This is a computer-generated donation receipt and does not require a physical signature.</div>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('donations');
}

$stmt = $pdo->prepare('SELECT * FROM donations WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$donation = $stmt->fetch();
if (!$donation) {
    redirect('donations');
}

if (!empty($donation['status']) && $donation['status'] === 'cancelled') {
    http_response_code(403);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Receipt disabled</title></head><body style="margin:0;background:#fff7f0;color:#1a0e00;"><div style="max-width:640px;margin:2rem auto;padding:1.5rem;border:1px solid #9a690f;border-radius:8px;font-family:Arial,Helvetica,sans-serif;"><h1 style="margin-top:0;color:#291901;">Receipt download disabled</h1><p>The donation receipt cannot be downloaded because this donation has been cancelled.</p><p><a style="color:#9a690f;" href="' . htmlspecialchars(url('view-donation') . '?id=' . urlencode($donation['id']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Back to donation details</a></p></div></body></html>';
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Dompdf is required for PDF downloads. Install it with: composer require dompdf/dompdf';
    exit;
}

require_once $autoload;

$html = buildReceiptHtml($donation);
$options = new Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf\Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', $donation['receipt_number']);
$dompdf->stream('Donation-Receipt-' . $filename . '.pdf', ['Attachment' => true]);
