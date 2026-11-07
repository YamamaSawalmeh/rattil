<?php
require_once __DIR__ . '/../config/db.php';
requireAjaxAuth();
$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

$body   = $_SERVER['REQUEST_METHOD']==='POST' ? (json_decode(file_get_contents('php://input'),true) ?: $_POST) : [];
$action = $_GET['action'] ?? $body['action'] ?? '';

switch ($action) {
    case 'today':
        // كشف الطلاب لحلقة اليوم
        if($role!=='teacher') jsonResponse(false,'للمعلمين فقط',403);
        $hid=(int)($_GET['halqa_id']??0);
        if(!$hid) jsonResponse(false,'halqa_id مطلوب',400);
        // التحقق أن الحلقة للمعلم
        $chk=$pdo->prepare("SELECT id FROM halaqat WHERE id=? AND teacher_id=?");
        $chk->execute([$hid,$uid]);
        if(!$chk->fetch()) jsonResponse(false,'غير مصرح',403);

        $s=$pdo->prepare("SELECT u.id,u.name,u.avatar,
            (SELECT status FROM attendance WHERE student_id=u.id AND halqa_id=? AND date=CURDATE()) AS today_status
            FROM users u JOIN halqa_students hs ON u.id=hs.student_id WHERE hs.halqa_id=? ORDER BY u.name");
        $s->execute([$hid,$hid]);
        jsonResponse(true,$s->fetchAll());

    case 'save':
        if($role!=='teacher') jsonResponse(false,'للمعلمين فقط',403);
        $hid     =(int)($body['halqa_id']??0);
        $records = $body['records']??[];
        $date    = $body['date']??date('Y-m-d');
        if(!$hid||empty($records)) jsonResponse(false,'بيانات ناقصة',400);

        $validStatuses=['present','absent','late','excused'];
        $saved=0;
        foreach($records as $rec){
            $sid=(int)($rec['student_id']??0);
            $status=trim($rec['status']??'');
            $note=trim($rec['note']??'');
            if(!$sid||!in_array($status,$validStatuses)) continue;

            $pdo->prepare("INSERT INTO attendance (halqa_id,student_id,teacher_id,date,status,note) VALUES(?,?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE status=VALUES(status),note=VALUES(note)")
                ->execute([$hid,$sid,$uid,$date,$status,$note]);

            // إشعار عند الغياب
            if($status==='absent'){
                sendNotification($sid,'attendance','تسجيل غياب',"سجّل المعلم غيابك بتاريخ $date",$pdo);
            }
            $saved++;
        }
        jsonResponse(true,['saved'=>$saved]);

    case 'student_summary':
        $studentId=(int)($_GET['student_id']??0);
        if($role==='student') $studentId=$uid;
        if(!$studentId) jsonResponse(false,'student_id مطلوب',400);

        $s=$pdo->prepare("SELECT status,COUNT(*) AS cnt FROM attendance WHERE student_id=? GROUP BY status");
        $s->execute([$studentId]); $byStatus=$s->fetchAll();
        $total=$pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=?");
        $total->execute([$studentId]); $total=(int)$total->fetchColumn();
        $present=0; foreach($byStatus as $r){if($r['status']==='present') $present=(int)$r['cnt'];}
        $rate=$total>0?round($present/$total*100):0;
        jsonResponse(true,['by_status'=>$byStatus,'total'=>$total,'present'=>$present,'rate'=>$rate]);

    case 'history':
        if($role!=='student') jsonResponse(false,'للطلاب فقط',403);
        $s=$pdo->prepare("SELECT a.*,h.name AS halqa_name FROM attendance a LEFT JOIN halaqat h ON a.halqa_id=h.id WHERE a.student_id=? ORDER BY a.date DESC LIMIT 30");
        $s->execute([$uid]); jsonResponse(true,$s->fetchAll());

    case 'halqa_report':
        if($role!=='teacher'&&$role!=='admin') jsonResponse(false,'غير مصرح',403);
        $hid=(int)($_GET['halqa_id']??0);
        if(!$hid) jsonResponse(false,'halqa_id مطلوب',400);
        $s=$pdo->prepare("SELECT u.name,a.date,a.status,a.note FROM attendance a JOIN users u ON a.student_id=u.id WHERE a.halqa_id=? ORDER BY a.date DESC,u.name ASC LIMIT 200");
        $s->execute([$hid]); jsonResponse(true,$s->fetchAll());

    default:
        jsonResponse(false,'action غير معروف',400);
}
