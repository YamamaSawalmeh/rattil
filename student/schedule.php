<?php
require_once __DIR__ . '/../config/db.php';
requireRole('student');

$user = getCurrentUser($pdo);
$notifs = getUnreadCount($pdo);
$sid = (int)$_SESSION['user_id'];

require_once __DIR__ . '/student_shared.php';

$stmt = $pdo->prepare("
    SELECT mp.*, h.name AS halqa_name
    FROM memorization_plan mp
    LEFT JOIN halaqat h ON mp.halqa_id = h.id
    WHERE mp.student_id = ?
    ORDER BY FIELD(mp.status,'in_progress','pending','completed'), mp.target_date ASC
");
$stmt->execute([$sid]);
$plans = $stmt->fetchAll();

$planProgress = [];
if (!empty($plans)) {
    $trackingExists = (bool)$pdo->query("SHOW TABLES LIKE 'quran_tracking'")->fetchColumn();
    if ($trackingExists) {
        $progressStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ayah_number)
            FROM quran_tracking
            WHERE student_id = ?
              AND surah_number = ?
              AND status = 'memorized'
              AND ayah_number BETWEEN ? AND ?
        ");
        foreach ($plans as $p) {
            $rangeTotal = max(1, (int)$p['ayah_to'] - (int)$p['ayah_from'] + 1);
            $progressStmt->execute([$sid, (int)$p['surah_number'], (int)$p['ayah_from'], (int)$p['ayah_to']]);
            $memorized = (int)$progressStmt->fetchColumn();
            $planProgress[(int)$p['id']] = min(100, round($memorized / $rangeTotal * 100));
        }
    }
}

$total = count($plans);
$completed = count(array_filter($plans, fn($p) => $p['status'] === 'completed'));
$inprog = count(array_filter($plans, fn($p) => $p['status'] === 'in_progress'));
$pending = count(array_filter($plans, fn($p) => $p['status'] === 'pending'));
$overallPct = $total > 0 ? round($completed / $total * 100) : 0;
$nextPlan = $plans[0] ?? null;

student_page_start('خطة الحفظ');
student_sidebar($user, 'schedule', $notifs);
?>

