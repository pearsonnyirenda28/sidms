<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/encryption.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/office_preview.php';

$user = requireAuth();
$id   = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Invalid file ID.'); }

$where = $user['role']==='admin' ? 'id = ?' : 'id = ? AND user_id = ?';
$p     = $user['role']==='admin' ? [$id] : [$id, $user['id']];
$stmt  = db()->prepare("SELECT * FROM files WHERE {$where} LIMIT 1");
$stmt->execute($p);
$file  = $stmt->fetch();
if (!$file) { http_response_code(404); exit('File not found.'); }

if (isset($_GET['raw'])) {
    $enc = file_get_contents(STORAGE_DIR . $file['stored_name']);
    if ($enc === false) { http_response_code(500); exit('Storage error.'); }
    $plain = decryptFile($enc, $file['file_iv'], $file['enc_key'], $file['key_iv']);
    if ($plain === false) { http_response_code(500); exit('Decryption failed.'); }
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: inline; filename="' . rawurlencode($file['original_name']) . '"');
    header('Cache-Control: no-store');
    echo $plain; exit;
}

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
$previewable = $isImage || $isVideo || $isPdf || $isText || $isOffice;

function fmtSize($b) { if($b<1024) return $b.' B'; if($b<1048576) return round($b/1024,1).' KB'; if($b<1073741824) return round($b/1048576,1).' MB'; return round($b/1073741824,2).' GB'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Secure View — <?= htmlspecialchars($file['original_name']) ?></title>
    <meta name="author" content="<?= APP_DEVELOPER ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        @media print { body * { display: none !important; } }
        body { user-select: none; }
        .watermark-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            opacity: 0.12; font-size: 3.5rem; font-weight: 800;
            color: #000; transform: rotate(-25deg); white-space: nowrap;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .preview-container { background: #f8f9fa; min-height: 70vh; }
        .office-container { width: 100%; height: 85vh; border: 0; background: #fff; }
        img, video { max-width: 100%; max-height: 75vh; -webkit-user-drag: none; }
        #pdf-container { background: #525659; overflow: auto; text-align: center; }
        #pdf-canvas { display: block; margin: auto; }
    </style>
</head>
<body>
<div class="watermark-overlay" id="watermark"><?= htmlspecialchars($user['username']) ?> • <?= date('Y-m-d H:i') ?></div>
<nav class="navbar navbar-dark sidms-nav px-4">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-shield-lock-fill me-2"></i><?= APP_NAME ?></a>
    <div class="ms-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-warning" onclick="readDocumentAloud()" title="Read document aloud" id="read-aloud-btn"><i class="bi bi-volume-up me-1"></i> Read Aloud</button>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</nav>
<div class="container-fluid px-4 py-4">
    <div class="card sidms-card">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-eye-fill me-2 text-info"></i>
            <span class="fw-medium"><?= htmlspecialchars($file['original_name']) ?></span>
            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($file['ai_category']) ?></span>
            <span class="badge bg-success ms-1"><i class="bi bi-shield-lock-fill me-1"></i>View Only</span>
            <span class="text-muted ms-auto small"><?= fmtSize((int)$file['file_size']) ?> • <?= date('d M Y', strtotime($file['uploaded_at'])) ?></span>
        </div>
        <div class="card-body p-0 preview-container" style="height: calc(100vh - 120px);">
            <?php if ($isImage): ?>
                <div class="text-center p-4"><img src="<?= BASE_URL ?>/preview.php?id=<?= $id ?>&raw=1" class="img-fluid rounded shadow" style="max-height:100%; max-width:100%;" id="preview-image" alt="<?= htmlspecialchars($file['original_name']) ?>" crossorigin="anonymous"><canvas id="watermark-canvas" style="display:none;"></canvas></div>
            <?php elseif ($isVideo): ?>
                <div class="text-center p-4"><video controls controlsList="nodownload" disablePictureInPicture style="max-height:100%; max-width:100%;" id="preview-video"><source src="<?= BASE_URL ?>/preview.php?id=<?= $id ?>&raw=1" type="<?= $file['mime_type'] ?>"></video></div>
            <?php elseif ($isPdf): ?>
                <div id="pdf-container"><canvas id="pdf-canvas"></canvas></div>
                <div id="pdf-controls" style="text-align:center; padding:0.5rem; background:var(--sidms-surface); border-top:1px solid var(--sidms-border);">
                    <button class="btn btn-sm btn-outline-light me-2" onclick="changePage(-1)">← Prev</button>
                    <span class="text-light small" id="page-info">Page 1 of 1</span>
                    <button class="btn btn-sm btn-outline-light ms-2" onclick="changePage(1)">Next →</button>
                    <span class="text-light small ms-3">Zoom:</span>
                    <button class="btn btn-sm btn-outline-light ms-1" onclick="zoomOut()">−</button>
                    <span class="text-light small mx-1" id="zoom-level">150%</span>
                    <button class="btn btn-sm btn-outline-light" onclick="zoomIn()">+</button>
                </div>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
                <script>
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
                    const pdfUrl = '<?= BASE_URL ?>/preview.php?id=<?= $id ?>&raw=1';
                    let pdfDoc = null, pageNum = 1, scale = 1.5;
                    function renderPage(num) {
                        pdfDoc.getPage(num).then(page => {
                            const canvas = document.getElementById('pdf-canvas');
                            const ctx = canvas.getContext('2d');
                            const viewport = page.getViewport({scale: scale});
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            page.render({canvasContext: ctx, viewport: viewport});
                            document.getElementById('page-info').textContent = `Page ${num} of ${pdfDoc.numPages}`;
                            document.getElementById('zoom-level').textContent = Math.round(scale * 100) + '%';
                        });
                    }
                    pdfjsLib.getDocument(pdfUrl).promise.then(pdf => { pdfDoc = pdf; renderPage(1); }).catch(err => { document.getElementById('pdf-container').innerHTML = '<p class="text-center text-muted pt-5">Failed to load PDF.</p>'; });
                    window.changePage = delta => { if (!pdfDoc) return; pageNum = Math.max(1, Math.min(pdfDoc.numPages, pageNum + delta)); renderPage(pageNum); };
                    window.zoomIn = () => { scale = Math.min(3, scale + 0.25); renderPage(pageNum); };
                    window.zoomOut = () => { scale = Math.max(0.5, scale - 0.25); renderPage(pageNum); };
                </script>
            <?php elseif ($isText): ?>
                <iframe src="<?= BASE_URL ?>/preview.php?id=<?= $id ?>&raw=1" style="width:100%; height:100%; border:0; background:#fff; color:#000;" sandbox="allow-same-origin"></iframe>
            <?php elseif ($isOffice): ?>
                <iframe src="<?= BASE_URL ?>/api/office_preview.php?id=<?= $id ?>" class="office-container" sandbox="allow-same-origin"></iframe>
            <?php else: ?>
                <div class="text-center py-5 text-muted"><i class="bi bi-file-earmark-x fs-1 d-block mb-3"></i><h5>Preview Not Available</h5></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<div id="toast-container" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1055;"></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>const BASE_URL = '<?= BASE_URL ?>'; const fileId = <?= $id ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js"></script>
<script>
    function speak(text, lang = 'en-US') {
        if (!('speechSynthesis' in window)) return;
        window.speechSynthesis.cancel();
        const utter = new SpeechSynthesisUtterance(text);
        utter.lang = lang;
        utter.rate = 0.95;
        window.speechSynthesis.speak(utter);
    }
    async function readDocumentAloud() {
        const btn = document.getElementById('read-aloud-btn');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-volume-up me-1"></i> <span class="spinner-border spinner-border-sm"></span> Extracting…';
        btn.disabled = true;
        try {
            const res = await fetch(`${BASE_URL}/api/get_document_text.php?file_id=${fileId}`);
            const data = await res.json();
            if (data.ok && data.text && data.text.length > 10) {
                speak(data.text);
                SIDMS.toast.success('Reading document aloud.');
            } else {
                SIDMS.toast.error('No readable text found.');
            }
        } catch (err) {
            SIDMS.toast.error('Failed to fetch document text.');
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    }
    setInterval(() => {
        const el = document.getElementById('watermark');
        if (el) { const now = new Date(); el.textContent = '<?= addslashes(htmlspecialchars($user['username'])) ?> • ' + now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0')+' '+String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0'); }
    }, 60000);
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', e => { if (e.ctrlKey && (e.key==='s'||e.key==='p')) e.preventDefault(); if (e.key==='F12'||(e.ctrlKey&&e.shiftKey&&e.key==='I')) e.preventDefault(); });
    document.addEventListener('dragstart', e => { if (e.target.tagName==='IMG'||e.target.tagName==='VIDEO') e.preventDefault(); });
    const img = document.getElementById('preview-image');
    if (img) img.onload = function() {
        const canvas = document.getElementById('watermark-canvas'), ctx = canvas.getContext('2d');
        canvas.width = this.naturalWidth; canvas.height = this.naturalHeight;
        ctx.drawImage(this, 0, 0);
        ctx.font = 'bold 28px "Segoe UI", Arial, sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,0.45)'; ctx.strokeStyle = 'rgba(0,0,0,0.6)'; ctx.lineWidth = 3;
        const text = '<?= addslashes(htmlspecialchars($user['username'])) ?> • <?= date('Y-m-d H:i') ?>';
        const tw = ctx.measureText(text).width;
        ctx.strokeText(text, (canvas.width-tw)/2, canvas.height/2);
        ctx.fillText(text, (canvas.width-tw)/2, canvas.height/2);
        this.src = canvas.toDataURL('image/png');
    };
    const video = document.getElementById('preview-video');
    if (video) { video.addEventListener('contextmenu', e => e.preventDefault()); video.setAttribute('controlsList','nodownload'); }
</script>
</body>
</html>