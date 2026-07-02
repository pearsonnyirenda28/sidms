<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/encryption.php';
require_once __DIR__ . '/includes/share.php';

$token = $_GET['token'] ?? '';
if (!$token) { http_response_code(400); exit('Missing token.'); }

$file = validateShareToken($token);
if (!$file) { http_response_code(404); exit('Invalid or expired link.'); }

if (isset($_GET['raw'])) {
    $enc = file_get_contents(STORAGE_DIR . $file['stored_name']);
    $plain = decryptFile($enc, $file['file_iv'], $file['enc_key'], $file['key_iv']);
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: inline; filename="' . rawurlencode($file['original_name']) . '"');
    echo $plain; exit;
}

$duration = (int)($file['duration'] ?? 3600);
$hours = round($duration / 3600, 1);
$durationText = $hours >= 24 ? round($hours/24,1) . ' days' : ($hours >= 1 ? $hours . ' hours' : round($duration/60) . ' minutes');

$isImage  = str_starts_with($file['mime_type'], 'image/');
$isVideo  = str_starts_with($file['mime_type'], 'video/');
$isPdf    = $file['mime_type'] === 'application/pdf';
$isText   = str_starts_with($file['mime_type'], 'text/') || in_array($file['mime_type'], ['application/json','application/xml']);
$isOffice = in_array($file['mime_type'], [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.ms-powerpoint'
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shared View — <?= htmlspecialchars($file['original_name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        @media print{body{display:none}} body{user-select:none}
        .watermark{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;opacity:0.12;font-size:3rem;font-weight:800;transform:rotate(-25deg);white-space:nowrap}
        .preview-container{background:#f8f9fa;min-height:70vh} img,video{max-width:100%;max-height:75vh}
    </style>
</head>
<body>
<div class="watermark">Guest Viewer · <?= date('Y-m-d H:i') ?></div>
<nav class="navbar navbar-dark bg-dark px-4"><span class="navbar-brand"><?= APP_NAME ?> — Shared View</span><span class="text-light small">Valid for <?= htmlspecialchars($durationText) ?></span></nav>
<div class="container-fluid px-4 py-4">
    <div class="card sidms-card">
        <div class="card-header"><?= htmlspecialchars($file['original_name']) ?> <span class="badge bg-warning">Temporary Access</span></div>
        <div class="card-body p-0 preview-container">
            <?php if($isImage): ?><div class="text-center p-4"><img src="?token=<?=$token?>&raw=1" class="img-fluid"></div>
            <?php elseif($isVideo): ?><div class="text-center p-4"><video controls controlsList="nodownload"><source src="?token=<?=$token?>&raw=1"></video></div>
            <?php elseif($isPdf): ?><iframe src="?token=<?=$token?>&raw=1" style="width:100%;height:85vh;border:0;background:#fff;"></iframe>
            <?php elseif($isText): ?><iframe src="?token=<?=$token?>&raw=1" style="width:100%;height:80vh;border:0;background:#fff;color:#000;"></iframe>
            <?php elseif($isOffice): ?><iframe src="<?= BASE_URL ?>/api/office_preview.php?id=<?= $file['id'] ?>" style="width:100%;height:85vh;border:0;background:#fff;" sandbox="allow-same-origin"></iframe>
            <?php else: ?><div class="text-center py-5 text-muted">Preview not available</div><?php endif; ?>
        </div>
    </div>
</div>
<script>
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', e => { if (e.ctrlKey && (e.key==='s'||e.key==='p')) e.preventDefault(); });
    document.addEventListener('dragstart', e => { if (e.target.tagName==='IMG'||e.target.tagName==='VIDEO') e.preventDefault(); });
</script>
</body>
</html>