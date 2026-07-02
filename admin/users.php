<?php
/**
 * User Management
 *
 * Create, activate, suspend, delete, and promote users.
 * Enforces maximum of 7 admin accounts.
 *
 * @package SIDMS
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/totp.php';
require_once __DIR__ . '/../includes/audit.php';

$user = requireAdmin();

/**
 * Count current admin users.
 */
function countAdminUsers(): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

/**
 * Check if admin limit (7) has been reached.
 */
function isAdminLimitReached(): bool {
    return countAdminUsers() >= 7;
}

$message = '';
$messageType = 'info';
$adminCount = countAdminUsers();
$adminLimit = 7;

// -----------------------------------------------------------------------------
// Handle POST Actions (with CSRF protection)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid security token.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $uid    = (int)($_POST['uid'] ?? 0);

        try {
            if ($action === 'activate') {
                db()->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$uid]);
                $message = 'User activated successfully.';
                auditLog('ADMIN_ACTION', "Activated user id={$uid}");
            } elseif ($action === 'suspend') {
                db()->prepare("UPDATE users SET status = 'suspended' WHERE id = ? AND role != 'admin'")->execute([$uid]);
                $message = 'User suspended.';
                auditLog('ADMIN_ACTION', "Suspended user id={$uid}");
            } elseif ($action === 'delete') {
                db()->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$uid]);
                $message = 'User deleted.';
                auditLog('ADMIN_ACTION', "Deleted user id={$uid}");
            } elseif ($action === 'make_admin') {
                if (isAdminLimitReached()) {
                    $message = 'Cannot promote to admin. Maximum of 7 admin accounts already exists.';
                    $messageType = 'danger';
                } else {
                    db()->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$uid]);
                    $message = 'User promoted to admin.';
                    $messageType = 'success';
                    auditLog('ADMIN_ACTION', "Promoted user id={$uid} to admin");
                }
            } elseif ($action === 'create') {
                $uname = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $pass  = trim($_POST['password'] ?? '');
                $role  = $_POST['role'] ?? 'user';

                if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $uname)) {
                    throw new Exception('Username must be 3-30 alphanumeric characters.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address.');
                }
                $pwdCheck = validatePasswordStrength($pass);
                if ($pwdCheck !== true) {
                    throw new Exception($pwdCheck);
                }
                if ($role === 'admin' && isAdminLimitReached()) {
                    throw new Exception('Maximum number of admin accounts (7) reached. Cannot create another admin.');
                }

                $secret = totpGenerateSecret();
                $hash   = password_hash($pass, PASSWORD_BCRYPT);

                db()->prepare(
                    "INSERT INTO users (username, email, password_hash, otp_secret, role, status)
                     VALUES (?, ?, ?, ?, ?, 'active')"
                )->execute([$uname, $email, $hash, $secret, $role]);

                $qrUrl = totpQRUrl($uname, $secret);
                $message = "User '{$uname}' created successfully. Provide this QR code for OTP setup:<br>
                            <img src='" . htmlspecialchars($qrUrl) . "' width='150' class='mt-2'>";
                $messageType = 'success';
                auditLog('ADMIN_ACTION', "Created user {$uname}");
            }
        } catch (PDOException $e) {
            $message = ($e->getCode() == 23000) ? 'Username or email already taken.' : 'Database error.';
            $messageType = 'danger';
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($action !== 'create') {
        header('Location: ' . BASE_URL . '/admin/users.php');
        exit;
    }
}

// -----------------------------------------------------------------------------
// Fetch Users
// -----------------------------------------------------------------------------
$users = db()->query(
    "SELECT u.*,
            (SELECT COUNT(*) FROM files WHERE user_id = u.id) AS file_count
     FROM users u
     ORDER BY u.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-dark sidms-nav px-4">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/admin/">
        <i class="bi bi-shield-lock-fill me-2"></i><?= APP_NAME ?> Admin
    </a>
    <div class="ms-auto d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/" class="btn btn-sm btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
        <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">
            <i class="bi bi-people me-2 text-primary"></i>User Management
            <span class="badge bg-info ms-2"><?= $adminCount ?>/<?= $adminLimit ?> Admins</span>
        </h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-person-plus me-2"></i>Create User
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="card sidms-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle sidms-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Files</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-muted small"><?= $u['id'] ?></td>
                        <td>
                            <i class="bi bi-person-circle me-2 text-primary"></i>
                            <?= htmlspecialchars($u['username']) ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                <?= $u['role'] ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : ($u['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= $u['status'] ?>
                            </span>
                        </td>
                        <td><?= $u['file_count'] ?></td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if (in_array($u['status'], ['pending', 'suspended'])): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="activate">
                                    <button type="submit" class="btn btn-xs btn-success" title="Activate">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($u['status'] === 'active' && $u['role'] !== 'admin'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="suspend">
                                    <button type="submit" class="btn btn-xs btn-warning" title="Suspend">
                                        <i class="bi bi-pause-fill"></i>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($u['role'] !== 'admin'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-xs btn-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>

                                <form method="POST" class="d-inline" onsubmit="return confirm('Promote this user to admin?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="make_admin">
                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Make Admin"
                                        <?= isAdminLimitReached() ? 'disabled' : '' ?>>
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content sidms-modal">
            <div class="modal-header">
                <h5 class="modal-title">Create New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input name="username" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                        <small class="text-muted">Min 8 chars, upper, lower, digit, special.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin" <?= isAdminLimitReached() ? 'disabled' : '' ?>>Admin</option>
                        </select>
                        <?php if (isAdminLimitReached()): ?>
                            <small class="text-warning">Admin limit (7) reached. Cannot create another admin.</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>