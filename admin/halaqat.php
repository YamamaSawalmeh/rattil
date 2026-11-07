<?php
require_once '../config/db.php';
requireRole('admin');

$user   = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$msg = $err = '';

/* ══════════════════════════════════════════════════
   CRUD — معالجة العمليات
   ══════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $name     = trim($_POST['name']          ?? '');
            $teacher  = (int)($_POST['teacher_id']   ?? 0) ?: null;
            $days     = trim($_POST['schedule_days'] ?? '');
            $time     = $_POST['schedule_time']      ? $_POST['schedule_time'] : null;
            $location = trim($_POST['location']      ?? '');
            $max      = max(1, (int)($_POST['max_students'] ?? 20));
            if (!$name) throw new Exception('اسم الحلقة مطلوب');
            $pdo->prepare("INSERT INTO halaqat (name, teacher_id, schedule_days, schedule_time, location, max_students, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())")
                ->execute([$name, $teacher, $days ?: null, $time, $location ?: null, $max]);
            $msg = 'تمت إضافة الحلقة بنجاح';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    elseif ($action === 'edit') {
        try {
            $id       = (int)$_POST['id'];
            $name     = trim($_POST['name']          ?? '');
            $teacher  = (int)($_POST['teacher_id']   ?? 0) ?: null;
            $days     = trim($_POST['schedule_days'] ?? '');
            $time     = $_POST['schedule_time']      ? $_POST['schedule_time'] : null;
            $location = trim($_POST['location']      ?? '');
            $max      = max(1, (int)($_POST['max_students'] ?? 20));
            $status   = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
            if (!$name) throw new Exception('اسم الحلقة مطلوب');
            $pdo->prepare("UPDATE halaqat SET name=?, teacher_id=?, schedule_days=?, schedule_time=?,
                            location=?, max_students=?, status=? WHERE id=?")
                ->execute([$name, $teacher, $days ?: null, $time, $location ?: null, $max, $status, $id]);
            $msg = 'تم تحديث الحلقة بنجاح';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        // حذف الطلاب المرتبطين أولاً
        $pdo->prepare("DELETE FROM halqa_students WHERE halqa_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM halaqat WHERE id = ?")->execute([$id]);
        $msg = 'تم حذف الحلقة بنجاح';
    }

    elseif ($action === 'add_student') {
        $halqa_id   = (int)$_POST['halqa_id'];
        $student_id = (int)$_POST['student_id'];
        if (!$halqa_id || !$student_id) { $err = 'بيانات غير صحيحة'; }
        else {
            // التحقق من الطاقة الاستيعابية
            $enrolled = (int)$pdo->prepare("SELECT COUNT(*) FROM halqa_students WHERE halqa_id = ?")->execute([$halqa_id]) && true
                ? $pdo->query("SELECT COUNT(*) FROM halqa_students WHERE halqa_id = $halqa_id")->fetchColumn() : 0;
            $max_s    = (int)$pdo->prepare("SELECT max_students FROM halaqat WHERE id = ?")->execute([$halqa_id]) && true
                ? $pdo->query("SELECT max_students FROM halaqat WHERE id = $halqa_id")->fetchColumn() : 99;
            // التحقق من التكرار
            $dup = $pdo->prepare("SELECT id FROM halqa_students WHERE halqa_id = ? AND student_id = ?");
            $dup->execute([$halqa_id, $student_id]);
            if ($dup->fetch()) { $err = 'الطالب مسجّل بالفعل في هذه الحلقة'; }
            elseif ($enrolled >= $max_s) { $err = 'الحلقة وصلت إلى الطاقة الاستيعابية القصوى'; }
            else {
                $pdo->prepare("INSERT INTO halqa_students (halqa_id, student_id, joined_at) VALUES (?, ?, NOW())")
                    ->execute([$halqa_id, $student_id]);
                $msg = 'تمت إضافة الطالب للحلقة بنجاح';
            }
        }
    }

    elseif ($action === 'remove_student') {
        $halqa_id   = (int)$_POST['halqa_id'];
        $student_id = (int)$_POST['student_id'];
        $pdo->prepare("DELETE FROM halqa_students WHERE halqa_id = ? AND student_id = ?")
            ->execute([$halqa_id, $student_id]);
        $msg = 'تم إزالة الطالب من الحلقة';
    }
}

/* ══════════════════════════════════════════════════
   DATA — جلب البيانات
   ══════════════════════════════════════════════════ */
