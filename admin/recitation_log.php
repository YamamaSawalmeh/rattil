<?php
require_once '../config/db.php';
requireRole('admin');

// استدعاء ملف التصميم الموحد والـ Shared الخاص بمنصة رتّل
require_once 'rattil_shared.php';

$user   = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$msg    = '';
$error  = '';

// قائمة السور الكريمة لإدخال التسميع الجديد يدويًا من قبل الأدمن
$suwarList = [
    [1,'الفاتحة',7],[2,'البقرة',286],[3,'آل عمران',200],[4,'النساء',176],
    [5,'المائدة',120],[6,'الأنعام',165],[36,'يس',83],[55,'الرحمن',78],
    [56,'الواقعة',96],[67,'الملك',30],[78,'النبأ',40],[87,'الأعلى',19],
    [89,'الفجر',30],[93,'الضحى',11],[94,'الشرح',8],[95,'التين',8],
    [96,'العلق',19],[97,'القدر',5],[98,'البينة',8],[99,'الزلزلة',8],
    [100,'العاديات',11],[101,'القارعة',11],[102,'التكاثر',8],[103,'العصر',3],
    [104,'الهمزة',9],[105,'الفيل',5],[106,'قريش',4],[107,'الماعون',7],
    [108,'الكوثر',3],[109,'الكافرون',6],[110,'النصر',3],[112,'الإخلاص',4],
    [113,'الفلق',5],[114,'الناس',6]
];

/* ─── 🧠 الباك إند والمنطق البرمجي (دون أي تغيير أو مساس) ─── */

// 1. عملية حذف سجل تسميع
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        $stmtLog = $pdo->prepare("SELECT student_id, points_awarded FROM recitation_log WHERE id = ?");
        $stmtLog->execute([$del_id]);
        $logData = $stmtLog->fetch();
        
        if ($logData) {
            if ($logData['points_awarded'] > 0) {
                $pdo->prepare("UPDATE student_points SET points = GREATEST(0, points - ?), total_earned = GREATEST(0, total_earned - ?) WHERE student_id = ?")
                    ->execute([$logData['points_awarded'], $logData['points_awarded'], $logData['student_id']]);
            }
            
            $pdo->prepare("DELETE FROM recitation_log WHERE id = ?")->execute([$del_id]);
            $pdo->commit();
            $msg = 'تم حذف سجل التسميع وإعادة تسوية نقاط الطالب بنجاح ✅';
        } else {
            $pdo->rollBack();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'حدث خطأ أثناء محاولة الحذف: ' . $e->getMessage();
    }
}

