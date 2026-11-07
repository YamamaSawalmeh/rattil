<?php
require_once '../config/db.php';
requireRole('teacher');
$user = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$msg = ''; $error = '';
 
// قائمة السور (مختصرة — الأكثر استخداماً)
$suwarList = [
    [1,'الفاتحة',7],[2,'البقرة',286],[3,'آل عمران',200],[4,'النساء',176],
    [5,'المائدة',120],[6,'الأنعام',165],[36,'يس',83],[55,'الرحمن',78],
    [56,'الواقعة',96],[67,'الملك',30],[78,'النبأ',40],[87,'الأعلى',19],
    [89,'الفجر',30],[93,'الضحى',11],[94,'الشرح',8],[95,'التين',8],
    [96,'العلق',19],[97,'القدر',5],[98,'البينة',8],[99,'الزلزلة',8],
    [100,'العاديات',11],[101,'القارعة',11],[102,'التكاثر',8],[103,'العصر',3],
    [104,'الهمزة',9],[105,'الفيل',5],[106,'قريش',4],[107,'الماعون',7],
    [108,'الكوثر',3],[109,'الكافرون',6],[110,'النصر',3],[111,'المسد',5],
    [112,'الإخلاص',4],[113,'الفلق',5],[114,'الناس',6]
];

$surahAyahCounts = array_column($suwarList, 2, 0);

// ── إجراء: حذف سجل تسميع ──────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $stmtD = $pdo->prepare("SELECT id FROM recitation_log WHERE id=? AND teacher_id=?");
    $stmtD->execute([$did, $user['id']]);
    if ($stmtD->fetch()) {
        $pdo->prepare("DELETE FROM recitation_log WHERE id=?")->execute([$did]);
        $msg = "تم حذف سجل التسميع بنجاح 🗑️";
    } else {
        $error = "غير مصرح لك بحذف هذا السجل أو أنه غير موجود.";
    }
}

