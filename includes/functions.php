<?php
/**
 * Common utility functions for the Student Enrollment Form Platform
 */

// Security constants
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('SESSION_TIMEOUT', 3600); // 1 hour
define('LOG_ERRORS', true);

// Include security classes
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/CSRFProtection.php';
require_once __DIR__ . '/SecurityUtils.php';

/**
 * Generate a secure random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate UUID v4
 * @return string
 */
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
    
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Sanitize input data (legacy function - use InputValidator for new code)
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return SecurityUtils::sanitizeOutput(trim($data));
}

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate Aadhar number format (12 digits)
 * @param string $aadhar
 * @return bool
 */
function isValidAadhar($aadhar) {
    // Remove any spaces or hyphens
    $aadhar = preg_replace('/[\s-]/', '', $aadhar);
    
    // Check if it's exactly 12 digits
    return preg_match('/^\d{12}$/', $aadhar);
}

/**
 * Check if installation is complete
 * @return bool
 */
function isInstalled() {
    return defined('INSTALLED') && INSTALLED === true && file_exists(__DIR__ . '/../config/config.php');
}

/**
 * Redirect to URL
 * @param string $url
 * @param int $statusCode
 */
function redirect($url, $statusCode = 302) {
    header("Location: {$url}", true, $statusCode);
    exit();
}

/**
 * Get current URL
 * @return string
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return "{$protocol}://{$host}{$uri}";
}

/**
 * Get base URL of the application
 * @return string
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    return "{$protocol}://{$host}{$path}";
}

/**
 * Format file size in human readable format
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Create directory if it doesn't exist
 * @param string $path
 * @param int $permissions
 * @return bool
 */
function ensureDirectory($path, $permissions = 0755) {
    if (!is_dir($path)) {
        return mkdir($path, $permissions, true);
    }
    return true;
}

/**
 * Log application errors
 * @param string $message
 * @param string $level
 */
function logError($message, $level = 'ERROR') {
    if (defined('LOG_ERRORS') && LOG_ERRORS) {
        $logFile = (defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/') . 'application.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // Create logs directory if it doesn't exist
        ensureDirectory(dirname($logFile));
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Generate CSRF token (legacy function - use CSRFProtection for new code)
 * @return string
 */
function generateCSRFToken() {
    $csrf = CSRFProtection::getInstance();
    return $csrf->generateToken();
}

/**
 * Validate CSRF token (legacy function - use CSRFProtection for new code)
 * @param string $token
 * @return bool
 */
function validateCSRFToken($token) {
    $csrf = CSRFProtection::getInstance();
    return $csrf->validateToken($token);
}

/**
 * Check if request is POST
 * @return bool
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 * @return bool
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get POST data with optional default value
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getPost($key, $default = null) {
    return isset($_POST[$key]) ? sanitizeInput($_POST[$key]) : $default;
}

/**
 * Get GET data with optional default value
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getGet($key, $default = null) {
    return isset($_GET[$key]) ? sanitizeInput($_GET[$key]) : $default;
}

/**
 * Check if user is authenticated (simplified)
 * @return bool
 */
function isAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require authentication (simplified)
 * @param string $redirectUrl
 */
function requireAuth($redirectUrl = '../auth/simple-login.php') {
    if (!isAuthenticated()) {
        SecurityUtils::logSecurityEvent('unauthorized_access_attempt', [
            'requested_url' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => SecurityUtils::getClientIP()
        ]);
        redirect($redirectUrl);
    }
}

/**
 * Validate and sanitize field input
 * @param string $fieldType
 * @param mixed $value
 * @param array $options
 * @return array
 */
function validateField($fieldType, $value, $options = []) {
    $validator = InputValidator::getInstance();
    return $validator->validateField($fieldType, $value, $options);
}

/**
 * Get CSRF token input HTML
 * @param string $action
 * @return string
 */
function csrfTokenInput($action = 'default') {
    $csrf = CSRFProtection::getInstance();
    return $csrf->getTokenInput($action);
}

/**
 * Validate request with CSRF protection
 * @param array $requestData
 * @param string $action
 * @return bool
 */
function validateRequest($requestData, $action = 'default') {
    $csrf = CSRFProtection::getInstance();
    return $csrf->validateRequest($requestData, $action);
}

/**
 * Log security event
 * @param string $event
 * @param array $details
 * @param string $severity
 */
function logSecurityEvent($event, $details = [], $severity = 'WARNING') {
    SecurityUtils::logSecurityEvent($event, $details, $severity);
}

/**
 * Check if the application is installed (enhanced version)
 * @return bool
 */
function isInstalledComplete() {
    return file_exists(__DIR__ . '/../config/config.php') && 
           file_exists(__DIR__ . '/../config/.installation_complete');
}

/**
 * Get application configuration value
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed
 */
function getConfig($key, $default = null) {
    static $config = null;
    
    if ($config === null) {
        $configFile = __DIR__ . '/../config/config.php';
        if (file_exists($configFile)) {
            // Define SEFP_INIT to allow config file to load
            if (!defined('SEFP_INIT')) {
                define('SEFP_INIT', true);
            }
            include_once $configFile;
            $config = true;
        } else {
            $config = false;
        }
    }
    
    if ($config && defined($key)) {
        return constant($key);
    }
    
    return $default;
}

/**
 * Enhanced log error function
 * @param string $message Error message
 * @param string $level Error level (ERROR, WARNING, INFO)
 * @param array $context Additional context data
 */
function logErrorEnhanced($message, $level = 'ERROR', $context = []) {
    if (!defined('LOG_ERRORS') || !LOG_ERRORS) {
        return;
    }
    
    $logFile = __DIR__ . '/../logs/application.log';
    $logDir = dirname($logFile);
    
    // Create log directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Generate secure file name with enhanced security
 * @param string $originalName Original file name
 * @return string Secure file name
 */
function generateSecureFileNameEnhanced($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    
    // Sanitize base name
    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    $baseName = substr($baseName, 0, 50); // Limit length
    
    // Generate unique identifier
    $uniqueId = generateRandomString(16);
    
    return $baseName . '_' . $uniqueId . '.' . $extension;
}