<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

function requireAuth(): array {
    if (empty($_SESSION['uid']) || empty($_SESSION['otp_ok'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    return ['id' => (int)$_SESSION['uid'], 'role' => $_SESSION['role'], 'username' => $_SESSION['username']];
}

function requireAdmin(): array {
    $u = requireAuth();
    if ($u['role'] !== 'admin') { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
    return $u;
}

function getUser(int $id): array|false {
    $stmt = db()->prepare('SELECT * FROM users WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function clientIP(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function validatePasswordStrength(string $pwd): true|string {
    if (strlen($pwd) < 8) return "Password must be at least 8 characters.";
    if (!preg_match('/[A-Z]/', $pwd)) return "Include at least one uppercase letter.";
    if (!preg_match('/[a-z]/', $pwd)) return "Include at least one lowercase letter.";
    if (!preg_match('/[0-9]/', $pwd)) return "Include at least one digit.";
    if (!preg_match('/[^A-Za-z0-9]/', $pwd)) return "Include at least one special character.";
    return true;
}

function registerUserSession(int $userId, string $role): bool {
    $sessionId = session_id();
    $ip = clientIP();
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $limit = ($role === 'admin') ? 2 : 1;
    $stmt = db()->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id=? AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $stmt->execute([$userId]);
    $active = (int)$stmt->fetchColumn();
    if ($active >= $limit) {
        $stmt = db()->prepare("DELETE FROM user_sessions WHERE user_id=? AND last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE) ORDER BY last_activity ASC LIMIT ?");
        $stmt->execute([$userId, $active - $limit + 1]);
    }
    $stmt = db()->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_activity) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE last_activity = NOW()");
    return $stmt->execute([$userId, $sessionId, $ip, $ua]);
}

function updateSessionActivity(): void {
    if (empty($_SESSION['uid'])) return;
    db()->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE user_id=? AND session_id=?")->execute([$_SESSION['uid'], session_id()]);
}

function unregisterUserSession(): void {
    if (empty($_SESSION['uid'])) return;
    db()->prepare("DELETE FROM user_sessions WHERE user_id=? AND session_id=?")->execute([$_SESSION['uid'], session_id()]);
}

function cleanupExpiredSessions(): void {
    db()->exec("DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
}

if (!empty($_SESSION['uid'])) updateSessionActivity();