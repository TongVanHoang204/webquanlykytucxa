<?php
/**
 * API: /modules/api/admin_ai_search.php
 * POST Body: { "query": "hóa đơn chưa đóng tháng 3 sinh viên nam tòa A" }
 * Response: { "ok": true, "sql_filter": {...}, "results": [...], "interpretation": "Giải thích" }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['query'] ?? '');

if (empty($query) || mb_strlen($query) < 3) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Vui lòng nhập truy vấn tìm kiếm.']);
    exit;
}

$systemPrompt = <<<'SYSPROMPT'
Bạn là bộ phân tích ngôn ngữ tự nhiên chuyên truy vấn cơ sở dữ liệu Ký túc xá sinh viên.
Cơ sở dữ liệu gồm các bảng: Students (StudentID, FullName, StudentCode, Gender, IsInDorm), Rooms (RoomNumber, Status, RoomType), Buildings (BuildingName), Contracts (Status, StartDate, EndDate), Invoices (Month, Year, Status, TotalAmount, DueDate), Feedbacks (Title, Content, Status).

Nhiệm vụ: Phân tích câu hỏi của Admin và trả về JSON xác định loại tìm kiếm và tham số lọc.

Format JSON output CHÍNH XÁC (không thêm gì khác):
{
  "entity": "invoice|student|room|feedback|contract",
  "filters": {
    "month": null,
    "year": null,
    "status": null,
    "gender": null,
    "building": null,
    "keyword": null,
    "room_type": null
  },
  "interpretation": "Giải thích ngắn gọn bằng tiếng Việt Admin đang tìm gì"
}

Ví dụ: "hóa đơn chưa thanh toán tháng 3" → entity: "invoice", filters: { month: 3, status: "Chưa thanh toán" }
SYSPROMPT;

$userPrompt = "Truy vấn admin: \"$query\"";

$filters = [
    'entity' => 'student',
    'filters' => ['keyword' => $query],
    'interpretation' => "Tìm kiếm: $query"
];

try {
    $ollamaHost = getenv('OLLAMA_HOST') ?: 'http://localhost:11434';
    $ollamaModel = getenv('OLLAMA_MODEL') ?: 'gemini-3-flash-preview:cloud';

    $payload = json_encode([
        'model' => $ollamaModel,
        'stream' => false,
        'format' => 'json',
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ]
    ]);

    $ch = curl_init("$ollamaHost/api/chat");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $raw) {
        $resp = json_decode($raw, true);
        $replyText = trim($resp['message']['content'] ?? '');
        $replyText = preg_replace('/^```json\s*/i', '', $replyText);
        $replyText = preg_replace('/\s*```$/', '', $replyText);
        $parsed = json_decode($replyText, true);
        if ($parsed && isset($parsed['entity'])) {
            $filters = $parsed;
        }
    }
} catch (Throwable $e) {
    // Continue with rule-based filter below
}

// Rule-based fallback parser
if (!isset($filters['entity'])) {
    $q = mb_strtolower($query);
    if (preg_match('/hóa đơn|tiền phòng|tiền điện|công nợ/', $q)) $filters['entity'] = 'invoice';
    elseif (preg_match('/phòng|tòa|building/', $q)) $filters['entity'] = 'room';
    elseif (preg_match('/phản ánh|báo hỏng|feedback/', $q)) $filters['entity'] = 'feedback';
    elseif (preg_match('/hợp đồng|contract/', $q)) $filters['entity'] = 'contract';
    else $filters['entity'] = 'student';
}

// Execute DB query based on parsed filters
$entity = $filters['entity'] ?? 'student';
$f = $filters['filters'] ?? [];
$results = [];

