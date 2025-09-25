<?php
require_once '../includes/functions.php';
require_once '../includes/Database.php';
require_once '../includes/ErrorLogger.php';

// Check if user is authenticated
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/');
    exit;
}

$errorLogger = new ErrorLogger();

/**
 * System Health Check Class
 */
class SystemHealthChecker {
    private $db;
    private $errorLogger;
    private $checks = [];
    
    public function __construct($database, $errorLogger) {
        $this->db = $database;
        $this->errorLogger = $errorLogger;
    }
    
    /**
     * Run all health checks
     */
    public function runAllChecks() {
        $this->checkDatabaseConnection();
        $this->checkFilePermissions();
        $this->checkDiskSpace();
        $this->checkPHPConfiguration();
        $this->checkDirectoryStructure();
        $this->checkLogFiles();
        $this->checkUploadFunctionality();
        $this->checkImageProcessing();
        $this->checkSecuritySettings();
        
        return $this->checks;
    }
    
    /**
     * Check database connection and basic queries
     */
    private function checkDatabaseConnection() {
        try {
            $pdo = $this->db->getConnection();
            
            // Test basic connection
            $stmt = $pdo->query("SELECT 1");
            $connectionOk = $stmt !== false;
            
            // Test table existence
            $tables = ['forms', 'form_fields', 'submissions', 'files', 'admin_users'];
            $tablesExist = true;
            $missingTables = [];
            
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if ($stmt->rowCount() === 0) {
                    $tablesExist = false;
                    $missingTables[] = $table;
                }
            }
            
            $this->checks['database'] = [
                'status' => $connectionOk && $tablesExist ? 'healthy' : 'error',
                'message' => $connectionOk && $tablesExist ? 'Database connection and tables OK' : 
                           (!$connectionOk ? 'Database connection failed' : 'Missing tables: ' . implode(', ', $missingTables)),
                'details' => [
                    'connection' => $connectionOk,
                    'tables_exist' => $tablesExist,
                    'missing_tables' => $missingTables
                ]
            ];
            
        } catch (Exception $e) {
            $this->checks['database'] = [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Check file permissions
     */
    private function checkFilePermissions() {
        $directories = [
            '../uploads/' => 'writable',
            '../uploads/files/' => 'writable',
            '../logs/' => 'writable',
            '../config/' => 'readable'
        ];
        
        $permissionIssues = [];
        $allOk = true;
        
        foreach ($directories as $dir => $requirement) {
            if (!is_dir($dir)) {
                $permissionIssues[] = "$dir does not exist";
                $allOk = false;
                continue;
            }
            
            if ($requirement === 'writable' && !is_writable($dir)) {
                $permissionIssues[] = "$dir is not writable";
                $allOk = false;
            } elseif ($requirement === 'readable' && !is_readable($dir)) {
                $permissionIssues[] = "$dir is not readable";
                $allOk = false;
            }
        }
        
        $this->checks['permissions'] = [
            'status' => $allOk ? 'healthy' : 'warning',
            'message' => $allOk ? 'File permissions OK' : 'Permission issues found',
            'details' => ['issues' => $permissionIssues]
        ];
    }
    
    /**
     * Check disk space
     */
    private function checkDiskSpace() {
        $freeBytes = disk_free_space('.');
        $totalBytes = disk_total_space('.');
        $usedPercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
        
        $status = 'healthy';
        if ($usedPercent > 90) {
            $status = 'error';
        } elseif ($usedPercent > 80) {
            $status = 'warning';
        }
        
        $this->checks['disk_space'] = [
            'status' => $status,
            'message' => sprintf('Disk usage: %.1f%% (%.2f GB free)', $usedPercent, $freeBytes / 1024 / 1024 / 1024),
            'details' => [
                'free_bytes' => $freeBytes,
                'total_bytes' => $totalBytes,
                'used_percent' => $usedPercent
            ]
        ];
    }
    
    /**
     * Check PHP configuration
     */
    private function checkPHPConfiguration() {
        $issues = [];
        $warnings = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $issues[] = 'PHP version ' . PHP_VERSION . ' is below minimum requirement (7.4.0)';
        }
        
        // Check required extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'session', 'filter', 'fileinfo'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $issues[] = "Required PHP extension '$ext' is not loaded";
            }
        }
        
