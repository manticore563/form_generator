<?php
/**
 * Form Builder Interface
 * Drag-and-drop form builder with field configuration panels
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/FormManager.php';

// Check authentication (compatible with both login methods)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/simple-login.php');
    exit;
}

$formManager = new FormManager();
$formId = $_GET['id'] ?? null;
$form = null;

if ($formId) {
    $form = $formManager->getFormConfig($formId);
    if (!$form) {
        header('Location: forms.php?error=form_not_found');
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_form':
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $config = json_decode($_POST['config'], true);
            
            if ($formId) {
                $success = $formManager->updateForm($formId, $title, $description) && 
                          $formManager->updateFormConfig($formId, $config);
            } else {
                $newFormId = $formManager->createForm($title, $description);
                if ($newFormId) {
                    $success = $formManager->updateFormConfig($newFormId, $config);
                    $formId = $newFormId;
                } else {
                    $success = false;
                }
            }
            
            echo json_encode(['success' => $success, 'form_id' => $formId]);
            exit;
            
        case 'preview_form':
            $config = json_decode($_POST['config'], true);
            // Return HTML preview (will be implemented in form renderer)
            echo json_encode(['success' => true, 'html' => '<!-- Preview will be implemented -->']);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $form ? 'Edit Form' : 'Create Form'; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/responsive-framework.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/form-builder.css">
    <style>
        /* High contrast fallback styles for form builder */
        body {
            background-color: #f8f9fa !important;
            color: #333 !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .admin-header {
            background: #fff !important;
            border-bottom: 1px solid #ddd !important;
            color: #333 !important;
        }
        
        .admin-header h1 {
            color: #333 !important;
            font-size: 1.5rem !important;
            font-weight: 600;
        }
        
        .form-builder-container {
            color: #333 !important;
        }
        
        .form-settings-panel h3,
        .field-palette h3,
        .canvas-header h3 {
            color: #333 !important;
            font-size: 18px !important;
        }
        
        .form-group label {
            color: #555 !important;
            font-weight: 500 !important;
            font-size: 14px !important;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            color: #333 !important;
            border: 1px solid #ddd !important;
            background: #fff !important;
        }
        
        .field-type {
            background: #f8f9fa !important;
            border: 1px solid #e9ecef !important;
            color: #333 !important;
        }
        
        .field-type span {
            color: #333 !important;
            font-size: 14px !important;
        }
        
        .drop-message {
            color: #999 !important;
            font-size: 16px !important;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            min-height: 36px;
            line-height: 1;
        }
        
        .btn-primary {
            background: #007cba !important;
            color: white !important;
        }
        
        .btn-primary:hover {
            background: #005a87 !important;
        }
        
        .btn-secondary {
            background: #6c757d !important;
            color: white !important;
        }
        
        .btn-outline {
            background: transparent !important;
            color: #007cba !important;
            border: 1px solid #007cba !important;
        }
        
        .btn-outline:hover {
            background: #007cba !important;
            color: white !important;
        }
        
        .btn-danger {
            background: #dc3545 !important;
            color: white !important;
        }
        
        .btn-sm {
            padding: 4px 12px;
            font-size: 12px;
            min-height: 32px;
        }
        
        .form-settings-panel,
        .field-palette,
        .form-canvas,
        .field-config-panel {
            background: #fff !important;
            border: 1px solid #ddd !important;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .canvas-header {
            border-bottom: 1px solid #ddd !important;
        }
        
        .drop-zone {
            border: 2px dashed #ddd !important;
            background: #fafafa !important;
        }
        
        .drop-zone.drag-over {
            border-color: #007cba !important;
            background: #f0f8ff !important;
        }
        
        /* Ensure grid layout works */
        .form-builder-container {
            display: grid;
            grid-template-columns: 250px 200px 1fr 300px;
            grid-template-rows: auto 1fr;
            gap: 16px;
            height: calc(100vh - 120px);
            padding: 16px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><?php echo $form ? 'Edit Form: ' . htmlspecialchars($form['title']) : 'Create New Form'; ?></h1>
            <div class="header-actions">
                <button id="preview-btn" class="btn btn-secondary">Preview</button>
                <button id="save-btn" class="btn btn-primary">Save Form</button>
                <a href="forms.php" class="btn btn-outline">Back to Forms</a>
            </div>
        </header>

        <div class="form-builder-container">
            <!-- Form Settings Panel -->
            <div class="form-settings-panel">
                <h3>Form Settings</h3>
                <div class="form-group">
                    <label for="form-title">Form Title *</label>
                    <input type="text" id="form-title" value="<?php echo $form ? htmlspecialchars($form['title']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="form-description">Description</label>
                    <textarea id="form-description" rows="3"><?php echo $form ? htmlspecialchars($form['description']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="submit-button-text">Submit Button Text</label>
                    <input type="text" id="submit-button-text" value="Submit">
                </div>
                <div class="form-group">
                    <label for="success-message">Success Message</label>
                    <textarea id="success-message" rows="2">Thank you for your submission!</textarea>
                </div>
            </div>

            <!-- Field Types Palette -->
            <div class="field-palette">
                <h3>Field Types</h3>
                <div class="field-types">
                    <div class="field-type" data-type="text" draggable="true">
                        <i class="icon-text"></i>
                        <span>Text Input</span>
                    </div>
                    <div class="field-type" data-type="email" draggable="true">
                        <i class="icon-email"></i>
                        <span>Email</span>
                    </div>
                    <div class="field-type" data-type="number" draggable="true">
                        <i class="icon-number"></i>
                        <span>Number</span>
                    </div>
                    <div class="field-type" data-type="aadhar" draggable="true">
                        <i class="icon-id"></i>
                        <span>Aadhar Number</span>
                    </div>
                    <div class="field-type" data-type="select" draggable="true">
                        <i class="icon-select"></i>
                        <span>Dropdown</span>
                    </div>
                    <div class="field-type" data-type="radio" draggable="true">
                        <i class="icon-radio"></i>
                        <span>Radio Buttons</span>
                    </div>
                    <div class="field-type" data-type="checkbox" draggable="true">
                        <i class="icon-checkbox"></i>
                        <span>Checkboxes</span>
                    </div>
                    <div class="field-type" data-type="file" draggable="true">
                        <i class="icon-file"></i>
                        <span>File Upload</span>
                    </div>
                    <div class="field-type" data-type="photo" draggable="true">
                        <i class="icon-camera"></i>
                        <span>Photo Upload</span>
                    </div>
                    <div class="field-type" data-type="signature" draggable="true">
                        <i class="icon-signature"></i>
                        <span>Signature</span>
                    </div>
                </div>
            </div>

            <!-- Form Builder Canvas -->
            <div class="form-canvas">
                <div class="canvas-header">
                    <h3>Form Preview</h3>
                    <div class="canvas-actions">
                        <button id="clear-form" class="btn btn-outline btn-sm">Clear All</button>
                    </div>
                </div>
                <div id="form-builder" class="form-builder">
                    <div class="drop-zone" id="drop-zone">
                        <p class="drop-message">Drag field types here to build your form</p>
                    </div>
                </div>
            </div>

            <!-- Field Configuration Panel -->
            <div class="field-config-panel" id="field-config-panel" style="display: none;">
                <h3>Field Configuration</h3>
                <div id="field-config-content">
                    <!-- Dynamic content based on field type -->
                </div>
                <div class="config-actions">
                    <button id="apply-config" class="btn btn-primary">Apply</button>
                    <button id="cancel-config" class="btn btn-secondary">Cancel</button>
                    <button id="delete-field" class="btn btn-danger">Delete Field</button>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div id="preview-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Form Preview</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body" id="preview-content">
                    <!-- Preview content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/form-builder.js"></script>
    <script>
        // Initialize form builder with existing data
        const formBuilder = new FormBuilder({
            formId: '<?php echo $formId ?? ''; ?>',
            initialData: <?php echo $form ? json_encode($form['config']) : 'null'; ?>
        });
    </script>
</body>
</html>