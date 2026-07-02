<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/audit.php';
$user=requireAdmin();
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['revoke'])){
    if(isset($_POST['csrf_token']) && $_POST['csrf_token']===$_SESSION['csrf_token']){
        $sid=$_POST['session_id']??''; db()->prepare("DELETE FROM user_sessions WHERE session_id=?")->execute([$sid]);
        auditLog('ADMIN_ACTION',"Revoked session: $sid");
    }
    header('Location: '.BASE_URL.'/admin/sessions.php'); exit;
}
$sessions=db()->query("SELECT s.*,u.username,u.role FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.last_activity > DATE_SUB(NOW(),INTERVAL 30 MINUTE) ORDER BY s.last_activity DESC")->fetchAll();
?><!DOCTYPE html><html><head><title>Sessions — <?= APP_NAME ?></title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head>
<body>
<nav class="navbar navbar-dark sidms-nav px-4"><a class="navbar-brand" href="<?= BASE_URL ?>/admin/"><i class="bi bi-shield-lock"></i> Admin</a><div class="ms-auto"><a href="<?= BASE_URL ?>/admin/" class="btn btn-sm btn-outline-light">← Dashboard</a></div></nav>
<div class="container-fluid px-4 py-4"><h4><i class="bi bi-people-fill me-2"></i>Active Sessions</h4>
<div class="card sidms-card"><table class="table sidms-table"><thead><tr><th>User</th><th>Role</th><th>IP Address</th><th>Last Activity</th><th>Device</th><th>Action</th></tr></thead><tbody>
<?php foreach($sessions as $s):?><tr><td><?=htmlspecialchars($s['username'])?></td><td><span class="badge bg-<?=$s['role']==='admin'?'danger':'secondary'?>"><?=$s['role']?></span></td><td><?=htmlspecialchars($s['ip_address'])?></td><td><?=date('Y-m-d H:i',strtotime($s['last_activity']))?></td><td class="small text-muted"><?=htmlspecialchars(substr($s['user_agent'],0,50))?>…</td><td><form method="POST" onsubmit="return SIDMS.toast.confirm('Terminate this session?').then(ok=>ok);"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="session_id" value="<?=$s['session_id']?>"><button type="submit" name="revoke" class="btn btn-sm btn-outline-danger">Revoke</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div><div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><script src="<?= BASE_URL ?>/assets/js/notifications.js"></script></body></html>