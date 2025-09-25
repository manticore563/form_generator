<?php
/**
 * Upload Diagnostic Script
 * Helps troubleshoot file upload issues
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .warning { background: #fff3cd; color: #856404; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>File Upload Diagnostic</h1>
    
    <div class="section">
        <h2>PHP Configuration</h2>
        <?php
        $config = [
            'file_uploads' => ini_get('file_uploads'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        ];
        
        foreach ($config as $key => $value) {
            $class = ($value && $value !== '0') ? 'success' : 'error';
            echo "<div class='$class'><strong>$key:</strong> $value</div>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>Directory Permissions</h2>
        <?php
        $dirs = [
            'uploads' => __DIR__ . '/uploads',
            'uploads/files' => __DIR__ . '/uploads/files',
            'uploads/temp' => __DIR__ . '/uploads/temp',
            'uploads/photos' => __DIR__ . '/uploads/photos',
            'uploads/signatures' => __DIR__ . '/uploads/signatures',
        ];
        
        foreach ($dirs as $name => $path) {
            if (!is_dir($path)) {
                echo "<div class='warning'><strong>$name:</strong> Directory does not exist - " . htmlspecialchars($path) . "</div>";
                if (@mkdir($path, 0755, true)) {
                    echo "<div class='success'>Created directory: $path</div>";
                } else {
                    echo "<div class='error'>Failed to create directory: $path</div>";
                }
            } else {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                $writable = is_writable($path) ? 'writable' : 'NOT writable';
                $class = is_writable($path) ? 'success' : 'error';
                echo "<div class='$class'><strong>$name:</strong> $path (permissions: $perms, $writable)</div>";
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>Test Upload</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="test_upload" value="1">
            <label>Select file to test: <input type="file" name="test_file" required></label><br><br>
            <button type="submit">Test Upload</button>
        </form>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_upload'])) {
            echo "<h3>Upload Test Results:</h3>";
            
            if (!isset($_FILES['test_file'])) {
                echo "<div class='error'>No file received!</div>";
            } else {
                $file = $_FILES['test_file'];
                echo "<pre>File data: " . htmlspecialchars(print_r($file, true)) . "</pre>";
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
                    ];
                    $error = $errors[$file['error']] ?? 'Unknown upload error';
                    echo "<div class='error'>Upload error: $error</div>";
                } else {
                    $target_dir = __DIR__ . '/uploads/temp/';
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    
                    $target_file = $target_dir . basename($file['name']);
                    
                    if (move_uploaded_file($file['tmp_name'], $target_file)) {
                        echo "<div class='success'>File uploaded successfully to: $target_file</div>";
                        echo "<div class='success'>File size: " . filesize($target_file) . " bytes</div>";
                        unlink($target_file); // Clean up
                    } else {
                        echo "<div class='error'>Failed to move uploaded file!</div>";
                    }
                }
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>System Information</h2>
        <pre>
PHP Version: <?php echo PHP_VERSION; ?>
Operating System: <?php echo PHP_OS; ?>
Server Software: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
Document Root: <?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?>
Current Directory: <?php echo __DIR__; ?>
        </pre>
    </div>
    
    <div class="section">
        <h2>Recommendations</h2>
        <ul>
            <li>Ensure all upload directories exist and have 755 or 777 permissions</li>
            <li>Check that PHP file_uploads is enabled</li>
            <li>Verify upload_max_filesize and post_max_size are sufficient</li>
            <li>Check if temporary directory is writable</li>
            <li>Review server error logs for specific upload errors</li>
        </ul>
    </div>
</body>
</html>
