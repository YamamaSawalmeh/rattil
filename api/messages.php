<?php
// منع أي output قبل الـ headers
ob_start();

require_once __DIR__ . '/../config/db.php';

// تأكيد: session شغّالة
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'غير مصرح — يرجى تسجيل الدخول أولاً']);
    exit;
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// قبول JSON و Form POST
$raw  = file_get_contents('php://input');
$body = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) $body = $decoded;
}
if (empty($body)) $body = $_POST;
$action = $_GET['action'] ?? $body['action'] ?? '';

try {
    switch ($action) {

        case 'send':
            $rid     = (int)($body['receiver_id'] ?? 0);
            $content = trim($body['content'] ?? '');
            if (!$rid)     { echo json_encode(['success'=>false,'error'=>'receiver_id مطلوب']); exit; }
            if (!$content) { echo json_encode(['success'=>false,'error'=>'محتوى الرسالة مطلوب']); exit; }
            if ($rid===$uid){ echo json_encode(['success'=>false,'error'=>'لا يمكن إرسال رسالة لنفسك']); exit; }

            $recv=$pdo->prepare("SELECT id,name FROM users WHERE id=?");
            $recv->execute([$rid]); $recv=$recv->fetch();
            if (!$recv) { echo json_encode(['success'=>false,'error'=>'المستخدم غير موجود']); exit; }

            $pdo->prepare("INSERT INTO messages (sender_id,receiver_id,content) VALUES(?,?,?)")
                ->execute([$uid,$rid,$content]);
            $mid=(int)$pdo->lastInsertId();

            $senderName=$_SESSION['name']??'مستخدم';
            sendNotification($rid,'message','رسالة جديدة 💬',
                "رسالة من {$senderName}: ".mb_substr($content,0,60).(mb_strlen($content)>60?'...':''),
                $pdo);

            echo json_encode(['success'=>true,'id'=>$mid,'sent'=>true],JSON_UNESCAPED_UNICODE);
            break;

        case 'inbox':
            $s=$pdo->prepare("SELECT m.*,u.name AS sender_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=? ORDER BY m.sent_at DESC LIMIT 50");
            $s->execute([$uid]);
            echo json_encode(['success'=>true,'data'=>$s->fetchAll()],JSON_UNESCAPED_UNICODE);
            break;

        case 'sent':
            $s=$pdo->prepare("SELECT m.*,u.name AS receiver_name FROM messages m JOIN users u ON m.receiver_id=u.id WHERE m.sender_id=? ORDER BY m.sent_at DESC LIMIT 50");
            $s->execute([$uid]);
            echo json_encode(['success'=>true,'data'=>$s->fetchAll()],JSON_UNESCAPED_UNICODE);
            break;

        case 'conversation':
            $rid2=(int)($_GET['with']??0);
            if(!$rid2){ echo json_encode(['success'=>false,'error'=>'with مطلوب']); exit; }
            $s=$pdo->prepare("SELECT m.*, m.sent_at AS created_at, u.name AS sender_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.sent_at ASC LIMIT 100");
            $s->execute([$uid,$rid2,$rid2,$uid]);
            $pdo->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?")->execute([$uid,$rid2]);
            echo json_encode(['success'=>true,'data'=>$s->fetchAll()],JSON_UNESCAPED_UNICODE);
            break;

        case 'fetch':
            $studentId=(int)($_GET['student_id']??0);
            if(!$studentId){ echo json_encode(['success'=>false,'error'=>'student_id مطلوب']); exit; }
            if($role==='teacher'){
                $chk=$pdo->prepare("SELECT COUNT(*) FROM halqa_students hs JOIN halaqat h ON h.id=hs.halqa_id WHERE h.teacher_id=? AND hs.student_id=?");
                $chk->execute([$uid,$studentId]);
                if(!$chk->fetchColumn()){ echo json_encode(['success'=>false,'error'=>'غير مصرح'],JSON_UNESCAPED_UNICODE); exit; }
            } elseif($role==='student' && $studentId!==$uid) {
                echo json_encode(['success'=>false,'error'=>'غير مصرح'],JSON_UNESCAPED_UNICODE); exit;
            }
            $s=$pdo->prepare("SELECT m.*, m.sent_at AS created_at, u.name AS sender_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?) ORDER BY m.sent_at ASC LIMIT 100");
            $s->execute([$uid,$studentId,$studentId,$uid]);
            $pdo->prepare("UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?")->execute([$uid,$studentId]);
            echo json_encode(['success'=>true,'data'=>$s->fetchAll()],JSON_UNESCAPED_UNICODE);
            break;

        case 'unread_count':
            $s=$pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
            $s->execute([$uid]);
            echo json_encode(['success'=>true,'count'=>(int)$s->fetchColumn()],JSON_UNESCAPED_UNICODE);
            break;

        case 'mark_read':
            $id=(int)($body['id']??0);
            if(!$id){ echo json_encode(['success'=>false,'error'=>'id مطلوب']); exit; }
            $pdo->prepare("UPDATE messages SET is_read=1 WHERE id=? AND receiver_id=?")->execute([$id,$uid]);
            echo json_encode(['success'=>true,'updated'=>true],JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success'=>false,'error'=>"action غير معروف: '$action'"],JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'خطأ في الخادم: '.$e->getMessage()],JSON_UNESCAPED_UNICODE);
}
