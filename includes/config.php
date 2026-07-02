<?php
// -----------------------------------------------------------------------------
// Production safety – do NOT display errors on a live server
// -----------------------------------------------------------------------------
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// -----------------------------------------------------------------------------
// Web Push Notifications – keys now read from environment
// -----------------------------------------------------------------------------
define('PUSH_ENABLED', true);
define('VAPID_PUBLIC_KEY',  getenv('VAPID_PUBLIC_KEY')  ?: '');
define('VAPID_PRIVATE_KEY', getenv('VAPID_PRIVATE_KEY') ?: '');

// -----------------------------------------------------------------------------
// Database – read from environment (safe defaults for local dev)
// -----------------------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'sidms');

// -----------------------------------------------------------------------------
// Paths & URLs – these are safe
// -----------------------------------------------------------------------------
define('BASE_URL', '/sidms');
define('STORAGE_DIR', __DIR__ . '/../storage/');

// -----------------------------------------------------------------------------
// Master encryption key – MUST be 32 random bytes, stored outside web root
// Now read from environment
// -----------------------------------------------------------------------------
define('MASTER_KEY', getenv('MASTER_KEY') ?: '');

// -----------------------------------------------------------------------------
// Limits & metadata – safe to hardcode
// -----------------------------------------------------------------------------
define('MAX_FILE_MB', 50);
define('APP_NAME', 'SIDMS');
define('APP_FULL_NAME', 'Secure Intelligent Document Management System');
define('APP_VERSION', '2.0');
define('APP_DEVELOPER', 'Bl@de_zar');

// -----------------------------------------------------------------------------
// Twilio SMS – disabled by default, keys read from env if enabled
// -----------------------------------------------------------------------------
define('SMS_ENABLED', false);
define('TWILIO_SID',   getenv('TWILIO_SID')   ?: '');
define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
define('TWILIO_FROM',  getenv('TWILIO_FROM')  ?: '');
define('ADMIN_PHONE',  getenv('ADMIN_PHONE')  ?: '+1234567890');

// -----------------------------------------------------------------------------
// Email
// -----------------------------------------------------------------------------
define('EMAIL_ENABLED', true);
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@localhost');

// -----------------------------------------------------------------------------
// Security thresholds
// -----------------------------------------------------------------------------
define('FAIL_LOGIN_MAX', 5);
define('DOWNLOAD_MAX', 20);
define('ALERT_WINDOW', 600);          // seconds

// -----------------------------------------------------------------------------
// AI / External APIs – all keys now from environment
// -----------------------------------------------------------------------------
define('AI_ENABLED', true);
define('AI_PROVIDER', 'auto');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
define('OPENAI_MODEL', 'gpt-4o-mini');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL', 'gemini-2.0-flash');
define('COHERE_API_KEY', getenv('COHERE_API_KEY') ?: '');
define('EMBEDDING_PROVIDER', 'openai');
define('AI_CACHE_MINUTES', 60);

// -----------------------------------------------------------------------------
// Session configuration
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure',   0);                     // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_path', '/');                      // ← FIX: cookie available everywhere
    session_name('SIDMS_SID');
    session_start();
}

// -----------------------------------------------------------------------------
// CSRF token
// -----------------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------------------------------
// Timezone
// -----------------------------------------------------------------------------
date_default_timezone_set('UTC');