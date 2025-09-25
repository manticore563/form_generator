<?php
/**
 * Configuration Template for Student Enrollment Form Platform
 * This file will be copied and populated during installation
 */

// Database Configuration
define('DB_HOST', '{{DB_HOST}}');
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASS', '{{DB_PASS}}');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Student Enrollment Form Platform');
define('APP_VERSION', '1.0.0');
define('APP_URL', '{{APP_URL}}');

// Security Configuration
define('SESSION_NAME', 'SEFP_SESSION');
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', __DIR__ . '/uploads/files/');
define('UPLOAD_URL', APP_URL . '/uploads/files/');

// Image Processing Configuration
define('IMAGE_MAX_WIDTH', 1024);
define('IMAGE_MAX_HEIGHT', 1024);
define('IMAGE_QUALITY', 85);
define('THUMBNAIL_SIZE', 150);

// Error Reporting
define('DEBUG_MODE', false);
define('LOG_ERRORS', true);
define('LOG_PATH', __DIR__ . '/logs/');

// Installation Status
define('INSTALLED', true);

// Timezone
date_default_timezone_set('UTC');

// Include database connection
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/functions.php';
?>