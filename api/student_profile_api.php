<?php
// المسار: /rattil/api/student_profile_api.php
header('Content-Type: application/json; charset=utf-8');

// استدعاء ملف الاتصال بقاعدة البيانات بناءً على الهيكل الفعلي للمشروع
require_once __DIR__ . '/../config/db.php';
requireRole('admin'); // حماية المسار للأدمن فقط

$action = $_GET['action'] ?? 'get_profile';

// ── Action 1: جلب بيانات الطالب المجمعة ───────────────────────────────────
if ($action === 'get_profile') {
    $student_id = (int)($_GET['id'] ?? 0);

    if (!$student_id) {
        echo json_encode(['success' => false, 'error' => 'معرّف الطالب غير صحيح أو مفقود.']);
        exit;
    }

    try {
        // 1. جلب البيانات الأساسية للطالب والنقاط والمستوى
        $stmtUser = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.phone,
                   COALESCE(sp.points, 0) as points, 
                   COALESCE(sp.total_earned, 0) as total_earned, 
                   COALESCE(sp.level, 1) as stored_level
            FROM users u
            LEFT JOIN student_points sp ON sp.student_id = u.id
            WHERE u.id = ? AND u.role = 'student'
        ");
        $stmtUser->execute([$student_id]);
        $student = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $student['level'] = getLevelForPoints((int)($student['total_earned'] ?? 0));
        }

        if (!$student) {
            echo json_encode(['success' => false, 'error' => 'الطالب غير موجود بالنظام.']);
            exit;
        }

        // 2. جلب الحلقات المسجل بها الطالب والمعلم المسؤول
        $stmtHalaqat = $pdo->prepare("
            SELECT h.name as halqa_name, t.name as teacher_name
            FROM halqa_students hs
            JOIN halaqat h ON h.id = hs.halqa_id
            LEFT JOIN users t ON t.id = h.teacher_id
            WHERE hs.student_id = ?
        ");
        $stmtHalaqat->execute([$student_id]);
        $halaqat = $stmtHalaqat->fetchAll(PDO::FETCH_ASSOC);

        // 3. جلب سجل التسميع والمراجعات الأخير (Timeline)
        $stmtRecitations = $pdo->prepare("
            SELECT l.id, l.surah_name, l.ayah_from, l.ayah_to, l.grade, l.points_awarded, l.notes, l.recorded_at,
                   t.name as teacher_name
            FROM recitation_log l
            LEFT JOIN users t ON t.id = l.teacher_id
            WHERE l.student_id = ?
            ORDER BY l.recorded_at DESC
        ");
        $stmtRecitations->execute([$student_id]);
        $recitations = $stmtRecitations->fetchAll(PDO::FETCH_ASSOC);

        // 4. جلب الشارات والأوسمة التي حصل عليها الطالب
        $stmtBadges = $pdo->prepare("
            SELECT b.name, b.icon, b.description, sb.granted_at
            FROM student_badges sb
            JOIN badges b ON b.id = sb.badge_id
            WHERE sb.student_id = ?
            ORDER BY sb.granted_at DESC
        ");
        $stmtBadges->execute([$student_id]);
        $badges = $stmtBadges->fetchAll(PDO::FETCH_ASSOC);

        // 5. حساب التقدير العام التلقائي بناء على التسميعات الأخيرة
        $generalGrade = 'يحتاج متابعة';
        $gradesArray = array_column($recitations, 'grade');
        if (!empty($gradesArray)) {
            $counts = array_count_values($gradesArray);
            arsort($counts);
            $mostFrequentGrade = key($counts);
            if (in_array($mostFrequentGrade, ['ممتاز', 'جيد جداً'])) {
                $generalGrade = $mostFrequentGrade;
            } elseif (in_array($mostFrequentGrade, ['جيد', 'مقبول'])) {
                $generalGrade = 'منتظم';
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'profile' => $student,
                'halaqat' => $halaqat,
                'recitations' => $recitations,
                'badges' => $badges,
                'general_grade' => $generalGrade,
                'total_recitations' => count($recitations)
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'حدث خطأ بخادم البيانات: ' . $e->getMessage()]);
    }
    exit;
}

// ── Action 2: إضافة ملاحظة سريعة جديدة من نفس الشاشة ─────────────────────────
if ($action === 'add_note') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير صالحة.']);
        exit;
    }

    $student_id = (int)($_POST['student_id'] ?? 0);
    $note_text  = trim($_POST['note'] ?? '');
    $current_user = getCurrentUser($pdo); // الأدمن الحالي الذي يكتب الملاحظة

    if (!$student_id || !$note_text) {
        echo json_encode(['success' => false, 'error' => 'البيانات المرسلة غير كاملة.']);
        exit;
    }

    try {
        // يتم إدراج الملاحظة التنبيهية الإدارية في جدول التسميع أو سجل الملاحظات، هنا سنضيفها كسجل ملاحظة عامة مرتبطة بـ سورة (توجيه عام) لكي يراها الطالب
        $stmt = $pdo->prepare("
            INSERT INTO recitation_log (student_id, teacher_id, surah_number, surah_name, ayah_from, ayah_to, grade, points_awarded, notes, recorded_at)
            VALUES (?, ?, 1, 'توجيه إداري', 1, 1, 'مقبول', 0, ?, NOW())
        ");
        $stmt->execute([$student_id, $current_user['id'], $note_text]);

        // إرسال إشعار فوري للطالب
        sendNotification($student_id, 'note', 'ملاحظة إدارية جديدة 📝', "أضاف لك المسؤول توجيهاً جديداً: $note_text", $pdo);

        echo json_encode(['success' => true, 'message' => 'تمت إضافة الملاحظة بنجاح وإشعار الطالب العلم!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'فشل حفظ الملاحظة: ' . $e->getMessage()]);
    }
    exit;
}