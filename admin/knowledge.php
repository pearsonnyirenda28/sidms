<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$user = requireAdmin();

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->prepare('DELETE FROM ai_knowledge WHERE id=?')->execute([$id]);
    header('Location: knowledge.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = (int)($_POST['id'] ?? 0);
    $term       = trim($_POST['term'] ?? '');
    $definition = trim($_POST['definition'] ?? '');
    $aliases    = trim($_POST['aliases'] ?? '');
    $category   = trim($_POST['category'] ?? '');
    if ($term && $definition) {
        if ($id) {
            db()->prepare('UPDATE ai_knowledge SET term=?, definition=?, aliases=?, category=? WHERE id=?')->execute([$term, $definition, $aliases, $category, $id]);
        } else {
            db()->prepare('INSERT INTO ai_knowledge (term, definition, aliases, category) VALUES (?,?,?,?)')->execute([$term, $definition, $aliases, $category]);
        }
    }
    header('Location: knowledge.php'); exit;
}

$entries = db()->query('SELECT * FROM ai_knowledge ORDER BY category, term')->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM ai_knowledge WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Knowledge Base — <?= APP_NAME ?></title>
    <meta name="author" content="<?= APP_DEVELOPER ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark sidms-nav px-4">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/admin/"><i class="bi bi-robot me-2"></i><?= APP_NAME ?> Admin</a>
    <div class="ms-auto d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/" class="btn btn-sm btn-outline-light">← Dashboard</a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>
<div class="container-fluid px-4 py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-database-fill-gear me-2 text-info"></i>AI Knowledge Base <small class="text-muted fs-6 ms-2"><?= count($entries) ?> entries</small></h4>
    <div class="card sidms-card mb-4">
        <div class="card-header fw-semibold"><?= $edit ? '<i class="bi bi-pencil me-2"></i>Edit Entry' : '<i class="bi bi-plus-circle me-2"></i>Add New Entry' ?></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Term / Concept</label><input type="text" name="term" class="form-control" value="<?= htmlspecialchars($edit['term'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Category</label><input type="text" name="category" class="form-control" value="<?= htmlspecialchars($edit['category'] ?? '') ?>" placeholder="e.g. Programming, Security"></div>
                    <div class="col-12"><label class="form-label">Definition</label><textarea name="definition" class="form-control" rows="4" required><?= htmlspecialchars($edit['definition'] ?? '') ?></textarea></div>
                    <div class="col-12"><label class="form-label">Aliases (comma‑separated)</label><input type="text" name="aliases" class="form-control" value="<?= htmlspecialchars($edit['aliases'] ?? '') ?>"></div>
                </div>
                <div class="mt-3"><button type="submit" class="btn btn-primary"><?= $edit ? 'Update' : 'Add' ?></button><?php if ($edit): ?> <a href="knowledge.php" class="btn btn-secondary ms-2">Cancel</a><?php endif; ?></div>
            </form>
        </div>
    </div>
    <div class="card sidms-card">
        <div class="card-header fw-semibold"><i class="bi bi-list-ul me-2"></i>All Entries</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 sidms-table">
                <thead><tr><th>ID</th><th>Term</th><th>Category</th><th>Definition</th><th>Aliases</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($entries as $e): ?>
                    <tr>
                        <td class="text-muted small"><?= $e['id'] ?></td>
                        <td class="fw-medium"><?= htmlspecialchars($e['term']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($e['category'] ?: 'General') ?></span></td>
                        <td><small><?= nl2br(htmlspecialchars($e['definition'])) ?></small></td>
                        <td class="text-muted small"><?= htmlspecialchars($e['aliases'] ?? '—') ?></td>
                        <td class="text-end">
                            <a href="?edit=<?= $e['id'] ?>" class="btn btn-xs btn-outline-info me-1"><i class="bi bi-pencil"></i></a>
                            <a href="?delete=<?= $e['id'] ?>" class="btn btn-xs btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($entries)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No entries yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>