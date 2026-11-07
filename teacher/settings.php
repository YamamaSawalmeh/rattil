<?php
require_once __DIR__ . '/../config/db.php';
requireRole('teacher');
$user   = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$sid    = (int)$_SESSION['user_id'];

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? ''); 
        $phone = trim($_POST['phone'] ?? '');
        if (mb_strlen($name) < 2) {
            $error = 'الاسم يجب أن يكون حرفين على الأقل.';
        } else {
            $pdo->prepare("UPDATE users SET name=?, phone=? WHERE id=?")->execute([$name, $phone ?: null, $sid]);
            $_SESSION['name'] = $name;
            $user['name']  = $name;
            $user['phone'] = $phone;
            $success = 'تم تحديث البيانات الشخصية بنجاح ✅';
        }
    } elseif ($action === 'change_password') {
        $cur  = $_POST['current_password'] ?? ''; 
        $new  = $_POST['new_password'] ?? ''; 
        $conf = $_POST['confirm_password'] ?? '';
        
        $row = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $row->execute([$sid]);
        $row = $row->fetch();
        
        if (!password_verify($cur, $row['password'])) {
            $error = 'كلمة المرور الحالية المأخوذة غير صحيحة.';
        } elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $error = 'كلمة المرور الجديدة ضعيفة — يجب أن تكون 8 أحرف على الأقل وتحتوي على حرف كبير ورقم.';
        } elseif ($new !== $conf) {
            $error = 'كلمة المرور الجديدة غير متطابقة مع حقل التأكيد.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $sid]);
            $success = 'تم تحديث كلمة المرور بنجاح وامنت حسابك 🔒';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إعدادات الحساب — رتِّل</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1C3329;        
    --primary-light: #2C4A3E;  
    --primary-soft: #EAECE6;   
    --gold: #D4AF37;
    --gold-dark: #B8860B;
    --cream: #FAF7F0;          
    --beige-card: #F4EFE6;     
    --beige-hover: #EFE9DC;    
    --white: #ffffff;
    --gray-dark: #2F3633;      
    --gray: #707A75;
    --gray-light: #EFEFEA;
    --radius-lg: 20px;         
    --radius-md: 14px;
    --radius-sm: 9px;
    --shadow-sm: 0 4px 12px rgba(28, 51, 41, 0.03);
    --shadow-md: 0 12px 35px rgba(28, 51, 41, 0.07);
    --transition: all 0.3s ease;
}

