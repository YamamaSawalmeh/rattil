<?php
/**
 * dashboard.php — لوحة التحكم الإدارية الرئيسية لمنصة رتِّل
 * تم التحديث بالالتزام الكامل بكافة معايير وأنماط rattil_shared.php واستدعاء دوالها القياسية
 */

require_once '../config/db.php';
requireRole('admin');

// استدعاء نظام التصميم والدوال المشتركة للمنصة
require_once 'rattil_shared.php';

$user   = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);

/* ══════════════════════════════════════════════════
   🧠 [DATA LAYER] جلب المؤشرات الحقيقية من قاعدة البيانات
   ══════════════════════════════════════════════════ */

// 1. بطاقات الأداء الإدارية الرئيسية (KPIs)
$total_students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$total_teachers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$total_halaqat  = (int)$pdo->query("SELECT COUNT(*) FROM halaqat WHERE status='active'")->fetchColumn();

// حضور اليوم (مؤشرات دقيقة متزامنة مع تاريخ اليوم الحقيقي)
$today_date = date('Y-m-d');

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE date = ?");
$stmt_total->execute([$today_date]);
$att_today_total = (int)$stmt_total->fetchColumn();

$stmt_present = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE status='present' AND date = ?");
$stmt_present->execute([$today_date]);
$att_today_present = (int)$stmt_present->fetchColumn();

// حساب نسبة الحضور الفعلية لليوم
$today_attendance_rate = $att_today_total > 0 ? round(($att_today_present / $att_today_total) * 100) : 0; 

// تنبيهات الملاحظات غير المقروءة
$open_alerts_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();

