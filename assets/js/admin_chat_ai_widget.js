// admin_chat_ai_widget.js

// ===================================
// 1. LẤY ELEMENT
// ===================================
const adminChatToggle = document.getElementById("adminChatAiToggle");
const adminChatWindow = document.getElementById("adminChatAiWindow");
const adminChatClose = document.getElementById("adminChatAiClose");
const adminChatMessages = document.getElementById("adminChatAiMessages");
const adminChatInput = document.getElementById("adminChatAiInput");
const adminChatSend = document.getElementById("adminChatAiSend");

// Ngữ cảnh chat admin (nhớ intent trước)
let adminChatContext = {
    lastIntents: [],
    lastQuestion: null,
    createdAt: Date.now(),
};

// ===================================
// 2. HELPER CHUẨN HÓA TIẾNG VIỆT + INTENT
// ===================================
function removeAdminVietnameseTones(str) {
    if (!str) return "";
    str = str.toLowerCase();
    str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, "a");
    str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, "e");
    str = str.replace(/ì|í|ị|ỉ|ĩ/g, "i");
    str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, "o");
    str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, "u");
    str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, "y");
    str = str.replace(/đ/g, "d");
    str = str.replace(/[^0-9a-z\s]/g, " ");
    return str.replace(/\s+/g, " ").trim();
}

function detectAdminIntents(rawQuestion) {
    const q = removeAdminVietnameseTones(rawQuestion || "");
    const intents = [];

    // Tổng quan hệ thống
    if (/tong quan|overview|bao cao nhanh|tinh hinh chung/.test(q)) {
        intents.push("ADMIN_OVERVIEW", "SYSTEM_LOGS");
    }

    // Công nợ / hóa đơn
    if (/con no|con nhieu no|con bao nhieu hoa don|con bao nhieu tien|con bao nhieu cong no/.test(q)) {
        intents.push("DEBT_STATS", "INVOICE_UNPAID");
    }

    // Phòng trống
    if (/phong trong|con phong khong|con phong nao khong|phong con cho|con cho khong/.test(q)) {
        intents.push("ROOMS_AVAILABLE");
    }

    // Thống kê phản ánh
    if (/phan anh|gop y|feedback|bao hong/.test(q)) {
        intents.push("FEEDBACK_STATS");
    }

    // Thống kê phòng
    if (/su dung phong|ti le phong|ti le su dung/.test(q)) {
        intents.push("ROOM_STATS");
    }

    // Thống kê sinh viên
    if (/sinh vien|tong sinh vien|bao nhieu sinh vien/.test(q)) {
        intents.push("STUDENT_STATS");
    }

    // Nếu chưa bắt được gì, nhưng có từ "tong" => coi như tổng quan
    if (!intents.length && /tong|overall|overview/.test(q)) {
        intents.push("ADMIN_OVERVIEW");
    }

    adminChatContext.lastIntents = intents;
    adminChatContext.lastQuestion = rawQuestion;

    return intents;
}

// ===================================
// 3. UI HELPER
// ===================================
function adminScrollToBottom() {
    if (!adminChatMessages) return;
    adminChatMessages.scrollTo({
        top: adminChatMessages.scrollHeight,
        behavior: "smooth",
    });
}

function adminEscapeHtml(text) {
    if (text === null || text === undefined) return "";
    const div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
}

function adminAddUserMessage(text) {
    if (!adminChatMessages) return;

    const div = document.createElement("div");
    div.className = "admin-chat-message user";

    div.innerHTML = `
        <div class="admin-chat-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="admin-chat-bubble">
            ${adminEscapeHtml(text).replace(/\n/g, "<br>")}
        </div>
    `;

    adminChatMessages.appendChild(div);
    adminScrollToBottom();
}