$halaqat = $pdo->query("
    SELECT h.*, u.name AS teacher_name,
           COUNT(DISTINCT hs.student_id) AS students_count
    FROM halaqat h
    LEFT JOIN users u ON u.id = h.teacher_id
    LEFT JOIN halqa_students hs ON hs.halqa_id = h.id
    GROUP BY h.id
    ORDER BY h.status DESC, h.name
")->fetchAll();

$teachers = $pdo->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();
$students = $pdo->query("SELECT id, name FROM users WHERE role = 'student' ORDER BY name")->fetchAll();

/* خريطة طلاب الحلقات */
$halqa_students_map = [];
$hs_all = $pdo->query("
    SELECT hs.halqa_id, u.id, u.name
    FROM halqa_students hs
    JOIN users u ON u.id = hs.student_id
    ORDER BY u.name
")->fetchAll();
foreach ($hs_all as $row) $halqa_students_map[$row['halqa_id']][] = $row;

/* إحصائيات */
$total_active   = count(array_filter($halaqat, fn($h) => $h['status'] === 'active'));
$total_inactive = count($halaqat) - $total_active;
$total_students = array_sum(array_column($halaqat, 'students_count'));

require_once 'rattil_shared.php';
rattil_page_start('إدارة الحلقات');
rattil_sidebar($user, 'halaqat', $unread);
rattil_notif_panel($unread);
rattil_modals_js();
?>

<div class="pw">
  <!-- رأس الصفحة -->
  <div class="ph anim">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        إدارة الحلقات
      </h1>
      <p>إجمالي <?= count($halaqat) ?> حلقة — <?= $total_active ?> نشطة، <?= $total_inactive ?> موقوفة</p>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center">
      <button class="notif-btn" onclick="openNotif()" aria-label="الإشعارات">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if ($unread > 0): ?><span class="notif-dot" id="notifDot"><?= $unread ?></span><?php endif; ?>
      </button>
      <button class="btn btn-primary" onclick="openModal('modalAdd')">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        إضافة حلقة
      </button>
    </div>
  </div>

  <!-- إحصائيات -->
  <div class="stats-grid">
    <div class="stat-box anim anim-d1">
      <div class="stat-ic g">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      </div>
      <div><div class="stat-v"><?= count($halaqat) ?></div><div class="stat-l">إجمالي الحلقات</div></div>
    </div>
    <div class="stat-box anim anim-d2">
      <div class="stat-ic a">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div><div class="stat-v"><?= $total_active ?></div><div class="stat-l">حلقات نشطة</div></div>
    </div>
    <div class="stat-box anim anim-d3">
      <div class="stat-ic b">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div><div class="stat-v"><?= $total_students ?></div><div class="stat-l">إجمالي الطلاب</div></div>
    </div>
    <div class="stat-box anim anim-d4">
      <div class="stat-ic p">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <div><div class="stat-v"><?= count($teachers) ?></div><div class="stat-l">المعلمون المتاحون</div></div>
    </div>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-ok anim">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><polyline points="20 6 9 17 4 12"/></svg>
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>
  <?php if ($err): ?>
  <div class="alert alert-err anim">
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <?= htmlspecialchars($err) ?>
  </div>
  <?php endif; ?>

  <!-- شبكة الحلقات -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:1.5rem">
    <?php foreach ($halaqat as $i => $h):
      $enrolled = (int)$h['students_count'];
      $capacity = (int)$h['max_students'];
      $pct      = $capacity > 0 ? min(100, round($enrolled / $capacity * 100)) : 0;
      $studs    = $halqa_students_map[$h['id']] ?? [];
    ?>
    <div class="card anim anim-d<?= min($i + 1, 5) ?>" style="padding:0;overflow:hidden;margin-bottom:0">
      <!-- رأس الحلقة -->
      <div style="background:linear-gradient(135deg,var(--olive-900),var(--olive-700));padding:1.25rem">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:.75rem">
          <div>
            <h3 style="color:#fff;font-size:1.05rem;font-weight:800;margin-bottom:.3rem"><?= htmlspecialchars($h['name']) ?></h3>
            <span class="badge <?= $h['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>" style="font-size:.72rem">
              <?= $h['status'] === 'active' ? 'نشطة' : 'موقوفة' ?>
            </span>
          </div>
          <div style="display:flex;gap:.4rem">
            <button class="btn btn-xs" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.2)" onclick='openEditHalaqa(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)'>
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="btn btn-xs" style="background:rgba(192,57,43,.3);color:#fca5a5;border:1px solid rgba(192,57,43,.3)" onclick="openDeleteHalaqa(<?= $h['id'] ?>, '<?= addslashes($h['name']) ?>')">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
          </div>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:.6rem;font-size:.77rem;color:rgba(255,255,255,.75)">
          <?php if ($h['teacher_name']): ?>
          <span style="display:flex;align-items:center;gap:.3rem">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <?= htmlspecialchars($h['teacher_name']) ?>
          </span>
          <?php endif; ?>
          <?php if ($h['schedule_days']): ?>
          <span style="display:flex;align-items:center;gap:.3rem">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?= htmlspecialchars($h['schedule_days']) ?>
            <?= $h['schedule_time'] ? ' — ' . date('g:i A', strtotime($h['schedule_time'])) : '' ?>
          </span>
          <?php endif; ?>
          <?php if ($h['location']): ?>
          <span style="display:flex;align-items:center;gap:.3rem">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= htmlspecialchars($h['location']) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- تفاصيل الحلقة -->
      <div style="padding:1.1rem 1.25rem">
        <!-- شريط الطاقة الاستيعابية -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem">
          <span style="font-size:.8rem;font-weight:700;color:var(--olive-700)">الطاقة الاستيعابية</span>
          <span style="font-size:.78rem;color:var(--muted)"><?= $enrolled ?> / <?= $capacity ?></span>
        </div>
        <div class="progress" style="margin-bottom:1rem">
          <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct >= 90 ? 'var(--danger)' : ($pct >= 70 ? '#f59e0b' : 'linear-gradient(90deg,var(--olive-600),var(--olive-400))') ?>"></div>
        </div>

        <!-- طلاب الحلقة -->
        <div style="margin-bottom:1rem">
          <div style="font-size:.8rem;font-weight:700;color:var(--olive-700);margin-bottom:.5rem">الطلاب المسجلون</div>
          <div style="display:flex;flex-wrap:wrap;gap:.35rem;min-height:28px">
            <?php if (empty($studs)): ?>
            <span style="font-size:.78rem;color:var(--muted);font-style:italic">لا يوجد طلاب بعد</span>
            <?php else: ?>
            <?php foreach ($studs as $s): ?>
            <span class="chip">
              <?= htmlspecialchars($s['name']) ?>
              <form method="POST" style="display:inline;margin:0" onsubmit="return confirm('إزالة هذا الطالب من الحلقة؟')">
                <input type="hidden" name="action" value="remove_student">
                <input type="hidden" name="halqa_id" value="<?= $h['id'] ?>">
                <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                <button type="submit" class="chip-rm" title="إزالة من الحلقة">
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </form>
            </span>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- إضافة طالب -->
        <?php if ($enrolled < $capacity): ?>
        <form method="POST" style="display:flex;gap:.5rem;align-items:center">
          <input type="hidden" name="action" value="add_student">
          <input type="hidden" name="halqa_id" value="<?= $h['id'] ?>">
          <select name="student_id" class="fc" style="flex:1;font-size:.83rem;padding:.48rem .8rem" required>
            <option value="">اختر طالباً...</option>
            <?php
            // استبعاد الطلاب المسجلين بالفعل
            $enrolled_ids = array_column($studs, 'id');
            foreach ($students as $st):
              if (in_array($st['id'], $enrolled_ids)) continue;
            ?>
            <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-gold" title="إضافة طالب">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إضافة
          </button>
        </form>
        <?php else: ?>
        <div class="alert alert-err" style="padding:.5rem .75rem;margin:0;font-size:.78rem">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="14" height="14"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          الحلقة ممتلئة
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($halaqat)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:4rem;color:var(--muted)">
      <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" style="opacity:.3;margin:0 auto 1.25rem;display:block"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      <p style="font-size:.95rem">لا توجد حلقات بعد</p>
      <button class="btn btn-primary" style="margin-top:1rem" onclick="openModal('modalAdd')">إضافة أول حلقة</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════
     مودال إضافة حلقة
     ══════════════════════════════════ -->
<div class="modal" id="modalAdd" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-head">
      <h3>
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        إضافة حلقة جديدة
      </h3>
      <button class="modal-close" onclick="closeModal('modalAdd')">×</button>
    </div>
    <form method="POST" onsubmit="setLoading(this)">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="fg">
          <label class="fl">اسم الحلقة <span style="color:var(--danger)">*</span></label>
          <input name="name" class="fc" required placeholder="مثال: حلقة الفجر للمتقدمين">
        </div>
        <div class="form-row">
          <div class="fg">
            <label class="fl">المعلم المسؤول</label>
            <select name="teacher_id" class="fc">
              <option value="">بدون معلم</option>
              <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="fl">الطاقة الاستيعابية</label>
            <input name="max_students" type="number" class="fc" value="20" min="1" max="200">
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label class="fl">أيام الدراسة</label>
            <input name="schedule_days" class="fc" placeholder="مثال: السبت، الاثنين، الأربعاء">
          </div>
          <div class="fg">
            <label class="fl">وقت الحلقة</label>
            <input name="schedule_time" type="time" class="fc">
          </div>
        </div>
        <div class="fg">
          <label class="fl">الموقع / المكان</label>
          <input name="location" class="fc" placeholder="مثال: المسجد الكبير — قاعة 3">
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          إضافة الحلقة
        </button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════
     مودال تعديل حلقة
     ══════════════════════════════════ -->
<div class="modal" id="modalEdit" role="dialog" aria-modal="true">
  <div class="modal-box">
    <div class="modal-head">
      <h3>
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        تعديل الحلقة
      </h3>
      <button class="modal-close" onclick="closeModal('modalEdit')">×</button>
    </div>
    <form method="POST" onsubmit="setLoading(this)">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editHId">
      <div class="modal-body">
        <div class="fg">
          <label class="fl">اسم الحلقة <span style="color:var(--danger)">*</span></label>
          <input name="name" id="editHName" class="fc" required>
        </div>
        <div class="form-row">
          <div class="fg">
            <label class="fl">المعلم المسؤول</label>
            <select name="teacher_id" id="editHTeacher" class="fc">
              <option value="">بدون معلم</option>
              <?php foreach ($teachers as $t): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="fl">الحالة</label>
            <select name="status" id="editHStatus" class="fc">
              <option value="active">نشطة</option>
              <option value="inactive">موقوفة</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label class="fl">أيام الدراسة</label>
            <input name="schedule_days" id="editHDays" class="fc">
          </div>
          <div class="fg">
            <label class="fl">وقت الحلقة</label>
            <input name="schedule_time" id="editHTime" type="time" class="fc">
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label class="fl">الموقع</label>
            <input name="location" id="editHLoc" class="fc">
          </div>
          <div class="fg">
            <label class="fl">الطاقة الاستيعابية</label>
            <input name="max_students" id="editHMax" type="number" class="fc" min="1" max="200">
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><polyline points="20 6 9 17 4 12"/></svg>
          حفظ التعديلات
        </button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalEdit')">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════
     مودال تأكيد الحذف
     ══════════════════════════════════ -->
<div class="modal" id="modalDelete" role="dialog" aria-modal="true">
  <div class="modal-box modal-sm">
    <div class="modal-head">
      <h3 class="danger">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        تأكيد حذف الحلقة
      </h3>
      <button class="modal-close" onclick="closeModal('modalDelete')">×</button>
    </div>
    <form method="POST" onsubmit="setLoading(this)">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="deleteHId">
      <div class="modal-body">
        <p>هل تريد حذف حلقة <strong id="deleteHName" style="color:var(--olive-700)"></strong>؟</p>
        <div class="alert alert-err" style="margin-top:1rem;margin-bottom:0">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          سيتم حذف بيانات الحلقة وجميع تسجيلات الطلاب فيها.
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-danger">نعم، احذف الحلقة</button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalDelete')">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditHalaqa(h) {
  document.getElementById('editHId').value      = h.id;
  document.getElementById('editHName').value    = h.name;
  document.getElementById('editHTeacher').value = h.teacher_id || '';
  document.getElementById('editHStatus').value  = h.status || 'active';
  document.getElementById('editHDays').value    = h.schedule_days || '';
  document.getElementById('editHTime').value    = h.schedule_time || '';
  document.getElementById('editHLoc').value     = h.location || '';
  document.getElementById('editHMax').value     = h.max_students || 20;
  openModal('modalEdit');
}
function openDeleteHalaqa(id, name) {
  document.getElementById('deleteHId').value        = id;
  document.getElementById('deleteHName').textContent = name;
  openModal('modalDelete');
}
function setLoading(form) {
  const btn = form.querySelector('[type="submit"]');
  if (btn) { btn.classList.add('loading'); btn.disabled = true; }
}
</script>
</body>
</html>