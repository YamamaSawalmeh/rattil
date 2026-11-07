<?php
require_once '../config/db.php';
requireRole('teacher');
$user = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$msg = '';

// ── POST: منح شارة ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'grant_badge') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $bid = (int)($_POST['badge_id'] ?? 0);
        // تحقق أن الطالب في حلقة المعلم
        $chk = $pdo->prepare("SELECT COUNT(*) FROM halqa_students hs JOIN halaqat h ON h.id=hs.halqa_id WHERE hs.student_id=? AND h.teacher_id=?");
        $chk->execute([$sid, $user['id']]);
        if ($chk->fetchColumn() > 0 && $sid && $bid) {
            try {
                $pdo->prepare("INSERT INTO student_badges (student_id,badge_id,granted_by) VALUES (?,?,?)")
                    ->execute([$sid, $bid, $user['id']]);
                // جلب اسم الشارة للإشعار
                $bname = $pdo->prepare("SELECT name FROM badges WHERE id=?");
                $bname->execute([$bid]);
                $badgeName = $bname->fetchColumn();
                sendNotification($sid,'badge','شارة جديدة! 🏅',"منحك المعلم شارة: $badgeName",$pdo);
                $msg = "تم منح الشارة بنجاح ✅";
            } catch(\Exception $e){ $msg = 'الطالب يمتلك هذه الشارة مسبقاً'; }
        }
    }
    if ($action === 'add_points') {
        $sid    = (int)($_POST['student_id'] ?? 0);
        $points = (int)($_POST['points'] ?? 0);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM halqa_students hs JOIN halaqat h ON h.id=hs.halqa_id WHERE hs.student_id=? AND h.teacher_id=?");
        $chk->execute([$sid, $user['id']]);
        if ($chk->fetchColumn() > 0 && $points > 0) {
            addPointsToStudent($sid, $points, $pdo);
            sendNotification($sid,'points','نقاط جديدة 🌟',"أضاف المعلم {$points} نقطة لرصيدك",$pdo);
            $msg = "تم إضافة {$points} نقطة ✅";
        }
    }
}

// ── فلتر وبحث ─────────────────────────────────────────────
$filterHalqa = (int)($_GET['halqa'] ?? 0);
$search = sanitize($_GET['q'] ?? '');

// جلب حلقات المعلم للفلتر
$stmtMyH = $pdo->prepare("SELECT id,name FROM halaqat WHERE teacher_id=? AND status='active'");
$stmtMyH->execute([$user['id']]);
$myHalaqat = $stmtMyH->fetchAll();

// جلب الطلاب
$where = "WHERE h.teacher_id=?";
$params = [$user['id']];
if ($filterHalqa) { $where .= " AND hs.halqa_id=?"; $params[] = $filterHalqa; }
if ($search) { $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)"; $s="%$search%"; $params[]=$s;$params[]=$s;$params[]=$s; }

