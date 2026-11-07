<?php
require_once __DIR__ . '/../config/db.php';
requireRole('admin');
$user   = getCurrentUser($pdo);
$notifs = getUnreadCount($pdo);
$aid    = (int)$_SESSION['user_id'];

$success = ''; 
$error = '';

// معالجة وحفظ البيانات عبر PHP PDO مع التحقق الصارم والآمن
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? ''); 
        $phone = trim($_POST['phone'] ?? '');
        
        if (mb_strlen($name) < 2) {
            $error = 'الاسم الكامل يجب أن يكون حرفين على الأقل.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $phone ?: null, $aid]);
            $_SESSION['name'] = $name;
            $user['name'] = $name;
            $user['phone'] = $phone;
            $success = 'تم تحديث معلومات الحساب الشخصية بنجاح.';
        }
    } elseif ($action === 'change_password') {
        $cur  = $_POST['current_password'] ?? ''; 
        $new  = $_POST['new_password'] ?? ''; 
        $conf = $_POST['confirm_password'] ?? '';
        
        // جلب كلمة المرور الحالية للتحقق منها
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$aid]);
        $row = $stmt->fetch();
        
        if (!$row || !password_verify($cur, $row['password'])) {
            $error = 'كلمة المرور الحالية التي أدخلتها غير صحيحة.';
        } elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
            $error = 'كلمة المرور الجديدة ضعيفة. يجب أن تتكون من 8 أحرف على الأقل، وتحتوي على حرف كبير ورقم.';
        } elseif ($new !== $conf) {
            $error = 'كلمتا المرور الجديدة وتأكيدها غير متطابقتين.';
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$new_hash, $aid]);
            $success = 'تمت إعادة تعيين كلمة المرور بنجاح لأمان حسابك.';
        }
    }
}