// 2. إحصائيات الحضور والغياب الإجمالية التراكمية لعرضها داخل بطاقة تفصيلية بديلة للرسمة
$global_attendance_stats = $pdo->query("
    SELECT 
        COUNT(*) as total_records,
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as total_absent,
        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as total_late
    FROM attendance
")->fetch(PDO::FETCH_ASSOC);

$total_rec = (int)($global_attendance_stats['total_records'] ?? 0);
$total_p   = (int)($global_attendance_stats['total_present'] ?? 0);
$total_a   = (int)($global_attendance_stats['total_absent'] ?? 0);
$total_l   = (int)($global_attendance_stats['total_late'] ?? 0);

// حساب النسب المئوية الإجمالية التراكمية
$percent_present = $total_rec > 0 ? round(($total_p / $total_rec) * 100, 1) : 0;
$percent_absent  = $total_rec > 0 ? round(($total_a / $total_rec) * 100, 1) : 0;
$percent_late    = $total_rec > 0 ? round(($total_l / $total_rec) * 100, 1) : 0;

// 3. جلب الأنشطة الأخيرة الحقيقية من سجلات التسميع
$recent_activities = $pdo->query("
    SELECT rl.id, DATE_FORMAT(rl.recorded_at, '%H:%i') as action_time,
           u.name as student_name, t.name as teacher_name, rl.surah_name,
           CONCAT('تم تسميع سورة ', rl.surah_name, ' بنجاح') as details
    FROM recitation_log rl
    JOIN users u ON u.id = rl.student_id
    JOIN users t ON t.id = rl.teacher_id
    ORDER BY rl.recorded_at DESC LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);

// 4. التنبيهات الإدارية الذكية المبنية على حالات الغياب والتأخر الفعالة
$critical_alerts = $pdo->query("
    SELECT DISTINCT u.id, u.name, 'تنبيه غياب' as alert_type, CONCAT('مسجل في حالة: ', att.status) as reason
    FROM attendance att
    JOIN users u ON u.id = att.student_id
    WHERE att.status IN ('absent', 'late')
    ORDER BY att.date DESC LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// بدء بناء هيكل الصفحة باستخدام الفانكشن القياسية الموحدة من rattil_shared
rattil_page_start("مركز التحكم الإداري");
?>

<style>
    /* التنسيقات الهيكلية المكملة لصفحة لوحة التحكم بالتوافق مع نظام ألوان رتيل القياسي */
    .dashboard-analytics-layout {
        display: grid;
        grid-template-columns: 1.6fr 1.2fr;
        gap: 1.5rem;
    }

    /* كارت الحضور البديل للرسمة */
    .attendance-summary-card {
        background: var(--surface);
        border-radius: var(--r-xl);
        border: 1px solid var(--border);
        padding: 1.5rem;
        box-shadow: var(--sh-sm);
    }

    .attendance-summary-card .card-header-title {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--olive-800);
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .attendance-summary-card .card-header-title svg {
        width: 20px; height: 20px;
        stroke: var(--olive-600);
        fill: none; stroke-width: 2;
    }

    .metrics-list-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
    }

    .metric-row-item {
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: var(--r-lg);
        padding: 1rem 1.25rem;
    }

    .metric-row-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .metric-row-label {
        font-size: 0.9rem;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .metric-row-value {
        font-size: 0.88rem;
        font-weight: 800;
        color: var(--olive-800);
    }

    .metric-row-value span {
        color: var(--muted);
        font-size: 0.78rem;
        font-weight: 500;
        margin-right: 0.25rem;
    }

    .side-panels-pane {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .panel-card {
        background: var(--surface);
        border-radius: var(--r-xl);
        border: 1px solid var(--border);
        padding: 1.5rem;
        box-shadow: var(--sh-sm);
    }

    .panel-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--olive-800);
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .activity-timeline {
        position: relative;
        padding-right: 1.25rem;
    }
    .activity-timeline::before {
        content: '';
        position: absolute;
        right: 4px; top: 0; bottom: 0;
        width: 2px;
        background: var(--border);
    }

    .timeline-node {
        position: relative;
        padding-bottom: 1.25rem;
    }
    .timeline-node:last-child { padding-bottom: 0; }
    
    .timeline-node::before {
        content: '';
        position: absolute;
        right: -1.5rem; top: 5px;
        width: 10px; height: 10px;
        border-radius: 50%;
        background: var(--gold-500);
        border: 2px solid var(--surface);
        box-shadow: var(--sh-xs);
    }

    .node-time {
        font-size: 0.72rem;
        color: var(--muted);
        font-weight: 600;
    }

    .node-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--text);
        margin: 0.15rem 0;
    }

    .alert-strip-modern {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: var(--r-md);
        margin-bottom: 0.75rem;
        border-right: 4px solid var(--danger);
        background: var(--danger-bg);
        font-size: 0.85rem;
    }
    .alert-strip-modern:last-child { margin-bottom: 0; }
    .alert-strip-modern strong { color: var(--danger); font-weight: 700; }

    @media (max-width: 1100px) {
        .dashboard-analytics-layout {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php rattil_sidebar($user, 'dashboard', $unread); ?>

<main class="pw">
    
    <header class="ph">
        <div class="ph-left">
            <h1>
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                <span>أهلاً بك مجدداً، <?= htmlspecialchars($user['name'] ?? 'الأدمن') ?> 👋</span>
            </h1>
            <p>إليك نظرة شاملة وتحليلية لأداء الحلقات والعملية التعليمية اليوم بالاعتماد على قاعدة البيانات.</p>
        </div>
        <div class="quick-actions-group" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="students.php?action=add" class="btn btn-primary btn-sm">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>تسجيل طالب جديد</span>
            </a>
            <a href="halaqat.php?action=create" class="btn btn-secondary btn-sm">
                <span>إنشاء حلقة جديدة</span>
            </a>
        </div>
    </header>

    <section class="stats-grid">
        <div class="stat-box">
            <div class="stat-ic g">
                <svg viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= number_format($total_students) ?></div>
                <div class="stat-l">إجمالي الطلاب</div>
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-ic a">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= number_format($total_teachers) ?></div>
                <div class="stat-l">المعلمين النشطين</div>
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-ic b">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= number_format($total_halaqat) ?></div>
                <div class="stat-l">الحلقات النشطة</div>
            </div>
        </div>

        <div class="stat-box">
            <div class="stat-ic p">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div class="stat-v"><?= $today_attendance_rate ?>%</div>
                <div class="stat-l">نسبة حضور اليوم</div>
            </div>
        </div>
    </section>

    <div class="dashboard-analytics-layout">
        
        <div class="attendance-summary-card">
            <div class="card-header-title">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>ملخص سجلات الحضور والغياب التراكمي في النظام</span>
            </div>
            
            <div class="metrics-list-wrapper">
                <div class="metric-row-item">
                    <div class="metric-row-info">
                        <div class="metric-row-label">
                            <span class="badge badge-active">✔</span>
                            <span>إجمالي عمليات الحضور الحالية</span>
                        </div>
                        <div class="metric-row-value"><?= number_format($total_p) ?> <span>سجل</span></div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= $percent_present ?>%; background: linear-gradient(90deg, var(--success), #2ecc71);"></div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem; font-weight: bold;"><?= $percent_present ?>% من إجمالي السجلات</div>
                </div>

                <div class="metric-row-item">
                    <div class="metric-row-info">
                        <div class="metric-row-label">
                            <span class="badge badge-inactive">✖</span>
                            <span>إجمالي حالات الغياب المسجلة</span>
                        </div>
                        <div class="metric-row-value"><?= number_format($total_a) ?> <span>سجل</span></div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= $percent_absent ?>%; background: linear-gradient(90deg, var(--danger), #e74c3c);"></div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem; font-weight: bold;"><?= $percent_absent ?>% من إجمالي السجلات</div>
                </div>

                <div class="metric-row-item">
                    <div class="metric-row-info">
                        <div class="metric-row-label">
                            <span class="badge badge-gold">⚠</span>
                            <span>إجمالي حالات التأخر عن الحلقات</span>
                        </div>
                        <div class="metric-row-value"><?= number_format($total_l) ?> <span>سجل</span></div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= $percent_late ?>%; background: linear-gradient(90deg, var(--gold-600), var(--gold-400));"></div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem; font-weight: bold;"><?= $percent_late ?>% من إجمالي السجلات</div>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed var(--border); font-size: 0.8rem; color: var(--muted); text-align: center;">
                مجموع العمليات التي تم تحليلها في قاعدة البيانات: <strong><?= number_format($total_rec) ?></strong> سجل حضور وغياب كامل.
            </div>
        </div>

        <div class="side-panels-pane">
            
            <div class="panel-card">
                <div class="panel-card-title">
                    <span>تنبيهات المتابعة الفورية</span>
                    <?php if ($open_alerts_count > 0): ?>
                        <span class="badge badge-inactive"><?= $open_alerts_count ?> جديد</span>
                    <?php endif; ?>
                </div>
                <div class="panel-body-content">
                    <?php if(!empty($critical_alerts)): ?>
                        <?php foreach($critical_alerts as $alert): ?>
                            <div class="alert-strip-modern">
                                <strong><?= htmlspecialchars($alert['alert_type']) ?>:</strong>
                                <span>الطالب <?= htmlspecialchars($alert['name']) ?> (<?= htmlspecialchars($alert['reason']) ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 0.85rem; color: var(--muted); text-align: center; padding: 1rem 0;">لا توجد تنبيهات غياب أو تأخر حرجة لليوم ✨</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel-card">
                <div class="panel-card-title">
                    <span>آخر عمليات التسميع الحالية</span>
                </div>
                <div class="activity-timeline">
                    <?php if(!empty($recent_activities)): ?>
                        <?php foreach($recent_activities as $act): ?>
                            <div class="timeline-node">
                                <div class="node-time"><?= $act['action_time'] ?> - بواسطة الشيخ/ <?= htmlspecialchars($act['teacher_name']) ?></div>
                                <div class="node-title"><?= htmlspecialchars($act['student_name']) ?></div>
                                <p style="font-size: 0.8rem; color: var(--muted); margin: 0;"><?= htmlspecialchars($act['details']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 0.85rem; color: var(--muted); text-align: center; padding: 1rem 0;">لم يتم تسجيل عمليات تسميع في النظام اليوم بعد.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</main>

</body>
</html>