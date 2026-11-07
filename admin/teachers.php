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
            $name  = trim($_POST['name']  ?? '');
            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password']   ?? '';
            $phone = trim($_POST['phone'] ?? '');
            if (mb_strlen($name) < 2) throw new Exception('الاسم يجب أن يكون حرفين على الأقل');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('البريد الإلكتروني غير صحيح');
            if (strlen($pass) < 6) throw new Exception('كلمة المرور 6 أحرف على الأقل');
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) throw new Exception('هذا البريد الإلكتروني مستخدم بالفعل');
            $pdo->prepare("INSERT INTO users (name, email, password, role, phone, created_at)
                            VALUES (?, ?, ?, 'teacher', ?, NOW())")
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $phone ?: null]);
            $msg = 'تمت إضافة المعلم بنجاح';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    elseif ($action === 'edit') {
        try {
            $id    = (int)$_POST['id'];
            $name  = trim($_POST['name']  ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            if (mb_strlen($name) < 2) throw new Exception('الاسم يجب أن يكون حرفين على الأقل');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('البريد الإلكتروني غير صحيح');
            // التحقق من تكرار البريد لمستخدم آخر
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $id]);
            if ($chk->fetch()) throw new Exception('هذا البريد الإلكتروني مستخدم من قِبَل مستخدم آخر');
            $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? AND role = 'teacher'")
                ->execute([$name, $email, $phone ?: null, $id]);
            if (!empty($_POST['password'])) {
                if (strlen($_POST['password']) < 6) throw new Exception('كلمة المرور الجديدة 6 أحرف على الأقل');
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    ->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            $msg = 'تم تحديث بيانات المعلم بنجاح';
        } catch (Exception $e) { $err = $e->getMessage(); }
    }

    elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        // فصل الحلقات عن هذا المعلم
        $pdo->prepare("UPDATE halaqat SET teacher_id = NULL WHERE teacher_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'")->execute([$id]);
        $msg = 'تم حذف حساب المعلم بنجاح';
    }
}

/* ══════════════════════════════════════════════════
   DATA — جلب البيانات
   ══════════════════════════════════════════════════ */
$search = trim($_GET['q'] ?? '');
$where  = "WHERE u.role = 'teacher'";
$params = [];
if ($search) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT
        u.*,
        COUNT(DISTINCT h.id)          AS halaqat_count,
        COUNT(DISTINCT hs.student_id) AS students_count,
        COUNT(DISTINCT rl.id)         AS recit_count,
        MAX(rl.recorded_at)           AS last_recit
    FROM users u
    LEFT JOIN halaqat h          ON h.teacher_id  = u.id AND h.status = 'active'
    LEFT JOIN halqa_students hs  ON hs.halqa_id   = h.id
    LEFT JOIN recitation_log rl  ON rl.teacher_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$teachers = $stmt->fetchAll();

/* إحصائيات سريعة */
$total_teachers  = count($teachers);
$active_today    = (int)$pdo->query("SELECT COUNT(DISTINCT teacher_id) FROM recitation_log WHERE DATE(recorded_at) = CURDATE()")->fetchColumn();
$total_sessions  = (int)$pdo->query("SELECT COUNT(*) FROM recitation_log")->fetchColumn();

require_once 'rattil_shared.php';
rattil_page_start('إدارة المعلمين');
rattil_sidebar($user, 'teachers', $unread);
rattil_notif_panel($unread);
rattil_modals_js();
?>

