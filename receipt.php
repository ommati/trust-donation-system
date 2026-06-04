<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/vendor/autoload.php';
requireLogin();

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

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-8">
        <div class="receipt-box mb-4">
            <div class="receipt-header">
                <div class="receipt-trust">
                    <img class="receipt-logo" src="<?php echo escape(TRUST_LOGO); ?>" alt="">
                    <div>
                        <h4 class="mb-1"><?php echo escape(TRUST_NAME); ?></h4>
                        <p class="mb-1 text-muted"><?php echo escape(TRUST_ADDRESS); ?></p>
                        <p class="mb-0 text-muted"><?php echo escape(TRUST_REGISTRATION); ?></p>
                    </div>
                </div>
                <div class="receipt-meta">
                    <h6 class="text-uppercase text-primary mb-1">Donation Receipt</h6>
                    <p class="mb-0"><strong>Receipt No:</strong> <?php echo escape($donation['receipt_number']); ?></p>
                    <p class="mb-0"><strong>Date:</strong> <?php echo escape($donation['donation_date']); ?></p>
                </div>
            </div>
            <div class="row g-3 receipt-details">
                <div class="col-12 col-sm-6">
                    <p class="mb-1"><strong>Donor Name:</strong></p>
                    <p><?php echo escape($donation['donor_name']); ?></p>
                </div>
                <div class="col-12 col-sm-6">
                    <p class="mb-1"><strong>Payment Mode:</strong></p>
                    <p><?php echo escape($donation['payment_mode']); ?></p>
                </div>
                <div class="col-12 col-sm-6">
                    <p class="mb-1"><strong>Amount (Figures):</strong></p>
                    <p><?php echo formatCurrency($donation['amount']); ?></p>
                </div>
                <div class="col-12 col-sm-6">
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
            <div class="mt-5 pt-3 border-top">
                <div class="row justify-content-end">
                    <div class="col-12 col-sm-7 col-lg-5 receipt-signature">
                        <p class="mb-2 small text-muted">Authorized Signatory</p>
                        <?php 
                        $sigPath = __DIR__ . '/' . TRUST_SIGNATURE;
                        if (file_exists($sigPath)) {
                            echo '<img src="' . escape(TRUST_SIGNATURE) . '" alt="Signature" class="receipt-signature-image">';
                        }
                        ?>
                        <div class="border-top pt-2 mt-2"><?php echo escape(AUTHORIZED_SIGNATORY); ?></div>
                    </div>
                </div>
            </div>
            <div class="action-group receipt-actions">
                <a class="btn btn-outline-secondary" href="<?php echo url('download-pdf') . '?id=' . urlencode($donation['id']); ?>">Download PDF</a>
                <button type="button" onclick="window.print();" class="btn btn-primary">Print Receipt</button>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
