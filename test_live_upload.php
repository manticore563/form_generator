<?php
/**
 * Live Upload Test - Debug actual form submission
 * Shows exactly what's being submitted
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/FormManager.php';

header('Content-Type: text/html; charset=utf-8');

$formManager = new FormManager();
$forms = $formManager->getAllForms();

// Find a form with file fields
$targetForm = null;
foreach ($forms as $form) {
    $config = json_decode($form['config'], true);
    if (isset($config['fields'])) {
        foreach ($config['fields'] as $field) {
            if (in_array($field['type'], ['photo', 'signature', 'file'])) {
                $targetForm = $form;
                break 2;
            }
        }
    }
}

if (!$targetForm) {
    echo "<h2>No forms with file upload fields found</h2>";
    echo "<p>Please add Photo, Signature, or File fields to your form using the form builder.</p>";
    exit;
}

$config = json_decode($targetForm['config'], true);

// Handle form submission
$submissionResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Debug Info - Form Submission</h2>";
    echo "<h3>POST Data:</h3><pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
    echo "<h3>FILES Data:</h3><pre>" . htmlspecialchars(print_r($_FILES, true)) . "</pre>";
    
    if (empty($_FILES)) {
        echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0;'>⚠️ No files received - check form enctype="multipart/form-data"</div>";
    } else {
        foreach ($_FILES as $fieldName => $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo "<div style='background: #f8d7da; padding: 10px; margin: 10px 0;'>⚠️ Upload error for $fieldName: " . $file['error'] . "</div>";
            } else {
                echo "<div style='background: #d4edda; padding: 10px; margin: 10px 0;'>✅ File uploaded: " . htmlspecialchars($file['name']) . " (" . $file['size'] . " bytes)</div>";
            }
        }
    }
}

// Display form with proper file inputs
?>
<!DOCTYPE html>
<html>
<head>
    <title>Live Upload Test - <?php echo htmlspecialchars($targetForm['title']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-container { max-width: 600px; margin: 0 auto; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="file"] { width: 100%; padding: 8px; }
        .submit-btn { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .debug { background: #f8f9fa; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Test: <?php echo htmlspecialchars($targetForm['title']); ?></h1>
        
        <form method="post" enctype="multipart/form-data">
            <?php foreach ($config['fields'] as $field): ?>
                <div class="form-group">
                    <label>
                        <?php echo htmlspecialchars($field['label']); ?>
                        <?php if ($field['required']): ?><span style="color: red;">*</span><?php endif; ?>
                    </label>
                    
                    <?php switch ($field['type']):
                        case 'text': case 'email': case 'number': ?>
                            <input type="<?php echo $field['type']; ?>" 
                                   name="<?php echo $field['id']; ?>" 
                                   placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>"
                                   <?php echo $field['required'] ? 'required' : ''; ?>>
                        <?php break; ?>
                        
                        <?php case 'photo': ?>
                            <input type="file" 
                                   name="<?php echo $field['id']; ?>" 
                                   accept="image/*"
                                   <?php echo $field['required'] ? 'required' : ''; ?>>
                        <?php break; ?>
                        
                        <?php case 'signature': ?>
                            <input type="file" 
                                   name="<?php echo $field['id']; ?>" 
                                   accept="image/*"
                                   <?php echo $field['required'] ? 'required' : ''; ?>>
                        <?php break; ?>
                        
                        <?php case 'file': ?>
                            <input type="file" 
                                   name="<?php echo $field['id']; ?>"
                                   <?php echo isset($field['allowedTypes']) ? 'accept=".' . implode(',.', $field['allowedTypes']) . '"' : ''; ?>
                                   <?php echo $field['required'] ? 'required' : ''; ?>>
                        <?php break; ?>
                    <?php endswitch; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="submit-btn">Test Upload</button>
        </form>
        
        <div class="debug">
            <h3>Form Configuration:</h3>
            <pre><?php echo htmlspecialchars(print_r($config, true)); ?></pre>
        </div>
    </div>
</body>
</html>