// 2. عملية إضافة سجل تسميع جديد من قبل الأدمن
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_log') {
    try {
        $student_id     = (int)($_POST['student_id'] ?? 0);
        $surah_num      = (int)($_POST['surah_num'] ?? 0);
        $surah_name     = trim($_POST['surah_name'] ?? '');
        $ayah_from      = (int)($_POST['ayah_from'] ?? 0);
        $ayah_to        = (int)($_POST['ayah_to'] ?? 0);
        $grade          = trim($_POST['grade'] ?? '');
        $points_awarded = (int)($_POST['points_awarded'] ?? 0);
        $notes          = trim($_POST['notes'] ?? '');

        if (!$student_id || !$surah_num || !$surah_name || !$ayah_from || !$ayah_to || !$grade) {
            throw new Exception('يرجى ملء جميع الحقول الأساسية لإتمام عملية تسجيل التسميع.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO recitation_log (student_id, teacher_id, surah_number, surah_name, ayah_from, ayah_to, grade, points_awarded, notes, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$student_id, $user['id'], $surah_num, $surah_name, $ayah_from, $ayah_to, $grade, $points_awarded, $notes]);

        if ($points_awarded > 0) {
            $pdo->prepare("UPDATE student_points SET points = points + ?, total_earned = total_earned + ? WHERE student_id = ?")
                ->execute([$points_awarded, $points_awarded, $student_id]);
        }

        sendNotification($student_id, 'recitation', 'تم تسجيل تسميع جديد! 📖', "قام المشرف بتوثيق تسميعك لسورة $surah_name بتقدير ($grade).", $pdo);

        $pdo->commit();
        $msg = 'تم تسجيل السجل بنجاح ومنح النقاط المقررة للطالب بنجاح 🎉';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

/* ─── 🔍 استعلام البيانات والفلترة (تم الاحتفاظ بالمنطق والترابط السريع) ─── */
$search_q = trim($_GET['q'] ?? '');
$filter_g = trim($_GET['grade'] ?? '');

$where_clauses = ["1=1"];
$params = [];

if ($search_q) {
    $where_clauses[] = "(s.name LIKE ? OR l.surah_name LIKE ? OR t.name LIKE ?)";
    $params[] = "%$search_q%"; $params[] = "%$search_q%"; $params[] = "%$search_q%";
}
if ($filter_g) {
    $where_clauses[] = "l.grade = ?";
    $params[] = $filter_g;
}

$where_str = implode(" AND ", $where_clauses);

$query = "
    SELECT l.*, s.name AS student_name, t.name AS teacher_name, s.avatar AS student_avatar,
           sp.points AS total_points, sp.level AS student_level,
           (SELECT COUNT(*) FROM recitation_log WHERE student_id = s.id) as total_recit_count
    FROM recitation_log l
    JOIN users s ON s.id = l.student_id
    LEFT JOIN users t ON t.id = l.teacher_id
    LEFT JOIN student_points sp ON sp.student_id = s.id
    WHERE $where_str
    ORDER BY l.recorded_at DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$students_list = $pdo->query("SELECT id, name FROM users WHERE role='student' ORDER BY name ASC")->fetchAll();
?>

<?php rattil_page_start('سجل ومتابعة التسميع'); ?>

<style>
    :root {
        --card-bg: #fdfbf7; /* لون بيج ناعم ودافئ للبطاقات */
        --card-border: #f0e6d2; /* إطار بيج متناسق */
        --card-radius: 16px;
        --shadow-sm: 0 4px 6px -1px rgba(15, 45, 31, 0.03), 0 2px 4px -1px rgba(15, 45, 31, 0.02);
        --shadow-md: 0 10px 20px -3px rgba(15, 45, 31, 0.08), 0 4px 8px -2px rgba(15, 45, 31, 0.04);
        --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --text-olive: #0f2d1f;
    }

    /* هيدر الصفحة المحسن */
    .ph-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    /* شبكة البطاقات المتجاوبة (Cards Grid) */
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    /* تصميم بطاقة الطالب العصري باللون البيج والزيتي */
    .student-card {
        background: var(--card-bg);
        border-radius: var(--card-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--card-border);
        transition: var(--transition-smooth);
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    .student-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-md);
        border-color: var(--olive-400);
        background: #fbf8f0; /* بيج أعمق قليلاً عند الهوفر */
    }

    /* رأس البطاقة والـ Avatar */
    .card-header-main {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .avatar-wrapper {
        position: relative;
    }

    .student-avatar-ui {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        object-fit: cover;
        background: var(--olive-900); /* خلفية زيتية للأفاتار الافتراضي */
        color: #fdfbf7; /* نص بيج */
        font-weight: 700;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--card-border);
    }

    .status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        position: absolute;
        bottom: 2px;
        left: 2px;
        border: 2px solid var(--card-bg);
    }

    .card-title-area h3 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--olive-950);
    }

    /* بادجات وتفاصيل البطاقة */
    .card-meta-info {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .meta-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.65rem;
        border-radius: 20px;
        font-size: 0.78rem;
        font-weight: 500;
    }

    .meta-pill.teacher { 
        background: rgba(15, 45, 31, 0.06); 
        color: var(--olive-800); 
        border: 1px solid rgba(15, 45, 31, 0.1);
    }
    .meta-pill.surah { 
        background: rgba(212, 163, 89, 0.12); 
        color: #8a5e13; 
        font-weight: 600;
        border: 1px solid rgba(212, 163, 89, 0.2);
    }

    /* الإحصائيات السريعة بالبطاقة باللون الزيتي المتناسق */
    .mini-stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        background: rgba(15, 45, 31, 0.03); /* زيتي شفاف جداً */
        padding: 0.75rem;
        border-radius: 12px;
        text-align: center;
        margin-bottom: 1.25rem;
        border: 1px solid rgba(15, 45, 31, 0.05);
    }

    .stat-box-item .stat-val {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.2rem;
        font-size: 0.95rem;
        font-weight: 800;
        color: var(--olive-900);
    }

    .stat-box-item .stat-lbl {
        font-size: 0.72rem;
        color: var(--muted);
        display: block;
        margin-top: 0.1rem;
    }

    .card-svg-icon {
        width: 14px;
        height: 14px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
    }

    /* الـ Side Profile Panel (اللوحة المنبثقة من الجانب) */
    .profile-side-panel {
        position: fixed;
        top: 0;
        right: -450px;
        width: 420px;
        height: 100vh;
        background: #ffffff;
        box-shadow: -10px 0 30px rgba(15, 45, 31, 0.15);
        z-index: 1050;
        transition: cubic-bezier(0.4, 0, 0.2, 1) 0.4s;
        display: flex;
        flex-direction: column;
        border-left: 1px solid rgba(0,0,0,0.05);
    }

    .profile-side-panel.open {
        right: 0;
    }

    .panel-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(9, 26, 16, 0.45);
        backdrop-filter: blur(4px);
        z-index: 1040;
        display: none;
    }

    .panel-header {
        padding: 1.5rem;
        background: var(--olive-900);
        color: #fdfbf7;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .panel-body-content {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
        background: #fcfbfa;
    }

    /* شريط التقدم */
    .progress-container {
        background: #e9e5db;
        border-radius: 8px;
        height: 8px;
        width: 100%;
        overflow: hidden;
        margin-top: 0.5rem;
    }
    .progress-bar-fill {
        background: var(--olive-600);
        height: 100%;
        width: 0%;
        transition: width 0.5s ease;
    }

    /* الزر العائم الثابت لإضافة تسميع فوراً */
    .floating-add-btn {
        position: fixed;
        bottom: 2rem;
        left: 2rem;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--olive-900);
        color: #fdfbf7;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(15, 45, 31, 0.35);
        cursor: pointer;
        transition: var(--transition-smooth);
        z-index: 999;
        border: 2px solid var(--card-border);
    }
    .floating-add-btn:hover {
        transform: scale(1.1) rotate(90deg);
        background: var(--olive-700);
    }
