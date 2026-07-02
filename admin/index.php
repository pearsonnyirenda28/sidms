<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$user = requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> — Admin Dashboard</title>
    <meta name="author" content="<?= APP_DEVELOPER ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';
    </script>
</head>
<body>
<nav class="navbar navbar-dark sidms-nav px-4">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-shield-lock-fill me-2 text-primary"></i><?= APP_NAME ?> <span class="d-none d-md-inline text-muted small ms-1">— <?= APP_FULL_NAME ?></span></a>
    <div class="navbar-nav ms-auto d-flex flex-row gap-3 align-items-center">
        <a class="nav-link text-light" href="<?= BASE_URL ?>/admin/users.php"><i class="bi bi-people me-1"></i>Users</a>
        <a class="nav-link text-light" href="<?= BASE_URL ?>/admin/logs.php"><i class="bi bi-journal-text me-1"></i>Logs</a>
        <a class="nav-link text-light" href="<?= BASE_URL ?>/admin/alerts.php"><i class="bi bi-bell me-1"></i>Alerts <span id="alert-badge" class="badge bg-danger ms-1 d-none"></span></a>
        <a class="nav-link text-light" href="<?= BASE_URL ?>/admin/sessions.php"><i class="bi bi-display me-1"></i>Sessions</a>
        <a class="nav-link text-light" href="<?= BASE_URL ?>/admin/knowledge.php"><i class="bi bi-database me-1"></i>Knowledge</a>
        <a class="nav-link text-light" href="<?= BASE_URL ?>/admin/push.php"><i class="bi bi-bell-fill me-1"></i>Push</a>
        <span class="text-light small opacity-75"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['username']) ?></span>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>
<div class="container-fluid px-4 py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2 text-primary"></i>Admin Dashboard <small class="text-muted fs-6 ms-2">Live · auto‑refreshes every 5s</small></h4>
    <div class="row g-3 mb-4" id="stat-cards">
        <div class="col-md-2 col-sm-4"><div class="stat-card card h-100"><div class="card-body text-center"><div class="stat-icon mx-auto bg-primary-subtle text-primary"><i class="bi bi-people-fill"></i></div><div class="stat-value mt-2" id="s-users">—</div><div class="stat-label">Active Users</div></div></div></div>
        <div class="col-md-2 col-sm-4"><div class="stat-card card h-100"><div class="card-body text-center"><div class="stat-icon mx-auto bg-info-subtle text-info"><i class="bi bi-files"></i></div><div class="stat-value mt-2" id="s-files">—</div><div class="stat-label">Total Files</div></div></div></div>
        <div class="col-md-2 col-sm-4"><div class="stat-card card h-100"><div class="card-body text-center"><div class="stat-icon mx-auto bg-success-subtle text-success"><i class="bi bi-hdd-fill"></i></div><div class="stat-value mt-2" id="s-storage">—</div><div class="stat-label">Storage</div></div></div></div>
        <div class="col-md-2 col-sm-4"><div class="stat-card card h-100"><div class="card-body text-center"><div class="stat-icon mx-auto bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle-fill"></i></div><div class="stat-value mt-2" id="s-alerts">—</div><div class="stat-label">New Alerts</div></div></div></div>
        <div class="col-md-2 col-sm-4"><div class="stat-card card h-100"><div class="card-body text-center"><div class="stat-icon mx-auto bg-warning-subtle text-warning"><i class="bi bi-shield-x"></i></div><div class="stat-value mt-2" id="s-failed">—</div><div class="stat-label">Failed Logins/1h</div></div></div></div>
        <div class="col-md-2 col-sm-4"><div class="stat-card card h-100"><div class="card-body text-center"><div class="stat-icon mx-auto bg-secondary-subtle text-secondary"><i class="bi bi-download"></i></div><div class="stat-value mt-2" id="s-downloads">—</div><div class="stat-label">Downloads/1h</div></div></div></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-lg-8"><div class="card sidms-card h-100"><div class="card-header fw-semibold"><i class="bi bi-activity me-2 text-primary"></i>Activity (Last 12 Hours)</div><div class="card-body"><canvas id="activity-chart" height="80"></canvas></div></div></div>
        <div class="col-lg-4"><div class="card sidms-card h-100"><div class="card-header fw-semibold"><i class="bi bi-pie-chart me-2 text-warning"></i>File Categories</div><div class="card-body d-flex align-items-center justify-content-center"><canvas id="cat-chart" height="200" width="200"></canvas></div></div></div>
    </div>
    <div class="card sidms-card"><div class="card-header fw-semibold"><i class="bi bi-clock-history me-2 text-info"></i>Recent Activity</div><div class="table-responsive"><table class="table table-hover mb-0 align-middle sidms-table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead><tbody id="recent-tbody"><tr><td colspan="5" class="text-center text-muted py-3">Loading…</td></tr></tbody></table></div></div>
</div>
<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1055;"></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>