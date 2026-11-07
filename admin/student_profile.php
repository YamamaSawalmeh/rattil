<?php
require_once __DIR__ . '/../config/db.php';
requireRole('admin');
require_once __DIR__ . '/rattil_shared.php';

$user = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$studentId = (int)($_GET['id'] ?? 0);

rattil_page_start('ملف الطالب');
rattil_sidebar($user, 'students', $unread);
?>

<main class="pw">
  <header class="ph">
    <div class="ph-left">
      <h1>
        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        ملف الطالب الكامل
      </h1>
      <p>عرض شامل لبيانات الطالب، الحلقات، التسميعات، الأوسمة، والملاحظات الإدارية.</p>
    </div>
    <a class="btn btn-secondary" href="/rattil/admin/students.php">العودة للطلاب</a>
  </header>

  <section class="card" style="padding:2rem;text-align:center">
    <?php if($studentId): ?>
      <p style="color:var(--muted);margin-bottom:1rem">سيتم فتح الملف الكامل في اللوحة الجانبية.</p>
      <button class="btn btn-primary" type="button" onclick="openStudentProfile(<?= $studentId ?>)">فتح الملف الآن</button>
    <?php else: ?>
      <p style="color:var(--danger)">لم يتم تحديد طالب.</p>
    <?php endif; ?>
  </section>
</main>

<?php include __DIR__ . '/student_profile_view.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const studentId = <?= $studentId ?>;
  if (studentId) openStudentProfile(studentId);
});
</script>
</body>
</html>
