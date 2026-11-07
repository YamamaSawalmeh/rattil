<?php
require_once '../config/db.php';
requireRole('teacher');
$user = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$msg = ''; $error = '';

// ── POST: تسجيل / تعديل الحضور ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_attendance') {
        $hid  = (int)($_POST['halqa_id'] ?? 0);
        $date = sanitize($_POST['date'] ?? date('Y-m-d'));
        // تحقق أن الحلقة للمعلم
        $chk = $pdo->prepare("SELECT id FROM halaqat WHERE id=? AND teacher_id=?");
        $chk->execute([$hid, $user['id']]);
        if (!$chk->fetch()) { $error = 'غير مصرح لك بإدارة هذه الحلقة'; }
        else {
            $statuses = $_POST['status'] ?? [];
            $notes    = $_POST['note']   ?? [];
            $saved = 0;
            foreach ($statuses as $sid => $status) {
                $sid = (int)$sid;
                $valid = ['present','absent','late','excused'];
                if (!in_array($status,$valid)) continue;
                $note = sanitize($notes[$sid] ?? '');
                $pdo->prepare("INSERT INTO attendance (halqa_id,student_id,teacher_id,date,status,note)
                               VALUES (?,?,?,?,?,?)
                               ON DUPLICATE KEY UPDATE status=VALUES(status),note=VALUES(note)")
                    ->execute([$hid,$sid,$user['id'],$date,$status,$note]);
                $saved++;
            }
            $msg = "تم حفظ وتحديث سجل الحضور لـ ($saved) طالب بنجاح ✅";
        }
    }
}

// ── جلب الحلقات النشطة للمعلم ─────────────────────────────
$stmtH = $pdo->prepare("SELECT id,name FROM halaqat WHERE teacher_id=? AND status='active'");
$stmtH->execute([$user['id']]);
$myHalaqat = $stmtH->fetchAll();

$selectedHalqa = (int)($_GET['halqa_id'] ?? ($myHalaqat[0]['id'] ?? 0));
$selectedDate  = sanitize($_GET['date'] ?? date('Y-m-d'));

// ── جلب طلاب الحلقة المحددة مع سجل حضورهم في هذا التاريخ ──
$students = [];
if ($selectedHalqa) {
    $stmtS = $pdo->prepare("SELECT u.id, u.name, u.email, att.status, att.note 
                            FROM halqa_students hs
                            JOIN users u ON u.id = hs.student_id
                            LEFT JOIN attendance att ON att.student_id = u.id AND att.halqa_id = ? AND att.date = ?
                            WHERE hs.halqa_id = ?
                            ORDER BY u.name ASC");
    $stmtS->execute([$selectedHalqa, $selectedDate, $selectedHalqa]);
    $students = $stmtS->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>رصد الحضور والغياب — رتِّل</title>
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
    --transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    
    /* ألوان الحضور الخاصة */
    --clr-present: #2D6A4F;
    --clr-absent: #E63946;
    --clr-late: #F4A261;
    --clr-excused: #457B9D;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Tajawal', sans-serif;
    background: var(--cream);
    color: var(--gray-dark);
    min-height: 100vh;
    display: flex;
    overflow-x: hidden;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
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
.sidebar-logo .logo-sub {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    margin-top: 6px;
}
.sidebar-user {
    padding: 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(255,255,255,0.02);
}
.avatar-circle {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f3e7c4 0%, var(--gold) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--primary);
    flex-shrink: 0;
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
    margin-right: 280px;
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    width: calc(100% - 280px);
}
.topbar {
    background: rgba(250, 247, 240, 0.85);
    backdrop-filter: blur(12px);
    padding: 16px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(44, 74, 62, 0.1);
    position: sticky;
    top: 0;
    z-index: 50;
}
.topbar-title { font-family: 'Amiri', serif; font-size: 1.6rem; color: var(--primary); font-weight: 700; }
.topbar-actions { display: flex; align-items: center; gap: 16px; }

/* ── Content Area ── */
.content { padding: 32px; flex: 1; animation: fadeIn 0.5s ease-out forwards; max-width: 1200px; width: 100%; margin: 0 auto; }

/* ── Card Component ── */
.card {
    background: var(--beige-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
    border: 1px solid rgba(44, 74, 62, 0.08); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition);
    margin-bottom: 24px;
}
.card-header {
    padding: 22px 24px; border-bottom: 1px solid rgba(44, 74, 62, 0.1); display: flex; align-items: center;
    justify-content: space-between; background: linear-gradient(90deg, rgba(44, 74, 62, 0.05) 0%, rgba(255,255,255,0) 100%);
}
.card-title { font-family: 'Amiri', serif; font-size: 1.4rem; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 10px; }
.card-body { padding: 24px; }

/* ── Configuration Form (Filters) ── */
.filter-bar { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
.form-group { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 200px; }
.form-group label { font-size: 0.88rem; font-weight: 600; color: var(--primary); }
.form-control { width: 100%; padding: 11px 14px; border: 1px solid rgba(44, 74, 62, 0.2); border-radius: var(--radius-sm); font-family: 'Tajawal', sans-serif; font-size: 0.92rem; color: var(--gray-dark); background: var(--white); transition: var(--transition); }
.form-control:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(44,74,62,0.1); }

/* ── Buttons ── */
.btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; border-radius: var(--radius-sm);
    font-weight: 600; font-size: 0.92rem; cursor: pointer; transition: var(--transition); border: none; text-decoration: none;
}
.btn-primary { background: var(--primary-light); color: var(--white); }
.btn-primary:hover { background: var(--primary); }
.btn-gold { background: var(--gold); color: var(--primary); }
.btn-gold:hover { background: var(--gold-dark); color: var(--white); }

