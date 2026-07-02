<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';
header('Content-Type: application/json');

$user = requireAuth();
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Admin only']);
    exit;
}
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Invalid token']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid file ID']);
    exit;
}

$stmt = db()->prepare("SELECT * FROM files WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file) {
    echo json_encode(['ok' => false, 'msg' => 'File not found']);
    exit;
}

$path = STORAGE_DIR . $file['stored_name'];
if (file_exists($path)) unlink($path);
db()->prepare("DELETE FROM files WHERE id = ?")->execute([$id]);
auditLog('DELETE', 'Deleted: ' . $file['original_name']);
echo json_encode(['ok' => true, 'msg' => 'File deleted']);