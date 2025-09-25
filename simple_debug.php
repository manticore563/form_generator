<?php
/**
 * Simple Debug - No dependencies
 * Shows basic PHP info and file upload test
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Form Submission Debug</h2>";
    echo "<h3>POST Data:</h3><pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
    echo "<h3>FILES Data:</h3><pre>" . htmlspecialchars(print_r($_FILES, true)) . "</pre>";
    
    if (empty($_FILES)) {
        echo "<div style='background: #f8d7da; padding: 10px;'>⚠️ No files received</div>";
    } else {
        foreach ($_FILES as $field => $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                echo "<div style='background: #d4edda; padding: 10px;'>✅ File '$field' uploaded: " . htmlspecialchars($file['name']) . "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 10px;'>⚠️ Upload error for '$field': " . $file['error'] . "</div>";
            }
        }
    }
    exit;
}

// Show PHP info
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .error { background: #f8d7da; color: #721c24; }
        .success { background: #d4edda; color: #155724; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Simple Debug - No Dependencies</h1>
    
    <div class="section">
        <h2>PHP Configuration</h2>
        <?php
        echo "PHP Version: " . PHP_VERSION . "<br>";
        echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "<br>";
        echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
        echo "File Uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";
        echo "Upload Tmp Dir: " . ini_get('upload_tmp_dir') . "<br>";
        echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
        ?>
    </div>
    
    <div class="section">
        <h2>Directory Check</h2>
        <?php
        $dirs = ['uploads', 'uploads/files', 'uploads/photos', 'uploads/signatures'];
        foreach ($dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (is_dir($path)) {
                $perms = substr(sprintf('%o', fileperms($path)), -4);
                $writable = is_writable($path) ? 'writable' : 'NOT writable';
                echo "<div class='success'>✅ $dir exists ($perms, $writable)</div>";
            } else {
                echo "<div class='error'>❌ $dir missing</div>";
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>File Upload Test</h2>
        <form method="post" enctype="multipart/form-data">
            <label>Test File: <input type="file" name="test_file" required></label><br><br>
            <label>Your Name: <input type="text" name="name" placeholder="Optional"></label><br><br>
            <button type="submit">Test Upload</button>
        </form>
    </div>
</body>
</html>
