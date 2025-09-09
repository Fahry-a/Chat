<?php
/**
 * Chat Application Configuration
 * Copy this file and rename to config.php, then update with your settings
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'chatapp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'ChatApp');
define('APP_URL', 'http://localhost/chatapp');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB
define('SESSION_LIFETIME', 86400); // 24 hours

// Security Settings
define('SESSION_NAME', 'chatapp_session');
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 6);

// File Upload Settings
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg']);
define('ALLOWED_AUDIO_TYPES', ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4']);
define('ALLOWED_FILE_TYPES', [
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip', 'text/plain', 'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
]);

// Polling interval for AJAX updates (milliseconds)
define('POLLING_INTERVAL', 3000);

// Time zone
define('DEFAULT_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers for API (adjust for production)
function setCORSHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Content-Type: application/json');
}

// Create upload directories if they don't exist
$upload_dirs = [
    UPLOAD_PATH,
    UPLOAD_PATH . 'images/',
    UPLOAD_PATH . 'videos/',
    UPLOAD_PATH . 'audio/',
    UPLOAD_PATH . 'files/',
    UPLOAD_PATH . 'avatars/'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        // Add .htaccess to prevent direct execution
        file_put_contents($dir . '.htaccess', "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n");
    }
}