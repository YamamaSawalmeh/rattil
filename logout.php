<?php
/**
 * RATTIL — logout.php
 * تسجيل الخروج وتدمير الجلسة
 */
require_once __DIR__ . '/config/db.php';

// تدمير الجلسة بشكل آمن
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect إلى صفحة الدخول
header('Location: /rattil/login.php');
exit;
