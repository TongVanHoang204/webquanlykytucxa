<?php
/**
 * API: /modules/api/admin_ai_insights.php
 * - Gọi từ Dashboard admin để lấy Executive Summary từ AI
 * - Gom data từ DB, đẩy sang Node server (Ollama)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

// Gom dữ liệu tổng quan từ DB
$data = [];

$r = $conn->query("SELECT COUNT(*) AS cnt FROM Invoices WHERE Status='Chưa thanh toán'");
$data['unpaidInvoices'] = (int)($r ? $r->fetch_assoc()['cnt'] : 0);

$r = $conn->query("SELECT SUM(TotalAmount) AS total FROM Invoices WHERE Status='Chưa thanh toán'");
$data['unpaidAmount'] = (float)($r ? $r->fetch_assoc()['total'] ?? 0 : 0);

$r = $conn->query("SELECT COUNT(*) AS cnt FROM Feedbacks WHERE Status IN ('Chưa xử lý', 'Đang xử lý')");
$data['pendingFeedbacks'] = (int)($r ? $r->fetch_assoc()['cnt'] : 0);

$r = $conn->query("SELECT COUNT(*) AS cnt FROM Feedbacks WHERE Status='Chưa xử lý'");
$data['newFeedbacks'] = (int)($r ? $r->fetch_assoc()['cnt'] : 0);

$r = $conn->query("SELECT COUNT(*) AS cnt FROM Rooms WHERE Status='Trống'");
$data['availableRooms'] = (int)($r ? $r->fetch_assoc()['cnt'] : 0);

$r = $conn->query("SELECT COUNT(*) AS cnt FROM Contracts WHERE Status='Hiệu lực'");
$data['activeContracts'] = (int)($r ? $r->fetch_assoc()['cnt'] : 0);

$r = $conn->query("SELECT COUNT(*) AS cnt FROM RoomRequests WHERE Status='Pending'");
$data['pendingRoomRequests'] = (int)($r ? $r->fetch_assoc()['cnt'] : 0);

$r = $conn->query("SELECT COUNT(*) AS cnt FROM Students");
$data['totalStudents'] = (int)($r ? $r->fetch_assoc()['cnt'] : 0);

// Build prompt cho Ollama
$systemPrompt = "Bạn là trợ lý hành chính AI của Ký túc xá sinh viên. Nhiệm vụ của bạn là đọc dữ liệu thực từ hệ thống và đưa ra 1 đoạn báo cáo ngắn gọn (tối đa 3 câu) bằng tiếng Việt. Đoạn báo cáo phải: trực tiếp, thực tế, nêu rõ vấn đề cần chú ý nhất và gợi ý hành động cụ thể. KHÔNG dùng markdown, KHÔNG dùng dấu **, KHÔNG dùng gạch đầu dòng, CHỈ viết văn xuôi.";

$userPrompt = "Dữ liệu hôm nay:
- Hóa đơn chưa thanh toán: {$data['unpaidInvoices']} hóa đơn (tổng " . number_format($data['unpaidAmount']) . " VNĐ)
- Phản ánh chưa/đang xử lý: {$data['pendingFeedbacks']} (trong đó {$data['newFeedbacks']} mới chưa đọc)
- Phòng trống sẵn cho thuê: {$data['availableRooms']} phòng
- Hợp đồng đang hiệu lực: {$data['activeContracts']}
- Đơn xin phòng chờ duyệt: {$data['pendingRoomRequests']}
- Tổng sinh viên: {$data['totalStudents']}

Hãy viết 1 đoạn báo cáo điều hành ngắn gọn, trực tiếp về tình hình hiện tại và điều nào cần ưu tiên xử lý ngay hôm nay.";

try {
    $ollamaHost = getenv('OLLAMA_HOST') ?: 'http://localhost:11434';
    $ollamaModel = getenv('OLLAMA_MODEL') ?: 'gemini-3-flash-preview:cloud';

    $payload = json_encode([
        'model' => $ollamaModel,
        'stream' => false,
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
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$raw) {
        throw new RuntimeException("Ollama HTTP $httpCode");
    }

    $resp = json_decode($raw, true);
    $reply = trim($resp['message']['content'] ?? '');

    if (empty($reply)) {
        throw new RuntimeException("Empty reply from Ollama");
    }

    echo json_encode(['ok' => true, 'summary' => $reply, 'data' => $data]);
} catch (Throwable $e) {
    // Fallback: tự tạo summary từ data khi AI không hoạt động
    $fallback = "Hệ thống đang có {$data['unpaidInvoices']} hóa đơn chưa thanh toán";
    if ($data['pendingFeedbacks'] > 0) {
        $fallback .= " và {$data['pendingFeedbacks']} phản ánh cần xử lý";
    }
    if ($data['pendingRoomRequests'] > 0) {
        $fallback .= ", {$data['pendingRoomRequests']} đơn xin phòng đang chờ duyệt";
    }
    $fallback .= ". Vui lòng kiểm tra và xử lý kịp thời.";

    echo json_encode([
        'ok' => true,
        'summary' => $fallback,
        'data' => $data,
        '_fallback' => true,
        '_error' => $e->getMessage()
    ]);
}
