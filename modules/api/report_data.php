<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/report_service.php';

try {
    requireRole(['Admin', 'Manager']);

    $reportKey = trim((string) ($_GET['report_key'] ?? ''));
    if ($reportKey === '') {
        throw new InvalidArgumentException('Thiếu report_key.');
    }

    $dataset = buildReportDataset($conn, $reportKey, $_GET);
    echo json_encode([
        'ok' => true,
        'dataset' => $dataset,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code($e instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