/* ── Dark Mode Class Override ── */
body.dark-mode {
    --cream: #121815;
    --beige-card: #1C2421;
    --white: #242E2A;
    --gray-dark: #EAECE6;
    --gray: #A2AFAA;
    --gray-light: #2C4A3E;
    --beige-hover: #232F2A;
    --shadow-md: 0 12px 35px rgba(0,0,0,0.3);
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Tajawal', sans-serif;
    background: var(--cream);
    color: var(--gray-dark);
    min-height: 100vh;
    display: flex;
    overflow-x: hidden;
    transition: var(--transition);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Sidebar ── */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, var(--primary) 0%, #0F1D17 100%);
    min-height: 100vh;
    position: fixed;
    right: 0;
    top: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
    box-shadow: -4px 0 30px rgba(0,0,0,0.15);
}
.sidebar-logo {
    padding: 32px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    text-align: center;
}
.sidebar-logo .logo-text {
    font-family: 'Amiri', serif;
    font-size: 2.4rem;
    color: var(--gold);
    line-height: 1;
    font-weight: 700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.sidebar-logo .logo-sub { font-size: 0.85rem; color: rgba(255,255,255,0.5); margin-top: 6px; }
.sidebar-user {
    padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; gap: 14px; background: rgba(255,255,255,0.02);
}
.avatar-circle {
    width: 46px; height: 46px; border-radius: 50%;
    background: linear-gradient(135deg, #f3e7c4 0%, var(--gold) 100%);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 1.2rem; color: var(--primary); flex-shrink: 0;
}
.sidebar-user-info .name { color: var(--white); font-weight: 600; font-size: 0.98rem; }
.sidebar-user-info .role { color: var(--gold); font-size: 0.8rem; margin-top: 2px; display: flex; align-items: center; gap: 4px; }
.sidebar-nav { flex: 1; padding: 20px 0; overflow-y: auto; }
.nav-section { padding: 12px 24px 6px; font-size: 0.75rem; color: rgba(255,255,255,0.35); font-weight: 700; letter-spacing: 0.8px; }
.nav-link { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: rgba(255,255,255,0.7); text-decoration: none; transition: var(--transition); font-size: 0.95rem; position: relative; }
.nav-link svg { width: 20px; height: 20px; opacity: 0.7; transition: var(--transition); }
.nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.05); color: var(--white); }
.nav-link:hover svg, .nav-link.active svg { opacity: 1; transform: scale(1.05); }
.nav-link.active { background: linear-gradient(90deg, rgba(255,255,255,0.06) 0%, rgba(255,255,255,0) 100%); color: var(--gold); }
.nav-link.active::before { content: ''; position: absolute; right: 0; top: 8px; bottom: 8px; width: 4px; background: var(--gold); border-radius: 4px 0 0 4px; }
.sidebar-footer { padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.06); }
.logout-btn { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.9rem; transition: var(--transition); }
.logout-btn:hover { color: #ff6b6b; }

/* ── Main Layout ── */
.main {
    margin-right: 280px; flex: 1; min-height: 100vh;
    display: flex; flex-direction: column; width: calc(100% - 280px);
}
.topbar {
    background: rgba(250, 247, 240, 0.85); backdrop-filter: blur(12px);
    padding: 16px 32px; display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid rgba(44, 74, 62, 0.1); position: sticky; top: 0; z-index: 50;
    transition: var(--transition);
}
body.dark-mode .topbar { background: rgba(18, 24, 21, 0.85); }
.topbar-title { font-family: 'Amiri', serif; font-size: 1.6rem; color: var(--primary); font-weight: 700; }
body.dark-mode .topbar-title { color: var(--gold); }

/* ── Content Area ── */
.content { padding: 32px; flex: 1; animation: fadeIn 0.5s ease-out forwards; max-width: 900px; width: 100%; margin: 0 auto; }

/* ── Card Component ── */
.card {
    background: var(--beige-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
    border: 1px solid rgba(44, 74, 62, 0.08); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition); margin-bottom: 32px;
}
.card-header {
    padding: 20px 24px; border-bottom: 1px solid rgba(44, 74, 62, 0.1); display: flex; align-items: center;
    justify-content: space-between; background: linear-gradient(90deg, rgba(44, 74, 62, 0.05) 0%, rgba(255,255,255,0) 100%);
}
.card-title { font-family: 'Amiri', serif; font-size: 1.35rem; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 10px; }
body.dark-mode .card-title { color: var(--white); }
.card-body { padding: 24px; }

/* ── Form Layout ── */
.form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 18px; }
.form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
.form-group label { font-size: 0.9rem; font-weight: 600; color: var(--primary); }
body.dark-mode .form-group label { color: var(--gray); }
.form-control { width: 100%; padding: 12px 16px; border: 1px solid rgba(44, 74, 62, 0.2); border-radius: var(--radius-sm); font-family: 'Tajawal', sans-serif; font-size: 0.95rem; color: var(--gray-dark); background: var(--white); transition: var(--transition); }
.form-control:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(44,74,62,0.1); }
.form-control:disabled { background: var(--gray-light); color: var(--gray); cursor: not-allowed; }

