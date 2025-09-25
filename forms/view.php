<?php
/**
 * Public Form View
 * Displays forms to users via share links
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/FormManager.php';
require_once __DIR__ . '/FormRenderer.php';
require_once __DIR__ . '/FormSubmissionHandler.php';

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
    $submissionHandler = new FormSubmissionHandler($form);
    $submissionResult = $submissionHandler->processSubmission($_POST, $_FILES);
    
    if ($submissionResult['success']) {
        // Show success page
        $showSuccessPage = true;
    } else {
        // Show errors
        $error = $submissionResult['error'] ?? 'An error occurred while processing your submission.';
        $fieldErrors = $submissionResult['errors'] ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="<?php echo $form ? htmlspecialchars($form['title']) : 'Form - ' . APP_NAME; ?>">
    <title><?php echo $form ? htmlspecialchars($form['title']) : 'Form Not Found'; ?> - <?php echo defined('APP_NAME') ? APP_NAME : 'Student Enrollment Platform'; ?></title>
    <link rel="stylesheet" href="../assets/css/responsive-framework.css">
    <link rel="stylesheet" href="../assets/css/public-form.css">
    <style>
        /* Fallback styles for better compatibility and contrast */
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
        
        /* High contrast text styles */
        .field-label {
            color: #333 !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            margin-bottom: 8px !important;
            display: block;
        }
        
        .radio-label,
        .checkbox-label {
            color: #333 !important;
            font-size: 14px !important;
        }
        
        .field-help {
            color: #666 !important;
            font-size: 13px !important;
        }
        
        .form-control {
            color: #333 !important;
            border: 2px solid #ddd;
            padding: 12px;
            font-size: 16px;
        }
        
        .form-control::placeholder {
            color: #999 !important;
        }
        
        .radio-option,
        .checkbox-option {
            color: #333 !important;
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
        
        /* Ensure all text is visible */
        .enrollment-form {
            color: #333;
        }
        
        .enrollment-form * {
            color: inherit;
        }
        
        /* Form header styling */
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
    </style>
</head>
<body>
    <div class="form-container">
        <?php if (isset($showSuccessPage) && $showSuccessPage): ?>
            <div class="success-container">
                <div class="success-icon">✅</div>
                <h2>Submission Successful!</h2>
                <p><?php echo htmlspecialchars($submissionResult['message']); ?></p>
                <div class="submission-id">
                    Submission ID: <?php echo htmlspecialchars($submissionResult['submission_id']); ?>
                </div>
                <p>Please save this submission ID for your records.</p>
                <div style="margin-top: 2rem;">
                    <a href="<?php echo defined('APP_URL') ? APP_URL : '../'; ?>" class="btn btn-primary">Return to Homepage</a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h2>Form Not Available</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
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
        <?php endif; ?>
    </div>

    <script src="../assets/js/form-validation.js"></script>
    <script src="../assets/js/preupload.js"></script>
    <script>
        // Initialize form validation
        <?php if ($formRenderer): ?>
        const formValidator = new FormValidator('enrollment-form', <?php echo $formRenderer->getValidationConfig(); ?>);
        <?php endif; ?>
        
        // Aadhar number formatting and validation
        document.addEventListener('DOMContentLoaded', function() {
            const aadharInputs = document.querySelectorAll('.aadhar-input');
            
            aadharInputs.forEach(function(input) {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, ''); // Remove all non-digits
                    
                    // Limit to 12 digits
                    if (value.length > 12) {
                        value = value.substr(0, 12);
                    }
                    
                    // Format as XXXX XXXX XXXX
                    if (value.length > 4 && value.length <= 8) {
                        value = value.substr(0, 4) + ' ' + value.substr(4);
                    } else if (value.length > 8) {
                        value = value.substr(0, 4) + ' ' + value.substr(4, 4) + ' ' + value.substr(8);
                    }
                    
                    e.target.value = value;
                });
                
                // Prevent non-numeric input
                input.addEventListener('keypress', function(e) {
                    const char = String.fromCharCode(e.which);
                    if (!/[0-9\s]/.test(char) && e.which !== 8 && e.which !== 9) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>