// ── إجراء: إضافة سجل تسميع جديد ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_recitation') {
    $student_id   = (int)($_POST['student_id'] ?? 0);
    $surah_num    = (int)($_POST['surah_num'] ?? 0);
    $surah_name   = sanitize($_POST['surah_name'] ?? '');
    $ayah_from    = (int)($_POST['ayah_from'] ?? 1);
    $ayah_to      = (int)($_POST['ayah_to'] ?? 1);
    $grade        = sanitize($_POST['grade'] ?? 'ممتاز');
    $notes        = sanitize($_POST['notes'] ?? '');
    $surahLabel   = $surah_name;
    
    // حساب النقاط بناءً على التقييم الممنوح
    $points_map = ['ممتاز' => 20, 'جيد جداً' => 15, 'جيد' => 10, 'مقبول' => 5, 'يحتاج مراجعة' => 2];
    $points_awarded = $points_map[$grade] ?? 0;
    $totalAyahs = (int)($surahAyahCounts[$surah_num] ?? 0);
    $completeSurah = isset($_POST['complete_surah']) && $totalAyahs > 0;
    if ($completeSurah) {
        $ayah_from = 1;
        $ayah_to = $totalAyahs;
    }

    // التحقق من صحة البيانات وأن الطالب ينتمي لحلقات هذا المعلم
    $chk = $pdo->prepare("SELECT h.id FROM halqa_students hs JOIN halaqat h ON h.id=hs.halqa_id WHERE hs.student_id=? AND h.teacher_id=? LIMIT 1");
    $chk->execute([$student_id, $user['id']]);
    $halqaId = (int)($chk->fetchColumn() ?: 0);
    
    if ($halqaId === 0) {
        $error = "عذراً، الطالب المحدد غير مسجل في أي من حلقاتك التعليمية.";
    } elseif (!$surah_num || !$surah_name) {
        $error = "يرجى تحديد السورة المراد تسميعها بشكل صحيح.";
    } elseif ($ayah_from < 1 || $ayah_to < $ayah_from) {
        $error = "يرجى إدخال مدى آيات صحيح للتسميع.";
    } else {
        $stmtI = $pdo->prepare("INSERT INTO recitation_log (student_id, teacher_id, halqa_id, surah_number, surah_name, ayah_from, ayah_to, grade, points_awarded, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtI->execute([$student_id, $user['id'], $halqaId, $surah_num, $surah_name, $ayah_from, $ayah_to, $grade, $points_awarded, $notes]);
        
        if ($points_awarded >= 15 || $completeSurah) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS quran_tracking (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id INT NOT NULL,
                    surah_number INT NOT NULL,
                    ayah_number INT NOT NULL,
                    status ENUM('memorized','reviewing','last_stop') NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_ayah (student_id, surah_number, ayah_number),
                    INDEX idx_qt_student_surah (student_id, surah_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            for ($a = $ayah_from; $a <= $ayah_to; $a++) {
                $pdo->prepare("INSERT INTO student_quran_marks (student_id,surah_number,ayah_number,status) VALUES(?,?,?,'memorized')
                               ON DUPLICATE KEY UPDATE status='memorized', marked_at=CURRENT_TIMESTAMP")
                    ->execute([$student_id, $surah_num, $a]);
                $pdo->prepare("INSERT INTO quran_tracking (student_id,surah_number,ayah_number,status) VALUES(?,?,?,'memorized')
                               ON DUPLICATE KEY UPDATE status='memorized', updated_at=CURRENT_TIMESTAMP")
                    ->execute([$student_id, $surah_num, $a]);
            }
        }
        
        // تحديث محفظة نقاط الطالب وإرسال الإشعار التلقائي له
        $totalAyahs = (int)($surahAyahCounts[$surah_num] ?? 0);
        if ($totalAyahs > 0) {
            $mrkStmt = $pdo->prepare("SELECT COUNT(DISTINCT ayah_number) FROM quran_tracking WHERE student_id=? AND surah_number=? AND status='memorized'");
            $mrkStmt->execute([$student_id, $surah_num]);
            $memorizedCount = (int)$mrkStmt->fetchColumn();

            if ($memorizedCount >= $totalAyahs) {
                $treeType = getTreeType($totalAyahs);
                $pdo->prepare("INSERT INTO student_garden (student_id,tree_type,surah_name,surah_number) VALUES(?,?,?,?)
                               ON DUPLICATE KEY UPDATE planted_at=planted_at")
                    ->execute([$student_id, $treeType, $surahLabel, $surah_num]);
                sendNotification($student_id, 'garden', 'شجرة جديدة في حديقتك!', "أتممت حفظ سورة " . $surahLabel . "، فزرعنا لك شجرة جديدة في حديقة الإنجاز.", $pdo);
            }
        }
        addPointsToStudent($student_id, $points_awarded, $pdo);
        sendNotification($student_id, 'recitation', 'تم توثيق تسميع جديد 📖', "أبرعت! سجل لك المعلم تسميع سورة " . $surahLabel . " بتقدير (" . $grade . ") وتم إضافة " . $points_awarded . " نقطة لرصيدك.", $pdo);
        
        $msg = "تم تسجيل جلسة التسميع بنجاح وإيداع النقاط بالطالب التابع لك 🎉";
    }
}

// ── جلب طلاب المعلم الحالي لبناء القائمة المنسدلة ───────────────
$stmtAllS = $pdo->prepare("SELECT DISTINCT u.id, u.name FROM halqa_students hs JOIN halaqat h ON h.id=hs.halqa_id JOIN users u ON u.id=hs.student_id WHERE h.teacher_id=? ORDER BY u.name ASC");
$stmtAllS->execute([$user['id']]);
$myStudents = $stmtAllS->fetchAll();

// ── جلب آخر 30 سجل تسميع موثق لدى المعلم ──────────────────────
$stmtLogs = $pdo->prepare("SELECT rl.*, u.name AS student_name FROM recitation_log rl JOIN users u ON u.id=rl.student_id WHERE rl.teacher_id=? ORDER BY rl.recorded_at DESC LIMIT 30");
$stmtLogs->execute([$user['id']]);
$logs = $stmtLogs->fetchAll();

$gradeColors = ['ممتاز'=>'#1C3329', 'جيد جداً'=>'#2C4A3E', 'جيد'=>'#707A75', 'مقبول'=>'#B8860B', 'يحتاج مراجعة'=>'#e74c3c'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>جلسات التسميع — رتِّل</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1C3329;        
    --primary-light: #2C4A3E;  
    --primary-soft: #EAECE6;   
    --gold: #D4AF37;
    --gold-dark: #B8860B;
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
    margin-right: 280px; flex: 1; min-height: 100vh;
    display: flex; flex-direction: column; width: calc(100% - 280px);
}
.topbar {
    background: rgba(250, 247, 240, 0.85); backdrop-filter: blur(12px);
    padding: 16px 32px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(44, 74, 62, 0.1); position: sticky; top: 0; z-index: 50;
}
.topbar-title { font-family: 'Amiri', serif; font-size: 1.6rem; color: var(--primary); font-weight: 700; }

/* ── Content Area ── */
.content { padding: 32px; flex: 1; animation: fadeIn 0.5s ease-out forwards; max-width: 1240px; width: 100%; margin: 0 auto; }

/* ── Card Component ── */
.card {
    background: var(--beige-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
    border: 1px solid rgba(44, 74, 62, 0.08); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition); margin-bottom: 24px;
}
.card-header {
    padding: 20px 24px; border-bottom: 1px solid rgba(44, 74, 62, 0.1); display: flex; align-items: center;
    justify-content: space-between; background: linear-gradient(90deg, rgba(44, 74, 62, 0.05) 0%, rgba(255,255,255,0) 100%);
}
.card-title { font-family: 'Amiri', serif; font-size: 1.35rem; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 10px; }
.card-body { padding: 24px; }

/* ── Form Layout ── */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
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
.btn-sm { padding: 6px 12px; font-size: 0.82rem; border-radius: 6px; }

/* ── Tables ── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px; background: rgba(44, 74, 62, 0.06); font-weight: 700; font-size: 0.88rem; color: var(--primary); text-align: right; border-bottom: 2px solid rgba(44, 74, 62, 0.1); }
.data-table td { padding: 14px; border-bottom: 1px solid rgba(44, 74, 62, 0.05); font-size: 0.92rem; text-align: right; vertical-align: middle; }
.data-table tr:hover td { background: var(--beige-hover); }

/* ── Badges ── */
.grade-badge { padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 600; color: var(--white); display: inline-block; }
.points-chip { background: var(--gold-soft); color: var(--gold-dark); border: 1px solid var(--gold); padding: 2px 10px; border-radius: 30px; font-weight: 700; font-size: 0.85rem; }

/* ── Alerts ── */
.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
.alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

@media(max-width: 768px) {
    .main { margin-right: 0; width: 100%; }
    .sidebar { transform: translateX(280px); }
    .content { padding: 16px; }
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
        <a class="nav-link active" href="recitation_log.php">
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
        <div class="topbar-title">📖 إدارة جلسات التسميع والحفظ</div>
        <button class="btn btn-gold btn-sm" onclick="toggleForm()">➕ رصد تسميع جديد</button>
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

        <div class="card" id="addFormCard" style="display: none; animation: fadeIn 0.4s ease;">
            <div class="card-header"><div class="card-title">📝 توثيق رصد قرآني جديد للطلاب</div></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_recitation">
                    <input type="hidden" name="surah_name" id="surahName" value="">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اختيار الطالب المقيد</label>
                            <select name="student_id" class="form-control" required>
                                <option value="">— اختر اسم الطالب —</option>
                                <?php foreach($myStudents as $st): ?>
                                    <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>السورة الكريمة</label>
                            <select name="surah_num" id="surahSelect" class="form-control" onchange="updateSurahName(this)" required>
                                <option value="">— اختر السورة —</option>
                                <?php foreach($suwarList as $su): ?>
                                    <option value="<?= $su[0] ?>" data-name="<?= $su[1] ?>" data-ayahs="<?= $su[2] ?>">سورة <?= $su[1] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                                                <div class="form-group" style="grid-column:1/-1; background:rgba(36,96,64,.07); border:1px solid rgba(36,96,64,.16); border-radius:10px; padding:.85rem 1rem;">
                            <label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;margin:0;color:var(--primary);font-weight:800">
                                <input type="checkbox" name="complete_surah" value="1" style="width:18px;height:18px;accent-color:var(--primary-light)">
                                إتمام حفظ السورة كاملة وزرع شجرة في حديقة الطالب
                            </label>
                        </div>
<div class="form-group">
                            <label>من الآية</label>
                            <input type="number" name="ayah_from" id="ayahFrom" class="form-control" min="1" value="1" required>
                        </div>

                        <div class="form-group">
                            <label>إلى الآية</label>
                            <input type="number" name="ayah_to" id="ayahTo" class="form-control" min="1" value="1" required>
                        </div>

                        <div class="form-group">
                            <label>التقييم والدرجة</label>
                            <select name="grade" class="form-control" required>
                                <option value="ممتاز">ممتاز (20 ن)</option>
                                <option value="جيد جداً">جيد جداً (15 ن)</option>
                                <option value="جيد">جيد (10 ن)</option>
                                <option value="مقبول">مقبول (5 ن)</option>
                                <option value="يحتاج مراجعة">يحتاج مراجعة (2 ن)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:18px;">
                        <label>ملاحظات الأداء أو التوجيهات التجودية</label>
                        <input type="text" name="notes" placeholder="أدخل أي ملاحظات خاصة بمواضع الإدغام أو أحكام المدود..." class="form-control">
                    </div>

                    <div style="margin-top:24px; text-align:left;">
                        <button type="button" class="btn" style="background:var(--gray-light); color:var(--gray-dark);" onclick="toggleForm()">إلغاء</button>
                        <button type="submit" class="btn btn-primary">💾 اعتماد وحفظ التسميع</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 آخر 30 جلسة تسميع موثقة</div>
            </div>
            
            <?php if(empty($logs)): ?>
                <div style="padding:60px; text-align:center; color:var(--gray); font-weight:500;">لا توجد سجلات تسميع مسجلة حالياً في النظام.</div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>السورة</th>
                                <th>المدى (الآيات)</th>
                                <th>التقدير الحاصل</th>
                                <th>النقاط المكتسبة</th>
                                <th>التوقيت والتاريخ</th>
                                <th>التوجيهات والملاحظات</th>
                                <th style="text-align:center; width:60px;">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($logs as $l): $bg = $gradeColors[$l['grade']] ?? '#94a3b8'; ?>
                            <tr>
                                <td style="font-weight:700; color:var(--primary);"><?= htmlspecialchars($l['student_name']) ?></td>
                                <td style="font-weight:600; color:var(--primary-light);">سورة <?= htmlspecialchars($l['surah_name']) ?></td>
                                <td style="font-variant-numeric: tabular-nums; font-weight:500;"><?= $l['ayah_from'] ?> إلى <?= $l['ayah_to'] ?></td>
                                <td><span class="grade-badge" style="background:<?= $bg ?>;"><?= $l['grade'] ?></span></td>
                                <td>
                                    <?php if($l['points_awarded'] > 0): ?>
                                        <span class="points-chip">+<?= $l['points_awarded'] ?> ن</span>
                                    <?php else: ?>
                                        <span style="color:var(--gray)">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.82rem; color:var(--gray); font-variant-numeric: tabular-nums;"><?= date('Y/m/dd H:i', strtotime($l['recorded_at'])) ?></td>
                                <td style="font-size:0.85rem; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--gray-dark);" title="<?= htmlspecialchars($l['notes'] ?? '') ?>">
                                    <?= htmlspecialchars($l['notes'] ?: '—') ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="?delete=<?= $l['id'] ?>" class="btn btn-sm" style="background:#fee2e2; color:#ef4444; padding:5px 10px;" onclick="return confirm('هل أنت متأكد من رغبتك في حذف سجل هذا التسميع نهائياً؟')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleForm(){
    const card = document.getElementById('addFormCard');
    if(card.style.display === 'none') {
        card.style.display = 'block';
    } else {
        card.style.display = 'none';
    }
}
function updateSurahName(sel){
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('surahName').value = opt.dataset.name || '';
    const maxAyah = opt.dataset.ayahs || '1';
    
    const fromInput = document.getElementById('ayahFrom');
    const toInput = document.getElementById('ayahTo');
    
    fromInput.setAttribute('max', maxAyah);
    toInput.setAttribute('max', maxAyah);
    toInput.value = maxAyah;
}
</script>
</body>
</html>
