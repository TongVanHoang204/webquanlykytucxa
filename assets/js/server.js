// ======================================================================
//  CHAT AI SERVER – FULL VERSION (OLLAMA)
//  - USER:  POST /api/chat-ai
//  - ADMIN: POST /api/admin-chat-ai
// ======================================================================

import express from "express";
import cors from "cors";
import "dotenv/config";
import mysql from "mysql2/promise";
import crypto from "node:crypto";
import { WebSocketServer } from "ws";

// ======================================================================
// 0️⃣ CONFIG
// ======================================================================

const OLLAMA_HOST = process.env.OLLAMA_HOST || "http://localhost:11434";
const OLLAMA_MODEL = process.env.OLLAMA_MODEL || "gemini-3-flash-preview:cloud"; // ví dụ: llama3.2, gemma3, mistral...
const REALTIME_SHARED_SECRET =
  process.env.WAVE1_REALTIME_SECRET ||
  process.env.REALTIME_SHARED_SECRET ||
  process.env.APP_KEY ||
  process.env.JWT_SECRET ||
  "";
const REALTIME_PUBLISH_SECRET =
  process.env.WAVE1_REALTIME_PUBLISH_SECRET || REALTIME_SHARED_SECRET;

console.log("🤖 OLLAMA_HOST:", OLLAMA_HOST);
console.log("🤖 OLLAMA_MODEL:", OLLAMA_MODEL);
console.log("📡 REALTIME:", REALTIME_SHARED_SECRET ? "enabled" : "disabled (missing secret)");

// ======================================================================
// 1️⃣ KẾT NỐI DATABASE
// ======================================================================

const db = mysql.createPool({
  host: "localhost",
  user: "root",
  password: "",
  database: "quanlyktx",
  charset: "utf8mb4",
});

// ======================================================================
// 2️⃣ EXPRESS SERVER
// ======================================================================

const app = express();
app.use(cors());
app.use(express.json());

// ======================================================================
// 2.5️⃣ REALTIME HUB
// ======================================================================

const websocketClients = new Map();

function realtimeBase64UrlDecode(value) {
  const padding = value.length % 4;
  const normalized = (padding ? value + "=".repeat(4 - padding) : value)
    .replace(/-/g, "+")
    .replace(/_/g, "/");

  return Buffer.from(normalized, "base64").toString("utf8");
}

function verifyRealtimeToken(token, secret) {
  if (!token || !secret || typeof token !== "string") {
    return null;
  }

  const parts = token.split(".", 2);
  if (parts.length !== 2 || !parts[0] || !parts[1]) {
    return null;
  }

  const [body, signature] = parts;
  const expected = crypto.createHmac("sha256", secret).update(body).digest("hex");
  if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(signature))) {
    return null;
  }

  try {
    const payload = JSON.parse(realtimeBase64UrlDecode(body));
    const uid = Number(payload?.uid || 0);
    const role = String(payload?.role || "");
    const exp = Number(payload?.exp || 0);
    if (!uid || !role || !exp || exp < Math.floor(Date.now() / 1000)) {
      return null;
    }
    return payload;
  } catch {
    return null;
  }
}

function addRealtimeClient(userId, socket) {
  const key = String(userId);
  const current = websocketClients.get(key) || new Set();
  current.add(socket);
  websocketClients.set(key, current);
}

function removeRealtimeClient(userId, socket) {
  const key = String(userId);
  const current = websocketClients.get(key);
  if (!current) {
    return;
  }

  current.delete(socket);
  if (!current.size) {
    websocketClients.delete(key);
  }
}

function publishRealtimeEvent(userIds, event, payload) {
  const normalizedUserIds = [
    ...new Set((userIds || []).map((value) => Number(value)).filter(Boolean)),
  ];
  const packet = JSON.stringify({
    event: event || "notification.created",
    payload: payload || {},
    sentAt: new Date().toISOString(),
  });

  let deliveredConnections = 0;
  normalizedUserIds.forEach((userId) => {
    const sockets = websocketClients.get(String(userId));
    if (!sockets) {
      return;
    }

    sockets.forEach((socket) => {
      if (socket.readyState === socket.OPEN) {
        socket.send(packet);
        deliveredConnections += 1;
      }
    });
  });

  return {
    targetedUsers: normalizedUserIds.length,
    deliveredConnections,
  };
}

app.post("/api/realtime/publish", (req, res) => {
  if (!REALTIME_PUBLISH_SECRET) {
    return res.status(503).json({
      ok: false,
      error: "REALTIME_NOT_CONFIGURED",
    });
  }

  const providedSecret = req.get("X-Realtime-Publish-Secret") || "";
  if (providedSecret !== REALTIME_PUBLISH_SECRET) {
    return res.status(403).json({
      ok: false,
      error: "FORBIDDEN",
    });
  }

  const userIds = Array.isArray(req.body?.userIds) ? req.body.userIds : [];
  const result = publishRealtimeEvent(userIds, req.body?.event, req.body?.payload);

  return res.json({
    ok: true,
    ...result,
  });
});

// ======================================================================
// 3️⃣ OLLAMA CHAT HELPER
// ======================================================================

