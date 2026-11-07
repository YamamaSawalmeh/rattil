<?php
require_once __DIR__ . '/../config/db.php';
requireRole('teacher');
$user   = getCurrentUser($pdo);
$notifs = getUnreadCount($pdo);
$tid    = (int)$_SESSION['user_id'];

// جلب طلاب المعلم للقائمة
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, u.role,
        (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=? AND is_read=0) AS unread,
        (
            SELECT m.content
            FROM messages m
            WHERE (m.sender_id=u.id AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=u.id)
            ORDER BY m.sent_at DESC
            LIMIT 1
        ) AS last_message,
        (
            SELECT m.sent_at
            FROM messages m
            WHERE (m.sender_id=u.id AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=u.id)
            ORDER BY m.sent_at DESC
            LIMIT 1
        ) AS last_message_at
    FROM users u
    JOIN halqa_students hs ON u.id=hs.student_id
    JOIN halaqat h ON hs.halqa_id=h.id
    WHERE h.teacher_id=? AND u.role='student'
    ORDER BY unread DESC, last_message_at DESC, u.name ASC
");
$stmt->execute([$tid,$tid,$tid,$tid,$tid,$tid]); $students=$stmt->fetchAll();

// إجمالي الرسائل غير المقروءة
$totalUnread = array_sum(array_column($students,'unread'));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>الرسائل والمراسلات — رتِّل</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1C3329;        
    --primary-light: #2C4A3E;  
    --primary-soft: #EAECE6;   
    --gold: #D4AF37;
    --gold-dark: #B8860B;
    --gold-soft: #fbf7eb;
    --cream: #FAF7F0;          
    --beige-card: #F4EFE6;     
    --beige-hover: #EFE9DC;    
    --white: #ffffff;
    --gray-dark: #2F3633;      
    --gray: #707A75;
    --gray-light: #EFEFEA;
    --radius-lg: 20px;         
    --radius-md: 14px;
    --radius-sm: 9px;
    --shadow-sm: 0 4px 12px rgba(28, 51, 41, 0.03);
    --shadow-md: 0 12px 35px rgba(28, 51, 41, 0.07);
    --transition: all 0.3s ease;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Tajawal', sans-serif;
    background: var(--cream);
    color: var(--gray-dark);
    height: 100vh;
    display: flex;
    overflow: hidden;
}

