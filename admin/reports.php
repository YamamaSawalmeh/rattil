<?php
/**
 * reports.php — لوحة تحكم التقارير المتقدمة المتكاملة مع قاعدة البيانات لمنصة رتِّل
 * تم التطوير والربط البرمجي الكامل بناءً على rattil_db.sql و rattil_shared.php
 */

// 1. تضمين ملف الاتصال والتأكد من الصلاحيات الإدارية
require_once '../config/db.php'; 
requireRole('admin');

// 2. تضمين ملف التصميم الموحد لمنصة رتّل
require_once 'rattil_shared.php';

// جلب بيانات الجلسة والإشعارات
$user_session = getCurrentUser($pdo);
$unread_recitation_logs = getUnreadCount($pdo); 

// حساب عدد الإشعارات غير المقروءة للمدير من جدول notifications
$stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `user_id` = ? AND `is_read` = 0");
$stmt_notif->execute([$user_session['id']]);
$unread_notifications_count = $stmt_notif->fetchColumn();


/* ══════════════════════════════════════════════════
   🧠 [DATA LAYER] استخراج الإحصائيات الدقيقة من قاعدة البيانات 100%
   ══════════════════════════════════════════════════ */

// ① إحصائية KPI: إجمالي الطلاب المقيدين
$stmt_total = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `role` = 'student'");
$total_students = $stmt_total->fetchColumn();

// ② إحصائية KPI: الطلاب النشطين (الذين لديهم تسميعات مسجلة خلال آخر 30 يوماً)
$stmt_active = $pdo->query("
    SELECT COUNT(DISTINCT `student_id`) 
    FROM `recitation_log` 
    WHERE `recorded_at` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$active_students = $stmt_active->fetchColumn();

// ③ إحصائية KPI: نسبة الحضور العام عبر جدول attendance
$stmt_att = $pdo->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN `status` IN ('present', 'late') THEN 1 ELSE 0 END) as present_count
    FROM `attendance`
");
$att_data = $stmt_att->fetch(PDO::FETCH_ASSOC);
$attendance_rate = ($att_data['total_records'] > 0) 
    ? round(($att_data['present_count'] / $att_data['total_records']) * 100) 
    : 100;

// ④ إحصائية KPI: إجمالي الآيات المسموعة (بدل الصفحات تماشياً مع بنية التسميع بالآية في recitation_log)
$stmt_ayats = $pdo->query("SELECT SUM(ABS(`ayah_to` - `ayah_from`) + 1) FROM `recitation_log`");
$total_ayats_read = $stmt_ayats->fetchColumn() ?? 0;