$stmtS = $pdo->prepare("SELECT DISTINCT u.id,u.name,u.email,u.phone,u.avatar,u.created_at,
    sp.points, sp.level, sp.total_earned,
    (SELECT COUNT(*) FROM recitation_log rl WHERE rl.student_id=u.id AND rl.teacher_id=?) AS recitation_count,
    (SELECT COUNT(*) FROM attendance a WHERE a.student_id=u.id AND a.status='present' AND a.teacher_id=?) AS present_count,
    (SELECT GROUP_CONCAT(hh.name SEPARATOR ' | ') FROM halqa_students hss JOIN halaqat hh ON hh.id=hss.halqa_id WHERE hss.student_id=u.id AND hh.teacher_id=?) AS halqat_names
    FROM halqa_students hs
    JOIN halaqat h ON h.id=hs.halqa_id
    JOIN users u ON u.id=hs.student_id
    LEFT JOIN student_points sp ON sp.student_id=u.id
    $where
    ORDER BY sp.points DESC");
// prepend teacher_id 3 times for subqueries
$stmtS->execute(array_merge([$user['id'],$user['id'],$user['id']], $params));
$students = $stmtS->fetchAll();

// الشارات المتاحة للمنح (teacher_granted فقط)
$stmtB = $pdo->query("SELECT * FROM badges WHERE type='teacher_granted'");
$badges = $stmtB->fetchAll();

// الطالب المحدد
$selectedStudent = null;
$studentBadges   = [];
$studentLogs     = [];
if (isset($_GET['sid'])) {
    $sid = (int)$_GET['sid'];
    $stmtSS = $pdo->prepare("SELECT u.*,sp.points,sp.level,sp.total_earned FROM users u LEFT JOIN student_points sp ON sp.student_id=u.id WHERE u.id=? AND u.role='student'");
    $stmtSS->execute([$sid]);
    $selectedStudent = $stmtSS->fetch();
    if ($selectedStudent) {
        $stmtSB = $pdo->prepare("SELECT b.*,sb.granted_at FROM student_badges sb JOIN badges b ON b.id=sb.badge_id WHERE sb.student_id=?");
        $stmtSB->execute([$sid]);
        $studentBadges = $stmtSB->fetchAll();
        $stmtSL = $pdo->prepare("SELECT * FROM recitation_log WHERE student_id=? AND teacher_id=? ORDER BY recorded_at DESC LIMIT 10");
        $stmtSL->execute([$sid,$user['id']]);
        $studentLogs = $stmtSL->fetchAll();
    }
}

$levelColors=[1=>'#94a3b8',2=>'#3498db',3=>'#22c55e',4=>'#e67e22',5=>'#e74c3c'];
$gradeColors=['ممتاز'=>'#1C3329','جيد جداً'=>'#2C4A3E','جيد'=>'#707A75','مقبول'=>'#D4AF37','يحتاج مراجعة'=>'#e74c3c'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>الطلاب — رتِّل</title>
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
.greeting-chip { background: rgba(44, 74, 62, 0.06); padding: 8px 16px; border-radius: 30px; font-size: 0.9rem; font-weight: 600; color: var(--primary); }

/* ── Content Area ── */
.content { padding: 32px; flex: 1; display: grid; grid-template-columns: 1fr 380px; gap: 24px; align-items: start; animation: fadeIn 0.5s ease-out forwards; max-width: 1400px; width: 100%; margin: 0 auto; }

/* ── Card Component ── */
.card {
    background: var(--beige-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
    border: 1px solid rgba(44, 74, 62, 0.08); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition);
}
.card-header {
    padding: 22px 24px; border-bottom: 1px solid rgba(44, 74, 62, 0.1); display: flex; align-items: center;
    justify-content: space-between; background: linear-gradient(90deg, rgba(44, 74, 62, 0.05) 0%, rgba(255,255,255,0) 100%);
}
.card-title { font-family: 'Amiri', serif; font-size: 1.4rem; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 10px; }
.card-title svg { width: 22px; height: 22px; color: var(--primary-light); }
.card-body { padding: 24px; }

/* ── Filters ── */
.filters { display: flex; gap: 12px; padding: 18px 24px; flex-wrap: wrap; background: rgba(44, 74, 62, 0.03); border-bottom: 1px solid rgba(44, 74, 62, 0.08); align-items: center; }
.filter-input { padding: 10px 14px; border: 1px solid rgba(44, 74, 62, 0.2); border-radius: var(--radius-sm); font-family: 'Tajawal', sans-serif; font-size: 0.9rem; color: var(--gray-dark); background: var(--white); transition: var(--transition); }
.filter-input:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(44,74,62,0.1); }

/* ── Buttons ── */
.btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: var(--radius-sm);
    font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: var(--transition); border: none; text-decoration: none;
}
.btn-primary { background: var(--primary-light); color: var(--white); }
.btn-primary:hover { background: var(--primary); }
.btn-gold { background: var(--gold); color: var(--primary); }
.btn-gold:hover { background: var(--gold-dark); color: var(--white); }
.btn-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; }

