<?php
require_once '../config/db.php';
requireRole('teacher');
$user   = getCurrentUser($pdo);
$unread = getUnreadCount($pdo);
$msg    = '';
$error  = '';

// ── POST handlers ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name     = sanitize($_POST['name'] ?? '');
        $days     = implode(',', array_map('sanitize', $_POST['days'] ?? []));
        $time     = sanitize($_POST['schedule_time'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $max      = (int)($_POST['max_students'] ?? 20);
        $status   = in_array($_POST['status']??'',['active','inactive']) ? $_POST['status'] : 'active';
        if (!$name || !$time) {
            $error = 'الاسم ووقت الحلقة مطلوبان';
        } else {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO halaqat (name,teacher_id,schedule_days,schedule_time,location,max_students,status)
                               VALUES (?,?,?,?,?,?,?)")
                    ->execute([$name, $user['id'], $days, $time, $location, $max, $status]);
                $msg = 'تمت إضافة الحلقة بنجاح';
            } else {
                $hid = (int)($_POST['halqa_id'] ?? 0);
                $check = $pdo->prepare("SELECT id FROM halaqat WHERE id=? AND teacher_id=?");
                $check->execute([$hid, $user['id']]);
                if ($check->fetch()) {
                    $pdo->prepare("UPDATE halaqat SET name=?,schedule_days=?,schedule_time=?,location=?,max_students=?,status=? WHERE id=?")
                        ->execute([$name, $days, $time, $location, $max, $status, $hid]);
                    $msg = 'تم تعديل الحلقة بنجاح';
                }
            }
        }
    }

    if ($action === 'remove_student') {
        $hid = (int)($_POST['halqa_id'] ?? 0);
        $sid = (int)($_POST['student_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM halaqat WHERE id=? AND teacher_id=?");
        $check->execute([$hid, $user['id']]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM halqa_students WHERE halqa_id=? AND student_id=?")->execute([$hid, $sid]);
            $msg = 'تم حذف الطالب من الحلقة';
        }
    }

    if ($action === 'add_student') {
        $hid = (int)($_POST['halqa_id'] ?? 0);
        $sid = (int)($_POST['student_id'] ?? 0);
        $check = $pdo->prepare("SELECT id FROM halaqat WHERE id=? AND teacher_id=?");
        $check->execute([$hid, $user['id']]);
        if ($check->fetch() && $sid) {
            try {
                $ins = $pdo->prepare("INSERT IGNORE INTO halqa_students (halqa_id,student_id) VALUES (?,?)");
                $ins->execute([$hid,$sid]);
                if ($ins->rowCount() > 0) {
                    $info = $pdo->prepare("SELECT u.name AS student_name, h.name AS halqa_name FROM users u JOIN halaqat h ON h.id=? WHERE u.id=? AND h.teacher_id=?");
                    $info->execute([$hid,$sid,$user['id']]);
                    if ($row = $info->fetch()) {
                        sendNotification((int)$user['id'], 'student', 'طالب جديد في الحلقة', "تمت إضافة {$row['student_name']} إلى حلقة {$row['halqa_name']}", $pdo);
                    }
                }
                $msg = 'تمت إضافة الطالب للحلقة بنجاح';
            } catch (\Exception $e) { $error = 'الطالب مسجّل مسبقاً'; }
        }
    }

    if ($action === 'toggle_status') {
        $hid = (int)($_POST['halqa_id'] ?? 0);
        $pdo->prepare("UPDATE halaqat SET status=IF(status='active','inactive','active') WHERE id=? AND teacher_id=?")
            ->execute([$hid, $user['id']]);
        $msg = 'تم تغيير حالة الحلقة';
    }

    if ($action === 'add_plan') {
        $hid        = (int)($_POST['plan_halqa_id'] ?? 0);
        $sid        = (int)($_POST['plan_student_id'] ?? 0);
        $surah_num  = (int)($_POST['surah_number'] ?? 0);
        $surah_name = sanitize($_POST['surah_name'] ?? '');
        $ayah_from  = (int)($_POST['ayah_from'] ?? 1);
        $ayah_to    = (int)($_POST['ayah_to'] ?? 1);
        $target     = $_POST['target_date'] ?: null;
        $status_p   = in_array($_POST['plan_status']??'',['pending','in_progress','completed']) ? $_POST['plan_status'] : 'pending';

        $chk = $pdo->prepare("SELECT id FROM halaqat WHERE id=? AND teacher_id=?");
        $chk->execute([$hid, $user['id']]);
        if ($chk->fetch() && $sid && $surah_num && $surah_name) {
            $schk = $pdo->prepare("SELECT id FROM halqa_students WHERE halqa_id=? AND student_id=?");
            $schk->execute([$hid, $sid]);
            if ($schk->fetch()) {
                $pdo->prepare("INSERT INTO memorization_plan
                    (student_id,halqa_id,surah_number,surah_name,ayah_from,ayah_to,target_date,status)
                    VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$sid,$hid,$surah_num,$surah_name,$ayah_from,$ayah_to,$target,$status_p]);
                $msg = 'تمت إضافة الخطة للطالب بنجاح';
            } else { $error = 'الطالب غير مسجل في هذه الحلقة'; }
        } else { $error = 'بيانات الخطة غير مكتملة'; }
    }

    if ($action === 'update_plan_status') {
        $plan_id   = (int)($_POST['plan_id'] ?? 0);
        $newStatus = in_array($_POST['plan_status']??'',['pending','in_progress','completed']) ? $_POST['plan_status'] : 'pending';
        $chk = $pdo->prepare("SELECT mp.id FROM memorization_plan mp
            JOIN halaqat h ON h.id=mp.halqa_id
            WHERE mp.id=? AND h.teacher_id=?");
        $chk->execute([$plan_id, $user['id']]);
        if ($chk->fetch()) {
            $pdo->prepare("UPDATE memorization_plan SET status=? WHERE id=?")->execute([$newStatus,$plan_id]);
            $msg = 'تم تحديث حالة الخطة بنجاح';
        }
    }

    if ($action === 'delete_plan') {
        $plan_id = (int)($_POST['plan_id'] ?? 0);
        $chk = $pdo->prepare("SELECT mp.id FROM memorization_plan mp
            JOIN halaqat h ON h.id=mp.halqa_id
            WHERE mp.id=? AND h.teacher_id=?");
        $chk->execute([$plan_id, $user['id']]);
        if ($chk->fetch()) {
            $pdo->prepare("DELETE FROM memorization_plan WHERE id=?")->execute([$plan_id]);
            $msg = 'تم حذف الخطة';
        }
    }
}

// ── جلب الحلقات ─────────────────────────────────────────────
$stmtH = $pdo->prepare("SELECT h.*,
    (SELECT COUNT(*) FROM halqa_students hs WHERE hs.halqa_id=h.id) AS student_count
    FROM halaqat h WHERE h.teacher_id=? ORDER BY h.status DESC, h.schedule_time");
$stmtH->execute([$user['id']]);
$halaqat = $stmtH->fetchAll();

$selectedHalqa    = null;
$halqaStudents    = [];
$availableStudents= [];
$halqaPlans       = [];

$selectedId = (int)($_GET['id'] ?? 0);
if (!$selectedId && !empty($halaqat)) $selectedId = $halaqat[0]['id'];

if ($selectedId) {
    $stmtSH = $pdo->prepare("SELECT h.* FROM halaqat h WHERE h.id=? AND h.teacher_id=?");
    $stmtSH->execute([$selectedId, $user['id']]);
    $selectedHalqa = $stmtSH->fetch();

    if ($selectedHalqa) {
        $stmtHS = $pdo->prepare("SELECT u.id,u.name,u.phone,u.email,u.avatar,
            (SELECT sp.points FROM student_points sp WHERE sp.student_id=u.id) AS points,
            hs.joined_at
            FROM halqa_students hs JOIN users u ON u.id=hs.student_id
            WHERE hs.halqa_id=? ORDER BY u.name");
        $stmtHS->execute([$selectedId]);
        $halqaStudents = $stmtHS->fetchAll();

        $stmtAv = $pdo->prepare("SELECT id,name FROM users WHERE role='student'
            AND id NOT IN (SELECT student_id FROM halqa_students WHERE halqa_id=?) ORDER BY name");
        $stmtAv->execute([$selectedId]);
        $availableStudents = $stmtAv->fetchAll();

        $stmtP = $pdo->prepare("SELECT mp.*, u.name as student_name
            FROM memorization_plan mp
            JOIN users u ON u.id=mp.student_id
            WHERE mp.halqa_id=? ORDER BY u.name, mp.target_date ASC");
        $stmtP->execute([$selectedId]);
        $halqaPlans = $stmtP->fetchAll();
    }
}

$ALL_SURAHS = [[1,'الفاتحة',7],[2,'البقرة',286],[3,'آل عمران',200],[4,'النساء',176],[5,'المائدة',120],[6,'الأنعام',165],[7,'الأعراف',206],[8,'الأنفال',75],[9,'التوبة',129],[10,'يونس',109],[11,'هود',123],[12,'يوسف',111],[13,'الرعد',43],[14,'إبراهيم',52],[15,'الحجر',99],[16,'النحل',128],[17,'الإسراء',111],[18,'الكهف',110],[19,'مريم',98],[20,'طه',135],[21,'الأنبياء',112],[22,'الحج',78],[23,'المؤمنون',118],[24,'النور',64],[25,'الفرقان',77],[26,'الشعراء',227],[27,'النمل',93],[28,'القصص',88],[29,'العنكبوت',69],[30,'الروم',60],[31,'لقمان',34],[32,'السجدة',30],[33,'الأحزاب',73],[34,'سبأ',54],[35,'فاطر',45],[36,'يس',83],[37,'الصافات',182],[38,'ص',88],[39,'الزمر',75],[40,'غافر',85],[41,'فصلت',54],[42,'الشورى',53],[43,'الزخرف',89],[44,'الدخان',59],[45,'الجاثية',37],[46,'الأحقاف',35],[47,'محمد',38],[48,'الفتح',29],[49,'الحجرات',18],[50,'ق',45],[51,'الذاريات',60],[52,'الطور',49],[53,'النجم',62],[54,'القمر',55],[55,'الرحمن',78],[56,'الواقعة',96],[57,'الحديد',29],[58,'المجادلة',22],[59,'الحشر',24],[60,'الممتحنة',13],[61,'الصف',14],[62,'الجمعة',11],[63,'المنافقون',11],[64,'التغابن',18],[65,'الطلاق',12],[66,'التحريم',12],[67,'الملک',30],[68,'القلم',52],[69,'الحاقة',52],[70,'المعارج',44],[71,'نوح',28],[72,'الجن',28],[73,'المزمل',20],[74,'المدثر',56],[75,'القيامة',40],[76,'الإنسان',31],[77,'المرسلات',50],[78,'النبأ',40],[79,'النازعات',46],[80,'عبس',42],[81,'التكوير',29],[82,'الانفطار',19],[83,'المطففين',36],[84,'الانشقاق',25],[85,'البروج',22],[86,'الطارق',17],[87,'الأعلى',19],[88,'الغاشية',26],[89,'الفجر',30],[90,'البلد',20],[91,'الشمس',15],[92,'الليل',21],[93,'الضحى',11],[94,'الشرح',8],[95,'التين',8],[96,'العلق',19],[97,'القدر',5],[98,'البينة',8],[99,'الزلزلة',8],[100,'العاديات',11],[101,'القارعة',11],[102,'التكاثر',8],[103,'العصر',3],[104,'الهمزة',9],[105,'الفيل',5],[106,'قريش',4],[107,'الماعون',7],[108,'الكوثر',3],[109,'الكافرون',6],[110,'النصر',3],[111,'المسد',5],[112,'الإخلاص',4],[113,'الفلق',5],[114,'الناس',6]];
$daysList = ['السبت','الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة'];
$statusLabels = ['pending'=>'قادم','in_progress'=>'جارٍ','completed'=>'مكتمل'];
$statusColors = ['pending'=>'#94a3b8','in_progress'=>'#D4AF37','completed'=>'#22c55e'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>إدارة الحلقات — رتِّل</title>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1C3329;        
    --primary-light: #2C4A3E;  
    --primary-soft: #EAECE6;   
    --gold: #D4AF37;
    --gold-dark: #B8860B;
    --gold-soft: #fbf7eb;
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
    --transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Tajawal', sans-serif;
    background: var(--cream);
    color: var(--gray-dark);
    min-height: 100vh;
    display: flex;
    overflow-x: hidden;
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
.sidebar-logo .logo-sub {
    font-size: 0.85rem;
    color: rgba(255,255,255,0.5);
    margin-top: 6px;
}
.sidebar-user {
    padding: 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 14px;
    background: rgba(255,255,255,0.02);
}
.avatar-circle {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f3e7c4 0%, var(--gold) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--primary);
    flex-shrink: 0;
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
.badge-count { margin-right: auto; background: var(--gold); color: var(--primary); border-radius: 20px; padding: 2px 8px; font-size: 0.75rem; font-weight: 700; }
.sidebar-footer { padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.06); }
.logout-btn { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.9rem; transition: var(--transition); }
.logout-btn:hover { color: #ff6b6b; }

/* ── Main Layout ── */
.main {
    margin-right: 280px;
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    width: calc(100% - 280px);
}
.topbar {
    background: rgba(250, 247, 240, 0.85);
    backdrop-filter: blur(12px);
    padding: 16px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(44, 74, 62, 0.1);
    position: sticky;
    top: 0;
    z-index: 50;
}
.topbar-title { font-family: 'Amiri', serif; font-size: 1.6rem; color: var(--primary); font-weight: 700; }
.topbar-actions { display: flex; align-items: center; gap: 16px; }
.notif-btn {
    position: relative; background: var(--white); border: 1px solid rgba(44, 74, 62, 0.15); border-radius: 50%;
    width: 42px; height: 42px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--primary-light);
}
.notif-dot { position: absolute; top: 2px; right: 2px; width: 9px; height: 9px; background: #e74c3c; border-radius: 50%; border: 2px solid #fff; }
.greeting-chip { background: rgba(44, 74, 62, 0.06); padding: 8px 16px; border-radius: 30px; font-size: 0.9rem; font-weight: 600; color: var(--primary); }

/* ── Content Area ── */
.content { padding: 32px; flex: 1; animation: fadeIn 0.5s ease-out forwards; max-width: 1400px; width: 100%; margin: 0 auto; }

/* ── Layout Panels ── */
.layout-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start; }

/* ── Card Component ── */
.card {
    background: var(--beige-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
    border: 1px solid rgba(44, 74, 62, 0.08); overflow: hidden; display: flex; flex-direction: column; transition: var(--transition);
}
.card-header {
    padding: 22px 24px; border-bottom: 1px solid rgba(44, 74, 62, 0.1); display: flex; align-items: center;
    justify-content: space-between; background: linear-gradient(90deg, rgba(44, 74, 62, 0.05) 0%, rgba(255,255,255,0) 100%);
}
.card-title { font-family: 'Amiri', serif; font-size: 1.4rem; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 10px; }
.card-title svg { width: 22px; height: 22px; color: var(--primary-light); }
.card-body { padding: 24px; }

/* ── Halaqat List Side Panel ── */
.hl-list { display: flex; flex-direction: column; gap: 12px; }
.hl-item {
    display: flex; flex-direction: column; padding: 16px; border-radius: var(--radius-md);
    background: var(--white); border: 1px solid rgba(44, 74, 62, 0.08); text-decoration: none; color: inherit; transition: var(--transition);
}
.hl-item:hover, .hl-item.active { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.hl-item.active { border-color: var(--gold); background: var(--gold-soft); }
.hl-name { font-weight: 700; color: var(--primary); font-size: 1.05rem; margin-bottom: 4px; }
.hl-meta { font-size: 0.85rem; color: var(--gray); display: flex; align-items: center; gap: 6px; width: 100%; }
.hl-meta svg { width: 14px; height: 14px; }

/* ── UI Elements & Forms ── */
.alert { padding: 14px 20px; border-radius: var(--radius-md); margin-bottom: 24px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
.alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
.alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

.btn {
    display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: var(--radius-sm);
    font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: var(--transition); border: none; text-decoration: none;
}
.btn-primary { background: var(--primary-light); color: var(--white); }
.btn-primary:hover { background: var(--primary); }
.btn-gold { background: var(--gold); color: var(--primary); }
.btn-gold:hover { background: var(--gold-dark); color: var(--white); }
.btn-sm { padding: 6px 12px; font-size: 0.8rem; border-radius: 6px; }
.btn-danger { background: #e74c3c; color: var(--white); }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.88rem; font-weight: 600; color: var(--primary); margin-bottom: 6px; }
.form-control {
    width: 100%; padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid rgba(44, 74, 62, 0.2);
    background: var(--white); font-family: inherit; font-size: 0.92rem; color: var(--gray-dark);
}
.form-control:focus { outline: none; border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(44,74,62,0.1); }

/* ── Tables ── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px; background: rgba(44, 74, 62, 0.06); font-weight: 700; font-size: 0.88rem; color: var(--primary); text-align: center; border-bottom: 2px solid rgba(44, 74, 62, 0.1); }
.data-table td { padding: 14px; border-bottom: 1px solid rgba(44, 74, 62, 0.05); font-size: 0.92rem; text-align: center; vertical-align: middle; }
.data-table tr:hover td { background: rgba(44, 74, 62, 0.02); }

.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-block; }

/* ── Overlay Panel ── */
.overlay-panel { position: fixed; top: 0; bottom: 0; left: -400px; width: 380px; background: var(--white); box-shadow: -5px 0 25px rgba(0,0,0,0.1); z-index: 120; transition: var(--transition); padding: 32px 24px; overflow-y: auto; }
.overlay-panel.show { left: 0; }
.overlay-bg { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 110; display: none; }
.overlay-bg.show { display: block; }
</style>
</head>
<body>

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
        <a class="nav-link active" href="halaqat.php">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            إدارة الحلقات
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
        <a class="logout-btn" href="logout.php">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            تسجيل الخروج
        </a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div class="topbar-title">إدارة وتوجيه الحلقات</div>
        <div class="topbar-actions">
            <button class="notif-btn" type="button">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:20px;height:20px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <?php if($unread > 0): ?><div class="notif-dot"></div><?php endif; ?>
            </button>
            <div class="greeting-chip">أهلاً بك، أستاذ <?= htmlspecialchars($user['name']) ?></div>
        </div>
    </div>

    <div class="content">
        <?php if($msg): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="layout-grid">
            <div class="hl-side-panel">
                <div style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-family:'Amiri',serif; font-size:1.3rem; color:var(--primary);">حلقاتي</h3>
                    <button class="btn btn-primary btn-sm" onclick="showAddHalqaModal()">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        جديدة
                    </button>
                </div>
                
                <div class="hl-list">
                    <?php if(empty($halaqat)): ?>
                        <p style="color:var(--gray); font-size:0.9rem; text-align:center; padding:20px;">لم تقم بإضافة حلقات بعد.</p>
                    <?php endif; ?>
                    <?php foreach($halaqat as $h): ?>
                        <a href="halaqat.php?id=<?= $h['id'] ?>" class="hl-item <?= $selectedId === (int)$h['id'] ? 'active' : '' ?>">
                            <div class="hl-name"><?= htmlspecialchars($h['name']) ?></div>
                            <div class="hl-meta">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span><?= $h['student_count'] ?> طالب</span>
                                <span style="margin-right:auto;" class="status-badge" style="background:<?= $h['status']=='active'?'#e8f5e9':'#ffebee' ?>; color:<?= $h['status']=='active'?'#2e7d32':'#c62828' ?>;">
                                    <?= $h['status'] === 'active' ? 'نشطة' : 'معطلة' ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hl-main-content">
                <?php if($selectedHalqa): ?>
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                تفاصيل ومواعيد: <?= htmlspecialchars($selectedHalqa['name']) ?>
                            </div>
                            <div>
                                <button class="btn btn-gold btn-sm" onclick="showEditHalqaModal(<?= htmlspecialchars(json_encode($selectedHalqa)) ?>)">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    تعديل بيانات الحلقة
                                </button>
                            </div>
                        </div>
                        <div class="card-body" style="display:grid; grid-template-columns: repeat(3,1fr); gap:16px; font-size:0.95rem;">
                            <div><strong>أيام التجمع:</strong> <?= htmlspecialchars($selectedHalqa['schedule_days'] ?: 'غير محدد') ?></div>
                            <div><strong>التوقيت الأساسي:</strong> <?= htmlspecialchars($selectedHalqa['schedule_time']) ?></div>
                            <div><strong>موقع / قاعة الحلقة:</strong> <?= htmlspecialchars($selectedHalqa['location'] ?: 'غير محدد') ?></div>
                        </div>
                    </div>

                    <div class="card" style="margin-bottom:24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                الطلاب المسجلين بالحلقة (<?= count($halqaStudents) ?>)
                            </div>
                            <div>
                                <button class="btn btn-primary btn-sm" onclick="document.getElementById('addStudentPanel').classList.add('show'); document.getElementById('panelOverlay').classList.add('show');">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                                    إدراج طالب جديد
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if(empty($halqaStudents)): ?>
                                <p style="text-align:center; color:var(--gray);">لا يوجد طلاب مسجلين في هذه الحلقة حالياً.</p>
                            <?php else: ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>الطالب</th>
                                            <th>البريد الإلكتروني</th>
                                            <th>مجموع النقاط</th>
                                            <th>تاريخ الانضمام</th>
                                            <th>إجراءات الإدارة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($halqaStudents as $st): ?>
                                            <tr>
                                                <td style="text-align:right; display:flex; align-items:center; gap:10px;">
                                                    <div class="avatar-circle" style="width:32px; height:32px; font-size:0.9rem;"><?= mb_substr($st['name'],0,1,'utf-8') ?></div>
                                                    <strong><?= htmlspecialchars($st['name']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($st['email']) ?></td>
                                                <td><span style="color:var(--gold-dark); font-weight:700;"><?= (int)$st['points'] ?> ن</span></td>
                                                <td><?= date('Y-m-d', strtotime($st['joined_at'])) ?></td>
                                                <td>
                                                    <button class="btn btn-gold btn-sm" style="padding:4px 8px;" onclick="openPlanModal(<?= $st['id'] ?>, '<?= htmlspecialchars($st['name'], ENT_QUOTES) ?>')">
                                                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                                                        خطة حفظ
                                                    </button>
                                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('هل أنت متأكد من استبعاد الطالب من هذه الحلقة؟');">
                                                        <input type="hidden" name="action" value="remove_student">
                                                        <input type="hidden" name="halqa_id" value="<?= $selectedId ?>">
                                                        <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px;">
                                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-16v4M4 7h16"/></svg>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                المستهدف والخطط التعليمية الحالية للطلاب
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if(empty($halqaPlans)): ?>
                                <p style="text-align:center; color:var(--gray);">لم يتم تعيين أي خطة حفظ لطلاب هذه الحلقة بعد.</p>
                            <?php else: ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>الطالب</th>
                                            <th>السورة المستهدفة</th>
                                            <th>من الآية</th>
                                            <th>إلى الآية</th>
                                            <th>تاريخ الانجاز المطلوب</th>
                                            <th>حالة الانجاز</th>
                                            <th>تحديث / حذف</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($halqaPlans as $p): ?>
                                            <tr>
                                                <td style="text-align:right;"><strong><?= htmlspecialchars($p['student_name']) ?></strong></td>
                                                <td>سورة <?= htmlspecialchars($p['surah_name']) ?></td>
                                                <td><?= $p['ayah_from'] ?></td>
                                                <td><?= $p['ayah_to'] ?></td>
                                                <td><?= $p['target_date'] ? date('Y-m-d', strtotime($p['target_date'])) : 'مفتوح' ?></td>
                                                <td>
                                                    <span class="status-badge" style="background:<?= $statusColors[$p['status']]; ?>20; color:<?= $statusColors[$p['status']]; ?>;">
                                                        <?= $statusLabels[$p['status']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" action="" style="display:inline-block;">
                                                        <input type="hidden" name="action" value="update_plan_status">
                                                        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                                        <select name="plan_status" onchange="this.form.submit()" class="form-control" style="padding:4px; font-size:0.8rem; width:auto; display:inline-block;">
                                                            <option value="pending" <?= $p['status']=='pending'?'selected':'' ?>>قادم</option>
                                                            <option value="in_progress" <?= $p['status']=='in_progress'?'selected':'' ?>>جارٍ</option>
                                                            <option value="completed" <?= $p['status']=='completed'?'selected':'' ?>>مكتمل</option>
                                                        </select>
                                                    </form>
                                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('هل ترغب بحذف هذه الخطة؟')">
                                                        <input type="hidden" name="action" value="delete_plan">
                                                        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px; margin-right:4px;">
                                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-16v4M4 7h16"/></svg>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div style="text-align:center; padding:60px; color:var(--gray);">يرجى اختيار حلقة من القائمة اليمنى أو البدء بإنشاء حلقة جديدة تماماً.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="overlay-bg" id="panelOverlay" onclick="closeAllPanels()"></div>

<div class="overlay-panel" id="addStudentPanel">
    <h3 style="font-family:'Amiri',serif; font-size:1.4rem; color:var(--primary); margin-bottom:20px;">إدراج طالب للحلقة</h3>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add_student">
        <input type="hidden" name="halqa_id" value="<?= $selectedId ?>">
        <div class="form-group">
            <label>اختر من الطلاب المتاحين بالنظام:</label>
            <select name="student_id" class="form-control" required>
                <option value="">-- اختر الطالب --</option>
                <?php foreach($availableStudents as $as): ?>
                    <option value="<?= $as['id'] ?>"><?= htmlspecialchars($as['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:10px;">تأكيد الإضافة للحلقة</button>
    </form>
</div>

<div class="overlay-panel" id="planPanel">
    <h3 style="font-family:'Amiri',serif; font-size:1.4rem; color:var(--primary); margin-bottom:10px;">إضافة خطة حفظ ومستهدف</h3>
    <p style="font-size:0.9rem; color:var(--gray); margin-bottom:20px;">الطالب: <span id="planStudentName" style="font-weight:700; color:var(--primary);"></span></p>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add_plan">
        <input type="hidden" name="plan_halqa_id" value="<?= $selectedId ?>">
        <input type="hidden" name="plan_student_id" id="planStudentId" value="">
        <input type="hidden" name="surah_name" id="surahNameInput" value="">
        
        <div class="form-group">
            <label>السورة الكريمة:</label>
            <select name="surah_number" id="surahSelect" class="form-control" onchange="onSurahChange()" required>
                <option value="">-- اختر السورة --</option>
                <?php foreach($ALL_SURAHS as $s): ?>
                    <option value="<?= $s[0] ?>" data-name="<?= $s[1] ?>" data-ayahs="<?= $s[2] ?>">سورة <?= $s[1] ?> (<?= $s[2] ?> آية)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
            <div class="form-group">
                <label>من الآية:</label>
                <input type="number" name="ayah_from" id="ayahFrom" class="form-control" value="1" min="1">
            </div>
            <div class="form-group">
                <label>إلى الآية:</label>
                <input type="number" name="ayah_to" id="ayahTo" class="form-control" value="1" min="1">
            </div>
        </div>
        <div class="form-group">
            <label>تاريخ الانجاز الأقصى:</label>
            <input type="date" name="target_date" class="form-control">
        </div>
        <div class="form-group">
            <label>حالة البدء:</label>
            <select name="plan_status" class="form-control">
                <option value="pending">قادم (مجدول)</option>
                <option value="in_progress">بدأ بالفعل (جارٍ)</option>
            </select>
        </div>
        <button type="submit" class="btn btn-gold" style="width:100%; justify-content:center; margin-top:14px;">حفظ وتثبيت الخطة</button>
    </form>
</div>

<div class="overlay-panel" id="halqaFormPanel">
    <h3 style="font-family:'Amiri',serif; font-size:1.4rem; color:var(--primary); margin-bottom:20px;" id="halqaPanelTitle">إضافة حلقة جديدة</h3>
    <form method="POST" action="">
        <input type="hidden" name="action" id="halqaAction" value="add">
        <input type="hidden" name="halqa_id" id="halqaIdInput" value="">
        
        <div class="form-group">
            <label>اسم الحلقة التعليمية:</label>
            <input type="text" name="name" id="halqaName" class="form-control" placeholder="مثال: حلقة عاصم الكبرى" required>
        </div>
        <div class="form-group">
            <label>أيام التجمع الأسبوعية:</label>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:0.85rem; background:var(--cream); padding:10px; border-radius:6px;">
                <?php foreach($daysList as $day): ?>
                    <label style="font-weight:normal; display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <input type="checkbox" name="days[]" value="<?= $day ?>" class="day-checkbox"> <?= $day ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-group">
            <label>توقيت الموعد:</label>
            <input type="text" name="schedule_time" id="halqaTime" class="form-control" placeholder="مثال: بعد صلاة العصر" required>
        </div>
        <div class="form-group">
            <label>الموقع / قاعة الدراسة:</label>
            <input type="text" name="location" id="halqaLocation" class="form-control" placeholder="مثال: التوسعة الشرقية - القاعة 4">
        </div>
        <div class="form-group">
            <label>الحد الأقصى للطلاب:</label>
            <input type="number" name="max_students" id="halqaMax" class="form-control" value="20" min="1">
        </div>
        <div class="form-group">
            <label>حالة تفعيل الحلقة:</label>
            <select name="status" id="halqaStatus" class="form-control">
                <option value="active">نشطة ومفعلة</option>
                <option value="inactive">معطلة مؤقتاً</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; margin-top:12px;" id="halqaSubmitBtn">إنشاء وتأكيد الحلقة</button>
    </form>
</div>

<div class="overlay-panel" id="notifPanel">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h3 style="font-family:'Amiri',serif; font-size:1.3rem; color:var(--primary);">التنبيهات والإشعارات</h3>
        <button class="btn btn-primary btn-sm" onclick="markAllRead()" style="font-size:0.75rem; padding:4px 8px;">مقروء الكل</button>
    </div>
    <div id="notifList" style="display:flex; flex-direction:column; gap:10px;"></div>
</div>

<script>
function closeAllPanels() {
    document.querySelectorAll('.overlay-panel').forEach(p => p.classList.remove('show'));
    document.getElementById('panelOverlay').classList.remove('show');
}

function showAddHalqaModal() {
    closeAllPanels();
    document.getElementById('halqaPanelTitle').innerText = 'إنشاء حلقة جديدة';
    document.getElementById('halqaAction').value = 'add';
    document.getElementById('halqaIdInput').value = '';
    document.getElementById('halqaName').value = '';
    document.getElementById('halqaTime').value = '';
    document.getElementById('halqaLocation').value = '';
    document.getElementById('halqaMax').value = '20';
    document.getElementById('halqaStatus').value = 'active';
    document.querySelectorAll('.day-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('halqaSubmitBtn').innerText = 'إنشاء وتأكيد الحلقة';
    
    document.getElementById('halqaFormPanel').classList.add('show');
    document.getElementById('panelOverlay').classList.add('show');
}

function showEditHalqaModal(h) {
    closeAllPanels();
    document.getElementById('halqaPanelTitle').innerText = 'تعديل بيانات الحلقة';
    document.getElementById('halqaAction').value = 'edit';
    document.getElementById('halqaIdInput').value = h.id;
    document.getElementById('halqaName').value = h.name;
    document.getElementById('halqaTime').value = h.schedule_time;
    document.getElementById('halqaLocation').value = h.location || '';
    document.getElementById('halqaMax').value = h.max_students;
    document.getElementById('halqaStatus').value = h.status;
    
    document.querySelectorAll('.day-checkbox').forEach(cb => {
        cb.checked = h.schedule_days ? h.schedule_days.split(',').includes(cb.value) : false;
    });
    document.getElementById('halqaSubmitBtn').innerText = 'تحديث البيانات وحفظها';
    
    document.getElementById('halqaFormPanel').classList.add('show');
    document.getElementById('panelOverlay').classList.add('show');
}

function openPlanModal(studentId, studentName) {
    closeAllPanels();
    document.getElementById('planStudentId').value = studentId;
    document.getElementById('planStudentName').innerText = studentName;
    document.getElementById('planPanel').classList.add('show');
    document.getElementById('panelOverlay').classList.add('show');
}

function onSurahChange() {
    const sel = document.getElementById('surahSelect');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('surahNameInput').value = opt.dataset.name || '';
    const total = parseInt(opt.dataset.ayahs) || 1;
    document.getElementById('ayahFrom').max = total;
    document.getElementById('ayahTo').max   = total;
    document.getElementById('ayahTo').value = total;
}

function toggleNotifPanel(){
    const np = document.getElementById('notifPanel');
    const ov = document.getElementById('panelOverlay');
    if(np.classList.contains('show')){
        closeAllPanels();
    } else {
        closeAllPanels();
        np.classList.add('show');
        ov.classList.add('show');
        loadNotifs();
    }
}

async function loadNotifs(){
    const r = await fetch('/rattil/api/notifications.php?action=list');
    const d = await r.json();
    if(!d.success) return;
    const list = document.getElementById('notifList');
    if(!d.data.length){
        list.innerHTML = '<p style="padding:1.5rem;text-align:center;color:var(--gray)">لا توجد إشعارات</p>';
        return;
    }
    list.innerHTML = d.data.map(n => `
        <div style="padding:12px; border-radius:8px; border:1px solid #eee; background:${n.is_read?'#fff':'var(--gold-soft)'}">
            <div style="font-weight:700; font-size:0.9rem;">${n.title}</div>
            <div style="font-size:0.85rem; color:var(--gray); margin-top:4px;">${n.body||''}</div>
        </div>
    `).join('');
}

async function markAllRead(){
    await fetch('/rattil/api/notifications.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=mark_all_read'});
    loadNotifs();
}
</script>
<script src="/rattil/assets/js/notifications.js?v=20260518"></script>
</body>
</html>