// ⑤ استعلام جدول أداء الحلقات التحليلي: يربط الحلقات بالمعلم ويحسب الطلاب ونسب الحضور والإنجاز ديناميكياً
$stmt_halaqat = $pdo->query("
    SELECT 
        h.`id`,
        h.`name`,
        u.`name` AS `teacher`,
        (SELECT COUNT(*) FROM `halqa_students` hs WHERE hs.`halqa_id` = h.`id`) AS `students_count`,
        
        -- حساب نسبة حضور الحلقة
        COALESCE(
            ROUND(
                (SUM(CASE WHEN att.`status` IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(att.`id`)) * 100
            ), 100
        ) AS `attendance_rate`,
        
        -- متوسط الدرجات الممنوحة للحلقة لتمثيل معدل الإنجاز والخطة
        COALESCE(
            ROUND(
                (SUM(CASE WHEN rl.`grade` = 'ممتاز' THEN 100 WHEN rl.`grade` = 'جيد جداً' THEN 85 WHEN rl.`grade` = 'جيد' THEN 70 WHEN rl.`grade` = 'مقبول' THEN 55 ELSE 40 END) / COUNT(rl.`id`))
            ), 0
        ) AS `progress_rate`

    FROM `halaqat` h
    LEFT JOIN `users` u ON h.`teacher_id` = u.`id`
    LEFT JOIN `attendance` att ON h.`id` = att.`halqa_id`
    LEFT JOIN `recitation_log` rl ON h.`id` = rl.`halqa_id`
    WHERE h.`status` = 'active'
    GROUP BY h.`id`, h.`name`, u.`name`
");
$halaqat_reports = $stmt_halaqat->fetchAll(PDO::FETCH_ASSOC);


// ⑥ استعلام أفضل الطلاب (Ranking System) بناءً على رصيد نقاطهم الحقيقي من جدول student_points
$stmt_top = $pdo->query("
    SELECT 
        u.`name`,
        COALESCE(sp.`total_earned`, 0) AS `points`,
        (SELECT h.`name` FROM `halqa_students` hs JOIN `halaqat` h ON hs.`halqa_id` = h.`id` WHERE hs.`student_id` = u.`id` LIMIT 1) AS `halaqa`
    FROM `users` u
    LEFT JOIN `student_points` sp ON u.`id` = sp.`student_id`
    WHERE u.`role` = 'student'
    ORDER BY `points` DESC
    LIMIT 5
");
$top_students = $stmt_top->fetchAll(PDO::FETCH_ASSOC);


// ⑦ جلب البيانات الزمنية لآخر 7 أيام للرسم البياني (Chart.js)
$chart_labels = [];
$chart_recitations = [];
for ($i = 6; $i >= 0; $i--) {
    $date_string = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('l', strtotime($date_string)); // اليوم بالإنجليزية أو يمكن تحويله للعربية
    
    $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM `recitation_log` WHERE DATE(`recorded_at`) = ?");
    $stmt_c->execute([$date_string]);
    $chart_recitations[] = $stmt_c->fetchColumn();
}


// بدء هيكل الصفحة باستدعاء ميثود الـ Shell الجاهزة من الـ Design System
rattil_page_start('تقارير الأداء والإحصائيات التحليلية');
?>

<style>
/* ══════════════════════════════════════════════════
   أكواد مخصصة لصفحة التقارير تعزز الـ Premium SaaS Layout 
   وتتكامل مع متغيرات rattil_shared.php الأساسية
   ══════════════════════════════════════════════════ */

/* تحسينات تأثير الـ Glow الخفيف والـ Glassmorphism الناعم */
.premium-kpi {
  position: relative;
  overflow: hidden;
}
.premium-kpi::after {
  content: '';
  position: absolute;
  bottom: -20px; left: -20px;
  width: 80px; height: 80px;
  background: radial-gradient(circle, var(--gold-300) 0%, transparent 70%);
  opacity: 0.12;
  border-radius: 50%;
  pointer-events: none;
  transition: transform var(--dur) var(--ease);
}
.premium-kpi:hover::after {
  transform: scale(1.5);
}

/* شبكة الرسوم البيانية المتقدمة */
.charts-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
  .charts-grid {
    grid-template-columns: 1fr;
  }
}

/* تخصيص الـ Ranking cards لأفضل الطلاب */
.ranking-container {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.premium-rank-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  transition: all var(--dur) var(--ease);
}
.premium-rank-item:hover {
  transform: translateX(-4px);
  background: var(--surface);
  box-shadow: var(--sh-sm);
  border-color: var(--olive-200);
}
.rank-meta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.rank-badge-premium {
  width: 28px; height: 28px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.8rem; font-weight: 800;
  background: var(--olive-100); color: var(--olive-700);
}
.rank-badge-premium.top-1 { background: linear-gradient(135deg, var(--gold-500), var(--gold-300)); color: var(--olive-950); box-shadow: 0 2px 6px rgba(201,168,76,0.3); }
.rank-badge-premium.top-2 { background: #e2e8f0; color: #334155; }
.rank-badge-premium.top-3 { background: #ffedd5; color: #c2410c; }

.student-avatar {
  width: 36px; height: 36px;
  border-radius: var(--r-sm);
  background: var(--olive-700);
  color: #fff;
  font-size: 0.82rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.student-info-text .s-name { font-size: 0.88rem; font-weight: 700; color: var(--text); }
.student-info-text .s-halaqa { font-size: 0.75rem; color: var(--muted); }

/* أدوات التحكم العلوية في الجداول */
.table-controls {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

@media print {
  body { background: #fff; color: #000; }
  .pw { padding: 0; margin: 0; width: 100%; }
  .card { box-shadow: none !important; border: 1px solid #ccc !important; }
}
</style>

<?php rattil_sidebar($user_session, 'reports', $unread_recitation_logs); ?>

<main class="pw">
    
    <header class="ph anim anim-d1">
        <div class="ph-left">
            <h1>
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                    <path d="M3 20h18" stroke-linecap="round"/>
                </svg>
                لوحة التقارير والتحليلات الحية
            </h1>
            <p>مراقبة أداء الحلقات القرآنيّة، إحصائيات التسميع، ونسب انضباط الطلاب مباشرة من قاعدة البيانات.</p>
        </div>
        
        <div class="ph-right" style="display: flex; gap: 0.6rem; align-items: center;">
            <button class="btn btn-secondary" onclick="window.print();" title="طباعة التقرير">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span>تصدير وطباعة</span>
            </button>
            
            <button class="btn btn-primary" onclick="window.location.reload();" title="تحديث البيانات">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                <span>تحديث حي</span>
            </button>

            <button class="notif-btn" onclick="openNotif()" id="notifButton" title="الإشعارات">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <?php if($unread_notifications_count > 0): ?>
                    <span class="notif-dot" id="notifDot"><?= $unread_notifications_count ?></span>
                <?php endif; ?>
            </button>
        </div>
    </header>

    <section class="stats-grid anim anim-d2">
        
        <div class="stat-box premium-kpi">
            <div class="stat-ic g">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= number_format($total_students) ?></div>
                <div class="stat-l">الطلاب المقيدين</div>
            </div>
        </div>

        <div class="stat-box premium-kpi">
            <div class="stat-ic b">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= number_format($active_students) ?></div>
                <div class="stat-l">النشطين (آخر 30 يوماً)</div>
            </div>
        </div>

        <div class="stat-box premium-kpi">
            <div class="stat-ic a">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= $attendance_rate ?>%</div>
                <div class="stat-l">نسبة الانضباط العام</div>
            </div>
        </div>

        <div class="stat-box premium-kpi">
            <div class="stat-ic p">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= number_format($total_ayats_read) ?></div>
                <div class="stat-l">آية تم تسميعها</div>
            </div>
        </div>

    </section>

    <div class="charts-grid anim anim-d3">
        
        <div class="card" style="margin-bottom: 0;">
            <div class="card-head">
                <div class="card-title">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                    تحليل وتيرة عمليات التسميع (آخر 7 أيام حية)
                </div>
            </div>
            <div style="position: relative; height: 320px; width: 100%;">
                <canvas id="rattilAnalyticsChart"></canvas>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column;">
            <div class="card-head">
                <div class="card-title">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    لوحة شرف الطلاب المتميزين
                </div>
            </div>
            <div class="ranking-container" style="flex: 1; overflow-y: auto;">
                <?php 
                $rank_counter = 1;
                foreach($top_students as $st): 
                    $rank_class = '';
                    if ($rank_counter === 1) $rank_class = 'top-1';
                    elseif ($rank_counter === 2) $rank_class = 'top-2';
                    elseif ($rank_counter === 3) $rank_class = 'top-3';
                    
                    // استخراج الحرفين الأولين كـ Initial البديل للايموجي
                    $words = explode(' ', $st['name']);
                    $initials = mb_substr($words[0] ?? '', 0, 1) . mb_substr($words[1] ?? '', 0, 1);
                ?>
                <div class="premium-rank-item">
                    <div class="rank-meta">
                        <span class="rank-badge-premium <?= $rank_class ?>"><?= $rank_counter ?></span>
                        <div class="student-avatar" style="<?= $rank_counter == 1 ? 'background: var(--gold-600);' : '' ?>">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="student-info-text">
                            <div class="s-name"><?= htmlspecialchars($st['name']) ?></div>
                            <div class="s-halaqa"><?= htmlspecialchars($st['halaqa'] ?? 'غير مسجل بحلقة') ?></div>
                        </div>
                    </div>
                    <div style="text-align: left;">
                        <span class="badge badge-gold" style="font-family: monospace; font-size: 0.85rem;">
                            <?= $st['points'] ?> نقطة
                        </span>
                    </div>
                </div>
                <?php 
                $rank_counter++;
                endforeach; 
                if (empty($top_students)):
                ?>
                    <div style="text-align: center; color: var(--muted); padding: 2rem;">لا توجد نقاط مسجلة للطلاب حالياً.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="card anim anim-d4">
        <div class="card-head">
            <div class="card-title">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                الأداء التحليلي التفصيلي لحلقات التحفيظ النشطة
            </div>
        </div>

        <div class="table-controls">
            <div class="search-input-wrap" style="max-width: 320px; width: 100%;">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" fill="none" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="fc" id="tableSearch" placeholder="ابحث عن حلقة أو معلم..." onkeyup="filterHalaqatTable()">
            </div>
            <div>
                <span class="chip">عدد الحلقات الحالية: <?= count($halaqat_reports) ?></span>
            </div>
        </div>

        <div class="tbl-wrap">
            <table class="rtbl" id="halaqatTable">
                <thead>
                    <tr>
                        <th>اسم الحلقة</th>
                        <th>المعلم المشرف</th>
                        <th>عدد الطلاب</th>
                        <th>نسبة الانضباط والحضور</th>
                        <th>معدل جودة الحفظ</th>
                        <th>حالة الحلقة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($halaqat_reports as $row): 
                        // تعيين تصنيف نصي ولوني ديناميكي للحلقة بناءً على نسبة الحفظ
                        $status_label = 'ممتاز';
                        $badge_class = 'grade-ممتاز';
                        if ($row['progress_rate'] < 55) { $status_label = 'يحتاج مراجعة'; $badge_class = 'grade-يحتاج\ مراجعة'; }
                        elseif ($row['progress_rate'] < 70) { $status_label = 'مقبول'; $badge_class = 'grade-مقبول'; }
                        elseif ($row['progress_rate'] < 85) { $status_label = 'جيد'; $badge_class = 'grade-جيد'; }
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--olive-800);"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--olive-400);"></div>
                                <?= htmlspecialchars($row['teacher'] ?? 'لم يحدد') ?>
                            </div>
                        </td>
                        <td style="font-weight: 700; font-family: monospace;"><?= $row['students_count'] ?> طلاب</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: flex-start;">
                                <span style="font-family: monospace; font-weight: 700; width: 35px;"><?= $row['attendance_rate'] ?>%</span>
                                <div class="progress" style="width: 100px; flex-shrink: 0;">
                                    <div class="progress-bar" style="width: <?= $row['attendance_rate'] ?>%; background: var(--olive-600);"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem; justify-content: flex-start;">
                                <span style="font-family: monospace; font-weight: 700; width: 35px;"><?= $row['progress_rate'] ?>%</span>
                                <div class="progress" style="width: 100px; flex-shrink: 0;">
                                    <div class="progress-bar" style="width: <?= $row['progress_rate'] ?>%; background: linear-gradient(90deg, var(--gold-500), var(--gold-300));"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="grade <?= $badge_class ?>">
                                <?= $status_label ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; 
                    if (empty($halaqat_reports)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--muted); padding: 3rem;">لا توجد حلقات نشطة حالياً في النظام.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php 
rattil_notif_panel($unread_notifications_count);
rattil_modals_js(); 
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('rattilAnalyticsChart').getContext('2d');
    
    // تدرج لوني ناعم وآمن للرسم البياني متوافق مع الهوية الفخمة
    const oliveGradient = ctx.createLinearGradient(0, 0, 0, 300);
    oliveGradient.addColorStop(0, 'rgba(45, 122, 82, 0.25)');
    oliveGradient.addColorStop(1, 'rgba(45, 122, 82, 0.00)');

    // تمرير البيانات المجلوبة من الـ PHP Loop الحقيقي بقاعدة البيانات لداخل الـ Chart
    const dynamicLabels = <?= json_encode($chart_labels) ?>;
    const dynamicData = <?= json_encode($chart_recitations) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dynamicLabels,
            datasets: [{
                label: 'عدد عمليات التسميع اليومية المقيدة',
                data: dynamicData,
                borderColor: '#2d7a52', // --olive-500
                borderWidth: 3,
                pointBackgroundColor: '#2d7a52',
                pointHoverRadius: 6,
                backgroundColor: oliveGradient,
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            rtl: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: 'Tajawal', size: 12, weight: '700' },
                        color: '#1a2e24'
                    }
                },
                tooltip: {
                    backgroundColor: '#0f2d1f',
                    titleFont: { family: 'Tajawal', size: 13, weight: '700' },
                    bodyFont: { family: 'Tajawal', size: 12 },
                    padding: 12,
                    textAlign: 'right'
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Tajawal', size: 11 }, color: '#6b7c75' }
                },
                y: {
                    grid: { color: 'rgba(22, 51, 36, 0.05)' },
                    ticks: { font: { family: 'Tajawal', size: 11 }, color: '#6b7c75', stepSize: 1 }
                }
            }
        }
    });
});

/**
 * فلترة سريعة ولحظية لجدول أداء الحلقات
 */
function filterHalaqatTable() {
    const input = document.getElementById("tableSearch");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("halaqatTable");
    const tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let match = false;
        const tds = tr[i].getElementsByTagName("td");
        
        if (tds[0] || tds[1]) {
            const text1 = tds[0].textContent || tds[0].innerText;
            const text2 = tds[1].textContent || tds[1].innerText;
            if (text1.toLowerCase().indexOf(filter) > -1 || text2.toLowerCase().indexOf(filter) > -1) {
                match = true;
            }
        }
        tr[i].style.display = match ? "" : "none";
    }
}
</script>

</body>
</html>