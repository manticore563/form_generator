<?php
require_once 'ImageProcessor.php';
require_once '../includes/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Validate required parameters
    if (!isset($_POST['fileId']) || !isset($_POST['cropData'])) {
        throw new Exception('Missing required parameters');
    }
    
    $fileId = $_POST['fileId'];
    $cropData = json_decode($_POST['cropData'], true);
    
    if (!$cropData || !isset($cropData['x'], $cropData['y'], $cropData['width'], $cropData['height'])) {
        throw new Exception('Invalid crop data');
    }
    
    // Get file information from database
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    $fileRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$fileRecord) {
        throw new Exception('File not found');
    }
    
    // Check if file is an image
    if (strpos($fileRecord['mime_type'], 'image/') !== 0) {
        throw new Exception('File is not an image');
    }
    
    // Construct file path
    $uploadPath = __DIR__ . '/files/';
    $originalPath = $uploadPath . $fileRecord['file_path'];
    
    if (!file_exists($originalPath)) {
        throw new Exception('Original file not found on disk');
    }
    
    // Process the image
    $processor = new ImageProcessor();
    
    // Validate image
    $validation = $processor->validateImage(['tmp_name' => $originalPath]);
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Crop the image
    $croppedPath = $processor->cropImage($originalPath, $cropData);
    if (!$croppedPath) {
        throw new Exception('Failed to crop image');
    }
    
    // Generate thumbnail
    $thumbnailPath = $processor->generateThumbnail($croppedPath);
    
    // Update database with cropped version
    $pathInfo = pathinfo($croppedPath);
    $relativePath = str_replace($uploadPath, '', $croppedPath);
    
    $stmt = $db->prepare("
        UPDATE files 
        SET stored_filename = ?, file_path = ?, file_size = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $pathInfo['basename'],
        $relativePath,
        filesize($croppedPath),
        $fileId
    ]);
    
    // Clean up original file if different from cropped
    if ($originalPath !== $croppedPath && file_exists($originalPath)) {
        unlink($originalPath);
    }
    
    // Generate response
    $response = [
        'success' => true,
        'fileId' => $fileId,
        'croppedPath' => $relativePath,
        'thumbnailPath' => $thumbnailPath ? str_replace($uploadPath, '', $thumbnailPath) : null,
        'dimensions' => $processor->getImageInfo($croppedPath)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Image cropping error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>