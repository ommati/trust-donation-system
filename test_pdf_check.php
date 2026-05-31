<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/lib/simple_pdf.php';

$pdf = new SimplePDF();
$pdf->addPage();
$pdf->setFont('Helvetica', 12);
$pdf->text(20, 20, 'Test PDF');
file_put_contents(__DIR__ . '/test-gen.pdf', $pdf->getPdfString());
echo "Generated test-gen.pdf (" . strlen(file_get_contents(__DIR__ . '/test-gen.pdf')) . " bytes)\n";
