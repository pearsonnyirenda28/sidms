<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid security token.';
    } else {
        $adminUser  = trim($_POST['admin_user'] ?? 'admin');
        $adminPass  = trim($_POST['admin_pass'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $confirmOverride = isset($_POST['confirm_override']) && $_POST['confirm_override'] === 'yes';

        if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $adminUser)) $errors[] = 'Username must be 3-30 alphanumeric.';
        if (strlen($adminPass) < 8) $errors[] = 'Password must be at least 8 characters.';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';

        if (empty($errors)) {
            $pwdCheck = validatePasswordStrength($adminPass);
            if ($pwdCheck !== true) $errors[] = $pwdCheck;
        }

        if (empty($errors)) {
            try {
                $pdo = new PDO('mysql:host='.DB_HOST.';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `".DB_NAME."`");

                $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(60) NOT NULL UNIQUE, email VARCHAR(120) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, otp_secret VARCHAR(32) NOT NULL, otp_setup TINYINT(1) DEFAULT 0, role ENUM('admin','user') DEFAULT 'user', status ENUM('active','pending','suspended') DEFAULT 'pending', created_at DATETIME DEFAULT NOW()) ENGINE=InnoDB");
                $pdo->exec("CREATE TABLE IF NOT EXISTS files (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(64) NOT NULL, mime_type VARCHAR(100) DEFAULT '', file_size BIGINT DEFAULT 0, enc_key TEXT NOT NULL, key_iv VARCHAR(64) NOT NULL, file_iv VARCHAR(64) NOT NULL, ai_category VARCHAR(60) DEFAULT 'Unknown', uploaded_at DATETIME DEFAULT NOW(), INDEX(user_id)) ENGINE=InnoDB");
                $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED, action VARCHAR(60) NOT NULL, detail TEXT, ip_address VARCHAR(45), user_agent VARCHAR(255), created_at DATETIME DEFAULT NOW(), INDEX(user_id), INDEX(action), INDEX(created_at)) ENGINE=InnoDB");
                $pdo->exec("CREATE TABLE IF NOT EXISTS alerts (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, type VARCHAR(40) NOT NULL, message TEXT NOT NULL, user_id INT UNSIGNED, ip VARCHAR(45), status ENUM('new','reviewed','dismissed') DEFAULT 'new', created_at DATETIME DEFAULT NOW(), INDEX(status)) ENGINE=InnoDB");
                $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, session_id VARCHAR(128) NOT NULL, ip_address VARCHAR(45), user_agent VARCHAR(255), last_activity DATETIME DEFAULT NOW(), created_at DATETIME DEFAULT NOW(), INDEX(user_id), INDEX(session_id), INDEX(last_activity)) ENGINE=InnoDB");
                $pdo->exec("CREATE TABLE IF NOT EXISTS ai_cache (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_id INT UNSIGNED NOT NULL, cache_type ENUM('insights','qa','full_text','general') DEFAULT 'insights', question_hash VARCHAR(64) NULL, question TEXT NULL, answer TEXT NULL, insights JSON NULL, full_text LONGTEXT NULL, embedding JSON NULL, created_at DATETIME DEFAULT NOW(), updated_at DATETIME DEFAULT NOW(), INDEX(file_id), INDEX(cache_type), UNIQUE KEY unique_qa (file_id, question_hash)) ENGINE=InnoDB");
                $pdo->exec("CREATE TABLE IF NOT EXISTS shared_links (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_id INT UNSIGNED NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, created_by INT UNSIGNED NOT NULL, max_access INT UNSIGNED NULL DEFAULT NULL, access_count INT UNSIGNED NOT NULL DEFAULT 0, duration INT UNSIGNED NOT NULL DEFAULT 3600, created_at DATETIME DEFAULT NOW(), expires_at DATETIME NOT NULL, INDEX(file_id), INDEX(token), INDEX(expires_at)) ENGINE=InnoDB");
                $pdo->exec("CREATE TABLE IF NOT EXISTS ai_knowledge (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, term VARCHAR(255) NOT NULL, definition TEXT NOT NULL, aliases TEXT NULL, category VARCHAR(100) NULL, created_at DATETIME DEFAULT NOW(), updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(), FULLTEXT(term, definition, aliases)) ENGINE=InnoDB");

                $secret = totpGenerateSecret();
                $hash   = password_hash($adminPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username,email,password_hash,otp_secret,otp_setup,role,status) VALUES (?,?,?,?,0,'admin','active') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)");
                $stmt->execute([$adminUser, $adminEmail, $hash, $secret]);

                if (!is_dir(STORAGE_DIR)) mkdir(STORAGE_DIR, 0750, true);
                file_put_contents(STORAGE_DIR . '.htaccess', "Deny from all\n");

                $done = true;
                $qr   = totpQRUrl($adminUser, $secret);
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIDMS Setup</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background:#0d1117; color:#e6edf3; }
        .setup-card { background:#161b22; border:1px solid #30363d; border-radius:12px; padding:2rem; }
        .form-control { background:#21262d; border:1px solid #30363d; color:#e6edf3; }
        .btn-primary { background:#2f81f7; border-color:#2f81f7; }
    </style>
</head>
<body>
<div class="container" style="max-width:600px;margin-top:60px">
    <h2 class="fw-bold text-primary"><i class="bi bi-shield-lock-fill me-2"></i>SIDMS Setup</h2>
    <?php if ($done): ?>
        <div class="setup-card text-center">
            <i class="bi bi-check-circle-fill text-success fs-1"></i>
            <h5 class="text-success mt-2">Installation Complete!</h5>
            <p>Scan this QR code with Google Authenticator:</p>
            <img src="<?= htmlspecialchars($qr) ?>" class="border border-success rounded my-2" width="200">
            <div class="alert alert-warning mt-3">⚠️ Delete the <code>/install/</code> folder now.</div>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary w-100">Go to Login</a>
        </div>
    <?php else: ?>
        <div class="setup-card">
            <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="mb-3">
                    <label class="form-label">Admin Username</label>
                    <input type="text" name="admin_user" class="form-control" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Email</label>
                    <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Password</label>
                    <input type="password" name="admin_pass" class="form-control" placeholder="Min 8 chars, upper, lower, digit, special" required>
                </div>
                <?php if ($alreadyInstalled ?? false): ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="confirm_override" value="yes" id="confirmOverride">
                    <label class="form-check-label" for="confirmOverride">I understand this resets the admin password.</label>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary w-100">Install SIDMS</button>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>