.bulk-actions { padding: 14px 24px; background: rgba(44, 74, 62, 0.04); border-bottom: 1px solid rgba(44, 74, 62, 0.08); display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.bulk-label { font-size: 0.85rem; font-weight: 700; color: var(--primary); margin-left: 8px; }

/* ── Attendance Table ── */
.attendance-table { width: 100%; border-collapse: collapse; }
.attendance-table th { padding: 14px; background: rgba(44, 74, 62, 0.06); font-weight: 700; font-size: 0.88rem; color: var(--primary); text-align: right; border-bottom: 2px solid rgba(44, 74, 62, 0.1); }
.attendance-table td { padding: 16px 14px; border-bottom: 1px solid rgba(44, 74, 62, 0.05); font-size: 0.95rem; vertical-align: middle; }
.student-row:hover td { background: rgba(44, 74, 62, 0.01); }

/* Custom Radio Styling */
.status-options { display: flex; gap: 6px; }
.status-btn { position: relative; }
.status-btn input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.status-lbl {
    display: inline-block; padding: 8px 14px; font-size: 0.82rem; font-weight: 700; border-radius: 30px;
    border: 1px solid rgba(44, 74, 62, 0.15); background: var(--white); color: var(--gray); cursor: pointer; transition: var(--transition);
}
.status-btn input[type="radio"]:checked + .status-lbl.p-lbl { background: var(--clr-present); border-color: var(--clr-present); color: var(--white); box-shadow: 0 3px 8px rgba(45,106,79,0.3); }
.status-btn input[type="radio"]:checked + .status-lbl.a-lbl { background: var(--clr-absent); border-color: var(--clr-absent); color: var(--white); box-shadow: 0 3px 8px rgba(230,57,70,0.3); }
.status-btn input[type="radio"]:checked + .status-lbl.l-lbl { background: var(--clr-late); border-color: var(--clr-late); color: var(--white); box-shadow: 0 3px 8px rgba(244,162,97,0.3); }
.status-btn input[type="radio"]:checked + .status-lbl.e-lbl { background: var(--clr-excused); border-color: var(--clr-excused); color: var(--white); box-shadow: 0 3px 8px rgba(69,123,157,0.3); }

/* ── Alerts ── */
.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
.alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

@media(max-width: 768px) {
    .main { margin-right: 0; width: 100%; }
    .sidebar { transform: translateX(280px); }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .attendance-table th:nth-child(3), .attendance-table td:nth-child(3) { display: none; } /* إخفاء البريد على الشاشات الصغيرة */
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
        <a class="nav-link active" href="attendance.php">
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
        <div class="topbar-title">📋 رصد حضور وغياب الحلقات</div>
    </div>

    <div class="content">
        <?php if($msg): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <div class="form-group">
                        <label>الحلقة المستهدفة</label>
                        <select name="halqa_id" class="form-control" onchange="this.form.submit()">
                            <?php if(empty($myHalaqat)): ?>
                                <option value="">لا توجد حلقات نشطة حالياً</option>
                            <?php endif; ?>
                            <?php foreach($myHalaqat as $h): ?>
                                <option value="<?= $h['id'] ?>" <?= $selectedHalqa == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>تاريخ اليوم</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" class="form-control" onchange="this.form.submit()">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">عرض السجل</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if($selectedHalqa): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">👥 قائمة طلاب الحلقة المحددة</div>
                </div>

                <div class="bulk-actions">
                    <span class="bulk-label">⚡ تعيين سريع للكل:</span>
                    <button type="button" class="btn btn-primary" style="padding:6px 14px; font-size:0.8rem; background:var(--clr-present);" onclick="markAll('present')">حضور للكل</button>
                    <button type="button" class="btn btn-primary" style="padding:6px 14px; font-size:0.8rem; background:var(--clr-absent);" onclick="markAll('absent')">غياب للكل</button>
                    <button type="button" class="btn btn-primary" style="padding:6px 14px; font-size:0.8rem; background:var(--clr-late);" onclick="markAll('late')">تأخير للكل</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_attendance">
                    <input type="hidden" name="halqa_id" value="<?= $selectedHalqa ?>">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate) ?>">

                    <?php if(empty($students)): ?>
                        <div style="padding:50px; text-align:center; color:var(--gray); font-weight:500;">لا يوجد طلاب مقيدين في هذه الحلقة حالياً.</div>
                    <?php else: ?>
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th>اسم الطالب</th>
                                    <th>البريد الإلكتروني</th>
                                    <th>حالة الحضور</th>
                                    <th>ملاحظات المعلم / سبب الغياب</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($students as $s): $curr = $s['status'] ?? 'present'; ?>
                                <tr class="student-row" id="row_<?= $s['id'] ?>">
                                    <td>
                                        <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($s['name']) ?></div>
                                    </td>
                                    <td style="color:var(--gray); font-size:0.88rem;"><?= htmlspecialchars($s['email']) ?></td>
                                    <td>
                                        <div class="status-options">
                                            <label class="status-btn">
                                                <input type="radio" name="status[<?= $s['id'] ?>]" value="present" data-student="<?= $s['id'] ?>" <?= $curr === 'present' ? 'checked' : '' ?>>
                                                <span class="status-lbl p-lbl">حاضر</span>
                                            </label>
                                            <label class="status-btn">
                                                <input type="radio" name="status[<?= $s['id'] ?>]" value="absent" data-student="<?= $s['id'] ?>" <?= $curr === 'absent' ? 'checked' : '' ?>>
                                                <span class="status-lbl a-lbl">غائب</span>
                                            </label>
                                            <label class="status-btn">
                                                <input type="radio" name="status[<?= $s['id'] ?>]" value="late" data-student="<?= $s['id'] ?>" <?= $curr === 'late' ? 'checked' : '' ?>>
                                                <span class="status-lbl l-lbl">متأخر</span>
                                            </label>
                                            <label class="status-btn">
                                                <input type="radio" name="status[<?= $s['id'] ?>]" value="excused" data-student="<?= $s['id'] ?>" <?= $curr === 'excused' ? 'checked' : '' ?>>
                                                <span class="status-lbl e-lbl">معذور</span>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="note[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['note'] ?? '') ?>" placeholder="مثال: تأخر لعذر طبي..." class="form-control" style="padding:6px 10px; font-size:0.85rem;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="padding:24px; text-align:left; border-top:1px solid rgba(44, 74, 62, 0.08); background:rgba(44, 74, 62, 0.01)">
                            <button type="submit" class="btn btn-gold" style="box-shadow: 0 4px 12px rgba(212,175,55,0.2);">💾 حفظ وتأكيد سجل الحضور</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function markAll(status) {
    document.querySelectorAll('.student-row').forEach(row => {
        const targetRadio = row.querySelector(`input[value="${status}"]`);
        if(targetRadio) {
            targetRadio.checked = true;
        }
    });
}
</script>
</body>
</html>
