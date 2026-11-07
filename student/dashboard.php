<?php
require_once __DIR__ . '/../config/db.php';
requireRole('student');

$user = getCurrentUser($pdo);
$notifs = getUnreadCount($pdo);
$sid = (int)$_SESSION['user_id'];

require_once __DIR__ . '/student_shared.php';

$stmt = $pdo->prepare("SELECT surah_name,grade,recorded_at FROM recitation_log WHERE student_id=? ORDER BY recorded_at DESC LIMIT 1");
$stmt->execute([$sid]);
$lastRec = $stmt->fetch();

$stmt = $pdo->prepare("SELECT points,total_earned,level FROM student_points WHERE student_id=?");
$stmt->execute([$sid]);
$pts = $stmt->fetch() ?: ['points'=>0,'total_earned'=>0,'level'=>1];
$pts['level'] = getLevelForPoints((int)$pts['total_earned']);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=?");
$stmt->execute([$sid]);
$attTotal = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=? AND status='present'");
$stmt->execute([$sid]);
$attPresent = (int)$stmt->fetchColumn();
$attRate = $attTotal > 0 ? round($attPresent / $attTotal * 100) : 0;

$stmt = $pdo->prepare("
    SELECT h.*
    FROM halaqat h
    JOIN halqa_students hs ON h.id=hs.halqa_id
    WHERE hs.student_id=? AND h.status='active'
    ORDER BY h.schedule_time
");
$stmt->execute([$sid]);
$halaqat = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_garden WHERE student_id=?");
$stmt->execute([$sid]);
$surahCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT b.name,b.icon FROM student_badges sb JOIN badges b ON sb.badge_id=b.id WHERE sb.student_id=? ORDER BY sb.granted_at DESC LIMIT 1");
$stmt->execute([$sid]);
$lastBadge = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$sid]);
$recentNotifs = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT *
    FROM memorization_plan
    WHERE student_id=? AND status IN ('in_progress','pending')
    ORDER BY FIELD(status,'in_progress','pending'), target_date ASC
    LIMIT 3
");
$stmt->execute([$sid]);
$nextPlans = $stmt->fetchAll();

$levelPoints = [1=>100,2=>500,3=>1000,4=>2000,5=>9999];
$nextLevel = $levelPoints[$pts['level']] ?? 9999;
$progress = $nextLevel > 0 ? min(100, round($pts['total_earned'] / $nextLevel * 100)) : 100;
$remaining = max(0, $nextLevel - (int)$pts['total_earned']);

student_page_start('لوحة الطالب');
student_sidebar($user, 'dashboard', $notifs);
?>

