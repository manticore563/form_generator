<?php
/**
 * Simple test for upload endpoint
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Endpoint Test</title>
</head>
<body>
    <h1>Test Upload Endpoint</h1>
    <form method="post" enctype="multipart/form-data" action="uploads/public_upload.php">
        <input type="file" name="file" required>
        <button type="submit">Upload</button>
    </form>
    
    <h2>Direct Test</h2>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h3>POST Data:</h3><pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
        echo "<h3>FILES Data:</h3><pre>" . htmlspecialchars(print_r($_FILES, true)) . "</pre>";
        
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                echo "<div style='color:green'>✅ File uploaded successfully</div>";
            } else {
                echo "<div style='color:red'>❌ Upload error: " . $file['error'] . "</div>";
            }
        }
    }
    ?>
</body>
</html>
