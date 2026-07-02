<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/auth.php';
auditLog('LOGOUT');
unregisterUserSession();
$_SESSION = [];
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;