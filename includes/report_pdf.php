<?php

require_once __DIR__ . '/vendor/fpdf/fpdf.php';

if (!function_exists('reportPdfSafeText')) {
    function reportPdfSafeText(string $text): string
    {
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
}

if (!function_exists('reportPdfShortText')) {
    function reportPdfShortText(string $text, int $maxLength = 48): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $maxLength, '...', 'UTF-8');
        }

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, max(0, $maxLength - 3)) . '...';
    }
}

if (!function_exists('reportPdfResolveFontPath')) {
    function reportPdfResolveFontPath(): string
    {
        $candidates = [];
        $root = dirname(__DIR__);
        foreach ([
            'assets/vendor/fonts/inter-*.ttf',
            'assets/vendor/fonts/manrope-*.ttf',
            'assets/vendor/fonts/poppins-*.ttf',
        ] as $pattern) {
            $matches = glob($root . DIRECTORY_SEPARATOR . $pattern);
            if (is_array($matches)) {
                $candidates = array_merge($candidates, $matches);
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('No TTF font available for PDF rendering.');
    }
}

if (!function_exists('reportPdfTextWidth')) {
    function reportPdfTextWidth(string $fontPath, int $fontSize, string $text): int
    {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        if (!is_array($bbox)) {
            return strlen($text) * $fontSize;
        }

        return (int) abs($bbox[2] - $bbox[0]);
    }
}

if (!function_exists('reportPdfWrapUtf8Text')) {
    function reportPdfWrapUtf8Text(string $fontPath, int $fontSize, string $text, int $maxWidth): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if (reportPdfTextWidth($fontPath, $fontSize, $candidate) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            if (reportPdfTextWidth($fontPath, $fontSize, $word) <= $maxWidth) {
                $current = $word;
                continue;
            }

            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $chunk = '';
            foreach ($chars as $char) {
                $candidateChunk = $chunk === '' ? $char : $chunk . $char;
                if (reportPdfTextWidth($fontPath, $fontSize, $candidateChunk) <= $maxWidth) {
                    $chunk = $candidateChunk;
                    continue;
                }

                if ($chunk !== '') {
                    $lines[] = $chunk;
                }
                $chunk = $char;
            }

            if ($chunk !== '') {
                $current = $chunk;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }
}

if (!function_exists('reportPdfDrawWrappedText')) {
    function reportPdfDrawWrappedText($image, string $fontPath, int $fontSize, int $x, int $y, int $maxWidth, int $color, string $text, int $lineHeight = 24): int
    {
        $lines = reportPdfWrapUtf8Text($fontPath, $fontSize, $text, $maxWidth);
        $drawY = $y;
        foreach ($lines as $line) {
            imagettftext($image, $fontSize, 0, $x, $drawY, $color, $fontPath, $line);
            $drawY += $lineHeight;
        }

        return count($lines) * $lineHeight;
    }
}

if (!function_exists('reportPdfColumnWidths')) {
    function reportPdfColumnWidths(array $columns, int $contentWidth): array
    {
        $weights = [];
        foreach ($columns as $column) {
            $weights[] = max(1, (float) ($column['width'] ?? 1));
        }

        $sum = array_sum($weights) ?: 1;
        $widths = [];
        foreach ($weights as $weight) {
            $widths[] = max(80, (int) round(($weight / $sum) * $contentWidth));
        }

        $delta = $contentWidth - array_sum($widths);
        if ($delta !== 0 && $widths) {
            $widths[0] += $delta;
        }

        return $widths;
    }
}

if (!function_exists('reportPdfBuildLayoutContext')) {
    function reportPdfBuildLayoutContext(array $columns, array $meta, string $fontPath, bool $firstPage): array
    {
        $pageWidth = 1754;
        $pageHeight = 1240;
        $margin = 64;
        $contentWidth = $pageWidth - ($margin * 2);
        $summary = is_array($meta['summary'] ?? null) ? $meta['summary'] : [];
        $summaryLines = [];
        foreach ($summary as $key => $value) {
            $summaryLines[] = $key . ': ' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        $summaryHeight = 0;
        $tableY = 214;
        if ($firstPage && $summaryLines) {
            $summaryText = implode(' | ', $summaryLines);
            $summaryLineCount = count(reportPdfWrapUtf8Text($fontPath, 14, $summaryText, $contentWidth - 32));
            $summaryHeight = 40 + ($summaryLineCount * 22);
            $tableY = 230 + $summaryHeight - 24;
        }

        $headerHeight = 44;
        $bodyStartY = $tableY + $headerHeight + 10;
        $pageBottom = $pageHeight - 70;
        $lineHeight = 20;
        $bodySize = 14;
        $columnWidths = reportPdfColumnWidths($columns, $contentWidth);
        $usableRowWidth = array_map(static function ($width) {
            return max(40, $width - 24);
        }, $columnWidths);

        return [
            'pageWidth' => $pageWidth,
            'pageHeight' => $pageHeight,
            'margin' => $margin,
            'contentWidth' => $contentWidth,
            'tableY' => $tableY,
            'headerHeight' => $headerHeight,
            'bodyStartY' => $bodyStartY,
            'pageBottom' => $pageBottom,
            'lineHeight' => $lineHeight,
            'bodySize' => $bodySize,
            'summaryLines' => $summaryLines,
            'summaryHeight' => $summaryHeight,
            'columnWidths' => $columnWidths,
            'usableRowWidth' => $usableRowWidth,
        ];
    }
}

if (!function_exists('reportPdfMeasureRowHeight')) {
    function reportPdfMeasureRowHeight(array $row, array $columns, array $usableRowWidth, string $fontPath, int $bodySize, int $lineHeight): int
    {
        $rowHeight = 0;
        foreach ($columns as $index => $column) {
            $key = (string) ($column['key'] ?? '');
            $value = $row[$key] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $wrapped = reportPdfWrapUtf8Text($fontPath, $bodySize, (string) $value, $usableRowWidth[$index] ?? 80);
            $rowHeight = max($rowHeight, (count($wrapped) * $lineHeight) + 16);
        }

        return max(36, $rowHeight);
    }
}

if (!function_exists('reportPdfRenderPageImage')) {
    function reportPdfRenderPageImage(array $report, array $rows, array $columns, array $meta, string $fontPath, bool $firstPage, ?array $layout = null): string
    {
        $layout = $layout ?? reportPdfBuildLayoutContext($columns, $meta, $fontPath, $firstPage);
        $pageWidth = (int) $layout['pageWidth'];
        $pageHeight = (int) $layout['pageHeight'];
        $margin = (int) $layout['margin'];
        $contentWidth = (int) $layout['contentWidth'];
        $titleColor = [28, 31, 35];
        $mutedColor = [88, 96, 105];
        $gridColor = [214, 221, 228];
        $headerFill = [237, 242, 247];
        $summaryFill = [248, 250, 252];

        $image = imagecreatetruecolor($pageWidth, $pageHeight);
        if (!$image) {
            throw new RuntimeException('Unable to allocate report canvas.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);

        $title = (string) ($report['title'] ?? 'Report');
        $generatedAt = (string) ($meta['generatedAt'] ?? '');
        $summary = is_array($meta['summary'] ?? null) ? $meta['summary'] : [];
        $summaryLines = $layout['summaryLines'] ?? [];

        $titleY = 118;
        $metaY = 156;
        $tableY = (int) $layout['tableY'];
        $summaryHeight = (int) ($layout['summaryHeight'] ?? 0);

        $titleColorAlloc = imagecolorallocate($image, $titleColor[0], $titleColor[1], $titleColor[2]);
        $mutedColorAlloc = imagecolorallocate($image, $mutedColor[0], $mutedColor[1], $mutedColor[2]);
        $gridColorAlloc = imagecolorallocate($image, $gridColor[0], $gridColor[1], $gridColor[2]);
        $headerFillAlloc = imagecolorallocate($image, $headerFill[0], $headerFill[1], $headerFill[2]);
        $summaryFillAlloc = imagecolorallocate($image, $summaryFill[0], $summaryFill[1], $summaryFill[2]);

        imagettftext($image, 30, 0, $margin, $titleY, $titleColorAlloc, $fontPath, $title);
        if ($generatedAt !== '') {
            imagettftext($image, 16, 0, $margin, $metaY, $mutedColorAlloc, $fontPath, 'Generated at: ' . $generatedAt);
        }

        if ($firstPage && $summaryLines) {
            imagefilledrectangle($image, $margin, 182, $pageWidth - $margin, 214 + (count($summaryLines) * 24), $summaryFillAlloc);
            $summaryText = implode(' | ', $summaryLines);
            reportPdfDrawWrappedText($image, $fontPath, 14, $margin + 16, 210, $contentWidth - 32, $mutedColorAlloc, $summaryText, 22);
        }

        $columnWidths = $layout['columnWidths'] ?? reportPdfColumnWidths($columns, $contentWidth);
        $headerHeight = (int) $layout['headerHeight'];
        imagefilledrectangle($image, $margin, $tableY, $pageWidth - $margin, $tableY + $headerHeight, $headerFillAlloc);

        $x = $margin + 12;
        foreach ($columns as $index => $column) {
            $label = (string) ($column['label'] ?? $column['key'] ?? '');
            imagettftext($image, 14, 0, $x, $tableY + 28, $titleColorAlloc, $fontPath, $label);
            $x += $columnWidths[$index] ?? 80;
        }

        $y = (int) $layout['bodyStartY'];
        $lineHeight = (int) $layout['lineHeight'];
        $bodySize = (int) $layout['bodySize'];
        $pageBottom = (int) $layout['pageBottom'];
        $usableRowWidth = $layout['usableRowWidth'] ?? array_map(static function ($width) {
            return max(40, $width - 24);
        }, $columnWidths);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $cellLines = [];
            $rowHeight = reportPdfMeasureRowHeight($row, $columns, $usableRowWidth, $fontPath, $bodySize, $lineHeight);

            if ($y + $rowHeight > $pageBottom) {
                break;
            }

            foreach ($columns as $index => $column) {
                $key = (string) ($column['key'] ?? '');
                $value = $row[$key] ?? '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $cellLines[$index] = reportPdfWrapUtf8Text($fontPath, $bodySize, (string) $value, $usableRowWidth[$index] ?? 80);
            }

            $cellX = $margin;
            foreach ($columns as $index => $column) {
                $width = $columnWidths[$index] ?? 80;
                imagerectangle($image, $cellX, $y, $cellX + $width, $y + $rowHeight, $gridColorAlloc);

                $textY = $y + 26;
                foreach ($cellLines[$index] ?? [''] as $line) {
                    imagettftext($image, $bodySize, 0, $cellX + 12, $textY, $titleColorAlloc, $fontPath, $line);
                    $textY += $lineHeight;
                }
                $cellX += $width;
            }

            $y += $rowHeight;
        }

        $tmpDir = sys_get_temp_dir();
        $tmpFile = tempnam($tmpDir, 'wave1_pdf_');
        if ($tmpFile === false) {
            imagedestroy($image);
            throw new RuntimeException('Unable to allocate temp file for PDF page.');
        }

        $jpegPath = $tmpFile . '.jpg';
        if (!imagejpeg($image, $jpegPath, 92)) {
            imagedestroy($image);
            @unlink($tmpFile);
            throw new RuntimeException('Unable to render report page image.');
        }

        imagedestroy($image);
        @unlink($tmpFile);

        return $jpegPath;
    }
}

if (!function_exists('reportPdfRenderPageImages')) {
    function reportPdfRenderPageImages(array $report): array
    {
        $columns = is_array($report['columns'] ?? null) ? $report['columns'] : [];
        $rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
        $meta = is_array($report['meta'] ?? null) ? $report['meta'] : [];
        $fontPath = reportPdfResolveFontPath();
        $pages = [];

        if (!$rows) {
            $pages[] = reportPdfRenderPageImage($report, [], $columns, $meta, $fontPath, true);
            return $pages;
        }

        $index = 0;
        $isFirstPage = true;
        while ($index < count($rows)) {
            $layout = reportPdfBuildLayoutContext($columns, $meta, $fontPath, $isFirstPage);
            $fitCount = 0;
            $cursorY = (int) $layout['bodyStartY'];
            $pageBottom = (int) $layout['pageBottom'];
            $lineHeight = (int) $layout['lineHeight'];
            $bodySize = (int) $layout['bodySize'];
            $usableRowWidth = $layout['usableRowWidth'] ?? [];

            while (($index + $fitCount) < count($rows)) {
                $row = $rows[$index + $fitCount];
                $rowHeight = reportPdfMeasureRowHeight($row, $columns, $usableRowWidth, $fontPath, $bodySize, $lineHeight);
                if ($cursorY + $rowHeight > $pageBottom && $fitCount > 0) {
                    break;
                }

                $cursorY += $rowHeight;
                $fitCount++;

                if ($cursorY >= $pageBottom) {
                    break;
                }
            }

            $fitCount = max(1, $fitCount);
            $chunk = array_slice($rows, $index, $fitCount);
            $pages[] = reportPdfRenderPageImage($report, $chunk, $columns, $meta, $fontPath, $isFirstPage, $layout);
            $index += $fitCount;
            $isFirstPage = false;
        }

        return $pages;
    }
}

if (!function_exists('outputReportPdf')) {
    function outputReportPdf(array $report, string $filename = 'report.pdf', string $destination = 'I')
    {
        $meta = is_array($report['meta'] ?? null) ? $report['meta'] : [];
        $pageImages = reportPdfRenderPageImages($report);
        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->SetAutoPageBreak(false, 0);

        try {
            foreach ($pageImages as $pageImage) {
                $pdf->AddPage('L', 'A4');
                $pdf->Image($pageImage, 0, 0, 297, 210);
            }

            if ($destination === 'S') {
                return $pdf->Output('S', $filename);
            }

            if ($destination === 'F') {
                return $pdf->Output('F', $filename);
            }

            return $pdf->Output('I', $filename);
        } finally {
            foreach ($pageImages as $pageImage) {
                if (is_string($pageImage) && is_file($pageImage)) {
                    @unlink($pageImage);
                }
            }
        }
    }
}
