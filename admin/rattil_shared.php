<?php
/**
 * rattil_shared.php — نظام التصميم الموحد لمنصة رتِّل
 * يشمل: CSS العالمي، الشريط الجانبي، لوحة الإشعارات، المودال
 */

function rattil_global_styles() { ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════
   RATTIL DESIGN SYSTEM v3 — متغيرات CSS العالمية
   ══════════════════════════════════════════════════ */
:root {
  /* ألوان الهوية */
  --olive-950: #091a10;
  --olive-900: #0f2d1f;
  --olive-800: #163324;
  --olive-700: #1d4a32;
  --olive-600: #246040;
  --olive-500: #2d7a52;
  --olive-400: #3d9668;
  --olive-300: #6ab68c;
  --olive-200: #a8d5be;
  --olive-100: #e8f5ee;
  --olive-50:  #f2faf5;

  --gold-600: #a07c1c;
  --gold-500: #c9a84c;
  --gold-400: #d4b86a;
  --gold-300: #e3cf9a;
  --gold-100: #fdf6e3;
  --gold-50:  #fffbf0;

  --beige:    #f7f3ec;
  --beige-dark: #ede8df;

  /* واجهة */
  --bg:       #f5f6f3;
  --surface:  #ffffff;
  --surface-2:#fafaf8;
  --border:   rgba(22,51,36,.08);
  --border-2: rgba(22,51,36,.14);
  --muted:    #6b7c75;
  --text:     #1a2e24;

  /* حالات */
  --danger:      #c0392b;
  --danger-bg:   #fde8e8;
  --danger-border:#fca5a5;
  --success:     #1a7340;
  --success-bg:  #e6f5ec;
  --success-border:#86efac;
  --warning:     #92400e;
  --warning-bg:  #fef3c7;
  --info:        #1d4ed8;
  --info-bg:     #eff6ff;

  /* الشريط الجانبي */
  --sidebar-w:   256px;

  /* أنصاف الأقطار */
  --r-2xl: 24px;
  --r-xl:  18px;
  --r-lg:  14px;
  --r-md:  10px;
  --r-sm:  7px;
  --r-xs:  5px;

  /* ظلال */
  --sh-xs: 0 1px 3px rgba(0,0,0,.05), 0 1px 2px rgba(0,0,0,.04);
  --sh-sm: 0 2px 8px rgba(0,0,0,.06), 0 1px 3px rgba(0,0,0,.05);
  --sh-md: 0 6px 20px rgba(0,0,0,.08), 0 2px 8px rgba(0,0,0,.05);
  --sh-lg: 0 12px 40px rgba(0,0,0,.10), 0 4px 16px rgba(0,0,0,.06);
  --sh-xl: 0 20px 60px rgba(0,0,0,.14);

  /* انتقالات */
  --ease: cubic-bezier(.4,0,.2,1);
  --ease-out: cubic-bezier(0,0,.2,1);
  --dur: .2s;
  --dur-2: .35s;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; font-size: 16px; }

body {
  font-family: 'Tajawal', sans-serif;
  background: var(--bg);
  color: var(--text);
  direction: rtl;
  min-height: 100vh;
  display: flex;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}

a { text-decoration: none; color: inherit; }
img { max-width: 100%; }
button { font-family: 'Tajawal', sans-serif; }

/* ══════════════════════════════════════════════════
   SIDEBAR — الشريط الجانبي
   ══════════════════════════════════════════════════ */
.rs {
  width: var(--sidebar-w);
  background: var(--olive-900);
  min-height: 100vh;
  position: fixed;
  top: 0;
  right: 0;
  display: flex;
  flex-direction: column;
  z-index: 200;
  transition: transform var(--dur-2) var(--ease);
}

/* خط زخرفي ذهبي في الأعلى */
.rs::before {
  content: '';
  position: absolute;
  top: 0; right: 0; left: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--gold-500), var(--gold-300), var(--gold-500));
}

