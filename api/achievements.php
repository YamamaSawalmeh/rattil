<?php
require_once __DIR__ . '/../config/db.php';
requireAjaxAuth();
$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

$body   = $_SERVER['REQUEST_METHOD']==='POST' ? (json_decode(file_get_contents('php://input'),true) ?: $_POST) : [];
$action = $_GET['action'] ?? $body['action'] ?? '';

switch ($action) {
    case 'badges':
        $sid=$role==='student'?$uid:(int)($_GET['student_id']??$uid);
        // المعلم يتحقق أن الطالب في حلقته
        if($role==='teacher'&&$sid!==$uid){
            $chk=$pdo->prepare("SELECT COUNT(*) FROM halqa_students hs JOIN halaqat h ON hs.halqa_id=h.id WHERE h.teacher_id=? AND hs.student_id=?");
            $chk->execute([$uid,$sid]);
            if(!$chk->fetchColumn()) jsonResponse(false,'غير مصرح',403);
        }
        $s=$pdo->prepare("SELECT sb.*,b.name,b.icon,b.description,b.type,u.name AS granter_name FROM student_badges sb JOIN badges b ON sb.badge_id=b.id LEFT JOIN users u ON sb.granted_by=u.id WHERE sb.student_id=? ORDER BY sb.granted_at DESC");
        $s->execute([$sid]); jsonResponse(true,$s->fetchAll());

    case 'grant_badge':
        if($role!=='teacher'&&$role!=='admin') jsonResponse(false,'للمعلمين فقط',403);
        $sid=(int)($body['student_id']??0);
        $bid=(int)($body['badge_id']??0);
        if(!$sid||!$bid) jsonResponse(false,'بيانات ناقصة',400);
        // التحقق أن الشارة من نوع teacher_granted
        $badge=$pdo->prepare("SELECT * FROM badges WHERE id=? AND type='teacher_granted'");
        $badge->execute([$bid]); $badge=$badge->fetch();
        if(!$badge) jsonResponse(false,'الشارة غير صحيحة',400);

        try{
            $pdo->prepare("INSERT INTO student_badges (student_id,badge_id,granted_by) VALUES(?,?,?)")
                ->execute([$sid,$bid,$uid]);
            sendNotification($sid,'badge',"شارة جديدة! {$badge['icon']}","منحك المعلم شارة: {$badge['name']}",$pdo);
            jsonResponse(true,['granted'=>true,'badge'=>$badge]);
        }catch(\PDOException $e){
            jsonResponse(false,'الطالب يمتلك هذه الشارة بالفعل',409);
        }

    case 'points':
        $sid=$role==='student'?$uid:(int)($_GET['student_id']??$uid);
        $s=$pdo->prepare("SELECT * FROM student_points WHERE student_id=?");
        $s->execute([$sid]);
        $pts=$s->fetch()?:['points'=>0,'total_earned'=>0,'level'=>1];
        jsonResponse(true,$pts);

    case 'leaderboard':
        $hid=(int)($_GET['halqa_id']??0);
        if($hid){
            $s=$pdo->prepare("SELECT u.id,u.name,sp.points,sp.total_earned,sp.level FROM users u JOIN student_points sp ON u.id=sp.student_id JOIN halqa_students hs ON u.id=hs.student_id WHERE hs.halqa_id=? ORDER BY sp.total_earned DESC LIMIT 10");
            $s->execute([$hid]);
        }else{
            $s=$pdo->prepare("SELECT u.id,u.name,sp.points,sp.total_earned,sp.level FROM users u JOIN student_points sp ON u.id=sp.student_id WHERE u.role='student' ORDER BY sp.total_earned DESC LIMIT 10");
            $s->execute();
        }
        jsonResponse(true,$s->fetchAll());

    case 'garden':
        $sid=$role==='student'?$uid:(int)($_GET['student_id']??$uid);
        $s=$pdo->prepare("SELECT * FROM student_garden WHERE student_id=? ORDER BY planted_at DESC");
        $s->execute([$sid]); jsonResponse(true,$s->fetchAll());

    case 'all_badges':
        // جميع الشارات المتاحة في النظام
        $s=$pdo->query("SELECT * FROM badges ORDER BY type,points_required");
        jsonResponse(true,$s->fetchAll());

    default:
        jsonResponse(false,'action غير معروف',400);
}