<style>
.dash-hero{position:relative;display:grid;grid-template-columns:minmax(0,1.25fr) 330px;gap:1.25rem;margin-bottom:1.3rem}
.hero-card{position:relative;overflow:hidden;min-height:300px;border-radius:var(--r-lg);background:linear-gradient(135deg,var(--olive-900),var(--olive-700));box-shadow:var(--sh-md);padding:1.6rem;color:#fff}
.hero-card::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 16% 20%,rgba(201,168,76,.24),transparent 28%),radial-gradient(circle at 80% 70%,rgba(255,255,255,.11),transparent 30%);pointer-events:none}
.hero-content{position:relative;z-index:2;display:grid;grid-template-columns:minmax(0,1fr) 230px;gap:1rem;align-items:center}
.hero-kicker{display:inline-flex;align-items:center;gap:.45rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);color:var(--gold-300);font-weight:900;border-radius:999px;padding:.35rem .7rem;font-size:.78rem}
.hero-title{font-size:2rem;line-height:1.25;margin:.85rem 0 .35rem;font-weight:900;color:#fff}
.hero-copy{color:rgba(255,255,255,.72);font-size:.95rem;max-width:560px}
.hero-actions{display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1.15rem}
.hero-actions .btn{border-color:rgba(255,255,255,.2)}
.hero-actions .btn-secondary{background:rgba(255,255,255,.09);color:#fff}
.level-orbit{width:210px;height:210px;border-radius:50%;display:grid;place-items:center;margin-inline:auto;background:conic-gradient(var(--gold-500) <?= $progress ?>%,rgba(255,255,255,.14) 0);position:relative;box-shadow:inset 0 0 0 1px rgba(255,255,255,.08),0 20px 45px rgba(0,0,0,.18)}
.level-orbit::before{content:'';position:absolute;inset:13px;border-radius:50%;background:linear-gradient(135deg,var(--olive-800),var(--olive-600));box-shadow:inset 0 0 28px rgba(0,0,0,.12)}
.level-orbit-inner{position:relative;text-align:center}.level-orbit-inner small{display:block;color:var(--gold-300);font-weight:800}.level-orbit-inner strong{display:block;font-size:3rem;line-height:1;color:#fff}.level-orbit-inner span{color:rgba(255,255,255,.68);font-size:.8rem}
.today-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:1.2rem;display:flex;flex-direction:column;gap:.95rem}
.today-title{font-weight:900;color:var(--olive-800);display:flex;align-items:center;gap:.5rem}
.today-chip{background:var(--olive-50);border:1px solid var(--border);border-radius:var(--r-md);padding:.85rem}
.today-chip span{display:block;color:var(--muted);font-size:.75rem;font-weight:700}.today-chip strong{display:block;color:var(--olive-800);font-size:1rem;margin-top:.15rem}
.metric-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.25rem}
.metric-card{position:relative;overflow:hidden;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-xs);padding:1.15rem;min-height:126px}
.metric-card::after{content:'';position:absolute;left:-24px;bottom:-30px;width:92px;height:92px;border-radius:50%;background:var(--olive-50)}
.metric-icon{width:40px;height:40px;border-radius:var(--r-md);display:grid;place-items:center;background:var(--olive-100);color:var(--olive-700);margin-bottom:.8rem}.metric-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.metric-value{font-size:1.65rem;font-weight:900;color:var(--olive-800);line-height:1}.metric-label{color:var(--muted);font-size:.8rem;font-weight:700;margin-top:.25rem}
.dash-grid{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:1.25rem;align-items:start}
.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:1.25rem;margin-bottom:1.25rem}
.panel-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem}.panel-title{display:flex;align-items:center;gap:.5rem;font-weight:900;color:var(--olive-800)}.panel-title svg{width:19px;height:19px;fill:none;stroke:var(--olive-500);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.journey{display:grid;gap:.75rem}
.journey-item{position:relative;display:grid;grid-template-columns:44px minmax(0,1fr) auto;gap:.8rem;align-items:center;background:linear-gradient(135deg,var(--olive-50),#fff);border:1px solid var(--border);border-radius:var(--r-md);padding:.8rem}
.journey-number{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:var(--gold-100);color:var(--olive-800);font-weight:900}
.journey-main strong{display:block;color:var(--olive-800);font-size:.95rem}.journey-main span{display:block;color:var(--muted);font-size:.78rem;margin-top:.12rem}.journey-date{font-size:.76rem;color:var(--muted);font-weight:800;text-align:left}
.halqa-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.85rem}
.halqa-card{background:linear-gradient(180deg,#fff,var(--olive-50));border:1px solid var(--border);border-radius:var(--r-lg);padding:1rem;position:relative;overflow:hidden}
.halqa-card::before{content:'';position:absolute;top:0;right:0;left:0;height:4px;background:linear-gradient(90deg,var(--gold-500),var(--olive-500))}
.halqa-card h3{color:var(--olive-800);font-size:1rem;margin-bottom:.25rem}.halqa-card p{color:var(--muted);font-size:.8rem}.halqa-meta{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.85rem}.halqa-meta span{background:#fff;border:1px solid var(--border);border-radius:999px;padding:.25rem .55rem;font-size:.74rem;color:var(--olive-700);font-weight:800}
.side-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:1.15rem;margin-bottom:1rem}
.attendance-ring{width:150px;height:150px;border-radius:50%;display:grid;place-items:center;margin:0 auto 1rem;background:conic-gradient(var(--olive-500) <?= $attRate ?>%,var(--border) 0);position:relative}.attendance-ring::before{content:'';position:absolute;inset:13px;background:#fff;border-radius:50%}.attendance-ring strong{position:relative;color:var(--olive-800);font-size:1.6rem}
.badge-glow{text-align:center}.badge-glow .medal{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;margin:0 auto .65rem;background:linear-gradient(135deg,var(--gold-100),#fff);border:1px solid var(--gold-300);font-size:1.9rem}.badge-glow h3{font-size:1rem;color:var(--olive-800)}.badge-glow p{font-size:.78rem;color:var(--muted);margin-top:.2rem}
.notif-mini{display:grid;gap:.65rem}.notif-pill{border:1px solid var(--border);background:var(--olive-50);border-radius:var(--r-md);padding:.75rem}.notif-pill strong{display:block;color:var(--olive-800);font-size:.86rem}.notif-pill span{display:block;color:var(--muted);font-size:.74rem;margin-top:.15rem}
@media(max-width:1150px){.dash-hero,.dash-grid{grid-template-columns:1fr}.today-card{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.today-title{grid-column:1/-1}.metric-strip{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:720px){.hero-content{grid-template-columns:1fr}.level-orbit{width:170px;height:170px}.metric-strip,.today-card{grid-template-columns:1fr}.journey-item{grid-template-columns:40px minmax(0,1fr)}.journey-date{grid-column:2;text-align:right}.hero-title{font-size:1.55rem}}
</style>

<main class="pw">
  <header class="ph anim">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        لوحة المتابعة
      </h1>
      <p>مرحبًا <?= htmlspecialchars($user['name']) ?>، اجعل للقرآن مكانًا ثابتًا في يومك.</p>
    </div>
    <button class="notif-btn" onclick="openNotif()" aria-label="الإشعارات">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($notifs>0): ?><span class="notif-dot" id="notifDot"><?= $notifs ?></span><?php endif; ?>
    </button>
  </header>

  <section class="dash-hero">
    <div class="hero-card anim anim-d1">
      <div class="hero-content">
        <div>
          <span class="hero-kicker">رحلتك مستمرة</span>
          <h2 class="hero-title">كل آية تحفظها اليوم… ترفعك درجة عند الله وفي نفسك</h2>
          <p class="hero-copy">ابدأ من المصحف أو راجع خطة الحفظ، وتابع آخر تسميع حتى تعرف خطوتك التالية بوضوح.</p>
          <div class="hero-actions">
            <a href="/rattil/student/quran.php" class="btn btn-gold">
              <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
              فتح المصحف
            </a>
            <a href="/rattil/student/schedule.php" class="btn btn-secondary">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              خطة الحفظ
            </a>
          </div>
        </div>
        <div class="level-orbit" aria-label="تقدم المستوى">
          <div class="level-orbit-inner">
            <small>المستوى</small>
            <strong><?= (int)$pts['level'] ?></strong>
            <span><?= $progress ?>% من الهدف</span>
          </div>
        </div>
      </div>
    </div>

    <aside class="today-card anim anim-d2">
      <div class="today-title">
        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6.253v13"/><path d="M12 6.253C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/><path d="M12 6.253C13.168 5.477 14.754 5 16.5 5s3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18s-3.332.477-4.5 1.253"/></svg>
        نبض اليوم
      </div>
      <div class="today-chip"><span>آخر تسميع</span><strong><?= $lastRec ? 'سورة '.htmlspecialchars($lastRec['surah_name']) : 'لا يوجد بعد' ?></strong></div>
      <div class="today-chip"><span>النقاط المتبقية</span><strong><?= $nextLevel < 9999 ? number_format($remaining).' نقطة' : 'قمة المستوى' ?></strong></div>
      <div class="today-chip"><span>آخر وسام</span><strong><?= $lastBadge ? htmlspecialchars(removeEmojis($lastBadge['name'])) : 'بانتظار أول وسام' ?></strong></div>
    </aside>
  </section>

  <section class="metric-strip">
    <div class="metric-card anim anim-d1">
      <div class="metric-icon"><svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
      <div class="metric-value"><?= $surahCount ?></div><div class="metric-label">سور في الحديقة</div>
    </div>
    <div class="metric-card anim anim-d2">
      <div class="metric-icon"><svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg></div>
      <div class="metric-value"><?= $attRate ?>%</div><div class="metric-label">انتظام الحضور</div>
    </div>
    <div class="metric-card anim anim-d3">
      <div class="metric-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
      <div class="metric-value"><?= number_format($pts['points']) ?></div><div class="metric-label">نقاط التميز</div>
    </div>
    <div class="metric-card anim anim-d4">
      <div class="metric-icon"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
      <div class="metric-value"><?= count($halaqat) ?></div><div class="metric-label">حلقات نشطة</div>
    </div>
  </section>

  <section class="dash-grid">
    <div>
      <div class="panel anim anim-d1">
        <div class="panel-head">
          <div class="panel-title">
            <svg viewBox="0 0 24 24"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-6"/></svg>
            خطوتك القادمة
          </div>
          <a href="/rattil/student/schedule.php" class="btn btn-ghost btn-sm">عرض الخطة</a>
        </div>
        <div class="journey">
          <?php if(empty($nextPlans)): ?>
            <div class="journey-item">
              <div class="journey-number">1</div>
              <div class="journey-main"><strong>لا توجد خطة حالية</strong><span>اطلب من معلمك إعداد مسار حفظ مناسب لك.</span></div>
            </div>
          <?php else: foreach($nextPlans as $i => $plan):
            $diff = $plan['target_date'] ? (int)ceil((strtotime($plan['target_date']) - time()) / 86400) : null;
          ?>
            <div class="journey-item">
              <div class="journey-number"><?= $i + 1 ?></div>
              <div class="journey-main">
                <strong>سورة <?= htmlspecialchars($plan['surah_name']) ?>، الآيات <?= (int)$plan['ayah_from'] ?>-<?= (int)$plan['ayah_to'] ?></strong>
                <span><?= $plan['status'] === 'in_progress' ? 'قيد الحفظ الآن' : 'مقطع قادم في الخطة' ?></span>
              </div>
              <div class="journey-date">
                <?= $diff === null ? 'بدون تاريخ' : ($diff > 0 ? "بعد $diff يوم" : ($diff === 0 ? 'اليوم' : 'متأخر '.abs($diff).' يوم')) ?>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="panel anim anim-d2">
        <div class="panel-head">
          <div class="panel-title">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            حلقاتك النشطة
          </div>
          <a href="/rattil/student/halaqat.php" class="btn btn-ghost btn-sm">كل الحلقات</a>
        </div>
        <div class="halqa-cards">
          <?php if(empty($halaqat)): ?>
            <p style="color:var(--muted);font-size:.9rem">لم يتم تسجيلك في حلقة نشطة بعد.</p>
          <?php else: foreach($halaqat as $h): ?>
            <article class="halqa-card">
              <h3><?= htmlspecialchars($h['name']) ?></h3>
              <p><?= htmlspecialchars($h['location'] ?? 'مكان غير محدد') ?></p>
              <div class="halqa-meta">
                <span><?= htmlspecialchars($h['schedule_days'] ?? 'أيام غير محددة') ?></span>
                <span><?= $h['schedule_time'] ? substr($h['schedule_time'],0,5) : 'وقت غير محدد' ?></span>
              </div>
            </article>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <aside>
      <div class="side-card anim">
        <div class="panel-title" style="justify-content:center;margin-bottom:.7rem">
          <svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="10"/></svg>
          الحضور
        </div>
        <div class="attendance-ring"><strong><?= $attRate ?>%</strong></div>
        <p style="text-align:center;color:var(--muted);font-size:.84rem"><?= $attPresent ?> حضور من أصل <?= $attTotal ?></p>
      </div>

      <div class="side-card badge-glow anim anim-d1">
        <div class="medal"><?= $lastBadge ? htmlspecialchars($lastBadge['icon']) : '★' ?></div>
        <h3><?= $lastBadge ? htmlspecialchars(removeEmojis($lastBadge['name'])) : 'وسامك القادم قريب' ?></h3>
        <p><?= $lastBadge ? 'آخر إنجاز حصلت عليه' : 'استمر في الحفظ والمراجعة' ?></p>
      </div>

      <div class="side-card anim anim-d2">
        <div class="panel-head" style="margin-bottom:.75rem">
          <div class="panel-title">
            <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
            المستجدات
          </div>
        </div>
        <div class="notif-mini">
          <?php if(empty($recentNotifs)): ?>
            <p style="color:var(--muted);font-size:.86rem;text-align:center;padding:.8rem 0">لا توجد إشعارات جديدة.</p>
          <?php else: foreach($recentNotifs as $n): ?>
            <div class="notif-pill">
              <strong><?= htmlspecialchars(removeEmojis($n['title'])) ?></strong>
              <span><?= date('d/m H:i', strtotime($n['created_at'])) ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </aside>
  </section>
</main>

<?php
require_once __DIR__ . '/../admin/rattil_shared.php';
rattil_notif_panel($notifs);
?>

<script>
async function markAllRead(){
  try{
    await fetch('/rattil/api/notifications.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=mark_all_read'});
    const dot=document.getElementById('notifDot');
    if(dot)dot.style.display='none';
  }catch(e){}
}
</script>
</body>
</html>
