<?php

class ErrorLogger {
    private $logPath;
    private $maxLogSize;
    private $rotateCount;
    
    public function __construct($logPath = 'logs/', $maxLogSize = 10485760, $rotateCount = 5) {
        $this->logPath = rtrim($logPath, '/') . '/';
        $this->maxLogSize = $maxLogSize; // 10MB default
        $this->rotateCount = $rotateCount;
        
        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    /**
     * Log error with specified level
     */
    public function logError($level, $message, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ];
        
        $this->writeLog('error.log', $logEntry);
        
        // Also log to PHP error log for critical errors
        if (in_array(strtolower($level), ['critical', 'emergency', 'alert'])) {
            error_log("[$level] $message - " . json_encode($context));
        }
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event, $details) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id() ?? 'none'
        ];
        
        $this->writeLog('security.log', $logEntry);
        
        // Alert for critical security events
        if (in_array($event, ['brute_force_attempt', 'unauthorized_access', 'malicious_file_upload'])) {
            $this->logError('critical', "Security event: $event", $details);
        }
    }
    
    /**
     * Log file operations
     */
    public function logFileOperation($operation, $result, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'result' => $result ? 'success' : 'failure',
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $this->writeLog('file_operations.log', $logEntry);
        
        if (!$result) {
            $this->logError('warning', "File operation failed: $operation", $details);
        }
    }
    
    /**
     * Log database operations
     */
    public function logDatabaseOperation($operation, $result, $query = '', $error = '') {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'operation' => $operation,
            'result' => $result ? 'success' : 'failure',
            'query' => $this->sanitizeQuery($query),
            'error' => $error
        ];
        
        $this->writeLog('database.log', $logEntry);
        
        if (!$result) {
            $this->logError('error', "Database operation failed: $operation", [
                'query' => $this->sanitizeQuery($query),
                'error' => $error
            ]);
        }
    }
    
    /**
     * Log access attempts
     */
    public function logAccess($resource, $granted, $user = null) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'resource' => $resource,
            'access_granted' => $granted,
            'user' => $user,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $this->writeLog('access.log', $logEntry);
        
        if (!$granted) {
            $this->logSecurityEvent('unauthorized_access', [
                'resource' => $resource,
                'user' => $user
            ]);
        }
    }
    
    /**
     * Write log entry to file
     */
    private function writeLog($filename, $logEntry) {
        $logFile = $this->logPath . $filename;
        
        // Rotate log if it's too large
        if (file_exists($logFile) && filesize($logFile) > $this->maxLogSize) {
            $this->rotateLog($logFile);
        }
        
        $logLine = json_encode($logEntry) . "\n";
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotate log files
     */
    private function rotateLog($logFile) {
        $pathInfo = pathinfo($logFile);
        $baseName = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        $directory = $pathInfo['dirname'];
        
        // Remove oldest log
        $oldestLog = $directory . '/' . $baseName . '.' . $this->rotateCount . ($extension ? '.' . $extension : '');
        if (file_exists($oldestLog)) {
            unlink($oldestLog);
        }
        
        // Rotate existing logs
        for ($i = $this->rotateCount - 1; $i >= 1; $i--) {
            $currentLog = $directory . '/' . $baseName . '.' . $i . ($extension ? '.' . $extension : '');
            $nextLog = $directory . '/' . $baseName . '.' . ($i + 1) . ($extension ? '.' . $extension : '');
            
            if (file_exists($currentLog)) {
                rename($currentLog, $nextLog);
            }
        }
        
        // Move current log to .1
        $firstRotated = $directory . '/' . $baseName . '.1' . ($extension ? '.' . $extension : '');
        rename($logFile, $firstRotated);
    }
    
    /**
     * Sanitize SQL query for logging
     */
    private function sanitizeQuery($query) {
        // Remove sensitive data patterns
        $query = preg_replace('/password\s*=\s*[\'"][^\'"]*[\'"]/i', 'password=***', $query);
        $query = preg_replace('/VALUES\s*\([^)]*\)/i', 'VALUES(...)', $query);
        return substr($query, 0, 500); // Limit length
    }
    
    /**
     * Get recent log entries
     */
    public function getRecentLogs($filename, $lines = 100) {
        $logFile = $this->logPath . $filename;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $logs = [];
        $handle = fopen($logFile, 'r');
        
        if ($handle) {
            // Read from end of file
            fseek($handle, -1, SEEK_END);
            $lineCount = 0;
            $pos = ftell($handle);
            $line = '';
            
            while ($pos >= 0 && $lineCount < $lines) {
                fseek($handle, $pos);
                $char = fgetc($handle);
                
                if ($char === "\n" || $pos === 0) {
                    if ($line !== '') {
                        $logData = json_decode(strrev($line), true);
                        if ($logData) {
                            array_unshift($logs, $logData);
                        }
                        $lineCount++;
                    }
                    $line = '';
                } else {
                    $line .= $char;
                }
                $pos--;
            }
            
            fclose($handle);
        }
        
        return $logs;
    }
    
    /**
     * Clean old log files
     */
    public function cleanOldLogs($daysToKeep = 30) {
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        $logFiles = glob($this->logPath . '*.log*');
        
        foreach ($logFiles as $logFile) {
            if (filemtime($logFile) < $cutoffTime) {
                unlink($logFile);
            }
        }
    }
    
    /**
     * Get log statistics
     */
    public function getLogStats() {
        $stats = [
            'total_size' => 0,
            'file_count' => 0,
            'recent_errors' => 0,
            'recent_security_events' => 0
        ];
        
        $logFiles = glob($this->logPath . '*.log');
        
        foreach ($logFiles as $logFile) {
            $stats['total_size'] += filesize($logFile);
            $stats['file_count']++;
        }
        
        // Count recent errors (last 24 hours)
        $recentErrors = $this->getRecentLogs('error.log', 1000);
        $yesterday = time() - 86400;
        
        foreach ($recentErrors as $error) {
            $errorTime = strtotime($error['timestamp']);
            if ($errorTime > $yesterday) {
                $stats['recent_errors']++;
            }
        }
        
        // Count recent security events
        $recentSecurity = $this->getRecentLogs('security.log', 1000);
        foreach ($recentSecurity as $event) {
            $eventTime = strtotime($event['timestamp']);
            if ($eventTime > $yesterday) {
                $stats['recent_security_events']++;
            }
        }
        
        return $stats;
    }
}