try {
    if ($entity === 'invoice') {
        $sql = "SELECT i.InvoiceID, i.Month, i.Year, i.TotalAmount, i.Status, i.DueDate,
                       s.FullName, s.StudentCode, r.RoomNumber, b.BuildingName
                FROM Invoices i
                JOIN Contracts c ON i.ContractID = c.ContractID
                JOIN Students s ON c.StudentID = s.StudentID
                JOIN Rooms r ON c.RoomID = r.RoomID
                JOIN Buildings b ON r.BuildingID = b.BuildingID
                WHERE 1=1";
        $params = []; $types = '';
        if (!empty($f['status'])) { $sql .= " AND i.Status = ?"; $params[] = $f['status']; $types .= 's'; }
        if (!empty($f['month']))  { $sql .= " AND i.Month = ?";  $params[] = (int)$f['month']; $types .= 'i'; }
        if (!empty($f['year']))   { $sql .= " AND i.Year = ?";   $params[] = (int)$f['year'];  $types .= 'i'; }
        if (!empty($f['building'])) { $sql .= " AND b.BuildingName LIKE ?"; $params[] = "%{$f['building']}%"; $types .= 's'; }
        if (!empty($f['keyword'])) { $sql .= " AND (s.FullName LIKE ? OR s.StudentCode LIKE ?)"; $like = "%{$f['keyword']}%"; $params[] = $like; $params[] = $like; $types .= 'ss'; }
        $sql .= " ORDER BY i.DueDate ASC LIMIT 50";
        $st = $conn->prepare($sql);
        if ($types) { $st->bind_param($types, ...$params); }
        $st->execute();
        $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

    } elseif ($entity === 'student') {
        $sql = "SELECT s.StudentID, s.FullName, s.StudentCode, s.Gender, s.IsInDorm,
                       f.FacultyName, r.RoomNumber, b.BuildingName
                FROM Students s
                LEFT JOIN Faculties f ON f.FacultyID = s.FacultyID
                LEFT JOIN Contracts c ON c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
                LEFT JOIN Rooms r ON r.RoomID = c.RoomID
                LEFT JOIN Buildings b ON b.BuildingID = r.BuildingID
                WHERE 1=1";
        $params = []; $types = '';
        if (!empty($f['gender']))  { $sql .= " AND s.Gender = ?"; $params[] = $f['gender']; $types .= 's'; }
        if (!empty($f['building'])){ $sql .= " AND b.BuildingName LIKE ?"; $params[] = "%{$f['building']}%"; $types .= 's'; }
        if (!empty($f['keyword'])) { $sql .= " AND (s.FullName LIKE ? OR s.StudentCode LIKE ?)"; $like = "%{$f['keyword']}%"; $params[] = $like; $params[] = $like; $types .= 'ss'; }
        $sql .= " ORDER BY s.FullName LIMIT 50";
        $st = $conn->prepare($sql);
        if ($types) { $st->bind_param($types, ...$params); }
        $st->execute();
        $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

    } elseif ($entity === 'feedback') {
        $sql = "SELECT f.FeedbackID, f.Title, f.Content, f.Status, f.CreatedAt,
                       s.FullName, s.StudentCode
                FROM Feedbacks f
                JOIN Students s ON f.StudentID = s.StudentID
                WHERE 1=1";
        $params = []; $types = '';
        if (!empty($f['status']))  { $sql .= " AND f.Status = ?"; $params[] = $f['status']; $types .= 's'; }
        if (!empty($f['keyword'])) { $sql .= " AND (f.Title LIKE ? OR f.Content LIKE ?)"; $like = "%{$f['keyword']}%"; $params[] = $like; $params[] = $like; $types .= 'ss'; }
        $sql .= " ORDER BY f.CreatedAt DESC LIMIT 50";
        $st = $conn->prepare($sql);
        if ($types) { $st->bind_param($types, ...$params); }
        $st->execute();
        $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

    } elseif ($entity === 'room') {
        $sql = "SELECT r.RoomID, r.RoomNumber, r.Status, r.RoomType, r.Capacity, r.RoomPrice,
                       b.BuildingName
                FROM Rooms r
                JOIN Buildings b ON r.BuildingID = b.BuildingID
                WHERE 1=1";
        $params = []; $types = '';
        if (!empty($f['status']))    { $sql .= " AND r.Status = ?";   $params[] = $f['status']; $types .= 's'; }
        if (!empty($f['building']))  { $sql .= " AND b.BuildingName LIKE ?"; $params[] = "%{$f['building']}%"; $types .= 's'; }
        if (!empty($f['room_type'])) { $sql .= " AND r.RoomType LIKE ?"; $params[] = "%{$f['room_type']}%"; $types .= 's'; }
        $sql .= " ORDER BY b.BuildingName, r.RoomNumber LIMIT 50";
        $st = $conn->prepare($sql);
        if ($types) { $st->bind_param($types, ...$params); }
        $st->execute();
        $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();
    }
} catch (Throwable $e) {
    error_log("[admin_ai_search] DB error: " . $e->getMessage());
}

echo json_encode([
    'ok' => true,
    'entity' => $entity,
    'interpretation' => $filters['interpretation'] ?? "Kết quả cho: $query",
    'count' => count($results),
    'results' => $results,
], JSON_UNESCAPED_UNICODE);
