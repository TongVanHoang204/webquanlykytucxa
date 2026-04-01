<?php

if (!function_exists('reportServiceEnsureUtf8')) {
    function reportServiceEnsureUtf8(mysqli $conn): void
    {
        if (method_exists($conn, 'set_charset')) {
            @$conn->set_charset('utf8mb4');
        }
    }
}

if (!function_exists('reportServiceNormalizeRange')) {
    function reportServiceNormalizeRange(array $filters, int $defaultDaysBack = 30): array
    {
        $now = new DateTimeImmutable('now');
        $defaultStart = $now->modify('-' . max(1, $defaultDaysBack) . ' days');

        $fromRaw = trim((string) ($filters['from'] ?? ''));
        $toRaw = trim((string) ($filters['to'] ?? ''));

        $from = $fromRaw !== '' ? new DateTimeImmutable($fromRaw) : $defaultStart;
        $to = $toRaw !== '' ? new DateTimeImmutable($toRaw) : $now;

        return [
            'from' => $from->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
            'to' => $to->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('reportServiceBindParams')) {
    function reportServiceBindParams(mysqli_stmt $stmt, string $types, array &$params): void
    {
        if ($types === '' || !$params) {
            return;
        }

        $bind = [$types];
        foreach ($params as $index => $value) {
            $bind[] = &$params[$index];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
}

if (!function_exists('reportServiceFetchRows')) {
    function reportServiceFetchRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        reportServiceEnsureUtf8($conn);

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare report query.');
        }

        if ($types !== '' && $params) {
            reportServiceBindParams($stmt, $types, $params);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error ?: 'Unknown report query execution failure.';
            $stmt->close();
            throw new RuntimeException($error);
        }
        $result = $stmt->get_result();
        if (!$result instanceof mysqli_result) {
            $error = $stmt->error ?: 'Unable to read report result set.';
            $stmt->close();
            throw new RuntimeException($error);
        }
        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $stmt->close();
        return $rows;
    }
}

if (!function_exists('reportServiceBuildResponse')) {
    function reportServiceBuildResponse(string $reportKey, string $title, array $columns, array $rows, array $summary, array $filters): array
    {
        return [
            'reportKey' => $reportKey,
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
            'meta' => [
                'summary' => $summary,
                'filters' => $filters,
                'generatedAt' => date('c'),
            ],
        ];
    }
}

if (!function_exists('buildFinanceSummaryDataset')) {
    function buildFinanceSummaryDataset(mysqli $conn, array $filters): array
    {
        $range = reportServiceNormalizeRange($filters, 30);
        $summaryRows = reportServiceFetchRows(
            $conn,
            "SELECT
                COUNT(*) AS InvoiceCount,
                COALESCE(SUM(TotalAmount), 0) AS TotalAmount,
                COALESCE(SUM(CASE WHEN Status = 'Đã thanh toán' THEN TotalAmount ELSE 0 END), 0) AS PaidAmount,
                COALESCE(SUM(CASE WHEN Status IN ('Chưa thanh toán', 'Quá hạn') THEN TotalAmount ELSE 0 END), 0) AS OutstandingAmount
             FROM invoices
             WHERE CreatedAt BETWEEN ? AND ?",
            'ss',
            [$range['from'], $range['to']]
        );

        $breakdownRows = reportServiceFetchRows(
            $conn,
            "SELECT
                Status,
                COUNT(*) AS InvoiceCount,
                COALESCE(SUM(TotalAmount), 0) AS TotalAmount
             FROM invoices
             WHERE CreatedAt BETWEEN ? AND ?
             GROUP BY Status
             ORDER BY Status ASC",
            'ss',
            [$range['from'], $range['to']]
        );

        $summary = $summaryRows[0] ?? [
            'InvoiceCount' => 0,
            'TotalAmount' => 0,
            'PaidAmount' => 0,
            'OutstandingAmount' => 0,
        ];

        return reportServiceBuildResponse(
            'finance_summary',
            'Finance Summary',
            [
                ['key' => 'Status', 'label' => 'Status', 'width' => 50],
                ['key' => 'InvoiceCount', 'label' => 'Invoices', 'width' => 35, 'align' => 'right'],
                ['key' => 'TotalAmount', 'label' => 'Amount', 'width' => 45, 'align' => 'right'],
            ],
            $breakdownRows,
            $summary,
            $range
        );
    }
}

if (!function_exists('buildFinanceDetailDataset')) {
    function buildFinanceDetailDataset(mysqli $conn, array $filters): array
    {
        $range = reportServiceNormalizeRange($filters, 30);
        $sql = "SELECT
                    i.InvoiceID,
                    i.Month,
                    i.Year,
                    i.RoomFee,
                    i.ElectricUsage,
                    i.ElectricPrice,
                    i.WaterUsage,
                    i.WaterPrice,
                    i.TotalAmount,
                    i.Status,
                    i.CreatedAt,
                    i.DueDate,
                    i.PaidAt,
                    c.ContractID,
                    s.StudentCode,
                    s.FullName AS StudentName,
                    r.RoomNumber,
                    b.BuildingName
                FROM invoices i
                INNER JOIN contracts c ON c.ContractID = i.ContractID
                INNER JOIN students s ON s.StudentID = c.StudentID
                LEFT JOIN rooms r ON r.RoomID = c.RoomID
                LEFT JOIN buildings b ON b.BuildingID = r.BuildingID
                WHERE i.CreatedAt BETWEEN ? AND ?";
        $params = [$range['from'], $range['to']];
        $types = 'ss';

        if (!empty($filters['status'])) {
            $sql .= ' AND i.Status = ?';
            $params[] = (string) $filters['status'];
            $types .= 's';
        }
        if (!empty($filters['student_id'])) {
            $sql .= ' AND s.StudentID = ?';
            $params[] = (int) $filters['student_id'];
            $types .= 'i';
        }
        if (!empty($filters['room_id'])) {
            $sql .= ' AND c.RoomID = ?';
            $params[] = (int) $filters['room_id'];
            $types .= 'i';
        }

        $sql .= ' ORDER BY i.Year DESC, i.Month DESC, i.InvoiceID DESC';
        $rows = reportServiceFetchRows($conn, $sql, $types, $params);

        $summary = [
            'InvoiceCount' => count($rows),
            'TotalAmount' => array_reduce($rows, function ($carry, $row) {
                return $carry + (float) ($row['TotalAmount'] ?? 0);
            }, 0.0),
        ];

        return reportServiceBuildResponse(
            'finance_detail',
            'Finance Detail',
            [
                ['key' => 'InvoiceID', 'label' => 'Invoice ID', 'width' => 24, 'align' => 'right'],
                ['key' => 'StudentName', 'label' => 'Student', 'width' => 45],
                ['key' => 'RoomNumber', 'label' => 'Room', 'width' => 22],
                ['key' => 'TotalAmount', 'label' => 'Amount', 'width' => 30, 'align' => 'right'],
                ['key' => 'Status', 'label' => 'Status', 'width' => 28],
                ['key' => 'CreatedAt', 'label' => 'Created At', 'width' => 34],
            ],
            $rows,
            $summary,
            $range
        );
    }
}

if (!function_exists('buildStudentListDataset')) {
    function buildStudentListDataset(mysqli $conn, array $filters): array
    {
        $sql = "SELECT
                    s.StudentID,
                    s.StudentCode,
                    s.FullName,
                    s.Email,
                    s.Phone,
                    s.Gender,
                    s.IsInDorm,
                    s.CreatedAt,
                    f.FacultyName,
                    c.ContractID,
                    c.Status AS ContractStatus,
                    c.StartDate,
                    c.EndDate,
                    r.RoomNumber,
                    b.BuildingName
                FROM students s
                LEFT JOIN faculties f ON f.FacultyID = s.FacultyID
                LEFT JOIN contracts c ON c.ContractID = (
                    SELECT c2.ContractID
                    FROM contracts c2
                    WHERE c2.StudentID = s.StudentID
                    ORDER BY CASE WHEN c2.Status = 'Hiệu lực' THEN 0 ELSE 1 END,
                             c2.CreatedAt DESC,
                             c2.ContractID DESC
                    LIMIT 1
                )
                LEFT JOIN rooms r ON r.RoomID = c.RoomID
                LEFT JOIN buildings b ON b.BuildingID = r.BuildingID
                WHERE 1 = 1";
        $params = [];
        $types = '';

        if (!empty($filters['search'])) {
            $sql .= ' AND (s.FullName LIKE ? OR s.StudentCode LIKE ? OR s.Email LIKE ? OR s.Phone LIKE ?)';
            $keyword = '%' . trim((string) $filters['search']) . '%';
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
            $params[] = $keyword;
            $types .= 'ssss';
        }
        if (!empty($filters['faculty_id'])) {
            $sql .= ' AND s.FacultyID = ?';
            $params[] = (int) $filters['faculty_id'];
            $types .= 'i';
        }
        if (isset($filters['gender']) && $filters['gender'] !== '' && $filters['gender'] !== 'all') {
            $sql .= ' AND s.Gender = ?';
            $params[] = (string) $filters['gender'];
            $types .= 's';
        }
        if (isset($filters['in_dorm']) && $filters['in_dorm'] !== '' && $filters['in_dorm'] !== 'all') {
            $sql .= ' AND s.IsInDorm = ?';
            $params[] = (int) $filters['in_dorm'];
            $types .= 'i';
        }

        $sql .= ' ORDER BY s.FullName ASC, s.StudentCode ASC';
        $rows = reportServiceFetchRows($conn, $sql, $types, $params);

        return reportServiceBuildResponse(
            'students_list',
            'Student List',
            [
                ['key' => 'StudentCode', 'label' => 'Student Code', 'width' => 28],
                ['key' => 'FullName', 'label' => 'Full Name', 'width' => 42],
                ['key' => 'Email', 'label' => 'Email', 'width' => 48],
                ['key' => 'FacultyName', 'label' => 'Faculty', 'width' => 40],
                ['key' => 'RoomNumber', 'label' => 'Room', 'width' => 22],
                ['key' => 'IsInDorm', 'label' => 'In Dorm', 'width' => 18, 'align' => 'center'],
            ],
            $rows,
            ['StudentCount' => count($rows)],
            $filters
        );
    }
}

if (!function_exists('buildContractListDataset')) {
    function buildContractListDataset(mysqli $conn, array $filters): array
    {
        $sql = "SELECT
                    c.ContractID,
                    c.StudentID,
                    c.RoomID,
                    c.StartDate,
                    c.EndDate,
                    c.Deposit,
                    c.Status,
                    c.PaymentStatus,
                    c.CreatedAt,
                    s.StudentCode,
                    s.FullName AS StudentName,
                    r.RoomNumber,
                    b.BuildingName
                FROM contracts c
                INNER JOIN students s ON s.StudentID = c.StudentID
                LEFT JOIN rooms r ON r.RoomID = c.RoomID
                LEFT JOIN buildings b ON b.BuildingID = r.BuildingID
                WHERE 1 = 1";
        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $sql .= ' AND c.Status = ?';
            $params[] = (string) $filters['status'];
            $types .= 's';
        }
        if (!empty($filters['student_id'])) {
            $sql .= ' AND c.StudentID = ?';
            $params[] = (int) $filters['student_id'];
            $types .= 'i';
        }

        $sql .= ' ORDER BY c.CreatedAt DESC, c.ContractID DESC';
        $rows = reportServiceFetchRows($conn, $sql, $types, $params);

        return reportServiceBuildResponse(
            'contracts_list',
            'Contract List',
            [
                ['key' => 'ContractID', 'label' => 'Contract ID', 'width' => 28, 'align' => 'right'],
                ['key' => 'StudentName', 'label' => 'Student', 'width' => 42],
                ['key' => 'RoomNumber', 'label' => 'Room', 'width' => 22],
                ['key' => 'StartDate', 'label' => 'Start Date', 'width' => 30],
                ['key' => 'EndDate', 'label' => 'End Date', 'width' => 30],
                ['key' => 'Status', 'label' => 'Status', 'width' => 24],
            ],
            $rows,
            ['ContractCount' => count($rows)],
            $filters
        );
    }
}

if (!function_exists('buildRoomsOccupancyDataset')) {
    function buildRoomsOccupancyDataset(mysqli $conn, array $filters): array
    {
        $sql = "SELECT
                    r.RoomID,
                    r.RoomNumber,
                    r.RoomType,
                    r.Capacity,
                    r.CurrentOccupants,
                    r.RoomPrice,
                    r.Status,
                    b.BuildingName,
                    COALESCE(active.ActiveContracts, 0) AS ActiveContracts,
                    GREATEST(r.Capacity - COALESCE(active.ActiveContracts, 0), 0) AS VacantSlots,
                    ROUND((COALESCE(active.ActiveContracts, 0) / NULLIF(r.Capacity, 0)) * 100, 2) AS OccupancyRate
                FROM rooms r
                LEFT JOIN buildings b ON b.BuildingID = r.BuildingID
                LEFT JOIN (
                    SELECT RoomID, COUNT(*) AS ActiveContracts
                    FROM contracts
                    WHERE Status = 'Hiệu lực'
                    GROUP BY RoomID
                ) active ON active.RoomID = r.RoomID
                WHERE 1 = 1";
        $params = [];
        $types = '';

        if (!empty($filters['building_id'])) {
            $sql .= ' AND r.BuildingID = ?';
            $params[] = (int) $filters['building_id'];
            $types .= 'i';
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND r.Status = ?';
            $params[] = (string) $filters['status'];
            $types .= 's';
        }

        $sql .= ' ORDER BY b.BuildingName ASC, r.RoomNumber ASC';
        $rows = reportServiceFetchRows($conn, $sql, $types, $params);

        return reportServiceBuildResponse(
            'rooms_occupancy',
            'Rooms Occupancy',
            [
                ['key' => 'BuildingName', 'label' => 'Building', 'width' => 40],
                ['key' => 'RoomNumber', 'label' => 'Room', 'width' => 24],
                ['key' => 'Capacity', 'label' => 'Capacity', 'width' => 20, 'align' => 'right'],
                ['key' => 'ActiveContracts', 'label' => 'Occupied', 'width' => 20, 'align' => 'right'],
                ['key' => 'VacantSlots', 'label' => 'Vacant', 'width' => 20, 'align' => 'right'],
                ['key' => 'OccupancyRate', 'label' => 'Rate %', 'width' => 20, 'align' => 'right'],
            ],
            $rows,
            ['RoomCount' => count($rows)],
            $filters
        );
    }
}

if (!function_exists('buildReportDataset')) {
    function buildReportDataset(mysqli $conn, string $reportKey, array $filters): array
    {
        switch ($reportKey) {
            case 'finance_summary':
                return buildFinanceSummaryDataset($conn, $filters);
            case 'finance_detail':
                return buildFinanceDetailDataset($conn, $filters);
            case 'students_list':
                return buildStudentListDataset($conn, $filters);
            case 'contracts_list':
                return buildContractListDataset($conn, $filters);
            case 'rooms_occupancy':
                return buildRoomsOccupancyDataset($conn, $filters);
            default:
                throw new InvalidArgumentException('Unsupported report key.');
        }
    }
}
