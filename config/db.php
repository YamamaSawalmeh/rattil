<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host    = 'localhost';
$dbname  = 'rattil_db';
$dbuser  = 'root';
$dbpass  = '';
$charset = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'خطأ في الاتصال بقاعدة البيانات']));
}

function requireRole(string $role): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
        $map = [
            'admin'   => '/rattil/admin/dashboard.php',
            'teacher' => '/rattil/teacher/dashboard.php',
            'student' => '/rattil/student/dashboard.php',
        ];
        if (isset($_SESSION['role']) && isset($map[$_SESSION['role']])) {
            header('Location: ' . $map[$_SESSION['role']]);
        } else {
            header('Location: /rattil/login.php');
        }
        exit;
    }
}

function requireAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /rattil/login.php');
        exit;
    }
}

function requireAjaxAuth(?string $role = null): void {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'غير مصرح']);
        exit;
    }
    if ($role !== null && $_SESSION['role'] !== $role) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'ليس لديك صلاحية']);
        exit;
    }
}

function sendNotification(int $userId, string $type, string $title, string $body, PDO $pdo): void {
    $pdo->prepare("INSERT INTO notifications (user_id,type,title,body) VALUES (?,?,?,?)")
        ->execute([$userId, $type, $title, $body]);
}

function checkAndAwardBadges(int $studentId, PDO $pdo): array {
    $earned = [];
    $row = $pdo->prepare("SELECT total_earned FROM student_points WHERE student_id=?");
    $row->execute([$studentId]);
    $pts = (int)($row->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT * FROM badges WHERE type='points_based' AND points_required<=? ORDER BY points_required ASC");
    $stmt->execute([$pts]);
    foreach ($stmt->fetchAll() as $badge) {
        $ex = $pdo->prepare("SELECT id FROM student_badges WHERE student_id=? AND badge_id=?");
        $ex->execute([$studentId, $badge['id']]);
        if (!$ex->fetch()) {
            $pdo->prepare("INSERT INTO student_badges (student_id,badge_id,granted_by) VALUES (?,?,NULL)")
                ->execute([$studentId, $badge['id']]);
            sendNotification($studentId,'badge','شارة جديدة! 🏅',"حصلت على شارة: {$badge['name']}",$pdo);
            $earned[] = $badge;
        }
    }
    return $earned;
}

function getLevelForPoints(int $totalEarned): int {
    return match (true) {
        $totalEarned >= 2000 => 5,
        $totalEarned >= 1000 => 4,
        $totalEarned >= 500  => 3,
        $totalEarned >= 100  => 2,
        default              => 1,
    };
}

function addPointsToStudent(int $studentId, int $points, PDO $pdo): array {
    if ($points <= 0) return [];
    $pdo->prepare("INSERT INTO student_points (student_id,points,total_earned,level) VALUES (?,?,?,1)
                   ON DUPLICATE KEY UPDATE points=points+VALUES(points), total_earned=total_earned+VALUES(total_earned)")
        ->execute([$studentId, $points, $points]);

    $stmt = $pdo->prepare("SELECT total_earned FROM student_points WHERE student_id=?");
    $stmt->execute([$studentId]);
    $level = getLevelForPoints((int)($stmt->fetchColumn() ?: 0));
    $pdo->prepare("UPDATE student_points SET level=? WHERE student_id=?")->execute([$level, $studentId]);
    return checkAndAwardBadges($studentId, $pdo);
}

function isSurahCompleted(int $studentId, int $surahNumber, int $totalAyahs, PDO $pdo): bool {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ayah_to - ayah_from + 1),0) FROM recitation_log WHERE student_id=? AND surah_number=?");
    $stmt->execute([$studentId, $surahNumber]);
    return (int)$stmt->fetchColumn() >= $totalAyahs;
}

function getTreeType(int $totalAyahs): string {
    if ($totalAyahs < 20)  return 'shrub';
    if ($totalAyahs <= 100) return 'palm';
    return 'oak';
}

function getCurrentUser(PDO $pdo): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT id,name,email,role,avatar,phone,created_at FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function getUnreadCount(PDO $pdo): int {
    if (!isset($_SESSION['user_id'])) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$_SESSION['user_id']]);
    return (int)$stmt->fetchColumn();
}

function sanitize(mixed $v): string {
    return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(bool $success, mixed $data = null, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $r = ['success' => $success];
    if ($success && $data !== null)      $r['data']  = $data;
    elseif (!$success && is_string($data)) $r['error'] = $data;
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
