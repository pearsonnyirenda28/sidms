<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$user = requireAdmin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? 'SIDMS';
    $message = $_POST['message'] ?? '';
    if ($message) {
        $ch = curl_init(BASE_URL . '/api/push_send.php');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['title' => $title, 'message' => $message]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Cookie: SIDMS_SID=' . session_id()],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10
        ]);
        curl_exec($ch);
        curl_close($ch);
        $msg = 'Push notification sent to all subscribers.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Push — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark sidms-nav px-4">
    <a class="navbar-brand" href="<?= BASE_URL ?>/admin/">← Dashboard</a>
</nav>
<div class="container-fluid px-4 py-4">
    <h4><i class="bi bi-bell-fill me-2"></i>Send Push Notification</h4>
    <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="POST">
        <div class="mb-3"><input name="title" class="form-control" placeholder="Title" value="SIDMS"></div>
        <div class="mb-3"><textarea name="message" class="form-control" rows="3" placeholder="Notification message" required></textarea></div>
        <button class="btn btn-primary">Send to All Subscribers</button>
    </form>
</div>
</body>
</html>