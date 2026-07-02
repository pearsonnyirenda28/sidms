<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/web_push_helper.php';
header('Content-Type: application/json');

$user = requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$title   = $input['title'] ?? 'SIDMS';
$message = $input['message'] ?? 'You have a new notification.';
$url     = $input['url'] ?? BASE_URL . '/dashboard.php';

$stmt = db()->query('SELECT endpoint, p256dh, auth FROM push_subscriptions');
$subscriptions = $stmt->fetchAll();

if (empty($subscriptions)) {
    echo json_encode(['ok' => false, 'error' => 'No subscribers']);
    exit;
}

$payload = json_encode(['title' => $title, 'body' => $message, 'icon' => BASE_URL . '/assets/icons/icon-192.png', 'url' => $url]);

$success = 0;
foreach ($subscriptions as $sub) {
    $result = sendWebPush($sub['endpoint'], $sub['p256dh'], $sub['auth'], $payload, VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY);
    if ($result) $success++;
}

echo json_encode(['ok' => true, 'sent' => $success, 'total' => count($subscriptions)]);