<?php
/**
 * Debug Form Upload Issues
 * Test actual form submission with file uploads
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/FormManager.php';
require_once __DIR__ . '/forms/FormSubmissionHandler.php';

header('Content-Type: text/html; charset=utf-8');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$debug = [];

// Check if we have a test form to work with
$formManager = new FormManager();
$forms = $formManager->getAllForms();

if (!$forms) {
    echo "<h2>No forms found. Please create a form first.</h2>";
    exit;
}

// Use the first active form
$testForm = null;
foreach ($forms as $form) {
    if ($form['is_active']) {
        $testForm = $form;
        break;
    }
}

if (!$testForm) {
    echo "<h2>No active forms found.</h2>";
    exit;
}

$debug[] = "Using form: " . $testForm['title'] . " (ID: " . $testForm['id'] . ")";

// Check form configuration for file fields
$hasFileFields = false;
$hasPhotoFields = false;
$hasSignatureFields = false;

if (isset($testForm['config']['fields'])) {
    foreach ($testForm['config']['fields'] as $field) {
        switch ($field['type']) {
            case 'file':
                $hasFileFields = true;
                $debug[] = "Found file field: " . $field['id'] . " - " . $field['label'];
                break;
            case 'photo':
                $hasPhotoFields = true;
                $debug[] = "Found photo field: " . $field['id'] . " - " . $field['label'];
                break;
            case 'signature':
                $hasSignatureFields = true;
                $debug[] = "Found signature field: " . $field['id'] . " - " . $field['label'];
                break;
        }
    }
}

$debug[] = "Has file fields: " . ($hasFileFields ? 'Yes' : 'No');
$debug[] = "Has photo fields: " . ($hasPhotoFields ? 'Yes' : 'No');
$debug[] = "Has signature fields: " . ($hasSignatureFields ? 'Yes' : 'No');

// Check upload directories
$uploadDirs = [
    'uploads/files' => __DIR__ . '/uploads/files',
    'uploads/photos' => __DIR__ . '/uploads/photos',
    'uploads/signatures' => __DIR__ . '/uploads/signatures',
    'uploads/temp' => __DIR__ . '/uploads/temp',
];

foreach ($uploadDirs as $name => $path) {
    if (!is_dir($path)) {
        $debug[] = "Creating directory: $path";
        if (mkdir($path, 0755, true)) {
            $debug[] = "✓ Created $name";
        } else {
            $debug[] = "✗ Failed to create $name";
        }
    } else {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? 'writable' : 'NOT writable';
        $debug[] = "✓ $name exists ($perms, $writable)";
    }
}

// Test FormSubmissionHandler
$debug[] = "Testing FormSubmissionHandler...";

try {
    $handler = new FormSubmissionHandler($testForm);
    $debug[] = "✓ FormSubmissionHandler initialized successfully";
} catch (Exception $e) {
    $debug[] = "✗ FormSubmissionHandler error: " . $e->getMessage();
}

// Display debug info
?>
<!DOCTYPE html>
<html>
<head>
    <title>Form Upload Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug { background: #f8f9fa; padding: 10px; margin: 5px 0; border-left: 3px solid #007cba; }
        .success { background: #d4edda; border-color: #28a745; }
        .error { background: #f8d7da; border-color: #dc3545; }
        .form-test { margin: 20px 0; padding: 20px; border: 1px solid #ccc; }
    </style>
</head>
<body>
    <h1>Form Upload Debug</h1>
    
    <h2>Debug Information</h2>
    <?php foreach ($debug as $line): ?>
        <div class="debug"><?php echo htmlspecialchars($line); ?></div>
    <?php endforeach; ?>
    
    <h2>Test Form Submission</h2>
    <div class="form-test">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="debug_submit" value="1">
            
            <?php if ($hasPhotoFields || $hasSignatureFields || $hasFileFields): ?>
                <h3>Upload Test Files</h3>
                
                <?php if ($hasPhotoFields): ?>
                    <label>Photo: <input type="file" name="photo" accept="image/*"></label><br><br>
                <?php endif; ?>
                
                <?php if ($hasSignatureFields): ?>
                    <label>Signature: <input type="file" name="signature" accept="image/*"></label><br><br>
                <?php endif; ?>
                
                <?php if ($hasFileFields): ?>
                    <label>File: <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.png"></label><br><br>
                <?php endif; ?>
                
                <button type="submit">Test Upload</button>
            <?php else: ?>
                <p>No file fields found in this form.</p>
            <?php endif; ?>
        </form>
    </div>
    
    <?php
    // Handle test submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['debug_submit'])) {
        echo "<h2>Submission Results</h2>";
        
        // Create mock POST data
        $mockPost = [
            'test_field' => 'test_value',
            'csrf_token' => 'test_token'  // Bypass CSRF for testing
        ];
        
        // Map uploaded files to form field IDs
        $mockFiles = [];
        
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $mockFiles['photo'] = $_FILES['photo'];
        }
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
            $mockFiles['signature'] = $_FILES['signature'];
        }
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $mockFiles['file'] = $_FILES['file'];
        }
        
        echo "<h3>Files received:</h3>";
        echo "<pre>" . htmlspecialchars(print_r($mockFiles, true)) . "</pre>";
        
        if (!empty($mockFiles)) {
            try {
                $handler = new FormSubmissionHandler($testForm);
                $result = $handler->processSubmission($mockPost, $mockFiles);
                
                echo "<h3>Handler Result:</h3>";
                echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
                
                if ($result['success']) {
                    echo "<div class='success'>✓ Upload successful!</div>";
                    
                    // Check if files were actually created
                    echo "<h3>Checking uploaded files:</h3>";
                    $uploadDir = __DIR__ . '/uploads/files';
                    $files = glob($uploadDir . '/*');
                    if ($files) {
                        foreach ($files as $file) {
                            echo "<div class='success'>Created: " . basename($file) . " (" . filesize($file) . " bytes)</div>";
                        }
                    } else {
                        echo "<div class='error'>No files found in uploads/files</div>";
                    }
                } else {
                    echo "<div class='error'>✗ Upload failed: " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>Exception: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='warning'>No files uploaded for testing</div>";
        }
    }
    ?>
    
    <h2>Recent Uploads</h2>
    <?php
    $uploadDir = __DIR__ . '/uploads/files';
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '/*');
        if ($files) {
            echo "<ul>";
            foreach ($files as $file) {
                $size = filesize($file);
                $time = date("Y-m-d H:i:s", filemtime($file));
                echo "<li>" . basename($file) . " ($size bytes, $time)</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No files in uploads/files</p>";
        }
    }
    ?>
</body>
</html>
