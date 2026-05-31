<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

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

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10">
        <div class="receipt-box shadow-sm mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h4 class="mb-1"><?php echo escape(TRUST_NAME); ?></h4>
                    <p class="mb-1 text-muted"><?php echo escape(TRUST_ADDRESS); ?></p>
                    <p class="mb-0 text-muted"><?php echo escape(TRUST_REGISTRATION); ?></p>
                </div>
                <div class="text-end">
                    <h6 class="text-uppercase text-primary mb-1">Donation Receipt</h6>
                    <p class="mb-0"><strong>Receipt No:</strong> <?php echo escape($donation['receipt_number']); ?></p>
                    <p class="mb-0"><strong>Date:</strong> <?php echo escape($donation['donation_date']); ?></p>
                </div>
            </div>
            <div class="row gy-3">
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Donor Name:</strong></p>
                    <p><?php echo escape($donation['donor_name']); ?></p>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Payment Mode:</strong></p>
                    <p><?php echo escape($donation['payment_mode']); ?></p>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Amount (Figures):</strong></p>
                    <p><?php echo formatCurrency($donation['amount']); ?></p>
                </div>
                <div class="col-sm-6">
                    <p class="mb-1"><strong>Purpose:</strong></p>
                    <p><?php echo escape($donation['purpose']); ?></p>
                </div>
                <div class="col-12">
                    <p class="mb-1"><strong>Amount (Words):</strong></p>
                    <p><?php echo escape(amountToWords($donation['amount'])); ?></p>
                </div>
                <div class="col-12">
                    <p class="mb-1"><strong>Donor Address:</strong></p>
                    <p><?php echo nl2br(escape($donation['address'])); ?></p>
                </div>
                <div class="col-12">
                    <p class="mb-1"><strong>Remarks:</strong></p>
                    <p><?php echo nl2br(escape($donation['remarks'])); ?></p>
                </div>
            </div>
            <div class="mt-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div>
                    <p class="mb-1 small text-muted">Authorized Signatory</p>
                    <div class="border-top pt-2">&nbsp;</div>
                </div>
                <div class="text-end">
                    <a class="btn btn-outline-secondary me-2" href="download-pdf.php?id=<?php echo $donation['id']; ?>">Download PDF</a>
                    <button type="button" onclick="window.print();" class="btn btn-primary">Print Receipt</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
