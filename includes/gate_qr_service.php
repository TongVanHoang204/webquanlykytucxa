<?php

if (!function_exists('gateQrReadSecretFromEnvFile')) {
    function gateQrReadSecretFromEnvFile(array $keys): ?string
    {
        $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            return null;
        }

        $env = parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if (!is_array($env)) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($env[$key]) && trim((string) $env[$key]) !== '') {
                return trim((string) $env[$key]);
            }
        }

        return null;
    }
}

if (!function_exists('gateQrSecret')) {
    function gateQrSecret(?string $fallback = null): string
    {
        $keys = ['WAVE1_GATE_SECRET', 'WAVE1_REALTIME_SECRET', 'APP_KEY'];
        foreach ($keys as $key) {
            $value = getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
                return trim((string) $_ENV[$key]);
            }
        }

        $fileValue = gateQrReadSecretFromEnvFile($keys);
        if (is_string($fileValue) && $fileValue !== '') {
            return $fileValue;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        throw new RuntimeException('Missing gate signing secret.');
    }
}

if (!function_exists('gateQrHash')) {
    function gateQrHash(string $token): string
    {
        return hash_hmac('sha256', $token, gateQrSecret());
    }
}

if (!function_exists('gateQrLoadStudent')) {
    function gateQrLoadStudent(mysqli $conn, int $studentId): ?array
    {
        $stmt = $conn->prepare('SELECT StudentID, UserID, StudentCode, FullName, IsInDorm FROM students WHERE StudentID = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare student lookup.');
        }

        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('gateQrLoadCardId')) {
    function gateQrLoadCardId(mysqli $conn, int $studentId): ?int
    {
        $stmt = $conn->prepare('SELECT CardID FROM accesscards WHERE StudentID = ? AND IsActive = 1 ORDER BY IssuedDate DESC, CardID DESC LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare card lookup.');
        }

        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $cardId = null;
        if ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $cardId = (int) $row['CardID'];
        }
        $stmt->close();

        return $cardId;
    }
}

if (!function_exists('issueStudentGateQrToken')) {
    function issueStudentGateQrToken(mysqli $conn, int $studentId, int $ttlSeconds = 60): array
    {
        $student = gateQrLoadStudent($conn, $studentId);
        if (!$student) {
            throw new RuntimeException('Student not found.');
        }

        if ((int) ($student['IsInDorm'] ?? 0) !== 1) {
            throw new RuntimeException('Student is not currently in dorm.');
        }

        $rawToken = bin2hex(random_bytes(24));
        $tokenHash = gateQrHash($rawToken);
        $expiresAt = (new DateTimeImmutable('now'))->modify('+' . max(1, $ttlSeconds) . ' seconds')->format('Y-m-d H:i:s');

        $conn->begin_transaction();
        try {
            $lockStmt = $conn->prepare('SELECT StudentID FROM students WHERE StudentID = ? LIMIT 1 FOR UPDATE');
            if (!$lockStmt) {
                throw new RuntimeException('Unable to prepare student token lock.');
            }
            $lockStmt->bind_param('i', $studentId);
            $lockStmt->execute();
            $lockStmt->close();

            $invalidateStmt = $conn->prepare('UPDATE gate_qr_tokens SET IsUsed = 1 WHERE StudentID = ? AND IsUsed = 0');
            if (!$invalidateStmt) {
                throw new RuntimeException('Unable to prepare prior token invalidation.');
            }
            $invalidateStmt->bind_param('i', $studentId);
            if (!$invalidateStmt->execute()) {
                $error = $invalidateStmt->error ?: 'Unknown token invalidation failure.';
                $invalidateStmt->close();
                throw new RuntimeException($error);
            }
            $invalidateStmt->close();

            $stmt = $conn->prepare('INSERT INTO gate_qr_tokens (StudentID, TokenHash, ExpiresAt) VALUES (?, ?, ?)');
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare gate token insert.');
            }

            $stmt->bind_param('iss', $studentId, $tokenHash, $expiresAt);
            if (!$stmt->execute()) {
                $error = $stmt->error ?: 'Unknown gate token insert failure.';
                $stmt->close();
                throw new RuntimeException($error);
            }
            $tokenId = (int) $stmt->insert_id;
            $stmt->close();
            if ($tokenId <= 0) {
                throw new RuntimeException('Gate token insert did not return an ID.');
            }
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        return [
            'TokenID' => $tokenId,
            'StudentID' => $studentId,
            'StudentName' => $student['FullName'] ?? '',
            'Token' => $rawToken,
            'TokenHash' => $tokenHash,
            'ExpiresAt' => $expiresAt,
            'ExpiresInSeconds' => max(1, $ttlSeconds),
        ];
    }
}