<div class="pw">
  <!-- رأس الصفحة -->
  <div class="ph anim">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        إدارة المعلمين
      </h1>
      <p>إجمالي <?= $total_teachers ?> معلم مسجّل في المنصة</p>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center">
      <button class="notif-btn" onclick="openNotif()" aria-label="الإشعارات">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if ($unread > 0): ?><span class="notif-dot" id="notifDot"><?= $unread ?></span><?php endif; ?>
      </button>
      <button class="btn btn-primary" onclick="openModal('modalAdd')">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        إضافة معلم
      </button>
    </div>
  </div>

  <!-- إحصائيات سريعة -->
  <div class="stats-grid">
    <div class="stat-box anim anim-d1">
      <div class="stat-ic g">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div><div class="stat-v"><?= $total_teachers ?></div><div class="stat-l">إجمالي المعلمين</div></div>
    </div>
    <div class="stat-box anim anim-d2">
      <div class="stat-ic a">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div><div class="stat-v"><?= $active_today ?></div><div class="stat-l">نشطون اليوم</div></div>
    </div>
    <div class="stat-box anim anim-d3">
      <div class="stat-ic b">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div><div class="stat-v"><?= number_format($total_sessions) ?></div><div class="stat-l">جلسات التسميع</div></div>
    </div>
    <div class="stat-box anim anim-d4">
      <div class="stat-ic p">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      </div>
      <div>
        <div class="stat-v"><?= (int)$pdo->query("SELECT COUNT(*) FROM halaqat WHERE status='active'")->fetchColumn() ?></div>
        <div class="stat-l">حلقات نشطة</div>
      </div>
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

  <!-- شريط البحث -->
  <form method="GET" class="search-bar anim">
    <div class="search-input-wrap">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input name="q" value="<?= htmlspecialchars($search) ?>" class="fc" placeholder="بحث بالاسم أو البريد أو الهاتف...">
    </div>
    <button type="submit" class="btn btn-primary">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      بحث
    </button>
    <?php if ($search): ?>
    <a href="?" class="btn btn-ghost">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      مسح
    </a>
    <?php endif; ?>
  </form>

  <!-- شبكة بطاقات المعلمين -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
    <?php foreach ($teachers as $i => $t): ?>
    <div class="card anim anim-d<?= min($i + 1, 5) ?>" style="padding:0;overflow:hidden;margin-bottom:0">

      <!-- رأس البطاقة -->
      <div style="background:linear-gradient(135deg,var(--olive-800),var(--olive-600));padding:1.25rem;display:flex;align-items:center;gap:1rem">
        <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--gold-500),var(--gold-400));display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;color:var(--olive-800);flex-shrink:0;box-shadow:0 3px 10px rgba(0,0,0,.3)">
          <?= mb_substr($t['name'], 0, 1, 'UTF-8') ?>
        </div>
        <div style="flex:1;min-width:0">
          <div style="color:#fff;font-weight:800;font-size:1rem;margin-bottom:.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?= htmlspecialchars($t['name']) ?>
          </div>
          <div style="color:rgba(255,255,255,.7);font-size:.78rem;display:flex;align-items:center;gap:.35rem">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <?= htmlspecialchars($t['email']) ?>
          </div>
          <?php if ($t['phone']): ?>
          <div style="color:rgba(255,255,255,.6);font-size:.76rem;margin-top:.15rem;display:flex;align-items:center;gap:.35rem">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <?= htmlspecialchars($t['phone']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- إحصائيات المعلم -->
      <div style="padding:1.1rem 1.25rem">
        <div style="display:flex;justify-content:space-around;background:var(--olive-50);border-radius:var(--r-md);padding:.85rem;margin-bottom:.9rem;border:1px solid var(--border)">
          <div style="text-align:center">
            <div style="font-size:1.4rem;font-weight:800;color:var(--olive-700)"><?= $t['halaqat_count'] ?></div>
            <div style="font-size:.72rem;color:var(--muted);margin-top:2px">حلقات</div>
          </div>
          <div style="width:1px;background:var(--border)"></div>
          <div style="text-align:center">
            <div style="font-size:1.4rem;font-weight:800;color:var(--olive-700)"><?= $t['students_count'] ?></div>
            <div style="font-size:.72rem;color:var(--muted);margin-top:2px">طلاب</div>
          </div>
          <div style="width:1px;background:var(--border)"></div>
          <div style="text-align:center">
            <div style="font-size:1.4rem;font-weight:800;color:var(--olive-700)"><?= $t['recit_count'] ?></div>
            <div style="font-size:.72rem;color:var(--muted);margin-top:2px">تسميع</div>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.9rem">
          <div style="font-size:.78rem;color:var(--muted)">
            <?php if ($t['last_recit']): ?>
              آخر نشاط: <?= date('j/n/Y', strtotime($t['last_recit'])) ?>
            <?php else: ?>
              لا يوجد نشاط بعد
            <?php endif; ?>
          </div>
          <div style="font-size:.75rem;color:var(--muted)">
            منذ <?= date('j/n/Y', strtotime($t['created_at'])) ?>
          </div>
        </div>

        <!-- أزرار الإجراءات -->
        <div style="display:flex;gap:.5rem">
          <button class="btn btn-sm btn-primary" style="flex:1" onclick='openEditModal(<?= htmlspecialchars(json_encode(['id'=>$t['id'],'name'=>$t['name'],'email'=>$t['email'],'phone'=>$t['phone']??'']), ENT_QUOTES) ?>)'>
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            تعديل
          </button>
          <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $t['id'] ?>, '<?= addslashes($t['name']) ?>')">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            حذف
          </button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($teachers)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:4rem;color:var(--muted)">
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" style="opacity:.3;margin-bottom:1.25rem;display:block;margin-right:auto;margin-left:auto"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      <p style="font-size:.95rem"><?= $search ? 'لا توجد نتائج للبحث عن "' . htmlspecialchars($search) . '"' : 'لا يوجد معلمون بعد، أضف أول معلم!' ?></p>
      <?php if (!$search): ?>
      <button class="btn btn-primary" style="margin-top:1rem" onclick="openModal('modalAdd')">إضافة معلم</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══════════════════════════════════
     مودال إضافة معلم
     ══════════════════════════════════ -->
