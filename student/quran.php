<?php
require_once __DIR__ . '/../config/db.php';
requireRole('student');

$user = getCurrentUser($pdo);
$notifs = getUnreadCount($pdo);
$sid = (int)$_SESSION['user_id'];

require_once __DIR__ . '/student_shared.php';

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

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quran_tracking WHERE student_id=? AND status='memorized'");
$stmt->execute([$sid]);
$memorizedCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM quran_tracking WHERE student_id=? AND status='reviewing'");
$stmt->execute([$sid]);
$reviewingCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_garden WHERE student_id=?");
$stmt->execute([$sid]);
$gardenCount = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>المصحف - رتّل</title>
<?php student_global_styles(); ?>
<link href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;600;700&display=swap" rel="stylesheet">
<style>
.quran-shell{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:1.25rem;align-items:start}
.quran-panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);overflow:hidden}
.quran-topbar{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.96);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.85rem 1rem;backdrop-filter:blur(10px)}
.surah-btn{display:inline-flex;align-items:center;gap:.55rem;border:1px solid var(--border-2);background:var(--olive-50);color:var(--olive-800);border-radius:var(--r-md);padding:.55rem .9rem;font-weight:800;cursor:pointer}
.surah-meta{display:flex;align-items:center;gap:.6rem;color:var(--muted);font-size:.86rem;flex-wrap:wrap}
.quran-actions{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}
.quran-select{border:1px solid var(--border-2);border-radius:var(--r-sm);background:#fff;color:var(--text);padding:.52rem .75rem;font-family:inherit}
.quran-reader{padding:1.75rem 2.25rem 2.5rem}
.surah-heading{text-align:center;margin-bottom:1.3rem;padding-bottom:1rem;border-bottom:1px solid var(--border)}
.surah-heading h2{font-family:'Scheherazade New',serif;color:var(--olive-800);font-size:2.25rem;line-height:1.2}
.surah-heading p{color:var(--muted);font-size:.88rem;margin-top:.35rem}
.bismillah{font-family:'Scheherazade New',serif;text-align:center;color:var(--olive-800);font-size:2rem;margin:1rem 0 1.35rem}
.quran-flow{font-family:'Scheherazade New',serif;font-size:1.75rem;line-height:2.85;text-align:justify;color:#1a1a1a;word-spacing:3px}
.ayah-span{display:inline;border-radius:var(--r-xs);padding:1px 4px;cursor:pointer;transition:background var(--dur),box-shadow var(--dur);-webkit-tap-highlight-color:transparent}
.ayah-span:hover{background:rgba(201,168,76,.16)}
.ayah-span.memorized{background:rgba(34,197,94,.16);box-shadow:inset 0 -2px #22c55e}
.ayah-span.reviewing{background:rgba(234,179,8,.18);box-shadow:inset 0 -2px #eab308}
.ayah-span.last_stop{background:rgba(59,130,246,.17);box-shadow:inset 0 -2px #3b82f6}
.ayah-span.playing{background:rgba(201,168,76,.3)!important;animation:qPulse .9s ease-in-out infinite}
@keyframes qPulse{0%,100%{opacity:1}50%{opacity:.58}}
.ayah-num{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border:1.5px solid rgba(22,51,36,.22);border-radius:50%;background:rgba(22,51,36,.04);color:var(--olive-800);font-family:'Tajawal',sans-serif;font-size:.74rem;font-weight:800;margin:0 .25rem;vertical-align:middle}
.tracking-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-xs);padding:1rem;position:sticky;top:1rem}
.tracking-title{display:flex;align-items:center;gap:.45rem;color:var(--olive-800);font-weight:800;margin-bottom:.85rem}
.tracking-grid{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}
.track-stat{background:var(--olive-50);border:1px solid var(--border);border-radius:var(--r-md);padding:.85rem;text-align:center}
.track-stat strong{display:block;color:var(--olive-800);font-size:1.35rem;line-height:1}
.track-stat span{color:var(--muted);font-size:.78rem}
.legend-list{display:grid;gap:.55rem;margin-top:1rem}
.legend-item{display:flex;align-items:center;gap:.55rem;color:var(--muted);font-size:.86rem}
.legend-dot{width:14px;height:14px;border-radius:4px;display:inline-block}
.ctx-menu{position:fixed;z-index:1000;background:#fff;border:1px solid var(--border);border-radius:var(--r-md);box-shadow:var(--sh-lg);overflow:hidden;min-width:210px;display:none}
.ctx-head{padding:.65rem .85rem;background:linear-gradient(135deg,var(--olive-800),var(--olive-600));color:#fff;font-size:.84rem;font-weight:800}
.ctx-row{display:flex;align-items:center;gap:.6rem;width:100%;border:0;background:#fff;color:var(--olive-800);padding:.7rem .9rem;font-family:inherit;font-weight:700;cursor:pointer;text-align:right;border-bottom:1px solid #f2f5f1}
.ctx-row:hover{background:var(--olive-50)}
.toast{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:var(--olive-800);color:#fff;padding:.7rem 1.2rem;border-radius:999px;box-shadow:var(--sh-md);opacity:0;pointer-events:none;transition:opacity var(--dur);z-index:1200}
.toast.show{opacity:1}
.surah-modal{position:fixed;inset:0;background:rgba(9,26,16,.35);z-index:900;display:none;align-items:flex-start;justify-content:center;padding:4.5rem 1rem 1rem}
.surah-modal.open{display:flex}
.surah-box{width:min(540px,100%);max-height:calc(100vh - 6rem);background:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-xl);overflow:hidden;display:flex;flex-direction:column}
.surah-search{padding:1rem;border-bottom:1px solid var(--border)}
.surah-search input{width:100%;border:1px solid var(--border-2);border-radius:var(--r-md);padding:.75rem 1rem;font-family:inherit;outline:none}
.surah-list{overflow:auto}
.surah-item{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-bottom:1px solid #f2f5f1;cursor:pointer}
.surah-item:hover,.surah-item.active{background:var(--olive-50)}
.surah-number{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--gold-100);color:var(--olive-800);font-weight:800;flex-shrink:0}
.surah-name{font-family:'Scheherazade New',serif;color:var(--olive-800);font-size:1.12rem;font-weight:700}
.surah-sub{color:var(--muted);font-size:.78rem}
.audio-player{position:fixed;right:var(--sidebar-w);left:0;bottom:0;height:68px;background:#fff;border-top:1px solid var(--border);box-shadow:var(--sh-md);display:none;align-items:center;gap:.9rem;padding:.7rem 1.2rem;z-index:300}
.audio-player button{border:0;border-radius:50%;cursor:pointer}
.ap-play{width:42px;height:42px;background:var(--olive-800);color:#fff;font-size:1rem}
.ap-btn{width:34px;height:34px;background:var(--olive-50);color:var(--olive-800)}
.ap-info{min-width:0;flex:1}
.ap-title{font-weight:800;color:var(--olive-800);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ap-sub{font-size:.78rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.loading-q{display:flex;align-items:center;justify-content:center;min-height:340px;color:var(--muted)}
@media(max-width:1000px){.quran-shell{grid-template-columns:1fr}.tracking-card{position:static}.audio-player{right:0}}
@media(max-width:700px){.quran-reader{padding:1.25rem 1rem 2rem}.quran-flow{font-size:1.35rem;line-height:2.6}.quran-topbar{align-items:flex-start;flex-direction:column}.quran-actions{width:100%}.quran-select{max-width:100%}.bismillah{font-size:1.55rem}.surah-heading h2{font-size:1.85rem}}
</style>
</head>
<body>
<?php student_sidebar($user, 'quran', $notifs); ?>

<main class="pw">
  <header class="ph anim">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        مصحف الطالب
      </h1>
      <p>اقرأ، استمع، وحدد حالة حفظك مباشرة من الآية.
        اليوم آية… وغدًا ختمة
      </p>
    </div>
    <button class="notif-btn" onclick="openNotif()" aria-label="الإشعارات">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($notifs>0): ?><span class="notif-dot" id="notifDot"><?= $notifs ?></span><?php endif; ?>
    </button>
  </header>

  <section class="quran-shell">
    <div class="quran-panel anim anim-d1">
      <div class="quran-topbar">
        <button class="surah-btn" type="button" onclick="openSurahModal()">
          <span id="tbName">جاري التحميل...</span>
          <span>▾</span>
        </button>
        <div class="surah-meta">
          <span id="tbMeta1">-</span>
          <span>•</span>
          <span id="tbMeta2">-</span>
        </div>
        <div class="quran-actions">
          <select class="quran-select" id="reciterSelect">
            <option value="ar.alafasy">مشاري العفاسي</option>
            <option value="ar.minshawi">المنشاوي</option>
            <option value="ar.abdurrahmanassudais">السديس</option>
            <option value="ar.husary">الحصري</option>
            <option value="ar.muhammadayyoub">محمد أيوب</option>
          </select>
          <button class="btn btn-primary btn-sm" id="playAllBtn" type="button" onclick="QApp.togglePlayAll()">استماع</button>
        </div>
      </div>

      <div id="loadingQ" class="loading-q"><div class="spinner"></div></div>
      <div class="quran-reader" id="quranReader" style="display:none">
        <div class="surah-heading">
          <h2 id="shName">-</h2>
          <p><span id="shAyahs">-</span> آية · <span id="shType">-</span></p>
        </div>
        <div class="bismillah" id="bismillah" style="display:none">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
        <div class="quran-flow" id="quranFlow"></div>
      </div>
    </div>

    <aside class="tracking-card anim anim-d2">
      <div class="tracking-title">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        متابعة الحفظ
      </div>
      <div class="tracking-grid">
        <div class="track-stat"><strong id="statMem"><?= $memorizedCount ?></strong><span>محفوظ</span></div>
        <div class="track-stat"><strong id="statRev"><?= $reviewingCount ?></strong><span>مراجعة</span></div>
        <div class="track-stat"><strong id="statStop">0</strong><span>آخر موضع</span></div>
        <div class="track-stat"><strong><?= $gardenCount ?></strong><span>في الحديقة</span></div>
      </div>
      <div class="legend-list">
        <div class="legend-item"><span class="legend-dot" style="background:rgba(34,197,94,.35);border:1px solid #22c55e"></span>محفوظ</div>
        <div class="legend-item"><span class="legend-dot" style="background:rgba(234,179,8,.35);border:1px solid #eab308"></span>مراجعة</div>
        <div class="legend-item"><span class="legend-dot" style="background:rgba(59,130,246,.35);border:1px solid #3b82f6"></span>آخر موضع</div>
        <div class="legend-item">اضغط على أي آية لتغيير حالتها.</div>
      </div>
    </aside>
  </section>
</main>

<div class="ctx-menu" id="ctxMenu">
  <div class="ctx-head" id="ctxHead">آية</div>
  <button class="ctx-row" type="button" onclick="QApp.mark('memorized')"><span class="legend-dot" style="background:#22c55e"></span>محفوظ</button>
  <button class="ctx-row" type="button" onclick="QApp.mark('reviewing')"><span class="legend-dot" style="background:#eab308"></span>مراجعة</button>
  <button class="ctx-row" type="button" onclick="QApp.mark('last_stop')"><span class="legend-dot" style="background:#3b82f6"></span>آخر موضع</button>
  <button class="ctx-row" type="button" onclick="QApp.mark('none')"><span class="legend-dot" style="background:#e5e7eb;border:1px solid #d1d5db"></span>إزالة التحديد</button>
  <button class="ctx-row" type="button" onclick="QApp.listenOne()">استماع لهذه الآية</button>
</div>

<div class="surah-modal" id="surahModal" onclick="if(event.target===this)closeSurahModal()">
  <div class="surah-box">
    <div class="surah-search"><input type="text" id="surahSearch" placeholder="ابحث عن سورة..." oninput="QApp.filter(this.value)"></div>
    <div class="surah-list" id="surahList"></div>
  </div>
</div>

<div class="audio-player" id="audioPlayer">
  <button class="ap-btn" type="button" onclick="QApp.prev()">⏮</button>
  <button class="ap-play" id="apPlayBtn" type="button" onclick="QApp.togglePlay()">▶</button>
  <button class="ap-btn" type="button" onclick="QApp.next()">⏭</button>
  <div class="ap-info"><div class="ap-title" id="apTitle">-</div><div class="ap-sub" id="apSub">-</div></div>
  <button class="ap-btn" type="button" onclick="QApp.stop()">×</button>
</div>

<div class="toast" id="toast"></div>

<?php
require_once __DIR__ . '/../admin/rattil_shared.php';
rattil_notif_panel($notifs);
?>

<script>
const QApp = {
  surah: 114,
  surahName: '',
  marks: {},
  queue: [],
  qIdx: 0,
  selAyah: null,
  allSurahs: [],
  audio: new Audio(),
  playingAll: false,
  singlePlay: false,
  OFFSETS:[0,0,7,293,493,669,789,954,1160,1235,1364,1473,1596,1707,1750,1802,1901,2029,2140,2250,2348,2483,2595,2673,2791,2855,2932,3159,3252,3340,3409,3469,3503,3533,3606,3660,3705,3788,3970,4058,4133,4218,4272,4325,4414,4473,4510,4545,4583,4612,4630,4675,4735,4784,4846,4901,4979,5075,5104,5126,5150,5163,5177,5188,5199,5217,5229,5241,5271,5323,5375,5419,5447,5475,5495,5551,5591,5622,5672,5712,5758,5800,5829,5848,5884,5909,5931,5948,5967,5993,6023,6043,6058,6079,6090,6098,6106,6125,6130,6138,6146,6157,6168,6176,6179,6188,6193,6197,6204,6207,6213,6216,6221,6225,6230],

  async init() {
    this.bindAudio();
    document.addEventListener('click', e => {
      if (!e.target.closest('#ctxMenu') && !e.target.closest('.ayah-span')) {
        document.getElementById('ctxMenu').style.display = 'none';
      }
    });
    await this.loadList();
  },

  async loadList() {
    const r = await fetch('/rattil/api/quran_text.php?action=surah_list');
    const d = await r.json();
    if (!d.success) throw new Error(d.error || 'تعذر تحميل السور');
    this.allSurahs = d.data;
    this.renderList(this.allSurahs);
    await this.loadSurah(this.surah);
  },

  renderList(list) {
    document.getElementById('surahList').innerHTML = list.map(s => `
      <div class="surah-item ${s.number === this.surah ? 'active' : ''}" onclick="QApp.pick(${s.number})">
        <div class="surah-number">${s.number}</div>
        <div><div class="surah-name">${s.name}</div><div class="surah-sub">${s.numberOfAyahs} آية · ${s.revelationType === 'Meccan' ? 'مكية' : 'مدنية'}</div></div>
      </div>
    `).join('');
  },

  filter(q) {
    q = q.trim();
    this.renderList(q ? this.allSurahs.filter(s => s.name.includes(q) || String(s.number).includes(q)) : this.allSurahs);
  },

  pick(num) {
    closeSurahModal();
    this.loadSurah(num);
  },

  async loadSurah(num) {
    this.surah = Number(num);
    this.stop();
    document.getElementById('loadingQ').style.display = 'flex';
    document.getElementById('quranReader').style.display = 'none';

    const [sr, tr] = await Promise.all([
      fetch(`/rattil/api/quran_text.php?action=surah&num=${this.surah}`),
      fetch(`/rattil/api/save_tracking.php?action=list&surah=${this.surah}`)
    ]);
    const sd = await sr.json();
    const td = await tr.json();
    if (!sd.success) throw new Error(sd.error || 'تعذر تحميل السورة');

    this.marks = {};
    if (td.success && Array.isArray(td.data)) {
      td.data.forEach(m => this.marks[Number(m.ayah_number)] = m.status);
    }

    this.queue = sd.data.ayahs.map(a => a.numberInSurah);
    this.surahName = sd.data.name;
    this.render(sd.data);
    this.updateStats();
  },

  render(surah) {
    document.getElementById('tbName').textContent = `${surah.number}. ${surah.name}`;
    document.getElementById('tbMeta1').textContent = `${surah.numberOfAyahs} آية`;
    document.getElementById('tbMeta2').textContent = surah.revelationType === 'Meccan' ? 'مكية' : 'مدنية';
    document.getElementById('shName').textContent = `سورة ${surah.name}`;
    document.getElementById('shAyahs').textContent = surah.numberOfAyahs;
    document.getElementById('shType').textContent = surah.revelationType === 'Meccan' ? 'مكية' : 'مدنية';
    document.getElementById('bismillah').style.display = (surah.number === 1 || surah.number === 9) ? 'none' : 'block';

    const flow = document.getElementById('quranFlow');
    flow.innerHTML = '';
    surah.ayahs.forEach(a => {
      const sp = document.createElement('span');
      sp.className = 'ayah-span' + (this.marks[a.numberInSurah] ? ` ${this.marks[a.numberInSurah]}` : '');
      sp.dataset.ayah = a.numberInSurah;
      sp.textContent = `${a.text} `;
      sp.addEventListener('click', e => {
        e.stopPropagation();
        this.showCtx(e, a.numberInSurah);
      });

      const num = document.createElement('span');
      num.className = 'ayah-num';
      num.textContent = this.toAr(a.numberInSurah);

      flow.appendChild(sp);
      flow.appendChild(num);
      flow.appendChild(document.createTextNode(' '));
    });

    document.getElementById('loadingQ').style.display = 'none';
    document.getElementById('quranReader').style.display = 'block';
    this.renderList(this.allSurahs);
  },

  showCtx(e, ayah) {
    this.selAyah = ayah;
    document.getElementById('ctxHead').textContent = `آية ${this.toAr(ayah)}`;
    const menu = document.getElementById('ctxMenu');
    menu.style.display = 'block';
    const rectW = 220;
    let left = e.clientX - rectW;
    let top = e.clientY + 10;
    if (left < 8) left = 8;
    if (left + rectW > window.innerWidth - 8) left = window.innerWidth - rectW - 8;
    if (top + 260 > window.innerHeight) top = Math.max(8, window.innerHeight - 260);
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  },

  async mark(status) {
    const ayah = this.selAyah;
    document.getElementById('ctxMenu').style.display = 'none';
    if (!ayah) return;

    const previous = this.marks[ayah];
    this.applyMark(ayah, status);

    try {
      const r = await fetch('/rattil/api/save_tracking.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({surah: this.surah, ayah, status})
      });
      const d = await r.json();
      if (!d.success) throw new Error(d.error || 'تعذر الحفظ');
      showToast(status === 'none' ? 'تمت إزالة التحديد' : 'تم حفظ الحالة');
    } catch (err) {
      this.applyMark(ayah, previous || 'none');
      showToast(err.message || 'تعذر الحفظ', true);
    }
  },

  applyMark(ayah, status) {
    const sp = document.querySelector(`.ayah-span[data-ayah="${ayah}"]`);
    if (sp) sp.className = 'ayah-span' + (status !== 'none' ? ` ${status}` : '');
    if (status !== 'none') this.marks[ayah] = status;
    else delete this.marks[ayah];
    this.updateStats();
  },

  updateStats() {
    const vals = Object.values(this.marks);
    document.getElementById('statMem').textContent = vals.filter(s => s === 'memorized').length;
    document.getElementById('statRev').textContent = vals.filter(s => s === 'reviewing').length;
    document.getElementById('statStop').textContent = vals.filter(s => s === 'last_stop').length;
  },

  listenOne() {
    document.getElementById('ctxMenu').style.display = 'none';
    this.singlePlay = true;
    this.playingAll = false;
    this.qIdx = this.queue.indexOf(this.selAyah);
    this.playAyah(this.selAyah);
  },

  togglePlayAll() {
    if (this.playingAll) {
      this.stop();
      return;
    }
    this.singlePlay = false;
    this.playingAll = true;
    this.qIdx = 0;
    this.playAyah(this.queue[0]);
    document.getElementById('playAllBtn').textContent = 'إيقاف';
  },

  playAyah(ayah) {
    if (!ayah) return;
    document.querySelectorAll('.ayah-span').forEach(s => s.classList.remove('playing'));
    const sp = document.querySelector(`.ayah-span[data-ayah="${ayah}"]`);
    if (sp) {
      sp.classList.add('playing');
      sp.scrollIntoView({behavior:'smooth', block:'center'});
    }
    const reciter = document.getElementById('reciterSelect').value;
    this.audio.src = `https://cdn.islamic.network/quran/audio/128/${reciter}/${(this.OFFSETS[this.surah] || 0) + ayah}.mp3`;
    this.audio.play().catch(() => showToast('تعذر تشغيل الصوت', true));
    document.getElementById('audioPlayer').style.display = 'flex';
    document.getElementById('apPlayBtn').textContent = '⏸';
    document.getElementById('apTitle').textContent = `سورة ${this.surahName} - آية ${this.toAr(ayah)}`;
    document.getElementById('apSub').textContent = sp ? sp.textContent.trim().slice(0, 70) : '';
  },

  togglePlay() {
    if (this.audio.paused) {
      this.audio.play();
      document.getElementById('apPlayBtn').textContent = '⏸';
    } else {
      this.audio.pause();
      document.getElementById('apPlayBtn').textContent = '▶';
    }
  },

  next() {
    this.qIdx = Math.min(this.queue.length - 1, this.qIdx + 1);
    this.playAyah(this.queue[this.qIdx]);
  },

  prev() {
    this.qIdx = Math.max(0, this.qIdx - 1);
    this.playAyah(this.queue[this.qIdx]);
  },

  stop() {
    this.audio.pause();
    this.audio.src = '';
    this.playingAll = false;
    document.getElementById('playAllBtn').textContent = 'استماع';
    document.getElementById('audioPlayer').style.display = 'none';
    document.querySelectorAll('.ayah-span').forEach(s => s.classList.remove('playing'));
  },

  bindAudio() {
    this.audio.addEventListener('ended', () => {
      if (!this.singlePlay && this.playingAll && this.qIdx < this.queue.length - 1) {
        this.qIdx++;
        this.playAyah(this.queue[this.qIdx]);
      } else {
        this.stop();
      }
    });
  },

  toAr(n) {
    return String(n).replace(/[0-9]/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
  }
};

function openSurahModal() {
  document.getElementById('surahModal').classList.add('open');
  document.getElementById('surahSearch').value = '';
  QApp.renderList(QApp.allSurahs);
  setTimeout(() => document.getElementById('surahSearch').focus(), 50);
}

function closeSurahModal() {
  document.getElementById('surahModal').classList.remove('open');
}

function showToast(msg, isError=false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = isError ? 'var(--danger)' : 'var(--olive-800)';
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 2500);
}

document.addEventListener('DOMContentLoaded', () => {
  QApp.init().catch(err => {
    document.getElementById('loadingQ').innerHTML = `<p style="color:var(--danger)">${err.message}</p>`;
  });
});
</script>
</body>
</html>
