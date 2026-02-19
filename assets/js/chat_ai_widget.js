// ===== CHAT AI WIDGET =====
const chatToggle = document.getElementById('chatAiToggle');
const chatWindow = document.getElementById('chatAiWindow');
const chatClose = document.getElementById('chatAiClose');
const chatMessages = document.getElementById('chatAiMessages');
const chatInput = document.getElementById('chatAiInput');
const chatSend = document.getElementById('chatAiSend');

// Data sinh viên + context
let studentData = null;
let lastStudentDataLoadedAt = 0;
let chatContext = { lastIntents: [], lastQuestion: null, createdAt: Date.now() };

/* ========== HELPER CHUẨN HÓA TIẾNG VIỆT & INTENT ========== */

function removeVietnameseTones(str) {
    str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, "a");
    str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, "e");
    str = str.replace(/ì|í|ị|ỉ|ĩ/g, "i");
    str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, "o");
    str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, "u");
    str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, "y");
    str = str.replace(/đ/g, "d");
    str = str.replace(/À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ/g, "A");
    str = str.replace(/È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ/g, "E");
    str = str.replace(/Ì|Í|Ị|Ỉ|Ĩ/g, "I");
    str = str.replace(/Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ/g, "O");
    str = str.replace(/Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ/g, "U");
    str = str.replace(/Ỳ|Ý|Ỵ|Ỷ|Ỹ/g, "Y");
    str = str.replace(/Đ/g, "D");
    str = str.replace(/[^0-9a-zA-Z\s]/g, " ");
    return str.replace(/\s+/g, " ").trim();
}

function normalizeQuestion(text) {
    return removeVietnameseTones(String(text || '').toLowerCase());
}

// Từ khóa intent
const INTENTS = {
    GREET: ['xin chao', 'chao ban', 'chao', 'hello', 'hi', 'good morning', 'good afternoon', 'good evening'],
    THANKS: ['cam on', 'cảm ơn', 'thank', 'thanks', 'on nhe', 'on a', 'biet on', 'doi on'],
    BOT_ID: ['ban la ai', 'may la gi', 'ban la gi', 'day la gi', 'ban lam duoc gi', 'ban ho tro gi', 'tro ly ai ktx'],

    STUDENT_INFO: ['thong tin cua toi', 'thong tin ca nhan', 'ten toi la gi', 'toi la ai', 'minh la ai', 'ho so cua toi', 'ho so sinh vien'],
    ROOM_INFO: ['phong toi', 'phong cua toi', 'phong dang o', 'phong hien tai', 'toi o phong', 'minh o phong', 'phong minh dang o', 'thong tin phong minh'],
    ROOM_CAPACITY: ['phong may nguoi', 'phong bao nhieu nguoi', 'suc chua phong', 'trong phong co bao nhieu nguoi', 'phong chua duoc bao nhieu'],
    CONTRACT_INFO: ['hop dong', 'thoi han o', 'han hop dong', 'hop dong phong', 'ngay het han', 'ngay ket thuc hop dong', 'thoi gian o ktx'],

    INVOICE_UNPAID: ['hoa don chua', 'hoa don chua thanh toan', 'no chua tra', 'con no khong', 'tien phong chua tra', 'tien nuoc chua tra', 'tien dien chua tra', 'con no bao nhieu', 'con no tien ktx'],
    INVOICE_STATS: ['tong hoa don', 'co bao nhieu hoa don', 'thong ke hoa don', 'lich su thanh toan', 'lich su hoa don', 'xem hoa don'],

    ROOMS_AVAILABLE: ['phong trong', 'phong con', 'con phong trong', 'phong con trong', 'phong nao con trong', 'tim phong trong', 'phong nao con cho'],
    FEEDBACK_STATS: ['phan anh', 'gop y', 'feedback', 'bao cao su co', 'bao hong tren he thong', 'xem phan anh cua toi'],

    REGISTER_ROOM: ['dang ky phong', 'dang ki phong', 'thue phong', 'xin phong', 'dang ky o ktx', 'lam sao de o ktx', 'dang ky ky tuc xa'],
    PRICING: ['gia phong', 'tien phong', 'chi phi phong', 'bao nhieu tien', 'gia bao nhieu', 'bao nhieu mot thang', 'tien phong mot thang', 'hoc phi ktx', 'phi ktx'],
    RULES: ['noi quy', 'quy dinh', 'quy tac', 'duoc phep', 'cam khong', 'luat le', 'co duoc nuoi cho khong', 'co duoc nuoi meo khong', 'gio gioi nghiem', 'co duoc nau an trong phong khong'],
    PAYMENT_GUIDE: ['thanh toan hoa don', 'tra tien phong', 'tra tien', 'huong dan thanh toan', 'chuyen khoan ktx', 'nop tien ktx o dau'],

    UTILITIES: ['tien ich', 'dich vu', 'phong gym', 'giat', 'bep', 'wifi', 'co wifi khong', 'co may giat khong', 'co phong tu hoc khong'],
    CONTACT: ['lien he', 'hotline', 'email', 'ho tro', 'gap ai', 'so dien thoai ktx', 'dia chi ktx'],

    CHECKOUT: ['tra phong', 'roi phong', 'nghi o', 'check out', 'ket thuc hop dong', 'khong o ktx nua', 'tra phong ktx'],
    ROOM_CHANGE: ['chuyen phong', 'doi phong', 'muon doi phong', 'chuyen sang phong khac'],
    REPAIR: ['bao hong', 'bao hu', 'sua', 'hu hong', 'loi ky thuat', 'vo den', 'vo ong nuoc', 'hu may lanh'],
    SECURITY: ['an ninh', 'an toan', 'bao ve', 'camera', 'khoa cua', 'mat an toan', 'mat an ninh'],
    SCHEDULE: ['gio giac', 'may gio', 'bao gio', 'thoi gian', 'gio mo cua', 'gio dong cua', 'gio gioi nghiem'],
    VISITORS: ['khach den', 'khach tham', 'visit', 'ban be', 'gia dinh', 'co duoc dua ban vao phong khong', 'co duoc ngu lai khong'],
    LOST_ITEM: ['mat do', 'that lac', 'mat cap', 'an trom', 'mat dien thoai', 'mat xe', 'mat vi']
};

