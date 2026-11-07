<?php
/**
 * RATTIL - register.php
 * صفحة إنشاء حساب جديد
 */
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    $map = [
        'admin'   => '/rattil/admin/dashboard.php',
        'teacher' => '/rattil/teacher/dashboard.php',
        'student' => '/rattil/student/dashboard.php',
    ];

    header('Location: ' . ($map[$_SESSION['role']] ?? '/rattil/login.php'));
    exit;
}

$error = '';
$old = [
    'name'  => '',
    'email' => '',
    'phone' => '',
    'role'  => 'student',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = trim($_POST['role'] ?? 'student');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $old = compact('name', 'email', 'phone', 'role');

    if ($name === '' || mb_strlen($name) < 2) {
        $error = 'الاسم يجب أن يكون حرفين على الأقل';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صالح';
    } elseif (!in_array($role, ['student', 'teacher', 'admin'], true)) {
        $error = 'الدور غير صالح';
    } elseif (strlen($password) < 8) {
        $error = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'كلمة المرور يجب أن تحتوي على حرف كبير';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'كلمة المرور يجب أن تحتوي على رقم';
    } elseif ($password !== $confirm) {
        $error = 'كلمتا المرور غير متطابقتين';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'البريد الإلكتروني مستخدم مسبقًا';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare("
                    INSERT INTO users
                    (name, email, password, role, phone)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $insert->execute([
                    $name,
                    $email,
                    $hash,
                    $role,
                    $phone ?: null,
                ]);

                $newId = (int)$pdo->lastInsertId();

                if ($role === 'student') {
                    $pdo->prepare("
                        INSERT INTO student_points
                        (student_id, points, total_earned, level)
                        VALUES (?, 0, 0, 1)
                    ")->execute([$newId]);
                }

                session_regenerate_id(true);

                $_SESSION['user_id'] = $newId;
                $_SESSION['name']    = $name;
                $_SESSION['email']   = $email;
                $_SESSION['role']    = $role;

                $map = [
                    'admin'   => '/rattil/admin/dashboard.php',
                    'teacher' => '/rattil/teacher/dashboard.php',
                    'student' => '/rattil/student/dashboard.php',
                ];

                header('Location: ' . $map[$role]);
                exit;
            } catch (PDOException $e) {
                $error = 'حدث خطأ في الخادم، حاول لاحقًا';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إنشاء حساب - رتّل</title>
<link rel="icon" type="image/png" href="/rattil/assets/images/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1B4332;
    --secondary: #2D6A4F;
    --gold: #D4AF37;
    --gold-dark: #B8860B;
    --cream: #FAF7F0;
    --border: #e7dcc0;
    --danger: #dc2626;
    --text: #1f2937;
    --muted: #777;
    --font-title: 'Amiri', serif;
    --font-body: 'Tajawal', sans-serif;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    min-height: 100vh;
    background: linear-gradient(90deg, #eef4ef 0%, #f6eed7 100%);
    color: var(--text);
    font-family: var(--font-body);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 42px 16px;
    position: relative;
    overflow-x: hidden;
}

body::before {
    content: "";
    position: fixed;
    inset: 0;
    background: radial-gradient(circle at 50% 28%, rgba(255, 255, 255, .42), transparent 34%);
    pointer-events: none;
}

a {
    color: inherit;
    text-decoration: none;
}

.auth-wrap {
    width: 100%;
    max-width: 412px;
    position: relative;
    z-index: 1;
}

.auth-card {
    background: #fff;
    border: 1px solid rgba(27, 67, 50, .06);
    border-radius: 20px;
    padding: 38px 30px 36px;
    box-shadow: 0 18px 38px rgba(31, 41, 55, .12);
    animation: fadeUp .4s ease;
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.auth-logo {
    width: 96px;
    height: 96px;
    object-fit: contain;
    display: block;
    margin: 0 auto 18px;
    padding: 14px;
    border-radius: 50%;
    background: #f7f2e8;
    border: 3px solid #fff;
    box-shadow: 0 6px 16px rgba(31, 41, 55, .14);
}

.auth-tag {
    color: var(--gold-dark);
    font-size: .92rem;
    text-align: center;
    margin-bottom: 22px;
    font-family: var(--font-title);
}

.auth-h {
    color: var(--primary);
    font-family: var(--font-title);
    font-size: 1.45rem;
    line-height: 1.2;
    text-align: center;
    margin-bottom: 26px;
}

.alert {
    padding: 13px 14px;
    border-radius: 12px;
    margin-bottom: 18px;
    font-size: .95rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert--err {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: var(--danger);
}

.form {
    display: flex;
    flex-direction: column;
    gap: 17px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.field-l {
    color: var(--primary);
    font-size: .86rem;
    font-weight: 700;
    text-align: center;
}

.field input,
.field select {
    width: 100%;
    height: 45px;
    border: 1px solid #ded8cb;
    background: var(--cream);
    border-radius: 8px;
    padding: 9px 12px;
    font-size: .95rem;
    font-family: var(--font-body);
    color: var(--text);
    outline: none;
    transition: .25s;
}

.field input:focus,
.field select:focus {
    background: #fff;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(27, 67, 50, .10);
}

.field input::placeholder {
    color: #b7b7b7;
}

.btn-primary {
    width: 100%;
    border: none;
    border-radius: 10px;
    background: var(--primary);
    color: #fff;
    cursor: pointer;
    font-family: var(--font-body);
    font-size: 1rem;
    font-weight: 700;
    padding: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: none;
    transition: .3s;
}

.btn-primary:hover {
    background: var(--secondary);
    transform: translateY(-2px);
}

.btn-primary:disabled {
    cursor: not-allowed;
    opacity: .75;
    transform: none;
}

.auth-foot {
    color: var(--muted);
    font-size: .9rem;
    margin-top: 24px;
    text-align: center;
}

.auth-foot a {
    color: var(--gold-dark);
    font-weight: 700;
}

.auth-foot a:hover {
    text-decoration: underline;
}

@media (max-width: 650px) {
    body {
        align-items: flex-start;
        padding-top: 24px;
    }

    .auth-card {
        border-radius: 18px;
        padding: 30px 20px 32px;
    }

    .auth-h {
        font-size: 1.35rem;
    }
}
</style>
</head>
<body>
<main class="auth-wrap">
    <section class="auth-card">
        <a href="/rattil/index.html">
            <img class="auth-logo" src="/rattil/assets/images/logo.png" alt="رتّل" onerror="this.style.display='none'">
        </a>

        <p class="auth-tag">رتّل آياته، فيطمئن قلبك قبل صوتك</p>
        <h1 class="auth-h">إنشاء حساب جديد</h1>

        <?php if ($error): ?>
            <div class="alert alert--err" role="alert" aria-live="polite">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="form" id="registerForm" novalidate>
            <label class="field">
                <span class="field-l">الاسم الكامل</span>
                <input type="text" name="name" required value="<?= htmlspecialchars($old['name'], ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label class="field">
                <span class="field-l">البريد الإلكتروني</span>
                <input type="email" name="email" required value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label class="field">
                <span class="field-l">كلمة المرور</span>
                <input type="password" name="password" id="password" required>
            </label>

            <label class="field">
                <span class="field-l">تأكيد كلمة المرور</span>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </label>

            <label class="field">
                <span class="field-l">الدور</span>
                <select name="role" required>
                    <option value="student" <?= $old['role'] === 'student' ? 'selected' : '' ?>>طالب</option>
                    <option value="teacher" <?= $old['role'] === 'teacher' ? 'selected' : '' ?>>معلم</option>
                    <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>مدير</option>
                </select>
            </label>

            <button class="btn-primary" type="submit" id="submitBtn">
                إنشاء الحساب
            </button>
        </form>

        <p class="auth-foot">
            لديك حساب بالفعل؟
            <a href="/rattil/login.php">تسجيل الدخول</a>
        </p>
    </section>
</main>

<script>
document.getElementById('registerForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'جار إنشاء الحساب...';
});
</script>
</body>
</html>
