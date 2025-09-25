<?php
/**
 * CSRFProtection Class
 * Comprehensive CSRF protection for all form submissions
 */

class CSRFProtection {
    
    private static $instance = null;
    private static $tokenName = 'csrf_token';
    private static $tokenLifetime = 3600; // 1 hour
    
    private function __construct() {}
    
    /**
     * Get singleton instance
     * @return CSRFProtection
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate CSRF token
     * @param string $action Optional action name for token scoping
     * @return string
     */
    public function generateToken($action = 'default') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        // Store token with timestamp and action
        $_SESSION['csrf_tokens'][$action] = [
            'token' => $token,
            'timestamp' => $timestamp
        ];
        
        // Clean up old tokens
        $this->cleanupExpiredTokens();
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     * @param string $token
     * @param string $action Optional action name for token scoping
     * @return bool
     */
    public function validateToken($token, $action = 'default') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists for this action
        if (!isset($_SESSION['csrf_tokens'][$action])) {
            return false;
        }
        
        $storedData = $_SESSION['csrf_tokens'][$action];
        
        // Check if token has expired
        if (time() - $storedData['timestamp'] > self::$tokenLifetime) {
            unset($_SESSION['csrf_tokens'][$action]);
            return false;
        }
        
        // Validate token using hash_equals to prevent timing attacks
        $isValid = hash_equals($storedData['token'], $token);
        
        // Remove token after validation (one-time use)
        if ($isValid) {
            unset($_SESSION['csrf_tokens'][$action]);
        }
        
        return $isValid;
    }
    
    /**
     * Get CSRF token for forms
     * @param string $action Optional action name
     * @return string
     */
    public function getToken($action = 'default') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Return existing valid token or generate new one
        if (isset($_SESSION['csrf_tokens'][$action])) {
            $storedData = $_SESSION['csrf_tokens'][$action];
            if (time() - $storedData['timestamp'] <= self::$tokenLifetime) {
                return $storedData['token'];
            }
        }
        
        return $this->generateToken($action);
    }
    
    /**
     * Generate HTML hidden input for CSRF token
     * @param string $action Optional action name
     * @return string
     */
    public function getTokenInput($action = 'default') {
        $token = $this->getToken($action);
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Validate CSRF token from request
     * @param array $requestData POST/GET data
     * @param string $action Optional action name
     * @return bool
     */
    public function validateRequest($requestData, $action = 'default') {
        if (!isset($requestData[self::$tokenName])) {
            return false;
        }
        
        return $this->validateToken($requestData[self::$tokenName], $action);
    }
    
    /**
     * Clean up expired tokens
     */
    private function cleanupExpiredTokens() {
        if (!isset($_SESSION['csrf_tokens'])) {
            return;
        }
        
        $currentTime = time();
        foreach ($_SESSION['csrf_tokens'] as $action => $data) {
            if ($currentTime - $data['timestamp'] > self::$tokenLifetime) {
                unset($_SESSION['csrf_tokens'][$action]);
            }
        }
    }
    
    /**
     * Clear all CSRF tokens
     */
    public function clearAllTokens() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['csrf_tokens']);
    }
    
    /**
     * Set token lifetime
     * @param int $seconds
     */
    public static function setTokenLifetime($seconds) {
        self::$tokenLifetime = $seconds;
    }
    
    /**
     * Set token name
     * @param string $name
     */
    public static function setTokenName($name) {
        self::$tokenName = $name;
    }
    
    /**
     * Get token name
     * @return string
     */
    public static function getTokenName() {
        return self::$tokenName;
    }
    
    /**
     * Middleware function to protect routes
     * @param array $requestData
     * @param string $action
     * @param callable $callback
     * @return mixed
     */
    public function protect($requestData, $action, $callback) {
        if (!$this->validateRequest($requestData, $action)) {
            http_response_code(403);
            return [
                'success' => false,
                'error' => 'Invalid security token. Please refresh the page and try again.'
            ];
        }
        
        return call_user_func($callback);
    }
    
    /**
     * Generate meta tag for AJAX requests
     * @param string $action
     * @return string
     */
    public function getMetaTag($action = 'default') {
        $token = $this->getToken($action);
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Get token for AJAX requests
     * @param string $action
     * @return array
     */
    public function getTokenForAjax($action = 'default') {
        return [
            'name' => self::$tokenName,
            'value' => $this->getToken($action)
        ];
    }
}
?>