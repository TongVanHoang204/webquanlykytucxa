<?php
/**
 * API: /modules/api/admin_ai_draft.php
 * Body: { "topic": "...", "type": "notice|warning|debt_reminder" }
 * Response: { "ok": true, "draft": { "title": "...", "content": "..." } }
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../db_connect.php';
require_once '../../includes/auth_check.php';
requireRole(['Admin', 'Manager']);

$input = json_decode(file_get_contents('php://input'), true);
$topic = trim($input['topic'] ?? '');
$type  = trim($input['type']  ?? 'notice');

if (empty($topic)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Vui lòng nhập chủ đề/ý tưởng thông báo.']);
    exit;
}

$typeLabels = [
    'notice'       => 'thông báo chung cho sinh viên ký túc xá',
    'warning'      => 'cảnh cáo vi phạm nội quy',
    'debt_reminder'=> 'nhắc nhở đóng tiền phòng/điện nước',
    'evacuation'   => 'thông báo khẩn về phòng cháy chữa cháy/di dời',
];
$typeLabel = $typeLabels[$type] ?? 'thông báo chung';

$systemPrompt = "Bạn là chuyên viên hành chính của Ban Quản Lý Ký Túc Xá Sinh Viên. Hãy soạn thảo văn bản thông báo chính thức, lịch sự và chuyên nghiệp bằng tiếng Việt. Phong cách: ngắn gọn, rõ ràng, có đủ thông tin cần thiết. Format output: JSON với 2 trường: title (tiêu đề ngắn) và content (nội dung đầy đủ). KHÔNG thêm markdown, chỉ trả về JSON.";

$userPrompt = "Loại văn bản: $typeLabel
Ý chính Admin muốn truyền đạt: $topic

Soạn thảo thông báo hoàn chỉnh gồm tiêu đề và nội dung (3-5 câu). Kết thúc bằng 'Trân trọng, Ban Quản Lý Ký Túc Xá'.
Trả về JSON: {\"title\": \"...\", \"content\": \"...\"}";

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
        CURLOPT_TIMEOUT => 40,
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

    // Strip markdown fences if any
    $replyText = preg_replace('/^```json\s*/i', '', $replyText);
    $replyText = preg_replace('/\s*```$/', '', $replyText);

    $draft = json_decode($replyText, true);
    if (!$draft || empty($draft['title']) || empty($draft['content'])) {
        throw new RuntimeException("Invalid JSON from AI: $replyText");
    }

    echo json_encode(['ok' => true, 'draft' => $draft]);
} catch (Throwable $e) {
    // Template fallback
    $templates = [
        'debt_reminder' => [
            'title' => "Thông báo nhắc nhở đóng tiền - " . date('m/Y'),
            'content' => "Kính gửi các bạn sinh viên,\n\nBan Quản Lý Ký Túc Xá xin thông báo về việc: $topic.\n\nĐề nghị các bạn sinh viên liên quan vui lòng hoàn tất nghĩa vụ tài chính trong thời gian sớm nhất để tránh ảnh hưởng đến quyền lợi lưu trú.\n\nMọi thắc mắc vui lòng liên hệ văn phòng BQL tại tòa A, tầng 1.\n\nTrân trọng,\nBan Quản Lý Ký Túc Xá"
        ],
        'warning' => [
            'title' => "Cảnh báo vi phạm nội quy - Yêu cầu chấn chỉnh",
            'content' => "Kính gửi các bạn sinh viên,\n\nBan Quản Lý Ký Túc Xá ghi nhận tình trạng: $topic.\n\nĐây là vi phạm nội quy ký túc xá. Ban Quản Lý yêu cầu các bạn sinh viên liên quan chấm dứt ngay hành vi vi phạm. Trường hợp tái phạm sẽ bị xử lý theo quy định.\n\nTrân trọng,\nBan Quản Lý Ký Túc Xá"
        ],
        'notice' => [
            'title' => "Thông báo quan trọng từ Ban Quản Lý",
            'content' => "Kính gửi các bạn sinh viên,\n\nBan Quản Lý Ký Túc Xá xin thông báo: $topic.\n\nĐề nghị các bạn sinh viên chú ý và thực hiện nghiêm túc.\n\nTrân trọng,\nBan Quản Lý Ký Túc Xá"
        ],
    ];
    $fallback = $templates[$type] ?? $templates['notice'];
    echo json_encode(['ok' => true, 'draft' => $fallback, '_fallback' => true]);
}
