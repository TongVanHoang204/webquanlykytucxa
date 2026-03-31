<?php

if (!class_exists('FPDF')) {
    class FPDF
    {
        protected $orientation;
        protected $unit;
        protected $k;
        protected $w;
        protected $h;
        protected $lMargin = 10;
        protected $tMargin = 10;
        protected $rMargin = 10;
        protected $bMargin = 10;
        protected $autoPageBreak = true;
        protected $autoPageBreakMargin = 10;
        protected $fontFamily = 'Helvetica';
        protected $fontStyle = '';
        protected $fontSize = 12;
        protected $pages = [];
        protected $page = 0;
        protected $x = 10;
        protected $y = 10;
        protected $title = '';
        protected $author = '';
        protected $subject = '';
        protected $keywords = '';

        public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
        {
            $this->orientation = strtoupper((string) $orientation);
            $this->unit = strtolower((string) $unit);
            $this->k = 72 / 25.4;

            if (is_array($size)) {
                $this->w = (float) ($size[0] ?? 210);
                $this->h = (float) ($size[1] ?? 297);
            } else {
                $this->w = 210;
                $this->h = 297;
            }

            if ($this->orientation === 'L') {
                [$this->w, $this->h] = [$this->h, $this->w];
            }
        }

        public function SetTitle($title)
        {
            $this->title = (string) $title;
        }

        public function SetAuthor($author)
        {
            $this->author = (string) $author;
        }

        public function SetSubject($subject)
        {
            $this->subject = (string) $subject;
        }

        public function SetKeywords($keywords)
        {
            $this->keywords = (string) $keywords;
        }

        public function SetAutoPageBreak($auto, $margin = 0)
        {
            $this->autoPageBreak = (bool) $auto;
            $this->autoPageBreakMargin = max(0, (float) $margin);
        }

        public function AddPage($orientation = '', $size = '', $rotation = 0)
        {
            if ($orientation !== '') {
                $this->orientation = strtoupper((string) $orientation);
                if ($this->orientation === 'L') {
                    [$this->w, $this->h] = [297, 210];
                } else {
                    [$this->w, $this->h] = [210, 297];
                }
            }

            $this->page++;
            $this->pages[$this->page] = [
                'content' => [],
                'images' => [],
                'width' => $this->w,
                'height' => $this->h,
            ];
            $this->x = $this->lMargin;
            $this->y = $this->tMargin;
        }

        public function SetFont($family, $style = '', $size = 0)
        {
            $this->fontFamily = (string) $family;
            $this->fontStyle = (string) $style;
            if ($size > 0) {
                $this->fontSize = (float) $size;
            }
        }

        public function SetX($x)
        {
            $this->x = (float) $x;
        }

        public function SetY($y, $resetX = true)
        {
            $this->y = (float) $y;
            if ($resetX) {
                $this->x = $this->lMargin;
            }
        }

        public function GetX()
        {
            return $this->x;
        }

        public function GetY()
        {
            return $this->y;
        }

        public function GetPageWidth()
        {
            return $this->w;
        }

        public function GetPageHeight()
        {
            return $this->h;
        }

        public function GetStringWidth($txt)
        {
            $text = $this->normalizeText($txt);
            $chars = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
            return max(0, $chars * ($this->fontSize * 0.35));
        }

        public function Ln($h = null)
        {
            $this->y += $h !== null ? (float) $h : max(4, $this->fontSize * 0.35);
            $this->x = $this->lMargin;
        }

        public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = 'L', $fill = false, $link = '')
        {
            $this->ensurePage();
            $w = (float) $w;
            $h = $h > 0 ? (float) $h : max(4, $this->fontSize * 0.35);

            if ($this->autoPageBreak && ($this->y + $h) > ($this->h - $this->bMargin)) {
                $this->AddPage($this->orientation);
            }

            $x = $this->x;
            $y = $this->y;
            $this->pages[$this->page]['content'][] = $this->buildCellCommand($x, $y, $w, $h, $txt, $border);

            if ($ln > 0) {
                $this->x = $this->lMargin;
                $this->y += $h;
            } else {
                $this->x += $w;
            }
        }

        public function MultiCell($w, $h, $txt, $border = 0, $align = 'L', $fill = false)
        {
            $lines = $this->wrapText((string) $txt, (float) $w);
            foreach ($lines as $line) {
                $this->Cell($w, $h, $line, $border, 1, $align, $fill);
            }
        }

        public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '')
        {
            $this->ensurePage();
            if (!is_string($file) || !is_file($file)) {
                throw new RuntimeException('Image file not found.');
            }

            $info = @getimagesize($file);
            if (!$info) {
                throw new RuntimeException('Unsupported image.');
            }

            $mime = $info['mime'] ?? '';
            if ($mime !== 'image/jpeg' && $mime !== 'image/jpg') {
                throw new RuntimeException('Only JPEG images are supported by this lightweight FPDF.');
            }

            $natWidth = (float) ($info[0] ?? 0);
            $natHeight = (float) ($info[1] ?? 0);
            if ($natWidth <= 0 || $natHeight <= 0) {
                throw new RuntimeException('Invalid image dimensions.');
            }

            if ($w <= 0 && $h <= 0) {
                $w = $this->w - $this->lMargin - $this->rMargin;
                $h = ($natHeight / $natWidth) * $w;
            } elseif ($w <= 0) {
                $w = ($natWidth / $natHeight) * $h;
            } elseif ($h <= 0) {
                $h = ($natHeight / $natWidth) * $w;
            }

            $x = $x === null ? $this->lMargin : (float) $x;
            $y = $y === null ? $this->tMargin : (float) $y;

            $this->pages[$this->page]['images'][] = [
                'file' => $file,
                'x' => (float) $x,
                'y' => (float) $y,
                'w' => (float) $w,
                'h' => (float) $h,
                'type' => 'jpeg',
                'width_px' => $natWidth,
                'height_px' => $natHeight,
            ];
        }

        public function AliasNbPages($alias = '{nb}')
        {
            return $alias;
        }

        public function Output($dest = 'I', $name = 'doc.pdf')
        {
            $pdf = $this->buildPdfDocument();

            switch (strtoupper((string) $dest)) {
                case 'S':
                    return $pdf;
                case 'F':
                    file_put_contents($name, $pdf);
                    return '';
                case 'D':
                    if (!headers_sent()) {
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: attachment; filename="' . basename($name) . '"');
                    }
                    echo $pdf;
                    return '';
                default:
                    if (!headers_sent()) {
                        header('Content-Type: application/pdf');
                        header('Content-Disposition: inline; filename="' . basename($name) . '"');
                    }
                    echo $pdf;
                    return '';
            }
        }

        protected function ensurePage()
        {
            if ($this->page === 0) {
                $this->AddPage($this->orientation);
            }
        }

        protected function buildCellCommand($x, $y, $w, $h, $txt, $border)
        {
            $text = $this->escapeText($this->normalizeText($txt));
            $fontSizePt = $this->fontSize;
            $xPt = $this->mmToPt($x);
            $yPt = $this->mmToPt($this->h - $y - $h + ($fontSizePt * 0.35));
            $commands = [];

            if ($border) {
                $commands[] = sprintf('%.2f %.2f %.2f %.2f re S', $xPt, $this->mmToPt($this->h - $y - $h), $this->mmToPt($w), $this->mmToPt($h));
            }

            $commands[] = sprintf('BT /F1 %.2f Tf %.2f %.2f Td (%s) Tj ET', $fontSizePt, $xPt + 2, $yPt, $text);
            return implode("\n", $commands);
        }

        protected function wrapText($text, $width)
        {
            $text = trim((string) $text);
            if ($text === '') {
                return [''];
            }

            $maxWidth = max(1, (float) $width);
            $lines = [];
            foreach (preg_split('/\r?\n/', $text) as $paragraph) {
                $words = preg_split('/\s+/', trim($paragraph));
                $current = '';
                foreach ($words as $word) {
                    $candidate = $current === '' ? $word : $current . ' ' . $word;
                    if ($this->GetStringWidth($candidate) <= $maxWidth) {
                        $current = $candidate;
                        continue;
                    }

                    if ($current !== '') {
                        $lines[] = $current;
                    }

                    if ($this->GetStringWidth($word) <= $maxWidth) {
                        $current = $word;
                        continue;
                    }

                    $chunks = $this->splitLongWord($word, $maxWidth);
                    foreach ($chunks as $chunk) {
                        if ($chunk !== '') {
                            $lines[] = $chunk;
                        }
                    }
                    $current = '';
                }

                if ($current !== '') {
                    $lines[] = $current;
                }
            }

            return $lines ?: [''];
        }

        protected function splitLongWord($word, $width)
        {
            $chars = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
            $chunk = '';
            $parts = [];
            for ($i = 0; $i < $chars; $i++) {
                $char = function_exists('mb_substr') ? mb_substr($word, $i, 1, 'UTF-8') : $word[$i];
                $candidate = $chunk . $char;
                if ($this->GetStringWidth($candidate) > $width && $chunk !== '') {
                    $parts[] = $chunk;
                    $chunk = $char;
                    continue;
                }
                $chunk = $candidate;
            }

            if ($chunk !== '') {
                $parts[] = $chunk;
            }

            return $parts;
        }

        protected function normalizeText($txt)
        {
            $text = (string) $txt;
            if ($text === '') {
                return '';
            }

            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
                if ($converted !== false && $converted !== '') {
                    return $converted;
                }
            }

            return preg_replace('/[^\x20-\x7E]/', '?', $text);
        }

        protected function escapeText($txt)
        {
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $txt);
        }

        protected function mmToPt($value)
        {
            return (float) $value * $this->k;
        }

        protected function buildPdfDocument()
        {
            $objects = [];
            $buffer = "%PDF-1.4\n";
            $pageCount = count($this->pages);
            $totalImages = 0;
            foreach ($this->pages as $page) {
                $totalImages += count($page['images']);
            }

            $imageStart = 4;
            $contentStart = $imageStart + $totalImages;
            $pageStart = $contentStart + $pageCount;

            $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

            $kids = [];
            for ($i = 0; $i < $pageCount; $i++) {
                $kids[] = ($pageStart + $i) . ' 0 R';
            }
            $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
            $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

            $imageObjectMap = [];
            $imageObjectId = $imageStart;
            foreach ($this->pages as $pageIndex => $page) {
                foreach ($page['images'] as $imageIndex => $image) {
                    $imageObjectMap[$pageIndex . ':' . $imageIndex] = $imageObjectId++;
                }
            }

            foreach ($this->pages as $pageIndex => $page) {
                foreach ($page['images'] as $imageIndex => $image) {
                    $data = file_get_contents($image['file']);
                    if ($data === false) {
                        throw new RuntimeException('Unable to read image file.');
                    }

                    $objects[] = sprintf(
                        "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                        (int) $image['width_px'],
                        (int) $image['height_px'],
                        strlen($data),
                        $data
                    );
                }
            }

            for ($i = 1; $i <= $pageCount; $i++) {
                $page = $this->pages[$i];
                $content = implode("\n", $page['content']);
                foreach ($page['images'] as $imageIndex => $image) {
                    $objId = $imageObjectMap[$i . ':' . $imageIndex] ?? null;
                    if ($objId === null) {
                        continue;
                    }
                    $x = $this->mmToPt($image['x']);
                    $y = $this->mmToPt($page['height'] - $image['y'] - $image['h']);
                    $w = $this->mmToPt($image['w']);
                    $h = $this->mmToPt($image['h']);
                    $content .= sprintf("\nq %.2f 0 0 %.2f %.2f %.2f cm /Im%d Do Q", $w, $h, $x, $y, $imageIndex + 1);
                }
                $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            }

            for ($i = 0; $i < $pageCount; $i++) {
                $page = $this->pages[$i + 1];
                $pageObjectId = $pageStart + $i;
                $contentObjectId = $contentStart + $i;

                $resourceImages = [];
                foreach ($page['images'] as $imageIndex => $image) {
                    $objId = $imageObjectMap[($i + 1) . ':' . $imageIndex] ?? null;
                    if ($objId !== null) {
                        $resourceImages[] = '/Im' . ($imageIndex + 1) . ' ' . $objId . ' 0 R';
                    }
                }

                $resourceParts = ['<<'];
                if ($resourceImages) {
                    $resourceParts[] = '/XObject << ' . implode(' ', $resourceImages) . ' >>';
                }
                if ($page['content']) {
                    $resourceParts[] = '/Font << /F1 3 0 R >>';
                }
                $resourceParts[] = '>>';

                $objects[] = sprintf(
                    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources %s /Contents %d 0 R >>',
                    $this->mmToPt($page['width']),
                    $this->mmToPt($page['height']),
                    implode(' ', $resourceParts),
                    $contentObjectId
                );
            }

            $xref = [];
            foreach ($objects as $index => $object) {
                $xref[] = strlen($buffer);
                $buffer .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
            }

            $xrefStart = strlen($buffer);
            $buffer .= "xref\n0 " . (count($objects) + 1) . "\n";
            $buffer .= "0000000000 65535 f \n";
            foreach ($xref as $offset) {
                $buffer .= sprintf('%010d 00000 n ', $offset) . "\n";
            }
            $buffer .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefStart . "\n%%EOF";

            return $buffer;
        }
    }
}