function scoreIntent(qNorm, patterns) {
    let score = 0;
    patterns.forEach(p => {
        if (!p) return;
        if (qNorm.includes(p)) {
            const len = p.split(' ').length;
            score += (len >= 3) ? 3 : (len === 2 ? 2 : 1);
        }
    });
    return score;
}

function detectIntents(qNorm) {
    const intentsWithScore = [];

    // 1. Tính điểm theo bảng INTENTS
    for (const intent in INTENTS) {
        const s = scoreIntent(qNorm, INTENTS[intent]);
        if (s > 0) intentsWithScore.push({ intent, score: s });
    }

    // 2. Rule đặc biệt
    const hasHoaDon = qNorm.includes('hoa don') || qNorm.includes('tien phong') || qNorm.includes('tien ktx');
    const hasChuaTra = qNorm.includes('chua tra') || qNorm.includes('chua thanh toan') || qNorm.includes('con no') || qNorm.includes('no khong');
    const hasTong = qNorm.includes('tong') || qNorm.includes('bao nhieu') || qNorm.includes('tat ca');
    const hasPhong = qNorm.includes('phong');
    const hasBaoNhieuNguoi = qNorm.includes('may nguoi') || qNorm.includes('bao nhieu nguoi') || qNorm.includes('suc chua');

    if (hasHoaDon && hasChuaTra) intentsWithScore.push({ intent: 'INVOICE_UNPAID', score: 5 });
    else if (hasHoaDon && hasTong) intentsWithScore.push({ intent: 'INVOICE_STATS', score: 4 });
    if (hasPhong && hasBaoNhieuNguoi) intentsWithScore.push({ intent: 'ROOM_CAPACITY', score: 4 });

    // 3. Không có intent → dùng context
    if (!intentsWithScore.length && chatContext.lastIntents.length) {
        const askPrice = ['gia', 'tien', 'bao nhieu', 'bao nhieu tien'].some(k => qNorm.includes(k));
        const askHoaDon = ['hoa don', 'no', 'con khong'].some(k => qNorm.includes(k));
        const last = chatContext.lastIntents;

        if (askPrice && (last.includes('ROOM_INFO') || last.includes('ROOMS_AVAILABLE')))
            intentsWithScore.push({ intent: 'PRICING', score: 3 });

        if (askHoaDon && (last.includes('INVOICE_UNPAID') || last.includes('INVOICE_STATS')))
            intentsWithScore.push({ intent: 'INVOICE_UNPAID', score: 3 });
    }

    if (!intentsWithScore.length) return [];
    intentsWithScore.sort((a, b) => b.score - a.score);
    const maxScore = intentsWithScore[0].score;

    return Array.from(new Set(
        intentsWithScore.filter(it => it.score >= maxScore - 1).map(it => it.intent)
    ));
}

/* ========== LẤY DATA SINH VIÊN TỪ PHP ========== */

async function loadStudentData() {
    try {
        const response = await fetch('/modules/api/get_student_full_profile.php');
        const text = await response.text();
        console.log('Raw API Response:', text);

        if (!response.ok) {
            console.error('API Error (HTTP ' + response.status + '):', text);
            return;
        }

        const data = JSON.parse(text);
        console.log('Parsed API Response:', data);

        if (data.success) {
            studentData = data;
            lastStudentDataLoadedAt = Date.now();
        } else {
            console.error('API returned error:', data.error);
        }
    } catch (err) {
        console.error('Error loading student data:', err);
    }
}

loadStudentData();