/* ── Buttons ── */
.btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 12px 28px; border-radius: var(--radius-sm);
    font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: var(--transition); border: none; text-decoration: none;
}
.btn-primary { background: var(--primary-light); color: var(--white); }
.btn-primary:hover { background: var(--primary); transform: translateY(-1px); }
.btn-danger { background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; }
.btn-danger:hover { background: #ef4444; color: #white; }

/* ── Dark Mode Toggle Component ── */
.theme-switch-wrapper { display: flex; align-items: center; justify-content: space-between; background: rgba(44,74,62,0.04); padding: 16px; border-radius: var(--radius-md); border: 1px dashed rgba(44,74,62,0.15); }
.theme-switch-text { font-weight: 600; font-size: 0.95rem; color: var(--gray-dark); }
.theme-btn-toggle { background: var(--white); border: 1px solid rgba(44,74,62,0.2); color: var(--primary); padding: 8px 16px; border-radius: 30px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: var(--transition); font-size: 0.85rem; box-shadow: var(--shadow-sm); }
.theme-btn-toggle:hover { background: var(--beige-hover); }

/* ── Alerts ── */
.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
.alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

@media(max-width: 768px) {
    .main { margin-right: 0; width: 100%; }
    .sidebar { transform: translateX(280px); }
    .content { padding: 16px; }
}
</style>
</head>
<body>

<script>
// تطبيق الوضع المحفوظ فوراً لمنع وميض الشاشة الأبيض عند تحميل الصفحة
(function() {
    const savedTheme = localStorage.getItem('rattil-theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
})();
</script>

<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-text">رتِّل</div>
        <div class="logo-sub">منصة إدارة الحلقات القرآنيّة</div>
    </div>
    <div class="sidebar-user">
        <div class="avatar-circle"><?= mb_substr($user['name']??'م', 0, 1, 'utf-8') ?></div>
        <div class="sidebar-user-info">
            <div class="name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="role">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px;display:inline;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                معلم معتمد
            </div>
        </div>
    </div>
    <div class="sidebar-nav">
        <div class="nav-section">الرئيسية</div>
        <a class="nav-link" href="dashboard.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"/></svg>
            لوحة التحكم
        </a>
        <div class="nav-section">الإدارة التعليمية</div>
        <a class="nav-link" href="halaqat.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
            إدارة الحلقات
        </a>
        <a class="nav-link" href="students.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            الطلاب
        </a>
        <a class="nav-link" href="recitation_log.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            جلسات التسميع
        </a>
        <a class="nav-link" href="attendance.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            رصد الحضور والغياب
        </a>
    </div>
    <div class="sidebar-footer">
        <a class="logout-btn" href="../logout.php">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            تسجيل الخروج
        </a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div class="topbar-title">⚙️ إعدادات الحساب والنظام</div>
    </div>

    <div class="content">
        <?php if($success): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="card-title">🎨 مظهر النظام المفضل</div>
            </div>
            <div class="card-body">
                <div class="theme-switch-wrapper">
                    <div class="theme-switch-text">يمكنك الآن التبديل بين الوضع الفاتح والداكن لراحة عينيك أثناء التصفح:</div>
                    <button class="theme-btn-toggle" onclick="toggleTheme()" id="themeToggleBtn">
                        🌙 المظهر الداكن
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">👤 البيانات الشخصية للرتبة</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>الاسم الكامل (المعتمد في الشهادات)</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>رقم الجوال أو التواصل</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="مثال: 059000000">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>البريد الإلكتروني (اسم المستخدم المعتمد - لا يمكن تعديله)</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>

                    <div style="text-align: left; margin-top: 12px;">
                        <button type="submit" class="btn btn-primary">💾 حفظ التغييرات الشخصية</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">🔒 حماية وتغيير كلمة المرور</div>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label>كلمة المرور الحالية للتحقق</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>تأكيد كلمة المرور الجديدة</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    
                    <p style="font-size: 0.82rem; color: var(--gray); margin-bottom: 20px;">
                        💡 معايير القوة: يجب ألا تقل عن 8 خانات، وتحتوي على حرف كبير واحد (A-Z) ورقم واحد على الأقل.
                    </p>

                    <div style="text-align: left;">
                        <button type="submit" class="btn btn-primary">🔐 تحديث كلمة المرور</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="border-color: #fca5a5;">
            <div class="card-header" style="background: rgba(239, 68, 68, 0.03);">
                <div class="card-title" style="color: #ef4444;">🚪 مغادرة الجلسة الحالية</div>
            </div>
            <div class="card-body">
                <p style="color: var(--gray); font-size: 0.92rem; margin-bottom: 16px;">
                    هل ترغب في الخروج من لوحة التحكم الآمنة لـ رتِّل وإنهاء ارتباط المتصفح الحالي؟
                </p>
                <a href="/rattil/logout.php" class="btn btn-danger">تسجيل الخروج الآمن</a>
            </div>
        </div>

    </div>
</div>

<script>
function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('rattil-theme', isDark ? 'dark' : 'light');
    updateToggleButton(isDark);
}

function updateToggleButton(isDark) {
    const btn = document.getElementById('themeToggleBtn');
    if (isDark) {
        btn.innerHTML = '☀️ الوضع الفاتح';
    } else {
        btn.innerHTML = '🌙 الوضع الداكن';
    }
}

// تحديث النص الخاص بالزر عند تشغيل الصفحة بناءً على الوضع الحالي
document.addEventListener('DOMContentLoaded', () => {
    const isDark = document.body.classList.contains('dark-mode');
    updateToggleButton(isDark);
});
</script>
</body>
</html>