/* ── Tables ── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px; background: rgba(44, 74, 62, 0.06); font-weight: 700; font-size: 0.88rem; color: var(--primary); text-align: right; border-bottom: 2px solid rgba(44, 74, 62, 0.1); }
.data-table td { padding: 14px; border-bottom: 1px solid rgba(44, 74, 62, 0.05); font-size: 0.92rem; text-align: right; vertical-align: middle; }
.data-table tr { transition: var(--transition); }
.data-table tr:hover td { background: var(--beige-hover); cursor: pointer; }
.level-chip { border-radius: 20px; padding: 3px 10px; font-size: 0.78rem; font-weight: 700; color: var(--white); display: inline-block; }

/* ── Details Panel ── */
.detail-sidebar { display: flex; flex-direction: column; gap: 24px; }
.student-profile { text-align: center; padding: 12px 0; }
.big-avatar { width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(135deg, #f3e7c4 0%, var(--gold) 100%); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: var(--primary); margin: 0 auto 16px; font-family: 'Amiri', serif; box-shadow: var(--shadow-sm); }
.s-name { font-family: 'Amiri', serif; font-size: 1.5rem; color: var(--primary); font-weight: 700; }
.s-email { font-size: 0.88rem; color: var(--gray); margin-top: 4px; }
.s-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 20px; }
.s-stat-box { background: var(--white); border: 1px solid rgba(44, 74, 62, 0.06); border-radius: var(--radius-md); padding: 12px; text-align: center; box-shadow: var(--shadow-sm); }
.s-stat-box .val { font-size: 1.3rem; font-weight: 700; color: var(--primary-light); }
.s-stat-box .lbl { font-size: 0.75rem; color: var(--gray); margin-top: 2px; font-weight: 600; }
.badge-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.badge-chip { padding: 6px 12px; background: var(--white); border: 1px solid var(--gold); border-radius: 20px; font-size: 0.82rem; display: flex; align-items: center; gap: 6px; font-weight: 500; box-shadow: var(--shadow-sm); }
.grade-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; color: var(--white); }

/* ── Alerts ── */
.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }

