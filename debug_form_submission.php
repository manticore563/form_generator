<?php
/**
 * Debug Form Submission Script
 * This script helps debug form submission issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/FormManager.php';
require_once __DIR__ . '/forms/FormSubmissionHandler.php';

echo "<h1>Form Submission Debug</h1>";

// Get form by share link
$shareLink = $_GET['link'] ?? 'jJSDtdWeBk1X';
echo "<p>Testing form with link: <strong>$shareLink</strong></p>";

$formManager = new FormManager();
$form = $formManager->getFormByShareLink($shareLink);

if (!$form) {
    echo "<p style='color: red;'>❌ Form not found with link: $shareLink</p>";
    exit;
}

echo "<p style='color: green;'>✅ Form found: " . htmlspecialchars($form['title']) . "</p>";
echo "<p>Form ID: " . $form['id'] . "</p>";

// Display form configuration
echo "<h2>Form Configuration</h2>";
echo "<pre>" . htmlspecialchars(json_encode($form['config'], JSON_PRETTY_PRINT)) . "</pre>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✅ Database connection successful</p>";
    
    // Test a simple query
    $result = $db->query("SELECT COUNT(*) as count FROM forms WHERE id = ?", [$form['id']]);
    if ($result) {
        echo "<p style='color: green;'>✅ Database query test successful</p>";
    } else {
        echo "<p style='color: red;'>❌ Database query test failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Test form submission handler
echo "<h2>Form Submission Handler Test</h2>";
try {
    $submissionHandler = new FormSubmissionHandler($form);
    echo "<p style='color: green;'>✅ FormSubmissionHandler created successfully</p>";
    
    // Test with sample data
    $testPostData = [];
    $testFiles = [];
    
    // Add sample data for each field
    foreach ($form['config']['fields'] as $field) {
        if (!in_array($field['type'], ['file', 'photo', 'signature'])) {
            switch ($field['type']) {
                case 'text':
                case 'email':
                    $testPostData[$field['id']] = 'test_value';
                    break;
                case 'number':
                    $testPostData[$field['id']] = '123';
                    break;
                case 'select':
                case 'radio':
                    if (!empty($field['options'])) {
                        $testPostData[$field['id']] = $field['options'][0];
                    }
                    break;
                case 'checkbox':
                    if (!empty($field['options'])) {
                        $testPostData[$field['id']] = [$field['options'][0]];
                    }
                    break;
            }
        }
    }
    
    echo "<h3>Test Data:</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($testPostData, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Add CSRF token for testing
    $testPostData['csrf_token'] = 'test_token';
    
    // Test the submission (but don't actually save)
    echo "<p>Testing form validation...</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ FormSubmissionHandler error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Check required files
echo "<h2>Required Files Check</h2>";
$requiredFiles = [
    'includes/Database.php',
    'includes/InputValidator.php',
    'includes/CSRFProtection.php',
    'includes/SecurityUtils.php',
    'includes/AadharValidator.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "<p style='color: green;'>✅ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file missing</p>";
    }
}

// Check uploads directory
echo "<h2>Upload Directories Check</h2>";
$uploadDirs = [
    'uploads/temp',
    'uploads/files'
];

foreach ($uploadDirs as $dir) {
    $fullPath = __DIR__ . '/' . $dir;
    if (is_dir($fullPath)) {
        if (is_writable($fullPath)) {
            echo "<p style='color: green;'>✅ $dir exists and is writable</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ $dir exists but is not writable</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ $dir does not exist</p>";
        // Try to create it
        if (@mkdir($fullPath, 0755, true)) {
            echo "<p style='color: green;'>✅ Created $dir directory</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create $dir directory</p>";
        }
    }
}

// Check PHP configuration
echo "<h2>PHP Configuration</h2>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";

echo "<h2>Form URL</h2>";
echo "<p>Access your form at: <a href='forms/view.php?link=$shareLink' target='_blank'>forms/view.php?link=$shareLink</a></p>";
?>
