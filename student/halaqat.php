<?php
require_once __DIR__ . '/../config/db.php';
requireRole('student');

$user = getCurrentUser($pdo);
$notifs = getUnreadCount($pdo);
$sid = (int)$_SESSION['user_id'];

require_once __DIR__ . '/student_shared.php';

$stmt = $pdo->prepare("
    SELECT h.*, u.name AS teacher_name, u.phone AS teacher_phone,
           (
               SELECT COUNT(*)
               FROM halqa_students hs2
               WHERE hs2.halqa_id = h.id
           ) AS student_count
    FROM halaqat h
    JOIN halqa_students hs ON h.id = hs.halqa_id AND hs.student_id = ?
    LEFT JOIN users u ON h.teacher_id = u.id
    ORDER BY h.schedule_time
");
$stmt->execute([$sid]);
$halaqat = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM memorization_plan WHERE student_id=? ORDER BY target_date ASC");
$stmt->execute([$sid]);
$plans = $stmt->fetchAll();

$plansByHalaqa = [];
foreach ($plans as $p) {
    $plansByHalaqa[$p['halqa_id']][] = $p;
}

$activeCount = count(array_filter($halaqat, fn($h) => $h['status'] === 'active'));
$nextSession = null;
foreach ($halaqat as $h) {
    if (!empty($h['schedule_time'])) {
        $nextSession = $h;
        break;
    }
}

student_page_start('حلقاتي');
student_sidebar($user, 'halaqat', $notifs);
?>

<style>
.halaqat-hero{position:relative;overflow:hidden;border-radius:var(--r-lg);background:linear-gradient(135deg,var(--olive-900),var(--olive-700));color:#fff;box-shadow:var(--sh-md);padding:1.6rem;margin-bottom:1.25rem;display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:1.25rem}
.halaqat-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 12% 20%,rgba(201,168,76,.25),transparent 28%),radial-gradient(circle at 85% 80%,rgba(255,255,255,.12),transparent 28%)}
.halaqat-hero>*{position:relative;z-index:1}.hero-badge{display:inline-flex;align-items:center;gap:.45rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);border-radius:999px;padding:.35rem .75rem;color:var(--gold-300);font-weight:900;font-size:.78rem}
.halaqat-hero h2{font-size:2rem;line-height:1.25;color:#fff;margin:.85rem 0 .4rem;font-weight:900}.halaqat-hero p{color:rgba(255,255,255,.72);font-size:.95rem;max-width:640px}
.hero-mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}.hero-mini{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.12);border-radius:var(--r-md);padding:.85rem}.hero-mini span{display:block;color:rgba(255,255,255,.68);font-size:.75rem}.hero-mini strong{display:block;color:#fff;font-size:1.25rem;margin-top:.15rem}
.halaqa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:1rem}
.halaqa-card{position:relative;overflow:hidden;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:1.2rem;min-height:350px;display:flex;flex-direction:column}
.halaqa-card::before{content:'';position:absolute;top:0;right:0;left:0;height:5px;background:linear-gradient(90deg,var(--gold-500),var(--olive-500))}
.halaqa-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;margin-bottom:1rem}.halaqa-title{display:flex;gap:.8rem;align-items:center}.halaqa-icon{width:54px;height:54px;border-radius:var(--r-lg);background:linear-gradient(135deg,var(--olive-100),var(--gold-100));display:grid;place-items:center;color:var(--olive-700);flex-shrink:0}.halaqa-icon svg{width:26px;height:26px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.halaqa-title h3{color:var(--olive-800);font-size:1.12rem;margin-bottom:.15rem}.halaqa-title p{color:var(--muted);font-size:.82rem}
.status-dot{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.3rem .65rem;font-size:.76rem;font-weight:900}.status-dot.active{background:var(--success-bg);color:var(--success)}.status-dot.inactive{background:var(--danger-bg);color:var(--danger)}
.info-mosaic{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-bottom:1rem}.info-tile{background:var(--olive-50);border:1px solid var(--border);border-radius:var(--r-md);padding:.78rem}.info-tile span{display:block;color:var(--muted);font-size:.72rem;font-weight:800;margin-bottom:.18rem}.info-tile strong{display:block;color:var(--olive-800);font-size:.88rem;line-height:1.4}
.capacity{margin:.2rem 0 1rem}.capacity-line{height:8px;border-radius:999px;background:var(--border);overflow:hidden}.capacity-line div{height:100%;border-radius:999px;background:linear-gradient(90deg,var(--olive-500),var(--gold-500))}.capacity small{display:flex;justify-content:space-between;margin-top:.35rem;color:var(--muted);font-weight:800;font-size:.74rem}
.mini-plan{background:linear-gradient(135deg,#fff,var(--olive-50));border:1px solid var(--border);border-radius:var(--r-lg);padding:.9rem;margin-top:auto}.mini-plan-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:.7rem;color:var(--olive-800);font-weight:900}
.plan-chip{display:flex;align-items:center;gap:.6rem;padding:.6rem .65rem;background:#fff;border:1px solid var(--border);border-radius:var(--r-md);margin-bottom:.5rem}.plan-chip:last-child{margin-bottom:0}.plan-state{width:10px;height:10px;border-radius:50%;background:var(--muted);flex-shrink:0}.plan-state.in_progress{background:var(--gold-500)}.plan-state.completed{background:var(--success)}.plan-chip strong{display:block;color:var(--olive-800);font-size:.84rem}.plan-chip span{display:block;color:var(--muted);font-size:.73rem}
.halaqa-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin-top:1rem}.empty-state{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:3rem 2rem;text-align:center}.empty-state .empty-icon{width:76px;height:76px;border-radius:50%;margin:0 auto 1rem;display:grid;place-items:center;background:var(--olive-100);color:var(--olive-700)}
.modal textarea{min-height:130px}
@media(max-width:980px){.halaqat-hero{grid-template-columns:1fr}.hero-mini-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:650px){.hero-mini-grid,.info-mosaic{grid-template-columns:1fr}.halaqa-grid{grid-template-columns:1fr}.halaqat-hero h2{font-size:1.5rem}}
</style>

<main class="pw">
  <header class="ph anim">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        حلقاتي
      </h1>
      <p>مساحتك مع المعلم، المواعيد، وخطة الحفظ لكل حلقة.</p>
    </div>
    <button class="notif-btn" onclick="openNotif()" aria-label="الإشعارات">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($notifs>0): ?><span class="notif-dot"><?= $notifs ?></span><?php endif; ?>
    </button>
  </header>

  <section class="halaqat-hero anim">
    <div>
      <span class="hero-badge">مجلس الحفظ</span>
      <h2>حافظ على موعدك مع القرآن… يحافظ القرآن على قلبك</h2>
      <p>تابع أين تحفظ، متى تحضر، ومن هو معلمك، وافتح خطة كل حلقة بدون ازدحام.</p>
    </div>
    <div class="hero-mini-grid">
      <div class="hero-mini"><span>الحلقات</span><strong><?= count($halaqat) ?></strong></div>
      <div class="hero-mini"><span>النشطة</span><strong><?= $activeCount ?></strong></div>
      <div class="hero-mini"><span>أقرب وقت</span><strong><?= $nextSession && $nextSession['schedule_time'] ? substr($nextSession['schedule_time'],0,5) : '-' ?></strong></div>
      <div class="hero-mini"><span>المقاطع</span><strong><?= count($plans) ?></strong></div>
    </div>
  </section>

  <?php if(empty($halaqat)): ?>
    <div class="empty-state anim">
      <div class="empty-icon">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      </div>
      <h3 style="color:var(--olive-800);margin-bottom:.35rem">لم تنضم لأي حلقة بعد</h3>
      <p style="color:var(--muted);font-size:.9rem">تواصل مع الإدارة ليتم تسجيلك في حلقة قرآنية مناسبة.</p>
    </div>
  <?php else: ?>
    <section class="halaqa-grid">
      <?php foreach($halaqat as $idx => $h):
        $capacityPct = $h['max_students'] > 0 ? min(100, round($h['student_count'] / $h['max_students'] * 100)) : 0;
        $plansForHalqa = $plansByHalaqa[$h['id']] ?? [];
      ?>
        <article class="halaqa-card anim anim-d<?= min(5, $idx + 1) ?>">
          <div class="halaqa-top">
            <div class="halaqa-title">
              <div class="halaqa-icon">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
              </div>
              <div>
                <h3><?= htmlspecialchars($h['name']) ?></h3>
                <p>المعلم: <?= htmlspecialchars($h['teacher_name'] ?? 'غير محدد') ?></p>
              </div>
            </div>
            <span class="status-dot <?= $h['status']==='active' ? 'active' : 'inactive' ?>"><?= $h['status']==='active' ? 'نشطة' : 'معلقة' ?></span>
          </div>

          <div class="info-mosaic">
            <div class="info-tile"><span>الأيام</span><strong><?= htmlspecialchars($h['schedule_days'] ?? '-') ?></strong></div>
            <div class="info-tile"><span>الوقت</span><strong><?= $h['schedule_time'] ? substr($h['schedule_time'],0,5) : '-' ?></strong></div>
            <div class="info-tile"><span>المكان</span><strong><?= htmlspecialchars($h['location'] ?? '-') ?></strong></div>
            <div class="info-tile"><span>هاتف المعلم</span><strong><?= htmlspecialchars($h['teacher_phone'] ?? '-') ?></strong></div>
          </div>

          <div class="capacity">
            <div class="capacity-line"><div style="width:<?= $capacityPct ?>%"></div></div>
            <small><span>الطلاب في الحلقة</span><span><?= (int)$h['student_count'] ?> من <?= (int)$h['max_students'] ?></span></small>
          </div>

          <div class="mini-plan">
            <div class="mini-plan-head">
              <span>ملخص خطة الحفظ</span>
              <span><?= count($plansForHalqa) ?> مقطع</span>
            </div>
            <?php if(empty($plansForHalqa)): ?>
              <p style="color:var(--muted);font-size:.84rem;text-align:center;padding:.55rem 0">لم يضع المعلم خطة لهذه الحلقة بعد.</p>
            <?php else: foreach(array_slice($plansForHalqa, 0, 3) as $p): ?>
              <div class="plan-chip">
                <span class="plan-state <?= htmlspecialchars($p['status']) ?>"></span>
                <div>
                  <strong>سورة <?= htmlspecialchars($p['surah_name']) ?></strong>
                  <span>آية <?= (int)$p['ayah_from'] ?>-<?= (int)$p['ayah_to'] ?><?= $p['target_date'] ? ' · '.date('d/m', strtotime($p['target_date'])) : '' ?></span>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <div class="halaqa-actions">
            <?php if($h['teacher_id']): ?>
              <button class="btn btn-primary" onclick="openMsgModal(<?= (int)$h['teacher_id'] ?>,'<?= htmlspecialchars($h['teacher_name'] ?? '', ENT_QUOTES) ?>')">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                تواصل مع المعلم
              </button>
            <?php endif; ?>
            <a class="btn btn-secondary" href="/rattil/student/schedule.php">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              الخطة كاملة
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

<div class="overlay show" id="msgOverlay" style="display:none;" onclick="closeMsgModal()"></div>
<div class="modal" id="msgModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3>
        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        رسالة إلى <span id="teacherName"></span>
      </h3>
      <button class="modal-close" onclick="closeMsgModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="fg">
        <label class="fl">محتوى الرسالة</label>
        <textarea id="msgContent" class="fc" rows="4" placeholder="اكتب رسالتك هنا..."></textarea>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeMsgModal()">إلغاء</button>
      <button class="btn btn-primary" id="sendBtn" onclick="sendMessage()">
        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        إرسال
      </button>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/../admin/rattil_shared.php';
rattil_notif_panel($notifs);
?>

<script>
let currentTeacherId = null;

function openMsgModal(tid, tname) {
  currentTeacherId = tid;
  document.getElementById('teacherName').textContent = tname;
  document.getElementById('msgContent').value = '';
  document.getElementById('msgModal').classList.add('show');
  document.getElementById('msgOverlay').style.display = 'block';
  document.getElementById('msgOverlay').classList.add('show');
}

function closeMsgModal() {
  document.getElementById('msgModal').classList.remove('show');
  document.getElementById('msgOverlay').classList.remove('show');
  setTimeout(() => document.getElementById('msgOverlay').style.display = 'none', 250);
}

async function sendMessage() {
  const content = document.getElementById('msgContent').value.trim();
  if (!content) return;
  const btn = document.getElementById('sendBtn');
  btn.classList.add('loading');
  try {
    const r = await fetch('/rattil/api/messages.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({action:'send', receiver_id:currentTeacherId, content})
    });
    const d = await r.json();
    if (d.success) closeMsgModal();
  } catch(e) {}
  btn.classList.remove('loading');
}
</script>
</body>
</html>
