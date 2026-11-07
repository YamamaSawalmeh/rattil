<?php
require_once __DIR__ . '/../config/db.php';

requireAjaxAuth();

$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$body = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : [];
$action = $_GET['action'] ?? $body['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'save' : 'list');
$allowedStatuses = ['memorized', 'reviewing', 'last_stop'];

function ensureTrackingTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS quran_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            surah_number INT NOT NULL,
            ayah_number INT NOT NULL,
            status ENUM('memorized','reviewing','last_stop') NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ayah (student_id, surah_number, ayah_number),
            INDEX idx_qt_student_surah (student_id, surah_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function teacherCanViewStudent(PDO $pdo, int $teacherId, int $studentId): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM halqa_students hs
        JOIN halaqat h ON h.id = hs.halqa_id
        WHERE h.teacher_id = ? AND hs.student_id = ?
    ");
    $stmt->execute([$teacherId, $studentId]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    ensureTrackingTable($pdo);

    if ($action === 'save') {
        if ($role !== 'student') {
            jsonResponse(false, 'للطلاب فقط', 403);
        }

        $surah = (int)($body['surah'] ?? $body['surah_number'] ?? 0);
        $ayah = (int)($body['ayah'] ?? $body['ayah_number'] ?? 0);
        $status = (string)($body['status'] ?? '');

        if ($surah < 1 || $surah > 114 || $ayah < 1) {
            jsonResponse(false, 'بيانات الآية غير صحيحة', 400);
        }

        if ($status === 'none' || $status === '' || $status === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM quran_tracking WHERE student_id=? AND surah_number=? AND ayah_number=?");
            $stmt->execute([$uid, $surah, $ayah]);
            jsonResponse(true, ['removed' => true]);
        }

        if (!in_array($status, $allowedStatuses, true)) {
            jsonResponse(false, 'حالة غير صحيحة', 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO quran_tracking (student_id, surah_number, ayah_number, status)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$uid, $surah, $ayah, $status]);

        jsonResponse(true, [
            'surah_number' => $surah,
            'ayah_number' => $ayah,
            'status' => $status,
        ]);
    }

    if ($action === 'list') {
        $surah = (int)($_GET['surah'] ?? $_GET['surah_number'] ?? 0);
        $studentId = (int)($_GET['student_id'] ?? 0);

        if ($role === 'student') {
            $studentId = $uid;
        } elseif ($role === 'teacher') {
            if (!$studentId || !teacherCanViewStudent($pdo, $uid, $studentId)) {
                jsonResponse(false, 'غير مصرح', 403);
            }
        } elseif ($role !== 'admin') {
            jsonResponse(false, 'غير مصرح', 403);
        }

        if (!$studentId || $surah < 1 || $surah > 114) {
            jsonResponse(false, 'student_id و surah مطلوبان', 400);
        }

        $legacyExists = (bool)$pdo->query("SHOW TABLES LIKE 'student_quran_marks'")->fetchColumn();

        if ($legacyExists) {
            $stmt = $pdo->prepare("
                SELECT ayah_number, status, updated_at FROM (
                    SELECT ayah_number, status, updated_at, 1 AS src
                    FROM quran_tracking
                    WHERE student_id=? AND surah_number=?
                    UNION ALL
                    SELECT ayah_number, status, marked_at AS updated_at, 0 AS src
                    FROM student_quran_marks
                    WHERE student_id=? AND surah_number=?
                ) x
                JOIN (
                    SELECT ayah_number AS ayah_key, MAX(src) AS best_src
                    FROM (
                        SELECT ayah_number, 1 AS src FROM quran_tracking WHERE student_id=? AND surah_number=?
                        UNION ALL
                        SELECT ayah_number, 0 AS src FROM student_quran_marks WHERE student_id=? AND surah_number=?
                    ) y
                    GROUP BY ayah_number
                ) pick ON pick.ayah_key = x.ayah_number AND pick.best_src = x.src
                ORDER BY ayah_number
            ");
            $stmt->execute([$studentId, $surah, $studentId, $surah, $studentId, $surah, $studentId, $surah]);
        } else {
            $stmt = $pdo->prepare("
                SELECT ayah_number, status, updated_at
                FROM quran_tracking
                WHERE student_id=? AND surah_number=?
                ORDER BY ayah_number
            ");
            $stmt->execute([$studentId, $surah]);
        }

        jsonResponse(true, $stmt->fetchAll());
    }

    if ($action === 'summary') {
        $studentId = (int)($_GET['student_id'] ?? 0);
        if ($role === 'student') {
            $studentId = $uid;
        } elseif ($role === 'teacher' && (!teacherCanViewStudent($pdo, $uid, $studentId))) {
            jsonResponse(false, 'غير مصرح', 403);
        }

        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) AS total
            FROM quran_tracking
            WHERE student_id=?
            GROUP BY status
        ");
        $stmt->execute([$studentId]);
        jsonResponse(true, $stmt->fetchAll());
    }

    jsonResponse(false, 'action غير معروف', 400);
} catch (Throwable $e) {
    jsonResponse(false, $e->getMessage(), 500);
}