/* ── Sidebar ── */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, var(--primary) 0%, #0F1D17 100%);
    min-height: 100vh;
    position: fixed;
    right: 0;
    top: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
    box-shadow: -4px 0 30px rgba(0,0,0,0.15);
}
.sidebar-logo {
    padding: 32px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    text-align: center;
}
.sidebar-logo .logo-text {
    font-family: 'Amiri', serif;
    font-size: 2.4rem;
    color: var(--gold);
    line-height: 1;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.sidebar-logo .logo-sub { font-size: 0.85rem; color: rgba(255,255,255,0.5); margin-top: 6px; }
.sidebar-user {
    padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; gap: 14px; background: rgba(255,255,255,0.02);
}
.avatar-circle {
    width: 46px; height: 46px; border-radius: 50%;
    background: linear-gradient(135deg, #f3e7c4 0%, var(--gold) 100%);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1.2rem; color: var(--primary); flex-shrink: 0;
}
.sidebar-user-info .name { color: var(--white); font-weight: 600; font-size: 0.98rem; }
.sidebar-user-info .role { color: var(--gold); font-size: 0.8rem; margin-top: 2px; display: flex; align-items: center; gap: 4px; }
.sidebar-nav { flex: 1; padding: 20px 0; overflow-y: auto; }
.nav-section { padding: 12px 24px 6px; font-size: 0.75rem; color: rgba(255,255,255,0.35); font-weight: 700; letter-spacing: 0.8px; }
.nav-link { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: rgba(255,255,255,0.7); text-decoration: none; transition: var(--transition); font-size: 0.95rem; position: relative; }
.nav-link svg { width: 20px; height: 20px; opacity: 0.7; transition: var(--transition); }
.nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.05); color: var(--white); }
.nav-link:hover svg, .nav-link.active svg { opacity: 1; transform: scale(1.05); }
.nav-link.active { background: linear-gradient(90deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0) 100%); color: var(--gold); }
.nav-link.active::before { content: ''; position: absolute; right: 0; top: 8px; bottom: 8px; width: 4px; background: var(--gold); border-radius: 4px 0 0 4px; }
.sidebar-footer { padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.06); }
.logout-btn { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.9rem; transition: var(--transition); }
.logout-btn:hover { color: #ff6b6b; }

/* ── Main Layout ── */
.main {
    margin-right: 280px; flex: 1; height: 100vh; min-height: 0;
    display: flex; flex-direction: column; width: calc(100% - 280px);
}
.topbar {
    background: rgba(250, 247, 240, 0.85); backdrop-filter: blur(12px);
    padding: 16px 32px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(44, 74, 62, 0.1); z-index: 50;
}
.topbar-title { font-family: 'Amiri', serif; font-size: 1.6rem; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 10px; }
.topbar-actions{display:flex;align-items:center;gap:12px}
.notif-btn{width:42px;height:42px;border:0;border-radius:50%;background:#fff;color:var(--primary);display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-sm);transition:var(--transition)}
.notif-btn:hover{background:var(--primary-soft);transform:translateY(-1px)}
.notif-btn svg{width:20px;height:20px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.unread-badge-total { background: var(--gold-dark); color: var(--white); padding: 2px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }

/* ── Chat Container ── */
.chat-container { display: flex; flex: 1; min-height:0; overflow: hidden; background: var(--white); border-top: 1px solid rgba(44,74,62,0.08); }

/* Chat List (Students Sidebar) */
.chat-sidebar { width: 320px; min-height:0; background: var(--beige-card); border-left: 1px solid rgba(44,74,62,0.1); display: flex; flex-direction: column; }
.chat-search-wrapper { padding: 16px; border-bottom: 1px solid rgba(44,74,62,0.08); }
.chat-search { width: 100%; padding: 10px 14px; border: 1px solid rgba(44,74,62,0.15); border-radius: var(--radius-sm); font-family: 'Tajawal', sans-serif; font-size: 0.9rem; background: var(--white); }
.chat-search:focus { outline: none; border-color: var(--primary); }

.students-list { flex: 1; overflow-y: auto; }
.student-item { padding: 16px; border-bottom: 1px solid rgba(44,74,62,0.05); cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: var(--transition); }
.student-item:hover { background: var(--beige-hover); }
.student-item.active { background: rgba(44,74,62,0.08); border-right: 4px solid var(--primary); }
.student-info { display: flex; align-items: center; gap: 12px; }
.student-meta .name { font-weight: 600; color: var(--primary); font-size: 0.95rem; }
.student-meta .preview { font-size: 0.8rem; color: var(--gray); margin-top: 3px; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.unread-badge { background: var(--primary); color: var(--white); font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; }

/* Chat Main Area */
.chat-area { flex: 1; min-height:0; display: flex; flex-direction: column; background: var(--cream); position: relative; }
.chat-header { padding: 16px 24px; background: var(--white); border-bottom: 1px solid rgba(44,74,62,0.08); display: flex; align-items: center; justify-content: space-between; }
.active-user-name { font-family: 'Amiri', serif; font-size: 1.3rem; font-weight: 700; color: var(--primary); }

.messages-box { flex: 1; min-height:0; padding: 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
.msg-row { display: flex; width: 100%; }
.msg-row.sent { justify-content: flex-start; } /* المعلم يرسل لليسار حسب الكود الأصلي أو العكس */
.msg-row.received { justify-content: flex-end; }

.msg-bubble { max-width: 65%; padding: 12px 18px; border-radius: var(--radius-md); font-size: 0.95rem; line-height: 1.5; box-shadow: var(--shadow-sm); position: relative; }
.msg-row.sent .msg-bubble { background: var(--primary-light); color: var(--white); border-bottom-right-radius: 2px; }
.msg-row.received .msg-bubble { background: var(--white); color: var(--gray-dark); border-bottom-left-radius: 2px; border: 1px solid rgba(44,74,62,0.06); }

.msg-time { font-size: 0.72rem; margin-top: 6px; text-align: left; display: block; opacity: 0.7; font-variant-numeric: tabular-nums; }
.msg-row.sent .msg-time { color: rgba(255,255,255,0.7); }
.msg-row.received .msg-time { color: var(--gray); }

/* Chat Input Bar */
.chat-input-bar { padding: 16px 24px; background: var(--white); border-top: 1px solid rgba(44,74,62,0.08); display: flex; align-items: flex-end; gap: 12px; }
.chat-textarea { flex: 1; border: 1px solid rgba(44,74,62,0.15); border-radius: 22px; padding: 11px 18px; font-family: 'Tajawal', sans-serif; font-size: 0.95rem; resize: none; max-height: 120px; min-height: 44px; outline: none; transition: var(--transition); background: var(--cream); }
.chat-textarea:focus { border-color: var(--primary); background: var(--white); }

.send-btn { background: var(--primary); color: var(--white); border: none; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition); flex-shrink: 0; }
.send-btn:hover { background: var(--primary-light); transform: scale(1.05); }
.send-btn:disabled { background: var(--gray-light); color: var(--gray); cursor: not-allowed; }

.no-chat-selected { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--gray); gap: 16px; background: var(--cream); }

/* Toast */
.toast-msg { position: fixed; bottom: 24px; left: 24px; padding: 12px 24px; border-radius: var(--radius-sm); color: #fff; font-size: .9rem; font-weight: 600; z-index: 9999; display: none; box-shadow: var(--shadow-md); }
.toast-msg.err { background: #ef4444; }
.toast-msg.success { background: #10b981; }

@media(max-width: 768px) {
    .main { margin-right: 0; width: 100%; }
    .sidebar { transform: translateX(280px); }
    .chat-sidebar { width: 100%; }
    .chat-area { display: none; }
    .chat-container.active-chat .chat-sidebar { display: none; }
    .chat-container.active-chat .chat-area { display: flex; }
}
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-text">رتِّل</div>
        <div class="logo-sub">منصة إدارة الحلقات القرآنيّة</div>
    </div>
    <div class="sidebar-user">
        <div class="avatar-circle"><?= mb_substr($user['name']??'م', 0, 1, 'utf-8') ?></div>
        <div class="sidebar-user-info">
            <div class="name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="role">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;display:inline;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                معلم معتمد
            </div>
        </div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">الرئيسية</div>
        <a class="nav-link" href="dashboard.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/></svg>
            لوحة التحكم
        </a>
        <div class="nav-section">الإدارة التعليمية</div>
        <a class="nav-link" href="halaqat.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
            إدارة الحلقات
        </a>
        <a class="nav-link" href="students.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            الطلاب
        </a>
        <a class="nav-link" href="recitation_log.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            جلسات التسميع
        </a>
        <a class="nav-link" href="attendance.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            رصد الحضور والغياب
        </a>
    </div>
    <div class="sidebar-footer">
        <a class="logout-btn" href="../logout.php">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            تسجيل الخروج
        </a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div class="topbar-title">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
            مركز الرسائل والمراسلات المباشرة
            <?php if($totalUnread > 0): ?>
                <span class="unread-badge-total"><?= $totalUnread ?> غير مقروءة</span>
            <?php endif; ?>
        </div>
        <div class="topbar-actions">
            <button class="notif-btn" type="button" title="الإشعارات" aria-label="الإشعارات">
                <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if($notifs>0): ?><span class="notif-badge" id="notifBadge"><?= $notifs ?></span><?php endif; ?>
            </button>
        </div>
    </div>

    <div class="chat-container" id="chatContainer">
        <div class="chat-sidebar">
            <div class="chat-search-wrapper">
                <input type="text" class="chat-search" id="chatSearch" placeholder="بحث سريع عن اسم طالب..." oninput="filterStudents()">
            </div>
            <div class="students-list" id="studentsList">
                <?php foreach($students as $st): ?>
                    <div class="student-item" id="item-<?= $st['id'] ?>" onclick="openChat(<?= $st['id'] ?>, '<?= htmlspecialchars($st['name'], ENT_QUOTES) ?>')">
                        <div class="student-info">
                            <div class="avatar-circle" style="width:38px; height:38px; font-size:1rem; background:rgba(44,74,62,0.1); color:var(--primary);"><?= mb_substr($st['name'],0,1,'utf-8') ?></div>
                            <div class="student-meta">
                                <div class="name"><?= htmlspecialchars($st['name']) ?></div>
                                <div class="preview" id="prev-<?= $st['id'] ?>"><?= htmlspecialchars($st['last_message'] ?: 'اضغط لبدء المحادثة السريعة...') ?></div>
                            </div>
                        </div>
                        <?php if($st['unread'] > 0): ?>
                            <span class="unread-badge" id="badge-<?= $st['id'] ?>"><?= $st['unread'] ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="chat-area" id="chatArea">
            <div class="no-chat-selected" id="noChatSelected">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--primary); opacity:0.3;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <p style="font-weight: 500;">يرجى اختيار طالب من القائمة اليمنى لبدء المحادثة الفورية</p>
            </div>

            <div id="chatActiveContent" style="display:none; flex:1; min-height:0; flex-direction:column; overflow:hidden;">
                <div class="chat-header">
                    <div class="active-user-name" id="activeChatName">اسم الطالب الحالي</div>
                    <button class="btn" style="background:var(--gray-light); padding:6px 12px; font-size:0.8rem;" onclick="backToList()">رجوع للقائمة</button>
                </div>
                
                <div class="messages-box" id="messagesBox">
                    </div>

                <div class="chat-input-bar">
                    <textarea class="chat-textarea" id="msgInput" rows="1" placeholder="اكتب رسالتك التوجيهية للطالب هنا..." oninput="autoExpand(this)"></textarea>
                    <button class="send-btn" id="sendBtn" onclick="sendMsg()">
                        <span id="sendSpinner" style="display:none; border:2px solid #fff; border-top:2px solid transparent; width:16px; height:16px; border-radius:50%; animation:spin 1s linear infinite;"></span>
                        <svg id="sendIcon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="transform: rotate(180deg);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-msg" id="toast"></div>

<script>
let currentStudentId = null;
let pollInterval = null;

function filterStudents() {
    const q = document.getElementById('chatSearch').value.toLowerCase();
    document.querySelectorAll('.student-item').forEach(item => {
        const name = item.querySelector('.name').textContent.toLowerCase();
        item.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

function autoExpand(el) {
    el.style.height = '44px';
    el.style.height = (el.scrollHeight) + 'px';
}

function backToList() {
    document.getElementById('chatContainer').classList.remove('active-chat');
}

function showToast(text, type='success') {
    const t = document.getElementById('toast');
    t.className = 'toast-msg ' + type;
    t.textContent = text;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 3000);
}

async function openChat(sid, sname) {
    currentStudentId = sid;
    document.getElementById('chatContainer').classList.add('active-chat');
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('chatActiveContent').style.display = 'flex';
    document.getElementById('activeChatName').textContent = 'المحادثة مع: ' + sname;

    document.querySelectorAll('.student-item').forEach(i => i.classList.remove('active'));
    document.getElementById('item-' + sid).classList.add('active');

    const badge = document.getElementById('badge-' + sid);
    if(badge) badge.remove();

    await loadMessages();
    
    // التمرير لأسفل المحادثة
    const box = document.getElementById('messagesBox');
    box.scrollTop = box.scrollHeight;

    clearInterval(pollInterval);
    pollInterval = setInterval(loadMessages, 5000);
}

async function loadMessages() {
    if(!currentStudentId) return;
    try {
        const r = await fetch(`/rattil/api/messages.php?action=fetch&student_id=${currentStudentId}`);
        const d = await r.json();
        if(!d.success) return;

        const box = document.getElementById('messagesBox');
        const isAtBottom = box.scrollHeight - box.clientHeight <= box.scrollTop + 50;

        box.innerHTML = d.data.map(m => {
            // تصنيف فئات الإرسال والاستقبال
            const cls = (m.sender_id == currentStudentId) ? 'sent' : 'received';
            return `
                <div class="msg-row ${cls}">
                    <div class="msg-bubble">
                        <div>${escapeHtml(m.content)}</div>
                        <span class="msg-time">${m.created_at}</span>
                    </div>
                </div>
            `;
        }).join('');

        if(isAtBottom) {
            box.scrollTop = box.scrollHeight;
        }

        if(d.data.length) {
            const last = d.data[d.data.length - 1];
            const prev = document.getElementById('prev-' + currentStudentId);
            if(prev) prev.textContent = last.content.substring(0, 30) + (last.content.length > 30 ? '...' : '');
        }
        if (window.NotifManager) NotifManager.poll(true);
    } catch(e) {}
}

async function sendMsg() {
    const input = document.getElementById('msgInput');
    const content = input.value.trim();
    if(!content || !currentStudentId) return;

    const btn = document.getElementById('sendBtn');
    const spinner = document.getElementById('sendSpinner');
    const icon = document.getElementById('sendIcon');
    
    btn.disabled = true; spinner.style.display = 'block'; icon.style.display = 'none';

    try {
        const r = await fetch('/rattil/api/messages.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'send', receiver_id: currentStudentId, content})
        });
        const d = await r.json();
        if(d.success) {
            input.value = '';
            input.style.height = '44px';
            await loadMessages();
            const box = document.getElementById('messagesBox');
            box.scrollTop = box.scrollHeight;
        } else {
            showToast(d.error || 'خطأ في عملية الإرسال', 'err');
        }
    } catch(e) {
        showToast('خطأ في الاتصال بالخادم', 'err');
    }
    btn.disabled = false; spinner.style.display = 'none'; icon.style.display = 'block';
}

function escapeHtml(text) {
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<style>
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>
<script src="/rattil/assets/js/notifications.js?v=20260518"></script>
</body>
</html>

