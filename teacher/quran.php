<?php
require_once '../config/db.php';
requireRole('teacher');
$user   = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$tid    = (int)$_SESSION['user_id'];

/* ── طلاب المعلم (لاختيار الطالب لمتابعة تقدمه) ── */
$myStudents = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, h.name as halqa_name
    FROM users u
    JOIN halqa_students hs ON hs.student_id = u.id
    JOIN halaqat h ON h.id = hs.halqa_id
    WHERE h.teacher_id = ?
    ORDER BY u.name
");
$myStudents->execute([$tid]);
$students = $myStudents->fetchAll();

$viewStudentId = (int)($_GET['student_id'] ?? 0);
$viewStudent   = null;
if ($viewStudentId) {
    // تأكد أن الطالب ينتمي لحلقة هذا المعلم
    $chk = $pdo->prepare("SELECT COUNT(*) FROM halqa_students hs JOIN halaqat h ON hs.halqa_id=h.id WHERE h.teacher_id=? AND hs.student_id=?");
    $chk->execute([$tid, $viewStudentId]);
    if ($chk->fetchColumn()) {
        $vs = $pdo->prepare("SELECT id,name FROM users WHERE id=?");
        $vs->execute([$viewStudentId]);
        $viewStudent = $vs->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<link rel="stylesheet" href="/rattil/assets/css/sidebar.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>المصحف الشريف — رتِّل</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;600;700;800&family=Scheherazade+New:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/rattil/assets/css/main.css">
<link rel="stylesheet" href="/rattil/assets/css/dashboard.css">
<style>
/* ── Layout ── */
.quran-layout{display:grid;grid-template-columns:280px 1fr;gap:1.5rem;max-width:1400px;margin:calc(0 + 1.5rem) auto 3rem;padding:0 1.5rem}

/* ── Sidebar ── */
.quran-sidebar{position:sticky;top:calc(0 + 1.5rem);max-height:calc(100vh - 0 - 3rem);overflow-y:auto;display:flex;flex-direction:column;gap:1rem}
.sidebar-card{background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-sm);overflow:hidden}
.sidebar-card-head{padding:1rem 1.25rem;background:linear-gradient(135deg,var(--color-secondary),var(--color-primary));color:#fff;font-weight:700;font-size:.95rem;display:flex;align-items:center;gap:.5rem}

/* Student selector */
.student-select-wrap{padding:1rem}
.student-mode-bar{display:flex;gap:.5rem;margin-bottom:.75rem}
.mode-btn{flex:1;padding:.45rem;border-radius:var(--radius-sm);border:2px solid #e5e7eb;background:#fff;cursor:pointer;font-family:inherit;font-size:.82rem;font-weight:600;color:var(--color-muted);transition:var(--transition);text-align:center}
.mode-btn.active{border-color:var(--color-secondary);background:var(--color-secondary);color:#fff}
.student-list{display:flex;flex-direction:column;gap:.3rem}
.student-btn{padding:.6rem .9rem;border-radius:var(--radius-sm);border:none;background:#f9fafb;cursor:pointer;font-family:inherit;font-size:.85rem;text-align:right;transition:var(--transition);color:var(--color-text)}
.student-btn:hover{background:var(--color-cream)}
.student-btn.active{background:linear-gradient(135deg,var(--color-secondary),var(--color-primary));color:#fff}
.student-meta{font-size:.73rem;opacity:.75;margin-top:.15rem}

/* Surah list */
.surah-search{padding:.75rem;border-bottom:1px solid #f0f0f0}
.surah-search input{width:100%;padding:.5rem .75rem;border:1.5px solid #e5e7eb;border-radius:var(--radius-sm);font-family:inherit;font-size:.88rem;direction:rtl}
.surah-search input:focus{outline:none;border-color:var(--color-secondary)}
.surah-list{max-height:380px;overflow-y:auto}
.surah-item{padding:.7rem 1.1rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;transition:var(--transition);border-bottom:1px solid #f5f5f5}
.surah-item:hover{background:var(--color-cream)}
.surah-item.active{background:linear-gradient(135deg,rgba(27,67,50,.08),rgba(45,106,79,.05));border-right:3px solid var(--color-secondary)}
.surah-num{width:28px;height:28px;border-radius:50%;background:var(--color-cream);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:var(--color-primary);flex-shrink:0}
.surah-info{flex:1}
.surah-name{font-weight:700;font-size:.9rem;color:var(--color-primary)}
.surah-ayahs{font-size:.73rem;color:var(--color-muted)}

/* ── Main Panel ── */
.quran-main{display:flex;flex-direction:column;gap:1rem}
.quran-controls{background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.reader-select{padding:.45rem .75rem;border:1.5px solid #e5e7eb;border-radius:var(--radius-sm);font-family:inherit;font-size:.87rem;background:#fff;cursor:pointer}
.reader-select:focus{outline:none;border-color:var(--color-secondary)}

/* Audio Player */
.audio-player{display:flex;align-items:center;gap:.75rem;flex:1;min-width:200px}
.audio-player audio{flex:1;height:36px}
.play-btn{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--color-secondary),var(--color-primary));border:none;color:#fff;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:var(--transition)}
.play-btn:hover{transform:scale(1.08)}

/* Mode indicator */
.view-mode-badge{padding:.3rem .9rem;border-radius:20px;font-size:.8rem;font-weight:700}
.mode-teacher{background:#dbeafe;color:#1e40af}
.mode-student{background:#EAECE6;color:#1C3329}

/* Legend (teacher mode = read-only) */
.legend{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;font-size:.82rem;margin-right:auto}
.legend-dot{width:12px;height:12px;border-radius:50%;display:inline-block;margin-left:.3rem}
.dot-memorized{background:#2C4A3E}
.dot-reviewing{background:#f59e0b}
.dot-last-stop{background:#3b82f6}

/* Quran Display */
.quran-display{background:#fff;border-radius:var(--radius-md);box-shadow:var(--shadow-sm);padding:2rem}
.surah-header{text-align:center;margin-bottom:1.75rem;padding-bottom:1.25rem;border-bottom:2px solid var(--color-gold)}
.surah-header h2{font-family:'Amiri',serif;font-size:1.8rem;color:var(--color-primary)}
.surah-header p{color:var(--color-muted);font-size:.88rem;margin-top:.4rem}
.basmalah{font-family:'Amiri',serif;font-size:1.5rem;text-align:center;color:var(--color-gold-dark);margin-bottom:1.5rem}
.ayahs-container{line-height:2.6;font-family:'Amiri',serif;font-size:1.35rem;text-align:justify;color:#1a1a1a}
.ayah-span{display:inline;padding:.1rem .2rem;border-radius:4px;transition:background .2s;position:relative}
/* Teacher view: hover effect only, no click coloring */
.ayah-span.teacher-view{cursor:default}
.ayah-span.teacher-view:hover{background:rgba(212,175,55,.08)}
/* Marks from student */
.ayah-span.memorized{background:rgba(44,74,62,.16);border-radius:4px}
.ayah-span.reviewing{background:rgba(245,158,11,.18);border-radius:4px}
.ayah-span.last-stop,
.ayah-span.last_stop{background:rgba(59,130,246,.22);border-radius:4px;border-bottom:2px solid #3b82f6}

.ayah-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:var(--color-cream);color:var(--color-gold-dark);font-size:.78rem;font-weight:700;margin:0 .3rem;vertical-align:middle;border:1px solid rgba(212,175,55,.3)}

.loading-ayahs{text-align:center;padding:3rem;color:var(--color-muted)}
.loading-ayahs .loading-spinner{margin:0 auto 1rem}
.empty-surah{text-align:center;padding:4rem;color:var(--color-muted)}
.empty-surah p{margin-top:.5rem;font-size:.9rem}

/* Student progress bar */
.progress-banner{background:linear-gradient(135deg,rgba(27,67,50,.06),rgba(45,106,79,.04));border:1px solid rgba(45,106,79,.15);border-radius:var(--radius-md);padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
.progress-banner .pb-label{font-size:.88rem;font-weight:700;color:var(--color-primary)}
.progress-banner .pb-stats{display:flex;gap:1rem;flex-wrap:wrap;margin-right:auto}
.pb-stat{text-align:center}
.pb-stat-val{font-size:1.1rem;font-weight:700;color:var(--color-secondary)}
.pb-stat-lbl{font-size:.72rem;color:var(--color-muted)}
.progress-wrap{flex:1;min-width:120px;background:#e5e7eb;border-radius:10px;height:8px;overflow:hidden}
.progress-fill{height:100%;background:linear-gradient(90deg,var(--color-secondary),var(--color-gold));border-radius:10px;transition:.5s}

@media(max-width:900px){
    .quran-layout{grid-template-columns:1fr}
    .quran-sidebar{position:static;max-height:none}
    .surah-list{max-height:220px}
}

/* ══ SIDEBAR المعلم ══ */
.sidebar{position:fixed;top:0;right:0;width:280px;height:100vh;background:linear-gradient(180deg,#1C3329 0%,#0F1D17 100%);display:flex;flex-direction:column;z-index:200;box-shadow:-4px 0 30px rgba(0,0,0,.15);transition:transform .3s ease;}
.sb-logo{padding:32px 24px;border-bottom:1px solid rgba(255,255,255,.06);text-align:center;}
.sb-logo img{max-height:48px;width:auto;object-fit:contain;margin:0 auto 8px;display:block;}
.sb-logo span{color:#D4AF37;font-size:2.3rem;font-weight:700;font-family:'Amiri',serif;line-height:1;}
.sb-user-row{display:flex;align-items:center;gap:14px;padding:22px 24px;border-bottom:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.02);}
.sb-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#f3e7c4,#D4AF37);color:#1C3329;font-weight:800;font-size:1.1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sb-user-name{color:#fff;font-size:.87rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.sb-user-role{color:#C5A880;font-size:.72rem;margin-top:1px;}
.sb-section-label{font-size:.74rem;color:rgba(255,255,255,.35);font-weight:800;padding:12px 24px 6px;}
.sb-nav{padding:20px 0;flex:1;overflow-y:auto;}
.sb-item{display:flex;align-items:center;gap:14px;padding:12px 24px;color:rgba(255,255,255,.72);font-size:.95rem;text-decoration:none;transition:all .22s ease;position:relative;}
.sb-item:hover,.sb-item.active{background:rgba(255,255,255,.08);color:#fff;}
.sb-item.active::after{content:'';position:absolute;right:0;top:8px;bottom:8px;width:4px;background:#D4AF37;border-radius:4px 0 0 4px;}
.sb-ic{width:20px;font-size:.95rem;color:#D4AF37;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;}
.sb-notif-dot{position:absolute;left:12px;top:50%;transform:translateY(-50%);background:#E74C3C;color:#fff;font-size:.62rem;font-weight:700;min-width:17px;height:17px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 4px;}
.sb-bottom{padding:20px 24px;border-top:1px solid rgba(255,255,255,.06);}
.sb-logout{display:flex;align-items:center;gap:13px;padding:10px 14px;border-radius:12px;color:rgba(255,120,120,.75);font-size:.95rem;text-decoration:none;transition:all .22s ease;}
.sb-logout:hover{background:rgba(255,0,0,.08);}
/* override quran-layout margin */
body{background:#f5f5f5;}
.quran-layout{margin-right:280px!important;}
@media(max-width:900px){
  .sidebar{transform:translateX(280px);}
  .sidebar.open{transform:translateX(0);}
  .quran-layout{margin-right:0!important;grid-template-columns:1fr;}
}

/* Modern RATTIL Quran teacher UI - presentation only */
:root{
  --qt-olive-950:#101c16;
  --qt-olive-900:#1C3329;
  --qt-olive-800:#263f34;
  --qt-olive-700:#2C4A3E;
  --qt-olive-600:#496857;
  --qt-olive-100:#EAECE6;
  --qt-olive-50:#F3F4EE;
  --qt-beige:#FAF7F0;
  --qt-beige-2:#F4EFE6;
  --qt-beige-3:#EFE9DC;
  --qt-gold:#D4AF37;
  --qt-text:#2F3633;
  --qt-muted:#707A75;
  --qt-border:rgba(44,74,62,.12);
  --qt-shadow:0 18px 45px rgba(28,51,41,.08);
  --qt-shadow-sm:0 8px 22px rgba(28,51,41,.055);
  --qt-radius-lg:22px;
  --qt-radius-md:16px;
  --qt-radius-sm:11px;
}

body{
  font-family:'Tajawal',sans-serif;
  background:
    radial-gradient(circle at top left, rgba(212,175,55,.10), transparent 28rem),
    linear-gradient(180deg,var(--qt-beige) 0%,#F8F4EB 100%);
  color:var(--qt-text);
}

.quran-layout{
  max-width:1460px;
  gap:1.35rem;
  padding:1.5rem 1.5rem 3rem;
  margin-top:0!important;
}

.quran-sidebar{gap:1.15rem;top:1.5rem}
.sidebar-card,
.quran-controls,
.quran-display,
.progress-banner{
  background:rgba(255,255,255,.72);
  border:1px solid var(--qt-border);
  border-radius:var(--qt-radius-lg);
  box-shadow:var(--qt-shadow-sm);
  backdrop-filter:blur(16px);
}

.sidebar-card{overflow:hidden;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease}
.sidebar-card:hover{transform:translateY(-2px);box-shadow:var(--qt-shadow);border-color:rgba(44,74,62,.2)}
.sidebar-card-head{
  background:linear-gradient(135deg,var(--qt-olive-900),var(--qt-olive-700));
  color:#fbf7eb;
  padding:1rem 1.15rem;
  font-family:'Tajawal',sans-serif;
  font-weight:800;
  letter-spacing:0;
}
.sidebar-card-head i{color:var(--qt-gold);font-size:1rem}

.student-select-wrap{padding:1rem}
.student-mode-bar{
  background:var(--qt-olive-50);
  border:1px solid var(--qt-border);
  border-radius:14px;
  padding:.3rem;
  gap:.3rem;
}
.mode-btn{
  border:0;
  border-radius:11px;
  background:transparent;
  color:var(--qt-muted);
  padding:.62rem .55rem;
  font-weight:800;
}
.mode-btn i{margin-left:.35rem;color:var(--qt-olive-600)}
.mode-btn:hover{background:rgba(255,255,255,.8);color:var(--qt-olive-900)}
.mode-btn.active{
  background:linear-gradient(135deg,var(--qt-olive-900),var(--qt-olive-700));
  color:#fff;
  box-shadow:0 8px 18px rgba(28,51,41,.16);
}
.mode-btn.active i{color:var(--qt-gold)}

.student-list{gap:.55rem}
.student-btn{
  background:#fff;
  border:1px solid rgba(44,74,62,.1);
  border-radius:14px;
  padding:.78rem .9rem;
  font-weight:800;
  box-shadow:0 4px 12px rgba(28,51,41,.035);
}
.student-btn:hover{background:var(--qt-beige-2);transform:translateX(-2px)}
.student-btn.active{
  background:linear-gradient(135deg,var(--qt-olive-800),var(--qt-olive-600));
  color:#fff;
  border-color:transparent;
}
.student-meta{color:inherit;font-weight:600}
.student-meta i{margin-left:.25rem;color:var(--qt-gold)}

.surah-search{padding:.9rem;border-bottom:1px solid var(--qt-border)}
.surah-search input,
.reader-select{
  border:1px solid rgba(44,74,62,.16);
  border-radius:13px;
  background:#fff;
  color:var(--qt-text);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.8);
  transition:border-color .2s ease,box-shadow .2s ease,background .2s ease;
}
.surah-search input{padding:.72rem .9rem;font-family:'Tajawal',sans-serif}
.surah-search input:focus,
.reader-select:focus{
  border-color:var(--qt-olive-600);
  box-shadow:0 0 0 4px rgba(44,74,62,.09);
  background:#fff;
}
.surah-item{
  margin:.35rem .65rem;
  padding:.75rem .8rem;
  border:1px solid transparent;
  border-radius:14px;
  border-bottom:0;
}
.surah-item:hover{background:var(--qt-beige-2);border-color:rgba(44,74,62,.09)}
.surah-item.active{
  background:linear-gradient(135deg,var(--qt-olive-100),rgba(255,255,255,.9));
  border:1px solid rgba(44,74,62,.18);
  border-right:4px solid var(--qt-olive-700);
}
.surah-num{
  background:#fff;
  color:var(--qt-olive-900);
  border:1px solid rgba(44,74,62,.15);
}
.surah-name{color:var(--qt-olive-900);font-family:'Tajawal',sans-serif}
.surah-ayahs{color:var(--qt-muted)}

.quran-main{gap:1.15rem}
.quran-controls{
  padding:1rem 1.15rem;
  position:sticky;
  top:1rem;
  z-index:20;
}
.view-mode-badge{
  display:inline-flex;
  align-items:center;
  gap:.45rem;
  border:1px solid rgba(44,74,62,.14);
  border-radius:999px;
  padding:.48rem .9rem;
  font-weight:800;
}
.mode-teacher,.mode-student{background:var(--qt-olive-100);color:var(--qt-olive-900)}
.reader-select{padding:.58rem .85rem;font-weight:700}
.audio-player{background:var(--qt-olive-50);border:1px solid var(--qt-border);border-radius:999px;padding:.35rem .45rem .35rem .75rem}
.audio-player audio{height:32px}
.play-btn{
  width:40px;
  height:40px;
  background:linear-gradient(135deg,var(--qt-olive-900),var(--qt-olive-600));
  box-shadow:0 10px 20px rgba(28,51,41,.18);
}
.play-btn:hover{transform:translateY(-1px) scale(1.04);box-shadow:0 14px 26px rgba(28,51,41,.24)}
.legend{
  background:rgba(255,255,255,.65);
  border:1px solid var(--qt-border);
  border-radius:999px;
  padding:.5rem .75rem;
  color:var(--qt-muted);
  font-weight:700;
}
.legend-dot{box-shadow:inset 0 0 0 1px rgba(44,74,62,.18)}
.dot-memorized{background:var(--qt-olive-700)}
.dot-reviewing{background:var(--qt-gold)}
.dot-last-stop{background:var(--qt-olive-100);border:1px solid var(--qt-olive-700)}

.progress-banner{
  background:linear-gradient(135deg,rgba(234,236,230,.9),rgba(255,255,255,.74));
  padding:1.05rem 1.25rem;
}
.progress-banner .pb-label{
  color:var(--qt-olive-900);
  font-weight:800;
  display:flex;
  align-items:center;
  gap:.45rem;
}
.pb-stat{
  min-width:74px;
  background:#fff;
  border:1px solid rgba(44,74,62,.1);
  border-radius:14px;
  padding:.55rem .7rem;
}
.pb-stat-val{color:var(--qt-olive-900);font-size:1.22rem}
.pb-stat-lbl{color:var(--qt-muted);font-weight:700}
.progress-wrap{height:10px;background:rgba(44,74,62,.12)}
.progress-fill{background:linear-gradient(90deg,var(--qt-olive-900),var(--qt-olive-600),var(--qt-gold))}

.quran-display{
  padding:2rem;
  box-shadow:var(--qt-shadow);
}
.surah-header{
  margin-bottom:1.5rem;
  padding:1.3rem 1rem 1.1rem;
  border:1px solid rgba(44,74,62,.1);
  border-radius:20px;
  background:linear-gradient(135deg,var(--qt-beige-2),rgba(255,255,255,.86));
}
.surah-header h2{
  font-family:'Scheherazade New','Amiri',serif;
  font-size:2.35rem;
  color:var(--qt-olive-900);
  line-height:1.25;
}
.surah-header p{font-family:'Tajawal',sans-serif;color:var(--qt-muted);font-weight:700}
.basmalah{
  font-family:'Scheherazade New','Amiri',serif;
  color:var(--qt-olive-900);
  background:linear-gradient(90deg,transparent,rgba(212,175,55,.12),transparent);
  border-radius:18px;
  padding:.8rem;
}
.ayahs-container{
  font-family:'Scheherazade New','Amiri',serif;
  font-size:1.72rem;
  line-height:2.85;
  color:#1f2924;
  word-spacing:3px;
}
.ayah-span{
  border-radius:8px;
  padding:.08rem .24rem;
  transition:background .2s ease,box-shadow .2s ease,color .2s ease;
}
.ayah-span.teacher-view:hover{background:rgba(44,74,62,.08)}
.ayah-span.memorized{background:rgba(44,74,62,.16);box-shadow:inset 0 -2px var(--qt-olive-700)}
.ayah-span.reviewing{background:rgba(212,175,55,.18);box-shadow:inset 0 -2px var(--qt-gold)}
.ayah-span.last-stop,
.ayah-span.last_stop{background:rgba(234,236,230,.95);box-shadow:inset 0 -2px var(--qt-olive-600);border-bottom:0}
.ayah-num{
  width:30px;
  height:30px;
  background:#fff;
  color:var(--qt-olive-900);
  border:1px solid rgba(44,74,62,.18);
  font-family:'Tajawal',sans-serif;
  box-shadow:0 3px 8px rgba(28,51,41,.06);
}
.empty-surah,
.loading-ayahs{
  border:1px dashed rgba(44,74,62,.18);
  border-radius:20px;
  background:rgba(250,247,240,.72);
}
.empty-icon{
  width:66px;
  height:66px;
  border-radius:22px;
  margin:0 auto 1rem;
  display:flex;
  align-items:center;
  justify-content:center;
  background:linear-gradient(135deg,var(--qt-olive-100),#fff);
  color:var(--qt-olive-900);
  font-size:1.7rem;
  border:1px solid rgba(44,74,62,.12);
}
.loading-spinner,
.spinner{
  border-color:rgba(44,74,62,.14)!important;
  border-top-color:var(--qt-olive-800)!important;
}

.sidebar{
  background:linear-gradient(180deg,var(--qt-olive-900) 0%,var(--qt-olive-950) 100%);
}
.sb-logo span,.sb-ic{color:var(--qt-gold)}
.sb-avatar{background:linear-gradient(135deg,#F3E7C4,var(--qt-gold));color:var(--qt-olive-900)}
.sb-item:hover,.sb-item.active{background:rgba(255,255,255,.075)}
.sb-item.active{color:#fff}
.sb-item.active::after{background:var(--qt-gold)}

/* Match teacher dashboard sidebar exactly */
.sidebar{width:280px;background:linear-gradient(180deg,var(--primary) 0%,#0F1D17 100%);min-height:100vh;position:fixed;right:0;top:0;z-index:100;display:flex;flex-direction:column;transition:var(--transition);box-shadow:-4px 0 30px rgba(0,0,0,0.15)}
.sidebar-logo{padding:32px 24px;border-bottom:1px solid rgba(255,255,255,0.06);text-align:center}
.sidebar-logo .logo-text{font-family:'Amiri',serif;font-size:2.4rem;color:var(--gold);line-height:1;font-weight:700;text-shadow:0 2px 4px rgba(0,0,0,0.2)}
.sidebar-logo .logo-sub{font-size:.85rem;color:rgba(255,255,255,0.5);margin-top:6px;letter-spacing:.5px}
.sidebar-user{padding:24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:14px;background:rgba(255,255,255,0.02)}
.avatar-circle{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#f3e7c4 0%,var(--gold) 100%);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem;color:var(--primary);flex-shrink:0;box-shadow:0 4px 10px rgba(0,0,0,0.15)}
.sidebar-user-info .name{color:var(--white);font-weight:600;font-size:.98rem}
.sidebar-user-info .role{color:var(--gold);font-size:.8rem;margin-top:2px;display:flex;align-items:center;gap:4px}
.sidebar-nav{flex:1;padding:20px 0;overflow-y:auto}
.nav-section{padding:12px 24px 6px;font-size:.75rem;color:rgba(255,255,255,0.35);font-weight:700;letter-spacing:.8px}
.nav-link{display:flex;align-items:center;gap:14px;padding:12px 24px;color:rgba(255,255,255,0.7);text-decoration:none;transition:var(--transition);font-size:.95rem;position:relative}
.nav-link svg{width:20px;height:20px;opacity:.7;transition:var(--transition)}
.nav-link:hover,.nav-link.active{background:rgba(255,255,255,0.05);color:var(--white)}
.nav-link:hover svg,.nav-link.active svg{opacity:1;transform:scale(1.05)}
.nav-link.active{background:linear-gradient(90deg,rgba(255,255,255,0.06) 0%,rgba(255,255,255,0) 100%);color:var(--gold)}
.nav-link.active::before{content:'';position:absolute;right:0;top:8px;bottom:8px;width:4px;background:var(--gold);border-radius:4px 0 0 4px;box-shadow:-2px 0 10px var(--gold)}
.badge-count{margin-right:auto;background:var(--gold);color:var(--primary);border-radius:20px;padding:2px 8px;font-size:.75rem;font-weight:700;box-shadow:0 2px 6px rgba(212,175,55,0.3)}
.sidebar-footer{padding:20px 24px;border-top:1px solid rgba(255,255,255,0.06)}
.logout-btn,.sb-logout{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:.9rem;transition:var(--transition)}
.sb-logout:hover{color:#ff6b6b;background:transparent}
.logout-btn:hover{color:#ff6b6b}
.sidebar-logo img,.sidebar-logo>span{display:none}
.sidebar-logo::before{content:'رتّل';display:block;font-family:'Amiri',serif;font-size:2.4rem;color:var(--gold);line-height:1;font-weight:700;text-shadow:0 2px 4px rgba(0,0,0,0.2)}
.sidebar-logo::after{content:'لوحة المعلم';display:block;font-size:.85rem;color:rgba(255,255,255,0.5);margin-top:6px;letter-spacing:.5px}
.sidebar-user .sb-user-role{display:none}
.sidebar-user-info::after{content:'معلم متميز';display:flex;align-items:center;gap:4px;color:var(--gold);font-size:.8rem;margin-top:2px}
.sidebar-user>.avatar-circle+div .name{color:var(--white);font-weight:600;font-size:.98rem}
.sidebar-user>.avatar-circle+div::after{content:'معلم متميز';display:flex;align-items:center;gap:4px;color:var(--gold);font-size:.8rem;margin-top:2px}
.sidebar-nav>.sb-section-label{display:none}
.sidebar-nav::before{content:'القائمة الرئيسية';display:block;padding:12px 24px 6px;font-size:.75rem;color:rgba(255,255,255,0.35);font-weight:700;letter-spacing:.8px}
.sb-item{display:flex;align-items:center;gap:14px;padding:12px 24px;color:rgba(255,255,255,0.7);text-decoration:none;transition:var(--transition);font-size:.95rem;position:relative}
.sb-item:hover,.sb-item.active{background:rgba(255,255,255,0.05);color:var(--white)}
.sb-item.active{background:linear-gradient(90deg,rgba(255,255,255,0.06) 0%,rgba(255,255,255,0) 100%);color:var(--gold)}
.sb-item.active::before{content:'';position:absolute;right:0;top:8px;bottom:8px;width:4px;background:var(--gold);border-radius:4px 0 0 4px;box-shadow:-2px 0 10px var(--gold)}
.sb-item.active::after{display:none}
.sb-ic{width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;opacity:.7;transition:var(--transition)}
.sb-item:hover .sb-ic,.sb-item.active .sb-ic{opacity:1;transform:scale(1.05)}
a[href="/rattil/teacher/quran_tracking.php"]{display:none}
.sb-notif-dot{margin-right:auto;background:var(--gold);color:var(--primary);border-radius:20px;padding:2px 8px;font-size:.75rem;font-weight:700;box-shadow:0 2px 6px rgba(212,175,55,0.3)}
.logout-btn{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,0.5);text-decoration:none;font-size:.9rem;transition:var(--transition)}
.sidebar-nav::before{display:none!important}
.sidebar-nav .nav-section{display:block}

@media(max-width:1100px){
  .quran-layout{grid-template-columns:260px minmax(0,1fr);padding:1rem}
  .quran-controls{position:static}
}
@media(max-width:900px){
  body{display:block}
  .quran-layout{padding:1rem;gap:1rem}
  .quran-sidebar{position:static}
  .quran-display{padding:1.25rem}
  .quran-controls{align-items:stretch}
  .audio-player,.legend{width:100%;border-radius:16px}
}
@media(max-width:620px){
  .quran-layout{padding:.75rem}
  .student-mode-bar,.progress-banner .pb-stats{grid-template-columns:1fr;display:grid}
  .quran-controls{padding:.85rem}
  .surah-header h2{font-size:1.9rem}
  .ayahs-container{font-size:1.38rem;line-height:2.6}
  .progress-banner{align-items:stretch}
  .progress-wrap{width:100%;flex-basis:100%}
  .pb-stat{min-width:0}
}

</style>
</head>
<body>

<!-- Navbar -->
<!-- SIDEBAR المعلم — نفس ثيم رتِّل -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="/rattil/assets/images/logo.png" alt="رتِّل" onerror="this.style.display='none'">
    <span>رتِّل</span>
  </div>
  <div class="sidebar-user">
    <div class="avatar-circle"><?= mb_substr($user['name'] ?? 'م',0,1) ?></div>
    <div>
      <div class="name"><?= htmlspecialchars($user['name'] ?? 'المعلم') ?></div>
      <div class="sb-user-role">معلم</div>
    </div>
  </div>
  <div class="sb-section-label">القائمة الرئيسية</div>
  <div class="sidebar-nav">
    <a href="/rattil/teacher/dashboard.php"      class="sb-item"><span class="sb-ic"><i class="fa-solid fa-table-columns"></i></span>الرئيسية</a>
    <a href="/rattil/teacher/quran.php"          class="sb-item active"><span class="sb-ic"><i class="fa-solid fa-book-open"></i></span>المصحف</a>
    <a href="/rattil/teacher/quran_tracking.php" class="sb-item"><span class="sb-ic"><i class="fa-solid fa-list-check"></i></span>مصحف التتبع</a>
    <a href="/rattil/teacher/halaqat.php"        class="sb-item"><span class="sb-ic"><i class="fa-solid fa-mosque"></i></span>الحلقات</a>
    <a href="/rattil/teacher/students.php"       class="sb-item"><span class="sb-ic"><i class="fa-solid fa-user-graduate"></i></span>الطلاب</a>
    <a href="/rattil/teacher/recitation_log.php" class="sb-item"><span class="sb-ic"><i class="fa-solid fa-microphone-lines"></i></span>سجل التسميع</a>
    <a href="/rattil/teacher/attendance.php"     class="sb-item"><span class="sb-ic"><i class="fa-solid fa-calendar-check"></i></span>الحضور</a>
    <a href="/rattil/teacher/messages.php"       class="sb-item"><span class="sb-ic"><i class="fa-solid fa-comment-dots"></i></span>الرسائل
      <?php if($unread>0): ?><span class="sb-notif-dot"><?= $unread ?></span><?php endif; ?>
    </a>
    <a href="/rattil/teacher/settings.php"       class="sb-item"><span class="sb-ic"><i class="fa-solid fa-sliders"></i></span>الإعدادات</a>
  </div>
  <div class="sidebar-footer">
    <a href="/rattil/logout.php" class="sb-logout"><span class="sb-ic"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>تسجيل الخروج</a>
  </div>
</nav>
<div class="overlay" id="overlay" onclick="closeAll()"></div>
<div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header"><h3>الإشعارات</h3><button class="btn-sm" onclick="markAllRead()">قراءة الكل</button></div>
    <div id="notifList"><div class="loading-spinner"></div></div>
</div>

<div class="quran-layout">

    <!-- ── Sidebar ── -->
    <aside class="quran-sidebar">

        <!-- Student selector -->
        <div class="sidebar-card">
            <div class="sidebar-card-head"><i class="fa-solid fa-chalkboard-user"></i> وضع العرض</div>
            <div class="student-select-wrap">
                <div class="student-mode-bar">
                    <button class="mode-btn <?= !$viewStudentId?'active':'' ?>" onclick="setMode('teacher')"><i class="fa-solid fa-book-open"></i> مصحفي</button>
                    <button class="mode-btn <?= $viewStudentId?'active':'' ?>" onclick="setMode('student')"><i class="fa-solid fa-user-round"></i> تقدم طالب</button>
                </div>
                <div id="studentListWrap" style="<?= !$viewStudentId?'display:none':'' ?>">
                    <p style="font-size:.78rem;color:var(--color-muted);margin-bottom:.5rem">اختر طالباً لمتابعة تقدمه:</p>
                    <div class="student-list">
                        <?php foreach($students as $s): ?>
                        <button class="student-btn <?= $viewStudentId==$s['id']?'active':'' ?>"
                            onclick="selectStudent(<?= $s['id'] ?>)">
                            <div><?= htmlspecialchars($s['name']) ?></div>
                            <div class="student-meta"><i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars($s['halqa_name']) ?></div>
                        </button>
                        <?php endforeach; ?>
                        <?php if(empty($students)): ?>
                        <p style="font-size:.82rem;color:var(--color-muted);text-align:center;padding:.5rem">لا يوجد طلاب في حلقاتك</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Surah list -->
        <div class="sidebar-card">
            <div class="sidebar-card-head"><i class="fa-solid fa-book-quran"></i> السور الكريمة</div>
            <div class="surah-search">
                <input type="text" id="surahSearch" placeholder="ابحث عن سورة..." oninput="filterSurahs(this.value)">
            </div>
            <div class="surah-list" id="surahList">
                <div class="loading-spinner" style="margin:1.5rem auto"></div>
            </div>
        </div>
    </aside>

    <!-- ── Main ── -->
    <main class="quran-main">

        <!-- Controls -->
        <div class="quran-controls">
            <button class="notif-btn" type="button" title="الإشعارات" aria-label="الإشعارات">
                <i class="fa-solid fa-bell"></i>
                <?php if($unread>0): ?><span class="notif-badge" id="notifBadge"><?= $unread ?></span><?php endif; ?>
            </button>
            <div>
                <span class="view-mode-badge <?= $viewStudentId?'mode-student':'mode-teacher' ?>" id="modeBadge">
                    <?= $viewStudentId ? '<i class="fa-solid fa-user-round"></i> تتابع: '.htmlspecialchars($viewStudent['name']??'') : '<i class="fa-solid fa-book-open"></i> وضع القراءة' ?>
                </span>
            </div>

            <!-- Reader select -->
            <select class="reader-select" id="readerSelect" onchange="changeReader()">
                <option value="ar.minshawi">المنشاوي</option>
                <option value="ar.alafasy">العفاسي</option>
                <option value="ar.abdurrahmanassudais">السديس</option>
                <option value="ar.husary">الحصري</option>
            </select>

            <!-- Audio -->
            <div class="audio-player">
                <button class="play-btn" id="playBtn" onclick="togglePlayCurrent()" title="تشغيل الآية الحالية"><i class="fa-solid fa-play"></i></button>
                <audio id="ayahAudio" onended="onAudioEnded()"></audio>
            </div>

            <!-- Legend (student marks) -->
            <div class="legend">
                <span><span class="legend-dot dot-memorized"></span>محفوظ</span>
                <span><span class="legend-dot dot-reviewing"></span>مراجعة</span>
                <span><span class="legend-dot dot-last-stop"></span>آخر توقف</span>
            </div>
        </div>

        <!-- Student progress banner -->
        <?php if($viewStudent): ?>
        <div class="progress-banner" id="progressBanner">
            <span class="pb-label"><i class="fa-solid fa-chart-line"></i> تقدم <?= htmlspecialchars($viewStudent['name']) ?> في هذه السورة</span>
            <div class="pb-stats">
                <div class="pb-stat"><div class="pb-stat-val" id="memorizedCount">—</div><div class="pb-stat-lbl">محفوظة</div></div>
                <div class="pb-stat"><div class="pb-stat-val" id="reviewingCount">—</div><div class="pb-stat-lbl">مراجعة</div></div>
                <div class="pb-stat"><div class="pb-stat-val" id="totalCount">—</div><div class="pb-stat-lbl">إجمالي الآيات</div></div>
            </div>
            <div class="progress-wrap"><div class="progress-fill" id="progressFill" style="width:0%"></div></div>
        </div>
        <?php endif; ?>

        <!-- Quran display -->
        <div class="quran-display" id="quranDisplay">
            <div class="empty-surah">
                <div class="empty-icon"><i class="fa-solid fa-book-open"></i></div>
                <p>اختر سورة من القائمة للبدء</p>
            </div>
        </div>
    </main>
</div>

<script src="/rattil/assets/js/main.js"></script>
<script src="/rattil/assets/js/notifications.js?v=20260518"></script>
<script>
/* ── State ── */
const TEACHER_MODE  = <?= $viewStudentId ? 'false' : 'true' ?>;
const VIEW_STUDENT  = <?= $viewStudentId ?: 'null' ?>;
let   viewStudentId = VIEW_STUDENT;
let   currentSurah  = null;
let   allSurahs     = [];
let   currentMarks  = {};      // {ayahNum: status}
let   currentAyah   = 1;
let   isPlaying     = false;

/* ── Boot ── */
document.addEventListener('DOMContentLoaded', async () => {
    syncTeacherSidebar();
    await loadSurahList();
});

function syncTeacherSidebar() {
    const nav = document.querySelector('.sidebar-nav');
    if (!nav) return;

    const unreadCount = <?= (int)$unread ?>;
    nav.innerHTML = `
        <div class="nav-section">القائمة الرئيسية</div>
        <a class="nav-link" href="dashboard.php">
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
        <a class="nav-link active" href="quran.php">
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
            ${unreadCount > 0 ? `<span class="badge-count">${unreadCount}</span>` : ''}
        </a>`;
    return;

    const labels = {
        '/rattil/teacher/dashboard.php': 'لوحة التحكم',
        '/rattil/teacher/halaqat.php': 'حلقاتي',
        '/rattil/teacher/students.php': 'الطلاب',
        '/rattil/teacher/recitation_log.php': 'تسجيل التسميع',
        '/rattil/teacher/attendance.php': 'الحضور والغياب',
        '/rattil/teacher/quran.php': 'القرآن الكريم',
        '/rattil/teacher/messages.php': 'الرسائل',
        '/rattil/teacher/settings.php': 'الإعدادات',
    };

    nav.querySelectorAll('.sb-item').forEach(link => {
        if (link.getAttribute('href') === '/rattil/teacher/quran_tracking.php') return;
        const label = labels[link.getAttribute('href')];
        if (!label) return;
        Array.from(link.childNodes)
            .filter(node => node.nodeType === Node.TEXT_NODE)
            .forEach(node => node.textContent = '');
        link.append(document.createTextNode(label));
    });

    const learning = nav.querySelector('a[href="/rattil/teacher/recitation_log.php"]');
    const account = nav.querySelector('a[href="/rattil/teacher/messages.php"]');
    if (learning && !document.getElementById('learningNavSection')) {
        const section = document.createElement('div');
        section.id = 'learningNavSection';
        section.className = 'nav-section';
        section.textContent = 'إدارة التعلم';
        learning.before(section);
    }
    if (account && !document.getElementById('accountNavSection')) {
        const section = document.createElement('div');
        section.id = 'accountNavSection';
        section.className = 'nav-section';
        section.textContent = 'الحساب';
        account.before(section);
    }
}

/* ── Load surah list from our local PHP proxy ── */
async function loadSurahList() {
    try {
        const r = await fetch('/rattil/api/quran_text.php?action=surah_list');
        const d = await r.json();
        if (!d.success) throw new Error(d.error);
        allSurahs = d.data;
        renderSurahList(allSurahs);
    } catch(e) {
        document.getElementById('surahList').innerHTML =
            '<p style="padding:1rem;text-align:center;color:var(--color-danger)">فشل تحميل قائمة السور: '+e.message+'</p>';
    }
}

function renderSurahList(list) {
    document.getElementById('surahList').innerHTML = list.map(s => `
        <div class="surah-item ${currentSurah?.number===s.number?'active':''}"
             id="si-${s.number}" onclick="loadSurah(${s.number})">
            <div class="surah-num">${s.number}</div>
            <div class="surah-info">
                <div class="surah-name">${s.name}</div>
                <div class="surah-ayahs">${s.numberOfAyahs} آية · ${s.revelationType==='Meccan'?'مكية':'مدنية'}</div>
            </div>
        </div>`).join('');
}

function filterSurahs(q) {
    const filtered = q.trim()
        ? allSurahs.filter(s => s.name.includes(q) || String(s.number).includes(q))
        : allSurahs;
    renderSurahList(filtered);
}

/* ── Load Surah ── */
async function loadSurah(num) {
    currentSurah = allSurahs.find(s => s.number === num);
    currentAyah  = 1;
    stopAudio();

    /* highlight active in list */
    document.querySelectorAll('.surah-item').forEach(el => el.classList.remove('active'));
    const si = document.getElementById('si-'+num);
    if (si) { si.classList.add('active'); si.scrollIntoView({block:'nearest',behavior:'smooth'}); }

    /* loading */
    document.getElementById('quranDisplay').innerHTML =
        '<div class="loading-ayahs"><div class="loading-spinner"></div><p>جارٍ تحميل السورة...</p></div>';

    try {
        /* Fetch ayah text */
        const r = await fetch(`/rattil/api/quran_text.php?action=surah&num=${num}`);
        const d = await r.json();
        if (!d.success) throw new Error(d.error || 'فشل تحميل السورة');
        const surah = d.data;

        /* Fetch marks (teacher sees student marks, or none for teacher-self mode) */
        currentMarks = {};
        const watchId = viewStudentId || 0;
        if (watchId) {
            const mr = await fetch(`/rattil/api/quran.php?action=get_marks&surah=${num}&student_id=${watchId}`);
            const md = await mr.json();
            if (md.success) md.data.forEach(m => { currentMarks[m.ayah_number] = m.status; });
        }

        renderSurah(surah);
        if (watchId) updateProgress(surah.numberOfAyahs);

    } catch(e) {
        document.getElementById('quranDisplay').innerHTML =
            `<div class="empty-surah"><p style="color:var(--color-danger)"><i class="fa-solid fa-triangle-exclamation"></i> ${e.message}</p></div>`;
    }
}

function renderSurah(surah) {
    const basmalah = surah.number !== 1 && surah.number !== 9
        ? '<div class="basmalah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>' : '';

    const ayahsHtml = surah.ayahs.map(a => {
        const status  = currentMarks[a.numberInSurah] || '';
        const cls     = status ? ` ${status} teacher-view` : ' teacher-view';
        return `<span class="ayah-span${cls}" data-ayah="${a.numberInSurah}"
                      onclick="handleAyahClick(${a.numberInSurah})"
                      title="الآية ${a.numberInSurah}">${a.text}
                    <span class="ayah-num">${toArabicNum(a.numberInSurah)}</span>
                </span> `;
    }).join('');

    document.getElementById('quranDisplay').innerHTML = `
        <div class="surah-header">
            <h2>سورة ${surah.name}</h2>
            <p>${surah.numberOfAyahs} آية · ${surah.revelationType==='Meccan'?'مكية':'مدنية'}
            ${viewStudentId ? ' · <strong style="color:var(--color-secondary)">يعرض تقدم الطالب</strong>' : ''}</p>
        </div>
        ${basmalah}
        <div class="ayahs-container">${ayahsHtml}</div>`;
}

/* ── Ayah click → play audio (teacher mode: only audio, no coloring) ── */
function handleAyahClick(ayahNum) {
    currentAyah = ayahNum;
    playAyah(ayahNum);
}

/* ── Progress update ── */
function updateProgress(total) {
    const mem = Object.values(currentMarks).filter(s => s==='memorized').length;
    const rev = Object.values(currentMarks).filter(s => s==='reviewing').length;
    const pct = total > 0 ? Math.round(mem/total*100) : 0;
    const memEl = document.getElementById('memorizedCount');
    const revEl = document.getElementById('reviewingCount');
    const totEl = document.getElementById('totalCount');
    const filEl = document.getElementById('progressFill');
    if (memEl) memEl.textContent = mem;
    if (revEl) revEl.textContent = rev;
    if (totEl) totEl.textContent = total;
    if (filEl) filEl.style.width = pct+'%';
}

/* ── Mode switching ── */
function setMode(mode) {
    document.querySelectorAll('.mode-btn').forEach((b,i) => {
        b.classList.toggle('active', (mode==='teacher'&&i===0)||(mode==='student'&&i===1));
    });
    document.getElementById('studentListWrap').style.display = mode==='student' ? '' : 'none';
    if (mode === 'teacher') {
        viewStudentId = null;
        document.getElementById('modeBadge').innerHTML = '<i class="fa-solid fa-book-open"></i> وضع القراءة';
        document.getElementById('modeBadge').className = 'view-mode-badge mode-teacher';
        const pb = document.getElementById('progressBanner');
        if (pb) pb.style.display = 'none';
        if (currentSurah) loadSurah(currentSurah.number);
    }
}

function selectStudent(sid) {
    viewStudentId = sid;
    document.querySelectorAll('.student-btn').forEach(b => b.classList.remove('active'));
    event.currentTarget.classList.add('active');
    const name = event.currentTarget.querySelector('div').textContent;
    document.getElementById('modeBadge').innerHTML = '<i class="fa-solid fa-user-round"></i> تتابع: '+name;
    document.getElementById('modeBadge').className = 'view-mode-badge mode-student';
    const pb = document.getElementById('progressBanner');
    if (pb) pb.style.display = '';
    if (currentSurah) loadSurah(currentSurah.number);
}

/* ── Audio ── */
const READERS = {
    'ar.minshawi':              'https://cdn.islamic.network/quran/audio/128/ar.minshawi/',
    'ar.alafasy':               'https://cdn.islamic.network/quran/audio/128/ar.alafasy/',
    'ar.abdurrahmanassudais':   'https://cdn.islamic.network/quran/audio/128/ar.abdurrahmanassudais/',
    'ar.husary':                'https://cdn.islamic.network/quran/audio/128/ar.husary/',
};

function getReaderBase() {
    return READERS[document.getElementById('readerSelect').value] || READERS['ar.minshawi'];
}

function getGlobalAyahNum(surahNum, ayahNum) {
    // الأوفست الصحيح — رقم الآية الكلي = OFFSETS[surah] + numberInSurah
    const OFFSETS=[0,0,7,293,493,669,789,954,1160,1235,1364,1473,1596,1707,1750,1802,1901,2029,2140,2250,2348,2483,2595,2673,2791,2855,2932,3159,3252,3340,3409,3469,3503,3533,3606,3660,3705,3788,3970,4058,4133,4218,4272,4325,4414,4473,4510,4545,4583,4612,4630,4675,4735,4784,4846,4901,4979,5075,5104,5126,5150,5163,5177,5188,5199,5217,5229,5241,5271,5323,5375,5419,5447,5475,5495,5551,5591,5622,5672,5712,5758,5800,5829,5848,5884,5909,5931,5948,5967,5993,6023,6043,6058,6079,6090,6098,6106,6125,6130,6138,6146,6157,6168,6176,6179,6188,6193,6197,6204,6207,6213,6216,6221,6225,6230];
    return (OFFSETS[surahNum] || 0) + ayahNum;
}

async function playAyah(ayahNum) {
    if (!currentSurah) return;
    const audio = document.getElementById('ayahAudio');
    const globalNum = getGlobalAyahNum(currentSurah.number, ayahNum);
    const url = getReaderBase() + globalNum + '.mp3';
    audio.src = url;
    audio.play().then(() => {
        isPlaying = true;
        document.getElementById('playBtn').innerHTML = '<i class="fa-solid fa-pause"></i>';
    }).catch(() => {
        showToast('تعذّر تشغيل الصوت', 'error');
    });
}

function togglePlayCurrent() {
    const audio = document.getElementById('ayahAudio');
    if (isPlaying) {
        audio.pause(); isPlaying = false;
        document.getElementById('playBtn').innerHTML = '<i class="fa-solid fa-play"></i>';
    } else {
        if (audio.src) {
            audio.play(); isPlaying = true;
            document.getElementById('playBtn').innerHTML = '<i class="fa-solid fa-pause"></i>';
        } else if (currentSurah) {
            playAyah(1);
        }
    }
}

function onAudioEnded() {
    isPlaying = false;
    document.getElementById('playBtn').innerHTML = '<i class="fa-solid fa-play"></i>';
}

function stopAudio() {
    const audio = document.getElementById('ayahAudio');
    audio.pause(); audio.src = '';
    isPlaying = false;
    document.getElementById('playBtn').innerHTML = '<i class="fa-solid fa-play"></i>';
}

function changeReader() { if (isPlaying) playAyah(currentAyah); }

/* ── Utils ── */
function toArabicNum(n) {
    return String(n).replace(/\d/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
}

function showToast(msg, type='success') {
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

/* ── Notifications ── */
function toggleNotifPanel(){document.getElementById('notifPanel').classList.toggle('show');document.getElementById('overlay').classList.add('show');loadNotifs()}
function closeAll(){document.querySelectorAll('.modal.show').forEach(m=>m.classList.remove('show'));document.getElementById('overlay').classList.remove('show');document.getElementById('notifPanel').classList.remove('show')}
async function loadNotifs(){const r=await fetch('/rattil/api/notifications.php?action=list');const d=await r.json();if(!d.success)return;const list=document.getElementById('notifList');if(!d.data.length){list.innerHTML='<p style="padding:1.5rem;text-align:center;color:var(--color-muted)">لا توجد إشعارات</p>';return;}list.innerHTML=d.data.map(n=>`<div class="notif-item ${n.is_read?'':'unread'}"><div class="notif-item-title">${n.title}</div><div class="notif-item-body">${n.body||''}</div><div class="notif-item-time">${n.created_at}</div></div>`).join('')}
async function markAllRead(){await fetch('/rattil/api/notifications.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=mark_all_read'});document.getElementById('notifBadge').style.display='none'}
document.addEventListener('click',e=>{if(!e.target.closest('.user-menu'))document.querySelectorAll('.user-dropdown.show').forEach(d=>d.classList.remove('show'))});
</script>
</body>
</html>