        // Check configuration settings
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        $postMaxSize = ini_get('post_max_size');
        $memoryLimit = ini_get('memory_limit');
        
        if ($this->parseSize($uploadMaxFilesize) < 5 * 1024 * 1024) {
            $warnings[] = "upload_max_filesize ($uploadMaxFilesize) is below recommended 5M";
        }
        
        if ($this->parseSize($postMaxSize) < 10 * 1024 * 1024) {
            $warnings[] = "post_max_size ($postMaxSize) is below recommended 10M";
        }
        
        if ($this->parseSize($memoryLimit) < 128 * 1024 * 1024) {
            $warnings[] = "memory_limit ($memoryLimit) is below recommended 128M";
        }
        
        $status = 'healthy';
        if (!empty($issues)) {
            $status = 'error';
        } elseif (!empty($warnings)) {
            $status = 'warning';
        }
        
        $this->checks['php_config'] = [
            'status' => $status,
            'message' => empty($issues) && empty($warnings) ? 'PHP configuration OK' : 'PHP configuration issues found',
            'details' => [
                'php_version' => PHP_VERSION,
                'issues' => $issues,
                'warnings' => $warnings,
                'settings' => [
                    'upload_max_filesize' => $uploadMaxFilesize,
                    'post_max_size' => $postMaxSize,
                    'memory_limit' => $memoryLimit
                ]
            ]
        ];
    }
    
    /**
     * Check directory structure
     */
    private function checkDirectoryStructure() {
        $requiredDirs = [
            '../admin/',
            '../auth/',
            '../forms/',
            '../includes/',
            '../uploads/',
            '../uploads/files/',
            '../logs/',
            '../config/',
            '../assets/',
            '../assets/css/',
            '../assets/js/'
        ];
        
        $missingDirs = [];
        foreach ($requiredDirs as $dir) {
            if (!is_dir($dir)) {
                $missingDirs[] = $dir;
            }
        }
        
        $this->checks['directory_structure'] = [
            'status' => empty($missingDirs) ? 'healthy' : 'error',
            'message' => empty($missingDirs) ? 'Directory structure OK' : 'Missing directories found',
            'details' => ['missing_directories' => $missingDirs]
        ];
    }
    
    /**
     * Check log files and recent errors
     */
    private function checkLogFiles() {
        $logStats = $this->errorLogger->getLogStats();
        
        $status = 'healthy';
        $message = 'Log system OK';
        
        if ($logStats['recent_errors'] > 50) {
            $status = 'error';
            $message = 'High error rate detected';
        } elseif ($logStats['recent_errors'] > 10) {
            $status = 'warning';
            $message = 'Elevated error rate';
        }
        
        if ($logStats['recent_security_events'] > 0) {
            $status = 'warning';
            $message .= ', Security events detected';
        }
        
        $this->checks['logs'] = [
            'status' => $status,
            'message' => $message,
            'details' => $logStats
        ];
    }
    
    /**
     * Test upload functionality
     */
    private function checkUploadFunctionality() {
        $uploadDir = '../uploads/files/';
        $testFile = $uploadDir . 'health_check_test.txt';
        
        try {
            // Test write
            $testContent = 'Health check test - ' . date('Y-m-d H:i:s');
            $writeResult = file_put_contents($testFile, $testContent);
            
            if ($writeResult === false) {
                throw new Exception('Cannot write to upload directory');
            }
            
            // Test read
            $readContent = file_get_contents($testFile);
            if ($readContent !== $testContent) {
                throw new Exception('File content mismatch after write/read');
            }
            
            // Clean up
            unlink($testFile);
            
            $this->checks['upload_functionality'] = [
                'status' => 'healthy',
                'message' => 'Upload functionality OK',
                'details' => ['test_passed' => true]
            ];
            
        } catch (Exception $e) {
            $this->checks['upload_functionality'] = [
                'status' => 'error',
                'message' => 'Upload functionality failed: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
    
    /**
     * Test image processing
     */
    private function checkImageProcessing() {
        $gdAvailable = extension_loaded('gd');
        $imagickAvailable = extension_loaded('imagick');
        
        if (!$gdAvailable && !$imagickAvailable) {
            $this->checks['image_processing'] = [
                'status' => 'error',
                'message' => 'No image processing extension available (GD or Imagick required)',
                'details' => ['gd' => false, 'imagick' => false]
            ];
            return;
        }
        
        // Test basic image creation
        try {
            if ($gdAvailable) {
                $testImage = imagecreate(100, 100);
                if ($testImage === false) {
                    throw new Exception('Cannot create test image with GD');
                }
                imagedestroy($testImage);
            }
            
            $this->checks['image_processing'] = [
                'status' => 'healthy',
                'message' => 'Image processing OK',
                'details' => [
                    'gd' => $gdAvailable,
                    'imagick' => $imagickAvailable,
                    'test_passed' => true
                ]
            ];
            
        } catch (Exception $e) {
            $this->checks['image_processing'] = [
                'status' => 'warning',
                'message' => 'Image processing test failed: ' . $e->getMessage(),
                'details' => [
                    'gd' => $gdAvailable,
                    'imagick' => $imagickAvailable,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Check security settings
     */
    private function checkSecuritySettings() {
        $issues = [];
        $warnings = [];
        
        // Check if installer directory exists
        if (is_dir('../install/')) {
            $issues[] = 'Installer directory still exists - should be deleted after installation';
        }
        
        // Check session settings
        if (ini_get('session.cookie_httponly') != '1') {
            $warnings[] = 'session.cookie_httponly should be enabled';
        }
        
        if (ini_get('session.cookie_secure') != '1' && isset($_SERVER['HTTPS'])) {
            $warnings[] = 'session.cookie_secure should be enabled for HTTPS';
        }
        
        // Check if error display is disabled in production
        if (ini_get('display_errors') == '1') {
            $warnings[] = 'display_errors should be disabled in production';
        }
        
        $status = 'healthy';
        if (!empty($issues)) {
            $status = 'error';
        } elseif (!empty($warnings)) {
            $status = 'warning';
        }
        
        $this->checks['security'] = [
            'status' => $status,
            'message' => empty($issues) && empty($warnings) ? 'Security settings OK' : 'Security issues found',
            'details' => [
                'issues' => $issues,
                'warnings' => $warnings
            ]
        ];
    }
    
    /**
     * Parse size string to bytes
     */
    private function parseSize($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }
    
    /**
     * Get overall system status
     */
    public function getOverallStatus() {
        $errorCount = 0;
        $warningCount = 0;
        
        foreach ($this->checks as $check) {
            if ($check['status'] === 'error') {
                $errorCount++;
            } elseif ($check['status'] === 'warning') {
                $warningCount++;
            }
        }
        
        if ($errorCount > 0) {
            return 'error';
        } elseif ($warningCount > 0) {
            return 'warning';
        }
        
        return 'healthy';
    }
}

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    header('Content-Type: application/json');
    
    try {
        $db = new Database();
        $healthChecker = new SystemHealthChecker($db, $errorLogger);
        $checks = $healthChecker->runAllChecks();
        $overallStatus = $healthChecker->getOverallStatus();
        
        echo json_encode([
            'success' => true,
            'overall_status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Initialize health checker for display
$db = new Database();
$healthChecker = new SystemHealthChecker($db, $errorLogger);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Check - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .health-check-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .health-overview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .status-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .status-healthy { background-color: #28a745; }

        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        
        .health-checks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .health-check-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }
        
        .health-check-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .health-check-title {
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .health-check-details {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9em;
        }
        
        .refresh-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .refresh-button:hover {
            background: #0056b3;
        }
        
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .timestamp {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="health-check-container">
        <h1>System Health Check</h1>
        
        <button class="refresh-button" onclick="runHealthCheck()">
            <span id="refresh-text">Run Health Check</span>
        </button>
        
        <div class="health-overview" id="health-overview">
            <h2>System Status</h2>
            <div id="overall-status">
                <span class="status-indicator status-healthy"></span>
                <span>Checking system health...</span>
            </div>
            <div class="timestamp" id="last-check"></div>
        </div>
        
        <div class="health-checks" id="health-checks">
            <!-- Health check results will be populated here -->
        </div>
    </div>

    <script>
        function runHealthCheck() {
            const refreshButton = document.querySelector('.refresh-button');
            const refreshText = document.getElementById('refresh-text');
            const healthChecks = document.getElementById('health-checks');
            const overallStatus = document.getElementById('overall-status');
            const lastCheck = document.getElementById('last-check');
            
            // Show loading state
            refreshButton.classList.add('loading');
            refreshText.textContent = 'Checking...';
            healthChecks.innerHTML = '<p>Running health checks...</p>';
            
            fetch('?action=check')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayHealthResults(data);
                        lastCheck.textContent = `Last check: ${data.timestamp}`;
                    } else {
                        overallStatus.innerHTML = `
                            <span class="status-indicator status-error"></span>
                            <span>Health check failed: ${data.error}</span>
                        `;
                        healthChecks.innerHTML = '<p>Health check failed. Please try again.</p>';
                    }
                })
                .catch(error => {
                    overallStatus.innerHTML = `
                        <span class="status-indicator status-error"></span>
                        <span>Health check failed: ${error.message}</span>
                    `;
                    healthChecks.innerHTML = '<p>Health check failed. Please try again.</p>';
                })
                .finally(() => {
                    refreshButton.classList.remove('loading');
                    refreshText.textContent = 'Run Health Check';
                });
        }
        
        function displayHealthResults(data) {
            const overallStatus = document.getElementById('overall-status');
            const healthChecks = document.getElementById('health-checks');
            
            // Update overall status
            const statusClass = `status-${data.overall_status}`;
            const statusText = data.overall_status === 'healthy' ? 'System Healthy' :
                             data.overall_status === 'warning' ? 'System Issues Detected' :
                             'System Errors Detected';
            
            overallStatus.innerHTML = `
                <span class="status-indicator ${statusClass}"></span>
                <span>${statusText}</span>
            `;
            
            // Display individual checks
            let checksHtml = '';
            for (const [checkName, checkData] of Object.entries(data.checks)) {
                const statusClass = `status-${checkData.status}`;
                const checkTitle = checkName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                
                checksHtml += `
                    <div class="health-check-item">
                        <div class="health-check-header">
                            <span class="status-indicator ${statusClass}"></span>
                            <span class="health-check-title">${checkTitle}</span>
                        </div>
                        <div class="health-check-message">${checkData.message}</div>
                        ${checkData.details ? `
                            <div class="health-check-details">
                                <strong>Details:</strong><br>
                                ${formatDetails(checkData.details)}
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            healthChecks.innerHTML = checksHtml;
        }
        
        function formatDetails(details) {
            if (typeof details === 'string') {
                return details;
            }
            
            let html = '';
            for (const [key, value] of Object.entries(details)) {
                if (Array.isArray(value)) {
                    if (value.length > 0) {
                        html += `<strong>${key.replace(/_/g, ' ')}:</strong> ${value.join(', ')}<br>`;
                    }
                } else if (typeof value === 'object') {
                    html += `<strong>${key.replace(/_/g, ' ')}:</strong><br>`;
                    for (const [subKey, subValue] of Object.entries(value)) {
                        html += `&nbsp;&nbsp;${subKey}: ${subValue}<br>`;
                    }
                } else {
                    html += `<strong>${key.replace(/_/g, ' ')}:</strong> ${value}<br>`;
                }
            }
            
            return html;
        }
        
        // Run initial health check
        document.addEventListener('DOMContentLoaded', function() {
            runHealthCheck();
        });
    </script>
</body>
</html>