async function ollamaChat({ system, user, model = OLLAMA_MODEL, format = null }) {
  const body = {
    model,
    stream: false,
    messages: [
      ...(system ? [{ role: "system", content: system }] : []),
      { role: "user", content: user },
    ],
  };

  // format: "json" giúp ép output dễ parse (tùy model, tỉ lệ thành công sẽ khác nhau)
  if (format) body.format = format;

  const r = await fetch(`${OLLAMA_HOST}/api/chat`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });

  if (!r.ok) {
    const t = await r.text().catch(() => "");
    throw new Error(`Ollama HTTP ${r.status}: ${t}`);
  }

  const data = await r.json();
  return (data?.message?.content || "").trim();
}

// ======================================================================
// 4️⃣ HÀM LẤY StudentID TỪ studentData
// ======================================================================

function extractStudentId(studentData) {
  if (!studentData) return null;
  if (studentData.StudentID) return studentData.StudentID;
  if (studentData.student && studentData.student.StudentID) return studentData.student.StudentID;
  return null;
}

// ======================================================================
// 4.5️⃣ HÀM PHÁT HIỆN INTENT (CHO USER)
// ======================================================================

async function detectIntent(prompt) {
  const classifyPrompt = `
Hãy phân loại câu hỏi của người dùng vào đúng intent.
Chỉ trả về MẢNG JSON, không thêm chữ nào khác.

INTENT HỖ TRỢ:
[
  "STUDENT_INFO",
  "ROOM_INFO",
  "CONTRACT_INFO",
  "INVOICE_UNPAID",
  "INVOICE_STATS",
  "FEEDBACK_STATS",
  "ROOMS_AVAILABLE",
  "CONTACT",
  "GENERAL",
  "CALCULATION"
]

CÂU HỎI:
"${prompt}"

Hãy trả về dạng:
["INVOICE_UNPAID"] hoặc ["ROOM_INFO"] hoặc ["GENERAL"]
`.trim();

  try {
    const text = await ollamaChat({
      system: "Bạn là bộ phân loại intent. Trả đúng JSON (mảng intent), không thêm chữ.",
      user: classifyPrompt,
      format: "json",
    });

    const parsed = JSON.parse(text);
    if (Array.isArray(parsed) && parsed.length > 0) return parsed;
    return ["GENERAL"];
  } catch (e) {
    console.error("❌ Lỗi parse intent, fallback GENERAL:", e);
    return ["GENERAL"];
  }
}

// ======================================================================
//  API CHAT AI CHO ADMIN
// ======================================================================

