<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$user = requireAdmin();
$page = max(1,(int)($_GET['page']??1)); $limit=50; $offset=($page-1)*$limit;
$action = $_GET['action']??''; $where=$action?"WHERE l.action=?":''; $params=$action?[$action]:[];
$total = db()->prepare("SELECT COUNT(*) FROM audit_logs l $where"); $total->execute($params); $total=$total->fetchColumn();
$pages = max(1,ceil($total/$limit));
$stmt = db()->prepare("SELECT l.*,u.username FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id $where ORDER BY l.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute(array_merge($params,[$limit,$offset])); $logs=$stmt->fetchAll();
$actions = db()->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
function badge($a){return match($a){'LOGIN_OK'=>'bg-success','LOGIN_FAIL'=>'bg-danger','UPLOAD'=>'bg-primary','DOWNLOAD'=>'bg-info','DELETE'=>'bg-warning','LOGOUT'=>'bg-secondary',default=>'bg-dark'};}
?><!DOCTYPE html><html><head><title>Audit Logs — <?= APP_NAME ?></title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"></head>
<body>
<nav class="navbar navbar-dark sidms-nav px-4"><a class="navbar-brand" href="<?= BASE_URL ?>/admin/"><i class="bi bi-shield-lock"></i> Admin</a><div class="ms-auto"><a href="<?= BASE_URL ?>/admin/" class="btn btn-sm btn-outline-light">← Dashboard</a></div></nav>
<div class="container-fluid px-4 py-4"><h4><i class="bi bi-journal-text me-2"></i>Audit Logs <span class="badge bg-secondary"><?= number_format($total) ?></span></h4>
<div class="d-flex justify-content-end mb-2"><form class="d-flex"><select name="action" class="form-select form-select-sm"><option value="">All</option><?php foreach($actions as $a):?><option value="<?=htmlspecialchars($a)?>" <?=$a===$action?'selected':''?>><?=htmlspecialchars($a)?></option><?php endforeach;?></select><button class="btn btn-sm btn-outline-primary ms-2">Filter</button></form></div>
<div class="card sidms-card"><table class="table sidms-table small"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead><tbody>
<?php foreach($logs as $l):?><tr><td><?=date('d M H:i:s',strtotime($l['created_at']))?></td><td><?=htmlspecialchars($l['username']??'—')?></td><td><span class="badge <?=badge($l['action'])?>"><?=htmlspecialchars($l['action'])?></span></td><td><?=htmlspecialchars($l['detail'])?></td><td><?=htmlspecialchars($l['ip_address'])?></td></tr><?php endforeach;?>
</tbody></table></div>
<?php if($pages>1):?><nav><ul class="pagination pagination-sm"><?php for($i=1;$i<=$pages;$i++):?><li class="page-item <?=$i===$page?'active':''?>"><a class="page-link" href="?page=<?=$i?>&action=<?=urlencode($action)?>"><?=$i?></a></li><?php endfor;?></ul></nav><?php endif;?>
</div><div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>