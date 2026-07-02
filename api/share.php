<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/share.php';
header('Content-Type: application/json');

$user = requireAuth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'msg'=>'Method not allowed']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Invalid security token']);
    exit;
}

$fileId = (int)($input['file_id'] ?? 0);
if (!$fileId) {
    echo json_encode(['ok'=>false,'msg'=>'File ID missing']);
    exit;
}

$where = $user['role'] === 'admin' ? 'id = ?' : 'id = ? AND user_id = ?';
$p     = $user['role'] === 'admin' ? [$fileId] : [$fileId, $user['id']];
$stmt  = db()->prepare("SELECT id FROM files WHERE {$where} LIMIT 1");
$stmt->execute($p);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'File not found or access denied']);
    exit;
}

$duration = max(300, min((int)($input['duration'] ?? 3600), 2592000));
$maxAccess = isset($input['max_access']) ? (int)$input['max_access'] : null;
if ($maxAccess !== null && $maxAccess <= 0) $maxAccess = null;

$token = createShareLink($fileId, $user['id'], $duration, $maxAccess);
if ($token) {
    echo json_encode(['ok'=>true,'token'=>$token]);
} else {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Could not create share link.']);
}