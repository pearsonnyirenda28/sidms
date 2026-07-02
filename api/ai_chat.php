<?php
/**
 * AI Chat API – session order fixed, always JSON, never redirect
 */

// ---------------------------------------------------------------------------
// 1. Load configuration FIRST – it sets session_name('SIDMS_SID')
// ---------------------------------------------------------------------------
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// CRITICAL: config.php must be loaded before any session_start()
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_document.php';
require_once __DIR__ . '/../includes/encryption.php';

// ---------------------------------------------------------------------------
// 2. Error handling
// ---------------------------------------------------------------------------
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode([
            'ok' => false,
            'error' => 'Fatal error in AI module',
            'debug' => [
                'message' => $error['message'],
                'file'    => basename($error['file']),
                'line'    => $error['line']
            ]
        ]);
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ---------------------------------------------------------------------------
// 3. Session recovery (if normal start didn't work)
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['uid'])) {
    $cookieName = session_name();           // now it's 'SIDMS_SID'
    if (isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] !== session_id()) {
        session_write_close();
        session_id($_COOKIE[$cookieName]);
        session_start();
    }
}

// ---------------------------------------------------------------------------
// 4. Authentication check
// ---------------------------------------------------------------------------
if (empty($_SESSION['uid']) || empty($_SESSION['otp_ok'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode([
        'ok'    => false,
        'error' => 'Authentication required',
        'debug' => [
            'session_id'    => session_id(),
            'cookie_name'   => session_name(),
            'cookie_exists' => isset($_COOKIE[session_name()]),
            'cookie_value'  => $_COOKIE[session_name()] ?? 'none',
            'uid'           => $_SESSION['uid'] ?? 'not set',
            'otp_ok'        => $_SESSION['otp_ok'] ?? 'not set',
            'username'      => $_SESSION['username'] ?? 'not set',
            'session_status'=> session_status()
        ]
    ]);
    exit;
}

$user = [
    'id'       => (int)$_SESSION['uid'],
    'role'     => $_SESSION['role'],
    'username' => $_SESSION['username']
];

// ---------------------------------------------------------------------------
// 5. Fallback if AI function is missing
// ---------------------------------------------------------------------------
if (!function_exists('conversationalAI')) {
    function conversationalAI($question, $history = [], $context = '') {
        return "AI engine is temporarily unavailable. Please try again later.";
    }
}

// ---------------------------------------------------------------------------
// 6. Process the request
// ---------------------------------------------------------------------------
try {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input) {
        throw new RuntimeException('Invalid JSON');
    }

    $question = trim($input['question'] ?? '');
    $fileId   = (int)($input['file_id'] ?? 0);
    $history  = is_array($input['history'] ?? null) ? $input['history'] : [];

    if ($question === '') {
        throw new RuntimeException('Question required');
    }

    // -----  Document context -------------------------------------------
    $documentContext = '';
    if ($fileId > 0) {
        $where  = $user['role'] === 'admin' ? 'id = ?' : 'id = ? AND user_id = ?';
        $params = $user['role'] === 'admin' ? [$fileId] : [$fileId, $user['id']];
        $stmt   = db()->prepare("SELECT * FROM files WHERE {$where} LIMIT 1");
        $stmt->execute($params);
        $file = $stmt->fetch();

        if ($file) {
            $stmt = db()->prepare("SELECT full_text FROM ai_cache WHERE file_id = ? AND cache_type = 'insights'");
            $stmt->execute([$fileId]);
            $text = $stmt->fetchColumn();

            if (!$text || strlen($text) < 20) {
                $enc = @file_get_contents(STORAGE_DIR . '/' . $file['stored_name']);
                if ($enc !== false) {
                    $plain = decryptFile($enc, $file['file_iv'], $file['enc_key'], $file['key_iv']);
                    if ($plain !== false) {
                        $tmpFile = tempnam(sys_get_temp_dir(), 'sidms_');
                        file_put_contents($tmpFile, $plain);
                        $text = extractTextFromFile($tmpFile, $file['mime_type']);
                        unlink($tmpFile);
                        if (!empty($text)) {
                            $text = preg_replace('/\s+/', ' ', $text);
                            @db()->prepare('INSERT INTO ai_cache (file_id, cache_type, full_text, updated_at) VALUES (?, "insights", ?, NOW()) ON DUPLICATE KEY UPDATE full_text = VALUES(full_text), updated_at = NOW()')
                                 ->execute([$fileId, $text]);
                        }
                    }
                }
            }
            if (!empty($text) && strlen($text) > 20) {
                $documentContext = "Document content:\n\"\"\"\n" . mb_substr($text, 0, 4000) . "\n\"\"\"\n\n";
            }
        }
    }

    // -----  Call the AI -------------------------------------------------
    $answer = conversationalAI($question, $history, $documentContext);
    if (!$answer) {
        $answer = "AI could not generate a response. Please try again.";
    }

    ob_end_clean();
    echo json_encode(['ok' => true, 'answer' => $answer]);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'ok'     => true,
        'answer' => '⚠️ Error: ' . $e->getMessage() . ' (in ' . basename($e->getFile()) . ' line ' . $e->getLine() . ')'
    ]);
}

restore_error_handler();