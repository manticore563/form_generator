<?php
/**
 * AccessLogger Class
 * Comprehensive access logging and security event monitoring
 */

class AccessLogger {
    
    private static $instance = null;
    private $logFile;
    private $accessLogFile;
    
    private function __construct() {
        $logDir = (defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/../logs/');
        $this->logFile = $logDir . 'security.log';
        $this->accessLogFile = $logDir . 'access.log';
        
        // Ensure log directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Get singleton instance
     * @return AccessLogger
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log file access attempt
     * @param string $fileId
     * @param string $action
     * @param bool $success
     * @param array $context
     */
    public function logFileAccess($fileId, $action, $success, $context = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'file_access',
            'file_id' => $fileId,
            'action' => $action, // 'view', 'download', 'upload', 'delete'
            'success' => $success,
            'ip' => SecurityUtils::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'session_id' => session_id(),
            'user_id' => $_SESSION['admin_id'] ?? null,
            'context' => $context
        ];
        
        $this->writeLog($this->accessLogFile, $logData);
        
        // Also log failed attempts to security log
        if (!$success) {
            SecurityUtils::logSecurityEvent('file_access_denied', [
                'file_id' => $fileId,
                'action' => $action,
                'context' => $context
            ], 'WARNING');
        }
    }
    
    /**
     * Log form submission attempt
     * @param string $formId
     * @param bool $success
     * @param array $errors
     */
    public function logFormSubmission($formId, $success, $errors = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'form_submission',
            'form_id' => $formId,
            'success' => $success,
            'errors' => $errors,
            'ip' => SecurityUtils::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'session_id' => session_id()
        ];
        
        $this->writeLog($this->accessLogFile, $logData);
    }
    
    /**
     * Log admin access
     * @param string $action
     * @param bool $success
     * @param array $details
     */
    public function logAdminAccess($action, $success, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'admin_access',
            'action' => $action,
            'success' => $success,
            'admin_id' => $_SESSION['admin_id'] ?? null,
            'ip' => SecurityUtils::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'details' => $details
        ];
        
        $this->writeLog($this->accessLogFile, $logData);
        