if (!function_exists('validateGateQrToken')) {
    function validateGateQrToken(mysqli $conn, string $token): array
    {
        $tokenHash = gateQrHash($token);
        $stmt = $conn->prepare(
            'SELECT t.TokenID, t.StudentID, t.TokenHash, t.ExpiresAt, t.IsUsed, s.FullName, s.StudentCode, s.IsInDorm
             FROM gate_qr_tokens t
             INNER JOIN students s ON s.StudentID = t.StudentID
             WHERE t.TokenHash = ? LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare gate token validation.');
        }

        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return ['valid' => false, 'reason' => 'token_not_found', 'tokenHash' => $tokenHash];
        }

        if ((int) ($row['IsUsed'] ?? 0) === 1) {
            return ['valid' => false, 'reason' => 'token_used', 'tokenHash' => $tokenHash, 'studentId' => (int) $row['StudentID']];
        }

        if (strtotime((string) ($row['ExpiresAt'] ?? '')) < time()) {
            return ['valid' => false, 'reason' => 'token_expired', 'tokenHash' => $tokenHash, 'studentId' => (int) $row['StudentID']];
        }

        return [
            'valid' => true,
            'reason' => null,
            'tokenHash' => $tokenHash,
            'TokenID' => (int) $row['TokenID'],
            'StudentID' => (int) $row['StudentID'],
            'FullName' => $row['FullName'] ?? '',
            'StudentCode' => $row['StudentCode'] ?? '',
            'ExpiresAt' => $row['ExpiresAt'] ?? null,
            'IsInDorm' => (int) ($row['IsInDorm'] ?? 0),
        ];
    }
}

if (!function_exists('gateQrLockTokenRow')) {
    function gateQrLockTokenRow(mysqli $conn, string $tokenHash): ?array
    {
        $stmt = $conn->prepare(
            'SELECT t.TokenID, t.StudentID, t.TokenHash, t.ExpiresAt, t.IsUsed, s.FullName, s.StudentCode, s.IsInDorm
             FROM gate_qr_tokens t
             INNER JOIN students s ON s.StudentID = t.StudentID
             WHERE t.TokenHash = ?
             LIMIT 1
             FOR UPDATE'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare gate token lock query.');
        }

        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('gateQrInferDirection')) {
    function gateQrInferDirection(mysqli $conn, int $studentId): string
    {
        $stmt = $conn->prepare(
            'SELECT Direction FROM gate_access_logs WHERE StudentID = ? AND Status = "accepted" ORDER BY CreatedAt DESC, LogID DESC LIMIT 1'
        );
        if (!$stmt) {
            return 'IN';
        }

        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $direction = 'IN';
        if ($result instanceof mysqli_result && ($row = $result->fetch_assoc())) {
            $direction = strtoupper((string) ($row['Direction'] ?? 'IN')) === 'IN' ? 'OUT' : 'IN';
        }
        $stmt->close();

        return $direction;
    }
}

