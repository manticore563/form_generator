<?php
/**
 * Simple Form Submission Test
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Simple Form Submission Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>" . htmlspecialchars(print_r($_POST, true)) . "</pre>";
    
    echo "<h2>FILES Data Received:</h2>";
    echo "<pre>" . htmlspecialchars(print_r($_FILES, true)) . "</pre>";
    
    echo "<h2>Raw Input:</h2>";
    $rawInput = file_get_contents('php://input');
    echo "<pre>" . htmlspecialchars($rawInput) . "</pre>";
    
    // Test database connection
    try {
        require_once __DIR__ . '/config/config.php';
        require_once __DIR__ . '/includes/Database.php';
        
        $db = Database::getInstance();
        echo "<h2>Database Test:</h2>";
        echo "<p style='color: green;'>✅ Database connection successful</p>";
        
        // Try to get the form
        require_once __DIR__ . '/admin/FormManager.php';
        $formManager = new FormManager();
        $form = $formManager->getFormByShareLink('jJSDtdWeBk1X');
        
        if ($form) {
            echo "<p style='color: green;'>✅ Form found: " . htmlspecialchars($form['title']) . "</p>";
            
            // Try form submission
            require_once __DIR__ . '/forms/FormSubmissionHandler.php';
            $submissionHandler = new FormSubmissionHandler($form);
            $result = $submissionHandler->processSubmission($_POST, $_FILES);
            
            echo "<h2>Submission Result:</h2>";
            echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";
            
        } else {
            echo "<p style='color: red;'>❌ Form not found</p>";
        }
        
    } catch (Exception $e) {
        echo "<h2>Error:</h2>";
        echo "<p style='color: red;'>❌ " . $e->getMessage() . "</p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    // Show a simple form for testing
    ?>
    <form method="POST" enctype="multipart/form-data">
        <h2>Test Form</h2>
        <p>
            <label>Name:</label><br>
            <input type="text" name="test_name" value="John Doe">
        </p>
        <p>
            <label>Email:</label><br>
            <input type="email" name="test_email" value="john@example.com">
        </p>
        <p>
            <label>File:</label><br>
            <input type="file" name="test_file">
        </p>
        <p>
            <button type="submit">Submit Test</button>
        </p>
    </form>
    <?php
}
?>
