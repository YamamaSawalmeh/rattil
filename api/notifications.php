<?php
require_once __DIR__ . '/../config/db.php';
requireAjaxAuth();
$uid = (int)$_SESSION['user_id'];

$body   = $_SERVER['REQUEST_METHOD']==='POST' ? (json_decode(file_get_contents('php://input'),true) ?: $_POST) : [];
$action = $_GET['action'] ?? $body['action'] ?? '';

switch ($action) {
    case 'count':
        $s=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $s->execute([$uid]);
        jsonResponse(true,['count'=>(int)$s->fetchColumn()]);

    case 'list':
        $s=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
        $s->execute([$uid]);
        jsonResponse(true,$s->fetchAll());

    case 'mark_read':
        $id=(int)($body['id']??0);
        if(!$id) jsonResponse(false,'id مطلوب',400);
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id,$uid]);
        jsonResponse(true,['updated'=>true]);

    case 'mark_all_read':
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
        jsonResponse(true,['updated'=>true]);

    default:
        jsonResponse(false,'action غير معروف',400);
}
