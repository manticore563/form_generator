<?php
/**
 * Test Form View - Uses Fixed Handler
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/FormManager.php';
require_once __DIR__ . '/FormRenderer.php';
require_once __DIR__ . '/FormSubmissionHandler_Fixed.php';

$formManager = new FormManager();
$shareLink = $_GET['link'] ?? $_GET['id'] ?? '';
$form = null;
$error = '';
$formRenderer = null;

if (!$shareLink) {
    $error = 'Invalid form link.';
} else {
    $form = $formManager->getFormByShareLink($shareLink);
    if (!$form) {
        $error = 'Form not found or is no longer active.';
    } else {
        $formRenderer = new FormRenderer($form);
        
        // Validate form configuration
        $configErrors = $formRenderer->validateConfig();
        if (!empty($configErrors)) {
            $error = 'Form configuration is invalid. Please contact the administrator.';
            error_log('Form validation errors for ' . $shareLink . ': ' . implode(', ', $configErrors));
        }
    }
}

// Handle form submission
$submissionResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form && $formRenderer) {
    error_log("=== FORM SUBMISSION TEST ===");
    error_log("POST data: " . json_encode($_POST));
    error_log("FILES data: " . json_encode($_FILES));
    
    $submissionHandler = new FormSubmissionHandler_Fixed($form);
    $submissionResult = $submissionHandler->processSubmission($_POST, $_FILES);
    
    error_log("Submission result: " . json_encode($submissionResult));
    
    if ($submissionResult['success']) {
        // Show success page
        $showSuccessPage = true;
    } else {
        // Show errors
        $error = $submissionResult['error'] ?? 'An error occurred while processing your submission.';
        $fieldErrors = $submissionResult['errors'] ?? [];
        error_log("Form submission failed: " . json_encode($fieldErrors));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="<?php echo $form ? htmlspecialchars($form['title']) : 'Form - ' . APP_NAME; ?>">
    <title><?php echo $form ? htmlspecialchars($form['title']) : 'Form Not Found'; ?> - TEST VERSION</title>
    <link rel="stylesheet" href="../assets/css/responsive-framework.css">
    <link rel="stylesheet" href="../assets/css/public-form.css">
    <style>
        /* Test version styling */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
        }
        .form-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .test-banner {
            background: #ff6b6b;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
        }
        
        .field-label {
            color: #333 !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            margin-bottom: 8px !important;
            display: block;
        }
        
        .form-control {
            color: #333 !important;
            border: 2px solid #ddd;
            padding: 12px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007cba;
            color: white !important;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        .btn:hover {
            background: #005a87;
            transform: translateY(-1px);
        }
        
        .form-header {
            background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .form-header h1 {
            margin: 0 0 1rem 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .form-description {
            margin: 0;
            opacity: 0.9;
            line-height: 1.5;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 20px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="test-banner">
            🧪 TEST VERSION - Enhanced Debugging Enabled
        </div>
        
        <?php if (isset($showSuccessPage) && $showSuccessPage): ?>
            <div class="success-container">
                <div class="success-icon">✅</div>
                <h2>Submission Successful!</h2>
                <p><?php echo htmlspecialchars($submissionResult['message']); ?></p>
                <div class="submission-id">
                    Submission ID: <?php echo htmlspecialchars($submissionResult['submission_id']); ?>
                </div>
                <p>Please save this submission ID for your records.</p>
                
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Submission Result: <?php echo htmlspecialchars(json_encode($submissionResult, JSON_PRETTY_PRINT)); ?>
                </div>
                
                <div style="margin-top: 2rem;">
                    <a href="<?php echo defined('APP_URL') ? APP_URL : '../'; ?>" class="btn btn-primary">Return to Homepage</a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h2>Form Not Available</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
                
                <?php if (isset($submissionResult)): ?>
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Submission Result: <?php echo htmlspecialchars(json_encode($submissionResult, JSON_PRETTY_PRINT)); ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 2rem;">
                    <a href="<?php echo defined('APP_URL') ? APP_URL : '../'; ?>" class="btn btn-primary">Go to Homepage</a>
                </div>
            </div>
        <?php else: ?>
            <div class="form-header">
                <h1><?php echo htmlspecialchars($form['title']); ?></h1>
                <?php if ($form['description']): ?>
                    <p class="form-description"><?php echo nl2br(htmlspecialchars($form['description'])); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="debug-info">
                <strong>POST Data Received:</strong><br>
                <?php echo htmlspecialchars(json_encode($_POST, JSON_PRETTY_PRINT)); ?>
                <br><br>
                <strong>FILES Data Received:</strong><br>
                <?php echo htmlspecialchars(json_encode($_FILES, JSON_PRETTY_PRINT)); ?>
            </div>
            <?php endif; ?>

            <?php echo $formRenderer->renderForm(); ?>
            
            <?php if (isset($fieldErrors) && !empty($fieldErrors)): ?>
                <div class="form-errors">
                    <h3>Please correct the following errors:</h3>
                    <ul>
                        <?php foreach ($fieldErrors as $fieldId => $errorMsg): ?>
                            <li><?php echo htmlspecialchars($errorMsg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="debug-info">
                <strong>Form Configuration:</strong><br>
                <?php echo htmlspecialchars(json_encode($form['config'], JSON_PRETTY_PRINT)); ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="../assets/js/form-validation.js"></script>
    <script src="../assets/js/preupload.js"></script>
    <script>
        // Initialize form validation
        <?php if ($formRenderer): ?>
        const formValidator = new FormValidator('enrollment-form', <?php echo $formRenderer->getValidationConfig(); ?>);
        <?php endif; ?>
        
        // Enhanced debugging
        console.log('Form loaded:', <?php echo json_encode($form ?? null); ?>);
        
        // Aadhar number formatting and validation
        document.addEventListener('DOMContentLoaded', function() {
            const aadharInputs = document.querySelectorAll('.aadhar-input');
            
            aadharInputs.forEach(function(input) {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 12) {
                        value = value.substr(0, 12);
                    }
                    
                    if (value.length > 4 && value.length <= 8) {
                        value = value.substr(0, 4) + ' ' + value.substr(4);
                    } else if (value.length > 8) {
                        value = value.substr(0, 4) + ' ' + value.substr(4, 4) + ' ' + value.substr(8);
                    }
                    
                    e.target.value = value;
                });
                
                input.addEventListener('keypress', function(e) {
                    const char = String.fromCharCode(e.which);
                    if (!/[0-9\s]/.test(char) && e.which !== 8 && e.which !== 9) {
                        e.preventDefault();
                    }
                });
            });
            
            // Add form submission debugging
            const form = document.getElementById('enrollment-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitting...');
                    console.log('Form data:', new FormData(form));
                });
            }
        });
    </script>
</body>
</html>