.rs-logo {
  padding: 1.5rem 1.25rem 1.1rem;
  display: flex;
  align-items: center;
  gap: .75rem;
  border-bottom: 1px solid rgba(255,255,255,.06);
  margin-top: 3px;
}
.rs-logo img { height: 38px;  }
.rs-logo-text {
  color: #fff;
  font-size: 1.35rem;
  font-weight: 900;
  letter-spacing: .5px;
}

/* بطاقة المستخدم */
.rs-user {
  margin: 1rem .875rem .5rem;
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: var(--r-lg);
  padding: .9rem 1rem;
  display: flex;
  align-items: center;
  gap: .8rem;
}
.rs-av {
  width: 42px; height: 42px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--gold-500), var(--gold-400));
  display: flex; align-items: center; justify-content: center;
  font-size: 1.05rem; font-weight: 800; color: var(--olive-800);
  flex-shrink: 0;
  box-shadow: 0 3px 10px rgba(0,0,0,.3);
}
.rs-user-name { color: #fff; font-weight: 700; font-size: .88rem; line-height: 1.3; }
.rs-user-role { color: var(--gold-300); font-size: .73rem; margin-top: 1px; }

/* قائمة التنقل */
.rs-nav {
  flex: 1;
  padding: .75rem .875rem 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
  overflow-y: auto;
  scrollbar-width: none;
}
.rs-nav::-webkit-scrollbar { display: none; }

.rs-section {
  color: rgba(255,255,255,.3);
  font-size: .67rem;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  padding: .9rem .875rem .3rem;
}

.rs-link {
  display: flex;
  align-items: center;
  gap: .8rem;
  padding: .68rem 1rem;
  border-radius: var(--r-md);
  color: rgba(255,255,255,.6);
  font-size: .9rem;
  font-weight: 500;
  transition: all var(--dur) var(--ease);
  position: relative;
  cursor: pointer;
}
.rs-link svg {
  width: 17px; height: 17px;
  fill: none; stroke: currentColor;
  stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
  flex-shrink: 0;
  transition: transform var(--dur) var(--ease);
}
.rs-link:hover {
  background: rgba(255,255,255,.07);
  color: #fff;
}
.rs-link:hover svg { transform: scale(1.1); }
.rs-link.active {
  background: linear-gradient(135deg, rgba(201,168,76,.16), rgba(201,168,76,.06));
  color: var(--gold-300);
  font-weight: 700;
}
.rs-link.active::before {
  content: '';
  position: absolute;
  right: 0; top: 20%; bottom: 20%;
  width: 3px;
  background: var(--gold-500);
  border-radius: 0 3px 3px 0;
}
.rs-link.active svg { stroke: var(--gold-300); }

.rs-badge {
  margin-right: auto;
  background: var(--danger);
  color: #fff;
  font-size: .66rem; font-weight: 700;
  padding: 1px 7px; border-radius: 20px;
}

/* تذييل الشريط */
.rs-foot {
  padding: .875rem;
  border-top: 1px solid rgba(255,255,255,.06);
}
.rs-foot .rs-link { color: rgba(248,113,113,.7); }
.rs-foot .rs-link:hover { background: rgba(248,113,113,.08); color: #f87171; }

/* ══════════════════════════════════════════════════
   PAGE WRAPPER
   ══════════════════════════════════════════════════ */
.pw {
  margin-right: var(--sidebar-w);
  width: calc(100% - var(--sidebar-w));
  padding: 2rem 2.25rem;
  min-height: 100vh;
}

/* رأس الصفحة */
.ph {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.75rem;
  gap: 1rem;
  flex-wrap: wrap;
}
.ph-left h1 {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--olive-800);
  display: flex; align-items: center; gap: .6rem;
}
.ph-left h1 svg {
  width: 22px; height: 22px;
  stroke: var(--olive-600); fill: none;
  stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
}
.ph-left p { color: var(--muted); font-size: .88rem; margin-top: .2rem; }

/* ══════════════════════════════════════════════════
   STAT BOXES
   ══════════════════════════════════════════════════ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 1.1rem;
  margin-bottom: 1.75rem;
}
.stat-box {
  background: var(--surface);
  border-radius: var(--r-lg);
  border: 1px solid var(--border);
  padding: 1.2rem 1.4rem;
  display: flex; align-items: center; gap: 1rem;
  box-shadow: var(--sh-xs);
  transition: transform var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
  cursor: default;
}
.stat-box:hover { transform: translateY(-3px); box-shadow: var(--sh-md); }
.stat-ic {
  width: 46px; height: 46px;
  border-radius: var(--r-md);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.stat-ic svg { width: 22px; height: 22px; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.stat-ic.g { background: var(--olive-100); } .stat-ic.g svg { stroke: var(--olive-600); }
.stat-ic.a { background: var(--gold-100); }  .stat-ic.a svg { stroke: var(--gold-500); }
.stat-ic.r { background: var(--danger-bg); } .stat-ic.r svg { stroke: var(--danger); }
.stat-ic.b { background: var(--info-bg); }   .stat-ic.b svg { stroke: var(--info); }
.stat-ic.p { background: #f5f3ff; }           .stat-ic.p svg { stroke: #7c3aed; }
.stat-v { font-size: 1.75rem; font-weight: 800; color: var(--olive-800); line-height: 1; }
.stat-l { font-size: .79rem; color: var(--muted); margin-top: .3rem; }

/* ══════════════════════════════════════════════════
   CARD
   ══════════════════════════════════════════════════ */
.card {
  background: var(--surface);
  border-radius: var(--r-lg);
  box-shadow: var(--sh-sm);
  border: 1px solid var(--border);
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}
.card-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}
.card-title {
  font-size: 1rem; font-weight: 700; color: var(--olive-700);
  display: flex; align-items: center; gap: .5rem;
}
.card-title svg { width: 18px; height: 18px; stroke: var(--olive-500); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ══════════════════════════════════════════════════
   BUTTONS
   ══════════════════════════════════════════════════ */
.btn {
  display: inline-flex; align-items: center; gap: .5rem;
  padding: .6rem 1.2rem;
  border-radius: var(--r-md);
  font-family: 'Tajawal', sans-serif;
  font-size: .9rem; font-weight: 700;
  cursor: pointer; border: none;
  transition: all var(--dur) var(--ease);
  text-decoration: none; white-space: nowrap;
  line-height: 1.4;
}
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.btn-primary   { background: var(--olive-700); color: #fff; box-shadow: 0 2px 8px rgba(22,67,36,.25); }
.btn-primary:hover   { background: var(--olive-600); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(22,67,36,.3); }
.btn-secondary { background: var(--surface); color: var(--olive-700); border: 1.5px solid var(--olive-200); }
.btn-secondary:hover { background: var(--olive-50); border-color: var(--olive-300); }
.btn-gold      { background: linear-gradient(135deg, var(--gold-500), var(--gold-400)); color: var(--olive-900); }
.btn-gold:hover { filter: brightness(1.06); transform: translateY(-1px); }
.btn-danger    { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #a93226; transform: translateY(-1px); }
.btn-ghost     { background: transparent; color: var(--olive-700); border: 1.5px solid var(--border-2); }
.btn-ghost:hover { background: var(--olive-50); }
.btn-sm  { padding: .4rem .9rem; font-size: .82rem; }
.btn-xs  { padding: .28rem .7rem; font-size: .78rem; }
.btn-icon { width: 36px; height: 36px; padding: 0; justify-content: center; border-radius: var(--r-sm); }

/* Loading state */
.btn.loading { opacity: .7; pointer-events: none; position: relative; }
.btn.loading::after {
  content: '';
  position: absolute;
  width: 14px; height: 14px;
  border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
}

/* ══════════════════════════════════════════════════
   FORMS
   ══════════════════════════════════════════════════ */
.fg { margin-bottom: .9rem; }
.fl {
  display: block;
  font-size: .84rem; font-weight: 700; color: var(--olive-700);
  margin-bottom: .4rem;
}
.fc {
  width: 100%; padding: .62rem 1rem;
  border: 1.5px solid #dde5df;
  border-radius: var(--r-md);
  font-family: 'Tajawal', sans-serif; font-size: .93rem; color: var(--text);
  background: #fff; outline: none;
  transition: border-color var(--dur), box-shadow var(--dur);
}
.fc:focus { border-color: var(--olive-500); box-shadow: 0 0 0 3px rgba(45,122,82,.1); }
.fc:disabled { background: var(--beige); opacity: .7; cursor: not-allowed; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }

/* ══════════════════════════════════════════════════
   TABLE
   ══════════════════════════════════════════════════ */
.tbl-wrap { overflow-x: auto; border-radius: var(--r-md); }
.rtbl { width: 100%; border-collapse: collapse; }
.rtbl thead { position: sticky; top: 0; z-index: 1; }
.rtbl th {
  background: var(--olive-50); color: var(--olive-700);
  padding: .72rem 1rem; font-size: .8rem; font-weight: 700;
  text-align: right; border-bottom: 2px solid rgba(45,122,82,.1);
  white-space: nowrap;
}
.rtbl td { padding: .65rem 1rem; font-size: .87rem; border-bottom: 1px solid #f2f5f1; vertical-align: middle; }
.rtbl tbody tr { transition: background var(--dur); }
.rtbl tbody tr:hover td { background: var(--olive-50); }
.rtbl tbody tr:last-child td { border-bottom: none; }

/* ══════════════════════════════════════════════════
   BADGES & CHIPS
   ══════════════════════════════════════════════════ */
.badge {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .22rem .7rem; border-radius: 20px;
  font-size: .77rem; font-weight: 700; white-space: nowrap;
}
.badge-active   { background: var(--success-bg); color: var(--success); }
.badge-inactive { background: var(--danger-bg);  color: var(--danger);  }
.badge-gold     { background: var(--gold-100);   color: var(--warning); }
.badge-info     { background: var(--info-bg);    color: var(--info); }

.grade { display: inline-block; padding: .2rem .65rem; border-radius: 20px; font-size: .77rem; font-weight: 700; white-space: nowrap; }
.grade-ممتاز            { background: #d1fae5; color: #065f46; }
.grade-جيد\ جداً       { background: #dbeafe; color: #1e40af; }
.grade-جيد              { background: #e0f2fe; color: #0369a1; }
.grade-مقبول            { background: #fef9c3; color: #713f12; }
.grade-يحتاج\ مراجعة   { background: #fee2e2; color: #991b1b; }

.chip {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .2rem .75rem; border-radius: 20px;
  font-size: .79rem; font-weight: 600;
  background: var(--olive-100); color: var(--olive-700);
  border: 1px solid rgba(45,122,82,.15);
}
.chip-rm {
  background: none; border: none; cursor: pointer;
  color: var(--danger); font-size: .85rem; padding: 0; line-height: 1;
  display: flex; align-items: center;
  transition: transform var(--dur);
}
.chip-rm:hover { transform: scale(1.2); }

/* ══════════════════════════════════════════════════
   ALERTS
   ══════════════════════════════════════════════════ */
.alert {
  padding: .85rem 1.1rem; border-radius: var(--r-md);
  font-size: .9rem; margin-bottom: 1.25rem;
  display: flex; align-items: center; gap: .65rem;
  animation: fadeIn .3s var(--ease);
}
.alert svg { width: 18px; height: 18px; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.alert-ok  { background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border); }
.alert-ok svg { stroke: var(--success); }
.alert-err { background: var(--danger-bg);  color: var(--danger);  border: 1px solid var(--danger-border); }
.alert-err svg { stroke: var(--danger); }
.alert-info { background: var(--info-bg); color: var(--info); border: 1px solid #bfdbfe; }

/* ══════════════════════════════════════════════════
   OVERLAY & MODALS
   ══════════════════════════════════════════════════ */
.overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.45);
  backdrop-filter: blur(4px); z-index: 300;
  opacity: 0; visibility: hidden;
  transition: opacity var(--dur-2) var(--ease), visibility var(--dur-2) var(--ease);
}
.overlay.show { opacity: 1; visibility: visible; }

.modal {
  position: fixed; inset: 0; z-index: 400;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
  opacity: 0; visibility: hidden;
  transition: opacity var(--dur-2) var(--ease), visibility var(--dur-2) var(--ease);
  pointer-events: none;
}
.modal.show { opacity: 1; visibility: visible; pointer-events: all; }
.modal.show .modal-box { transform: scale(1) translateY(0); }

.modal-box {
  background: var(--surface);
  border-radius: var(--r-xl);
  box-shadow: var(--sh-xl);
  width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto;
  transform: scale(.96) translateY(18px);
  transition: transform .3s var(--ease-out);
}
.modal-box.modal-sm { max-width: 400px; }
.modal-box.modal-lg { max-width: 700px; }

.modal-head {
  padding: 1.2rem 1.5rem;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; background: var(--surface); z-index: 1;
  border-radius: var(--r-xl) var(--r-xl) 0 0;
}
.modal-head h3 { font-size: 1rem; font-weight: 800; color: var(--olive-800); display: flex; align-items: center; gap: .5rem; }
.modal-head h3 svg { width: 18px; height: 18px; stroke: var(--olive-600); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.modal-head h3.danger { color: var(--danger); }
.modal-head h3.danger svg { stroke: var(--danger); }
.modal-close {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--bg); border: 1px solid var(--border);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: var(--muted); font-size: 1.2rem; line-height: 1;
  transition: background var(--dur), color var(--dur), border-color var(--dur);
}
.modal-close:hover { background: var(--danger-bg); color: var(--danger); border-color: var(--danger-border); }

.modal-body { padding: 1.5rem; }
.modal-foot {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border);
  display: flex; gap: .75rem; justify-content: flex-end;
  background: var(--surface-2);
  border-radius: 0 0 var(--r-xl) var(--r-xl);
}

/* ══════════════════════════════════════════════════
   PROGRESS BAR
   ══════════════════════════════════════════════════ */
.progress { height: 6px; background: var(--beige-dark); border-radius: 10px; overflow: hidden; }
.progress-bar { height: 100%; background: linear-gradient(90deg, var(--olive-600), var(--olive-400)); border-radius: 10px; transition: width .6s var(--ease); }

/* ══════════════════════════════════════════════════
   PAGINATION
   ══════════════════════════════════════════════════ */
.pagination { display: flex; gap: .4rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem; }
.pg-btn {
  padding: .38rem .85rem; border: 1.5px solid var(--olive-200);
  border-radius: var(--r-sm); background: var(--surface);
  color: var(--olive-700); font-size: .84rem;
  font-family: 'Tajawal', sans-serif; cursor: pointer;
  text-decoration: none; transition: all var(--dur);
}
.pg-btn:hover, .pg-btn.active { background: var(--olive-700); color: #fff; border-color: var(--olive-700); }

/* ══════════════════════════════════════════════════
   SEARCH BAR
   ══════════════════════════════════════════════════ */
.search-bar { display: flex; gap: .75rem; margin-bottom: 1.5rem; align-items: center; }
.search-bar .fc { flex: 1; }
.search-input-wrap { position: relative; flex: 1; }
.search-input-wrap svg { position: absolute; right: .9rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; stroke: var(--muted); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; pointer-events: none; }
.search-input-wrap .fc { padding-right: 2.5rem; }

/* ══════════════════════════════════════════════════
   NOTIFICATION PANEL
   ══════════════════════════════════════════════════ */
.notif-panel {
  position: fixed; top: 0; left: 0;
  width: 340px; height: 100vh;
  background: var(--surface); z-index: 350;
  box-shadow: var(--sh-lg);
  transform: translateX(-100%);
  transition: transform .3s var(--ease-out);
  display: flex; flex-direction: column;
}
.notif-panel.show { transform: translateX(0); }
.notif-panel-head {
  padding: 1.2rem 1.25rem;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  background: var(--olive-50);
}
.notif-panel-head h3 { font-size: .95rem; font-weight: 700; color: var(--olive-700); display: flex; align-items: center; gap: .5rem; }
.notif-panel-head h3 svg { width: 17px; height: 17px; stroke: var(--olive-500); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.notif-list { flex: 1; overflow-y: auto; }
.notif-item { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); transition: background var(--dur); }
.notif-item:hover { background: var(--olive-50); }
.notif-item.unread { background: var(--olive-50); border-right: 3px solid var(--olive-500); }
.notif-item-title { font-weight: 700; font-size: .87rem; color: var(--olive-800); margin-bottom: .2rem; }
.notif-item-body  { font-size: .81rem; color: var(--muted); margin-bottom: .25rem; }
.notif-item-time  { font-size: .74rem; color: #aab5b0; }

/* زر الإشعارات */
.notif-btn {
  position: relative; background: var(--olive-50); border: 1px solid var(--border);
  width: 38px; height: 38px; border-radius: var(--r-md);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  transition: background var(--dur);
}
.notif-btn svg { width: 18px; height: 18px; stroke: var(--olive-700); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.notif-btn:hover { background: var(--olive-100); }
.notif-dot {
  position: absolute; top: -3px; left: -3px;
  min-width: 17px; height: 17px;
  background: var(--danger); color: #fff;
  font-size: .63rem; font-weight: 700;
  border-radius: 20px; padding: 0 4px;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid var(--surface);
}

/* ══════════════════════════════════════════════════
   QUICK ACTIONS GRID
   ══════════════════════════════════════════════════ */
.qa-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(145px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.qa-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--r-lg); padding: 1.2rem 1rem;
  display: flex; flex-direction: column; align-items: center; gap: .65rem;
  text-decoration: none; color: var(--olive-700);
  transition: all var(--dur) var(--ease); text-align: center;
}
.qa-card:hover { transform: translateY(-4px); box-shadow: var(--sh-md); border-color: var(--olive-200); }
.qa-ic { width: 44px; height: 44px; background: var(--olive-100); border-radius: var(--r-md); display: flex; align-items: center; justify-content: center; transition: background var(--dur); }
.qa-ic svg { width: 22px; height: 22px; stroke: var(--olive-600); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.qa-card:hover .qa-ic { background: var(--olive-700); }
.qa-card:hover .qa-ic svg { stroke: #fff; }
.qa-card span { font-size: .84rem; font-weight: 700; }

/* ══════════════════════════════════════════════════
   KPI CARDS
   ══════════════════════════════════════════════════ */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
.kpi {
  background: var(--surface); border-radius: var(--r-lg); padding: 1.2rem;
  text-align: center; border: 1px solid var(--border); box-shadow: var(--sh-xs);
  border-top: 3px solid var(--olive-500);
  transition: transform var(--dur), box-shadow var(--dur);
}
.kpi:hover { transform: translateY(-2px); box-shadow: var(--sh-md); }
.kpi-v { font-size: 1.8rem; font-weight: 800; color: var(--olive-800); line-height: 1; }
.kpi-l { font-size: .77rem; color: var(--muted); margin-top: .3rem; }

/* ══════════════════════════════════════════════════
   SPINNER & ANIMATIONS
   ══════════════════════════════════════════════════ */
.spinner {
  display: inline-block; width: 26px; height: 26px;
  border: 2.5px solid var(--border);
  border-top-color: var(--olive-500);
  border-radius: 50%;
  animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
@keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: none; } }

.anim { opacity: 0; transform: translateY(14px); transition: opacity .45s var(--ease), transform .45s var(--ease); }
.anim.in { opacity: 1; transform: none; }
.anim-d1 { transition-delay: .07s; }
.anim-d2 { transition-delay: .14s; }
.anim-d3 { transition-delay: .21s; }
.anim-d4 { transition-delay: .28s; }
.anim-d5 { transition-delay: .35s; }

/* ══════════════════════════════════════════════════
   RANK LIST
   ══════════════════════════════════════════════════ */
.rank-item { display: flex; align-items: center; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--border); }
.rank-item:last-child { border-bottom: none; }
.rank-n {
  width: 27px; height: 27px; border-radius: 50%;
  background: var(--olive-100); color: var(--olive-700);
  display: flex; align-items: center; justify-content: center;
  font-size: .75rem; font-weight: 800; flex-shrink: 0;
}
.rank-n.gold { background: linear-gradient(135deg, var(--gold-500), var(--gold-400)); color: var(--olive-900); }

/* ══════════════════════════════════════════════════
   MOBILE TOGGLE
   ══════════════════════════════════════════════════ */
#rsToggle {
  display: none;
  position: fixed; top: 1rem; right: 1rem; z-index: 500;
  background: var(--olive-800); border: none;
  border-radius: var(--r-md); padding: .55rem;
  cursor: pointer; box-shadow: var(--sh-md);
}
#rsToggle svg { width: 22px; height: 22px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; }

/* ══════════════════════════════════════════════════
   RESPONSIVE
   ══════════════════════════════════════════════════ */
@media (max-width: 1100px) { .charts-grid { grid-template-columns: 1fr !important; } }
@media (max-width: 900px) {
  .rs { transform: translateX(100%); }
  .rs.open { transform: translateX(0); }
  .pw { margin-right: 0; width: 100%; padding: 1.25rem 1rem; }
  .form-row, .form-row-3 { grid-template-columns: 1fr; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .kpi-grid { grid-template-columns: 1fr 1fr; }
  .qa-grid { grid-template-columns: repeat(3, 1fr); }
  #rsToggle { display: flex; }
}
@media (max-width: 600px) {
  .stats-grid { grid-template-columns: 1fr; }
  .qa-grid { grid-template-columns: repeat(2, 1fr); }
  .ph { flex-direction: column; align-items: flex-start; }
}

/* Print */
@media print {
  .rs, #rsToggle, .notif-panel, .overlay, .btn { display: none !important; }
  .pw { margin: 0; width: 100%; padding: 0; }
}
</style>
<?php }

/* ─── Sidebar HTML ──────────────────────────────────── */
function rattil_sidebar(array $user, string $active = 'dashboard', int $unread = 0): void {
  $init = mb_substr($user['name'], 0, 1, 'UTF-8');
  $links = [
    'dashboard'      => ['/rattil/admin/dashboard.php',     'الرئيسية',    '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>'],
    'halaqat'        => ['/rattil/admin/halaqat.php',        'الحلقات',     '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
    'teachers'       => ['/rattil/admin/teachers.php',       'المعلمون',    '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    'students'       => ['/rattil/admin/students.php',       'الطلاب',      '<polygon points="12 2 2 7 12 12 22 7 12 2"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>'],
    'recitation_log' => ['/rattil/admin/recitation_log.php', 'سجل التسميع', '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
    'reports'        => ['/rattil/admin/reports.php',        'التقارير',    '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
    'settings'       => ['/rattil/admin/settings.php',       'الإعدادات',   '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
  ];
?>
<aside class="rs" id="rs">
  <a href="/rattil/admin/dashboard.php" class="rs-logo">
    <img src="/rattil/assets/images/logo.png" alt="رتِّل" onerror="this.style.display='none'">
    <span class="rs-logo-text">رتِّل</span>
  </a>

  <div class="rs-user">
    <div class="rs-av"><?= $init ?></div>
    <div>
      <div class="rs-user-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="rs-user-role">مدير النظام</div>
    </div>
  </div>

  <nav class="rs-nav" role="navigation" aria-label="القائمة الرئيسية">
    <div class="rs-section">القائمة الرئيسية</div>
    <?php foreach ($links as $key => [$href, $label, $icon]): ?>
    <a href="<?= $href ?>" class="rs-link <?= $active === $key ? 'active' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><?= $icon ?></svg>
      <span><?= $label ?></span>
      <?php if ($key === 'recitation_log' && $unread > 0): ?>
      <span class="rs-badge"><?= $unread ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="rs-foot">
    <a href="/rattil/logout.php" class="rs-link">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>تسجيل الخروج</span>
    </a>
  </div>
</aside>

<!-- زر القائمة للموبايل -->
<button id="rsToggle" onclick="toggleSidebar()" aria-label="فتح القائمة">
  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<div class="overlay" id="rsOverlay" onclick="closeSidebar()"></div>

<script>
function toggleSidebar() {
  const s = document.getElementById('rs');
  const o = document.getElementById('rsOverlay');
  s.classList.toggle('open');
  o.classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('rs').classList.remove('open');
  document.getElementById('rsOverlay').classList.remove('show');
}
// انيميشن عند التمرير
const _obs = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); }), { threshold: .08 });
document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.anim').forEach(el => _obs.observe(el)));
</script>
<?php }

/* ─── Notification Panel ──────────────────────────── */
function rattil_notif_panel(int $unread = 0): void { ?>
<div class="overlay" id="notifOverlay" onclick="closeNotif()"></div>
<div class="notif-panel" id="notifPanel" role="dialog" aria-label="الإشعارات">
  <div class="notif-panel-head">
    <h3>
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      الإشعارات
    </h3>
    <button class="btn btn-xs btn-secondary" onclick="markAllRead()">قراءة الكل</button>
  </div>
  <div class="notif-list" id="notifList">
    <div style="padding:2.5rem;text-align:center"><div class="spinner"></div></div>
  </div>
</div>

<script>
function openNotif() {
  document.getElementById('notifPanel').classList.add('show');
  document.getElementById('notifOverlay').classList.add('show');
  loadNotifs();
}
function closeNotif() {
  document.getElementById('notifPanel').classList.remove('show');
  document.getElementById('notifOverlay').classList.remove('show');
}
async function loadNotifs() {
  try {
    const r = await fetch('/rattil/api/notifications.php?action=list');
    const d = await r.json();
    const list = document.getElementById('notifList');
    if (!d.success || !d.data.length) {
      list.innerHTML = '<p style="padding:2rem;text-align:center;color:var(--muted);font-size:.9rem">لا توجد إشعارات</p>';
      return;
    }
    list.innerHTML = d.data.map(n => `
      <div class="notif-item ${n.is_read ? '' : 'unread'}">
        <div class="notif-item-title">${n.title}</div>
        <div class="notif-item-body">${n.body || ''}</div>
        <div class="notif-item-time">${n.created_at}</div>
      </div>`).join('');
  } catch(e) { console.error(e); }
}
async function markAllRead() {
  try {
    await fetch('/rattil/api/notifications.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=mark_all_read'
    });
    const dot = document.getElementById('notifDot');
    if (dot) dot.style.display = 'none';
    await loadNotifs();
  } catch(e) {}
}
</script>
<?php }

/* ─── Modal JS Helpers ─────────────────────────────── */
function rattil_modals_js(): void { ?>
<script>
const _modalOverlay = document.getElementById('rsOverlay');

function openModal(id) {
  document.getElementById(id).classList.add('show');
  _modalOverlay.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).classList.remove('show');
  _modalOverlay.classList.remove('show');
  document.body.style.overflow = '';
}

// إغلاق بـ Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));
    _modalOverlay.classList.remove('show');
    document.body.style.overflow = '';
  }
});

// إغلاق عند النقر خارج المودال
_modalOverlay.addEventListener('click', () => {
  document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));
  _modalOverlay.classList.remove('show');
  document.body.style.overflow = '';
  closeSidebar();
});
</script>
<?php }

/* ─── Page Shell (head meta + body open) ──────────── */
function rattil_page_start(string $title): void { ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($title) ?> — رتِّل</title>
<?php rattil_global_styles(); ?>
</head>
<body>
<?php }