// استدعاء نظام التصميم المشترك لـ رتّل
if (!function_exists('rattil_global_styles')) {
    include_once __DIR__ . '/rattil_shared.php';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات الحساب — منصة رتِّل</title>
    <?php rattil_global_styles(); ?>
    <style>
        /* ══════════════════════════════════════════════════
           نظام التصميم الجديد: البسيط والمرتب والحديث (Minimal v2)
           ══════════════════════════════════════════════════ */
        
        /* مظهر لوحة التحكم المتناسق الافتراضي (الوضع الفاتح) */
        :root {
            --settings-bg: #f8faf9;
            --settings-card: #ffffff;
            --settings-text: #1e293b;
            --settings-muted: #64748b;
            --settings-border: #e2e8f0;
            --settings-input-bg: #ffffff;
            --settings-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
            
            /* ألوان رتّل المعتمدة الهادئة */
            --color-olive: #1d4a32;
            --color-olive-hover: #163324;
            --color-beige: #f4f1ea;
            --color-beige-dark: #e9e4d9;
            --color-light-gray: #f1f5f9;
        }

        /* متغيرات النمط الداكن الفعلي المستقر */
        body.dark-mode {
            --settings-bg: #0b130e;
            --settings-card: #121d16;
            --settings-text: #f1f5f9;
            --settings-muted: #94a3b8;
            --settings-border: #1e2e24;
            --settings-input-bg: #16241c;
            --settings-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            --color-beige: #1a2920;
            --color-beige-dark: #23372b;
            --color-light-gray: #16241c;
        }

        /* تهيئة عامة لهيكل الصفحة المستقر */
        body {
            background-color: var(--settings-bg);
            color: var(--settings-text);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* حاوية التوسيط والتنظيم الداخلية لمنع العناصر الطايرة */
        .settings-content-container {
            max-width: 760px;
            margin: 0 auto;
            padding: 1rem 0 3rem 0;
        }

        /* هيدر الصفحة البسيط والأنيق */
        .settings-simple-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--settings-border);
        }

        .settings-simple-header svg {
            width: 26px;
            height: 26px;
            stroke: var(--color-olive);
            fill: none;
            stroke-width: 2;
        }

        body.dark-mode .settings-simple-header svg {
            stroke: var(--olive-300);
        }

        .settings-simple-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--settings-text);
        }

        .settings-simple-header p {
            font-size: 0.88rem;
            color: var(--settings-muted);
            margin: 0.25rem 0 0 0;
        }

        /* كروت المحتوى المنظمة والعملية */
        .settings-clean-card {
            background-color: var(--settings-card);
            border: 1px solid var(--settings-border);
            border-radius: 8px;
            box-shadow: var(--settings-shadow);
            padding: 1.75rem;
            margin-bottom: 1.75rem;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        .card-inner-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 1.25rem;
            color: var(--color-olive);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .card-inner-title {
            color: var(--olive-200);
        }

        .card-inner-title svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 2;
        }

        /* استمارات الإدخال المرتبة والمحاذاة المتقنة */
        .settings-grid-fields {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 640px) {
            .settings-grid-fields {
                grid-template-columns: 1fr;
            }
        }

        .field-block {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .field-block label {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--settings-text);
        }

        .input-clean {
            font-family: 'Tajawal', sans-serif;
            width: 100%;
            padding: 0.65rem 0.85rem;
            font-size: 0.92rem;
            color: var(--settings-text);
            background-color: var(--settings-input-bg);
            border: 1px solid var(--settings-border);
            border-radius: 6px;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .input-clean:focus {
            border-color: var(--color-olive);
            box-shadow: 0 0 0 3px rgba(29, 74, 50, 0.1);
        }

        .input-clean:disabled {
            background-color: var(--color-light-gray);
            color: var(--settings-muted);
            cursor: not-allowed;
        }

        /* أزرار رتّل البسيطة والعملية */
        .btn-olive-flat {
            font-family: 'Tajawal', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--color-olive);
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.5rem;
            font-size: 0.92rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-olive-flat:hover {
            background-color: var(--color-olive-hover);
        }

        .btn-beige-flat {
            font-family: 'Tajawal', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--color-beige);
            color: var(--settings-text);
            border: 1px solid var(--settings-border);
            padding: 0.65rem 1.5rem;
            font-size: 0.92rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-beige-flat:hover {
            background-color: var(--color-beige-dark);
        }

        /* مكون معلومات المدير المصمم ببساطة تامة ونقاء */
        .manager-summary-box {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        @media (max-width: 480px) {
            .manager-summary-box {
                flex-direction: column;
                text-align: center;
            }
        }

        .manager-avatar-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background-color: var(--color-beige);
            border: 2px solid var(--settings-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--color-olive);
            flex-shrink: 0;
        }

        body.dark-mode .manager-avatar-circle {
            color: var(--olive-200);
        }

        .manager-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .manager-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            color: var(--settings-text);
        }

        .manager-role {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-olive);
        }

        body.dark-mode .manager-role {
            color: var(--olive-300);
        }

        .manager-email {
            font-size: 0.85rem;
            color: var(--settings-muted);
        }

        /* عناصر التبديل الميكانيكية للنمط الداكن (Clean UI Toggle) */
        .toggle-control-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.25rem 0;
        }

        .toggle-label-side {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .toggle-label-side .title {
            font-size: 0.95rem;
            font-weight: 700;
        }

        .toggle-label-side .description {
            font-size: 0.82rem;
            color: var(--settings-muted);
        }

        .switch-input-element {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch-input-element input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider-bar {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #cbd5e1;
            border-radius: 24px;
            transition: background-color 0.2s ease;
        }

        .slider-bar::before {
            content: "";
            position: absolute;
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: #ffffff;
            border-radius: 50%;
            transition: transform 0.2s ease;
        }

        input:checked + .slider-bar {
            background-color: var(--color-olive);
        }

        input:checked + .slider-bar::before {
            transform: translateX(20px);
        }

        /* تنبيهات الأنظمة النظيفة والمسطحة */
        .flat-alert {
            padding: 0.85rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .flat-alert-success {
            background-color: #e6f4ea;
            color: #137333;
            border: 1px solid #ceead6;
        }

        .flat-alert-error {
            background-color: #fce8e6;
            color: #c5221f;
            border: 1px solid #fad2cf;
        }

        body.dark-mode .flat-alert-success {
            background-color: #132d1b;
            color: #81c995;
            border: 1px solid #1b4d2a;
        }

        body.dark-mode .flat-alert-error {
            background-color: #3c1e1e;
            color: #f28b82;
            border: 1px solid #5a2323;
        }
    </style>
</head>
<body>

    <?php rattil_sidebar($user, 'settings', $notifs); ?>
    
    <div class="pw">
        
        <div class="settings-content-container">
            
            <div class="settings-simple-header">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="3" />
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                </svg>
                <div>
                    <h1>إعدادات الحساب</h1>
                    <p>إدارة وتحديث بيانات ملفك الشخصي الإداري والتحكم ببيئة النظام العامة.</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="flat-alert flat-alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flat-alert flat-alert-error">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <div class="settings-clean-card">
                <h3 class="card-inner-title">
                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    ملخص بيانات الهوية الحالية
                </h3>
                <div class="manager-summary-box">
                    <div class="manager-avatar-circle">
                        <?php 
                        // استخراج الحرف الأول من اسم المدير لعرض Initial Avatar عملي ومرتب ونظيف
                        $initialLetter = mb_substr($user['name'] ?? 'م', 0, 1, 'UTF-8');
                        echo htmlspecialchars($initialLetter);
                        ?>
                    </div>
                    <div class="manager-details">
                        <h4 class="manager-name"><?= htmlspecialchars($user['name']) ?></h4>
                        <span class="manager-role">مدير النظام المعتمد</span>
                        <span class="manager-email"><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                </div>
            </div>

            <div class="settings-clean-card">
                <h3 class="card-inner-title">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    تعديل الملف الشخصي للبيانات
                </h3>
                
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="settings-grid-fields">
                        <div class="field-block">
                            <label for="profile-name">الاسم الكامل</label>
                            <input type="text" id="profile-name" name="name" class="input-clean" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="field-block">
                            <label for="profile-phone">رقم الهاتف الجوال</label>
                            <input type="tel" id="profile-phone" name="phone" class="input-clean" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="05xxxxxxxx">
                        </div>
                    </div>
                    
                    <div class="field-block" style="margin-bottom: 1.5rem;">
                        <label>البريد الإلكتروني الأساسي (معرّف ثابت للحماية)</label>
                        <input type="email" class="input-clean" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>

                    <div style="text-align: left;">
                        <button type="submit" class="btn-olive-flat">حفظ تغييرات الهوية</button>
                    </div>
                </form>
            </div>

            <div class="settings-clean-card">
                <h3 class="card-inner-title">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    تغيير كلمة المرور الشخصية
                </h3>
                
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="field-block" style="margin-bottom: 1.25rem;">
                        <label for="pass-current">كلمة المرور الحالية المعتمدة</label>
                        <input type="password" id="pass-current" name="current_password" class="input-clean" required placeholder="••••••••">
                    </div>
                    
                    <div class="settings-grid-fields">
                        <div class="field-block">
                            <label for="pass-new">كلمة المرور الجديدة</label>
                            <input type="password" id="pass-new" name="new_password" class="input-clean" required placeholder="••••••••">
                        </div>
                        <div class="field-block">
                            <label for="pass-conf">تأكيد كلمة المرور الجديدة</label>
                            <input type="password" id="pass-conf" name="confirm_password" class="input-clean" required placeholder="••••••••">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-top: 1.5rem;">
                        <p style="font-size: 0.8rem; color: var(--settings-muted); margin: 0; font-weight: 500;">
                            تنبيه أمان: يجب ألا تقل عن 8 خانات، وتشتمل على حرف كبير واحد ورقماً على الأقل.
                        </p>
                        <button type="submit" class="btn-beige-flat">تحديث معايير المرور</button>
                    </div>
                </form>
            </div>

            <div class="settings-clean-card">
                <h3 class="card-inner-title">
                    <svg viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    خصائص ومظهر النظام العام
                </h3>
                
                <div class="toggle-control-row">
                    <div class="toggle-label-side">
                        <span class="title">النمط الداكن الهادئ (Dark Mode)</span>
                        <span class="description">تغيير خلفيات وعناصر الواجهة بالكامل لتوفير تجربة ليلية مريحة وقليلة الإضاءة.</span>
                    </div>
                    <div>
                        <label class="switch-input-element">
                            <input type="checkbox" id="darkModeSystemToggle">
                            <span class="slider-bar"></span>
                        </label>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const darkModeToggle = document.getElementById('darkModeSystemToggle');
            const bodyElement = document.body;

            // 1. فحص وتطبيق النمط المحفوظ مسبقاً في متصفح المستخدم لضمان الثبات والاتزان فور الاستدعاء
            if (localStorage.getItem('rattil-dark-style') === 'enabled') {
                bodyElement.classList.add('dark-mode');
                darkModeToggle.checked = true;
            } else {
                bodyElement.classList.remove('dark-mode');
                darkModeToggle.checked = false;
            }

            // 2. مراقبة حركة التغيير الفورية للمفتاح (Toggle) وحفظها فورياً
            darkModeToggle.addEventListener('change', () => {
                if (darkModeToggle.checked) {
                    bodyElement.classList.add('dark-mode');
                    localStorage.setItem('rattil-dark-style', 'enabled');
                } else {
                    bodyElement.classList.remove('dark-mode');
                    localStorage.setItem('rattil-dark-style', 'disabled');
                }
            });
        });
    </script>
</body>
</html>