<div class="modal" id="modalAdd" role="dialog" aria-modal="true" aria-labelledby="addTitle">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="addTitle">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        إضافة معلم جديد
      </h3>
      <button class="modal-close" onclick="closeModal('modalAdd')" aria-label="إغلاق">×</button>
    </div>
    <form method="POST" id="formAdd" onsubmit="setLoading(this)">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="fg">
            <label class="fl">الاسم الكامل <span style="color:var(--danger)">*</span></label>
            <input name="name" class="fc" required placeholder="مثال: أحمد محمد" minlength="2">
          </div>
          <div class="fg">
            <label class="fl">رقم الهاتف</label>
            <input name="phone" class="fc" placeholder="05xxxxxxxx" pattern="[0-9+\-\s]{7,15}">
          </div>
        </div>
        <div class="fg">
          <label class="fl">البريد الإلكتروني <span style="color:var(--danger)">*</span></label>
          <input name="email" type="email" class="fc" required placeholder="teacher@rattil.com">
        </div>
        <div class="fg">
          <label class="fl">كلمة المرور <span style="color:var(--danger)">*</span> <small style="color:var(--muted);font-weight:400">(6 أحرف على الأقل)</small></label>
          <div style="position:relative">
            <input name="password" type="password" class="fc" required minlength="6" id="addPass" placeholder="••••••••">
            <button type="button" onclick="togglePass('addPass',this)" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);display:flex;align-items:center">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          إضافة المعلم
        </button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalAdd')">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════
     مودال تعديل معلم
     ══════════════════════════════════ -->
<div class="modal" id="modalEdit" role="dialog" aria-modal="true" aria-labelledby="editTitle">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="editTitle">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        تعديل بيانات المعلم
      </h3>
      <button class="modal-close" onclick="closeModal('modalEdit')" aria-label="إغلاق">×</button>
    </div>
    <form method="POST" id="formEdit" onsubmit="setLoading(this)">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="editId">
      <div class="modal-body">
        <div class="form-row">
          <div class="fg">
            <label class="fl">الاسم الكامل <span style="color:var(--danger)">*</span></label>
            <input name="name" id="editName" class="fc" required minlength="2">
          </div>
          <div class="fg">
            <label class="fl">رقم الهاتف</label>
            <input name="phone" id="editPhone" class="fc" placeholder="05xxxxxxxx">
          </div>
        </div>
        <div class="fg">
          <label class="fl">البريد الإلكتروني <span style="color:var(--danger)">*</span></label>
          <input name="email" id="editEmail" type="email" class="fc" required>
        </div>
        <div class="fg">
          <label class="fl">كلمة مرور جديدة <small style="color:var(--muted);font-weight:400">(اتركه فارغاً للإبقاء على الحالية)</small></label>
          <div style="position:relative">
            <input name="password" type="password" class="fc" minlength="6" id="editPass" placeholder="••••••••">
            <button type="button" onclick="togglePass('editPass',this)" style="position:absolute;left:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);display:flex;align-items:center">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
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
<div class="modal" id="modalDelete" role="dialog" aria-modal="true" aria-labelledby="delTitle">
  <div class="modal-box modal-sm">
    <div class="modal-head">
      <h3 class="danger" id="delTitle">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        تأكيد الحذف
      </h3>
      <button class="modal-close" onclick="closeModal('modalDelete')" aria-label="إغلاق">×</button>
    </div>
    <form method="POST" onsubmit="setLoading(this)">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" id="deleteId">
      <div class="modal-body">
        <p style="font-size:.93rem">هل أنت متأكد من حذف حساب المعلم <strong id="deleteName" style="color:var(--olive-700)"></strong>؟</p>
        <div class="alert alert-err" style="margin-top:1rem;margin-bottom:0">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          ستُفصل الحلقات المسندة إليه ولا يمكن التراجع عن هذا الإجراء.
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-danger">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
          نعم، احذف
        </button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalDelete')">إلغاء</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(t) {
  document.getElementById('editId').value    = t.id;
  document.getElementById('editName').value  = t.name;
  document.getElementById('editEmail').value = t.email;
  document.getElementById('editPhone').value = t.phone || '';
  document.getElementById('editPass').value  = '';
  openModal('modalEdit');
}

function openDeleteModal(id, name) {
  document.getElementById('deleteId').value       = id;
  document.getElementById('deleteName').textContent = name;
  openModal('modalDelete');
}

function togglePass(fieldId, btn) {
  const f = document.getElementById(fieldId);
  f.type = f.type === 'password' ? 'text' : 'password';
}

function setLoading(form) {
  const btn = form.querySelector('[type="submit"]');
  if (btn) {
    btn.classList.add('loading');
    btn.disabled = true;
  }
}
</script>
</body>
</html>