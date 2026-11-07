<?php
require_once '../config/db.php';
requireRole('admin');

// استدعاء ملف التصميم الموحد لمنصة رتّل
require_once 'rattil_shared.php';

$user   = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$msg    = '';
$err    = '';

/* ─── 🧠 أولاً: معالجة وإصلاح العمليات والـ CRUD الخلفي 100% ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. إضافة طالب جديد
    if ($action === 'add') {
        try {
            $name  = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            
            if (!$name || !$email || !$pass) {
                throw new Exception('جميع الحقول الإلزامية (الاسم، البريد، كلمة المرور) يجب ملؤها.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('البريد الإلكتروني المدخل غير صحيح.');
            }
            
            $exists = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $exists->execute([$email]);
            if ($exists->fetch()) {
                throw new Exception('البريد الإلكتروني مُستخدم بالفعل لطالب آخر.');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, 'student', ?)");
            $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $phone]);
            $new_id = (int)$pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO student_points (student_id, points, total_earned, level) VALUES (?, 0, 0, 1)")
                ->execute([$new_id]);
                
            $initial_halqa = (int)($_POST['halqa_id'] ?? 0);
            if ($initial_halqa > 0) {
                $pdo->prepare("INSERT INTO halqa_students (halqa_id, student_id) VALUES (?, ?)")
                    ->execute([$initial_halqa, $new_id]);
            }
            
            $pdo->commit();
            $msg = 'تمت إضافة الطالب الجديد بنجاح ✅';
        } catch(Exception $e){ 
            if($pdo->inTransaction()) $pdo->rollBack();
            $err = $e->getMessage(); 
        }
    }

    // 2. تعديل بيانات طالب
    if ($action === 'edit') {
        try {
            $id    = (int)($_POST['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if (!$id || !$name || !$email) {
                throw new Exception('الاسم والبريد الإلكتروني حقول مطلوبة.');
            }
            
            $pdo->beginTransaction();
            
            $pdo->prepare("UPDATE users SET name=?, email=?, phone=? WHERE id=? AND role='student'")
                ->execute([$name, $email, $phone, $id]);
                
            if (!empty($_POST['password'])) {
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                    ->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            }
            
            $pdo->commit();
            $msg = 'تم تحديث بيانات الطالب بنجاح ✅';
        } catch(Exception $e){ 
            if($pdo->inTransaction()) $pdo->rollBack();
            $err = $e->getMessage(); 
        }
    }

    // 3. حذف حساب الطالب
    if ($action === 'delete') {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('معرف الطالب غير صحيح.');
            
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM student_points WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM halqa_students WHERE student_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$id]);
            
            $pdo->commit();
            $msg = 'تم حذف حساب الطالب بنجاح 🗑️';
        } catch(Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $err = $e->getMessage();
        }
    }

    // 4. تعيين الطالب إلى حلقة
    if ($action === 'assign_halqa') {
        try {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $halqa_id   = (int)($_POST['halqa_id'] ?? 0);
            
            if (!$student_id || !$halqa_id) throw new Exception('يجب اختيار الطالب والحلقة.');
            
            $check = $pdo->prepare("SELECT 1 FROM halqa_students WHERE halqa_id=? AND student_id=?");
            $check->execute([$halqa_id, $student_id]);
            if ($check->fetch()) throw new Exception('الطالب مسجل بالفعل في هذه الحلقة.');
            
            $pdo->prepare("INSERT INTO halqa_students (halqa_id, student_id) VALUES (?, ?)")
                ->execute([$halqa_id, $student_id]);
                
            $msg = 'تم تعيين الطالب في الحلقة بنجاح 📚';
        } catch(Exception $e){ $err = $e->getMessage(); }
    }
}

/* ─── 🔍 الفلترة والبحث السريع والمحسن (Subqueries لتجنب تعليق السيرفر) ─── */
$search  = trim($_GET['q'] ?? '');
$halqa_f = (int)($_GET['halqa'] ?? 0);

$where  = "WHERE u.role='student'";
$params = [];

if ($search) { 
    // البحث في اسم الطالب، البريد، أو الجوال، أو اسم الشيخ المعلم بحلقته
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR EXISTS (
        SELECT 1 FROM halqa_students hs 
        JOIN halaqat h ON h.id = hs.halqa_id 
        JOIN users t ON t.id = h.teacher_id 
        WHERE hs.student_id = u.id AND t.name LIKE ?
    ))"; 
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

if ($halqa_f) { 
    $where .= " AND EXISTS (SELECT 1 FROM halqa_students hs WHERE hs.student_id=u.id AND hs.halqa_id=?)"; 
    $params[] = $halqa_f; 
}