        // Log failed admin access attempts to security log
        if (!$success) {
            SecurityUtils::logSecurityEvent('admin_access_denied', [
                'action' => $action,
                'details' => $details
            ], 'ERROR');
        }
    }
    
    /**
     * Log suspicious activity
     * @param string $activity
     * @param array $details
     * @param string $severity
     */
    public function logSuspiciousActivity($activity, $details = [], $severity = 'WARNING') {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'suspicious_activity',
            'activity' => $activity,
            'severity' => $severity,
            'ip' => SecurityUtils::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'session_id' => session_id(),
            'details' => $details
        ];
        
        $this->writeLog($this->accessLogFile, $logData);
        SecurityUtils::logSecurityEvent($activity, $details, $severity);
    }
    
    /**
     * Get access statistics
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getAccessStatistics($days = 7) {
        $stats = [
            'total_requests' => 0,
            'failed_requests' => 0,
            'file_accesses' => 0,
            'form_submissions' => 0,
            'admin_accesses' => 0,
            'suspicious_activities' => 0,
            'unique_ips' => [],
            'top_user_agents' => [],
            'daily_breakdown' => []
        ];
        
        if (!file_exists($this->accessLogFile)) {
            return $stats;
        }
        
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        $lines = file($this->accessLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data || !isset($data['timestamp'])) {
                continue;
            }
            
            $logTime = strtotime($data['timestamp']);
            if ($logTime < $cutoffTime) {
                continue;
            }
            
            $stats['total_requests']++;
            
            if (isset($data['success']) && !$data['success']) {
                $stats['failed_requests']++;
            }
            
            switch ($data['type'] ?? '') {
                case 'file_access':
                    $stats['file_accesses']++;
                    break;
                case 'form_submission':
                    $stats['form_submissions']++;
                    break;
                case 'admin_access':
                    $stats['admin_accesses']++;
                    break;
                case 'suspicious_activity':
                    $stats['suspicious_activities']++;
                    break;
            }
            
            // Track unique IPs
            if (isset($data['ip'])) {
                $stats['unique_ips'][$data['ip']] = true;
            }
            
            // Track user agents
            if (isset($data['user_agent'])) {
                $ua = $data['user_agent'];
                $stats['top_user_agents'][$ua] = ($stats['top_user_agents'][$ua] ?? 0) + 1;
            }
            
            // Daily breakdown
            $date = date('Y-m-d', $logTime);
            $stats['daily_breakdown'][$date] = ($stats['daily_breakdown'][$date] ?? 0) + 1;
        }
        
        $stats['unique_ips'] = count($stats['unique_ips']);
        arsort($stats['top_user_agents']);
        $stats['top_user_agents'] = array_slice($stats['top_user_agents'], 0, 10, true);
        
        return $stats;
    }
    
    /**
     * Get recent security events
     * @param int $limit
     * @return array
     */
    public function getRecentSecurityEvents($limit = 50) {
        $events = [];
        
        if (!file_exists($this->accessLogFile)) {
            return $events;
        }
        
        $lines = file($this->accessLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines); // Most recent first
        
        $count = 0;
        foreach ($lines as $line) {
            if ($count >= $limit) {
                break;
            }
            
            $data = json_decode($line, true);
            if (!$data) {
                continue;
            }
            
            // Only include security-relevant events
            if (in_array($data['type'] ?? '', ['suspicious_activity']) || 
                (isset($data['success']) && !$data['success'])) {
                $events[] = $data;
                $count++;
            }
        }
        
        return $events;
    }
    
    /**
     * Clean up old log files
     * @param int $daysToKeep
     * @return array
     */
    public function cleanupLogs($daysToKeep = 90) {
        $results = [
            'access_log_cleaned' => false,
            'security_log_cleaned' => false,
            'lines_removed' => 0
        ];
        
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        
        // Clean access log
        if (file_exists($this->accessLogFile)) {
            $results['lines_removed'] += $this->cleanLogFile($this->accessLogFile, $cutoffTime);
            $results['access_log_cleaned'] = true;
        }
        
        // Clean security log
        if (file_exists($this->logFile)) {
            $results['lines_removed'] += $this->cleanLogFile($this->logFile, $cutoffTime);
            $results['security_log_cleaned'] = true;
        }
        
        return $results;
    }
    
    /**
     * Clean individual log file
     * @param string $logFile
     * @param int $cutoffTime
     * @return int Lines removed
     */
    private function cleanLogFile($logFile, $cutoffTime) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $keptLines = [];
        $removedCount = 0;
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && isset($data['timestamp'])) {
                $logTime = strtotime($data['timestamp']);
                if ($logTime >= $cutoffTime) {
                    $keptLines[] = $line;
                } else {
                    $removedCount++;
                }
            } else {
                $keptLines[] = $line; // Keep malformed lines for investigation
            }
        }
        
        // Write back the kept lines
        file_put_contents($logFile, implode("\n", $keptLines) . "\n");
        
        return $removedCount;
    }
    
    /**
     * Write log entry
     * @param string $logFile
     * @param array $data
     */
    private function writeLog($logFile, $data) {
        $logEntry = json_encode($data) . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Monitor for brute force attacks
     * @param string $identifier
     * @param string $action
     * @return bool True if suspicious activity detected
     */
    public function detectBruteForce($identifier, $action) {
        $recentAttempts = $this->getRecentFailedAttempts($identifier, $action, 300); // 5 minutes
        
        if (count($recentAttempts) >= 5) {
            $this->logSuspiciousActivity('brute_force_detected', [
                'identifier' => $identifier,
                'action' => $action,
                'attempts_count' => count($recentAttempts)
            ], 'ERROR');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get recent failed attempts
     * @param string $identifier
     * @param string $action
     * @param int $timeWindow
     * @return array
     */
    private function getRecentFailedAttempts($identifier, $action, $timeWindow) {
        $attempts = [];
        
        if (!file_exists($this->accessLogFile)) {
            return $attempts;
        }
        
        $cutoffTime = time() - $timeWindow;
        $lines = file($this->accessLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data || !isset($data['timestamp'])) {
                continue;
            }
            
            $logTime = strtotime($data['timestamp']);
            if ($logTime < $cutoffTime) {
                continue;
            }
            
            if (isset($data['success']) && !$data['success'] &&
                isset($data['ip']) && $data['ip'] === $identifier &&
                isset($data['action']) && $data['action'] === $action) {
                $attempts[] = $data;
            }
        }
        
        return $attempts;
    }
}
?>