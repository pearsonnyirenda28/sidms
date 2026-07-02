<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/ai_monitor.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['pre_uid'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$uid    = (int)$_SESSION['pre_uid'];
$secret = $_SESSION['pre_secret'];
$setup  = (bool)$_SESSION['pre_setup'];
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token.';
    } else {
        $code = preg_replace('/\D/', '', $_POST['otp_code'] ?? '');
        if (totpVerify($secret, $code)) {
            if (!$setup) {
                db()->prepare("UPDATE users SET otp_setup=1 WHERE id=?")->execute([$uid]);
            }
            if (!registerUserSession($uid, $_SESSION['pre_role'])) {
                session_destroy();
                header('Location: ' . BASE_URL . '/index.php?error=device_limit');
                exit;
            }
            session_regenerate_id(true);
            $_SESSION['uid']      = $uid;
            $_SESSION['username'] = $_SESSION['pre_username'];
            $_SESSION['role']     = $_SESSION['pre_role'];
            $_SESSION['otp_ok']   = true;
            unset($_SESSION['pre_uid'], $_SESSION['pre_username'], $_SESSION['pre_role'], $_SESSION['pre_secret'], $_SESSION['pre_setup']);
            auditLog('LOGIN_OK', 'OTP verified', $uid);
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid or expired OTP code.';
            auditLog('LOGIN_FAIL', 'Bad OTP', $uid);
            aiMonitor('LOGIN_FAIL', $uid);
        }
    }
}

$qrUrl = $setup ? '' : totpQRUrl($_SESSION['pre_username'], $secret);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP Verification — <?= APP_NAME ?></title>
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
            <div class="auth-logo text-warning"><i class="bi bi-phone"></i></div>
            <h3 class="fw-bold mt-2">Two-Factor Authentication</h3>
            <p class="text-muted small">Enter the 6-digit code from your authenticator app</p>
        </div>
        <?php if (!$setup && $qrUrl): ?>
        <div class="alert alert-info text-center py-3 mb-3">
            <strong>First login — scan this QR code</strong><br>
            <img src="<?= htmlspecialchars($qrUrl) ?>" class="mt-2 rounded" width="160" alt="OTP QR Code">
            <p class="mt-2 mb-0 small">Use Google Authenticator or Authy</p>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="mb-4">
                <label for="otp_code" class="form-label fw-semibold">6-Digit OTP Code</label>
                <input type="text" name="otp_code" id="otp_code" class="form-control text-center otp-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            </div>
            <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold text-dark"><i class="bi bi-shield-check me-2"></i>Verify OTP</button>
        </form>
        <div class="text-center mt-3"><a href="<?= BASE_URL ?>/index.php" class="text-decoration-none small text-muted">← Back to login</a></div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>