<style>
.plan-hero{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:1.25rem;margin-bottom:1.25rem}
.plan-hero-main{position:relative;overflow:hidden;border-radius:var(--r-lg);background:linear-gradient(135deg,var(--olive-900),var(--olive-700));color:#fff;box-shadow:var(--sh-md);padding:1.5rem;min-height:280px}
.plan-hero-main::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 18% 20%,rgba(201,168,76,.25),transparent 28%),radial-gradient(circle at 82% 78%,rgba(255,255,255,.1),transparent 30%)}
.plan-hero-main>*{position:relative;z-index:1}.plan-kicker{display:inline-flex;align-items:center;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);color:var(--gold-300);border-radius:999px;padding:.35rem .75rem;font-weight:900;font-size:.78rem}
.plan-hero-main h2{font-size:2rem;line-height:1.25;color:#fff;margin:.85rem 0 .35rem;font-weight:900}.plan-hero-main p{color:rgba(255,255,255,.72);font-size:.95rem;max-width:620px}
.hero-progress{margin-top:1.35rem;max-width:560px}.hero-progress-top{display:flex;justify-content:space-between;color:rgba(255,255,255,.82);font-weight:800;font-size:.85rem;margin-bottom:.5rem}.hero-bar{height:14px;background:rgba(0,0,0,.22);border-radius:999px;overflow:hidden;padding:3px}.hero-bar div{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--gold-500),var(--gold-300));box-shadow:0 0 18px rgba(201,168,76,.35)}
.next-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:1.15rem;display:flex;flex-direction:column;gap:.85rem}.next-card-title{font-weight:900;color:var(--olive-800);display:flex;align-items:center;gap:.45rem}.next-surah{background:linear-gradient(135deg,var(--olive-50),#fff);border:1px solid var(--border);border-radius:var(--r-md);padding:1rem}.next-surah small{display:block;color:var(--muted);font-weight:800}.next-surah strong{display:block;color:var(--olive-800);font-size:1.25rem;margin:.15rem 0}.next-surah span{display:block;color:var(--muted);font-size:.82rem}
.plan-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.25rem}.plan-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-xs);padding:1rem;position:relative;overflow:hidden}.plan-stat::after{content:'';position:absolute;left:-24px;bottom:-24px;width:74px;height:74px;border-radius:50%;background:var(--olive-50)}.plan-stat strong{display:block;color:var(--olive-800);font-size:1.7rem;line-height:1}.plan-stat span{display:block;color:var(--muted);font-size:.8rem;font-weight:800;margin-top:.28rem}
.filter-dock{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;background:var(--surface);border:1px solid var(--border);box-shadow:var(--sh-sm);border-radius:var(--r-lg);padding:.9rem 1rem;margin-bottom:1rem}.filter-title{font-weight:900;color:var(--olive-800)}.filter-actions{display:flex;gap:.45rem;flex-wrap:wrap}.plan-filter{border:1px solid var(--border-2);background:#fff;color:var(--olive-700);border-radius:999px;padding:.45rem .85rem;font-family:inherit;font-weight:900;cursor:pointer}.plan-filter.active{background:var(--olive-700);color:#fff;border-color:var(--olive-700)}
.path-wrap{position:relative;display:grid;gap:1rem}.path-wrap::before{content:'';position:absolute;right:28px;top:16px;bottom:16px;width:3px;background:linear-gradient(var(--gold-400),var(--olive-300));border-radius:999px}
.plan-card{position:relative;display:grid;grid-template-columns:58px minmax(0,1fr) 160px;gap:1rem;align-items:stretch;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:1rem;margin-right:0;transition:opacity .2s,transform .2s,box-shadow .2s}.plan-card:hover{transform:translateY(-2px);box-shadow:var(--sh-md)}.plan-card.hidden-plan{display:none}
.path-node{position:relative;z-index:2;width:58px;height:58px;border-radius:50%;display:grid;place-items:center;background:#fff;border:3px solid var(--olive-200);box-shadow:0 0 0 6px var(--bg);color:var(--olive-800);font-weight:900}.plan-card.in_progress .path-node{border-color:var(--gold-500);background:var(--gold-100)}.plan-card.completed .path-node{border-color:var(--success);background:var(--success-bg);color:var(--success)}
.plan-main h3{font-size:1.05rem;color:var(--olive-800);margin-bottom:.25rem}.plan-main p{color:var(--muted);font-size:.84rem}.plan-tags{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.7rem}.plan-tags span{display:inline-flex;border:1px solid var(--border);background:var(--olive-50);color:var(--olive-700);font-weight:800;font-size:.74rem;border-radius:999px;padding:.25rem .55rem}
.plan-side{display:flex;flex-direction:column;align-items:flex-end;justify-content:center;gap:.45rem}.status-pill{display:inline-flex;border-radius:999px;padding:.35rem .7rem;font-size:.78rem;font-weight:900}.status-pill.pending{background:var(--info-bg);color:var(--info)}.status-pill.in_progress{background:var(--warning-bg);color:var(--warning)}.status-pill.completed{background:var(--success-bg);color:var(--success)}
.due-text{font-size:.76rem;color:var(--muted);font-weight:800;text-align:left}.due-text.hot{color:var(--warning)}.due-text.late{color:var(--danger)}
.mini-progress{height:7px;background:var(--border);border-radius:999px;overflow:hidden;width:130px}.mini-progress div{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--gold-500),var(--olive-500))}
.empty-plan{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:3rem 2rem;text-align:center;color:var(--muted)}.empty-plan svg{width:58px;height:58px;stroke:var(--olive-500);fill:none;stroke-width:2;margin-bottom:1rem}
@media(max-width:1050px){.plan-hero{grid-template-columns:1fr}.plan-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:720px){.plan-card{grid-template-columns:48px minmax(0,1fr)}.plan-side{grid-column:2;align-items:flex-start}.path-wrap::before{right:23px}.path-node{width:48px;height:48px}.plan-stats{grid-template-columns:1fr}.plan-hero-main h2{font-size:1.5rem}}
</style>