app.post("/api/admin-chat-ai", async (req, res) => {
  try {
    const { prompt, intents = [], useContext = {} } = req.body || {};

    console.log("🟨 [ADMIN-AI] Prompt:", prompt);
    console.log("🟨 [ADMIN-AI] Intents:", intents);

    if (!prompt || typeof prompt !== "string") {
      return res.status(400).json({ success: false, error: "NO_PROMPT" });
    }

    // dbContext_Admin: dữ liệu tổng hợp cho admin
    const dbContext_Admin = {};

    // ===================== 1) TỔNG QUAN PHÒNG / TÒA =====================
    {
      const [allRooms] = await db.query(`
        SELECT 
          COUNT(*) AS TotalRooms,
          SUM(CASE WHEN r.Status = 'Trống' THEN 1 ELSE 0 END) AS AvailableRooms,
          SUM(r.Capacity) AS TotalCapacity,
          COUNT(DISTINCT r.BuildingID) AS TotalBuildings,
          (
            SELECT COUNT(*)
            FROM contracts c
            WHERE c.Status = 'Hiệu lực'
          ) AS TotalActiveContracts
        FROM rooms r
      `);

      const [slots] = await db.query(`
        SELECT 
          SUM(
            GREATEST(
              r.Capacity - (
                SELECT COUNT(*)
                FROM contracts c
                WHERE c.RoomID = r.RoomID
                  AND c.Status = 'Hiệu lực'
              ), 
              0
            )
          ) AS TotalSlotsLeft
        FROM rooms r
      `);

      const room = allRooms[0] || {};
      const totalSlotsLeft = slots?.[0]?.TotalSlotsLeft ? slots[0].TotalSlotsLeft : 0;
      const totalOccupied = room.TotalActiveContracts || 0;

      dbContext_Admin.roomSummary = {
        TotalRooms: room.TotalRooms || 0,
        AvailableRooms: room.AvailableRooms || 0,
        TotalCapacity: room.TotalCapacity || 0,
        TotalBuildings: room.TotalBuildings || 0,
        TotalActiveContracts: room.TotalActiveContracts || 0,
        TotalSlotsLeft: totalSlotsLeft,
        TotalOccupied: totalOccupied,
      };
    }

    // ===================== 2) TỔNG QUAN SINH VIÊN =====================
    {
      const [rows] = await db.query(`
        SELECT
          COUNT(*) AS TotalStudents,
          SUM(CASE WHEN IsInDorm = 1 THEN 1 ELSE 0 END) AS InDorm,
          SUM(CASE WHEN IsInDorm = 0 THEN 1 ELSE 0 END) AS OutDorm,
          SUM(CASE WHEN Gender = 'Nam' THEN 1 ELSE 0 END) AS Male,
          SUM(CASE WHEN Gender = 'Nữ' THEN 1 ELSE 0 END) AS Female,
          SUM(CASE WHEN Gender = 'Khác' THEN 1 ELSE 0 END) AS OtherGender
        FROM students
      `);

      const summary = rows[0] || {};

      const [topFaculties] = await db.query(`
        SELECT 
          f.FacultyName,
          COUNT(*) AS StudentCount
        FROM students s
        LEFT JOIN faculties f ON f.FacultyID = s.FacultyID
        GROUP BY f.FacultyID
        ORDER BY StudentCount DESC
        LIMIT 5
      `);

      dbContext_Admin.studentSummary = {
        TotalStudents: summary.TotalStudents || 0,
        InDorm: summary.InDorm || 0,
        OutDorm: summary.OutDorm || 0,
        Male: summary.Male || 0,
        Female: summary.Female || 0,
        OtherGender: summary.OtherGender || 0,
        TopFaculties: topFaculties || [],
      };
    }

    // ===================== 3) TỔNG QUAN HỢP ĐỒNG =====================
    {
      const [rows] = await db.query(`
        SELECT
          COUNT(*) AS TotalContracts,
          SUM(CASE WHEN Status = 'Hiệu lực' THEN 1 ELSE 0 END) AS ActiveContracts,
          SUM(CASE WHEN Status = 'Hết hạn' THEN 1 ELSE 0 END) AS ExpiredContracts,
          SUM(CASE WHEN Status = 'Đã hủy' THEN 1 ELSE 0 END) AS CancelledContracts,
          SUM(Deposit) AS TotalDeposit
        FROM contracts
      `);

      const c = rows[0] || {};

      dbContext_Admin.contractSummary = {
        TotalContracts: c.TotalContracts || 0,
        ActiveContracts: c.ActiveContracts || 0,
        ExpiredContracts: c.ExpiredContracts || 0,
        CancelledContracts: c.CancelledContracts || 0,
        TotalDeposit: c.TotalDeposit || 0,
      };
    }

    // ===================== 4) TỔNG QUAN HÓA ĐƠN =====================
    {
      const [all] = await db.query(`
        SELECT
          COUNT(*) AS TotalInvoices,
          SUM(CASE WHEN Status = 'Đã thanh toán' THEN 1 ELSE 0 END) AS PaidInvoices,
          SUM(CASE WHEN Status = 'Chưa thanh toán' THEN 1 ELSE 0 END) AS UnpaidInvoices,
          SUM(TotalAmount) AS TotalAmount,
          SUM(CASE WHEN Status = 'Đã thanh toán' THEN TotalAmount ELSE 0 END) AS TotalPaid,
          SUM(CASE WHEN Status = 'Chưa thanh toán' THEN TotalAmount ELSE 0 END) AS TotalUnpaid
        FROM invoices
      `);

      const inv = all[0] || {};

      dbContext_Admin.invoiceSummary = {
        TotalInvoices: inv.TotalInvoices || 0,
        PaidInvoices: inv.PaidInvoices || 0,
        UnpaidInvoices: inv.UnpaidInvoices || 0,
        TotalAmount: inv.TotalAmount || 0,
        TotalPaid: inv.TotalPaid || 0,
        TotalUnpaid: inv.TotalUnpaid || 0,
      };

      const [rows] = await db.query(`
        SELECT 
          i.InvoiceID,
          i.Month,
          i.Year,
          i.TotalAmount,
          i.Status,
          i.DueDate,
          s.FullName AS StudentName,
          s.StudentCode,
          r.RoomNumber,
          b.BuildingName
        FROM invoices i
        JOIN contracts c ON c.ContractID = i.ContractID
        JOIN students  s ON s.StudentID  = c.StudentID
        JOIN rooms     r ON r.RoomID     = c.RoomID
        JOIN buildings b ON b.BuildingID = r.BuildingID
        WHERE i.Status = 'Chưa thanh toán'
        ORDER BY i.DueDate ASC
        LIMIT 100
      `);

      const totalDebt = rows.reduce((sum, invRow) => sum + (Number(invRow.TotalAmount) || 0), 0);

      dbContext_Admin.unpaidInvoicesGlobal = rows;
      dbContext_Admin.unpaidInvoicesSummary = {
        count: rows.length,
        totalAmount: totalDebt,
      };
    }

    // ===================== 5) PHẢN ÁNH / GÓP Ý =====================
    {
      const [rows] = await db.query(`
        SELECT 
          f.FeedbackID,
          f.Title,
          f.Status,
          f.CreatedAt,
          s.FullName AS StudentName,
          r.RoomNumber,
          b.BuildingName
        FROM feedbacks f
        JOIN students s  ON s.StudentID  = f.StudentID
        LEFT JOIN contracts c ON c.StudentID = s.StudentID AND c.Status = 'Hiệu lực'
        LEFT JOIN rooms r     ON r.RoomID    = c.RoomID
        LEFT JOIN buildings b ON b.BuildingID = r.BuildingID
        ORDER BY f.CreatedAt DESC
        LIMIT 100
      `);

      dbContext_Admin.feedbacksGlobal = rows;
      dbContext_Admin.feedbacksGlobalSummary = {
        total: rows.length,
        pending: rows.filter((f) => f.Status !== "Đã xử lý").length,
        done: rows.filter((f) => f.Status === "Đã xử lý").length,
      };
    }

    // ===================== 6) ĐƠN XIN PHÒNG (roomrequests) =====================
    {
      const [rows] = await db.query(`
        SELECT
          COUNT(*) AS TotalRequests,
          SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) AS PendingRequests,
          SUM(CASE WHEN Status = 'Approved' THEN 1 ELSE 0 END) AS ApprovedRequests,
          SUM(CASE WHEN Status = 'Rejected' THEN 1 ELSE 0 END) AS RejectedRequests
        FROM roomrequests
      `);

      const r = rows[0] || {};

      dbContext_Admin.roomRequestSummary = {
        TotalRequests: r.TotalRequests || 0,
        PendingRequests: r.PendingRequests || 0,
        ApprovedRequests: r.ApprovedRequests || 0,
        RejectedRequests: r.RejectedRequests || 0,
      };
    }

    // ===================== 7) THANH TOÁN (payments) =====================
    {
      const [rows] = await db.query(`
        SELECT
          COUNT(*) AS TotalPayments,
          SUM(Amount) AS TotalAmount,
          SUM(CASE WHEN Status = 'Chờ xác nhận' THEN 1 ELSE 0 END) AS PendingPayments,
          SUM(CASE WHEN Status = 'Đã xác nhận' THEN 1 ELSE 0 END) AS ConfirmedPayments,
          SUM(CASE WHEN Status = 'Từ chối' THEN 1 ELSE 0 END) AS RejectedPayments
        FROM payments
      `);

      const p = rows[0] || {};

      dbContext_Admin.paymentsSummary = {
        TotalPayments: p.TotalPayments || 0,
        TotalAmount: p.TotalAmount || 0,
        PendingPayments: p.PendingPayments || 0,
        ConfirmedPayments: p.ConfirmedPayments || 0,
        RejectedPayments: p.RejectedPayments || 0,
      };
    }

    // ===================== 8) THÔNG BÁO ADMIN / SINH VIÊN =====================
    {
      const [adminNoti] = await db.query(`
        SELECT
          COUNT(*) AS TotalAdminNotifications,
          SUM(CASE WHEN IsRead = 0 THEN 1 ELSE 0 END) AS UnreadAdminNotifications
        FROM adminnotifications
      `);

      const an = adminNoti[0] || {};

      dbContext_Admin.adminNotificationSummary = {
        Total: an.TotalAdminNotifications || 0,
        Unread: an.UnreadAdminNotifications || 0,
      };

      const [stuNoti] = await db.query(`
        SELECT
          COUNT(*) AS TotalStudentNotifications,
          SUM(CASE WHEN IsRead = 0 THEN 1 ELSE 0 END) AS UnreadStudentNotifications
        FROM notifications
      `);

      const sn = stuNoti[0] || {};

      dbContext_Admin.studentNotificationSummary = {
        Total: sn.TotalStudentNotifications || 0,
        Unread: sn.UnreadStudentNotifications || 0,
      };
    }

    // ===================== 9) THÔNG BÁO CHUNG (announcements) =====================
    {
      const [sumRows] = await db.query(`SELECT COUNT(*) AS TotalAnnouncements FROM announcements`);

      const [latest] = await db.query(`
        SELECT
          AnnouncementID,
          Title,
          DatePosted,
          PostedBy
        FROM announcements
        ORDER BY DatePosted DESC
        LIMIT 5
      `);

      dbContext_Admin.announcementsSummary = {
        TotalAnnouncements: (sumRows[0] && sumRows[0].TotalAnnouncements) || 0,
      };
      dbContext_Admin.announcementsLatest = latest || [];
    }

    // ===================== 10) MẠNG XÃ HỘI NỘI BỘ =====================
    {
      const [posts] = await db.query(`SELECT COUNT(*) AS TotalPosts FROM posts`);
      const [comments] = await db.query(`SELECT COUNT(*) AS TotalComments FROM postcomments`);
      const [likes] = await db.query(`SELECT COUNT(*) AS TotalReactions FROM postlikes`);

      dbContext_Admin.socialSummary = {
        TotalPosts: (posts[0] && posts[0].TotalPosts) || 0,
        TotalComments: (comments[0] && comments[0].TotalComments) || 0,
        TotalReactions: (likes[0] && likes[0].TotalReactions) || 0,
      };
    }

    // ===================== 11) SYSTEM LOGS (dựa trên contractlogs) =====================
    {
      const { logFilter = {} } = useContext;

      if (intents.includes("SYSTEM_LOGS")) {
        const whereClauses = [];
        const params = [];

        if (logFilter.startDate) {
          whereClauses.push("l.CreatedAt >= ?");
          params.push(logFilter.startDate);
        }
        if (logFilter.endDate) {
          whereClauses.push("l.CreatedAt <= ?");
          params.push(logFilter.endDate);
        }
        if (logFilter.adminName) {
          whereClauses.push("l.PerformedBy LIKE ?");
          params.push(`%${logFilter.adminName}%`);
        }

        const whereSQL = whereClauses.length > 0 ? "WHERE " + whereClauses.join(" AND ") : "";

        const [logs] = await db.query(
          `
          SELECT
            l.LogID,
            l.Action,
            l.Description,
            l.CreatedAt,
            l.PerformedBy AS AdminName,
            c.ContractID,
            s.StudentCode,
            s.FullName AS StudentName,
            r.RoomNumber,
            b.BuildingName
          FROM contractlogs l
          JOIN contracts  c ON c.ContractID = l.ContractID
          JOIN students   s ON s.StudentID = c.StudentID
          JOIN rooms      r ON r.RoomID    = c.RoomID
          JOIN buildings  b ON b.BuildingID = r.BuildingID
          ${whereSQL}
          ORDER BY l.CreatedAt DESC
          LIMIT 100
          `,
          params
        );

        dbContext_Admin.systemLogs = {
          filters: logFilter,
          total: logs.length,
          logs,
        };
      }
    }

    // ===================== 12) TÌM SINH VIÊN (STUDENT_LOOKUP) =====================
    if (intents.includes("STUDENT_LOOKUP")) {
      const searchKey = req.body.searchKey || "";
      if (searchKey) {
        const like = `%${searchKey}%`;
        const [rows] = await db.query(
          `
          SELECT 
            s.StudentID,
            s.StudentCode,
            s.FullName,
            s.ClassName,
            s.CourseYear,
            f.FacultyName
          FROM students s
          LEFT JOIN faculties f ON f.FacultyID = s.FacultyID
          WHERE s.StudentCode LIKE ? OR s.FullName LIKE ?
          LIMIT 20
          `,
          [like, like]
        );
        dbContext_Admin.studentSearch = {
          keyword: searchKey,
          results: rows,
        };
      }
    }

    // ===== SYSTEM PROMPT DÀNH RIÊNG CHO ADMIN =====
    const systemPromptAdmin = `
Bạn là trợ lý AI hỗ trợ cho Admin quản lý ký túc xá.

🎯 VAI TRÒ:
- Đọc và phân tích dữ liệu từ dbContext_Admin.
- Tóm tắt nhanh tình trạng ký túc xá: phòng ở, sinh viên, hợp đồng, hóa đơn, công nợ, phản ánh, đơn xin phòng, thanh toán, thông báo và hoạt động nội bộ.
- Tuyệt đối KHÔNG tự suy đoán hoặc bịa số liệu.

🗣️ CÁCH GIAO TIẾP:
- Luôn trả lời bằng TIẾNG VIỆT.
- Giọng điệu thân thiện, lịch sự, phù hợp với admin.
- Có thể chào hỏi ngắn gọn ở đầu (ví dụ: “Chào anh/chị”, “Mình đã xem qua dữ liệu hiện tại…”).
- KHÔNG dùng dấu *, **, hoặc ký hiệu trang trí dạng markdown.

🧾 CẤU TRÚC CÂU TRẢ LỜI:
- Dòng đầu: tiêu đề + 1 icon phù hợp (ví dụ: 📊 Tổng quan ký túc xá hôm nay:)
- Nội dung: dùng gạch đầu dòng "- " hoặc đánh số "1., 2., 3."
- Không liệt kê toàn bộ danh sách dài nếu không được yêu cầu, chỉ tóm tắt xu hướng hoặc con số chính.
- Dòng cuối: gợi ý nhẹ nhàng, ví dụ:
  "Bạn muốn mình lọc theo tháng, theo tòa nhà hay theo sinh viên cụ thể không?"

⚠️ NGUYÊN TẮC BẮT BUỘC:
- Chỉ sử dụng dữ liệu có trong dbContext_Admin.
- Nếu không có dữ liệu liên quan, nói rõ:
  "Hiện tại mình chưa thấy dữ liệu này trong dbContext_Admin."
- Có thể gợi ý admin kiểm tra bộ lọc hoặc hỏi chi tiết hơn.
- Không dùng từ ngữ phỏng đoán như “có thể”, “ước tính”, “chắc là” nếu không có số liệu.

Mục tiêu của bạn là giúp admin nắm nhanh tình hình và ra quyết định dễ dàng hơn.
`.trim();


    const userPromptAdmin = `
DB CONTEXT (toàn hệ thống cho admin):
${JSON.stringify(dbContext_Admin || {}, null, 2)}

CÂU HỎI CỦA ADMIN:
${prompt}

Hãy trả lời ngắn gọn, rõ ràng, đúng với dữ liệu trên.
Nếu không có dữ liệu phù hợp, hãy nói thẳng và gợi ý admin kiểm tra lại bộ lọc hoặc hỏi cụ thể hơn.
`.trim();

    const reply = await ollamaChat({
      system: systemPromptAdmin,
      user: userPromptAdmin,
    });

    return res.json({ success: true, reply });
  } catch (err) {
    console.error("🟥 [ADMIN-AI] Lỗi khi xử lý:", err);
    return res.status(500).json({
      success: false,
      error: "Lỗi khi xử lý yêu cầu từ admin.",
    });
  }
});

