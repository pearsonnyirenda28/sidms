<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/ai_monitor.php';
require_once __DIR__ . '/includes/auth.php';

cleanupExpiredSessions();

if (!empty($_SESSION['uid']) && !empty($_SESSION['otp_ok'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $stmt = db()->prepare("SELECT * FROM users WHERE username=? AND status='active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['pre_uid']      = $user['id'];
            $_SESSION['pre_username'] = $user['username'];
            $_SESSION['pre_role']     = $user['role'];
            $_SESSION['pre_secret']   = $user['otp_secret'];
            $_SESSION['pre_setup']    = $user['otp_setup'];
            auditLog('LOGIN_STEP1', "Password OK for {$username}", $user['id']);
            header('Location: ' . BASE_URL . '/otp.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            auditLog('LOGIN_FAIL', "Failed login for {$username}");
            aiMonitor('LOGIN_FAIL', null, clientIP());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — <?= APP_NAME ?></title>
    <meta name="author" content="<?= APP_DEVELOPER ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-bg">
<div class="auth-wrap">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="auth-logo"><i class="bi bi-shield-lock-fill"></i></div>
            <h3 class="fw-bold mt-2"><?= APP_NAME ?></h3>
            <p class="text-muted small"><?= APP_FULL_NAME ?></p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" id="username" class="form-control" autocomplete="username" required autofocus>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="password" class="form-control" autocomplete="current-password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-box-arrow-in-right me-2"></i>Continue to OTP</button>
        </form>
        <hr class="my-4">
        <div class="text-center">
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline-success w-100 py-2"><i class="bi bi-person-plus me-2"></i>Request Access</a>
        </div>
        <div class="text-center mt-4">
            <p class="text-muted small mb-2">Scan to open on mobile</p>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/index.php') ?>" width="150" height="150" alt="QR Code">
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>