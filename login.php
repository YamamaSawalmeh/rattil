<?php
require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    $map = [
        'admin'   => '/rattil/admin/dashboard.php',
        'teacher' => '/rattil/teacher/dashboard.php',
        'student' => '/rattil/student/dashboard.php'
    ];

    header('Location: ' . ($map[$_SESSION['role']] ?? '/rattil/login.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور.';
    } else {
        $stmt = $pdo->prepare("SELECT id,name,email,password,role FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            $map = [
                'admin'   => '/rattil/admin/dashboard.php',
                'teacher' => '/rattil/teacher/dashboard.php',
                'student' => '/rattil/student/dashboard.php'
            ];

            header('Location: ' . $map[$user['role']]);
            exit;

        } else {
            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>تسجيل الدخول — رتِّل</title>

<link rel="icon" type="image/png" href="/rattil/assets/images/logo.png">

<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

<style>
:root{
    --primary:#1B4332;
    --secondary:#2D6A4F;
    --gold:#D4AF37;
    --cream:#FAF7F0;
    --text:#1f2937;

    --font-title:'Amiri', serif;
    --font-body:'Tajawal', sans-serif;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    min-height:100vh;
    background:var(--cream);
    font-family:var(--font-body);
    display:flex;
    align-items:center;
    justify-content:center;
    padding:24px;
    position:relative;
    overflow:hidden;
}

body::before{
    content:'';
    position:fixed;
    inset:0;
    background:
        radial-gradient(circle at 20% 30%, rgba(27,67,50,.08), transparent 40%),
        radial-gradient(circle at 80% 70%, rgba(212,175,55,.08), transparent 40%);
    pointer-events:none;
}

.auth-wrap{
    width:100%;
    max-width:430px;
    position:relative;
    z-index:1;
}

.auth-card{
    background:#fff;
    border-radius:30px;
    padding:42px 34px;
    box-shadow:0 10px 35px rgba(0,0,0,.10);
    border:1px solid rgba(212,175,55,.15);
    animation:fadeUp .4s ease;
}

@keyframes fadeUp{
    from{
        opacity:0;
        transform:translateY(18px);
    }
    to{
        opacity:1;
        transform:translateY(0);
    }
}

.auth-logo{
    width:88px;
    display:block;
    margin:0 auto 14px;
}

.auth-tag{
    text-align:center;
    color:#777;
    margin-bottom:28px;
    font-size:.95rem;
}

.auth-h{
    text-align:center;
    font-family:var(--font-title);
    color:var(--primary);
    margin-bottom:24px;
    font-size:2rem;
}

.alert{
    padding:13px 14px;
    border-radius:12px;
    margin-bottom:18px;
    font-size:.95rem;
}

.alert--err{
    background:#fef2f2;
    color:#dc2626;
    border:1px solid #fecaca;
}

.form{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.field{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.field-l{
    color:var(--primary);
    font-weight:700;
    font-size:.95rem;
}

.input-wrap{
    position:relative;
}

.input-icon{
    position:absolute;
    top:50%;
    right:14px;
    transform:translateY(-50%);
    color:#aaa;
    font-size:1rem;
}

.field input{
    width:100%;
    border:2px solid rgba(212,175,55,.22);
    background:var(--cream);
    border-radius:14px;
    padding:14px 46px 14px 44px;
    font-size:1rem;
    font-family:var(--font-body);
    transition:.25s;
    outline:none;
    color:var(--text);
}

.field input:focus{
    border-color:var(--gold);
    background:#fff;
}

.field input::placeholder{
    color:#bbb;
}

.toggle-password{
    position:absolute;
    top:50%;
    left:14px;
    transform:translateY(-50%);
    border:none;
    background:none;
    cursor:pointer;
    font-size:1rem;
    color:#999;
}

.btn{
    border:none;
    cursor:pointer;
    border-radius:14px;
    transition:.3s;
    font-family:var(--font-body);
    font-weight:700;
}

.btn-primary{
    background:var(--primary);
    color:#fff;
    padding:15px;
    font-size:1.05rem;
    box-shadow:0 6px 16px rgba(27,67,50,.25);
}

.btn-primary:hover{
    background:var(--secondary);
    transform:translateY(-2px);
}

.auth-foot{
    margin-top:18px;
    text-align:center;
    color:#777;
    font-size:.95rem;
}

.auth-foot a{
    color:#B8860B;
    text-decoration:none;
    font-weight:700;
}

.auth-foot a:hover{
    text-decoration:underline;
}

.demo-creds{
    margin-top:24px;
    background:rgba(27,67,50,.04);
    border:1px dashed rgba(27,67,50,.18);
    border-radius:14px;
    padding:14px;
    font-size:.85rem;
}

.demo-creds summary{
    cursor:pointer;
    color:var(--primary);
    font-weight:700;
    margin-bottom:10px;
}

.demo-creds ul{
    padding-right:18px;
    line-height:2;
}

.back-link{
    position:fixed;
    top:20px;
    right:24px;
    text-decoration:none;
    color:var(--primary);
    opacity:.7;
    font-weight:700;
}

.back-link:hover{
    opacity:1;
}

@media(max-width:480px){

    .auth-card{
        padding:30px 20px;
        border-radius:22px;
    }

    .auth-h{
        font-size:1.7rem;
    }
}
</style>
</head>

<body>

<a href="/rattil/index.html" class="back-link">← الرئيسية</a>

<div class="auth-wrap">

    <div class="auth-card">

        <a href="/rattil/index.html">
            <img class="auth-logo"
                 src="/rattil/assets/images/logo.png"
                 alt="رتِّل"
                 onerror="this.style.display='none'">
        </a>

        <p class="auth-tag">
            رتِّل آياته، فيطمئن قلبك قبل صوتك
        </p>

        <h1 class="auth-h">تسجيل الدخول</h1>

        <?php if($error): ?>
            <div class="alert alert--err">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="form" id="loginForm">

            <label class="field">

                <span class="field-l">البريد الإلكتروني</span>

                <div class="input-wrap">

                    <span class="input-icon">✉️</span>

                    <input
                        type="email"
                        name="email"
                        placeholder="example@rattil.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >

                </div>

            </label>

            <label class="field">

                <span class="field-l">كلمة المرور</span>

                <div class="input-wrap">

                    <span class="input-icon">🔒</span>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="••••••••"
                        required
                    >

                    <button type="button"
                            class="toggle-password"
                            onclick="togglePassword()"
                            id="toggleBtn">
                        👁️
                    </button>

                </div>

            </label>

            <button class="btn btn-primary" type="submit" id="submitBtn">
                تسجيل الدخول
            </button>

        </form>

        <p class="auth-foot">
            <a href="/rattil/forgot_password.php">
                نسيت كلمة المرور؟
            </a>
        </p>

        <p class="auth-foot">
            ليس لديك حساب؟
            <a href="/rattil/register.php">
                إنشاء حساب جديد
            </a>
        </p>

        <details class="demo-creds">
            <summary>حسابات تجريبية</summary>

            <ul>
                <li><b>مدير:</b> admin@rattil.com — <code>Admin@123</code></li>
                <li><b>معلم:</b> teacher@rattil.com — <code>Teacher@123</code></li>
                <li><b>طالب:</b> student@rattil.com — <code>Student@123</code></li>
            </ul>
        </details>

    </div>

</div>

<script>
function togglePassword(){

    const password = document.getElementById('password');
    const btn = document.getElementById('toggleBtn');

    if(password.type === 'password'){
        password.type = 'text';
        btn.textContent = '🙈';
    }else{
        password.type = 'password';
        btn.textContent = '👁️';
    }
}

document.getElementById('loginForm').addEventListener('submit', function(){

    const btn = document.getElementById('submitBtn');

    btn.disabled = true;
    btn.textContent = 'جارٍ تسجيل الدخول...';
});
</script>

</body>
</html>