// ======================================================================
// 5️⃣ API CHÍNH: /api/chat-ai USER
// ======================================================================

app.post("/api/chat-ai", async (req, res) => {
  try {
    let { prompt, intents = null, studentData } = req.body || {};

    if (!prompt || typeof prompt !== "string") {
      return res.status(400).json({
        success: false,
        error: "Thiếu prompt gửi lên server.",
      });
    }

    if (!Array.isArray(intents) || intents.length === 0) {
      intents = await detectIntent(prompt);
      console.log("🤖 Auto-intent:", intents);
    }

    console.log("📌 Prompt:", prompt);
    console.log("📌 Intents:", intents);
    console.log("📌 studentData:", studentData);

    const studentId = extractStudentId(studentData);
    console.log("📌 StudentID:", studentId);

    const dbContext = {};

    // === 0. STUDENT_INFO ==================================================
    if (studentId && intents.includes("STUDENT_INFO")) {
      const [rows] = await db.query(
        `
        SELECT 
          s.StudentID,
          s.FullName,
          s.Gender,
          s.BirthDate,
          s.StudentCode,
          s.ClassName,
          s.CourseYear,
          s.Phone,
          s.Email,
          s.Address,
          f.FacultyName
        FROM students s 
        LEFT JOIN faculties f ON f.FacultyID = s.FacultyID 
        WHERE s.StudentID = ?
        LIMIT 1
        `,
        [studentId]
      );
      dbContext.studentProfile = rows[0] || null;
    }

    // === 1. INVOICE_UNPAID ================================================
    if (studentId && intents.includes("INVOICE_UNPAID")) {
      const [rows] = await db.query(
        `
        SELECT 
          i.InvoiceID,
          i.Month,
          i.Year,
          i.RoomFee,
          i.ElectricUsage,
          i.ElectricPrice,
          i.WaterUsage,
          i.WaterPrice,
          i.TotalAmount,
          i.DueDate,
          i.Status,
          i.Note,
          r.RoomNumber,
          b.BuildingName 
        FROM invoices i 
        JOIN contracts c ON c.ContractID = i.ContractID 
        JOIN rooms r ON r.RoomID = c.RoomID 
        JOIN buildings b ON b.BuildingID = r.BuildingID 
        WHERE c.StudentID = ?
          AND i.Status = 'Chưa thanh toán'
        ORDER BY i.DueDate ASC
        `,
        [studentId]
      );

      dbContext.unpaidInvoices = rows;
      dbContext.unpaidSummary = {
        count: rows.length,
        totalAmount: rows.reduce((sum, inv) => sum + (Number(inv.TotalAmount) || 0), 0),
      };
    }

    // === 2. INVOICE_STATS ==================================================
    if (studentId && intents.includes("INVOICE_STATS")) {
      const [rows] = await db.query(
        `
        SELECT 
          i.InvoiceID,
          i.Month,
          i.Year,
          i.RoomFee,
          i.TotalAmount,
          i.Status,
          i.CreatedAt,
          i.PaidAt,
          i.DueDate,
          i.Note 
        FROM invoices i 
        JOIN contracts c ON c.ContractID = i.ContractID 
        WHERE c.StudentID = ?
        ORDER BY i.CreatedAt DESC 
        LIMIT 30
        `,
        [studentId]
      );
      dbContext.invoiceHistory = rows;
    }

    // === 3. ROOM_INFO ======================================================
    if (studentId && intents.includes("ROOM_INFO")) {
      const [rows] = await db.query(
        `
        SELECT 
          r.RoomID,
          r.RoomNumber,
          r.Capacity,
          (
            SELECT COUNT(*) 
            FROM contracts c 
            WHERE c.RoomID = r.RoomID 
              AND c.Status = 'Hiệu lực'
          ) AS CurrentOccupants,
          r.RoomType,
          r.RoomPrice,
          r.Status AS RoomStatus,
          b.BuildingName,
          c.StartDate,
          c.EndDate,
          c.Status AS ContractStatus,
          c.Deposit 
        FROM contracts c 
        JOIN rooms r ON r.RoomID = c.RoomID 
        JOIN buildings b ON b.BuildingID = r.BuildingID 
        WHERE c.StudentID = ?
          AND c.Status = 'Hiệu lực'
        ORDER BY c.StartDate DESC 
        LIMIT 1
        `,
        [studentId]
      );
      dbContext.currentRoom = rows[0] || null;
    }

    // === 3b. CONTRACT_INFO =================================================
    if (studentId && intents.includes("CONTRACT_INFO")) {
      const [rows] = await db.query(
        `
        SELECT 
          c.ContractID,
          c.StartDate,
          c.EndDate,
          c.Status,
          c.Deposit,
          r.RoomNumber,
          b.BuildingName 
        FROM contracts c 
        JOIN rooms r ON r.RoomID = c.RoomID 
        JOIN buildings b ON b.BuildingID = r.BuildingID 
        WHERE c.StudentID = ?
        ORDER BY c.StartDate DESC 
        LIMIT 5
        `,
        [studentId]
      );
      dbContext.contracts = rows;
    }

    // === 4. ROOMS_AVAILABLE ================================================
    if (intents.includes("ROOMS_AVAILABLE")) {
      const [rows] = await db.query(`
        SELECT 
          b.BuildingName,
          r.RoomNumber,
          r.Capacity,
          (
            SELECT COUNT(*) 
            FROM contracts c 
            WHERE c.RoomID = r.RoomID 
              AND c.Status = 'Hiệu lực'
          ) AS CurrentOccupants,
          (r.Capacity - (
              SELECT COUNT(*) 
              FROM contracts c 
              WHERE c.RoomID = r.RoomID 
                AND c.Status = 'Hiệu lực'
          )) AS SlotsLeft,
          r.RoomType,
          r.RoomPrice,
          r.Status AS RoomStatus 
        FROM rooms r 
        JOIN buildings b ON b.BuildingID = r.BuildingID 
        HAVING SlotsLeft > 0 
        ORDER BY b.BuildingName, r.RoomNumber 
        LIMIT 40
      `);

      dbContext.availableRooms = rows;

      const [summary] = await db.query(`
        SELECT 
          COUNT(*) AS TotalRooms,
          SUM(
            CASE WHEN (
              SELECT COUNT(*) 
              FROM contracts c 
              WHERE c.RoomID = r.RoomID 
                AND c.Status = 'Hiệu lực'
            ) < r.Capacity THEN 1 ELSE 0 END
          ) AS TotalAvailableRooms,
          SUM(r.Capacity) AS TotalCapacity,
          SUM(
            (
              SELECT COUNT(*) 
              FROM contracts c 
              WHERE c.RoomID = r.RoomID 
                AND c.Status = 'Hiệu lực'
            )
          ) AS TotalOccupied,
          (
            SUM(r.Capacity) -
            SUM(
              (
                SELECT COUNT(*) 
                FROM contracts c 
                WHERE c.RoomID = r.RoomID 
                  AND c.Status = 'Hiệu lực'
              )
            )
          ) AS TotalSlotsLeft 
        FROM rooms r
      `);

      dbContext.roomSummary = summary[0] || null;
    }

    // === 5. FEEDBACK_STATS ================================================
    if (studentId && intents.includes("FEEDBACK_STATS")) {
      const [rows] = await db.query(
        `
        SELECT 
          FeedbackID,
          Title,
          Content,
          Reply,
          Status,
          ImagePath,
          CreatedAt,
          UpdatedAt 
        FROM feedbacks 
        WHERE StudentID = ?
        ORDER BY CreatedAt DESC 
        LIMIT 20
        `,
        [studentId]
      );

      dbContext.feedbacks = rows;
      dbContext.feedbackSummary = {
        total: rows.length,
        processing: rows.filter((f) => f.Status === "Đang xử lý").length,
        done: rows.filter((f) => f.Status === "Đã xử lý").length,
      };
    }

    // ======================================================================
    // 6. SYSTEM PROMPT CHO USER
    // ======================================================================

    const systemPrompt = `
Bạn là trợ lý AI của ký túc xá. Luôn trả lời bằng TIẾNG VIỆT, thân thiện, rõ ràng, không bịa dữ liệu.

- Nếu câu hỏi về:
  • Thông tin sinh viên → dùng dbContext.studentProfile (nếu có).
  • Phòng hiện tại → dbContext.currentRoom.
  • Hợp đồng → dbContext.contracts.
  • Hóa đơn chưa thanh toán → dbContext.unpaidInvoices + unpaidSummary.
  • Lịch sử hóa đơn → dbContext.invoiceHistory.
  • Phản ánh → dbContext.feedbacks + feedbackSummary.
  • Phòng trống → dbContext.availableRooms + roomSummary.

- Nếu intent = "CONTACT" → trả lời cố định:
  • 📞 Hotline: 1900-xxxx-xxx (hỗ trợ 24/7)
  • ✉️ Email: ktx@university.edu.vn
  • 🏢 Văn phòng: Tòa A - Tầng 1 (8:00–17:00, Thứ 2–Thứ 6)
  • 💻 Online: Gửi phản ánh qua hệ thống hoặc chat với admin
  • 🚨 Khẩn cấp: Liên hệ bảo vệ tại cổng hoặc gọi 113

- Nếu câu hỏi MANG TÍNH CHUNG CHUNG về ký túc xá (không cần dùng dữ liệu trong dbContext),
  hãy trả lời như một mục FAQ, có thể dựa trên các nội dung mẫu sau (được phép diễn đạt lại cho tự nhiên):

  1️⃣ Đăng ký phòng ở KTX:
  - Bước 1: Đăng nhập vào hệ thống bằng tài khoản sinh viên.
  - Bước 2: Vào mục "Đăng ký phòng" hoặc chọn phòng trống trên trang chủ.
  - Bước 3: Điền đầy đủ thông tin và chọn phòng phù hợp.
  - Bước 4: Chờ ban quản lý duyệt yêu cầu (khoảng 1–3 ngày làm việc).
  - Bước 5: Sau khi được duyệt, thanh toán tiền cọc và đến nhận phòng.

  2️⃣ Chính sách thanh toán & hoàn trả:
  - Tiền phòng: Thanh toán theo tháng, hạn chót thường là ngày 5 hàng tháng.
  - Tiền cọc: Thường bằng 1 tháng tiền phòng, được hoàn khi kết thúc hợp đồng.
  - Điện nước: Tính theo đồng hồ thực tế, thanh toán cùng tiền phòng.
  - Phương thức thanh toán: Có thể hỗ trợ VNPay, MoMo, chuyển khoản ngân hàng,...
  - Hoàn trả cọc: Hoàn trong vài ngày sau khi trả phòng (trừ các khoản phạt nếu có hư hỏng hoặc vi phạm).

  3️⃣ Nội quy KTX (ví dụ):
  - Giờ giấc: Đóng cửa ban đêm, mở cửa buổi sáng (ví dụ: đóng 23:00, mở 5:00).
  - Khách thăm: Cần đăng ký, không được ở lại qua đêm.
  - Vệ sinh: Giữ vệ sinh phòng ở và khu vực chung.
  - Cấm: Sử dụng chất kích thích, gây ồn, nuôi động vật, nấu ăn trong phòng,...
  - Tài sản: Giữ gìn tài sản KTX, nếu làm hư hỏng có thể phải bồi thường.

  4️⃣ Tiện nghi phòng & khu vực chung:
  - Trong phòng: Có thể bao gồm giường, tủ quần áo, bàn học, ghế,...
  - Điện/điều hòa: Có quạt trần hoặc điều hòa (tùy loại phòng), ổ cắm điện đầy đủ.
  - Vệ sinh: Có nhà vệ sinh riêng hoặc dùng chung (tùy loại phòng).
  - Internet: WiFi miễn phí hoặc thu phí nhỏ, tốc độ dùng cho học tập.
  - Tiện ích chung: Có thể có máy giặt, phòng tập gym, khu sinh hoạt chung, khu BBQ,...

  5️⃣ Quy trình trả phòng:
  - Bước 1: Thông báo cho ban quản lý trước một khoảng thời gian (ví dụ: 15 ngày).
  - Bước 2: Thanh toán hết các khoản nợ (tiền phòng, điện, nước,...).
  - Bước 3: Dọn dẹp phòng, trả lại chìa khóa và các vật dụng được cấp.
  - Bước 4: Ban quản lý kiểm tra tình trạng phòng.
  - Bước 5: Hoàn trả tiền cọc (nếu không có hư hỏng hoặc vi phạm).

- Định dạng câu trả lời:
  • Dòng đầu: tiêu đề + icon (ví dụ: "📌 Thông tin về đăng ký phòng ở KTX:", "💰 Chính sách thanh toán:", ...).
  • Nội dung bên dưới dùng gạch đầu dòng "- " hoặc liệt kê 1., 2., 3.
  • Cuối câu trả lời luôn thêm 1 câu gợi ý: "Bạn cần mình hỗ trợ thêm phần nào không?".

- Không có dữ liệu → nói rõ ràng: 
  "Hiện tại hệ thống chưa có dữ liệu này, bạn vui lòng kiểm tra lại hoặc liên hệ ban quản lý nhé."
`.trim();


    const combinedPrompt = `
INTENTS (ý định đã phân tích):
${JSON.stringify(intents, null, 2)}

STUDENT DATA (từ PHP / API):
${JSON.stringify(studentData || {}, null, 2)}

DB CONTEXT (dữ liệu lấy từ MySQL):
${JSON.stringify(dbContext || {}, null, 2)}

CÂU HỎI CỦA NGƯỜI DÙNG:
${prompt}

Hãy trả lời ngắn gọn, rõ ràng, đúng với dữ liệu trên.
Nếu không có dữ liệu phù hợp, hãy trả lời trung thực và gợi ý người dùng liên hệ ban quản lý khi cần.
`.trim();

    const reply = await ollamaChat({
      system: systemPrompt,
      user: combinedPrompt,
    });

    return res.json({ success: true, reply });
  } catch (err) {
    console.error("===== OLLAMA ERROR =====");
    console.error(err);
    console.error("===== END =====");
    return res.status(500).json({
      success: false,
      error: "AI error",
    });
  }
});

