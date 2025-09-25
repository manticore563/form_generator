<?php
require_once __DIR__ . '/../includes/Database.php';

class FileManager {
    private $db;
    private $uploadPath;
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
    
    public function storeFile($file, $submissionId, $fieldName) {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
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
                throw new Exception('Failed to move uploaded file');
            }
            
            // Store file metadata in database
            $fileId = $this->storeFileMetadata($submissionId, $fieldName, $file, $secureFilename, $yearMonth);
            
            return $fileId;
            
        } catch (Exception $e) {
            error_log("File storage error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getFileUrl($fileId, $includeToken = false) {
        $url = '/uploads/serve.php?file=' . urlencode($fileId);
        if ($includeToken) {
            $token = $this->generateAccessToken($fileId);
            $url .= '&token=' . urlencode($token);
        }
        return $url;
    }
    
    public function deleteFile($fileId) {
        try {
            // Get file metadata
            $stmt = $this->db->prepare("SELECT * FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $fileData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$fileData) {
                return false;
            }
            
            // Delete physical file
            $filePath = $this->uploadPath . $fileData['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete database record
            $stmt = $this->db->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("File deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getFilesBySubmission($submissionId) {
        $stmt = $this->db->prepare("
            SELECT id, field_name, original_filename, file_size, mime_type, uploaded_at
            FROM files 
            WHERE submission_id = ?
            ORDER BY uploaded_at ASC
        ");
        $stmt->execute([$submissionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function cleanupOrphanedFiles() {
        try {
            // Find files older than 24 hours with no associated submission
            $stmt = $this->db->prepare("
                SELECT f.* FROM files f
                LEFT JOIN submissions s ON f.submission_id = s.id
                WHERE s.id IS NULL AND f.uploaded_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $orphanedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $cleanedCount = 0;
            foreach ($orphanedFiles as $file) {
                if ($this->deleteFile($file['id'])) {
                    $cleanedCount++;
                }
            }
            
            return $cleanedCount;
            
        } catch (Exception $e) {
            error_log("File cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function cleanupTempFiles() {
        try {
            // Clean up temporary files older than 1 hour
            $tempPath = $this->uploadPath . 'temp/';
            if (!is_dir($tempPath)) {
                return 0;
            }
            
            $cleanedCount = 0;
            $files = glob($tempPath . '*');
            $cutoffTime = time() - 3600; // 1 hour ago
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    if (unlink($file)) {
                        $cleanedCount++;
                    }
                }
            }
            
            return $cleanedCount;
            
        } catch (Exception $e) {
            error_log("Temp file cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function getFileMetadata($fileId) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, s.form_id, s.submitted_at as submission_date
                FROM files f
                LEFT JOIN submissions s ON f.submission_id = s.id
                WHERE f.id = ?
            ");
            $stmt->execute([$fileId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get file metadata error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getStorageStats() {
        try {
            $stats = [];
            
            // Total files count
            $stmt = $this->db->prepare("SELECT COUNT(*) as total_files FROM files");
            $stmt->execute();
            $stats['total_files'] = $stmt->fetchColumn();
            
            // Total storage size
            $stmt = $this->db->prepare("SELECT SUM(file_size) as total_size FROM files");
            $stmt->execute();
            $stats['total_size'] = $stmt->fetchColumn() ?: 0;
            
            // Files by type
            $stmt = $this->db->prepare("
                SELECT mime_type, COUNT(*) as count, SUM(file_size) as size
                FROM files 
                GROUP BY mime_type
                ORDER BY count DESC
            ");
            $stmt->execute();
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Files by month
            $stmt = $this->db->prepare("
                SELECT DATE_FORMAT(uploaded_at, '%Y-%m') as month, 
                       COUNT(*) as count, 
                       SUM(file_size) as size
                FROM files 
                WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(uploaded_at, '%Y-%m')
                ORDER BY month DESC
            ");
            $stmt->execute();
            $stats['by_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Orphaned files
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as orphaned_count
                FROM files f
                LEFT JOIN submissions s ON f.submission_id = s.id
                WHERE s.id IS NULL
            ");
            $stmt->execute();
            $stats['orphaned_files'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Storage stats error: " . $e->getMessage());
            return [];
        }
    }
    
    public function moveFile($fileId, $newPath) {
        try {
            $fileData = $this->getFileMetadata($fileId);
            if (!$fileData) {
                throw new Exception('File not found');
            }
            
            $currentPath = $this->uploadPath . $fileData['file_path'];
            $newFullPath = $this->uploadPath . $newPath;
            
            // Ensure destination directory exists
            $newDir = dirname($newFullPath);
            if (!is_dir($newDir)) {
                mkdir($newDir, 0755, true);
            }
            
            // Move file
            if (!rename($currentPath, $newFullPath)) {
                throw new Exception('Failed to move file');
            }
            
            // Update database
            $stmt = $this->db->prepare("UPDATE files SET file_path = ? WHERE id = ?");
            $stmt->execute([$newPath, $fileId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("File move error: " . $e->getMessage());
            return false;
        }
    }
    
    public function copyFile($fileId, $newSubmissionId, $newFieldName) {
        try {
            $fileData = $this->getFileMetadata($fileId);
            if (!$fileData) {
                throw new Exception('Source file not found');
            }
            
            $sourcePath = $this->uploadPath . $fileData['file_path'];
            if (!file_exists($sourcePath)) {
                throw new Exception('Source file does not exist on disk');
            }
            
            // Generate new file ID and path
            $newFileId = bin2hex(random_bytes(16));
            $pathInfo = pathinfo($fileData['stored_filename']);
            $newFilename = $pathInfo['filename'] . '_copy_' . substr($newFileId, 0, 8) . '.' . $pathInfo['extension'];
            
            $yearMonth = date('Y/m');
            $newRelativePath = $yearMonth . '/' . $newFilename;
            $newFullPath = $this->uploadPath . $newRelativePath;
            
            // Ensure destination directory exists
            $newDir = dirname($newFullPath);
            if (!is_dir($newDir)) {
                mkdir($newDir, 0755, true);
            }
            
            // Copy file
            if (!copy($sourcePath, $newFullPath)) {
                throw new Exception('Failed to copy file');
            }
            
            // Insert new file record
            $stmt = $this->db->prepare("
                INSERT INTO files (id, submission_id, field_name, original_filename, stored_filename, file_path, file_size, mime_type, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $newFileId,
                $newSubmissionId,
                $newFieldName,
                $fileData['original_filename'],
                $newFilename,
                $newRelativePath,
                filesize($newFullPath),
                $fileData['mime_type']
            ]);
            
            return $newFileId;
            
        } catch (Exception $e) {
            error_log("File copy error: " . $e->getMessage());
            return false;
        }
    }
    
    public function validateFileAccess($fileId, $userId = null) {
        try {
            $fileData = $this->getFileMetadata($fileId);
            if (!$fileData) {
                return false;
            }
            
            // Check if file exists on disk
            $filePath = $this->uploadPath . $fileData['file_path'];
            if (!file_exists($filePath)) {
                return false;
            }
            
            // Additional access control logic can be added here
            // For now, return true if file exists and has valid metadata
            return true;
            
        } catch (Exception $e) {
            error_log("File access validation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getFilesByForm($formId) {
        try {
            $stmt = $this->db->prepare("
                SELECT f.*, s.id as submission_id, s.submitted_at
                FROM files f
                JOIN submissions s ON f.submission_id = s.id
                WHERE s.form_id = ?
                ORDER BY s.submitted_at DESC, f.uploaded_at DESC
            ");
            $stmt->execute([$formId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get files by form error: " . $e->getMessage());
            return [];
        }
    }
    
    public function archiveOldFiles($daysOld = 365) {
        try {
            // Create archive directory
            $archivePath = $this->uploadPath . 'archive/';
            if (!is_dir($archivePath)) {
                mkdir($archivePath, 0755, true);
            }
            
            // Find old files
            $stmt = $this->db->prepare("
                SELECT f.* FROM files f
                JOIN submissions s ON f.submission_id = s.id
                WHERE s.submitted_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysOld]);
            $oldFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $archivedCount = 0;
            foreach ($oldFiles as $file) {
                $sourcePath = $this->uploadPath . $file['file_path'];
                $archiveFilePath = $archivePath . $file['stored_filename'];
                
                if (file_exists($sourcePath)) {
                    if (rename($sourcePath, $archiveFilePath)) {
                        // Update database with archive path
                        $stmt = $this->db->prepare("
                            UPDATE files SET file_path = ? WHERE id = ?
                        ");
                        $stmt->execute(['archive/' . $file['stored_filename'], $file['id']]);
                        $archivedCount++;
                    }
                }
            }
            
            return $archivedCount;
            
        } catch (Exception $e) {
            error_log("File archiving error: " . $e->getMessage());
            return 0;
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
    
    private function generateAccessToken($fileId) {
        return hash('sha256', $fileId . 'file_access_salt');
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
?>