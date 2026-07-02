<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth.php';
header('Content-Type: application/json'); requireAdmin();
$db=db();
$stats=[
    'users'=>(int)$db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
    'files'=>(int)$db->query("SELECT COUNT(*) FROM files")->fetchColumn(),
    'storage'=>formatSize((int)$db->query("SELECT COALESCE(SUM(file_size),0) FROM files")->fetchColumn()),
    'alerts'=>(int)$db->query("SELECT COUNT(*) FROM alerts WHERE status='new'")->fetchColumn(),
    'failed'=>(int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='LOGIN_FAIL' AND created_at >= NOW() - INTERVAL 1 HOUR")->fetchColumn(),
    'downloads'=>(int)$db->query("SELECT COUNT(*) FROM audit_logs WHERE action='DOWNLOAD' AND created_at >= NOW() - INTERVAL 1 HOUR")->fetchColumn(),
];
$hourly=$db->query("SELECT DATE_FORMAT(created_at,'%H:00') hr, COUNT(*) n FROM audit_logs WHERE created_at >= NOW() - INTERVAL 12 HOUR GROUP BY hr ORDER BY hr")->fetchAll();
$recent=$db->query("SELECT l.*,u.username FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 15")->fetchAll();
$cats=$db->query("SELECT ai_category,COUNT(*) n FROM files GROUP BY ai_category ORDER BY n DESC LIMIT 8")->fetchAll();
function formatSize($b){if($b<1024)return $b.' B';if($b<1048576)return round($b/1024,1).' KB';if($b<1073741824)return round($b/1048576,1).' MB';return round($b/1073741824,2).' GB';}
echo json_encode(['stats'=>$stats,'hourly'=>$hourly,'recent'=>$recent,'cats'=>$cats]);