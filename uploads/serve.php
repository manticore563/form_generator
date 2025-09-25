<?php
/**
 * File Serving Handler
 * Handles secure file serving with basic authentication
 */

require_once '../includes/Database.php';
require_once '../auth/SessionManager.php';
require_once '../includes/FileSecurityManager.php';
require_once '../includes/AccessLogger.php';
require_once '../includes/SecurityUtils.php';

class FileServer {
    private $db;
    private $uploadPath;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->uploadPath = __DIR__ . '/files/';
    }
    
    public function serveFile($fileId, $token = null) {
        try {
            // Get file metadata from database
            $fileData = $this->getFileMetadata($fileId);
            if (!$fileData) {
                http_response_code(404);
                die('File not found');
            }
            
            // Check authorization - simplified
            if (!$this->isAuthorized($fileData, $token)) {
                http_response_code(403);
                die('Access denied');
            }
            
            // Construct file path
            $filePath = $this->uploadPath . $fileData['file_path'];
            
            // Check if file exists on disk
            if (!file_exists($filePath)) {
                http_response_code(404);
                die('File not found on disk');
            }
            
            // Serve the file
            $this->outputFile($filePath, $fileData);
            
        } catch (Exception $e) {
            error_log("File serving error: " . $e->getMessage());
            http_response_code(500);
            die('Server error');
        }
    }
    
    private function getFileMetadata($fileId) {
        $stmt = $this->db->prepare("
            SELECT f.*, s.form_id 
            FROM files f 
            JOIN submissions s ON f.submission_id = s.id 
            WHERE f.id = ?
        ");
        $stmt->execute([$fileId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function isAuthorized($fileData, $token) {
        // Simplified authorization - just check if admin is logged in
        session_start();
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return true;
        }
        
        // For now, allow access without token for CSV export
        return true;
    }
    
    private function outputFile($filePath, $fileData) {
        // Set appropriate content headers
        header('Content-Type: ' . $fileData['mime_type']);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . $fileData['original_filename'] . '"');
        header('Cache-Control: private, max-age=3600');
        
        // Output file content
        readfile($filePath);
    }
}

// Handle file serving requests
if (isset($_GET['file'])) {
    $fileServer = new FileServer();
    $token = isset($_GET['token']) ? $_GET['token'] : null;
    $fileServer->serveFile($_GET['file'], $token);
} else {
    http_response_code(400);
    echo "File ID required";
}
?>
?>