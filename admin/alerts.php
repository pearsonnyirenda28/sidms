<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/audit.php';
$user=requireAdmin();
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!isset($_POST['csrf_token'])||$_POST['csrf_token']!==$_SESSION['csrf_token'])$error='Invalid token';
    else{
        $id=(int)($_POST['id']??0); $action=$_POST['action']??'';
        if($id && in_array($action,['reviewed','dismissed'])){
            db()->prepare("UPDATE alerts SET status=? WHERE id=?")->execute([$action,$id]); auditLog('ADMIN_ACTION',"Alert $id $action");
        }elseif($action==='clear_all'){
            db()->exec("UPDATE alerts SET status='dismissed' WHERE status='new'"); auditLog('ADMIN_ACTION','All new alerts dismissed');
        }
    }
    header('Location: '.BASE_URL.'/admin/alerts.php'); exit;
}
$alerts = db()->query("SELECT a.*,u.username FROM alerts a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 200")->fetchAll();
function typeBadge($t){return $t==='BRUTE_FORCE'?'bg-danger':($t==='DATA_EXFIL'?'bg-warning':'bg-secondary');}
function statusBadge($s){return $s==='new'?'bg-danger':($s==='reviewed'?'bg-success':'bg-secondary');}
?><!DOCTYPE html><html><head><title>Alerts — <?= APP_NAME ?></title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head>
<body>
<nav class="navbar navbar-dark sidms-nav px-4"><a class="navbar-brand" href="<?= BASE_URL ?>/admin/"><i class="bi bi-shield-lock"></i> Admin</a><div class="ms-auto"><a href="<?= BASE_URL ?>/admin/" class="btn btn-sm btn-outline-light">← Dashboard</a></div></nav>
<div class="container-fluid px-4 py-4"><div class="d-flex justify-content-between mb-3"><h4><i class="bi bi-bell-fill text-danger me-2"></i>Security Alerts</h4><form method="POST"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="clear_all"><button class="btn btn-sm btn-outline-warning">Dismiss All New</button></form></div>
<div class="card sidms-card"><table class="table sidms-table"><thead><tr><th>Time</th><th>Type</th><th>Message</th><th>User</th><th>IP</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($alerts as $a):?><tr class="<?=$a['status']==='new'?'table-danger':''?>"><td><?=date('d M H:i',strtotime($a['created_at']))?></td><td><span class="badge <?=typeBadge($a['type'])?>"><?=htmlspecialchars($a['type'])?></span></td><td><?=htmlspecialchars($a['message'])?></td><td><?=htmlspecialchars($a['username']??'—')?></td><td><?=htmlspecialchars($a['ip'])?></td><td><span class="badge <?=statusBadge($a['status'])?>"><?=$a['status']?></span></td><td><?php if($a['status']==='new'):?><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="id" value="<?=$a['id']?>"><input type="hidden" name="action" value="reviewed"><button class="btn btn-xs btn-outline-success">Review</button></form> <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="id" value="<?=$a['id']?>"><input type="hidden" name="action" value="dismissed"><button class="btn btn-xs btn-outline-secondary">Dismiss</button></form><?php else:?>—<?php endif;?></td></tr><?php endforeach; ?>
</tbody></table></div></div><div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>