</style>

<?php rattil_sidebar($user, 'recitation_log', $unread); ?>

<div class="pw">
    <div class="ph-wrapper">
        <div class="ph-left">
            <h1 style="display: flex; align-items: center; gap: 0.6rem; margin: 0;">
                <svg style="width:28px; height:28px; stroke: var(--olive-900);" viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                داشبورد متابعة التسميع اليومي
            </h1>
            <p style="color: var(--muted); margin: 0.2rem 0 0 0;">واجهة تفاعلية ذكية مخصصة لمراقبة وإدارة مستويات حفظ الطلاب داخل الحلقات.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addLogModal')" style="display: flex; align-items: center; gap: 0.4rem;">
            <svg style="width:16px; height:16px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            توثيق تسميع جديد
        </button>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-ok"><div class="alert-icon">✓</div><div><?= htmlspecialchars($msg) ?></div></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-err"><div class="alert-icon">✕</div><div><?= htmlspecialchars($error) ?></div></div>
    <?php endif; ?>

    <div class="card" style="border-radius: var(--card-radius); padding: 1.25rem; margin-bottom: 1.5rem; background: var(--card-bg); border-color: var(--card-border);">
        <form method="GET" class="filter-bar" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 250px;" class="fg">
                <label class="fl" style="display: flex; align-items: center; gap: 0.3rem;">
                    <svg class="card-svg-icon" style="stroke: var(--olive-700);" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    ابحث في البطاقات
                </label>
                <input type="text" name="q" id="liveSearchInput" value="<?= htmlspecialchars($search_q) ?>" class="fc" placeholder="اكتب اسم الطالب، السورة الحالية، أو الشيخ المسؤول...">
            </div>
            <div style="flex: 1; min-width: 180px;" class="fg">
                <label class="fl" style="display: flex; align-items: center; gap: 0.3rem;">
                    <svg class="card-svg-icon" style="stroke: var(--olive-700);" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    التقدير الأخير
                </label>
                <select name="grade" id="liveGradeFilter" class="fc">
                    <option value="">كافة الحالات والتقديرات</option>
                    <option value="ممتاز" <?= $filter_g === 'ممتاز' ? 'selected' : '' ?>>ممتاز</option>
                    <option value="جيد جداً" <?= $filter_g === 'جيد جداً' ? 'selected' : '' ?>>جيد جداً</option>
                    <option value="جيد" <?= $filter_g === 'جيد' ? 'selected' : '' ?>>جيد</option>
                    <option value="مقبول" <?= $filter_g === 'مقبول' ? 'selected' : '' ?>>مقبول</option>
                    <option value="إعادة" <?= $filter_g === 'إعادة' ? 'selected' : '' ?>>إعادة</option>
                </select>
            </div>
            <div style="margin-bottom: 0.9rem; display:flex; gap:0.5rem;">
                <button type="submit" class="btn btn-primary" id="searchBtn">تصفية الآن</button>
                <?php if($search_q || $filter_g): ?>
                    <a href="?" class="btn btn-secondary">إعادة تعيين</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="cards-grid" id="studentsContainer">
        <?php if(empty($logs)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 4rem; color: var(--muted); background: var(--card-bg);">
                لا توجد بطاقات تسميع تطابق خيارات البحث الحالية.
            </div>
        <?php else: ?>
            <?php foreach($logs as $l): 
                // تحديد لون الحالة التفاعلية بناءً على آخر تقدير
                $g = $l['grade'];
                $statusColor = '#d4a359'; // افتراضي جيد جداً (ذهبي رتل)
                if($g == 'ممتاز') $statusColor = '#2d7a52'; // أخضر زيتوني رتل
                if($g == 'إعادة') $statusColor = '#e53e3e'; // أحمر تنبيه
            ?>
                <div class="student-card class-card-item" 
                     data-name="<?= htmlspecialchars($l['student_name']) ?>"
                     data-grade="<?= htmlspecialchars($l['grade']) ?>"
                     data-id="<?= $l['student_id'] ?>"
                     data-teacher="<?= htmlspecialchars($l['teacher_name'] ?: 'المشرف العام') ?>"
                     data-surah="سورة <?= htmlspecialchars($l['surah_name']) ?>"
                     data-range="من <?= (int)$l['ayah_from'] ?> إلى <?= (int)$l['ayah_to'] ?>"
                     data-points="<?= (int)$l['total_points'] ?>"
                     data-level="<?= (int)($l['student_level'] ?? 1) ?>"
                     data-count="<?= (int)$l['total_recit_count'] ?>"
                     data-notes="<?= htmlspecialchars($l['notes'] ?: 'لا توجد ملاحظات مسجلة على هذا التسميع.') ?>"
                     data-date="<?= date('Y-m-d H:i', strtotime($l['recorded_at'])) ?>"
                     data-logid="<?= $l['id'] ?>">
                    
                    <div class="card-header-main">
                        <div class="avatar-wrapper">
                            <?php if(!empty($l['student_avatar'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($l['student_avatar']) ?>" class="student-avatar-ui" alt="Avatar">
                            <?php else: ?>
                                <div class="student-avatar-ui"><?= mb_substr($l['student_name'], 0, 1, 'UTF-8') ?></div>
                            <?php endif; ?>
                            <div class="status-indicator" style="background: <?= $statusColor ?>;"></div>
                        </div>
                        <div class="card-title-area">
                            <h3><?= htmlspecialchars($l['student_name']) ?></h3>
                            <span style="font-size: 0.75rem; color: var(--muted); display: flex; align-items: center; gap: 0.2rem; margin-top: 0.15rem;">
                                <svg class="card-svg-icon" style="width:12px; height:12px;" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                <?= date('d/m H:i', strtotime($l['recorded_at'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-meta-info">
                        <span class="meta-pill teacher">
                            <svg class="card-svg-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            الشيخ: <?= htmlspecialchars($l['teacher_name'] ?: 'المشرف العام') ?>
                        </span>
                        <span class="meta-pill surah">
                            <svg class="card-svg-icon" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            سورة <?= htmlspecialchars($l['surah_name']) ?>
                        </span>
                    </div>

                    <div class="mini-stats-row">
                        <div class="stat-box-item">
                            <span class="stat-val">
                                <svg class="card-svg-icon" viewBox="0 0 24 24"><polyline points="23 4 12 15 7 10 1 16"></polyline><polyline points="17 4 23 4 23 10"></polyline></svg>
                                <?= (int)$l['total_recit_count'] ?>
                            </span>
                            <span class="stat-lbl">التسميعات</span>
                        </div>
                        <div class="stat-box-item">
                            <span class="stat-val">
                                <svg class="card-svg-icon" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                <?= number_format((int)$l['total_points']) ?>
                            </span>
                            <span class="stat-lbl">النقاط</span>
                        </div>
                        <div class="stat-box-item">
                            <span class="stat-val">
                                <svg class="card-svg-icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                <?= (int)($l['student_level'] ?? 1) ?>
                            </span>
                            <span class="stat-lbl">المستوى</span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto;">
                        <span class="badge" style="background: <?= $statusColor ?>20; color: <?= $statusColor ?>; font-weight: 700; border: 1px solid <?= $statusColor ?>40;">
                            <?= htmlspecialchars($g) ?>
                        </span>
                        <button type="button" class="btn btn-secondary btn-xs" style="border-radius: 8px; padding: 0.4rem 0.8rem; display: flex; align-items: center; gap: 0.3rem;" onclick="openStudentProfilePanel(this.closest('.student-card'))">
                            <svg class="card-svg-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            عرض التفاصيل
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="panel-backdrop" id="panelBackdrop" onclick="closeStudentProfilePanel()"></div>
<div class="profile-side-panel" id="profileSidePanel">
    <div class="panel-header">
        <h3 id="panelStudentName" style="margin: 0; font-size: 1.15rem; font-weight: 700;">اسم الطالب</h3>
        <button onclick="closeStudentProfilePanel()" style="background:none; border:none; color:white; font-size:1.5rem; cursor:pointer; line-height:1;">&times;</button>
    </div>
    <div class="panel-body-content">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div id="panelAvatarCircle" style="width: 80px; height: 80px; border-radius: 50%; background: var(--olive-900); color: #fdfbf7; font-size: 2rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem; border: 3px solid var(--card-border);"></div>
            <div style="font-size: 0.88rem; color: var(--muted);" id="panelTeacherName">المعلم المسؤول: </div>
        </div>

        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: var(--olive-950); display: flex; align-items: center; gap: 0.3rem;">
            <svg class="card-svg-icon" style="stroke: var(--olive-700);" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            مستوى تقدم الطالب الحالي
        </h4>
        <div class="progress-container">
            <div class="progress-bar-fill" id="panelProgressBar"></div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--muted); margin-top: 0.35rem; margin-bottom: 1.5rem;">
            <span id="panelLevelText">المستوى 1</span>
            <span>المستوى التالي 100%</span>
        </div>

        <div class="card" style="background: var(--card-bg); border-color: var(--card-border); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
            <h4 style="margin: 0 0 0.75rem 0; font-size: 0.9rem; color: var(--olive-900); display: flex; align-items: center; gap: 0.3rem;">
                <svg class="card-svg-icon" style="stroke: var(--olive-700);" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                تفاصيل آخر عملية تسميع ومراجعة
            </h4>
            <div style="font-size: 0.85rem; line-height: 1.6; color: var(--text);">
                <p style="margin: 0.25rem 0;"><strong>الموضع:</strong> <span id="panelLastSurah"></span></p>
                <p style="margin: 0.25rem 0;"><strong>الآيات المحفوظة:</strong> <span id="panelLastRange"></span></p>
                <p style="margin: 0.25rem 0;"><strong>التقييم المسجل:</strong> <span id="panelLastGrade" class="badge"></span></p>
                <p style="margin: 0.25rem 0;"><strong>التاريخ والوقت:</strong> <span id="panelLastDate"></span></p>
                <p style="margin: 0.5rem 0 0 0; padding: 0.6rem; background: #ffffff; border-radius: 6px; border: 1px dashed var(--card-border);">
                    <strong>ملاحظات الشيخ:</strong> <br><span id="panelLastNotes" style="color:var(--muted); font-size:0.8rem;"></span>
                </p>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; margin-top: auto;">
            <a id="panelProfileBtn" href="#" class="btn btn-primary" style="flex: 1; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;">
                <svg class="card-svg-icon" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                زيارة الملف الكامل
            </a>
            <a id="panelDeleteBtn" href="#" class="btn btn-danger" style="padding: 0 1rem; display: flex; align-items: center; justify-content: center;" onclick="return confirm('هل أنت متأكد من حذف هذا السجل الإداري؟')" title="حذف السجل من المنصة">
                <svg class="card-svg-icon" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </a>
        </div>
    </div>
</div>

<button class="floating-add-btn" onclick="openModal('addLogModal')" title="توثيق تسميع سريع">
    <svg style="width:24px; height:24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
</button>

<div class="modal" id="addLogModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>📖 توثيق سجل تسميع جديد</h3>
            <button class="modal-close" onclick="closeModal('addLogModal')">×</button>
        </div>
        <form method="POST" action="" onsubmit="return handleFormSubmit(this)">
            <input type="hidden" name="action" value="add_log">
            <input type="hidden" name="surah_name" id="surahName">
            
            <div class="modal-body">
                <div class="fg">
                    <label class="fl">اختر الطالب المسجل بالمنصة *</label>
                    <select name="student_id" required class="fc">
                        <option value="">-- حدد اسم الطالب --</option>
                        <?php foreach($students_list as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="fg">
                    <label class="fl">السورة الكريمة *</label>
                    <select name="surah_num" required class="fc" onchange="updateSurahName(this)">
                        <option value="">-- اختر السورة --</option>
                        <?php foreach($suwarList as $s): ?>
                            <option value="<?= $s[0] ?>" data-name="<?= $s[1] ?>" data-ayahs="<?= $s[2] ?>">
                                <?= $s[0] ?>. سورة <?= $s[1] ?> (<?= $s[2] ?> آية)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="fg">
                        <label class="fl">من الآية *</label>
                        <input type="number" name="ayah_from" id="ayahFrom" required min="1" class="fc" placeholder="1">
                    </div>
                    <div class="fg">
                        <label class="fl">إلى الآية *</label>
                        <input type="number" name="ayah_to" id="ayahTo" required min="1" class="fc" placeholder="الأخير">
                    </div>
                </div>

                <div class="form-row">
                    <div class="fg">
                        <label class="fl">التقدير والتقييم *</label>
                        <select name="grade" required class="fc">
                            <option value="ممتاز">ممتاز</option>
                            <option value="جيد جداً">جيد جداً</option>
                            <option value="جيد">جيد</option>
                            <option value="مقبول">مقبول</option>
                            <option value="يحتاج مراجعة">يحتاج مراجعة</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl">النقاط المستحقة</label>
                        <input type="number" name="points_awarded" min="0" max="500" class="fc" value="10">
                    </div>
                </div>

                <div class="fg">
                    <label class="fl">ملاحظات التسميع والتوجيهات</label>
                    <textarea name="notes" class="fc" rows="2" placeholder="اكتب أي ملاحظات حول التجويد والمخارج..."></textarea>
                </div>
            </div>

            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addLogModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">تثبيت وحفظ السجل</button>
            </div>
        </form>
    </div>
</div>

<?php rattil_modals_js(); ?>

<script>
// ⚡ ميزة الفلترة والبحث اللحظي الخفيف (Live Filter Without Reload) لراحة الأدمن
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('liveSearchInput');
    const cards = document.querySelectorAll('.class-card-item');

    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            cards.forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                const surah = card.getAttribute('data-surah').toLowerCase();
                const teacher = card.getAttribute('data-teacher').toLowerCase();
                
                if(name.includes(query) || surah.includes(query) || teacher.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});

// فتح وإغلاق اللوحة الجانبية المنبثقة للطالب (Side Panel Setup)
function openStudentProfilePanel(cardElement) {
    const name = cardElement.getAttribute('data-name');
    const teacher = cardElement.getAttribute('data-teacher');
    const surah = cardElement.getAttribute('data-surah');
    const range = cardElement.getAttribute('data-range');
    const grade = cardElement.getAttribute('data-grade');
    const points = cardElement.getAttribute('data-points');
    const level = cardElement.getAttribute('data-level');
    const notes = cardElement.getAttribute('data-notes');
    const date = cardElement.getAttribute('data-date');
    const studentId = cardElement.getAttribute('data-id');
    const logId = cardElement.getAttribute('data-logid');

    // تعبئة بيانات اللوحة الجانبية ديناميكياً
    document.getElementById('panelStudentName').innerText = name;
    document.getElementById('panelTeacherName').innerText = `المعلم المسؤول: ${teacher}`;
    document.getElementById('panelAvatarCircle').innerText = name.charAt(0);
    document.getElementById('panelLastSurah').innerText = surah;
    document.getElementById('panelLastRange').innerText = range;
    document.getElementById('panelLastGrade').innerText = grade;
    document.getElementById('panelLastDate').innerText = date;
    document.getElementById('panelLastNotes').innerText = notes;
    document.getElementById('panelLevelText').innerText = `المستوى الحالي: ${level}`;
    
    // حساب شريط تقدم متناسق مبني على النقاط للمظهر الجمالي الرياضي
    const progressPercent = Math.min(100, (points % 100));
    document.getElementById('panelProgressBar').style.width = `${progressPercent}%`;

    // تحديث روابط الإجراءات والتعديل وحذف السجل
    document.getElementById('panelProfileBtn').href = `student_profile.php?id=${studentId}`;
    document.getElementById('panelDeleteBtn').href = `?delete=${logId}`;

    // إظهار اللوحة والـ Backdrop
    document.getElementById('profileSidePanel').classList.add('open');
    document.getElementById('panelBackdrop').style.display = 'block';
}

function closeStudentProfilePanel() {
    document.getElementById('profileSidePanel').classList.remove('open');
    document.getElementById('panelBackdrop').style.display = 'none';
}

function updateSurahName(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('surahName').value = opt.dataset.name || '';
    const maxAyah = opt.dataset.ayahs || '';
    
    const fromInput = document.getElementById('ayahFrom');
    const toInput = document.getElementById('ayahTo');
    
    if(maxAyah) {
        fromInput.setAttribute('max', maxAyah);
        toInput.setAttribute('max', maxAyah);
        toInput.value = maxAyah;
    }
}

function handleFormSubmit(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = 'جاري الحفظ والتوثيق...';
    }
    return true;
}
</script>

</body>
</html>