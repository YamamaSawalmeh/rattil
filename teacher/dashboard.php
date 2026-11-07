<?php
require_once '../config/db.php';
requireRole('teacher');
$user = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);

// ── إحصائيات سريعة ──────────────────────────────────────────
// عدد الحلقات
$stmtH = $pdo->prepare("SELECT COUNT(*) FROM halaqat WHERE teacher_id=? AND status='active'");
$stmtH->execute([$user['id']]);
$totalHalaqat = (int)$stmtH->fetchColumn();

// عدد الطلاب (غير مكرر)
$stmtS = $pdo->prepare("SELECT COUNT(DISTINCT hs.student_id) FROM halqa_students hs
    JOIN halaqat h ON h.id=hs.halqa_id WHERE h.teacher_id=?");
$stmtS->execute([$user['id']]);
$totalStudents = (int)$stmtS->fetchColumn();

// جلسات تسميع هذا الأسبوع
$stmtR = $pdo->prepare("SELECT COUNT(*) FROM recitation_log WHERE teacher_id=? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmtR->execute([$user['id']]);
$weekRecitations = (int)$stmtR->fetchColumn();

// حضور اليوم
$stmtA = $pdo->prepare("SELECT COUNT(*) FROM attendance a
    JOIN halaqat h ON h.id=a.halqa_id
    WHERE h.teacher_id=? AND a.date=CURDATE() AND a.status='present'");
$stmtA->execute([$user['id']]);
$todayPresent = (int)$stmtA->fetchColumn();

// آخر 5 تسميعات
$stmtLR = $pdo->prepare("SELECT rl.*, u.name AS student_name FROM recitation_log rl
    JOIN users u ON u.id=rl.student_id
    WHERE rl.teacher_id=? ORDER BY rl.recorded_at DESC LIMIT 5");
$stmtLR->execute([$user['id']]);
$latestRecitations = $stmtLR->fetchAll();

// حلقاتي
$stmtMyH = $pdo->prepare("SELECT h.*,
    (SELECT COUNT(*) FROM halqa_students hs WHERE hs.halqa_id=h.id) AS student_count
    FROM halaqat h WHERE h.teacher_id=? AND h.status='active' ORDER BY h.schedule_time");
$stmtMyH->execute([$user['id']]);
$myHalaqat = $stmtMyH->fetchAll();

// أعلى 5 طلاب نقاطاً
$stmtTop = $pdo->prepare("SELECT u.name, u.avatar, sp.points, sp.total_earned, sp.level
    FROM student_points sp JOIN users u ON u.id=sp.student_id
    JOIN halqa_students hs ON hs.student_id=sp.student_id
    JOIN halaqat h ON h.id=hs.halqa_id
    WHERE h.teacher_id=? ORDER BY sp.total_earned DESC LIMIT 5");
$stmtTop->execute([$user['id']]);
$topStudents = $stmtTop->fetchAll();

$gradeColors = [
    'ممتاز'         => 'rgba(44, 74, 62, 0.2)#2C4A3E',
    'جيد جداً'      => 'rgba(61, 94, 80, 0.2)#3D5E50',
    'جيد'           => 'rgba(92, 122, 107, 0.2)#5C7A6B',
    'مقبول'         => 'rgba(212, 175, 55, 0.2)#B8860B',
    'يحتاج مراجعة' => 'rgba(192, 41, 43, 0.2)#c0392b',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة المعلم — رتِّل</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1C3329;        /* زيتوني داكن جداً فخم */
    --primary-light: #2C4A3E;  /* زيتوني كلاسيكي */
    --primary-soft: #EAECE6;   /* بيج مائل للزيتوني الفاتح */
    --gold: #D4AF37;
    --gold-dark: #B8860B;
    --gold-soft: #fbf7eb;
    --cream: #FAF7F0;          /* لون الخلفية الأساسية */
    --beige-card: #F4EFE6;     /* بيج ناعم مخصص للبطاقات */
    --beige-hover: #EFE9DC;    /* درجة بيج أعمق عند التمرير */
    --white: #ffffff;
    --gray-dark: #2F3633;      /* رمادي زيتي داكن للنصوص */
    --gray: #707A75;
    --gray-light: #EFEFEA;
    --radius-lg: 20px;         /* زوايا أكثر انحناءً لجمالية عصرية */
    --radius-md: 14px;
    --radius-sm: 9px;
    --shadow-sm: 0 4px 12px rgba(28, 51, 41, 0.03);
    --shadow-md: 0 12px 35px rgba(28, 51, 41, 0.07);
    --transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

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
    letter-spacing: 0.5px;
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
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.sidebar-user-info .name {
    color: var(--white);
    font-weight: 600;
    font-size: 0.98rem;
}

.sidebar-user-info .role {
    color: var(--gold);
    font-size: 0.8rem;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.sidebar-nav {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
}

.nav-section {
    padding: 12px 24px 6px;
    font-size: 0.75rem;
    color: rgba(255,255,255,0.35);
    font-weight: 700;
    letter-spacing: 0.8px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 24px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    transition: var(--transition);
    font-size: 0.95rem;
    position: relative;
}

.nav-link svg {
    width: 20px;
    height: 20px;
    opacity: 0.7;
    transition: var(--transition);
}

.nav-link:hover, .nav-link.active {
    background: rgba(255,255,255,0.05);
    color: var(--white);
}

.nav-link:hover svg, .nav-link.active svg {
    opacity: 1;
    transform: scale(1.05);
}

.nav-link.active {
    background: linear-gradient(90deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0) 100%);
    color: var(--gold);
}

.nav-link.active::before {
    content: '';
    position: absolute;
    right: 0;
    top: 8px;
    bottom: 8px;
    width: 4px;
    background: var(--gold);
    border-radius: 4px 0 0 4px;
    box-shadow: -2px 0 10px var(--gold);
}

.badge-count {
    margin-right: auto;
    background: var(--gold);
    color: var(--primary);
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(212,175,55,0.3);
}

.sidebar-footer {
    padding: 20px 24px;
    border-top: 1px solid rgba(255,255,255,0.06);
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    font-size: 0.9rem;
    transition: var(--transition);
}

.logout-btn:hover {
    color: #ff6b6b;
}

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
    -webkit-backdrop-filter: blur(12px);
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

.topbar-title {
    font-family: 'Amiri', serif;
    font-size: 1.6rem;
    color: var(--primary);
    font-weight: 700;
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.notif-btn {
    position: relative;
    background: var(--white);
    border: 1px solid rgba(44, 74, 62, 0.15);
    border-radius: 50%;
    width: 42px;
    height: 42px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-light);
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
}

.notif-btn svg { width: 20px; height: 20px; }
.notif-btn:hover { background: var(--primary-soft); transform: translateY(-1px); }
.notif-dot { position: absolute; top: 2px; right: 2px; width: 9px; height: 9px; background: #e74c3c; border-radius: 50%; border: 2px solid #fff; }

.greeting-chip {
    background: var(--primary-soft);
    border: 1px solid rgba(44, 74, 62, 0.15);
    border-radius: 30px;
    padding: 8px 16px;
    font-size: 0.88rem;
    color: var(--primary-light);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ── Content Container ── */
.content {
    padding: 32px;
    flex: 1;
    animation: fadeIn 0.5s ease-out forwards;
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
}

.page-header {
    text-align: center;
    margin-bottom: 36px;
}

.page-header h1 {
    font-family: 'Amiri', serif;
    font-size: 2.4rem;
    color: var(--primary);
    font-weight: 700;
}

.page-header p {
    color: var(--gray);
    margin-top: 8px;
    font-size: 1rem;
}

/* ── Stats Grid (كروت الإحصائيات المطورة بالبيج والزيتي) ── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--beige-card);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(44, 74, 62, 0.08);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.stat-card::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 5px;
    bottom: 0;
    right: 0;
    background: var(--primary-light);
}

.stat-card:hover {
    transform: translateY(-5px);
    background: var(--beige-hover);
    border-color: var(--primary-light);
    box-shadow: 0 15px 35px rgba(28, 51, 41, 0.12);
}

.stat-card.green::after { background: var(--primary-light); }
.stat-card.gold::after { background: var(--gold-dark); }
.stat-card.blue::after { background: #4A6B82; } /* أزرق مطفأ يتناسق مع الزيتي */
.stat-card.teal::after { background: #3B6E60; } /* تيل زيتي */

.stat-icon-wrapper {
    width: 56px;
    height: 56px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
}

.stat-card.green .stat-icon-wrapper { background: rgba(44, 74, 62, 0.1); color: var(--primary-light); }
.stat-card.gold .stat-icon-wrapper { background: var(--gold-soft); color: var(--gold-dark); }
.stat-card.blue .stat-icon-wrapper { background: rgba(74, 107, 130, 0.1); color: #4A6B82; }
.stat-card.teal .stat-icon-wrapper { background: rgba(59, 110, 96, 0.1); color: #3B6E60; }

.stat-icon-wrapper svg { width: 28px; height: 28px; }

.stat-value {
    font-size: 2.4rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.1;
}

.stat-label {
    color: var(--gray);
    font-size: 0.95rem;
    margin-top: 6px;
    font-weight: 600;
}

/* ── Layout Grids ── */
.grid-2 {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.grid-equal {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

/* ── Card Component (البطاقات الرئيسية الجذابة) ── */
.card {
    background: var(--beige-card);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    border: 1px solid rgba(44, 74, 62, 0.08);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
}

.card:hover {
    box-shadow: 0 14px 40px rgba(28, 51, 41, 0.1);
    border-color: rgba(44, 74, 62, 0.2);
}

.card-header {
    padding: 22px 24px;
    border-bottom: 1px solid rgba(44, 74, 62, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(90deg, rgba(44, 74, 62, 0.05) 0%, rgba(255,255,255,0) 100%);
}

.card-title {
    font-family: 'Amiri', serif;
    font-size: 1.4rem;
    color: var(--primary);
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-title svg { width: 24px; height: 24px; color: var(--primary-light); }

.card-link {
    font-size: 0.88rem;
    color: var(--primary-light);
    text-decoration: none;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: var(--transition);
}

.card-link:hover { color: var(--gold-dark); transform: translateX(-2px); }
.card-body { padding: 24px; flex: 1; }

/* ── Modern Centered Tables ── */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    padding: 16px 20px;
    background: rgba(44, 74, 62, 0.06);
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--primary);
    text-align: center;
    border-bottom: 2px solid rgba(44, 74, 62, 0.1);
}

.data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(44, 74, 62, 0.05);
    font-size: 0.95rem;
    color: var(--gray-dark);
    text-align: center;
    vertical-align: middle;
}

.data-table tr:last-child td { border-bottom: none; }
.data-table tr { transition: var(--transition); }
.data-table tr:hover td { background: rgba(44, 74, 62, 0.03); }

/* ── Badges ── */
.grade-badge {
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 0.82rem;
    font-weight: 700;
    display: inline-block;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

/* ── Top Students List ── */
.top-students-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.top-student {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    border-radius: var(--radius-md);
    border: 1px solid rgba(44, 74, 62, 0.08);
    background: rgba(255, 255, 255, 0.4);
    transition: var(--transition);
}

.top-student:hover {
    border-color: var(--primary-light);
    background: var(--white);
    transform: scale(1.02);
    box-shadow: var(--shadow-sm);
}

.rank {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
    box-shadow: var(--shadow-sm);
}

.rank-1 { background: linear-gradient(135deg, #FFF3B0 0%, #CAA100 100%); color: #4A3B00; }
.rank-2 { background: linear-gradient(135deg, #E2E8F0 0%, #94A3B8 100%); color: #334155; }
.rank-3 { background: linear-gradient(135deg, #FFEDD5 0%, #D97706 100%); color: #78350F; }
.rank-other { background: var(--gray-light); color: var(--gray); }

.top-student .avatar-circle { box-shadow: none; border: 2px solid var(--white); }
.top-student-info { flex: 1; }
.top-student-info .ts-name { font-weight: 700; font-size: 0.98rem; color: var(--gray-dark); }
.top-student-info .ts-pts { font-size: 0.85rem; color: var(--gray); margin-top: 2px; font-weight: 500; }
.level-badge { background: var(--gold-soft); border: 1px solid rgba(212, 175, 55, 0.3); color: var(--gold-dark); border-radius: 20px; padding: 4px 10px; font-size: 0.78rem; font-weight: 600; }

/* ── Halaqat Grid ── */
.halqa-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    padding: 24px;
}

.halqa-mini {
    border: 1px solid rgba(44, 74, 62, 0.08);
    border-radius: var(--radius-md);
    padding: 20px;
    transition: var(--transition);
    cursor: pointer;
    background: rgba(255, 255, 255, 0.5);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    text-align: center;
}

.halqa-mini:hover {
    border-color: var(--primary-light);
    box-shadow: var(--shadow-md);
    transform: translateY(-4px);
    background: var(--white);
}

.halqa-mini .h-name {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.15rem;
    margin-bottom: 12px;
}

.halqa-mini .h-meta {
    font-size: 0.9rem;
    color: var(--gray);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.halqa-mini .h-meta span {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.halqa-mini .h-meta svg { width: 16px; height: 16px; color: var(--primary-light); }
.halqa-mini .h-count { margin-top: 16px; }

.progress-container {
    background: var(--gray-light);
    border-radius: 10px;
    height: 7px;
    width: 100%;
    margin-bottom: 8px;
    overflow: hidden;
}

.progress-bar { background: linear-gradient(90deg, var(--primary-light), var(--primary)); height: 100%; border-radius: 10px; }
.progress-text { font-size: 0.8rem; color: var(--gray-dark); font-weight: 600; display: flex; justify-content: space-between; }

/* ── Quick Actions Grid ── */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    padding: 24px;
}

.qa-btn {
    background: rgba(255, 255, 255, 0.5);
    border: 1px solid rgba(44, 74, 62, 0.08);
    border-radius: var(--radius-md);
    padding: 22px 14px;
    text-align: center;
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    box-shadow: var(--shadow-sm);
}

.qa-btn .qa-icon {
    color: var(--primary-light);
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.qa-btn .qa-icon svg { width: 30px; height: 30px; }
.qa-btn .qa-label { font-size: 0.92rem; color: var(--gray-dark); font-weight: 700; }

.qa-btn:hover {
    border-color: var(--primary-light);
    background: var(--white);
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.qa-btn:hover .qa-icon { color: var(--gold-dark); transform: scale(1.15); }
.qa-btn:hover .qa-label { color: var(--primary); }

/* ── Responsive & Mobile ── */
.hamburger {
    display: none;
    background: var(--white);
    border: 1px solid rgba(44, 74, 62, 0.15);
    border-radius: var(--radius-sm);
    padding: 8px 12px;
    cursor: pointer;
    color: var(--primary);
}

@media (max-width: 1200px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .grid-2, .grid-equal { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    .sidebar { transform: translateX(280px); }
    .sidebar.open { transform: translateX(0); }
    .main { margin-right: 0; width: 100%; }
    .hamburger { display: block; }
    .topbar { padding: 16px; }
    .content { padding: 20px; }
}

@media (max-width: 600px) {
    .stats-grid { grid-template-columns: 1fr; }
    .halqa-grid, .quick-actions { grid-template-columns: 1fr; }
    .data-table th, .data-table td { padding: 10px 12px; font-size: 0.82rem; }
    .topbar-title { font-size: 1.2rem; }
}
</style>
</head>
<body>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-text">رتِّل</div>
        <div class="logo-sub">لوحة المعلم</div>
    </div>
    <div class="sidebar-user">
        <div class="avatar-circle"><?= mb_substr($user['name'] ?? 'م',0,1) ?></div>
        <div class="sidebar-user-info">
            <div class="name"><?= htmlspecialchars($user['name'] ?? 'المعلم') ?></div>
            <div class="role">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                معلم متميز
            </div>
        </div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">القائمة الرئيسية</div>
        <a class="nav-link active" href="dashboard.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            لوحة التحكم
        </a>
        <a class="nav-link" href="halaqat.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            حلقاتي
        </a>
        <a class="nav-link" href="students.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            الطلاب
        </a>
        
        <div class="nav-section">إدارة التعلم</div>
        <a class="nav-link" href="recitation_log.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            تسجيل التسميع
        </a>
        <a class="nav-link" href="attendance.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            الحضور والغياب
        </a>
        <a class="nav-link" href="quran.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            القرآن الكريم
        </a>
        
        <div class="nav-section">الحساب</div>
        <a class="nav-link" href="messages.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            الرسائل
        </a>
        <a class="nav-link" href="settings.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
            الإعدادات
        </a>
        <a class="nav-link" href="#" id="notifLink" data-notif-trigger onclick="return openNotif(event)">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            الإشعارات
            <?php if($unread>0): ?><span class="badge-count"><?= $unread ?></span><?php endif ?>
        </a>
    </div>
    <div class="sidebar-footer">
        <a class="logout-btn" href="../logout.php">
            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 01-3-3h4a3 3 0 013 3v1"/></svg>
            تسجيل الخروج
        </a>
    </div>
</nav>

<div class="main">
    <div class="topbar">
        <button class="hamburger" onclick="toggleSidebar()" aria-label="فتح القائمة">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="topbar-title">لوحة التحكم</div>
        <div class="topbar-actions">
            <div class="greeting-chip">مرحباً، <?= htmlspecialchars(explode(' ',$user['name'] ?? ' ')[0]) ?></div>
            <button class="notif-btn" title="الإشعارات">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <?php if($unread>0): ?><span class="notif-dot"></span><?php endif ?>
            </button>
        </div>
    </div>

    <div class="content">
        <div class="page-header">
            <h1>أهلاً بك فضيلة المعلم</h1>
            <p>نظرة عامة على نشاطك وحلقاتك اليوم — <?= date('l، d F Y') ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-icon-wrapper">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div class="stat-value"><?= $totalHalaqat ?></div>
                <div class="stat-label">حلقاتي النشطة</div>
            </div>
            <div class="stat-card gold">
                <div class="stat-icon-wrapper">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <div class="stat-value"><?= $totalStudents ?></div>
                <div class="stat-label">إجمالي الطلاب</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon-wrapper">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <div class="stat-value"><?= $weekRecitations ?></div>
                <div class="stat-label">تسميعات هذا الأسبوع</div>
            </div>
            <div class="stat-card teal">
                <div class="stat-icon-wrapper">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-value"><?= $todayPresent ?></div>
                <div class="stat-label">حاضرون اليوم</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-5 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        آخر التسميعات التسجيلية
                    </div>
                    <a class="card-link" href="recitation_log.php">عرض الكل 
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m7 7l-7-7 7-7"/></svg>
                    </a>
                </div>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>السورة والآيات</th>
                                <th>التقييم</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($latestRecitations)): ?>
                            <tr><td colspan="4" style="text-align:center;color:var(--gray);padding:32px">لا توجد تسميعات مسجلة بعد</td></tr>
                            <?php else: foreach($latestRecitations as $r): 
                                $colorData = explode('#', $gradeColors[$r['grade']] ?? 'rgba(153,153,153,0.2)#707A75');
                                $bg = $colorData[0];
                                $fg = '#' . $colorData[1];
                            ?>
                            <tr>
                                <td><span style="font-weight: 700; color: var(--gray-dark);"><?= htmlspecialchars($r['student_name']) ?></span></td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                                        <svg style="width:16px;height:16px;color:var(--primary-light)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        <?= htmlspecialchars($r['surah_name']) ?> (<?= $r['ayah_from'] ?>-<?= $r['ayah_to'] ?>)
                                    </span>
                                </td>
                                <td><span class="grade-badge" style="background:<?= $bg ?>; color:<?= $fg ?>"><?= $r['grade'] ?></span></td>
                                <td style="color:var(--gray); font-size:.85rem; font-weight:500;"><?= date('d/m Y',strtotime($r['recorded_at'])) ?></td>
                            </tr>
                            <?php endforeach; endif ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        الطلاب الأكثر تميزاً
                    </div>
                    <a class="card-link" href="students.php">الكل
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m7 7l-7-7 7-7"/></svg>
                    </a>
                </div>
                <div class="card-body">
                    <div class="top-students-list">
                        <?php if(empty($topStudents)): ?>
                            <p style="color:var(--gray);text-align:center;padding:20px">لا توجد بيانات متاحة</p>
                        <?php else: foreach($topStudents as $i=>$s): ?>
                        <div class="top-student">
                            <div class="rank rank-<?= $i<3 ? ($i+1) : 'other' ?>"><?= $i+1 ?></div>
                            <div class="avatar-circle" style="width:36px;height:36px;font-size:.9rem"><?= mb_substr($s['name'] ?? 'ط',0,1) ?></div>
                            <div class="top-student-info">
                                <div class="ts-name"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="ts-pts"><?= number_format($s['points']) ?> نقطة</div>
                            </div>
                            <div class="level-badge">مستوى <?= getLevelForPoints((int)$s['total_earned']) ?></div>
                        </div>
                        <?php endforeach; endif ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-equal">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                        حلقاتي القرآنية
                    </div>
                    <a class="card-link" href="halaqat.php">إدارة الحلقات
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5m7 7l-7-7 7-7"/></svg>
                    </a>
                </div>
                <div class="halqa-grid">
                    <?php if(empty($myHalaqat)): ?>
                        <p style="color:var(--gray);padding:10px;grid-column:1/-1;text-align:center;">لا توجد حلقات مسجلة باسمك حالياً.</p>
                    <?php else: foreach($myHalaqat as $h): 
                        $pct = $h['max_students'] > 0 ? ($h['student_count'] / $h['max_students']) * 100 : 0;
                    ?>
                    <div class="halqa-mini" onclick="location.href='halaqat.php?id=<?= $h['id'] ?>'">
                        <div>
                            <div class="h-name"><?= htmlspecialchars($h['name']) ?></div>
                            <div class="h-meta">
                                <span>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <?= date('h:i A',strtotime($h['schedule_time'])) ?>
                                </span>
                                <span>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <?= htmlspecialchars($h['location']) ?>
                                </span>
                                <span>
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <?= htmlspecialchars($h['schedule_days']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="h-count">
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="progress-text">
                                <span>الاستيعاب الحالي</span>
                                <strong><?= $h['student_count'] ?> / <?= $h['max_students'] ?> طالب</strong>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        إجراءات وإختصارات سريعة
                    </div>
                </div>
                <div class="quick-actions">
                    <a class="qa-btn" href="recitation_log.php?action=new">
                        <div class="qa-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </div>
                        <div class="qa-label">تسجيل تسميع</div>
                    </a>
                    <a class="qa-btn" href="attendance.php?action=today">
                        <div class="qa-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div class="qa-label">تسجيل حضور</div>
                    </a>
                    <a class="qa-btn" href="students.php">
                        <div class="qa-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <div class="qa-label">عرض الطلاب</div>
                    </a>
                    <a class="qa-btn" href="quran.php">
                        <div class="qa-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <div class="qa-label">القرآن الكريم</div>
                    </a>
                    <a class="qa-btn" href="students.php?action=badge">
                        <div class="qa-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm0 0h4m-4 0H8m12 3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="qa-label">منح شارة</div>
                    </a>
                    <a class="qa-btn" href="halaqat.php">
                        <div class="qa-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                        </div>
                        <div class="qa-label">إدارة الحلقات</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
}
</script>
<script src="/rattil/assets/js/notifications.js?v=20260518"></script>
</body>
</html>
