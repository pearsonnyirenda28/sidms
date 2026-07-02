<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$done  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['confirm'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = 'Username must be 3-30 alphanumeric characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $pwdCheck = validatePasswordStrength($password);
            if ($pwdCheck !== true) {
                $error = $pwdCheck;
            } else {
                try {
                    $secret = totpGenerateSecret();
                    $hash   = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = db()->prepare("INSERT INTO users (username, email, password_hash, otp_secret, status) VALUES (?,?,?,?,'pending')");
                    $stmt->execute([$username, $email, $hash, $secret]);
                    $done = true;
                } catch (PDOException $e) {
                    $error = ($e->getCode() == 23000) ? 'Username or email already taken.' : 'Registration failed.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register — <?= APP_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            <div class="auth-logo text-success"><i class="bi bi-person-plus"></i></div>
            <h3 class="fw-bold mt-2">Request Access</h3>
        </div>
        <?php if ($done): ?>
            <div class="alert alert-success">Registration submitted! Pending admin approval.</div>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline-primary w-100">← Back to Login</a>
        <?php else: ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3">
                    <label for="reg_username">Username</label>
                    <input type="text" name="username" id="reg_username" class="form-control" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" required>
                    <small class="text-muted">3-30 alphanumeric characters</small>
                </div>
                <div class="mb-3">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required>
                </div>
                <div class="mb-3">
                    <label for="new_password">Password</label>
                    <input type="password" name="password" id="new_password" class="form-control" autocomplete="new-password" required>
                    <small class="text-muted">Min 8 chars, upper, lower, digit, special.</small>
                </div>
                <div class="mb-3">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm" id="confirm_password" class="form-control" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-success w-100">Register</button>
            </form>
            <div class="text-center mt-3"><a href="<?= BASE_URL ?>/index.php">← Back to login</a></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>