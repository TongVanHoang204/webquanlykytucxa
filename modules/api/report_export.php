<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/report_service.php';
require_once __DIR__ . '/../../includes/report_pdf.php';
require_once __DIR__ . '/../../includes/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

try {
    requireRole(['Admin', 'Manager']);

    $reportKey = trim((string) ($_GET['report_key'] ?? ''));
    $format = strtolower(trim((string) ($_GET['format'] ?? 'xlsx')));
    if ($reportKey === '') {
        throw new InvalidArgumentException('Thiếu report_key.');
    }

    if (!in_array($format, ['xlsx', 'pdf'], true)) {
        throw new InvalidArgumentException('Định dạng export không hợp lệ.');
    }

    $dataset = buildReportDataset($conn, $reportKey, $_GET);
    $filename = $reportKey . '-' . date('Ymd-His');

    if ($format === 'pdf') {
        outputReportPdf($dataset, $filename . '.pdf', 'I');
        exit;
    }

    $columns = array_map(static function ($column) {
        return (string) ($column['label'] ?? $column['key'] ?? '');
    }, $dataset['columns'] ?? []);
    $rows = [];
    foreach (($dataset['rows'] ?? []) as $row) {
        $rows[] = array_map(static function ($column) use ($row) {
            $key = (string) ($column['key'] ?? '');
            $value = $row[$key] ?? '';
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            return (string) $value;
        }, $dataset['columns'] ?? []);
    }

    $sheetRows = array_merge([$columns], $rows);
    $xlsx = SimpleXLSXGen::fromArray($sheetRows ?: [['Không có dữ liệu']], 'Bao cao');
    $xlsx->downloadAs($filename . '.xlsx');
    exit;
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
