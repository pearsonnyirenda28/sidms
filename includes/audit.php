<?php
require_once __DIR__ . '/db.php';
function auditLog(string $action, string $detail = '', ?int $userId = null): void {
    $uid = $userId ?? ($_SESSION['uid'] ?? null);
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, detail, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$uid, $action, $detail, $ip, $ua]);
}