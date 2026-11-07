<?php
require_once __DIR__ . '/../config/db.php';
requireRole('teacher');

$user = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$tid = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, h.name AS halqa_name
    FROM users u
    JOIN halqa_students hs ON hs.student_id = u.id
    JOIN halaqat h ON h.id = hs.halqa_id
    WHERE h.teacher_id = ?
    ORDER BY u.name
");
$stmt->execute([$tid]);
$students = $stmt->fetchAll();
$initialStudent = (int)($_GET['student_id'] ?? ($students[0]['id'] ?? 0));
$initialSurah = (int)($_GET['surah'] ?? 114);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>مصحف التتبع - رتّل</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;600;700;800&family=Scheherazade+New:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--primary:#1C3329;--primary-light:#2C4A3E;--primary-soft:#EAECE6;--gold:#D4AF37;--gold-dark:#B8860B;--cream:#FAF7F0;--beige-card:#F4EFE6;--beige-hover:#EFE9DC;--white:#fff;--gray-dark:#2F3633;--gray:#707A75;--gray-light:#EFEFEA;--radius-lg:20px;--radius-md:14px;--radius-sm:9px;--shadow-sm:0 4px 12px rgba(28,51,41,.04);--shadow-md:0 12px 35px rgba(28,51,41,.08);--transition:all .25s ease}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Tajawal',sans-serif;background:var(--cream);color:var(--gray-dark);min-height:100vh;display:flex;overflow-x:hidden}
a{text-decoration:none;color:inherit}
.sidebar{width:280px;background:linear-gradient(180deg,var(--primary) 0%,#0F1D17 100%);min-height:100vh;position:fixed;right:0;top:0;z-index:100;display:flex;flex-direction:column;box-shadow:-4px 0 30px rgba(0,0,0,.15)}
.sidebar-logo{padding:32px 24px;border-bottom:1px solid rgba(255,255,255,.06);text-align:center}
.logo-text{font-family:'Amiri',serif;font-size:2.3rem;color:var(--gold);line-height:1;font-weight:700}
.logo-sub{font-size:.82rem;color:rgba(255,255,255,.5);margin-top:6px}
.sidebar-user{padding:22px 24px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;gap:14px;background:rgba(255,255,255,.02)}
.avatar-circle{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#f3e7c4,var(--gold));display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--primary);flex-shrink:0}
.sidebar-user .name{color:#fff;font-weight:700}.sidebar-user .role{color:var(--gold);font-size:.8rem;margin-top:2px}
.sidebar-nav{flex:1;padding:20px 0;overflow:auto}.nav-section{padding:12px 24px 6px;font-size:.74rem;color:rgba(255,255,255,.35);font-weight:800}
.nav-link{display:flex;align-items:center;gap:14px;padding:12px 24px;color:rgba(255,255,255,.72);transition:var(--transition);font-size:.95rem;position:relative}
.nav-link svg{width:20px;height:20px;opacity:.75}.nav-link:hover,.nav-link.active{background:rgba(255,255,255,.055);color:#fff}.nav-link.active{color:var(--gold)}
.nav-link.active::before{content:'';position:absolute;right:0;top:8px;bottom:8px;width:4px;background:var(--gold);border-radius:4px 0 0 4px}
.badge-count{margin-right:auto;background:var(--gold);color:var(--primary);border-radius:20px;padding:2px 8px;font-size:.75rem;font-weight:800}
.sidebar-footer{padding:20px 24px;border-top:1px solid rgba(255,255,255,.06)}.logout-btn{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.55);font-size:.9rem}
.main{margin-right:280px;flex:1;min-height:100vh;width:calc(100% - 280px);display:flex;flex-direction:column}
.topbar{background:rgba(250,247,240,.88);backdrop-filter:blur(12px);padding:16px 32px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(44,74,62,.1);position:sticky;top:0;z-index:50}
.topbar-title{font-family:'Amiri',serif;font-size:1.6rem;color:var(--primary);font-weight:700}.greeting-chip{background:rgba(44,74,62,.06);padding:8px 16px;border-radius:30px;font-size:.9rem;font-weight:700;color:var(--primary)}
.content{padding:30px;max-width:1400px;width:100%;margin:0 auto;display:grid;grid-template-columns:320px minmax(0,1fr);gap:22px;align-items:start}
.card{background:var(--beige-card);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);border:1px solid rgba(44,74,62,.08);overflow:hidden}
.card-header{padding:20px 22px;border-bottom:1px solid rgba(44,74,62,.1);background:linear-gradient(90deg,rgba(44,74,62,.05),rgba(255,255,255,0))}
.card-title{font-family:'Amiri',serif;font-size:1.35rem;color:var(--primary);font-weight:700}
.card-body{padding:20px}
.form-group{margin-bottom:16px}.form-group label{display:block;margin-bottom:6px;color:var(--primary);font-weight:800;font-size:.88rem}
.form-control{width:100%;border:1px solid rgba(44,74,62,.2);border-radius:var(--radius-sm);background:#fff;padding:11px 13px;font-family:inherit;color:var(--gray-dark);outline:none}
.form-control:focus{border-color:var(--primary-light);box-shadow:0 0 0 3px rgba(44,74,62,.1)}
.legend{display:grid;gap:10px;margin-top:14px}.legend-item{display:flex;align-items:center;gap:9px;font-weight:700;color:var(--gray);font-size:.88rem}.legend-dot{width:15px;height:15px;border-radius:5px;display:inline-block}
.summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:16px}.summary-box{background:#fff;border:1px solid rgba(44,74,62,.08);border-radius:var(--radius-md);padding:13px;text-align:center}.summary-box strong{display:block;color:var(--primary);font-size:1.35rem}.summary-box span{font-size:.78rem;color:var(--gray);font-weight:700}
.quran-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:18px 22px;border-bottom:1px solid rgba(44,74,62,.1);background:#fff}
.mode-badge{padding:7px 13px;border-radius:999px;background:var(--primary-soft);color:var(--primary);font-weight:800;font-size:.86rem}.muted{color:var(--gray);font-size:.9rem}
.quran-display{background:#fff;padding:32px;min-height:500px}
.surah-header{text-align:center;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid rgba(44,74,62,.1)}
.surah-header h2{font-family:'Scheherazade New',serif;font-size:2.25rem;color:var(--primary)}.surah-header p{color:var(--gray);font-size:.9rem;margin-top:5px}
.basmalah{font-family:'Scheherazade New',serif;text-align:center;color:var(--primary);font-size:2rem;margin:10px 0 22px}
.ayahs-container{font-family:'Scheherazade New',serif;font-size:1.68rem;line-height:2.85;text-align:justify;color:#1a1a1a;word-spacing:3px}
.ayah-span{display:inline;border-radius:5px;padding:1px 4px}.ayah-span.memorized{background:rgba(34,197,94,.17);box-shadow:inset 0 -2px #22c55e}.ayah-span.reviewing{background:rgba(234,179,8,.18);box-shadow:inset 0 -2px #eab308}.ayah-span.last_stop{background:rgba(59,130,246,.18);box-shadow:inset 0 -2px #3b82f6}
.ayah-num{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:var(--primary-soft);border:1px solid rgba(44,74,62,.18);color:var(--primary);font-family:'Tajawal',sans-serif;font-size:.74rem;font-weight:800;margin:0 .25rem;vertical-align:middle}
.empty,.loading{text-align:center;color:var(--gray);padding:70px 20px;font-weight:700}.spinner{display:inline-block;width:28px;height:28px;border:3px solid rgba(44,74,62,.15);border-top-color:var(--primary);border-radius:50%;animation:spin .7s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
.hamburger{display:none;background:var(--primary);color:#fff;border:0;border-radius:10px;padding:8px 11px;font-size:1.1rem}
@media(max-width:1050px){.content{grid-template-columns:1fr}.sidebar{transform:translateX(100%);transition:var(--transition)}.sidebar.open{transform:translateX(0)}.main{margin-right:0;width:100%}.hamburger{display:inline-block}}
@media(max-width:650px){.content{padding:18px}.quran-display{padding:22px 16px}.ayahs-container{font-size:1.35rem;line-height:2.65}.topbar{padding:14px 18px}.basmalah{font-size:1.55rem}.surah-header h2{font-size:1.8rem}}
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-text">رتّل</div>
    <div class="logo-sub">منصة إدارة الحلقات القرآنية</div>
  </div>
  <div class="sidebar-user">
    <div class="avatar-circle"><?= mb_substr($user['name'] ?? 'م', 0, 1, 'UTF-8') ?></div>
    <div><div class="name"><?= htmlspecialchars($user['name'] ?? '') ?></div><div class="role">معلم</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">الرئيسية</div>
    <a class="nav-link" href="/rattil/teacher/dashboard.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Zm10 0a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V6ZM4 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2Zm10 0a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2Z"/></svg>لوحة التحكم</a>
    <div class="nav-section">الإدارة التعليمية</div>
    <a class="nav-link" href="/rattil/teacher/quran.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path stroke-width="2" d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>المصحف</a>
    <a class="nav-link active" href="/rattil/teacher/quran_tracking.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M9 11l3 3L22 4"/><path stroke-width="2" d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>مصحف التتبع</a>
    <a class="nav-link" href="/rattil/teacher/halaqat.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4z"/></svg>الحلقات</a>
    <a class="nav-link" href="/rattil/teacher/students.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 4a4 4 0 1 1 0 8 4 4 0 0 1 0-8Zm-8 17a8 8 0 0 1 16 0"/></svg>الطلاب</a>
    <a class="nav-link" href="/rattil/teacher/recitation_log.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path stroke-width="2" d="M14 2v6h6"/></svg>سجل التسميع</a>
    <a class="nav-link" href="/rattil/teacher/attendance.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/></svg>الحضور</a>
    <a class="nav-link" href="/rattil/teacher/messages.php"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M21 12c0 4.4-4 8-9 8a10 10 0 0 1-4-.8L3 20l1.4-3.6A7.5 7.5 0 0 1 3 12c0-4.4 4-8 9-8s9 3.6 9 8Z"/></svg>الرسائل<?php if($unread>0): ?><span class="badge-count"><?= $unread ?></span><?php endif; ?></a>
  </nav>
  <div class="sidebar-footer"><a class="logout-btn" href="/rattil/logout.php">تسجيل الخروج</a></div>
</aside>

<main class="main">
  <div class="topbar">
    <button class="hamburger" type="button" onclick="toggleSidebar()">☰</button>
    <div class="topbar-title">مصحف تتبع الطالب</div>
    <div class="greeting-chip">قراءة متابعة فقط</div>
  </div>

  <div class="content">
    <aside class="card">
      <div class="card-header"><div class="card-title">اختيار العرض</div></div>
      <div class="card-body">
        <div class="form-group">
          <label for="studentSelect">الطالب</label>
          <select class="form-control" id="studentSelect" onchange="TrackingApp.loadCurrent()">
            <?php foreach($students as $student): ?>
              <option value="<?= (int)$student['id'] ?>" <?= $initialStudent === (int)$student['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($student['name']) ?> - <?= htmlspecialchars($student['halqa_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="surahSelect">السورة</label>
          <select class="form-control" id="surahSelect" onchange="TrackingApp.loadCurrent()">
            <option value="">جاري تحميل السور...</option>
          </select>
        </div>

        <div class="summary-grid">
          <div class="summary-box"><strong id="memCount">0</strong><span>محفوظ</span></div>
          <div class="summary-box"><strong id="revCount">0</strong><span>مراجعة</span></div>
          <div class="summary-box"><strong id="stopCount">0</strong><span>آخر موضع</span></div>
          <div class="summary-box"><strong id="totalMarked">0</strong><span>معلّم</span></div>
        </div>

        <div class="legend">
          <div class="legend-item"><span class="legend-dot" style="background:rgba(34,197,94,.35);border:1px solid #22c55e"></span>محفوظ</div>
          <div class="legend-item"><span class="legend-dot" style="background:rgba(234,179,8,.35);border:1px solid #eab308"></span>مراجعة</div>
          <div class="legend-item"><span class="legend-dot" style="background:rgba(59,130,246,.35);border:1px solid #3b82f6"></span>آخر موضع</div>
        </div>
      </div>
    </aside>

    <section class="card">
      <div class="quran-toolbar">
        <span class="mode-badge" id="studentBadge">اختر طالبًا</span>
        <span class="muted" id="surahBadge">اختر سورة</span>
      </div>
      <div class="quran-display" id="quranDisplay">
        <div class="empty">اختر الطالب والسورة لعرض مصحف التتبع.</div>
      </div>
    </section>
  </div>
</main>

<script>
const INITIAL_SURAH = <?= max(1, min(114, $initialSurah)) ?>;

const TrackingApp = {
  surahs: [],
  marks: {},

  async init() {
    await this.loadSurahList();
    if (document.getElementById('studentSelect').value) {
      await this.loadCurrent();
    }
  },

  async loadSurahList() {
    const r = await fetch('/rattil/api/quran_text.php?action=surah_list');
    const d = await r.json();
    if (!d.success) throw new Error(d.error || 'تعذر تحميل السور');
    this.surahs = d.data;
    const select = document.getElementById('surahSelect');
    select.innerHTML = this.surahs.map(s =>
      `<option value="${s.number}" ${s.number === INITIAL_SURAH ? 'selected' : ''}>${s.number}. ${s.name}</option>`
    ).join('');
  },

  async loadCurrent() {
    const studentSelect = document.getElementById('studentSelect');
    const surahSelect = document.getElementById('surahSelect');
    const studentId = Number(studentSelect.value || 0);
    const surahNum = Number(surahSelect.value || 0);
    if (!studentId || !surahNum) return;

    document.getElementById('studentBadge').textContent = `الطالب: ${studentSelect.options[studentSelect.selectedIndex].text}`;
    document.getElementById('surahBadge').textContent = `السورة: ${surahSelect.options[surahSelect.selectedIndex].text}`;
    document.getElementById('quranDisplay').innerHTML = '<div class="loading"><span class="spinner"></span><p style="margin-top:12px">جاري تحميل مصحف التتبع...</p></div>';

    const [sr, mr] = await Promise.all([
      fetch(`/rattil/api/quran_text.php?action=surah&num=${surahNum}`),
      fetch(`/rattil/api/save_tracking.php?action=list&student_id=${studentId}&surah=${surahNum}`)
    ]);
    const sd = await sr.json();
    const md = await mr.json();
    if (!sd.success) throw new Error(sd.error || 'تعذر تحميل السورة');
    if (!md.success) throw new Error(md.error || 'تعذر تحميل التتبع');

    this.marks = {};
    md.data.forEach(m => this.marks[Number(m.ayah_number)] = m.status);
    this.renderSurah(sd.data);
    this.updateSummary();
  },

  renderSurah(surah) {
    const basmalah = surah.number !== 1 && surah.number !== 9
      ? '<div class="basmalah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>'
      : '';

    const ayahs = surah.ayahs.map(a => {
      const status = this.marks[a.numberInSurah] || '';
      return `<span class="ayah-span ${status}" title="آية ${a.numberInSurah}">${a.text}</span><span class="ayah-num">${this.toAr(a.numberInSurah)}</span> `;
    }).join('');

    document.getElementById('quranDisplay').innerHTML = `
      <div class="surah-header">
        <h2>سورة ${surah.name}</h2>
        <p>${surah.numberOfAyahs} آية · ${surah.revelationType === 'Meccan' ? 'مكية' : 'مدنية'}</p>
      </div>
      ${basmalah}
      <div class="ayahs-container">${ayahs}</div>
    `;
  },

  updateSummary() {
    const vals = Object.values(this.marks);
    document.getElementById('memCount').textContent = vals.filter(s => s === 'memorized').length;
    document.getElementById('revCount').textContent = vals.filter(s => s === 'reviewing').length;
    document.getElementById('stopCount').textContent = vals.filter(s => s === 'last_stop').length;
    document.getElementById('totalMarked').textContent = vals.length;
  },

  toAr(n) {
    return String(n).replace(/[0-9]/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
  }
};

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

document.addEventListener('DOMContentLoaded', () => {
  TrackingApp.init().catch(err => {
    document.getElementById('quranDisplay').innerHTML = `<div class="empty" style="color:#c0392b">${err.message}</div>`;
  });
});
</script>
</body>
</html>