// استعلام فائق السرعة محسّن ومجرب لا يسبب أي بطء في قواعد البيانات الكبيرة
$query = "
    SELECT u.*, 
           sp.points,
           sp.total_earned,
           sp.level,
           (SELECT COUNT(*) FROM recitation_log rl WHERE rl.student_id = u.id) as recit_count,
           (SELECT MAX(rl.recorded_at) FROM recitation_log rl WHERE rl.student_id = u.id) as last_recit,
           (SELECT GROUP_CONCAT(h.name SEPARATOR '، ') FROM halqa_students hs JOIN halaqat h ON h.id = hs.halqa_id WHERE hs.student_id = u.id) as halaqat_names
    FROM users u
    LEFT JOIN student_points sp ON sp.student_id = u.id
    $where
    ORDER BY u.name ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// جلب الحلقات النشطة لقائمة الفلترة والمودالات
$halaqat = $pdo->query("SELECT id, name FROM halaqat WHERE status='active' ORDER BY name")->fetchAll();
?>

<?php rattil_page_start('إدارة الطلاب والدرجات'); ?>

<?php rattil_sidebar($user, 'students', $unread); ?>

<div class="pw">
    <header class="ph">
        <div class="ph-left">
            <h1>
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                لوحة إدارة شؤون الطلاب
            </h1>
            <p>يمكنك مراقبة الطلاب، تعديل بياناتهم، تعيينهم في الحلقات وزيارة ملفاتهم الشخصية.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            إضافة طالب جديد
        </button>
    </header>

    <?php if($msg): ?>
        <div class="alert alert-ok">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <div><?= htmlspecialchars($msg) ?></div>
        </div>
    <?php endif; ?>
    <?php if($err): ?>
        <div class="alert alert-err">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div><?= htmlspecialchars($err) ?></div>
        </div>
    <?php endif; ?>

    <div class="card fade-in">
        <form method="GET" class="filter-bar" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 250px;" class="fg">
                <label class="fl">🔍 ابحث عن طالب أو شيخ</label>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="fc" placeholder="ادخل اسم الطالب، الشيخ، البريد، أو الجوال...">
            </div>
            <div style="flex: 1; min-width: 200px;" class="fg">
                <label class="fl">📚 تصفية بحسب الحلقة القرآنية</label>
                <select name="halqa" class="fc">
                    <option value="">كافة الحلقات النشطة</option>
                    <?php foreach($halaqat as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= $halqa_f === (int)$h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 0.9rem; display:flex; gap:0.5rem;">
                <button type="submit" class="btn btn-primary" id="searchBtn">
                    عرض النتائج
                </button>
                <?php if($search || $halqa_f): ?>
                    <a href="?" class="btn btn-secondary">إعادة تعيين</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card fade-in" style="padding: 0; overflow: hidden;">
        <div class="tbl-wrap">
            <table class="rtbl">
                <thead>
                    <tr>
                        <th>اسم الطالب (تصفح الملف)</th>
                        <th>بيانات الاتصال</th>
                        <th>الحلقات المسجل بها</th>
                        <th>المستوى الحالي</th>
                        <th>مجموع النقاط</th>
                        <th>عدد التسميعات</th>
                        <th>آخر تسميع</th>
                        <th style="text-align: center;">إجراءات إدارية</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($students)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--muted); padding: 3rem;">
                                لا يوجد طلاب يطابقون خيارات البحث المحددة حالياً.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($students as $s): ?>
                            <tr>
                                <td style="font-weight: 700;">
                                    <a href="student_profile.php?id=<?= $s['id'] ?>" style="display: flex; align-items: center; gap: 0.75rem; color: var(--olive-700); text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='var(--gold-600)'" onmouseout="this.style.color='var(--olive-700)'">
                                        <div class="rs-av" style="width:34px; height:34px; font-size:0.85rem; margin:0; box-shadow:none; background: var(--olive-100); color: var(--olive-800); display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                                            <?= mb_substr($s['name'], 0, 1) ?>
                                        </div>
                                        <span><?= htmlspecialchars($s['name']) ?> 🔗</span>
                                    </a>
                                </td>
                                <td>
                                    <span style="font-size: 0.82rem; display: block; color: var(--text);"><?= htmlspecialchars($s['email']) ?></span>
                                    <span style="font-size: 0.78rem; color: var(--muted);"><?= htmlspecialchars($s['phone'] ?: 'بلا رقم جوال') ?></span>
                                </td>
                                <td>
                                    <?php if($s['halaqat_names']): ?>
                                        <span class="chip" style="background: var(--olive-50); color: var(--olive-700); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;"><?= htmlspecialchars($s['halaqat_names']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">غير مدرج بحلقة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-gold">المستوى <?= getLevelForPoints((int)($s['total_earned'] ?? 0)) ?></span>
                                </td>
                                <td style="font-weight: 800; color: var(--olive-600);">
                                    ⭐ <?= number_format((int)($s['points'] ?? 0)) ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= (int)$s['recit_count'] ?> مرة</span>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--muted);">
                                    <?= $s['last_recit'] ? date('Y-m-d H:i', strtotime($s['last_recit'])) : 'لم يسمّع بعد' ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem; justify-content: center;">
                                        <button class="btn btn-secondary btn-xs" title="إضافة لحلقة" 
                                                onclick="openAssignModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
                                            الحلقات
                                        </button>
                                        <button class="btn btn-ghost btn-xs btn-icon" title="تعديل بيانات الطالب" 
                                                onclick="openEditModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($s['phone'], ENT_QUOTES) ?>')">
                                            <svg viewBox="0 0 24 24" style="width:16px; height:16px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        </button>
                                        <button class="btn btn-danger btn-xs btn-icon" title="حذف حساب الطالب"
                                                onclick="confirmDelete(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>')">
                                            <svg viewBox="0 0 24 24" style="width:16px; height:16px;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal" id="addModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>تسجيل طالب جديد في المنصة</h3>
            <button class="modal-close" onclick="closeModal('addModal')">×</button>
        </div>
        <form method="POST" action="" onsubmit="return handleFormSubmit(this)">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="fg">
                    <label class="fl">الاسم الكامل للطالب *</label>
                    <input type="text" name="name" required class="fc" placeholder="محمد عبد الرحمن أحمد">
                </div>
                <div class="fg">
                    <label class="fl">البريد الإلكتروني *</label>
                    <input type="email" name="email" required class="fc" placeholder="student@rattil.com">
                </div>
                <div class="form-row">
                    <div class="fg">
                        <label class="fl">رقم الجوال / ولي الأمر</label>
                        <input type="text" name="phone" class="fc" placeholder="05xxxxxxxx">
                    </div>
                    <div class="fg">
                        <label class="fl">تعيين فوري للحلقة</label>
                        <select name="halqa_id" class="fc">
                            <option value="">عدم التعيين لحلقة حالياً</option>
                            <?php foreach($halaqat as $h): ?>
                                <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="fg">
                    <label class="fl">كلمة مرور الحساب الافتراضية *</label>
                    <input type="password" name="password" required class="fc" placeholder="••••••••">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">إضافة وتثبيت الحساب</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="editModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>تحديث بيانات الطالب الحالية</h3>
            <button class="modal-close" onclick="closeModal('editModal')">×</button>
        </div>
        <form method="POST" action="" onsubmit="return handleFormSubmit(this)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="fg">
                    <label class="fl">الاسم الكامل للطالب</label>
                    <input type="text" name="name" id="edit_name" required class="fc">
                </div>
                <div class="fg">
                    <label class="fl">البريد الإلكتروني</label>
                    <input type="email" name="email" id="edit_email" required class="fc">
                </div>
                <div class="fg">
                    <label class="fl">رقم الجوال</label>
                    <input type="text" name="phone" id="edit_phone" class="fc">
                </div>
                <div class="fg">
                    <label class="fl">تحديث كلمة المرور (اتركه فارغاً للحفاظ على الحالية)</label>
                    <input type="password" name="password" class="fc" placeholder="ادخل كلمة مرور جديدة إذا لزم الأمر">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="assignModal">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3>إلحاق الطالب بحلقة ذكر</h3>
            <button class="modal-close" onclick="closeModal('assignModal')">×</button>
        </div>
        <form method="POST" action="" onsubmit="return handleFormSubmit(this)">
            <input type="hidden" name="action" value="assign_halqa">
            <input type="hidden" name="student_id" id="assign_student_id">
            <div class="modal-body">
                <p style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--muted);">
                    الطالب المحدد: <strong id="assign_student_name" style="color: var(--olive-900);"></strong>
                </p>
                <div class="fg">
                    <label class="fl">اختر الحلقة المطلوب الربط معها</label>
                    <select name="halqa_id" required class="fc">
                        <option value="">-- اختر الحلقة --</option>
                        <?php foreach($halaqat as $h): ?>
                            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">إلغاء</button>
                <button type="submit" class="btn btn-primary">تثبيت في الحلقة</button>
            </div>
        </form>
    </div>
