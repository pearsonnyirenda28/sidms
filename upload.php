<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/encryption.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/ai_monitor.php';
header('Content-Type: application/json');

$user = requireAuth();
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['ok'=>false,'msg'=>'Invalid token']); exit;
}
if (!isset($_FILES['file'])) { echo json_encode(['ok'=>false,'msg'=>'No file']); exit; }

$f = $_FILES['file'];
$maxB = MAX_FILE_MB * 1024 * 1024;
if ($f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['ok'=>false,'msg'=>'Upload error']); exit; }
if ($f['size'] > $maxB) { echo json_encode(['ok'=>false,'msg'=>'File too large']); exit; }

$orig = basename($f['name']);
$mime = mime_content_type($f['tmp_name']) ?: 'application/octet-stream';

// Duplicate check
$stmtCheck = db()->prepare("SELECT id FROM files WHERE user_id = ? AND original_name = ?");
$stmtCheck->execute([$user['id'], $orig]);
if ($stmtCheck->fetch()) {
    echo json_encode(['ok'=>false,'msg'=>'A file with this name already exists.']);
    exit;
}

$allowed = ['image/','video/','text/','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.','application/vnd.ms-'];
$ok = false;
foreach ($allowed as $a) if (str_starts_with($mime, $a)) { $ok = true; break; }
if (!$ok) { echo json_encode(['ok'=>false,'msg'=>'File type not allowed']); exit; }

$plain = file_get_contents($f['tmp_name']);
$enc = encryptFile($plain);
$stored = bin2hex(random_bytes(16)).'.enc';
if (!is_dir(STORAGE_DIR)) mkdir(STORAGE_DIR, 0750, true);
file_put_contents(STORAGE_DIR.$stored, $enc['cipher']);

$cat = aiClassify($orig, $mime);
$stmt = db()->prepare('INSERT INTO files (user_id,original_name,stored_name,mime_type,file_size,enc_key,key_iv,file_iv,ai_category) VALUES (?,?,?,?,?,?,?,?,?)');
$stmt->execute([$user['id'], $orig, $stored, $mime, strlen($plain), $enc['enc_key'], $enc['key_iv'], $enc['iv'], $cat]);
auditLog('UPLOAD', "Uploaded: {$orig}");
echo json_encode(['ok'=>true,'msg'=>'Uploaded','category'=>$cat]);