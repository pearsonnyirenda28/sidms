<?php
/**
 * Get Document Text API – bulletproof JSON, never redirects
 */
ob_start();

error_reporting(0);
ini_set('display_errors', 0);

// Load config FIRST to set session name to 'SIDMS_SID'
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../includes/office_preview.php';
require_once __DIR__ . '/../includes/ai_document.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Manual auth check (no redirect)
if (empty($_SESSION['uid']) || empty($_SESSION['otp_ok'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}
$user = [
    'id'   => (int)$_SESSION['uid'],
    'role' => $_SESSION['role']
];

$fileId = (int)($_GET['file_id'] ?? 0);
if (!$fileId) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'text' => 'File ID missing']);
    exit;
}

try {
    // Access control
    $where = $user['role'] === 'admin' ? 'id=?' : 'id=? AND user_id=?';
    $params = $user['role'] === 'admin' ? [$fileId] : [$fileId, $user['id']];
    $stmt = db()->prepare("SELECT * FROM files WHERE {$where} LIMIT 1");
    $stmt->execute($params);
    $file = $stmt->fetch();

    if (!$file) {
        ob_end_clean();
        echo json_encode(['ok' => false, 'text' => 'File not found']);
        exit;
    }

    // Check cache first
    $stmt = db()->prepare("SELECT full_text FROM ai_cache WHERE file_id=? AND cache_type='insights'");
    $stmt->execute([$fileId]);
    $text = $stmt->fetchColumn();

    if (!$text || strlen($text) < 20) {
        $enc = file_get_contents(STORAGE_DIR . '/' . $file['stored_name']);
        if ($enc === false) throw new RuntimeException('Storage error');
        $plain = decryptFile($enc, $file['file_iv'], $file['enc_key'], $file['key_iv']);
        if ($plain === false) throw new RuntimeException('Decryption failed');

        $tmpFile = tempnam(sys_get_temp_dir(), 'sidms_');
        file_put_contents($tmpFile, $plain);
        $text = extractTextFromFile($tmpFile, $file['mime_type']);
        unlink($tmpFile);

        if (!empty($text)) {
            $text = preg_replace('/\s+/', ' ', $text);
            db()->prepare('INSERT INTO ai_cache (file_id, cache_type, full_text, updated_at) VALUES (?, "insights", ?, NOW()) ON DUPLICATE KEY UPDATE full_text = VALUES(full_text), updated_at = NOW()')
                ->execute([$fileId, $text]);
        }
    }

    ob_end_clean();
    echo json_encode(['ok' => true, 'text' => $text ?: 'No extractable text.']);
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'text' => 'Error: ' . $e->getMessage()]);
}