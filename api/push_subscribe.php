<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$user = requireAuth();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['endpoint'])) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = db()->prepare('INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth)');
$stmt->execute([
    $user['id'],
    $input['endpoint'],
    $input['keys']['p256dh'] ?? '',
    $input['keys']['auth'] ?? ''
]);

echo json_encode(['ok' => true]);