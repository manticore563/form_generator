<?php
/**
 * Export Cleanup Script
 * Cleans up expired export files - can be run via cron job
 */

// Initialize application
define('SEFP_INIT', true);
require_once '../config/config.php';
require_once 'SubmissionManager.php';

// Check if running from command line or authenticated admin
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        die('Unauthorized');
    }
}

try {
    $submissionManager = new SubmissionManager();
    $cleanedCount = $submissionManager->cleanupExpiredExports();
    
    $message = "Cleanup completed. Removed {$cleanedCount} expired export files.";
    
    if ($isCli) {
        echo $message . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'cleaned_count' => $cleanedCount
        ]);
    }
    
} catch (Exception $e) {
    $errorMessage = "Cleanup failed: " . $e->getMessage();
    error_log($errorMessage);
    
    if ($isCli) {
        echo $errorMessage . "\n";
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
    }
}
?>