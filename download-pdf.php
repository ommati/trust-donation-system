<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
session_write_close();

require_once __DIR__ . '/lib/simple_pdf.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('donations.php');
}

$stmt = $pdo->prepare('SELECT * FROM donations WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$donation = $stmt->fetch();
if (!$donation) {
    redirect('donations.php');
}

$pdf = new SimplePDF();
$pdf->addPage();
$pdf->setFont('Helvetica', 16);
$pdf->cell(20, 20, TRUST_NAME);
$pdf->setFont('Helvetica', 10);
$pdf->text(20, 30, TRUST_ADDRESS);
$pdf->text(20, 36, TRUST_REGISTRATION);
$pdf->setFont('Helvetica', 14);
$pdf->text(20, 50, 'Donation Receipt');
$pdf->setFont('Helvetica', 10);
$pdf->text(20, 62, 'Receipt Number: ' . $donation['receipt_number']);
$pdf->text(120, 62, 'Date: ' . $donation['donation_date']);
$pdf->text(20, 74, 'Donor Name: ' . $donation['donor_name']);
$pdf->text(20, 82, 'Mobile Number: ' . $donation['mobile']);
$pdf->text(20, 90, 'Payment Mode: ' . $donation['payment_mode']);
$pdf->text(20, 98, 'Amount: ' . formatCurrency($donation['amount']));
$pdf->text(20, 106, 'Amount (Words): ' . amountToWords($donation['amount']));
$pdf->text(20, 118, 'Purpose: ' . $donation['purpose']);
$pdf->multiText(20, 130, 'Address: ' . $donation['address']);
$pdf->multiText(20, 150, 'Remarks: ' . $donation['remarks']);
$pdf->text(20, 180, 'Authorized Signatory: __________________________');
$pdf->output('Donation-Receipt-' . $donation['receipt_number'] . '.pdf');