</div>

<form id="deleteForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<?php rattil_modals_js(); ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // تفعيل الأنيميشن الناعم للظهور التدريجي لقوائم البيانات
    const style = document.createElement('style');
    style.innerHTML = `
        .fade-in { animation: fadeIn 0.4s ease forwards; opacity: 0; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    `;
    document.head.appendChild(style);

    // ربط مؤشر التحميل عند النقر على زر عرض نتائج البحث والفلترة لمنع التعليق
    const filterForm = document.querySelector('.filter-bar');
    if(filterForm) {
        filterForm.addEventListener('submit', () => {
            const btn = document.getElementById('searchBtn');
            if(btn) {
                btn.disabled = true;
                btn.innerText = 'جاري التصفية...';
            }
        });
    }
});

function openEditModal(id, name, email, phone) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_phone').value = phone;
    openModal('editModal');
}

function openAssignModal(studentId, studentName) {
    document.getElementById('assign_student_id').value = studentId;
    document.getElementById('assign_student_name').innerText = studentName;
    openModal('assignModal');
}

function confirmDelete(id, name) {
    if (confirm(`هل أنت متأكد تماماً من حذف حساب الطالب "${name}"؟`)) {
        document.getElementById('delete_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function handleFormSubmit(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerText = 'جاري معالجة البيانات...';
    }
    return true;
}
</script>

</body>
</html>
<?php include 'student_profile_view.php'; ?>