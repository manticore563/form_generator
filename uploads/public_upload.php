<?php
/**
 * Public Upload Endpoint
 * Allows pre-submission uploads (photo/signature/file) to a temporary folder.
 * Returns a temp token that the form can submit later.
 */

// Basic CORS headers if needed (adjust domain in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Prevent any PHP errors from being output as HTML
error_reporting(0);
ini_set('display_errors', 0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing file']);
        exit;
    }

    $file = $_FILES['file'];

    // Basic upload error handling
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by a PHP extension'
        ];
        $msg = $errMap[$file['error']] ?? ('Upload error code ' . $file['error']);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    // Size limit (default 10MB)
    $maxBytes = 10 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File size exceeds 10MB limit']);
        exit;
    }

    // Very basic extension check
    $origName = $file['name'];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $blocked = ['php','phtml','php3','php4','php5','php7','phar','exe','bat','cmd','sh','com','vbs'];
    if (in_array($ext, $blocked, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File type not allowed']);
        exit;
    }

    // Ensure temp folder exists
    $tempDir = __DIR__ . '/temp';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }

    // Generate temp id and filename
    $tempId = 'tmp_' . bin2hex(random_bytes(12));
    $storedName = $tempId . '.' . $ext;
    $destPath = $tempDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
        exit;
    }

    // Return token and preview info
    $relPath = 'uploads/temp/' . $storedName; // relative to web root of this app

    echo json_encode([
        'success' => true,
        'temp_id' => $tempId,
        'temp_name' => $storedName,
        'original_name' => $origName,
        'size' => $file['size'],
        'mime' => $file['type'] ?? null,
        'preview_url' => $relPath
    ]);
    exit;

} catch (Throwable $e) {
    error_log('public_upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    exit;
}
