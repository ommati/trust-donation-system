<?php
// simple_pdf.php - Minimal PHP PDF library for receipt downloads
class SimplePDF
{
    protected $pages = [];
    protected $currentPage = 0;
    protected $content = [];
    protected $font = 'Helvetica';
    protected $fontSize = 12;
    protected $pageWidth = 210; // mm
    protected $pageHeight = 297; // mm

    public function addPage()
    {
        $this->currentPage = count($this->pages) + 1;
        $this->pages[$this->currentPage] = [];
        return $this->currentPage;
    }

    public function setFont($family = 'Helvetica', $size = 12)
    {
        $this->font = $family;
        $this->fontSize = $size;
    }

    public function text($x, $y, $text)
    {
        $stream = sprintf('BT /F1 %F Tf %F %F Td (%s) Tj ET', $this->fontSize, $x * $this->mmToPt(), ($this->pageHeight - $y) * $this->mmToPt(), $this->escapeText($text));
        $this->pages[$this->currentPage][] = $stream;
    }

    public function multiText($x, $y, $text, $lineHeight = 6)
    {
        $lines = explode("\n", $text);
        foreach ($lines as $index => $line) {
            $this->text($x, $y + ($index * $lineHeight), $line);
        }
    }

    public function cell($x, $y, $text, $fontSize = null)
    {
        if ($fontSize !== null) {
            $previous = $this->fontSize;
            $this->fontSize = $fontSize;
            $this->text($x, $y, $text);
            $this->fontSize = $previous;
            return;
        }
        $this->text($x, $y, $text);
    }

    public function getPdfString()
    {
        if (empty($this->pages)) {
            return '';
        }

        $objects = [];
        $objects[] = null; // 1-based index
        $contentRefs = [];

        foreach ($this->pages as $pageIndex => $pageContents) {
            $data = implode("\n", $pageContents);
            $contentRefs[$pageIndex] = count($objects);
            $objects[] = sprintf('<< /Length %d >>\nstream\n%s\nendstream', strlen($data), $data);
        }

        $fontRef = count($objects);
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $pagesRef = count($objects);
        $objects[] = ''; // placeholder for Pages object

        $pageTreeKids = [];
        foreach ($this->pages as $pageIndex => $pageContents) {
            $pageNumber = count($objects) + 1;
            $pageTreeKids[] = sprintf('%d 0 R', $pageNumber);
            $objects[] = sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %F %F] /Resources << /Font << /F1 %d 0 R >> >> /Contents %d 0 R >>',
                $pagesRef,
                $this->pageWidth * $this->mmToPt(),
                $this->pageHeight * $this->mmToPt(),
                $fontRef,
                $contentRefs[$pageIndex]
            );
        }

        $objects[$pagesRef] = sprintf('<< /Type /Pages /Kids [ %s ] /Count %d >>', implode(' ', $pageTreeKids), count($pageTreeKids));
        $catalogRef = count($objects);
        $objects[] = sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesRef);

        $pdf = "%PDF-1.4\n%âãÏÓ\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            if ($index === 0) {
                continue;
            }
            $offsets[$index] = strlen($pdf);
            $pdf .= sprintf('%d 0 obj\n%s\nendobj\n', $index, $object);
        }

        $xrefPos = strlen($pdf);
        $pdf .= 'xref\n0 ' . count($objects) . '\n0000000000 65535 f \n';
        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n \n', $offset);
        }
        $pdf .= sprintf('trailer<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%EOF', count($objects), $catalogRef, $xrefPos);

        return $pdf;
    }

    public function output($filename = 'document.pdf')
    {
        $pdf = $this->getPdfString();
        if ($pdf === '') {
            return;
        }

        if (ob_get_length()) {
            @ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    protected function mmToPt()
    {
        return 72 / 25.4;
    }

    protected function escapeText($text)
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        return $text;
    }
}
