<?php
/**
 * File Cleanup Script
 * 
 * This script should be run periodically (e.g., daily via cron job) to:
 * - Remove orphaned files
 * - Clean up temporary files
 * - Archive old files
 * - Generate cleanup reports
 * 
 * Usage:
 * php cleanup.php [--dry-run] [--verbose] [--archive-days=365]
 */

require_once 'FileManager.php';
require_once '../includes/Database.php';

class FileCleanupService {
    private $fileManager;
    private $dryRun = false;
    private $verbose = false;
    private $archiveDays = 365;
    
    public function __construct($options = []) {
        $this->fileManager = new FileManager();
        $this->dryRun = $options['dry_run'] ?? false;
        $this->verbose = $options['verbose'] ?? false;
        $this->archiveDays = $options['archive_days'] ?? 365;
    }
    
    public function runCleanup() {
        $this->log("Starting file cleanup process...");
        $startTime = microtime(true);
        
        $results = [
            'orphaned_files' => 0,
            'temp_files' => 0,
            'archived_files' => 0,
            'errors' => []
        ];
        
        try {
            // Clean up orphaned files
            $this->log("Cleaning up orphaned files...");
            if (!$this->dryRun) {
                $results['orphaned_files'] = $this->fileManager->cleanupOrphanedFiles();
            } else {
                $results['orphaned_files'] = $this->countOrphanedFiles();
            }
            $this->log("Orphaned files processed: " . $results['orphaned_files']);
            
            // Clean up temporary files
            $this->log("Cleaning up temporary files...");
            if (!$this->dryRun) {
                $results['temp_files'] = $this->fileManager->cleanupTempFiles();
            } else {
                $results['temp_files'] = $this->countTempFiles();
            }
            $this->log("Temporary files processed: " . $results['temp_files']);
            
            // Archive old files
            if ($this->archiveDays > 0) {
                $this->log("Archiving files older than {$this->archiveDays} days...");
                if (!$this->dryRun) {
                    $results['archived_files'] = $this->fileManager->archiveOldFiles($this->archiveDays);
                } else {
                    $results['archived_files'] = $this->countOldFiles();
                }
                $this->log("Files archived: " . $results['archived_files']);
            }
            
            // Generate storage statistics
            $this->generateStorageReport();
            
        } catch (Exception $e) {
            $error = "Cleanup error: " . $e->getMessage();
            $results['errors'][] = $error;
            $this->log($error, 'ERROR');
            error_log($error);
        }
        
        $duration = round(microtime(true) - $startTime, 2);
        $this->log("Cleanup completed in {$duration} seconds");
        
        return $results;
    }
    
    private function countOrphanedFiles() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM files f
                LEFT JOIN submissions s ON f.submission_id = s.id
                WHERE s.id IS NULL AND f.uploaded_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function countTempFiles() {
        $tempPath = __DIR__ . '/files/temp/';
        if (!is_dir($tempPath)) {
            return 0;
        }
        
        $count = 0;
        $files = glob($tempPath . '*');
        $cutoffTime = time() - 3600; // 1 hour ago
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                $count++;
            }
        }
        
        return $count;
    }
    
    private function countOldFiles() {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM files f
                JOIN submissions s ON f.submission_id = s.id
                WHERE s.submitted_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$this->archiveDays]);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function generateStorageReport() {
        $this->log("Generating storage report...");
        
        $stats = $this->fileManager->getStorageStats();
        
        $this->log("=== STORAGE STATISTICS ===");
        $this->log("Total files: " . number_format($stats['total_files'] ?? 0));
        $this->log("Total size: " . $this->formatBytes($stats['total_size'] ?? 0));
        $this->log("Orphaned files: " . number_format($stats['orphaned_files'] ?? 0));
        
        if (!empty($stats['by_type'])) {
            $this->log("Files by type:");
            foreach ($stats['by_type'] as $type) {
                $this->log("  {$type['mime_type']}: {$type['count']} files ({$this->formatBytes($type['size'])})");
            }
        }
        
        $this->log("=== END REPORT ===");
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}";
        
        if ($this->verbose || $level === 'ERROR') {
            echo $logMessage . PHP_EOL;
        }
        
        // Log to file
        $logFile = __DIR__ . '/logs/cleanup.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $options = [];
    
    // Parse command line arguments
    for ($i = 1; $i < $argc; $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($arg === '--verbose') {
            $options['verbose'] = true;
        } elseif (strpos($arg, '--archive-days=') === 0) {
            $options['archive_days'] = (int)substr($arg, 15);
        } elseif ($arg === '--help') {
            echo "File Cleanup Script\n";
            echo "Usage: php cleanup.php [options]\n";
            echo "Options:\n";
            echo "  --dry-run          Show what would be cleaned without actually doing it\n";
            echo "  --verbose          Show detailed output\n";
            echo "  --archive-days=N   Archive files older than N days (default: 365)\n";
            echo "  --help             Show this help message\n";
            exit(0);
        }
    }
    
    $cleanup = new FileCleanupService($options);
    $results = $cleanup->runCleanup();
    
    // Exit with error code if there were errors
    exit(empty($results['errors']) ? 0 : 1);
}

// Web interface (for manual cleanup)
if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    header('Content-Type: application/json');
    
    // Basic authentication check (should be enhanced for production)
    session_start();
    if (!isset($_SESSION['admin_logged_in'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $options = [
        'dry_run' => isset($_GET['dry_run']),
        'verbose' => true,
        'archive_days' => (int)($_GET['archive_days'] ?? 365)
    ];
    
    $cleanup = new FileCleanupService($options);
    $results = $cleanup->runCleanup();
    
    echo json_encode($results);
    exit;
}

// Prevent direct web access
if (!defined('CLEANUP_SCRIPT')) {
    http_response_code(403);
    echo "Direct access not allowed";
}
?>