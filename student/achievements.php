<?php
require_once __DIR__ . '/../config/db.php';
requireRole('student');

$user = getCurrentUser($pdo);
$notifs = getUnreadCount($pdo);
$sid = (int)$_SESSION['user_id'];

require_once __DIR__ . '/student_shared.php';

$ptsStmt = $pdo->prepare("SELECT * FROM student_points WHERE student_id=?");
$ptsStmt->execute([$sid]);
$pts = $ptsStmt->fetch() ?: ['points'=>0,'total_earned'=>0,'level'=>1];
$pts['level'] = getLevelForPoints((int)$pts['total_earned']);

$treesStmt = $pdo->prepare("SELECT * FROM student_garden WHERE student_id=? ORDER BY planted_at DESC");
$treesStmt->execute([$sid]);
$trees = $treesStmt->fetchAll();

$pointsBadgesStmt = $pdo->prepare("
    SELECT sb.*, b.name, b.icon, b.description
    FROM student_badges sb
    JOIN badges b ON sb.badge_id=b.id
    WHERE sb.student_id=? AND b.type='points_based'
    ORDER BY sb.granted_at DESC
");
$pointsBadgesStmt->execute([$sid]);
$pointsBadges = $pointsBadgesStmt->fetchAll();

$teacherBadgesStmt = $pdo->prepare("
    SELECT sb.*, b.name, b.icon, b.description, u.name AS granter
    FROM student_badges sb
    JOIN badges b ON sb.badge_id=b.id
    LEFT JOIN users u ON sb.granted_by=u.id
    WHERE sb.student_id=? AND b.type='teacher_granted'
    ORDER BY sb.granted_at DESC
");
$teacherBadgesStmt->execute([$sid]);
$teacherBadges = $teacherBadgesStmt->fetchAll();

$levelPoints = [1=>100,2=>500,3=>1000,4=>2000,5=>9999];
$nextLevel = $levelPoints[$pts['level']] ?? 9999;
$progress = $nextLevel > 0 ? min(100, round($pts['total_earned'] / $nextLevel * 100)) : 100;
$remaining = max(0, $nextLevel - (int)$pts['total_earned']);

$shrubs = count(array_filter($trees, fn($t) => $t['tree_type'] === 'shrub'));
$palms = count(array_filter($trees, fn($t) => $t['tree_type'] === 'palm'));
$oaks = count(array_filter($trees, fn($t) => $t['tree_type'] === 'oak'));
$totalTrees = count($trees);
$latestBadge = !empty($teacherBadges) ? $teacherBadges[0] : (!empty($pointsBadges) ? $pointsBadges[0] : null);

$slots = [
    [50,40],[35,47],[64,47],[23,54],[48,55],[75,54],
    [34,64],[60,64],[18,70],[47,73],[76,71],
    [29,81],[59,81],[42,87],[70,87],[51,90],
    [14,61],[86,63],[24,88],[83,82],[37,36],[68,36],
    [12,78],[88,75],[32,90],[66,90],[51,67],[39,58],
];

function treeKindClass(string $type): string {
    return match ($type) {
        'palm' => 'tree-palm',
        'oak' => 'tree-oak',
        default => 'tree-shrub',
    };
}

function latestBadgeSvgIcon(bool $hasBadge): string {
    if ($hasBadge) {
        return '
          <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false">
            <circle class="badge-svg-halo" cx="32" cy="28" r="21"/>
            <circle class="badge-svg-medal" cx="32" cy="28" r="15"/>
            <path class="badge-svg-mark" d="M25.5 28.5 30 33l9-10"/>
            <path class="badge-svg-ribbon right" d="M36 43l8 16 3-8 8 1-10-17"/>
            <path class="badge-svg-ribbon left" d="M28 43 20 59l-3-8-8 1 10-17"/>
          </svg>';
    }

    return '
      <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <path class="badge-svg-cup" d="M22 14h20v10c0 11-5 18-10 18s-10-7-10-18V14Z"/>
        <path class="badge-svg-cup-line" d="M22 20h-8c0 9 4 15 11 16M42 20h8c0 9-4 15-11 16"/>
        <path class="badge-svg-base" d="M32 42v8M24 54h16M20 58h24"/>
        <circle class="badge-svg-star" cx="32" cy="25" r="4"/>
      </svg>';
}

function gardenSeedSvgIcon(): string {
    return '
      <svg viewBox="0 0 64 64" aria-hidden="true" focusable="false">
        <path d="M32 55V33" fill="none" stroke="#1b4332" stroke-width="4" stroke-linecap="round"/>
        <path d="M31 36C18 34 12 26 13 14c12 1 20 8 18 22Z" fill="#64ba38"/>
        <path d="M34 39c14-1 22-9 22-22-12 0-21 8-22 22Z" fill="#2d6a55"/>
        <path d="M23 55h18" fill="none" stroke="#c9a84c" stroke-width="4" stroke-linecap="round"/>
      </svg>';
}

student_page_start('حديقة الإنجاز');
student_sidebar($user, 'achievements', $notifs);
?>

<style>
.garden-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:1.25rem;align-items:start}
.garden-hero{position:relative;min-height:620px;background:linear-gradient(180deg,#2d6a55 0%,#265c49 100%);border-radius:var(--r-lg);border:1px solid rgba(22,51,36,.12);box-shadow:var(--sh-md);overflow:hidden}
.garden-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 25% 18%,rgba(255,255,255,.1),transparent 24%),radial-gradient(circle at 78% 76%,rgba(201,168,76,.12),transparent 28%);pointer-events:none}
.garden-head{position:relative;z-index:4;padding:1.35rem 1.5rem 0;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;color:#fff}
.garden-head h2{font-size:1.45rem;font-weight:900;color:#fff}.garden-head p{font-size:.88rem;color:rgba(255,255,255,.72);margin-top:.2rem}
.garden-filters{display:flex;gap:.35rem;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.14);padding:.35rem;border-radius:var(--r-md)}
.filter-btn{border:0;border-radius:var(--r-sm);padding:.45rem .75rem;background:transparent;color:rgba(255,255,255,.72);font-weight:800;cursor:pointer}
.filter-btn.active{background:#fff;color:var(--olive-800);box-shadow:var(--sh-xs)}
.island-wrap{position:absolute;inset:4.8rem 1rem 4rem;display:flex;align-items:center;justify-content:center}
.island{position:relative;width:min(760px,92vw);height:500px;filter:drop-shadow(0 36px 34px rgba(0,0,0,.28));animation:islandFloat 6s ease-in-out infinite}
@keyframes islandFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.island-shadow{position:absolute;left:14%;right:14%;bottom:26px;height:70px;background:rgba(0,0,0,.18);filter:blur(22px);border-radius:50%}
.island-top{position:absolute;left:8%;right:8%;top:82px;height:330px;background:linear-gradient(145deg,#a9df50 0%,#7fc63c 78%);clip-path:polygon(50% 0,100% 26%,50% 100%,0 26%);border-radius:22px;overflow:hidden}
.island-top::before{content:'';position:absolute;inset:0;background:repeating-linear-gradient(35deg,rgba(255,255,255,.08) 0 2px,transparent 2px 28px),radial-gradient(circle at 45% 45%,rgba(255,255,255,.16),transparent 35%)}
.island-side-left,.island-side-right{position:absolute;top:252px;height:118px;background:#79562b;overflow:hidden}
.island-side-left{left:8%;width:42%;clip-path:polygon(0 0,100% 100%,100% 52%,0 0)}
.island-side-right{right:8%;width:42%;clip-path:polygon(0 100%,100% 0,100% 0,0 52%)}
.island-side-left::after,.island-side-right::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 18px 14px,#5a3d20 0 10px,transparent 11px),radial-gradient(circle at 58px 32px,#68451f 0 8px,transparent 9px);background-size:82px 46px;opacity:.75}
.grass-specks{position:absolute;inset:0;clip-path:polygon(50% 0,100% 26%,50% 100%,0 26%);pointer-events:none}
.grass-specks span{position:absolute;width:8px;height:12px;border-right:2px solid rgba(56,120,39,.35);border-radius:50%;transform:rotate(24deg)}
.tree-node{position:absolute;z-index:5;transform:translate(-50%,-100%);transition:opacity .25s,transform .25s;cursor:pointer}
.tree-node.hidden-tree{opacity:0;pointer-events:none;transform:translate(-50%,-85%) scale(.65)}
.tree-plant{position:relative;width:64px;height:88px;transform-origin:50% 100%;animation:sway 3.8s ease-in-out infinite}
.tree-node:nth-child(3n) .tree-plant{animation-delay:.4s}.tree-node:nth-child(4n) .tree-plant{animation-duration:4.5s}
@keyframes sway{0%,100%{transform:rotate(-1.8deg)}50%{transform:rotate(2deg)}}
.trunk{position:absolute;left:27px;bottom:7px;width:12px;height:34px;background:linear-gradient(#9b6a2e,#6d461d);border-radius:4px;box-shadow:inset -3px 0 rgba(0,0,0,.15)}
.tree-oak .leaf{position:absolute;border-radius:50%;background:#55ad35;box-shadow:inset -7px -8px rgba(29,89,41,.17),0 3px 0 rgba(0,0,0,.06)}
.tree-oak .leaf.a{width:48px;height:48px;left:8px;top:5px}.tree-oak .leaf.b{width:38px;height:38px;left:1px;top:29px}.tree-oak .leaf.c{width:39px;height:39px;right:1px;top:28px}
.tree-shrub .leaf{position:absolute;border-radius:48% 52% 45% 55%;background:#64ba38;box-shadow:inset -8px -8px rgba(29,89,41,.14)}
.tree-shrub .leaf.a{width:50px;height:45px;left:8px;top:20px}.tree-shrub .leaf.b{width:34px;height:32px;left:4px;top:9px}.tree-shrub .leaf.c{width:32px;height:33px;right:5px;top:8px}
.tree-palm .trunk{height:48px;transform:rotate(-5deg);bottom:3px}
.tree-palm .leaf{position:absolute;left:30px;top:7px;width:11px;height:48px;background:#53aa32;border-radius:100% 0 100% 0;transform-origin:bottom center;box-shadow:inset -3px -5px rgba(29,89,41,.15)}
.tree-palm .leaf.a{transform:rotate(-60deg)}.tree-palm .leaf.b{transform:rotate(-30deg)}.tree-palm .leaf.c{transform:rotate(0deg)}.tree-palm .leaf.d{transform:rotate(32deg)}.tree-palm .leaf.e{transform:rotate(62deg)}
.fruit{position:absolute;width:7px;height:7px;background:#f6e27a;border-radius:50%;box-shadow:17px 12px #f6e27a,28px -3px #f6e27a;left:17px;top:25px}
.tree-label{position:absolute;right:50%;bottom:-20px;transform:translateX(50%);background:rgba(255,255,255,.94);border:1px solid rgba(22,51,36,.09);border-radius:999px;padding:.18rem .55rem;font-size:.72rem;font-weight:800;color:var(--olive-800);white-space:nowrap;box-shadow:var(--sh-xs)}
.tree-tip{position:absolute;right:50%;bottom:88px;transform:translateX(50%) translateY(8px);width:210px;background:#fff;border:1px solid rgba(22,51,36,.09);border-radius:var(--r-md);padding:.85rem;box-shadow:var(--sh-lg);opacity:0;visibility:hidden;transition:.2s;text-align:right;z-index:20}
.tree-node:hover{z-index:50}.tree-node:hover .tree-tip{opacity:1;visibility:visible;transform:translateX(50%) translateY(0)}
.tip-title{font-weight:900;color:var(--olive-800);font-size:1rem}.tip-meta{color:var(--muted);font-size:.78rem;margin-top:.25rem}.tip-note{background:var(--olive-50);border-radius:var(--r-sm);padding:.55rem;margin-top:.6rem;color:var(--olive-700);font-size:.8rem}
.empty-garden{position:absolute;z-index:8;left:50%;top:52%;transform:translate(-50%,-50%);background:rgba(255,255,255,.95);border:1px solid rgba(22,51,36,.1);box-shadow:var(--sh-lg);border-radius:var(--r-lg);padding:1.6rem;text-align:center;width:min(360px,88%)}
.empty-garden .seed{width:62px;height:62px;margin:0 auto .35rem;display:grid;place-items:center;border-radius:50%;background:rgba(201,168,76,.12)}.empty-garden .seed svg{width:42px;height:42px}.empty-garden h3{color:var(--olive-800);font-weight:900;margin:.3rem 0}.empty-garden p{color:var(--muted);font-size:.9rem}
.garden-stats{position:absolute;z-index:7;left:50%;bottom:1.2rem;transform:translateX(-50%);display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.92);border:1px solid rgba(255,255,255,.7);border-radius:var(--r-lg);box-shadow:var(--sh-md);padding:.85rem 1.2rem}
.garden-stat{text-align:center;min-width:84px}.garden-stat strong{display:block;color:var(--olive-800);font-size:1.25rem}.garden-stat span{font-size:.74rem;color:var(--muted);font-weight:700}
.side-stack{display:grid;gap:1rem}
.level-card{background:linear-gradient(135deg,var(--olive-800),var(--olive-600));color:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-md);padding:1.35rem;overflow:hidden;position:relative}
.level-card::after{content:'إنجاز';position:absolute;left:-.8rem;bottom:-1.8rem;color:rgba(255,255,255,.06);font-size:4.5rem;font-weight:900}
.level-card small{color:var(--gold-300);font-weight:900}.level-card h3{font-size:2.2rem;margin:.2rem 0 .8rem}.level-progress{height:10px;background:rgba(0,0,0,.22);border-radius:999px;overflow:hidden}.level-progress div{height:100%;background:linear-gradient(90deg,var(--gold-500),var(--gold-300));border-radius:999px}
.badge-card,.badges-list{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--sh-sm);padding:1.2rem}
.card-mini-title{font-weight:900;color:var(--olive-800);margin-bottom:.8rem;display:flex;align-items:center;gap:.45rem}.latest-badge{text-align:center}.latest-badge .badge-icon{width:74px;height:74px;margin:0 auto .35rem;display:grid;place-items:center;border-radius:50%;background:linear-gradient(135deg,rgba(201,168,76,.18),rgba(255,255,255,.92));border:1px solid rgba(201,168,76,.32);box-shadow:0 10px 22px rgba(22,51,36,.08)}.latest-badge .badge-icon svg{width:52px;height:52px}.badge-svg-halo{fill:#f8efd2}.badge-svg-medal,.badge-svg-cup{fill:#c9a84c}.badge-svg-ribbon{fill:#2d6a55}.badge-svg-ribbon.left{opacity:.86}.badge-svg-mark,.badge-svg-cup-line,.badge-svg-base{fill:none;stroke:#1b4332;stroke-width:4;stroke-linecap:round;stroke-linejoin:round}.badge-svg-mark{stroke:#fff}.badge-svg-star{fill:#fff}.latest-badge h3{color:var(--olive-800);font-size:1.15rem;margin:.35rem 0}.latest-badge p{color:var(--muted);font-size:.84rem}
.badge-row{display:flex;align-items:center;gap:.65rem;padding:.65rem 0;border-top:1px solid var(--border)}.badge-row:first-of-type{border-top:0}.badge-row .icon{width:38px;height:38px;display:grid;place-items:center;flex:0 0 38px;border-radius:50%;background:rgba(201,168,76,.14);border:1px solid rgba(201,168,76,.28)}.badge-row .icon svg{width:25px;height:25px}.badge-row strong{display:block;color:var(--olive-800);font-size:.9rem}.badge-row span{display:block;color:var(--muted);font-size:.76rem}
@media(max-width:1100px){.garden-layout{grid-template-columns:1fr}.side-stack{grid-template-columns:repeat(2,minmax(0,1fr))}.garden-hero{min-height:560px}.island{width:680px;max-width:100%}}
@media(max-width:760px){.garden-head{padding:1rem}.garden-filters{width:100%;overflow:auto}.filter-btn{white-space:nowrap}.garden-hero{min-height:520px}.island{transform:scale(.82)}.garden-stats{width:calc(100% - 2rem);justify-content:space-around;gap:.25rem}.side-stack{grid-template-columns:1fr}}
</style>

<main class="pw">
  <header class="ph anim">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        حديقة الإنجاز
      </h1>
      <p>كل سورة تتمها تتحول إلى شجرة في حديقتك، بنفس ترتيب إنجازاتك.</p>
    </div>
    <button class="notif-btn" onclick="openNotif()" aria-label="الإشعارات">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($notifs>0): ?><span class="notif-dot" id="notifDot"><?= $notifs ?></span><?php endif; ?>
    </button>
  </header>

  <section class="garden-layout">
    <div class="garden-hero anim anim-d1">
      <div class="garden-head">
        <div>
          <h2>حديقتك القرآنية</h2>
          <p>المشهد يحافظ على ترتيب السور حسب تاريخ الزراعة، والأحدث يظهر أولًا.</p>
        </div>
        <div class="garden-filters" role="group" aria-label="تصفية الحديقة">
          <button class="filter-btn" type="button" onclick="filterGarden('day', this)">يومي</button>
          <button class="filter-btn" type="button" onclick="filterGarden('week', this)">أسبوعي</button>
          <button class="filter-btn" type="button" onclick="filterGarden('month', this)">شهري</button>
          <button class="filter-btn active" type="button" onclick="filterGarden('all', this)">الكل</button>
        </div>
      </div>

      <div class="island-wrap">
        <div class="island-shadow"></div>
        <div class="island">
          <div class="island-side-left"></div>
          <div class="island-side-right"></div>
          <div class="island-top">
            <div class="grass-specks">
              <?php for($i=0;$i<48;$i++): ?>
                <span style="left:<?= (7 + ($i * 19) % 86) ?>%;top:<?= (8 + ($i * 31) % 82) ?>%;"></span>
              <?php endfor; ?>
            </div>
          </div>

          <?php if(empty($trees)): ?>
            <div class="empty-garden">
              <div class="seed"><?= gardenSeedSvgIcon() ?></div>
              <h3>حديقتك تنتظر أول شجرة</h3>
              <p>أتمم حفظ سورة كاملة، وستنبت هنا أول شجرة بإذن الله.</p>
            </div>
          <?php else: foreach($trees as $i => $tree):
            $slot = $slots[$i % count($slots)];
            $cycle = intdiv($i, count($slots));
            $left = max(8, min(92, $slot[0] + (($cycle % 3) - 1) * 3));
            $top = max(10, min(86, $slot[1] + ($cycle * 4)));
            $class = treeKindClass($tree['tree_type']);
            $dateStr = date('d/m/Y', strtotime($tree['planted_at']));
            $delay = min($i * 0.04, 1.2);
          ?>
            <div class="tree-node" data-index="<?= $i ?>" style="left:<?= $left ?>%;top:<?= $top ?>%;z-index:<?= 20 + (int)$top ?>;animation-delay:<?= $delay ?>s">
              <div class="tree-tip">
                <div class="tip-title">سورة <?= htmlspecialchars($tree['surah_name']) ?></div>
                <div class="tip-meta">زُرعت في <?= $dateStr ?></div>
                <div class="tip-note">تم إتمام حفظ هذه السورة، فكبرت بها الحديقة.</div>
              </div>
              <div class="tree-plant <?= $class ?>">
                <div class="trunk"></div>
                <div class="leaf a"></div><div class="leaf b"></div><div class="leaf c"></div><div class="leaf d"></div><div class="leaf e"></div>
                <?php if($tree['tree_type'] !== 'palm'): ?><div class="fruit"></div><?php endif; ?>
              </div>
              <div class="tree-label"><?= htmlspecialchars($tree['surah_name']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="garden-stats">
        <div class="garden-stat"><strong><?= $totalTrees ?></strong><span>سورة</span></div>
        <div class="garden-stat"><strong><?= $shrubs ?></strong><span>شجيرات</span></div>
        <div class="garden-stat"><strong><?= $palms + $oaks ?></strong><span>أشجار كبيرة</span></div>
      </div>
    </div>

    <aside class="side-stack">
      <div class="level-card anim anim-d2">
        <small>المستوى الحالي</small>
        <h3><?= (int)$pts['level'] ?></h3>
        <div class="level-progress"><div style="width:<?= $progress ?>%"></div></div>
        <p style="font-size:.86rem;color:rgba(255,255,255,.74);margin-top:.75rem">
          <?php if($nextLevel < 9999): ?>
            بقي <?= number_format($remaining) ?> نقطة للمستوى التالي.
          <?php else: ?>
            وصلت إلى أعلى مستوى، واصل تثبيت الحفظ.
          <?php endif; ?>
        </p>
      </div>

      <div class="badge-card anim anim-d3">
        <div class="card-mini-title">أحدث وسام</div>
        <div class="latest-badge">
          <?php if($latestBadge): ?>
            <div class="badge-icon"><?= latestBadgeSvgIcon(true) ?></div>
            <h3><?= htmlspecialchars(removeEmojis($latestBadge['name'])) ?></h3>
            <p><?= htmlspecialchars(removeEmojis($latestBadge['description'] ?? '')) ?></p>
          <?php else: ?>
            <div class="badge-icon" style="opacity:.7"><?= latestBadgeSvgIcon(false) ?></div>
            <h3>لا توجد أوسمة بعد</h3>
            <p>استمر في الحفظ لتحصل على أول وسام.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="badges-list anim anim-d4">
        <div class="card-mini-title">الأوسمة الأخيرة</div>
        <?php $allBadges = array_slice(array_merge($teacherBadges, $pointsBadges), 0, 5); ?>
        <?php if(empty($allBadges)): ?>
          <p style="color:var(--muted);font-size:.86rem;text-align:center;padding:1rem 0">لا توجد أوسمة مسجلة بعد.</p>
        <?php else: foreach($allBadges as $badge): ?>
          <div class="badge-row">
            <div class="icon"><?= latestBadgeSvgIcon(true) ?></div>
            <div>
              <strong><?= htmlspecialchars(removeEmojis($badge['name'])) ?></strong>
              <span><?= date('d/m/Y', strtotime($badge['granted_at'])) ?></span>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </aside>
  </section>
</main>

<?php
require_once __DIR__ . '/../admin/rattil_shared.php';
rattil_notif_panel($notifs);
?>

<script>
function filterGarden(type, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  let limit = 9999;
  if (type === 'day') limit = 3;
  if (type === 'week') limit = 8;
  if (type === 'month') limit = 16;

  document.querySelectorAll('.tree-node').forEach(tree => {
    const index = Number(tree.dataset.index || 0);
    tree.classList.toggle('hidden-tree', index >= limit);
  });
}
</script>
</body>
</html>
