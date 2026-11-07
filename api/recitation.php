<?php
require_once __DIR__ . '/../config/db.php';
requireAjaxAuth();
$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

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

$body   = $_SERVER['REQUEST_METHOD']==='POST' ? (json_decode(file_get_contents('php://input'),true) ?: $_POST) : [];
$action = $_GET['action'] ?? $body['action'] ?? '';

switch ($action) {

    case 'list':
        $studentId=(int)($_GET['student_id']??0);
        if($role==='student') $studentId=$uid;
        if(!$studentId) jsonResponse(false,'student_id مطلوب',400);
        if($role==='teacher'){
            $chk=$pdo->prepare("SELECT COUNT(*) FROM halqa_students hs JOIN halaqat h ON hs.halqa_id=h.id WHERE h.teacher_id=? AND hs.student_id=?");
            $chk->execute([$uid,$studentId]);
            if(!$chk->fetchColumn()) jsonResponse(false,'غير مصرح',403);
        }
        $s=$pdo->prepare("SELECT rl.*,u.name AS teacher_name FROM recitation_log rl LEFT JOIN users u ON rl.teacher_id=u.id WHERE rl.student_id=? ORDER BY rl.recorded_at DESC LIMIT 50");
        $s->execute([$studentId]); jsonResponse(true,$s->fetchAll());

    case 'add':
        if($role!=='teacher') jsonResponse(false,'للمعلمين فقط',403);
        $sid      = (int)($body['student_id']   ?? 0);
        $hid      = (int)($body['halqa_id']     ?? 0);
        $surahN   = (int)($body['surah_number'] ?? 0);
        $surahName= trim($body['surah_name']    ?? '');
        $from     = (int)($body['ayah_from']    ?? 0);
        $to       = (int)($body['ayah_to']      ?? 0);
        $grade    = trim($body['grade']         ?? '');
        $notes    = trim($body['notes']         ?? '');
        $totalAyahs=(int)($body['total_ayahs'] ?? 0); // عدد آيات السورة كاملة

        $allowed=['ممتاز','جيد جداً','جيد','مقبول','يحتاج مراجعة'];
        if(!$sid||!$surahN||!$from||!$to||!$surahName||!in_array($grade,$allowed))
            jsonResponse(false,'بيانات ناقصة أو غير صحيحة',400);

        // النقاط حسب الدرجة
        $pts=match($grade){'ممتاز'=>20,'جيد جداً'=>15,'جيد'=>10,'مقبول'=>5,default=>0};

        // ① حفظ سجل التسميع
        $pdo->prepare("INSERT INTO recitation_log (student_id,teacher_id,halqa_id,surah_number,surah_name,ayah_from,ayah_to,grade,notes,points_awarded) VALUES(?,?,?,?,?,?,?,?,?,?)")
            ->execute([$sid,$uid,$hid?:null,$surahN,$surahName,$from,$to,$grade,$notes,$pts]);
        $newId=(int)$pdo->lastInsertId();

        // ② إضافة النقاط والمستوى والشارات
        $earnedBadges=addPointsToStudent($sid,$pts,$pdo);

        // ③ تلوين الآيات تلقائياً كـ "memorized" إذا الدرجة ممتاز أو جيد جداً
        if(in_array($grade,['ممتاز','جيد جداً'])){
            for($a=$from;$a<=$to;$a++){
                $pdo->prepare("INSERT INTO student_quran_marks (student_id,surah_number,ayah_number,status) VALUES(?,?,?,'memorized')
                               ON DUPLICATE KEY UPDATE status='memorized'")
                    ->execute([$sid,$surahN,$a]);
                $pdo->prepare("INSERT INTO quran_tracking (student_id,surah_number,ayah_number,status) VALUES(?,?,?,'memorized')
                               ON DUPLICATE KEY UPDATE status='memorized', updated_at=CURRENT_TIMESTAMP")
                    ->execute([$sid,$surahN,$a]);
            }
        }

        // ④ فحص إذا الطالب أكمل السورة كاملة → أضف للحديقة
        $newTree=null;
        if($totalAyahs>0){
            // عدد الآيات المحفوظة من هذه السورة
            $mrkStmt=$pdo->prepare("SELECT COUNT(DISTINCT ayah_number) FROM quran_tracking WHERE student_id=? AND surah_number=? AND status='memorized'");
            $mrkStmt->execute([$sid,$surahN]);
            $memorizedCount=(int)$mrkStmt->fetchColumn();

            if($memorizedCount>=$totalAyahs){
                // التحقق أنها غير موجودة في الحديقة
                $gChk=$pdo->prepare("SELECT id FROM student_garden WHERE student_id=? AND surah_number=?");
                $gChk->execute([$sid,$surahN]);
                if(!$gChk->fetch()){
                    $treeType=getTreeType($totalAyahs);
                    $pdo->prepare("INSERT INTO student_garden (student_id,tree_type,surah_name,surah_number) VALUES(?,?,?,?)")
                        ->execute([$sid,$treeType,$surahName,$surahN]);
                    // إشعار للطالب
                    sendNotification($sid,'badge','🌳 شجرة جديدة في حديقتك!',"أتممت حفظ سورة $surahName — أُضيفت شجرة جديدة لحديقتك! تبارك الله",$pdo);
                    $newTree=['type'=>$treeType,'surah'=>$surahName,'emoji'=>['shrub'=>'🌿','palm'=>'🌴','oak'=>'🌳'][$treeType]];
                }
            }
        }

        // ⑤ إشعار للطالب بالتسميع
        sendNotification($sid,'recitation',"تسميع جديد 📖","سجّل معلمك تسميع سورة $surahName (آية $from–$to) — الدرجة: $grade",$pdo);

        jsonResponse(true,[
            'id'           => $newId,
            'points'       => $pts,
            'badges'       => $earnedBadges,
            'new_tree'     => $newTree,
        ]);

    case 'student_summary':
        $studentId=(int)($_GET['student_id']??0);
        if($role==='student') $studentId=$uid;
        if(!$studentId) jsonResponse(false,'student_id مطلوب',400);
        $s=$pdo->prepare("SELECT grade,COUNT(*) AS cnt,SUM(points_awarded) AS pts FROM recitation_log WHERE student_id=? GROUP BY grade");
        $s->execute([$studentId]); $byGrade=$s->fetchAll();
        $t=$pdo->prepare("SELECT COUNT(*),COALESCE(SUM(points_awarded),0) FROM recitation_log WHERE student_id=?");
        $t->execute([$studentId]); $t=$t->fetch(PDO::FETCH_NUM);
        jsonResponse(true,['by_grade'=>$byGrade,'total_sessions'=>(int)$t[0],'total_points'=>(int)$t[1]]);

    case 'delete':
        if($role!=='teacher'&&$role!=='admin') jsonResponse(false,'غير مصرح',403);
        $id=(int)($body['id']??0);
        if(!$id) jsonResponse(false,'id مطلوب',400);
        $pdo->prepare("DELETE FROM recitation_log WHERE id=?")->execute([$id]);
        jsonResponse(true,['deleted'=>true]);

    default:
        jsonResponse(false,'action غير معروف',400);
}
