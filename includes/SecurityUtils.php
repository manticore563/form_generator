<?php
/**
 * SecurityUtils Class
 * Additional security utilities and helpers
 */

class SecurityUtils {
    
    // Rate limiting storage
    private static $rateLimitData = [];
    
    /**
     * Sanitize output for display
     * @param string $data
     * @param bool $allowHtml
     * @return string
     */
    public static function sanitizeOutput($data, $allowHtml = false) {
        if (!is_string($data)) {
            return $data;
        }
        
        if ($allowHtml) {
            // Allow only safe HTML tags
            $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6>';
            return strip_tags($data, $allowedTags);
        }
        
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Generate secure random password
     * @param int $length
     * @param bool $includeSymbols
     * @return string
     */
    public static function generateSecurePassword($length = 12, $includeSymbols = true) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($includeSymbols) {
            $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }
        
        $password = '';
        $charsLength = strlen($chars);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
        
        return $password;
    }
    
    /**
     * Hash password securely
     * @param string $password
     * @return string
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verify password
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Check if password needs rehashing
     * @param string $hash
     * @return bool
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Rate limiting check
     * @param string $identifier (IP address, user ID, etc.)
     * @param int $maxAttempts
     * @param int $timeWindow (in seconds)
     * @return bool True if allowed, false if rate limited
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        $currentTime = time();
        
        // Clean up old entries
        if (isset(self::$rateLimitData[$identifier])) {
            self::$rateLimitData[$identifier] = array_filter(
                self::$rateLimitData[$identifier],
                function($timestamp) use ($currentTime, $timeWindow) {
                    return ($currentTime - $timestamp) < $timeWindow;
                }
            );
        }
        
        // Check if rate limit exceeded
        if (isset(self::$rateLimitData[$identifier]) && 
            count(self::$rateLimitData[$identifier]) >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        if (!isset(self::$rateLimitData[$identifier])) {
            self::$rateLimitData[$identifier] = [];
        }
        self::$rateLimitData[$identifier][] = $currentTime;
        
        return true;
    }
    
    /**
     * Get client IP address
     * @return string
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Validate file upload security
     * @param array $file $_FILES array element
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file was uploaded or upload failed.';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size.';
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            $errors[] = 'File type not allowed.';
        }
        
        // Check for malicious content in filename
        $filename = $file['name'];
        if (preg_match('/\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$/i', $filename)) {
            $errors[] = 'Executable files are not allowed.';
        }
        
        // Check for null bytes in filename
        if (strpos($filename, "\0") !== false) {
            $errors[] = 'Invalid filename.';
        }
        
        // Additional checks for image files
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = 'Invalid image file.';
            } else {
                // Check image dimensions
                list($width, $height) = $imageInfo;
                if ($width > 5000 || $height > 5000) {
                    $errors[] = 'Image dimensions too large.';
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType
        ];
    }
    
    /**
     * Generate secure filename
     * @param string $originalName
     * @param string $prefix
     * @return string
     */
    public static function generateSecureFilename($originalName, $prefix = '') {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Generate unique identifier
        $uniqueId = bin2hex(random_bytes(8));
        
        return $prefix . $basename . '_' . $uniqueId . '.' . $extension;
    }
    
    /**
     * Log security events
     * @param string $event
     * @param array $details
     * @param string $severity
     */
    public static function logSecurityEvent($event, $details = [], $severity = 'WARNING') {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'severity' => $severity,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => $details
        ];
        
        $logMessage = json_encode($logData) . PHP_EOL;
        
        // Log to security log file
        $logFile = (defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/') . 'security.log';
        
        // Create logs directory if it doesn't exist
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also log critical events to main error log
        if ($severity === 'CRITICAL' || $severity === 'ERROR') {
            error_log("SECURITY [{$severity}] {$event}: " . json_encode($details));
        }
    }
    
    /**
     * Check for suspicious user agent
     * @param string $userAgent
     * @return bool
     */
    public static function isSuspiciousUserAgent($userAgent = null) {
        if ($userAgent === null) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/java/i',
            '/perl/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate referrer
     * @param string $expectedDomain
     * @return bool
     */
    public static function validateReferrer($expectedDomain = null) {
        if (!isset($_SERVER['HTTP_REFERER'])) {
            return false;
        }
        
        if ($expectedDomain === null) {
            $expectedDomain = $_SERVER['HTTP_HOST'];
        }
        
        $referrerHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        return $referrerHost === $expectedDomain;
    }
    
    /**
     * Generate Content Security Policy header
     * @param array $directives
     * @return string
     */
    public static function generateCSPHeader($directives = []) {
        $defaultDirectives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline'",
            'style-src' => "'self' 'unsafe-inline'",
            'img-src' => "'self' data:",
            'font-src' => "'self'",
            'connect-src' => "'self'",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'"
        ];
        
        $directives = array_merge($defaultDirectives, $directives);
        
        $csp = [];
        foreach ($directives as $directive => $value) {
            $csp[] = $directive . ' ' . $value;
        }
        
        return implode('; ', $csp);
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        // Prevent XSS attacks
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Strict transport security (if HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = self::generateCSPHeader();
        header('Content-Security-Policy: ' . $csp);
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
    }
}
?>