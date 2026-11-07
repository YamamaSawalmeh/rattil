<?php
/**
 * student_shared.php — نظام التصميم الموحد لصفحات الطالب في منصة رتِّل
 * يعتمد على نفس CSS Variables والـ Design System الخاص بالأدمن من rattil_shared.php
 */

function student_global_styles(): void {
    // نستدعي CSS العالمي من الأدمن مباشرةً
    require_once __DIR__ . '/../admin/rattil_shared.php';
    rattil_global_styles();
}

/* ─── Sidebar الطالب ──────────────────────────────── */
function student_sidebar(array $user, string $active = 'dashboard', int $unread = 0): void {
    $init  = mb_substr($user['name'], 0, 1, 'UTF-8');
    $links = [
        'dashboard'    => ['/rattil/student/dashboard.php',    'الرئيسية',    '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>'],
        'quran'        => ['/rattil/student/quran.php',         'المصحف',      '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'],
        'halaqat'      => ['/rattil/student/halaqat.php',       'حلقاتي',      '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
        'schedule'     => ['/rattil/student/schedule.php',      'خطة الحفظ',   '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        'achievements' => ['/rattil/student/achievements.php',  'حديقة الإنجاز','<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'],
        'settings'     => ['/rattil/student/settings.php',      'الإعدادات',   '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
    ];
?>
<aside class="rs" id="rs">
  <a href="/rattil/student/dashboard.php" class="rs-logo">
    <img src="/rattil/assets/images/logo.png" alt="رتِّل" onerror="this.style.display='none'">
    <span class="rs-logo-text">رتِّل</span>
  </a>

  <div class="rs-user">
    <div class="rs-av"><?= $init ?></div>
    <div>
      <div class="rs-user-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="rs-user-role">طالب</div>
    </div>
  </div>

  <nav class="rs-nav" role="navigation" aria-label="قائمة الطالب">
    <div class="rs-section">القائمة الرئيسية</div>
    <?php foreach ($links as $key => [$href, $label, $icon]): ?>
    <a href="<?= $href ?>" class="rs-link <?= $active === $key ? 'active' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><?= $icon ?></svg>
      <span><?= $label ?></span>
      <?php if ($key === 'achievements' && $unread > 0): ?>
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
const _obs = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); }), { threshold: .08 });
document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('.anim').forEach(el => _obs.observe(el)));
</script>
<?php }

/* ─── Page Shell ─────────────────────────────────── */
function student_page_start(string $title): void { ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= htmlspecialchars($title) ?> — رتِّل</title>
<?php student_global_styles(); ?>
</head>
<body>
<?php }

/* ─── Emoji Cleaner ─────────────────────────────── */
function removeEmojis(string $text): string {
    $patterns = [
        '/[\x{1F600}-\x{1F64F}]/u',
        '/[\x{1F300}-\x{1F5FF}]/u',
        '/[\x{1F680}-\x{1F6FF}]/u',
        '/[\x{2600}-\x{26FF}]/u',
        '/[\x{2700}-\x{27BF}]/u',
        '/[\x{1F900}-\x{1F9FF}]/u',
        '/[\x{1F004}-\x{1F96B}]/u',
        '/[\x{1F1E6}-\x{1F1FF}]/u',
    ];
    return trim(preg_replace($patterns, '', $text));
}