/* ── Modals / Overlays ── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 999; display: none; align-items: center; justify-content: center; opacity: 0; transition: var(--transition); }
.modal-overlay.show { display: flex; opacity: 1; }
.modal { background: var(--beige-card); border-radius: var(--radius-lg); padding: 32px; width: 460px; max-width: 95vw; box-shadow: var(--shadow-md); border: 1px solid rgba(44, 74, 62, 0.1); transform: translateY(20px); transition: var(--transition); }
.modal-overlay.show .modal { transform: translateY(0); }
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.modal-title { font-family: 'Amiri', serif; font-size: 1.5rem; color: var(--primary); font-weight: 700; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray); transition: var(--transition); }
.modal-close:hover { color: var(--primary); }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 0.88rem; font-weight: 600; color: var(--primary); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid rgba(44, 74, 62, 0.2); border-radius: var(--radius-sm); font-family: 'Tajawal', sans-serif; font-size: 0.92rem; color: var(--gray-dark); background: var(--white); }
.form-control:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(44,74,62,0.1); }

@media(max-width: 1100px){ .content { grid-template-columns: 1fr; } .detail-sidebar { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; } }
@media(max-width: 768px){ .detail-sidebar { grid-template-columns: 1fr; } }
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
        <a class="nav-link active" href="students.php">
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
        <div class="topbar-title">👥 إدارة وملفات الطلاب</div>
        <div class="greeting-chip">إجمالي المقيدين: <?= count($students) ?> طالب</div>
    </div>

    <div class="content">
        <div>
            <?php if($msg): ?>
                <div class="alert alert-success">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="GET" class="filters">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 بحث بالاسم، البريد أو الهاتف..." class="filter-input" style="flex:1; min-width:200px;">
                    
                    <select name="halqa" class="filter-input">
                        <option value="">كل الحلقات</option>
                        <?php foreach($myHalaqat as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= $filterHalqa == $h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">تطبيق الفلتر</button>
                    <?php if($search || $filterHalqa): ?>
                        <a href="students.php" class="btn" style="background:var(--gray-light); color:var(--gray-dark);">إعادة تعيين</a>
                    <?php endif; ?>
                </form>

                <?php if(empty($students)): ?>
                    <div style="padding:50px; text-align:center; color:var(--gray); font-weight:500;">لا يوجد طلاب مطابقون لمعايير البحث الحالية.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:50px; text-align:center;">#</th>
                                <th>الطالب</th>
                                <th>الحلقة المقيد بها</th>
                                <th>النقاط</th>
                                <th>المستوى</th>
                                <th style="text-align:center;">التسميعات</th>
                                <th style="text-align:center;">إجراءات سريعة</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($students as $i=>$s): $lc = $levelColors[$s['level']??1] ?? '#94a3b8'; ?>
                            <tr onclick="selectStudent(<?= $s['id'] ?>)" id="row_<?= $s['id'] ?>">
                                <td style="text-align:center; color:var(--gray); font-size:0.85rem; font-weight:600;"><?= $i+1 ?></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px">
                                        <div class="avatar-circle" style="width:36px; height:36px; font-size:0.95rem; background:var(--primary-light); color:var(--white);"><?= mb_substr($s['name'],0,1,'utf-8') ?></div>
                                        <div>
                                            <div style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($s['name']) ?></div>
                                            <div style="font-size:0.8rem; color:var(--gray); margin-top:2px;"><?= htmlspecialchars($s['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size:0.88rem; font-weight:500; color:var(--gray-dark);"><?= htmlspecialchars($s['halqat_names']??'—') ?></td>
                                <td style="font-weight:700; color:var(--gold-dark);"><?= number_format($s['points']??0) ?> ن</td>
                                <td><span class="level-chip" style="background:<?= $lc ?>;">مستوى <?= $s['level']??1 ?></span></td>
                                <td style="text-align:center; font-weight:600; color:var(--primary);"><?= $s['recitation_count'] ?></td>
                                <td onclick="event.stopPropagation();" style="text-align:center;">
                                    <button class="btn btn-sm btn-gold" onclick="openGrantBadge(<?= $s['id'] ?>,'<?= addslashes($s['name']) ?>')">🏅 شارة</button>
                                    <button class="btn btn-sm" style="background:rgba(44,74,62,0.08); color:var(--primary-light);" onclick="openAddPoints(<?= $s['id'] ?>,'<?= addslashes($s['name']) ?>')">⭐ نقاط</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="detail-sidebar" id="detailSidebar">
            <?php if($selectedStudent): ?>
                <div class="card">
                    <div class="student-profile">
                        <div class="big-avatar"><?= mb_substr($selectedStudent['name'],0,1,'utf-8') ?></div>
                        <div class="s-name"><?= htmlspecialchars($selectedStudent['name']) ?></div>
                        <div class="s-email"><?= htmlspecialchars($selectedStudent['email']) ?></div>
                        <div class="s-stats">
                            <div class="s-stat-box"><div class="val"><?= number_format($selectedStudent['points']??0) ?></div><div class="lbl">النقاط الحالية</div></div>
                            <div class="s-stat-box"><div class="val"><?= $selectedStudent['total_earned']??0 ?></div><div class="lbl">إجمالي المكتسب</div></div>
                            <div class="s-stat-box"><div class="val"><?= $selectedStudent['level']??1 ?></div><div class="lbl">المستوى التعليمي</div></div>
                        </div>
                    </div>
                </div>
                
                <?php if(!empty($studentBadges)): ?>
                    <div class="card">
                        <div class="card-header"><div class="card-title">🏅 الأوسمة والشارات الممنوحة (<?= count($studentBadges) ?>)</div></div>
                        <div class="card-body">
                            <div class="badge-grid">
                                <?php foreach($studentBadges as $b): ?>
                                    <div class="badge-chip"><span><?= $b['icon'] ?></span> <?= htmlspecialchars($b['name']) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($studentLogs)): ?>
                    <div class="card">
                        <div class="card-header"><div class="card-title">📖 آخر عمليات التسميع الموثقة</div></div>
                        <div class="card-body" style="padding:16px;">
                            <?php foreach($studentLogs as $l): $c = $gradeColors[$l['grade']] ?? '#94a3b8'; ?>
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid rgba(44,74,62,0.06);">
                                    <div>
                                        <div style="font-size:0.9rem; font-weight:700; color:var(--primary);">سورة <?= htmlspecialchars($l['surah_name']) ?></div>
                                        <div style="font-size:0.78rem; color:var(--gray); margin-top:2px;">الآيات: <?= $l['ayah_from'] ?> - <?= $l['ayah_to'] ?> · <?= date('m/d', strtotime($l['recorded_at'])) ?></div>
                                    </div>
                                    <span class="grade-badge" style="background:<?= $c ?>;"><?= $l['grade'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card">
                    <div class="card-body" style="text-align:center; color:var(--gray); padding:50px; font-weight:500;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:40px; height:40px; color:var(--gray); opacity:0.5; margin-bottom:12px; display:inline-block;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p>انقر فوق سجل أي طالب من الجدول المقابل لاستعراض الملف الأكاديمي الشامل هنا.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="grantBadgeModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">🏅 منح وسام / شارة طالب</div>
            <button class="modal-close" onclick="closeModal('grantBadgeModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="grant_badge">
            <input type="hidden" name="student_id" id="badgeStudentId">
            <div class="form-group" style="background:var(--white); padding:10px; border-radius:6px; border:1px solid rgba(44,74,62,0.06); text-align:center;">
                <label id="badgeStudentName" style="font-size:1.05rem; font-weight:700; color:var(--primary); margin:0;"></label>
            </div>
            <div class="form-group">
                <label>اختر الشارة التكريمية المتاحة:</label>
                <select name="badge_id" class="form-control" required>
                    <option value="">— اختر الشارة من القائمة —</option>
                    <?php foreach($badges as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%; justify-content:center; margin-top:10px;">اعتماد ومنح الشارة للطالب</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="addPointsModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">⭐ مكافأة وإضافة نقاط تميز</div>
            <button class="modal-close" onclick="closeModal('addPointsModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_points">
            <input type="hidden" name="student_id" id="pointsStudentId">
            <div class="form-group" style="background:var(--white); padding:10px; border-radius:6px; border:1px solid rgba(44,74,62,0.06); text-align:center;">
                <label id="pointsStudentName" style="font-size:1.05rem; font-weight:700; color:var(--primary); margin:0;"></label>
            </div>
            <div class="form-group">
                <label>مقدار النقاط الإضافية المعطاة:</label>
                <input type="number" name="points" class="form-control" min="1" max="500" placeholder="مثال: 30" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:10px;">إيداع ومزامنة النقاط بالرصيد</button>
        </form>
    </div>
</div>

<script>
function selectStudent(id){
    location.href='students.php?sid='+id+'<?= $filterHalqa?"&halqa=$filterHalqa":'' ?><?= $search?"&q=".urlencode($search):'' ?>';
}
function openGrantBadge(id,name){
    document.getElementById('badgeStudentId').value=id;
    document.getElementById('badgeStudentName').textContent='الطالب المستهدف: '+name;
    const modal = document.getElementById('grantBadgeModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}
function openAddPoints(id,name){
    document.getElementById('pointsStudentId').value=id;
    document.getElementById('pointsStudentName').textContent='الطالب المستهدف: '+name;
    const modal = document.getElementById('addPointsModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}
function closeModal(id){
    const modal = document.getElementById(id);
    modal.classList.remove('show');
    setTimeout(() => modal.style.display = 'none', 300);
}
document.querySelectorAll('.modal-overlay').forEach(m=>{
    m.addEventListener('click',e=>{if(e.target===m) closeModal(m.id);});
});
</script>
</body>
</html>
