<?php
require_once 'FileManager.php';
require_once '../includes/Database.php';

header('Content-Type: application/json');

// Check authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$fileManager = new FileManager();

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($action, $fileManager);
            break;
        case 'POST':
            handlePostRequest($action, $fileManager);
            break;
        case 'DELETE':
            handleDeleteRequest($action, $fileManager);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("File API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGetRequest($action, $fileManager) {
    switch ($action) {
        case 'stats':
            $stats = $fileManager->getStorageStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'files':
            $submissionId = $_GET['submission_id'] ?? null;
            $formId = $_GET['form_id'] ?? null;
            
            if ($submissionId) {
                $files = $fileManager->getFilesBySubmission($submissionId);
            } elseif ($formId) {
                $files = $fileManager->getFilesByForm($formId);
            } else {
                throw new Exception('Missing submission_id or form_id parameter');
            }
            
            echo json_encode(['success' => true, 'data' => $files]);
            break;
            
        case 'metadata':
            $fileId = $_GET['file_id'] ?? null;
            if (!$fileId) {
                throw new Exception('Missing file_id parameter');
            }
            
            $metadata = $fileManager->getFileMetadata($fileId);
            if (!$metadata) {
                http_response_code(404);
                echo json_encode(['error' => 'File not found']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $metadata]);
            break;
            
        case 'download':
            $fileId = $_GET['file_id'] ?? null;
            if (!$fileId) {
                throw new Exception('Missing file_id parameter');
            }
            
            // Redirect to serve.php for actual file serving
            $includeToken = isset($_GET['token']) && $_GET['token'] === 'true';
            $url = $fileManager->getFileUrl($fileId, $includeToken);
            
            echo json_encode(['success' => true, 'download_url' => $url]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handlePostRequest($action, $fileManager) {
    switch ($action) {
        case 'cleanup':
            require_once 'cleanup.php';
            
            $options = [
                'dry_run' => isset($_POST['dry_run']) && $_POST['dry_run'] === 'true',
                'verbose' => true,
                'archive_days' => (int)($_POST['archive_days'] ?? 365)
            ];
            
            define('CLEANUP_SCRIPT', true);
            $cleanup = new FileCleanupService($options);
            $results = $cleanup->runCleanup();
            
            echo json_encode(['success' => true, 'data' => $results]);
            break;
            
        case 'move':
            $fileId = $_POST['file_id'] ?? null;
            $newPath = $_POST['new_path'] ?? null;
            
            if (!$fileId || !$newPath) {
                throw new Exception('Missing file_id or new_path parameter');
            }
            
            $success = $fileManager->moveFile($fileId, $newPath);
            echo json_encode(['success' => $success]);
            break;
            
        case 'copy':
            $fileId = $_POST['file_id'] ?? null;
            $newSubmissionId = $_POST['new_submission_id'] ?? null;
            $newFieldName = $_POST['new_field_name'] ?? null;
            
            if (!$fileId || !$newSubmissionId || !$newFieldName) {
                throw new Exception('Missing required parameters');
            }
            
            $newFileId = $fileManager->copyFile($fileId, $newSubmissionId, $newFieldName);
            if ($newFileId) {
                echo json_encode(['success' => true, 'new_file_id' => $newFileId]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to copy file']);
            }
            break;
            
        case 'validate':
            $fileId = $_POST['file_id'] ?? null;
            if (!$fileId) {
                throw new Exception('Missing file_id parameter');
            }
            
            $isValid = $fileManager->validateFileAccess($fileId);
            echo json_encode(['success' => true, 'valid' => $isValid]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDeleteRequest($action, $fileManager) {
    switch ($action) {
        case 'file':
            // Get file ID from URL path or query parameter
            $fileId = $_GET['file_id'] ?? null;
            if (!$fileId) {
                // Try to get from request body
                $input = json_decode(file_get_contents('php://input'), true);
                $fileId = $input['file_id'] ?? null;
            }
            
            if (!$fileId) {
                throw new Exception('Missing file_id parameter');
            }
            
            $success = $fileManager->deleteFile($fileId);
            echo json_encode(['success' => $success]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>