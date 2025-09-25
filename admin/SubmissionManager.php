<?php
/**
 * SubmissionManager Class
 * Handles submission viewing, management, and export operations
 */

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

class SubmissionManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get submissions for a form with filtering and pagination
     * @param string $formId
     * @param array $filters
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getSubmissions($formId, $filters = [], $page = 1, $perPage = 20) {
        try {
            $offset = ($page - 1) * $perPage;
            $whereConditions = ['form_id = ?'];
            $params = [$formId];
            
            // Add search filter
            if (!empty($filters['search'])) {
                $whereConditions[] = 'submission_data LIKE ?';
                $params[] = '%' . $filters['search'] . '%';
            }
            
            // Add date range filter
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'submitted_at >= ?';
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'submitted_at <= ?';
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM submissions WHERE $whereClause";
            $countStmt = $this->db->query($countQuery, $params);
            $totalCount = $countStmt ? $countStmt->fetch()['total'] : 0;
            
            // Get submissions
            $query = "SELECT id, submission_data, submitted_at, ip_address 
                     FROM submissions 
                     WHERE $whereClause 
                     ORDER BY submitted_at DESC 
                     LIMIT $perPage OFFSET $offset";
            
            $stmt = $this->db->query($query, $params);
            $submissions = [];
            
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    $row['submission_data'] = json_decode($row['submission_data'], true);
                    $submissions[] = $row;
                }
            }
            
            return [
                'submissions' => $submissions,
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalCount / $perPage)
            ];
        } catch (Exception $e) {
            error_log("SubmissionManager::getSubmissions - " . $e->getMessage());
            return [
                'submissions' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Get detailed submission by ID
     * @param string $submissionId
     * @return array|false
     */
    public function getSubmissionDetails($submissionId) {
        try {
            $query = "SELECT s.*, f.title as form_title, f.config as form_config
                     FROM submissions s
                     JOIN forms f ON s.form_id = f.id
                     WHERE s.id = ?";
            
            $stmt = $this->db->query($query, [$submissionId]);
            
            if ($stmt && $row = $stmt->fetch()) {
                $row['submission_data'] = json_decode($row['submission_data'], true);
                $row['form_config'] = json_decode($row['form_config'], true);
                
                // Get associated files
                $row['files'] = $this->getSubmissionFiles($submissionId);
                
                return $row;
            }
            return false;
        } catch (Exception $e) {
            error_log("SubmissionManager::getSubmissionDetails - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get files associated with a submission
     * @param string $submissionId
     * @return array
     */
    public function getSubmissionFiles($submissionId) {
        try {
            $query = "SELECT * FROM files WHERE submission_id = ? ORDER BY field_name, uploaded_at";
            $stmt = $this->db->query($query, [$submissionId]);
            
            if ($stmt) {
                return $stmt->fetchAll();
            }
            return [];
        } catch (Exception $e) {
            error_log("SubmissionManager::getSubmissionFiles - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a submission and associated files
     * @param string $submissionId
     * @return bool
     */
    public function deleteSubmission($submissionId) {
        try {
            $this->db->beginTransaction();
            
            // Get files to delete from filesystem
            $files = $this->getSubmissionFiles($submissionId);
            
            // Delete submission (cascade will handle files table)
            $query = "DELETE FROM submissions WHERE id = ?";
            $result = $this->db->query($query, [$submissionId]);
            
            if ($result) {
                // Delete physical files
                foreach ($files as $file) {
                    if (file_exists($file['file_path'])) {
                        unlink($file['file_path']);
                    }
                }
                
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("SubmissionManager::deleteSubmission - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete multiple submissions
     * @param array $submissionIds
     * @return array Results array with success/failure counts
     */
    public function deleteMultipleSubmissions($submissionIds) {
        $results = ['success' => 0, 'failed' => 0];
        
        foreach ($submissionIds as $submissionId) {
            if ($this->deleteSubmission($submissionId)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Export submissions to CSV format
     * @param string $formId
     * @param array $filters
     * @return array|false Export result with file info on success, false on failure
     */
    public function exportToCSV($formId, $filters = []) {
        try {
            error_log("Starting CSV export for form: $formId");
            
            // Get form configuration
            require_once __DIR__ . '/FormManager.php';
            $formManager = new FormManager();
            $form = $formManager->getFormConfig($formId);
            
            if (!$form) {
                error_log("Form not found: $formId");
                return false;
            }
            
            error_log("Form found: " . $form['title']);
            
            // Get all submissions (no pagination for export)
            $allSubmissions = $this->getAllSubmissions($formId, $filters);
            error_log("Found " . count($allSubmissions) . " submissions");
            
            // Create CSV file even if no submissions (empty CSV with headers)
            $sanitizedTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $form['title']);
            $filename = 'submissions_' . $sanitizedTitle . '_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = __DIR__ . '/../uploads/exports/' . $filename;
            
            // Ensure export directory exists
            $exportDir = dirname($filepath);
            if (!is_dir($exportDir)) {
                if (!mkdir($exportDir, 0755, true)) {
                    error_log("Failed to create export directory: $exportDir");
                    return false;
                }
                error_log("Created export directory: $exportDir");
            }
            
            $file = fopen($filepath, 'w');
            if (!$file) {
                error_log("Failed to create CSV file: $filepath");
                return false;
            }
            
            // Build CSV headers
            $headers = ['Submission ID', 'Submitted At', 'IP Address'];
            $fieldHeaders = [];
            $fileFieldHeaders = [];
            
            if (isset($form['config']['fields']) && is_array($form['config']['fields'])) {
                foreach ($form['config']['fields'] as $field) {
                    $fieldHeaders[] = $field['label'] ?? $field['name'] ?? 'Unknown Field';
                    
                    // Add file URL columns for file fields
                    if (in_array($field['type'] ?? '', ['file', 'photo', 'signature'])) {
                        $fileFieldHeaders[] = ($field['label'] ?? $field['name'] ?? 'Unknown Field') . ' - File URL';
                    }
                }
            }
            
            $allHeaders = array_merge($headers, $fieldHeaders, $fileFieldHeaders);
            fputcsv($file, $allHeaders);
            
            // Write data rows if submissions exist
            if (!empty($allSubmissions)) {
                foreach ($allSubmissions as $submission) {
                    $row = [
                        $submission['id'],
                        $submission['submitted_at'],
                        $submission['ip_address'] ?? 'Unknown'
                    ];
                    
                    // Add field data
                    if (isset($form['config']['fields']) && is_array($form['config']['fields'])) {
                        foreach ($form['config']['fields'] as $field) {
                            $fieldId = $field['id'] ?? $field['name'] ?? '';
                            $fieldName = $field['name'] ?? $field['id'] ?? '';
                            
                            // Try both field ID and name as keys
                            $value = $submission['submission_data'][$fieldId] ?? $submission['submission_data'][$fieldName] ?? '';
                            
                            // Handle array values (checkboxes, multi-select)
                            if (is_array($value)) {
                                $value = implode(', ', $value);
                            }
                            
                            $row[] = $value;
                        }
                    }
                    
                    // Add file URLs with access tokens
                    $submissionFiles = $this->getSubmissionFiles($submission['id']);
                    $filesByField = [];
                    foreach ($submissionFiles as $file) {
                        $filesByField[$file['field_name']] = $file;
                    }
                    
                    if (isset($form['config']['fields']) && is_array($form['config']['fields'])) {
                        foreach ($form['config']['fields'] as $field) {
                            if (in_array($field['type'] ?? '', ['file', 'photo', 'signature'])) {
                                $fieldId = $field['id'] ?? $field['name'] ?? '';
                                $fieldName = $field['name'] ?? $field['id'] ?? '';
                                
                                if (isset($filesByField[$fieldId]) || isset($filesByField[$fieldName])) {
                                    $fileData = $filesByField[$fieldId] ?? $filesByField[$fieldName];
                                    $fileUrl = $this->getSecureFileDownloadUrl($fileData['id']);
                                    $row[] = $fileUrl;
                                } else {
                                    $row[] = '';
                                }
                            }
                        }
                    }
                    
                    fputcsv($file, $row);
                }
            }
            
            fclose($file);
            
            // Generate export record for tracking and cleanup
            $exportId = $this->createExportRecord($formId, $filename, $filepath);
            
            $result = [
                'export_id' => $exportId,
                'filename' => $filename,
                'filepath' => $filepath,
                'download_url' => $this->getExportDownloadUrl($exportId),
                'record_count' => count($allSubmissions),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            error_log("CSV export completed successfully: " . json_encode($result));
            return $result;
            
        } catch (Exception $e) {
            error_log("SubmissionManager::exportToCSV - " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Create export record for tracking and cleanup
     * @param string $formId
     * @param string $filename
     * @param string $filepath
     * @return string Export ID
     */
    private function createExportRecord($formId, $filename, $filepath) {
        try {
            // Generate unique ID
            $exportId = uniqid('exp_' . time() . '_', true);
            error_log("Creating export record with ID: $exportId");
            
            // Check if exports table exists, create if not
            $this->ensureExportsTableExists();
            
            $query = "INSERT INTO exports (id, form_id, filename, filepath, created_at, expires_at, download_count) 
                     VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 0)";
            
            error_log("Executing export insert query with params: [" . implode(', ', [$exportId, $formId, $filename, $filepath]) . "]");
            
            $result = $this->db->query($query, [$exportId, $formId, $filename, $filepath]);
            
            if ($result) {
                error_log("Export record created successfully: $exportId");
                
                // Verify the record was actually inserted
                $verifyQuery = "SELECT id, filename, created_at FROM exports WHERE id = ?";
                $verifyResult = $this->db->query($verifyQuery, [$exportId]);
                
                if ($verifyResult && $verifyResult->fetch()) {
                    error_log("Export record verified in database: $exportId");
                    return $exportId;
                } else {
                    error_log("Export record not found after insertion: $exportId");
                    return uniqid('exp_fallback_');
                }
            } else {
                error_log('Failed to create export record in database - query returned false');
                return uniqid('exp_fallback_');
            }
        } catch (Exception $e) {
            error_log("SubmissionManager::createExportRecord - " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return uniqid('exp_fallback_');
        }
    }
    
    /**
     * Ensure exports table exists
     */
    private function ensureExportsTableExists() {
        try {
            error_log("Checking if exports table exists...");
            
            // Check if table exists
            $checkQuery = "SHOW TABLES LIKE 'exports'";
            $checkStmt = $this->db->query($checkQuery);
            
            if (!$checkStmt || $checkStmt->rowCount() === 0) {
                error_log("Exports table does not exist, creating it...");
                
                $query = "CREATE TABLE exports (
                    id VARCHAR(64) PRIMARY KEY,
                    form_id VARCHAR(64) NOT NULL,
                    filename VARCHAR(255) NOT NULL,
                    filepath VARCHAR(500) NOT NULL,
                    created_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    downloaded_at DATETIME NULL,
                    download_count INT DEFAULT 0,
                    INDEX idx_form_id (form_id),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $result = $this->db->query($query);
                
                if ($result !== false) {
                    error_log("Exports table created successfully");
                } else {
                    error_log("Failed to create exports table");
                }
            } else {
                error_log("Exports table already exists");
            }
            
        } catch (Exception $e) {
            error_log("Error checking/creating exports table: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Get export download URL
     * @param string $exportId
     * @return string
     */
    private function getExportDownloadUrl($exportId) {
        // Build URL dynamically from current request
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get the base path by removing '/admin/submissions.php' from current path
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = '';
        
        // If we're in admin directory, get parent path
        if (strpos($currentPath, '/admin/') !== false) {
            $basePath = substr($currentPath, 0, strpos($currentPath, '/admin/'));
        } else {
            // Fallback: assume we're in the admin directory
            $basePath = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        }
        
        $baseUrl = "{$protocol}://{$host}{$basePath}";
        return $baseUrl . '/admin/export-download.php?id=' . $exportId;
    }
    
    /**
     * Get secure file download URL with access token
     * @param string $fileId
     * @return string
     */
    private function getSecureFileDownloadUrl($fileId) {
        // Simple download URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get base path
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = '';
        if (strpos($currentPath, '/admin/') !== false) {
            $basePath = substr($currentPath, 0, strpos($currentPath, '/admin/'));
        } else {
            $basePath = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        }
        
        $baseUrl = "{$protocol}://{$host}{$basePath}";
        return $baseUrl . '/uploads/serve.php?file=' . urlencode($fileId);
    }
    
    /**
     * Get all submissions without pagination (for export) - Public method
     * @param string $formId
     * @param array $filters
     * @return array
     */
    public function getAllSubmissionsPublic($formId, $filters = []) {
        return $this->getAllSubmissions($formId, $filters);
    }
    
    /**
     * Get all submissions without pagination (for export)
     * @param string $formId
     * @param array $filters
     * @return array
     */
    private function getAllSubmissions($formId, $filters = []) {
        try {
            $whereConditions = ['form_id = ?'];
            $params = [$formId];
            
            // Add search filter
            if (!empty($filters['search'])) {
                $whereConditions[] = 'submission_data LIKE ?';
                $params[] = '%' . $filters['search'] . '%';
            }
            
            // Add date range filter
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'submitted_at >= ?';
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'submitted_at <= ?';
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $query = "SELECT id, submission_data, submitted_at, ip_address 
                     FROM submissions 
                     WHERE $whereClause 
                     ORDER BY submitted_at DESC";
            
            $stmt = $this->db->query($query, $params);
            $submissions = [];
            
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    $row['submission_data'] = json_decode($row['submission_data'], true);
                    $submissions[] = $row;
                }
            }
            
            return $submissions;
        } catch (Exception $e) {
            error_log("SubmissionManager::getAllSubmissions - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get file download URL (legacy method)
     * @param string $fileId
     * @return string
     */
    private function getFileDownloadUrl($fileId) {
        // Build URL dynamically
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = '';
        if (strpos($currentPath, '/admin/') !== false) {
            $basePath = substr($currentPath, 0, strpos($currentPath, '/admin/'));
        } else {
            $basePath = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        }
        
        $baseUrl = "{$protocol}://{$host}{$basePath}";
        return $baseUrl . '/uploads/serve.php?file=' . $fileId;
    }
    
    /**
     * Get export by ID
     * @param string $exportId
     * @return array|false
     */
    public function getExport($exportId) {
        try {
            error_log("Looking for export ID: $exportId");
            
            // First check if the table exists
            $this->ensureExportsTableExists();
            
            $query = "SELECT * FROM exports WHERE id = ? AND expires_at > NOW()";
            error_log("Executing export lookup query: $query with ID: $exportId");
            
            $stmt = $this->db->query($query, [$exportId]);
            
            if ($stmt && $row = $stmt->fetch()) {
                error_log("Export found: " . json_encode($row));
                return $row;
            } else {
                error_log("Export not found or expired for ID: $exportId");
                
                // Check if record exists but is expired
                $expiredQuery = "SELECT id, expires_at FROM exports WHERE id = ?";
                $expiredStmt = $this->db->query($expiredQuery, [$exportId]);
                
                if ($expiredStmt && $expiredRow = $expiredStmt->fetch()) {
                    error_log("Export exists but expired: " . json_encode($expiredRow));
                } else {
                    error_log("Export record does not exist at all for ID: $exportId");
                    
                    // Debug: Show all exports in table
                    $debugQuery = "SELECT id, created_at, expires_at FROM exports ORDER BY created_at DESC LIMIT 5";
                    $debugStmt = $this->db->query($debugQuery);
                    if ($debugStmt) {
                        $allExports = $debugStmt->fetchAll();
                        error_log("Recent exports in database: " . json_encode($allExports));
                    }
                }
                
                return false;
            }
        } catch (Exception $e) {
            error_log("SubmissionManager::getExport - " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Clean up expired exports
     * @return int Number of cleaned up exports
     */
    public function cleanupExpiredExports() {
        try {
            // Get expired exports
            $query = "SELECT filepath FROM exports WHERE expires_at <= NOW()";
            $stmt = $this->db->query($query);
            $expiredFiles = [];
            
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    $expiredFiles[] = $row['filepath'];
                }
            }
            
            // Delete expired records
            $deleteQuery = "DELETE FROM exports WHERE expires_at <= NOW()";
            $this->db->query($deleteQuery);
            
            // Delete physical files
            $deletedCount = 0;
            foreach ($expiredFiles as $filepath) {
                if (file_exists($filepath)) {
                    if (unlink($filepath)) {
                        $deletedCount++;
                    }
                }
            }
            
            return $deletedCount;
        } catch (Exception $e) {
            error_log("SubmissionManager::cleanupExpiredExports - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Record export download for statistics
     * @param string $exportId
     * @return bool
     */
    public function recordExportDownload($exportId) {
        try {
            $query = "UPDATE exports SET 
                     downloaded_at = NOW(), 
                     download_count = download_count + 1 
                     WHERE id = ?";
            
            $result = $this->db->query($query, [$exportId]);
            return $result !== false;
        } catch (Exception $e) {
            error_log("SubmissionManager::recordExportDownload - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get active exports for a form
     * @param string $formId
     * @return array
     */
    public function getActiveExports($formId) {
        try {
            $query = "SELECT id, filename, created_at, expires_at, downloaded_at, download_count
                     FROM exports 
                     WHERE form_id = ? AND expires_at > NOW()
                     ORDER BY created_at DESC";
            
            $stmt = $this->db->query($query, [$formId]);
            
            if ($stmt) {
                return $stmt->fetchAll();
            }
            return [];
        } catch (Exception $e) {
            error_log("SubmissionManager::getActiveExports - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get export statistics
     * @param string $formId Optional form ID to filter by
     * @return array
     */
    public function getExportStats($formId = null) {
        try {
            $whereClause = $formId ? 'WHERE form_id = ?' : '';
            $params = $formId ? [$formId] : [];
            
            $query = "SELECT 
                        COUNT(*) as total_exports,
                        COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_exports,
                        COUNT(CASE WHEN downloaded_at IS NOT NULL THEN 1 END) as downloaded_exports,
                        SUM(download_count) as total_downloads,
                        MAX(created_at) as last_export_created
                      FROM exports 
                      {$whereClause}";
            
            $stmt = $this->db->query($query, $params);
            
            if ($stmt && $row = $stmt->fetch()) {
                return $row;
            }
            
            return [
                'total_exports' => 0,
                'active_exports' => 0,
                'downloaded_exports' => 0,
                'total_downloads' => 0,
                'last_export_created' => null
            ];
        } catch (Exception $e) {
            error_log("SubmissionManager::getExportStats - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get submission statistics for a form
     * @param string $formId
     * @return array
     */
    public function getSubmissionStats($formId) {
        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN DATE(submitted_at) = CURDATE() THEN 1 END) as today,
                        COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as this_week,
                        COUNT(CASE WHEN submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as this_month,
                        MIN(submitted_at) as first_submission,
                        MAX(submitted_at) as last_submission
                      FROM submissions 
                      WHERE form_id = ?";
            
            $stmt = $this->db->query($query, [$formId]);
            
            if ($stmt && $row = $stmt->fetch()) {
                return $row;
            }
            
            return [
                'total' => 0,
                'today' => 0,
                'this_week' => 0,
                'this_month' => 0,
                'first_submission' => null,
                'last_submission' => null
            ];
        } catch (Exception $e) {
            error_log("SubmissionManager::getSubmissionStats - " . $e->getMessage());
            return [];
        }
    }
}
?>