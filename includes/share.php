<?php
require_once __DIR__ . '/db.php';

function createShareLink(int $fileId, int $userId, int $durationSeconds = 3600, ?int $maxAccess = null): string|false {
    $token = bin2hex(random_bytes(32));
    try {
        $stmt = db()->prepare('INSERT INTO shared_links (file_id, token, created_by, expires_at, max_access, duration) VALUES (?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND, ?, ?)');
        $stmt->execute([$fileId, $token, $userId, $durationSeconds, $maxAccess, $durationSeconds]);
        return $token;
    } catch (PDOException $e) {
        error_log('Share link create error: ' . $e->getMessage());
        return false;
    }
}

function validateShareToken(string $token): array|false {
    $stmt = db()->prepare('SELECT f.*, s.expires_at, s.max_access, s.access_count, s.duration FROM files f JOIN shared_links s ON s.file_id = f.id WHERE s.token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) return false;
    if (strtotime($row['expires_at']) <= time()) return false;
    if (!is_null($row['max_access']) && (int)$row['access_count'] >= (int)$row['max_access']) return false;
    db()->prepare('UPDATE shared_links SET access_count = access_count + 1 WHERE token = ?')->execute([$token]);
    return $row;
}

function cleanupExpiredShares(): void {
    db()->exec("DELETE FROM shared_links WHERE expires_at <= UTC_TIMESTAMP() OR (max_access IS NOT NULL AND access_count >= max_access)");
}