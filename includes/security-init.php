<?php
/**
 * Security Initialization
 * Sets up security headers and configurations for the application
 */

// Prevent direct access
if (!defined('SECURITY_INIT_ALLOWED')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// Include security classes
require_once __DIR__ . '/SecurityUtils.php';
require_once __DIR__ . '/CSRFProtection.php';
require_once __DIR__ . '/InputValidator.php';
require_once __DIR__ . '/FileSecurityManager.php';
require_once __DIR__ . '/AccessLogger.php';

// Set security headers for all requests
SecurityUtils::setSecurityHeaders();

// Start secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    
    session_start();
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Check for suspicious user agents
if (SecurityUtils::isSuspiciousUserAgent()) {
    $accessLogger = AccessLogger::getInstance();
    $accessLogger->logSuspiciousActivity('suspicious_user_agent_detected', [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'requested_url' => $_SERVER['REQUEST_URI'] ?? ''
    ]);
}

// Rate limiting for general requests
$clientIP = SecurityUtils::getClientIP();
if (!SecurityUtils::checkRateLimit('general_' . $clientIP, 100, 300)) { // 100 requests per 5 minutes
    $accessLogger = AccessLogger::getInstance();
    $accessLogger->logSuspiciousActivity('general_rate_limit_exceeded', [
        'ip' => $clientIP,
        'requested_url' => $_SERVER['REQUEST_URI'] ?? ''
    ], 'WARNING');
    
    http_response_code(429);
    header('Retry-After: 300');
    exit('Rate limit exceeded. Please try again later.');
}

// Log page access for security monitoring
if (defined('LOG_PAGE_ACCESS') && LOG_PAGE_ACCESS) {
    $accessLogger = AccessLogger::getInstance();
    $accessLogger->logAdminAccess('page_access', true, [
        'page' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET'
    ]);
}
?>