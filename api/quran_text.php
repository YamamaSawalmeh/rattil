<?php
ob_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>'غير مصرح']);
    exit;
}
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// قائمة السور الـ 114
$SURAHS = [
    [1,'الفاتحة',7,'Meccan'],[2,'البقرة',286,'Medinan'],[3,'آل عمران',200,'Medinan'],
    [4,'النساء',176,'Medinan'],[5,'المائدة',120,'Medinan'],[6,'الأنعام',165,'Meccan'],
    [7,'الأعراف',206,'Meccan'],[8,'الأنفال',75,'Medinan'],[9,'التوبة',129,'Medinan'],
    [10,'يونس',109,'Meccan'],[11,'هود',123,'Meccan'],[12,'يوسف',111,'Meccan'],
    [13,'الرعد',43,'Medinan'],[14,'إبراهيم',52,'Meccan'],[15,'الحجر',99,'Meccan'],
    [16,'النحل',128,'Meccan'],[17,'الإسراء',111,'Meccan'],[18,'الكهف',110,'Meccan'],
    [19,'مريم',98,'Meccan'],[20,'طه',135,'Meccan'],[21,'الأنبياء',112,'Meccan'],
    [22,'الحج',78,'Medinan'],[23,'المؤمنون',118,'Meccan'],[24,'النور',64,'Medinan'],
    [25,'الفرقان',77,'Meccan'],[26,'الشعراء',227,'Meccan'],[27,'النمل',93,'Meccan'],
    [28,'القصص',88,'Meccan'],[29,'العنكبوت',69,'Meccan'],[30,'الروم',60,'Meccan'],
    [31,'لقمان',34,'Meccan'],[32,'السجدة',30,'Meccan'],[33,'الأحزاب',73,'Medinan'],
    [34,'سبأ',54,'Meccan'],[35,'فاطر',45,'Meccan'],[36,'يس',83,'Meccan'],
    [37,'الصافات',182,'Meccan'],[38,'ص',88,'Meccan'],[39,'الزمر',75,'Meccan'],
    [40,'غافر',85,'Meccan'],[41,'فصلت',54,'Meccan'],[42,'الشورى',53,'Meccan'],
    [43,'الزخرف',89,'Meccan'],[44,'الدخان',59,'Meccan'],[45,'الجاثية',37,'Meccan'],
    [46,'الأحقاف',35,'Meccan'],[47,'محمد',38,'Medinan'],[48,'الفتح',29,'Medinan'],
    [49,'الحجرات',18,'Medinan'],[50,'ق',45,'Meccan'],[51,'الذاريات',60,'Meccan'],
    [52,'الطور',49,'Meccan'],[53,'النجم',62,'Meccan'],[54,'القمر',55,'Meccan'],
    [55,'الرحمن',78,'Medinan'],[56,'الواقعة',96,'Meccan'],[57,'الحديد',29,'Medinan'],
    [58,'المجادلة',22,'Medinan'],[59,'الحشر',24,'Medinan'],[60,'الممتحنة',13,'Medinan'],
    [61,'الصف',14,'Medinan'],[62,'الجمعة',11,'Medinan'],[63,'المنافقون',11,'Medinan'],
    [64,'التغابن',18,'Medinan'],[65,'الطلاق',12,'Medinan'],[66,'التحريم',12,'Medinan'],
    [67,'الملك',30,'Meccan'],[68,'القلم',52,'Meccan'],[69,'الحاقة',52,'Meccan'],
    [70,'المعارج',44,'Meccan'],[71,'نوح',28,'Meccan'],[72,'الجن',28,'Meccan'],
    [73,'المزمل',20,'Meccan'],[74,'المدثر',56,'Meccan'],[75,'القيامة',40,'Meccan'],
    [76,'الإنسان',31,'Medinan'],[77,'المرسلات',50,'Meccan'],[78,'النبأ',40,'Meccan'],
    [79,'النازعات',46,'Meccan'],[80,'عبس',42,'Meccan'],[81,'التكوير',29,'Meccan'],
    [82,'الانفطار',19,'Meccan'],[83,'المطففين',36,'Meccan'],[84,'الانشقاق',25,'Meccan'],
    [85,'البروج',22,'Meccan'],[86,'الطارق',17,'Meccan'],[87,'الأعلى',19,'Meccan'],
    [88,'الغاشية',26,'Meccan'],[89,'الفجر',30,'Meccan'],[90,'البلد',20,'Meccan'],
    [91,'الشمس',15,'Meccan'],[92,'الليل',21,'Meccan'],[93,'الضحى',11,'Meccan'],
    [94,'الشرح',8,'Meccan'],[95,'التين',8,'Meccan'],[96,'العلق',19,'Meccan'],
    [97,'القدر',5,'Meccan'],[98,'البينة',8,'Medinan'],[99,'الزلزلة',8,'Medinan'],
    [100,'العاديات',11,'Meccan'],[101,'القارعة',11,'Meccan'],[102,'التكاثر',8,'Meccan'],
    [103,'العصر',3,'Meccan'],[104,'الهمزة',9,'Meccan'],[105,'الفيل',5,'Meccan'],
    [106,'قريش',4,'Meccan'],[107,'الماعون',7,'Meccan'],[108,'الكوثر',3,'Meccan'],
    [109,'الكافرون',6,'Meccan'],[110,'النصر',3,'Medinan'],[111,'المسد',5,'Meccan'],
    [112,'الإخلاص',4,'Meccan'],[113,'الفلق',5,'Meccan'],[114,'الناس',6,'Meccan'],
];

try {
    if ($action === 'surah_list') {
        $result = array_map(fn($s) => [
            'number'        => $s[0],
            'name'          => $s[1],
            'numberOfAyahs' => $s[2],
            'revelationType'=> $s[3],
        ], $SURAHS);
        echo json_encode(['success'=>true,'data'=>$result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'surah') {
        $num = (int)($_GET['num'] ?? 0);
        if ($num < 1 || $num > 114) {
            echo json_encode(['success'=>false,'error'=>'رقم سورة غير صحيح']);
            exit;
        }

        // ✅ جلب نص السورة بـ curl — edition quran-uthmani يضمن النص الصحيح لكل سورة
        $url = "https://api.alquran.cloud/v1/surah/{$num}/quran-uthmani";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 RATTIL/1.0',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$raw || $code !== 200) {
            echo json_encode(['success'=>false,'error'=>"فشل curl: $err (HTTP $code)"]);
            exit;
        }

        $data = json_decode($raw, true);
        if (!$data || $data['code'] !== 200) {
            echo json_encode(['success'=>false,'error'=>'خطأ في بيانات API','raw'=>substr($raw,0,200)]);
            exit;
        }

        echo json_encode(['success'=>true,'data'=>$data['data']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success'=>false,'error'=>"action غير معروف: '$action'"]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
