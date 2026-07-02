<?php
/**
 * AI Summarize API – bulletproof JSON output
 * Works with sidebar AI panel (per‑document insights & Q&A)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ai_document.php';

// Catch any fatal error and still return JSON
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(['ok' => false, 'error' => 'Internal server error']);
        error_log('AI Summarize fatal error: ' . $error['message']);
    }
});

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------
try {
    $user = requireAuth();
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

// ---------------------------------------------------------------------------
// Input validation
// ---------------------------------------------------------------------------
$fileId = (int)($_GET['file_id'] ?? 0);
$action = $_GET['action'] ?? 'insights';

if ($fileId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File ID required']);
    exit;
}

// ---------------------------------------------------------------------------
// Access control (owner or admin)
// ---------------------------------------------------------------------------
$where = $user['role'] === 'admin' ? 'id = ?' : 'id = ? AND user_id = ?';
$params = $user['role'] === 'admin' ? [$fileId] : [$fileId, $user['id']];
$stmt = db()->prepare("SELECT id FROM files WHERE {$where} LIMIT 1");
$stmt->execute($params);

if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

// ---------------------------------------------------------------------------
// Execute requested action
// ---------------------------------------------------------------------------
try {
    if ($action === 'insights') {
        // Generate or retrieve cached insights
        $insights = getDocumentInsights($fileId);

        if (is_array($insights) && isset($insights['error'])) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $insights['error']]);
            exit;
        }

        echo json_encode(['ok' => true, 'insights' => $insights]);
        exit;
    }

    if ($action === 'ask') {
        // Accept question from POST (form‑encoded or JSON)
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        $question = trim(
            $_POST['question']
            ?? ($json['question'] ?? '')
        );

        if ($question === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Question required']);
            exit;
        }

        $answer = askDocumentQuestion($fileId, $question);

        if ($answer === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Unable to answer']);
            exit;
        }

        echo json_encode(['ok' => true, 'answer' => $answer]);
        exit;
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
    error_log('AI Summarize exception: ' . $e->getMessage());
}
exit;