if (!function_exists('gateQrHasRecentDuplicate')) {
    function gateQrHasRecentDuplicate(mysqli $conn, int $studentId, string $direction, int $windowSeconds = 15): bool
    {
        $stmt = $conn->prepare(
            'SELECT LogID FROM gate_access_logs WHERE StudentID = ? AND Direction = ? AND Status = "accepted" AND CreatedAt >= DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare gate duplicate lookup.');
        }

        $stmt->bind_param('isi', $studentId, $direction, $windowSeconds);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result instanceof mysqli_result && $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('gateQrInsertRejectedLog')) {
    function gateQrInsertRejectedLog(mysqli $conn, int $studentId, ?int $cardId, int $scannedByUserId, string $gateName, string $direction, ?string $tokenHash, string $reason): ?int
    {
        if ($studentId <= 0) {
            return null;
        }

        $status = 'rejected';
        $scanMethod = 'QR';
        $stmt = $conn->prepare(
            'INSERT INTO gate_access_logs (StudentID, CardID, GateName, Direction, ScannedByUserID, ScanMethod, ScanToken, Status, RejectReason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('iississss', $studentId, $cardId, $gateName, $direction, $scannedByUserId, $scanMethod, $tokenHash, $status, $reason);
        $stmt->execute();
        $logId = (int) $stmt->insert_id;
        $stmt->close();

        return $logId;
    }
}

if (!function_exists('registerGateScan')) {
    function registerGateScan(mysqli $conn, int $studentId, string $token, int $scannedByUserId, string $gateName = 'Main Gate', ?string $direction = null): array
    {
        $conn->begin_transaction();

        try {
            $tokenHash = gateQrHash($token);
            $tokenInfo = gateQrLockTokenRow($conn, $tokenHash);
            $cardId = gateQrLoadCardId($conn, $studentId);

            if (!$tokenInfo) {
                $reason = 'token_not_found';
                gateQrInsertRejectedLog($conn, $studentId, $cardId, $scannedByUserId, $gateName, $direction ?? 'IN', $tokenHash, $reason);
                $conn->commit();
                return [
                    'ok' => false,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'tokenHash' => $tokenHash,
                ];
            }

            if ((int) ($tokenInfo['IsUsed'] ?? 0) === 1) {
                $reason = 'token_used';
                gateQrInsertRejectedLog($conn, $studentId, $cardId, $scannedByUserId, $gateName, $direction ?? 'IN', $tokenHash, $reason);
                $conn->commit();
                return [
                    'ok' => false,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'tokenHash' => $tokenHash,
                ];
            }

            if (strtotime((string) ($tokenInfo['ExpiresAt'] ?? '')) < time()) {
                $reason = 'token_expired';
                gateQrInsertRejectedLog($conn, $studentId, $cardId, $scannedByUserId, $gateName, $direction ?? 'IN', $tokenHash, $reason);
                $conn->commit();
                return [
                    'ok' => false,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'tokenHash' => $tokenHash,
                ];
            }

            if ((int) $tokenInfo['StudentID'] !== $studentId) {
                $reason = 'student_mismatch';
                gateQrInsertRejectedLog($conn, $studentId, $cardId, $scannedByUserId, $gateName, $direction ?? 'IN', $tokenHash, $reason);
                $conn->commit();
                return [
                    'ok' => false,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'tokenHash' => $tokenHash,
                ];
            }

            $student = [
                'FullName' => $tokenInfo['FullName'] ?? '',
                'StudentCode' => $tokenInfo['StudentCode'] ?? '',
                'IsInDorm' => (int) ($tokenInfo['IsInDorm'] ?? 0),
            ];
            if ((int) ($student['IsInDorm'] ?? 0) !== 1) {
                $reason = 'student_not_active_in_dorm';
                gateQrInsertRejectedLog($conn, $studentId, $cardId, $scannedByUserId, $gateName, $direction ?? 'IN', $tokenHash, $reason);
                $conn->commit();
                return [
                    'ok' => false,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'tokenHash' => $tokenHash,
                ];
            }

            $resolvedDirection = strtoupper((string) ($direction ?? ''));
            if ($resolvedDirection !== 'IN' && $resolvedDirection !== 'OUT') {
                $resolvedDirection = gateQrInferDirection($conn, $studentId);
            }

            if (gateQrHasRecentDuplicate($conn, $studentId, $resolvedDirection, 15)) {
                $reason = 'duplicate_scan_window';
                gateQrInsertRejectedLog($conn, $studentId, $cardId, $scannedByUserId, $gateName, $resolvedDirection, $tokenHash, $reason);
                $conn->commit();
                return [
                    'ok' => false,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'direction' => $resolvedDirection,
                    'tokenHash' => $tokenHash,
                ];
            }

            $stmt = $conn->prepare('UPDATE gate_qr_tokens SET IsUsed = 1 WHERE TokenID = ? AND IsUsed = 0');
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare gate token consume.');
            }
            $stmt->bind_param('i', $tokenInfo['TokenID']);
            if (!$stmt->execute()) {
                $error = $stmt->error ?: 'Unknown gate token consume failure.';
                $stmt->close();
                throw new RuntimeException($error);
            }
            if ($stmt->affected_rows < 1) {
                $stmt->close();
                $reason = 'token_used';
                gateQrInsertRejectedLog($conn, $studentId, $cardId, $scannedByUserId, $gateName, $resolvedDirection, $tokenHash, $reason);
                $conn->commit();
                return [
                    'ok' => false,
                    'status' => 'rejected',
                    'reason' => $reason,
                    'direction' => $resolvedDirection,
                    'tokenHash' => $tokenHash,
                ];
            }
            $stmt->close();

            $status = 'accepted';
            $scanMethod = 'QR';
            $rejectReason = null;
            $stmt = $conn->prepare(
                'INSERT INTO gate_access_logs (StudentID, CardID, GateName, Direction, ScannedByUserID, ScanMethod, ScanToken, Status, RejectReason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare gate log insert.');
            }

            $stmt->bind_param('iississss', $studentId, $cardId, $gateName, $resolvedDirection, $scannedByUserId, $scanMethod, $tokenHash, $status, $rejectReason);
            if (!$stmt->execute()) {
                $error = $stmt->error ?: 'Unknown gate log insert failure.';
                $stmt->close();
                throw new RuntimeException($error);
            }
            $logId = (int) $stmt->insert_id;
            $stmt->close();
            if ($logId <= 0) {
                throw new RuntimeException('Gate access log insert did not return an ID.');
            }

            $conn->commit();

            return [
                'ok' => true,
                'status' => 'accepted',
                'direction' => $resolvedDirection,
                'logId' => $logId,
                'studentId' => $studentId,
                'studentName' => $student['FullName'] ?? '',
                'cardId' => $cardId,
                'tokenHash' => $tokenHash,
                'gateName' => $gateName,
            ];
        } catch (Throwable $e) {
            if ($conn->errno === 0) {
                // fall through to rollback and surface a rejected response upstream
            }
            $conn->rollback();
            return [
                'ok' => false,
                'status' => 'rejected',
                'reason' => 'exception',
                'message' => $e->getMessage(),
            ];
        }
    }
}
