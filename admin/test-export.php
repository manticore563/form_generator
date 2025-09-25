<?php
/**
 * Export System Test Script
 * Tests CSV export and file serving functionality
 */

// Initialize application
define('SEFP_INIT', true);
require_once '../config/config.php';
require_once 'SubmissionManager.php';
require_once 'FormManager.php';

// Check authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $action = $_GET['action'] ?? 'test';
    $formManager = new FormManager();
    $submissionManager = new SubmissionManager();
    
    switch ($action) {
        case 'test_export':
            $formId = $_GET['form_id'] ?? '';
            if (!$formId) {
                throw new Exception('Form ID required');
            }
            
            // Test export creation
            $exportResult = $submissionManager->exportToCSV($formId);
            
            if ($exportResult) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Export created successfully',
                    'export' => $exportResult
                ]);
            } else {
                throw new Exception('Export creation failed');
            }
            break;
            
        case 'list_exports':
            $formId = $_GET['form_id'] ?? '';
            if (!$formId) {
                throw new Exception('Form ID required');
            }
            
            $exports = $submissionManager->getActiveExports($formId);
            $stats = $submissionManager->getExportStats($formId);
            
            echo json_encode([
                'success' => true,
                'exports' => $exports,
                'stats' => $stats
            ]);
            break;
            
        case 'cleanup':
            $cleanedCount = $submissionManager->cleanupExpiredExports();
            
            echo json_encode([
                'success' => true,
                'message' => "Cleaned up {$cleanedCount} expired exports",
                'cleaned_count' => $cleanedCount
            ]);
            break;
            
        case 'test_file_url':
            $fileId = $_GET['file_id'] ?? '';
            if (!$fileId) {
                throw new Exception('File ID required');
            }
            
            require_once '../uploads/serve.php';
            $fileServer = new FileServer();
            $secureUrl = $fileServer->getSecureDownloadUrl($fileId);
            
            echo json_encode([
                'success' => true,
                'secure_url' => $secureUrl
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => true,
                'message' => 'Export system test endpoint',
                'available_actions' => [
                    'test_export' => 'Create test export (requires form_id)',
                    'list_exports' => 'List active exports (requires form_id)',
                    'cleanup' => 'Clean up expired exports',
                    'test_file_url' => 'Generate secure file URL (requires file_id)'
                ]
            ]);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>