<main class="pw">
  <header class="ph anim">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        خطة الحفظ
      </h1>
      <p>مسار واضح للمقاطع القادمة والجارية والمكتملة.</p>
    </div>
    <button class="notif-btn" onclick="openNotif()" aria-label="الإشعارات">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($notifs>0): ?><span class="notif-dot"><?= $notifs ?></span><?php endif; ?>
    </button>
  </header>

  <section class="plan-hero">
    <div class="plan-hero-main anim anim-d1">
      <span class="plan-kicker">مسار الحفظ</span>
      <h2> خطتك اليوم… ختمتك غدًا بإذن الله</h2>
      <p>كل مقطع يظهر كمحطة في الرحلة. ركّز على الجاري الآن، واترك الباقي مرتبًا أمامك.</p>
      <div class="hero-progress">
        <div class="hero-progress-top"><span>التقدم الإجمالي</span><span><?= $overallPct ?>%</span></div>
        <div class="hero-bar"><div style="width:<?= $overallPct ?>%"></div></div>
      </div>
    </div>

    <aside class="next-card anim anim-d2">
      <div class="next-card-title">
        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
        المحطة الأقرب
      </div>
      <div class="next-surah">
        <?php if($nextPlan): ?>
          <small><?= $nextPlan['status'] === 'in_progress' ? 'قيد الحفظ الآن' : 'المقطع القادم' ?></small>
          <strong>سورة <?= htmlspecialchars($nextPlan['surah_name']) ?></strong>
          <span>آية <?= (int)$nextPlan['ayah_from'] ?> إلى <?= (int)$nextPlan['ayah_to'] ?></span>
        <?php else: ?>
          <small>لا توجد محطة بعد</small>
          <strong>بانتظار خطة من المعلم</strong>
          <span>ستظهر المقاطع هنا عند إضافتها.</span>
        <?php endif; ?>
      </div>
      <a href="/rattil/student/quran.php" class="btn btn-primary" style="justify-content:center">افتح المصحف</a>
    </aside>
  </section>

  <section class="plan-stats">
    <div class="plan-stat anim anim-d1"><strong><?= $total ?></strong><span>إجمالي المقاطع</span></div>
    <div class="plan-stat anim anim-d2"><strong><?= $inprog ?></strong><span>جاري تنفيذها</span></div>
    <div class="plan-stat anim anim-d3"><strong><?= $pending ?></strong><span>قادمة</span></div>
    <div class="plan-stat anim anim-d4"><strong><?= $completed ?></strong><span>مكتملة</span></div>
  </section>

  <section class="filter-dock anim anim-d1">
    <div class="filter-title">محطات الخطة</div>
    <div class="filter-actions">
      <button class="plan-filter active" type="button" onclick="filterPlans('all', this)">الكل <?= $total ?></button>
      <button class="plan-filter" type="button" onclick="filterPlans('in_progress', this)">جاري <?= $inprog ?></button>
      <button class="plan-filter" type="button" onclick="filterPlans('pending', this)">قادم <?= $pending ?></button>
      <button class="plan-filter" type="button" onclick="filterPlans('completed', this)">مكتمل <?= $completed ?></button>
    </div>
  </section>

  <?php if(empty($plans)): ?>
    <div class="empty-plan anim">
      <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <p>لا توجد خطة حفظ بعد. تواصل مع معلمك لإعداد خطة تناسبك.</p>
    </div>
  <?php else: ?>
    <section class="path-wrap">
      <?php
      $statusAr = ['in_progress'=>'جاري','pending'=>'قادم','completed'=>'مكتمل'];
      foreach($plans as $i => $p):
        $diff = $p['target_date'] ? (int)ceil((strtotime($p['target_date']) - time()) / 86400) : null;
        $dueClass = $diff !== null && $diff < 0 ? 'late' : ($diff !== null && $diff <= 3 ? 'hot' : '');
        $dueText = $diff === null ? 'بدون تاريخ' : ($diff > 0 ? "بعد $diff يوم" : ($diff === 0 ? 'اليوم' : 'متأخر '.abs($diff).' يوم'));
        $rangeTotal = max(1, (int)$p['ayah_to'] - (int)$p['ayah_from'] + 1);
        $planPct = $p['status'] === 'completed' ? 100 : ($planProgress[(int)$p['id']] ?? 0);
      ?>
        <article class="plan-card <?= htmlspecialchars($p['status']) ?> anim" data-status="<?= htmlspecialchars($p['status']) ?>">
          <div class="path-node"><?= $i + 1 ?></div>
          <div class="plan-main">
            <h3>سورة <?= htmlspecialchars($p['surah_name']) ?></h3>
            <p>من آية <?= (int)$p['ayah_from'] ?> إلى آية <?= (int)$p['ayah_to'] ?></p>
            <div class="plan-tags">
              <?php if($p['halqa_name']): ?><span><?= htmlspecialchars($p['halqa_name']) ?></span><?php endif; ?>
              <span><?= $rangeTotal ?> آية</span>
              <?php if($p['target_date']): ?><span><?= date('d/m/Y', strtotime($p['target_date'])) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="plan-side">
            <span class="status-pill <?= htmlspecialchars($p['status']) ?>"><?= $statusAr[$p['status']] ?></span>
            <span class="due-text <?= $dueClass ?>"><?= $dueText ?></span>
            <div class="mini-progress" title="<?= $planPct ?>%"><div style="width:<?= $planPct ?>%"></div></div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

<?php
require_once __DIR__ . '/../admin/rattil_shared.php';
rattil_notif_panel($notifs);
?>

<script>
function filterPlans(status, btn) {
  document.querySelectorAll('.plan-filter').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.plan-card').forEach(card => {
    card.classList.toggle('hidden-plan', status !== 'all' && card.dataset.status !== status);
  });
}
</script>
</body>
</html>