function adminAddBotMessage(text) {
    if (!adminChatMessages) return;

    const msgWrap = document.createElement("div");
    msgWrap.className = "admin-chat-message bot";

    const avatar = document.createElement("div");
    avatar.className = "admin-chat-avatar";
    avatar.innerHTML = '<i class="fas fa-robot"></i>';

    const bubble = document.createElement("div");
    bubble.className = "admin-chat-bubble";

    const safe =
        typeof text === "string" ?
        text :
        JSON.stringify(text, null, 2);

    bubble.innerHTML = adminEscapeHtml(safe).replace(/\n/g, "<br>");

    msgWrap.appendChild(avatar);
    msgWrap.appendChild(bubble);

    adminChatMessages.appendChild(msgWrap);
    adminScrollToBottom();
}

function adminShowTyping() {
    if (!adminChatMessages) return;
    adminHideTyping();

    const div = document.createElement("div");
    div.className = "admin-chat-message bot admin-typing";
    div.innerHTML = `
        <div class="admin-chat-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div class="admin-chat-bubble">
            <div class="typing-dots">
                <span></span><span></span><span></span>
            </div>
        </div>
    `;
    adminChatMessages.appendChild(div);
    adminScrollToBottom();
}

function adminHideTyping() {
    if (!adminChatMessages) return;
    const t = adminChatMessages.querySelector(".admin-typing");
    if (t) t.remove();
}

// ===================================
// 4. GỌI SERVER ADMIN AI (NODE PORT 3000)
// ===================================
async function callAdminAI(message, intents, context) {
    const payload = {
        prompt: message,
        intents: intents || [],
        useContext: context || {},
    };

    console.log("🟨 [ADMIN-AI-FE] Payload gửi server:", payload);

    try {
        const res = await fetch("http://localhost:3000/api/admin-chat-ai", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload),
        });

        const rawText = await res.text();
        console.log("🟨 [ADMIN-AI-FE] Raw response text:", rawText);

        let data;
        try {
            data = JSON.parse(rawText);
        } catch (jsonErr) {
            console.error("🟥 [ADMIN-AI-FE] Không parse được JSON:", jsonErr);
            return {
                success: false,
                reply: "Xin lỗi, phản hồi từ server không đúng định dạng JSON. Bạn kiểm tra lại server AI giúp mình nhé.",
            };
        }

        console.log("🟨 [ADMIN-AI-FE] Parsed response:", data);
        return data;
    } catch (err) {
        console.error("🟥 [ADMIN-AI-FE] Lỗi gọi server Admin AI:", err);
        return {
            success: false,
            reply: "Xin lỗi, mình không kết nối được tới máy chủ AI (Admin).",
        };
    }
}

// ===================================
// 5. XỬ LÝ GỬI TIN
// ===================================
async function adminSendMessage() {
    if (!adminChatInput) return;

    const message = (adminChatInput.value || "").trim();
    if (!message) return;

    adminAddUserMessage(message);
    adminChatInput.value = "";

    adminShowTyping();

    try {
        const intents = detectAdminIntents(message);
        const res = await callAdminAI(message, intents, adminChatContext);

        adminHideTyping();

        if (!res || res.success === false || !res.reply) {
            adminAddBotMessage(
                "Xin lỗi, mình không đọc được phản hồi từ mô hình AI."
            );
            return;
        }

        adminAddBotMessage(res.reply);
    } catch (err) {
        adminHideTyping();
        console.error("[ADMIN-AI-FE] Lỗi gửi tin:", err);
        adminAddBotMessage(
            "Có lỗi khi kết nối tới server AI Admin, bạn thử lại sau nhé."
        );
    }
}

// ===================================
// 6. GẮN EVENT
// ===================================
if (adminChatToggle && adminChatWindow) {
    adminChatToggle.addEventListener("click", () => {
        const isActive = adminChatWindow.classList.toggle("active");
        if (isActive && adminChatInput) {
            adminChatInput.focus();
        }
    });
}

if (adminChatClose && adminChatWindow) {
    adminChatClose.addEventListener("click", () => {
        adminChatWindow.classList.remove("active");
    });
}

if (adminChatSend) {
    adminChatSend.addEventListener("click", () => {
        adminSendMessage();
    });
}

if (adminChatInput) {
    adminChatInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            adminSendMessage();
        }
    });
}