// ======================================================================
// 6️⃣ START SERVER
// ======================================================================

const server = app.listen(3000, () => {
  console.log("🚀 Chat AI server chạy tại http://localhost:3000");
});

const realtimeWss = new WebSocketServer({ noServer: true });

realtimeWss.on("connection", (socket, request, claims) => {
  const userId = Number(claims.uid);
  addRealtimeClient(userId, socket);

  socket.on("close", () => {
    removeRealtimeClient(userId, socket);
  });

  socket.on("error", () => {
    removeRealtimeClient(userId, socket);
  });

  socket.send(
    JSON.stringify({
      event: "realtime.ready",
      payload: {
        uid: userId,
        role: claims.role,
      },
      sentAt: new Date().toISOString(),
    })
  );
});

server.on("upgrade", (request, socket, head) => {
  const requestUrl = new URL(request.url || "/", "http://localhost");
  if (requestUrl.pathname !== "/ws") {
    socket.destroy();
    return;
  }

  if (!REALTIME_SHARED_SECRET) {
    socket.write("HTTP/1.1 503 Service Unavailable\r\n\r\n");
    socket.destroy();
    return;
  }

  const token = requestUrl.searchParams.get("token") || "";
  const claims = verifyRealtimeToken(token, REALTIME_SHARED_SECRET);
  if (!claims) {
    socket.write("HTTP/1.1 401 Unauthorized\r\n\r\n");
    socket.destroy();
    return;
  }

  realtimeWss.handleUpgrade(request, socket, head, (ws) => {
    realtimeWss.emit("connection", ws, request, claims);
  });
});
