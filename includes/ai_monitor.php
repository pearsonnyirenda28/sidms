<?php
/**
 * AI‑powered anomaly detection and alerting.
 * Notifications via SMS, email, and web push.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/web_push_helper.php';

function aiMonitor(string $action, ?int $userId = null, string $ip = ''): void {
    $ip  = $ip ?: ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $uid = $userId ?? ($_SESSION['uid'] ?? null);
    $win = date('Y-m-d H:i:s', time() - ALERT_WINDOW);

    if ($action === 'LOGIN_FAIL') {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE (ip_address=? OR user_id=?) AND action='LOGIN_FAIL' AND created_at>=?"
        );
        $stmt->execute([$ip, $uid, $win]);
        if ($stmt->fetchColumn() >= FAIL_LOGIN_MAX) {
            createAlert('BRUTE_FORCE', "Multiple failed logins from IP {$ip} (user_id={$uid})", $uid, $ip);
        }
    }

    if ($action === 'DOWNLOAD' && $uid) {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM audit_logs
             WHERE user_id=? AND action='DOWNLOAD' AND created_at>=?"
        );
        $stmt->execute([$uid, $win]);
        if ($stmt->fetchColumn() >= DOWNLOAD_MAX) {
            createAlert('DATA_EXFIL', "Excessive downloads by user_id={$uid} (IP {$ip})", $uid, $ip);
        }
    }
}

function createAlert(string $type, string $msg, ?int $uid, string $ip): void {
    $win = date('Y-m-d H:i:s', time() - ALERT_WINDOW);
    $stmt = db()->prepare(
        "SELECT id FROM alerts WHERE type=? AND (user_id=? OR ip=?) AND created_at>=? LIMIT 1"
    );
    $stmt->execute([$type, $uid, $ip, $win]);
    if ($stmt->fetch()) return;

    $ins = db()->prepare(
        'INSERT INTO alerts (type,message,user_id,ip,status,created_at)
         VALUES (?,?,?,?,"new",NOW())'
    );
    $ins->execute([$type, $msg, $uid, $ip]);

    // SMS alert (if enabled)
    sendSmsAlert("SIDMS ALERT [{$type}]: {$msg}");

    // Email alert (if enabled)
    if (EMAIL_ENABLED) {
        $to = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@localhost';
        sendEmail($to, "SIDMS ALERT [{$type}]", $msg);
    }

    // Web push to all subscribers (if enabled)
    if (PUSH_ENABLED) {
        $stmt = db()->query("SELECT endpoint, p256dh, auth FROM push_subscriptions");
        $subs = $stmt->fetchAll();
        foreach ($subs as $sub) {
            sendWebPush(
                $sub['endpoint'],
                $sub['p256dh'],
                $sub['auth'],
                json_encode([
                    'title' => "SIDMS ALERT [{$type}]",
                    'body'  => $msg,
                    'icon'  => BASE_URL . '/assets/icons/icon-192.png',
                    'url'   => BASE_URL . '/admin/alerts.php'
                ]),
                VAPID_PUBLIC_KEY,
                VAPID_PRIVATE_KEY
            );
        }
    }
}

function aiClassify(string $filename, string $mime): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'pdf'  => 'PDF Document',
        'doc'  => 'Word Document',  'docx' => 'Word Document',
        'xls'  => 'Spreadsheet',    'xlsx' => 'Spreadsheet',
        'ppt'  => 'Presentation',   'pptx' => 'Presentation',
        'jpg'  => 'Image', 'jpeg' => 'Image', 'png' => 'Image', 'gif' => 'Image',
        'zip'  => 'Archive', 'rar' => 'Archive', '7z' => 'Archive',
        'txt'  => 'Text File', 'csv' => 'CSV Data',
        'mp4'  => 'Video', 'mp3' => 'Audio',
    ];
    return $map[$ext] ?? ucfirst(explode('/', $mime)[0] ?? 'Unknown');
}