<?php
/**
 * FileSecurityManager Class
 * Handles file security, malicious file detection, and access controls
 */

class FileSecurityManager {
    
    private static $instance = null;
    
    // Dangerous file extensions
    private static $dangerousExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht',
        'pl', 'py', 'jsp', 'asp', 'aspx', 'sh', 'cgi', 'exe', 'com',
        'bat', 'cmd', 'scr', 'vbs', 'js', 'jar', 'app', 'deb', 'rpm'
    ];
    
    // Dangerous MIME types
    private static $dangerousMimeTypes = [
        'application/x-php',
        'application/x-httpd-php',
        'application/php',
        'application/x-sh',
        'application/x-csh',
        'text/x-php',
        'text/x-shellscript',
        'application/x-executable',
        'application/x-msdownload',
        'application/x-msdos-program'
    ];
    
    // Magic bytes for common file types
    private static $fileMagicBytes = [
        'jpg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif' => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"],
        'pdf' => ["\x25\x50\x44\x46"],
        'zip' => ["\x50\x4B\x03\x04", "\x50\x4B\x05\x06", "\x50\x4B\x07\x08"]
    ];
    
    private function __construct() {}
    
    /**
     * Get singleton instance
     * @return FileSecurityManager
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Comprehensive file security scan
     * @param array $file $_FILES array element
     * @param array $allowedTypes
     * @return array
     */
    public function scanFile($file, $allowedTypes = []) {
        $results = [
            'safe' => true,
            'threats' => [],
            'warnings' => []
        ];
        
        // Check if file exists and is uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $results['safe'] = false;
            $results['threats'][] = 'File not properly uploaded';
            return $results;
        }
        
        // Check file extension
        $extensionCheck = $this->checkFileExtension($file['name']);
        if (!$extensionCheck['safe']) {
            $results['safe'] = false;
            $results['threats'] = array_merge($results['threats'], $extensionCheck['threats']);
        }
        
        // Check MIME type
        $mimeCheck = $this->checkMimeType($file['tmp_name']);
        if (!$mimeCheck['safe']) {
            $results['safe'] = false;
            $results['threats'] = array_merge($results['threats'], $mimeCheck['threats']);
        }
        
        // Check file content
        $contentCheck = $this->scanFileContent($file['tmp_name']);
        if (!$contentCheck['safe']) {
            $results['safe'] = false;
            $results['threats'] = array_merge($results['threats'], $contentCheck['threats']);
        }
        
        // Verify file magic bytes
        $magicCheck = $this->verifyFileMagicBytes($file['tmp_name'], $file['name']);
        if (!$magicCheck['safe']) {
            $results['warnings'] = array_merge($results['warnings'], $magicCheck['warnings']);
        }
        
        // Check for embedded executables
        $embeddedCheck = $this->checkEmbeddedExecutables($file['tmp_name']);
        if (!$embeddedCheck['safe']) {
            $results['safe'] = false;
            $results['threats'] = array_merge($results['threats'], $embeddedCheck['threats']);
        }
        
        // Log security scan results
        SecurityUtils::logSecurityEvent('file_security_scan', [
            'filename' => $file['name'],
            'size' => $file['size'],
            'mime_type' => $file['type'],
            'safe' => $results['safe'],
            'threats_count' => count($results['threats']),
            'warnings_count' => count($results['warnings'])
        ]);
        
        return $results;
    }
    
    /**
     * Check file extension for dangerous types
     * @param string $filename
     * @return array
     */
    private function checkFileExtension($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($extension, self::$dangerousExtensions)) {
            return [
                'safe' => false,
                'threats' => ["Dangerous file extension: {$extension}"]
            ];
        }
        
        // Check for double extensions (e.g., file.jpg.php)
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            foreach ($parts as $part) {
                if (in_array(strtolower($part), self::$dangerousExtensions)) {
                    return [
                        'safe' => false,
                        'threats' => ["Hidden dangerous extension detected: {$part}"]
                    ];
                }
            }
        }
        
        return ['safe' => true, 'threats' => []];
    }
    
    /**
     * Check MIME type
     * @param string $filePath
     * @return array
     */
    private function checkMimeType($filePath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        if (in_array($mimeType, self::$dangerousMimeTypes)) {
            return [
                'safe' => false,
                'threats' => ["Dangerous MIME type: {$mimeType}"]
            ];
        }
        
        return ['safe' => true, 'threats' => []];
    }
    
    /**
     * Scan file content for malicious patterns
     * @param string $filePath
     * @return array
     */
    private function scanFileContent($filePath) {
        $content = file_get_contents($filePath, false, null, 0, 8192); // Read first 8KB
        
        if ($content === false) {
            return [
                'safe' => false,
                'threats' => ['Unable to read file content']
            ];
        }
        
        $threats = [];
        
        // Check for PHP tags
        if (preg_match('/<\?php|<\?=|<\?|\?>/', $content)) {
            $threats[] = 'PHP code detected in file';
        }
        
        // Check for script tags
        if (preg_match('/<script\b[^>]*>.*?<\/script>/is', $content)) {
            $threats[] = 'JavaScript code detected in file';
        }
        
        // Check for executable signatures
        $executableSignatures = [
            "\x4D\x5A", // PE executable
            "\x7F\x45\x4C\x46", // ELF executable
            "\xFE\xED\xFA\xCE", // Mach-O executable (32-bit)
            "\xFE\xED\xFA\xCF", // Mach-O executable (64-bit)
        ];
        
        foreach ($executableSignatures as $signature) {
            if (strpos($content, $signature) === 0) {
                $threats[] = 'Executable file signature detected';
                break;
            }
        }
        
        // Check for suspicious strings
        $suspiciousPatterns = [
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/base64_decode\s*\(/i',
            '/file_get_contents\s*\(/i',
            '/curl_exec\s*\(/i'
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $threats[] = 'Suspicious code pattern detected';
                break;
            }
        }
        
        return [
            'safe' => empty($threats),
            'threats' => $threats
        ];
    }
    
    /**
     * Verify file magic bytes match extension
     * @param string $filePath
     * @param string $filename
     * @return array
     */
    private function verifyFileMagicBytes($filePath, $filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!isset(self::$fileMagicBytes[$extension])) {
            return ['safe' => true, 'warnings' => []]; // Unknown extension, can't verify
        }
        
        $fileHeader = file_get_contents($filePath, false, null, 0, 16);
        if ($fileHeader === false) {
            return [
                'safe' => false,
                'warnings' => ['Unable to read file header']
            ];
        }
        
        $expectedMagicBytes = self::$fileMagicBytes[$extension];
        $headerMatches = false;
        
        foreach ($expectedMagicBytes as $magicBytes) {
            if (strpos($fileHeader, $magicBytes) === 0) {
                $headerMatches = true;
                break;
            }
        }
        
        if (!$headerMatches) {
            return [
                'safe' => false,
                'warnings' => ["File header doesn't match extension: {$extension}"]
            ];
        }
        
        return ['safe' => true, 'warnings' => []];
    }
    
    /**
     * Check for embedded executables or archives
     * @param string $filePath
     * @return array
     */
    private function checkEmbeddedExecutables($filePath) {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [
                'safe' => false,
                'threats' => ['Unable to read file for embedded executable check']
            ];
        }
        
        $threats = [];
        
        // Check for embedded ZIP files (could contain executables)
        if (strpos($content, "PK\x03\x04") !== false) {
            $threats[] = 'Embedded ZIP archive detected';
        }
        
        // Check for embedded PE executables
        if (strpos($content, "MZ") !== false) {
            $threats[] = 'Embedded Windows executable detected';
        }
        
        // Check for embedded ELF executables
        if (strpos($content, "\x7F\x45\x4C\x46") !== false) {
            $threats[] = 'Embedded Linux executable detected';
        }
        
        return [
            'safe' => empty($threats),
            'threats' => $threats
        ];
    }
    
    /**
     * Create secure upload directory structure
     * @param string $baseDir
     * @return string
     */
    public function createSecureUploadDir($baseDir) {
        // Create directory structure: uploads/YYYY/MM/DD
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        
        $uploadDir = $baseDir . '/' . $year . '/' . $month . '/' . $day;
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            
            // Create .htaccess to prevent direct access
            $htaccessContent = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($uploadDir . '/.htaccess', $htaccessContent);
            
            // Create index.php to prevent directory listing
            $indexContent = "<?php\n// Access denied\nheader('HTTP/1.0 403 Forbidden');\nexit();\n?>";
            file_put_contents($uploadDir . '/index.php', $indexContent);
        }
        
        return $uploadDir;
    }
    
    /**
     * Quarantine suspicious file
     * @param string $filePath
     * @param string $reason
     * @return bool
     */
    public function quarantineFile($filePath, $reason) {
        $quarantineDir = __DIR__ . '/../quarantine';
        
        if (!is_dir($quarantineDir)) {
            mkdir($quarantineDir, 0700, true);
            
            // Create .htaccess to prevent any access
            $htaccessContent = "Order Deny,Allow\nDeny from all\n";
            file_put_contents($quarantineDir . '/.htaccess', $htaccessContent);
        }
        
        $quarantineFile = $quarantineDir . '/' . date('Y-m-d_H-i-s') . '_' . basename($filePath);
        
        if (move_uploaded_file($filePath, $quarantineFile)) {
            SecurityUtils::logSecurityEvent('file_quarantined', [
                'original_path' => $filePath,
                'quarantine_path' => $quarantineFile,
                'reason' => $reason
            ], 'ERROR');
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate file access permissions
     * @param string $fileId
     * @param string $submissionId
     * @param string $userContext
     * @return bool
     */
    public function validateFileAccess($fileId, $submissionId = null, $userContext = 'public') {
        // For admin users, allow access to all files
        if ($userContext === 'admin' && isAuthenticated()) {
            return true;
        }
        
        // For public access, validate that the file belongs to the submission
        if ($submissionId) {
            $db = Database::getInstance();
            $query = "SELECT id FROM files WHERE id = ? AND submission_id = ?";
            $result = $db->query($query, [$fileId, $submissionId]);
            
            return $result && $result->rowCount() > 0;
        }
        
        // Log unauthorized access attempt
        SecurityUtils::logSecurityEvent('unauthorized_file_access_attempt', [
            'file_id' => $fileId,
            'submission_id' => $submissionId,
            'user_context' => $userContext,
            'ip' => SecurityUtils::getClientIP()
        ], 'WARNING');
        
        return false;
    }
    
    /**
     * Clean up old files and quarantine
     * @param int $daysOld
     * @return array
     */
    public function cleanupOldFiles($daysOld = 30) {
        $results = [
            'files_deleted' => 0,
            'quarantine_cleaned' => 0,
            'errors' => []
        ];
        
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        
        // Clean up quarantine directory
        $quarantineDir = __DIR__ . '/../quarantine';
        if (is_dir($quarantineDir)) {
            $files = glob($quarantineDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $results['quarantine_cleaned']++;
                    } else {
                        $results['errors'][] = "Failed to delete quarantine file: {$file}";
                    }
                }
            }
        }
        
        // Clean up orphaned upload files
        $uploadsDir = __DIR__ . '/../uploads';
        if (is_dir($uploadsDir)) {
            $this->cleanupDirectory($uploadsDir, $cutoffTime, $results);
        }
        
        SecurityUtils::logSecurityEvent('file_cleanup_completed', $results);
        
        return $results;
    }
    
    /**
     * Recursively clean up directory
     * @param string $dir
     * @param int $cutoffTime
     * @param array &$results
     */
    private function cleanupDirectory($dir, $cutoffTime, &$results) {
        $files = glob($dir . '/*');
        
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->cleanupDirectory($file, $cutoffTime, $results);
            } elseif (is_file($file) && filemtime($file) < $cutoffTime) {
                // Check if file is referenced in database
                $filename = basename($file);
                $db = Database::getInstance();
                $query = "SELECT id FROM files WHERE stored_filename = ?";
                $result = $db->query($query, [$filename]);
                
                if (!$result || $result->rowCount() === 0) {
                    // File not in database, safe to delete
                    if (unlink($file)) {
                        $results['files_deleted']++;
                    } else {
                        $results['errors'][] = "Failed to delete orphaned file: {$file}";
                    }
                }
            }
        }
    }
}
?>