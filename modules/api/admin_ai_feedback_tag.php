<?php
/**
 * API: /modules/api/admin_ai_feedback_tag.php
 * POST Body: { "feedback_id": 123 }  OR  { "title": "...", "content": "..." }
 * Response: { "ok": true, "tags": ["Hư đèn", "Khẩn cấp"], "severity": "high|medium|low", "category": "Điện" }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$conn->set_charset('utf8mb4');

$input = json_decode(file_get_contents('php://input'), true);
$feedbackId = (int)($input['feedback_id'] ?? 0);
$title   = trim($input['title']   ?? '');
$content = trim($input['content'] ?? '');

// Nếu có feedback_id, load từ DB
if ($feedbackId > 0) {
    $st = $conn->prepare("SELECT Title, Content FROM Feedbacks WHERE FeedbackID = ? LIMIT 1");
    $st->bind_param('i', $feedbackId);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    if (!$res) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Không tìm thấy phản ánh.']);
        exit;
    }
    $title   = $res['Title'];
    $content = $res['Content'];
    $st->close();
}

if (empty($title) && empty($content)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Thiếu nội dung phản ánh.']);
    exit;
}

$systemPrompt = "Bạn là hệ thống phân tích phản ánh sinh viên của Ký túc xá. Hãy phân tích nội dung phản ánh và trả về JSON với cấu trúc chính xác sau (KHÔNG thêm gì khác):
{
  \"tags\": [\"tag1\", \"tag2\"],
  \"severity\": \"high|medium|low\",
  \"category\": \"Điện|Nước|Vệ sinh|An ninh|Thiết bị|Hành vi|Khác\",
  \"summary\": \"Tóm tắt 1 câu ngắn\"
}

Quy tắc severity: high = mất điện/nước/đánh nhau/khói lửa, medium = hỏng hóc thiết bị/tiếng ồn lớn, low = thẩm mỹ/đề xuất cải thiện.
Tags mẫu: Hư đèn, Vỡ ống nước, Mất điện, Tiếng ồn, Mùi hôi, Côn trùng, Ổ khóa hỏng, Điều hòa hỏng, Wifi yếu, Vi phạm nội quy, Cần sửa chữa khẩn.";

$userPrompt = "Tiêu đề phản ánh: $title\nNội dung: $content";

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

    if ($httpCode !== 200 || !$raw) {
        throw new RuntimeException("Ollama HTTP $httpCode");
    }

    $resp = json_decode($raw, true);
    $replyText = trim($resp['message']['content'] ?? '');
    $replyText = preg_replace('/^```json\s*/i', '', $replyText);
    $replyText = preg_replace('/\s*```$/', '', $replyText);

    $result = json_decode($replyText, true);
    if (!$result || !isset($result['severity'])) {
        throw new RuntimeException("Invalid AI response");
    }

    // Nếu có feedback_id: lưu tags vào DB (cột AITags nếu có)
    if ($feedbackId > 0) {
        $tagsJson = json_encode($result['tags'] ?? [], JSON_UNESCAPED_UNICODE);
        $severity = $result['severity'];
        $category = $result['category'] ?? 'Khác';
        // Thêm cột nếu chưa có
        @$conn->query("ALTER TABLE Feedbacks ADD COLUMN AITags JSON NULL, ADD COLUMN AISeverity VARCHAR(10) NULL, ADD COLUMN AICategory VARCHAR(50) NULL");
        $upd = $conn->prepare("UPDATE Feedbacks SET AITags=?, AISeverity=?, AICategory=? WHERE FeedbackID=?");
        $upd->bind_param('sssi', $tagsJson, $severity, $category, $feedbackId);
        $upd->execute();
        $upd->close();
    }

    echo json_encode(['ok' => true] + $result);
} catch (Throwable $e) {
    // Rule-based fallback
    $text = strtolower($title . ' ' . $content);
    $tags = [];
    $severity = 'low';
    $category = 'Khác';

    if (preg_match('/điện|đèn|cúp điện|mất điện/', $text)) { $tags[] = 'Điện'; $category = 'Điện'; $severity = 'high'; }
    if (preg_match('/nước|ống|vỡ|tắc/', $text)) { $tags[] = 'Nước'; $category = 'Nước'; $severity = 'high'; }
    if (preg_match('/hỏng|hư|sửa|broken/', $text)) { $tags[] = 'Cần sửa chữa'; $severity = 'medium'; }
    if (preg_match('/wifi|mạng|internet/', $text)) { $tags[] = 'Wifi yếu'; $category = 'Thiết bị'; $severity = 'medium'; }
    if (preg_match('/mùi|hôi|bẩn|rác/', $text)) { $tags[] = 'Vệ sinh'; $category = 'Vệ sinh'; }
    if (preg_match('/tiếng ồn|ồn ào|ầm ĩ/', $text)) { $tags[] = 'Tiếng ồn'; $category = 'Hành vi'; }
    if (empty($tags)) $tags = ['Khác'];

    echo json_encode([
        'ok' => true,
        'tags' => $tags,
        'severity' => $severity,
        'category' => $category,
        'summary' => mb_substr("$title - $content", 0, 60) . '...',
        '_fallback' => true,
    ]);
}
