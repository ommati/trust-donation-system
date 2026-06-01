<?php
// fpdf.php - Working FPDF implementation for text and image rendering

class FPDF
{
    public $page;
    public $n;
    public $offsets;
    public $buffer;
    public $pages;
    public $state;
    public $compress;
    public $k;
    public $DefOrientation;
    public $CurOrientation;
    public $PageFormats;
    public $DefPageFormat;
    public $CurPageFormat;
    public $PageSizes;
    public $wPt;
    public $hPt;
    public $w;
    public $h;
    public $lMargin;
    public $tMargin;
    public $rMargin;
    public $bMargin;
    public $cMargin;
    public $x;
    public $y;
    public $lasth;
    public $LineWidth;
    public $CoreFonts;
    public $CurrentFont;
    public $fonts;
    public $FontFiles;
    public $diffs;
    public $images;
    public $PageLinks;
    public $links;
    public $FontSizePt;
    public $FontSize;
    public $DrawColor;
    public $FillColor;
    public $TextColor;
    public $ColorFlag;
    public $ws;

    function __construct($orientation = 'P', $unit = 'mm', $format = 'A4')
    {
        $this->state = 0;
        $this->page = 0;
        $this->n = 2;
        $this->buffer = '';
        $this->pages = [];
        $this->PageFormats = ['a4' => [210, 297], 'letter' => [216, 279]];
        $this->CurPageFormat = [210, 297];
        $this->DefPageFormat = [210, 297];
        $this->compress = false;
        $this->k = 2.83464567;
        $this->DefOrientation = 'P';
        $this->CurOrientation = 'P';
        $this->wPt = 595.28;
        $this->hPt = 841.89;
        $this->w = 210;
        $this->h = 297;
        $this->lMargin = 10;
        $this->tMargin = 10;
        $this->rMargin = 10;
        $this->bMargin = 10;
        $this->cMargin = 5;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->lasth = 0;
        $this->LineWidth = 0.567;
        $this->CoreFonts = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];
        $this->CurrentFont = ['name' => 'helvetica', 'style' => '', 'size' => 12];
        $this->fonts = [];
        $this->FontFiles = [];
        $this->diffs = [];
        $this->images = [];
        $this->links = [];
        $this->PageLinks = [];
        $this->FontSizePt = 12;
        $this->FontSize = 12;
        $this->DrawColor = '0 G';
        $this->FillColor = '0 g';
        $this->TextColor = '0 0 0 rg';
        $this->ColorFlag = false;
        $this->ws = 0;
        $this->AddFont('Helvetica', '', 'helvetica');
        $this->SetFont('Helvetica', '', 12);
    }

    function AddPage()
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->state = 2;
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
    }

    function SetFont($family = '', $style = '', $size = 0)
    {
        $family = strtolower($family);
        if ($family == '') {
            $family = $this->CurrentFont['name'];
        }
        if (in_array($family, $this->CoreFonts)) {
            $family = $family;
        }
        $this->CurrentFont = ['name' => $family, 'style' => $style, 'size' => ($size == 0 ? $this->FontSize : $size)];
        $this->FontSizePt = ($size == 0 ? $this->FontSizePt : $size);
        $this->FontSize = ($size == 0 ? $this->FontSize : $size);
    }

    function SetXY($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    function SetX($x)
    {
        $this->x = $x;
    }

    function SetY($y)
    {
        $this->y = $y;
    }

    function GetX()
    {
        return $this->x;
    }

    function GetY()
    {
        return $this->y;
    }

    function SetDrawColor($r, $g = null, $b = null)
    {
        if ($g === null || $b === null) {
            $this->DrawColor = sprintf('%.3F G', $r / 255);
            return;
        }
        $this->DrawColor = sprintf('%.3F %.3F %.3F RG', $r / 255, $g / 255, $b / 255);
    }

    function SetFillColor($r, $g = null, $b = null)
    {
        if ($g === null || $b === null) {
            $this->FillColor = sprintf('%.3F g', $r / 255);
            return;
        }
        $this->FillColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    }

    function SetTextColor($r, $g = null, $b = null)
    {
        if ($g === null || $b === null) {
            $this->TextColor = sprintf('%.3F g', $r / 255);
            return;
        }
        $this->TextColor = sprintf('%.3F %.3F %.3F rg', $r / 255, $g / 255, $b / 255);
    }

    function SetLineWidth($width)
    {
        $this->LineWidth = $width;
    }

    function Line($x1, $y1, $x2, $y2)
    {
        $k = $this->k;
        $this->pages[$this->page] .= sprintf(
            "q %.2F w %s %.2F %.2F m %.2F %.2F l S Q\n",
            $this->LineWidth * $k,
            $this->DrawColor,
            $x1 * $k,
            ($this->h - $y1) * $k,
            $x2 * $k,
            ($this->h - $y2) * $k
        );
    }

    function Rect($x, $y, $w, $h, $style = '')
    {
        $k = $this->k;
        $op = (stripos($style, 'F') !== false) ? 'f' : 'S';
        if (stripos($style, 'D') !== false || stripos($style, 'S') !== false) {
            $op = (stripos($style, 'F') !== false) ? 'B' : 'S';
        }
        $this->pages[$this->page] .= sprintf(
            "q %.2F w %s %s %.2F %.2F %.2F %.2F re %s Q\n",
            $this->LineWidth * $k,
            $this->DrawColor,
            $this->FillColor,
            $x * $k,
            ($this->h - $y) * $k,
            $w * $k,
            -$h * $k,
            $op
        );
    }

    function Circle($x, $y, $r, $style = 'D')
    {
        $k = $this->k;
        $c = 0.5522847498;
        $x0 = $x * $k;
        $y0 = ($this->h - $y) * $k;
        $r *= $k;
        $op = (stripos($style, 'F') !== false) ? 'f' : 'S';
        if (stripos($style, 'D') !== false && stripos($style, 'F') !== false) {
            $op = 'B';
        }

        $this->pages[$this->page] .= sprintf(
            "q %.2F w %s %s %.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %s Q\n",
            $this->LineWidth * $k,
            $this->DrawColor,
            $this->FillColor,
            $x0 + $r,
            $y0,
            $x0 + $r,
            $y0 + $c * $r,
            $x0 + $c * $r,
            $y0 + $r,
            $x0,
            $y0 + $r,
            $x0 - $c * $r,
            $y0 + $r,
            $x0 - $r,
            $y0 + $c * $r,
            $x0 - $r,
            $y0,
            $x0 - $r,
            $y0 - $c * $r,
            $x0 - $c * $r,
            $y0 - $r,
            $x0,
            $y0 - $r,
            $x0 + $c * $r,
            $y0 - $r,
            $x0 + $r,
            $y0 - $c * $r,
            $x0 + $r,
            $y0,
            $op
        );
    }

    function Image($file, $x, $y, $w = 0, $h = 0)
    {
        if (!file_exists($file)) {
            return false;
        }

        $key = realpath($file) ?: $file;
        if (!isset($this->images[$key])) {
            $info = $this->parseImage($file);
            if (!$info) {
                return false;
            }
            $info['i'] = count($this->images) + 1;
            $this->images[$key] = $info;
        }

        $info = $this->images[$key];
        if ($w == 0 && $h == 0) {
            $w = $info['w'] * 25.4 / 96;
            $h = $info['h'] * 25.4 / 96;
        } elseif ($w == 0) {
            $w = $h * $info['w'] / $info['h'];
        } elseif ($h == 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        $k = $this->k;
        $this->pages[$this->page] .= sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q\n",
            $w * $k,
            $h * $k,
            $x * $k,
            ($this->h - ($y + $h)) * $k,
            $info['i']
        );
        return true;
    }

    function Cell($w = 0, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $k = $this->k;
        $width = ($w == 0) ? ($this->w - $this->rMargin - $this->x) : $w;
        $textWidth = $this->getApproxTextWidth($txt);
        $tx = $this->x + $this->cMargin;

        if ($align == 'C') {
            $tx = $this->x + max(0, ($width - $textWidth) / 2);
        } elseif ($align == 'R') {
            $tx = $this->x + max(0, $width - $this->cMargin - $textWidth);
        }

        $ty = $this->y + (($h > 0) ? ($h * 0.7) : 0);
        $s = sprintf(
            'BT /F1 %.2F Tf %.2F %.2F Td (%s) Tj ET',
            $this->FontSizePt,
            $tx * $k,
            ($this->h - $ty) * $k,
            $this->_escape($this->normalizeText($txt))
        );
        $s = 'q ' . $this->TextColor . ' ' . $s . ' Q';
        $this->pages[$this->page] .= $s . "\n";
        $this->lasth = $h;

        if ($ln > 0) {
            $this->x = $this->lMargin;
            $this->y += $h;
        } else {
            $this->x += $width;
        }
    }

    function MultiCell($w = 0, $h = 5, $txt = '', $border = 0, $align = 'J', $fill = false)
    {
        $w = ($w == 0) ? ($this->w - $this->rMargin - $this->x) : $w;
        $s = str_replace("\r", '', $txt);
        $maxChars = max(1, (int) floor(($w - (2 * $this->cMargin)) / max(1, $this->FontSize * 0.35)));
        $lines = [];
        $startX = $this->x;

        foreach (explode("\n", $s) as $paragraph) {
            $wrapped = wordwrap($paragraph, $maxChars, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $lines[] = $line;
            }
        }

        foreach ($lines as $line) {
            $this->SetX($startX);
            $this->Cell($w, $h, $line, $border, 1, $align, $fill);
        }
    }

    function Ln($h = null)
    {
        $this->x = $this->lMargin;
        if (is_null($h)) {
            $this->y += $this->lasth;
        } else {
            $this->y += $h;
        }
    }

    function Output($name = '', $dest = '')
    {
        if ($this->state < 3) {
            $this->Close();
        }
        $pdf = $this->_putpdf();
        if ($dest == '') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        } elseif ($dest == 'S') {
            return $pdf;
        } elseif ($dest == 'F') {
            file_put_contents($name, $pdf);
            return;
        }
    }

    function Close()
    {
        if ($this->state == 3) {
            return;
        }
        $this->state = 3;
    }

    function _putpdf()
    {
        $objects = [
            1 => '',
            2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $imageResources = [];
        $pageRefs = [];
        $nextObject = 4;

        foreach ($this->images as $image) {
            $imageRef = $nextObject++;
            $imageResources[] = sprintf('/I%d %d 0 R', $image['i'], $imageRef);
            $objects[$imageRef] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent %d /Filter %s /Length %d >>\r\nstream\r\n%s\r\nendstream",
                $image['w'],
                $image['h'],
                $image['cs'],
                $image['bpc'],
                $image['f'],
                strlen($image['data']),
                $image['data']
            );
        }

        $xObjects = '';
        if (!empty($imageResources)) {
            $xObjects = ' /XObject << ' . implode(' ', $imageResources) . ' >>';
        }

        foreach ($this->pages as $content) {
            $pageRef = $nextObject++;
            $contentRef = $nextObject++;
            $pageRefs[] = $pageRef . ' 0 R';
            $objects[$pageRef] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R >>%s >> /Contents %d 0 R >>',
                $this->wPt,
                $this->hPt,
                $xObjects,
                $contentRef
            );
            $objects[$contentRef] = sprintf("<< /Length %d >>\r\nstream\r\n%s\r\nendstream", strlen($content), $content);
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $pageRefs), count($pageRefs));

        ksort($objects);

        $pdf = "%PDF-1.4\r\n";
        $this->offsets = [];
        foreach ($objects as $objectNumber => $object) {
            $this->offsets[$objectNumber] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\r\n%s\r\nendobj\r\n", $objectNumber, $object);
        }

        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\r\n0 " . $size . "\r\n";
        $pdf .= "0000000000 65535 f \r\n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \r\n", $this->offsets[$i] ?? 0);
        }
        $pdf .= sprintf("trailer\r\n<< /Size %d /Root 1 0 R >>\r\nstartxref\r\n%d\r\n%%EOF", $size, $xref);
        return $pdf;
    }

    function AddFont($family, $style = '', $file = '')
    {
        $family = strtolower($family);
        $this->fonts[$family] = [];
    }

    function _escape($txt)
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $txt);
    }

    function normalizeText($txt)
    {
        $txt = (string) $txt;
        $txt = str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $txt);
        $txt = str_replace(["₹", "â‚¹"], 'Rs.', $txt);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $txt);
        return $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '', $txt);
    }

    function getApproxTextWidth($txt)
    {
        return strlen($this->normalizeText($txt)) * $this->FontSize * 0.35;
    }

    function parseImage($file)
    {
        $info = @getimagesize($file);
        if (!$info) {
            return false;
        }

        if ($info[2] == IMAGETYPE_JPEG) {
            return [
                'w' => $info[0],
                'h' => $info[1],
                'cs' => '/DeviceRGB',
                'bpc' => 8,
                'f' => '/DCTDecode',
                'data' => file_get_contents($file),
            ];
        }

        if ($info[2] == IMAGETYPE_PNG) {
            return $this->parsePng($file);
        }

        return false;
    }

    function parsePng($file)
    {
        $data = file_get_contents($file);
        if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }

        $pos = 8;
        $width = 0;
        $height = 0;
        $bits = 8;
        $colorType = 2;
        $palette = '';
        $trns = '';
        $compressed = '';

        while ($pos < strlen($data)) {
            $length = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            $chunk = substr($data, $pos + 8, $length);
            $pos += 12 + $length;

            if ($type === 'IHDR') {
                $header = unpack('Nwidth/Nheight/Cbits/Ccolor/Ccompression/Cfilter/Cinterlace', $chunk);
                $width = $header['width'];
                $height = $header['height'];
                $bits = $header['bits'];
                $colorType = $header['color'];
                if ($header['interlace'] != 0 || $bits != 8) {
                    return false;
                }
            } elseif ($type === 'PLTE') {
                $palette = $chunk;
            } elseif ($type === 'tRNS') {
                $trns = $chunk;
            } elseif ($type === 'IDAT') {
                $compressed .= $chunk;
            } elseif ($type === 'IEND') {
                break;
            }
        }

        $raw = @gzuncompress($compressed);
        if ($raw === false) {
            return false;
        }

        $rgb = $this->pngToRgb($raw, $width, $height, $colorType, $palette, $trns);
        if ($rgb === false) {
            return false;
        }

        return [
            'w' => $width,
            'h' => $height,
            'cs' => '/DeviceRGB',
            'bpc' => 8,
            'f' => '/FlateDecode',
            'data' => gzcompress($rgb),
        ];
    }

    function pngToRgb($raw, $width, $height, $colorType, $palette, $trns)
    {
        $channels = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$colorType] ?? null;
        if ($channels === null) {
            return false;
        }

        $stride = $width * $channels;
        $offset = 0;
        $previous = str_repeat("\0", $stride);
        $output = '';

        for ($y = 0; $y < $height; $y++) {
            $filter = ord($raw[$offset]);
            $offset++;
            $scanline = substr($raw, $offset, $stride);
            $offset += $stride;
            $scanline = $this->pngUnfilter($filter, $scanline, $previous, $channels);
            $previous = $scanline;

            for ($x = 0; $x < $width; $x++) {
                $i = $x * $channels;
                if ($colorType == 0) {
                    $g = ord($scanline[$i]);
                    $output .= chr($g) . chr($g) . chr($g);
                } elseif ($colorType == 2) {
                    $output .= substr($scanline, $i, 3);
                } elseif ($colorType == 3) {
                    $index = ord($scanline[$i]);
                    $r = ord($palette[$index * 3] ?? "\xff");
                    $g = ord($palette[$index * 3 + 1] ?? "\xff");
                    $b = ord($palette[$index * 3 + 2] ?? "\xff");
                    $a = isset($trns[$index]) ? ord($trns[$index]) : 255;
                    $output .= $this->blendOnWhite($r, $g, $b, $a);
                } elseif ($colorType == 4) {
                    $g = ord($scanline[$i]);
                    $a = ord($scanline[$i + 1]);
                    $output .= $this->blendOnWhite($g, $g, $g, $a);
                } elseif ($colorType == 6) {
                    $r = ord($scanline[$i]);
                    $g = ord($scanline[$i + 1]);
                    $b = ord($scanline[$i + 2]);
                    $a = ord($scanline[$i + 3]);
                    $output .= $this->blendOnWhite($r, $g, $b, $a);
                }
            }
        }

        return $output;
    }

    function pngUnfilter($filter, $scanline, $previous, $bytesPerPixel)
    {
        $length = strlen($scanline);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $x = ord($scanline[$i]);
            $left = ($i >= $bytesPerPixel) ? ord($result[$i - $bytesPerPixel]) : 0;
            $up = ord($previous[$i] ?? "\0");
            $upLeft = ($i >= $bytesPerPixel) ? ord($previous[$i - $bytesPerPixel] ?? "\0") : 0;

            if ($filter == 1) {
                $x = ($x + $left) & 255;
            } elseif ($filter == 2) {
                $x = ($x + $up) & 255;
            } elseif ($filter == 3) {
                $x = ($x + floor(($left + $up) / 2)) & 255;
            } elseif ($filter == 4) {
                $x = ($x + $this->paeth($left, $up, $upLeft)) & 255;
            }

            $result .= chr($x);
        }

        return $result;
    }

    function paeth($a, $b, $c)
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }
        return ($pb <= $pc) ? $b : $c;
    }

    function blendOnWhite($r, $g, $b, $a)
    {
        $r = (int) round(($r * $a + 255 * (255 - $a)) / 255);
        $g = (int) round(($g * $a + 255 * (255 - $a)) / 255);
        $b = (int) round(($b * $a + 255 * (255 - $a)) / 255);
        return chr($r) . chr($g) . chr($b);
    }
}