async function ensureFreshData() {
    if (!studentData) {
        await loadStudentData();
        return;
    }
    const FIVE_MIN = 5 * 60 * 1000;
    if (Date.now() - lastStudentDataLoadedAt > FIVE_MIN) {
        await loadStudentData();
    }
}

/* ========== UI & MESSAGE HIỂN THỊ ========== */

if (chatToggle && chatWindow) {
    chatToggle.addEventListener('click', () => {
        chatWindow.classList.toggle('active');
        if (chatWindow.classList.contains('active')) chatInput.focus();
    });
    if (chatClose) {
        chatClose.addEventListener('click', () => chatWindow.classList.remove('active'));
    }

    const sendMessage = async() => {
        const message = chatInput.value.trim();
        if (!message) return;

        addUserMessage(message);
        chatInput.value = '';

        // Bật hiệu ứng typing ngay
        showTypingIndicator();

        try {
            // Gửi yêu cầu AI → chờ kết quả
            const response = await getAIResponse(message);

            // Khi có trả lời → tắt typing
            removeTypingIndicator();

            // Hiển thị tin nhắn bot
            addBotMessage(response);
        } catch (e) {
            removeTypingIndicator();
            addBotMessage("Xin lỗi, hệ thống AI đang bận. Bạn thử lại sau nhé!");
        }
    };


    if (chatSend) chatSend.addEventListener('click', sendMessage);
    if (chatInput) {
        chatInput.addEventListener('keypress', e => {
            if (e.key === 'Enter') sendMessage();
        });
    }

    document.addEventListener('click', e => {
        if (e.target.classList.contains('chat-ai-suggestion')) {
            const q = e.target.dataset.question;
            chatInput.value = q;
            sendMessage();
        }
    });
}

function preprocessBotText(text) {
    let t = (text !== undefined && text !== null) ? String(text).trim() : '';
    if (t.includes('')) return t;
    t = t.replace(/:\s*-\s/g, ':\n- ');
    t = t.replace(/\.?\s+-\s/g, '\n- ');
    return t;
}

function addUserMessage(text) {
    if (!chatMessages) return;
    const messageDiv = document.createElement('div');
    messageDiv.className = 'chat-ai-message user';
    const safe = escapeHtml(text);
    messageDiv.innerHTML = `
        <div class="chat-ai-bubble">
            <p>${safe}</p>
        </div>
        <div class="chat-ai-avatar">
            <i class="fas fa-user"></i>
        </div>`;
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

function addBotMessage(text) {
    if (!chatMessages) return;
    const processed = preprocessBotText(text);
    const safeHtml = escapeHtml(processed).replace(/\n/g, '<br>');

    const messageDiv = document.createElement('div');
    messageDiv.className = 'chat-ai-message bot';
    messageDiv.innerHTML = `
        <div class="chat-ai-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div class="chat-ai-bubble">
            <p>${safeHtml}</p>
        </div>`;
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

function showTypingIndicator() {
    if (!chatMessages) return;

    // Xóa typing cũ nếu tồn tại
    removeTypingIndicator();

    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-ai-message bot typing-indicator';
    typingDiv.innerHTML = `
        <div class="chat-ai-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div class="chat-ai-bubble typing-bubble">
            <div class="typing-dots">
                <span></span><span></span><span></span>
            </div>
        </div>
    `;
    chatMessages.appendChild(typingDiv);
    scrollToBottom();
}


function removeTypingIndicator() {
    if (!chatMessages) return;
    const typing = chatMessages.querySelector('.typing-indicator');
    if (typing) typing.remove();
}

function scrollToBottom() {
    if (!chatMessages) return;
    chatMessages.scrollTo({ top: chatMessages.scrollHeight, behavior: 'smooth' });
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}



/* ========== MAIN AI LOGIC ========== */

async function getAIResponse(question) {
    try {
        const qNorm = normalizeQuestion(question);
        const intents = detectIntents(qNorm);

        console.log('🎯 Detected intents:', intents);

        chatContext.lastIntents = intents;
        chatContext.lastQuestion = question;

        // 👉 Đảm bảo dữ liệu sinh viên
        await ensureFreshData();

        // 🔥 THÊM DÒNG NÀY ĐỂ CHECK
        console.log('🟨 studentData TRƯỚC KHI GỬI NODE:', studentData);

        const payload = {
            prompt: question,
            intents: intents,
            studentData: studentData,
            useContext: chatContext
        };

        console.log('📤 Send payload to AI:', payload);

        const res = await fetch('http://localhost:3000/api/chat-ai', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        console.log('📩 AI response:', data);

        if (data.success && data.reply) return data.reply;
        return 'Xin lỗi, hệ thống AI đang gặp sự cố. Bạn thử lại sau nhé!';
    } catch (err) {
        console.error('Chat AI fetch error:', err);
        return 'Có lỗi khi kết nối tới máy chủ AI.';
    }
}