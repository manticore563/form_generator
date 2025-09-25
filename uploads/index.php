<?php
require_once '../includes/Database.php';

// Simple session check for file uploads
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die('Unauthorized');
}

class FileUploadHandler {
    private $db;
    private $allowedTypes = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    private $maxFileSize = 5242880; // 5MB
    private $uploadPath;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->uploadPath = __DIR__ . '/files/';
        $this->ensureUploadDirectory();
    }
    
    private function ensureUploadDirectory() {
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
        
        // Create organized subdirectories by year/month
        $yearMonth = date('Y/m');
        $fullPath = $this->uploadPath . $yearMonth;
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }
        
        // Create .htaccess to prevent direct access
        $htaccessPath = $this->uploadPath . '.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, "Deny from all\n");
        }
    }
    
    public function handleUpload($submissionId, $fieldName, $file) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }
            
            // Generate secure filename
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $secureFilename = $this->generateSecureFilename($fileExtension);
            
            // Create organized storage path
            $yearMonth = date('Y/m');
            $storagePath = $this->uploadPath . $yearMonth . '/';
            $fullPath = $storagePath . $secureFilename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                return ['success' => false, 'error' => 'Failed to move uploaded file'];
            }
            
            // Store file metadata in database
            $fileId = $this->storeFileMetadata($submissionId, $fieldName, $file, $secureFilename, $yearMonth);
            
            return [
                'success' => true,
                'fileId' => $fileId,
                'filename' => $secureFilename,
                'path' => $yearMonth . '/' . $secureFilename
            ];
            
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Upload failed due to server error'];
        }
    }
    
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error: ' . $this->getUploadErrorMessage($file['error'])];
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowed size of 5MB'];
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'File type not allowed. Allowed types: images, PDF, Word documents'];
        }
        
        // Additional security checks for images
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return ['valid' => false, 'error' => 'Invalid image file'];
            }
        }
        
        return ['valid' => true];
    }
    
    private function generateSecureFilename($extension) {
        return bin2hex(random_bytes(16)) . '.' . strtolower($extension);
    }
    
    private function storeFileMetadata($submissionId, $fieldName, $file, $secureFilename, $yearMonth) {
        $fileId = bin2hex(random_bytes(16));
        
        $stmt = $this->db->prepare("
            INSERT INTO files (id, submission_id, field_name, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $filePath = $yearMonth . '/' . $secureFilename;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $stmt->execute([
            $fileId,
            $submissionId,
            $fieldName,
            $file['name'],
            $secureFilename,
            $filePath,
            $file['size'],
            $mimeType
        ]);
        
        return $fileId;
    }
    
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File size exceeds maximum allowed size';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }
}

// Handle AJAX upload requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    header('Content-Type: application/json');
    
    // Validate required parameters
    if (!isset($_POST['submission_id']) || !isset($_POST['field_name']) || !isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }
    
    $handler = new FileUploadHandler();
    $result = $handler->handleUpload($_POST['submission_id'], $_POST['field_name'], $_FILES['file']);
    
    echo json_encode($result);
    exit;
}

// Prevent direct access
http_response_code(403);
echo "Access denied";
?>