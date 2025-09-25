<?php
/**
 * Student Enrollment Platform Configuration
 * Generated automatically during installation on 2025-09-18 13:52:34
 * 
 * WARNING: This file contains sensitive information. 
 * Do not share or commit this file to version control.
 */

// Prevent direct access
if (!defined('SEFP_INIT')) {
    define('SEFP_INIT', true);
}

// Database Configuration
define('DB_HOST', 'sdb-89.hosting.stackcp.net');
define('DB_NAME', 'poly_form-353131373a27');
define('DB_USER', 'poly_form-353131373a27');
define('DB_PASS', 'Ramlal@563');
define('DB_CHARSET', 'utf8mb4');

// Security Configuration
define('SECURITY_KEY', '0066f14151bf0b6aa5d7556206ce410a193ee0e82f6daab57320bb948f630ad9');
define('CSRF_SECRET_KEY', 'f714da746ac5533b06a52a4df2e35baa');
define('SESSION_SECRET_KEY', '2ce98dc6e4a5692e993cd41cb96635a9');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CSRF_TOKEN_EXPIRY', 1800); // 30 minutes in seconds
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/uploads/');

// Image Processing Configuration
define('IMAGE_MAX_WIDTH', 1024);
define('IMAGE_MAX_HEIGHT', 1024);
define('IMAGE_QUALITY', 85);
define('THUMBNAIL_SIZE', 150);

// Application Configuration
define('APP_NAME', 'Student Enrollment Platform');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', false);
define('APP_INSTALLED', true);

// Paths Configuration
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Timezone Configuration
date_default_timezone_set('Asia/Kolkata');

// Error Reporting Configuration
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Error Log Configuration
ini_set('error_log', LOGS_PATH . '/php_errors.log');

// Session Configuration
ini_set('session.name', 'SEFP_SESSION');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_samesite', 'Strict');

// Security Headers Configuration
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Installation marker
define('SEFP_INSTALLED', true);
define('INSTALLED', true);
