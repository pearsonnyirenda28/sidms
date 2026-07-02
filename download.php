<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/encryption.php';
require_once __DIR__ . '/includes/audit.php';
$user = requireAuth();
if ($user['role'] !== 'admin') { http_response_code(403); exit('Admin only'); }
$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Bad request'); }

$stmt = db()->prepare("SELECT * FROM files WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file) { http_response_code(404); exit('File not found'); }

$enc = file_get_contents(STORAGE_DIR . $file['stored_name']);
if ($enc === false) { http_response_code(500); exit('Storage error'); }
$plain = decryptFile($enc, $file['file_iv'], $file['enc_key'], $file['key_iv']);
if ($plain === false) { http_response_code(500); exit('Decryption failed'); }

auditLog('DOWNLOAD', "Downloaded: " . $file['original_name']);
header('Content-Type: ' . $file['mime_type']);
header('Content-Disposition: attachment; filename="' . rawurlencode($file['original_name']) . '"');
header('Content-Length: ' . strlen($plain));
echo $plain;