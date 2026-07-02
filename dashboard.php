<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/encryption.php';
require_once __DIR__ . '/includes/ai_monitor.php';

$user = requireAuth();

$where  = $user['role'] === 'admin' ? '' : 'WHERE f.user_id = ?';
$params = $user['role'] === 'admin' ? [] : [$user['id']];
$search = trim($_GET['q'] ?? '');
if ($search) {
    $where   = $where ? $where . " AND f.original_name LIKE ?" : "WHERE f.original_name LIKE ?";
    $params[] = "%{$search}%";
}
$files = db()->prepare(
    "SELECT f.*, u.username FROM files f JOIN users u ON u.id=f.user_id
     {$where} ORDER BY f.uploaded_at DESC LIMIT 200"
);
$files->execute($params);
$files = $files->fetchAll();

$totalFiles = db()->prepare("SELECT COUNT(*) FROM files WHERE user_id=?");
$totalFiles->execute([$user['id']]);
$totalFiles = $totalFiles->fetchColumn();

$totalSize  = db()->prepare("SELECT COALESCE(SUM(file_size),0) FROM files WHERE user_id=?");
$totalSize->execute([$user['id']]);
$totalSize = $totalSize->fetchColumn();

function fmtSize(int $b): string {
    if ($b < 1024) return $b.' B';
    if ($b < 1048576) return round($b/1024,1).' KB';
    if ($b < 1073741824) return round($b/1048576,1).' MB';
    return round($b/1073741824,2).' GB';
}
function fileIcon(string $cat): string {
    $map = [
        'PDF Document'=>'bi-file-pdf','Word Document'=>'bi-file-word',
        'Spreadsheet'=>'bi-file-spreadsheet','Presentation'=>'bi-file-slides',
        'Image'=>'bi-file-image','Video'=>'bi-file-play','Audio'=>'bi-file-music',
        'Archive'=>'bi-file-zip','Text File'=>'bi-file-text','CSV Data'=>'bi-filetype-csv',
    ];
    return $map[$cat] ?? 'bi-file-earmark';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — <?= APP_NAME ?></title>
    <meta name="author" content="<?= APP_DEVELOPER ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';
    </script>
    <script>
        function generateQRCode(text, width = 150, height = 150) {
            const img = document.createElement('img');
            img.src = `https://api.qrserver.com/v1/create-qr-code/?size=${width}x${height}&data=${encodeURIComponent(text)}`;
            img.width = width;
            img.height = height;
            img.alt = 'QR Code';
            return img;
        }
    </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sidms-nav">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold fs-5" href="<?= BASE_URL ?>/dashboard.php">
            <i class="bi bi-shield-lock-fill me-2 text-primary"></i><?= APP_NAME ?>
            <span class="d-none d-md-inline text-muted small">— <?= APP_FULL_NAME ?></span>
        </a>
        <div class="navbar-nav ms-auto d-flex flex-row gap-2 align-items-center">
            <?php if ($user['role']==='admin'): ?>
            <a class="nav-link text-light" href="<?= BASE_URL ?>/admin/"><i class="bi bi-speedometer2 me-1"></i>Admin</a>
            <?php endif; ?>
            <span class="text-light small opacity-75"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['username']) ?></span>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="stat-card card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-files"></i></div><div><div class="stat-value"><?= $totalFiles ?></div><div class="stat-label">My Files</div></div></div></div></div>
        <div class="col-md-3"><div class="stat-card card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="stat-icon bg-success-subtle text-success"><i class="bi bi-hdd"></i></div><div><div class="stat-value"><?= fmtSize((int)$totalSize) ?></div><div class="stat-label">Storage Used</div></div></div></div></div>
        <div class="col-md-3"><div class="stat-card card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-shield-check"></i></div><div><div class="stat-value">AES-256</div><div class="stat-label">Encryption</div></div></div></div></div>
        <div class="col-md-3"><div class="stat-card card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="stat-icon bg-info-subtle text-info"><i class="bi bi-person-badge"></i></div><div><div class="stat-value text-capitalize"><?= htmlspecialchars($user['role']) ?></div><div class="stat-label">Access Level</div></div></div></div></div>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card sidms-card h-100">
                <div class="card-header fw-semibold"><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload File</div>
                <div class="card-body">
                    <div id="drop-zone" class="drop-zone mb-3"><i class="bi bi-cloud-arrow-up fs-2 text-muted"></i><p class="mb-0 mt-2 small text-muted">Drag & drop or click to select</p><p class="mb-0 small text-muted">Max <?= MAX_FILE_MB ?>MB</p></div>
                    <form id="upload-form" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="file" id="file-input" name="file" class="d-none" multiple>
                        <button type="button" id="browse-btn" class="btn btn-outline-primary w-100 mb-2"><i class="bi bi-folder2-open me-2"></i>Browse Files</button>
                        <div id="file-list" class="mb-3"></div>
                        <div id="upload-progress" class="d-none"><div class="progress mb-2"><div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div></div>
                        <button type="submit" id="upload-btn" class="btn btn-primary w-100 d-none"><i class="bi bi-upload me-2"></i>Encrypt & Upload</button>
                    </form>
                    <div id="upload-msg"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card sidms-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-folder2 me-2 text-warning"></i><?= $user['role']==='admin'?'All Files':'My Files' ?> <span class="badge bg-secondary ms-2"><?= count($files) ?></span></span>
                    <form method="GET" class="d-flex gap-2" style="max-width:280px">
                        <input name="q" class="form-control form-control-sm" placeholder="Search files…" value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                        <?php if ($search): ?><a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></a><?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover mb-0 align-middle sidms-table">
                        <thead><tr><th>File</th><th>Category</th><th>Size</th><?php if($user['role']==='admin'):?><th>Owner</th><?php endif;?><th>Uploaded</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($files as $f): ?>
                        <tr>
                            <td><i class="bi <?= fileIcon($f['ai_category']) ?> me-2 text-primary"></i><span class="fw-medium"><?= htmlspecialchars($f['original_name']) ?></span></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($f['ai_category']) ?></span></td>
                            <td class="text-muted small"><?= fmtSize((int)$f['file_size']) ?></td>
                            <?php if ($user['role']==='admin'): ?><td><span class="badge bg-secondary"><?= htmlspecialchars($f['username']) ?></span></td><?php endif; ?>
                            <td class="text-muted small"><?= date('d M Y H:i', strtotime($f['uploaded_at'])) ?></td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>/preview.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="Preview"><i class="bi bi-eye"></i></a>
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="createShareModal(<?= $f['id'] ?>)" title="Share"><i class="bi bi-share"></i></button>
                                <?php if ($user['role']==='admin'): ?>
                                <a href="<?= BASE_URL ?>/download.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-success me-1" title="Download"><i class="bi bi-download"></i></a>
                                <button class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteFile(<?= $f['id'] ?>, this, '<?= $_SESSION['csrf_token'] ?>')"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$files): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>No files found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content sidms-modal" style="color:#e6edf3;">
        <div class="modal-header"><h5 class="modal-title" style="color:#e6edf3;">Share for Viewing</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" style="color:#e6edf3; font-weight:600;">Expires after</label>
                <select id="share-duration" class="form-select" style="background:#21262d; border-color:#30363d; color:#e6edf3;">
                    <option value="3600">1 hour</option><option value="21600" selected>6 hours</option><option value="43200">12 hours</option><option value="86400">1 day</option><option value="259200">3 days</option><option value="604800">7 days</option>
                </select>
            </div>
            <div class="mb-3"><label class="form-label" style="color:#e6edf3; font-weight:600;">Max number of viewers (empty = unlimited)</label>
                <input type="number" id="share-max-access" class="form-control" placeholder="e.g. 10" min="1" value="" style="background:#21262d; border-color:#30363d; color:#e6edf3;">
                <small style="color:#adbac7;">Leave blank for unlimited views.</small>
            </div>
            <button type="button" class="btn btn-primary w-100 mb-3" onclick="generateShareLink()"><i class="bi bi-link-45deg me-1"></i> Generate Link</button>
            <div class="mb-3"><label class="form-label" style="color:#e6edf3; font-weight:600;">Shareable link</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="shareLinkInput" readonly style="background:#21262d; border-color:#30363d; color:#e6edf3;">
                    <button class="btn btn-outline-primary" onclick="copyShareLink()" style="color:#58a6ff; border-color:#30363d;"><i class="bi bi-clipboard"></i> Copy</button>
                </div>
            </div>
            <div id="shareQrCode" class="text-center mt-3"></div>
        </div>
    </div></div>
</div>

<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1055;"></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js');
</script>
</body>
</html>