<?php
/**
 * Forms Management Interface
 * Lists all forms with CRUD operations
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
$message = '';
$messageType = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete':
            $formId = $_POST['form_id'] ?? '';
            if ($formId && $formManager->deleteForm($formId)) {
                $message = 'Form deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error deleting form.';
                $messageType = 'error';
            }
            break;
            
        case 'toggle_status':
            $formId = $_POST['form_id'] ?? '';
            $isActive = $_POST['is_active'] ?? '0';
            if ($formId && $formManager->setFormStatus($formId, $isActive === '1')) {
                $message = 'Form status updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error updating form status.';
                $messageType = 'error';
            }
            break;
            
        case 'duplicate':
            $formId = $_POST['form_id'] ?? '';
            $originalForm = $formManager->getFormConfig($formId);
            if ($originalForm) {
                $newTitle = $originalForm['title'] . ' (Copy)';
                $newFormId = $formManager->createForm($newTitle, $originalForm['description']);
                if ($newFormId && $formManager->updateFormConfig($newFormId, $originalForm['config'])) {
                    $message = 'Form duplicated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error duplicating form.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Form not found.';
                $messageType = 'error';
            }
            break;
    }
}

// Get all forms
$forms = $formManager->getAllForms();

// Handle GET parameters for messages
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'created':
            $message = 'Form created successfully.';
            $messageType = 'success';
            break;
        case 'updated':
            $message = 'Form updated successfully.';
            $messageType = 'success';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'form_not_found':
            $message = 'Form not found.';
            $messageType = 'error';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forms Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/responsive-framework.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* High contrast and improved dropdown styles */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .admin-header {
            background: #fff;
            border-bottom: 1px solid #ddd;
            color: #333;
        }
        
        .admin-header h1 {
            color: #333;
        }
        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .form-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: visible;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }
        
        .form-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .form-card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .form-card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .form-card-description {
            color: #666;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .form-card-meta {
            padding: 15px 20px;
            background: #f8f9fa;
            font-size: 13px;
            color: #666;
        }
        
        .form-card-actions {
            padding: 15px 20px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .submissions-count {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .btn-xs {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .share-link {
            font-family: monospace;
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #666;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Forms Management</h1>
            <div class="header-actions">
                <a href="form-builder.php" class="btn btn-primary">Create New Form</a>
                <a href="dashboard.php" class="btn btn-outline">Dashboard</a>
                <a href="../auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <div class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($forms)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h3>No forms created yet</h3>
                    <p>Create your first form to start collecting student enrollment data.</p>
                    <a href="form-builder.php" class="btn btn-primary mt-3">Create Your First Form</a>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>All Forms (<?php echo count($forms); ?>)</h2>
                    <div class="d-flex gap-2">
                        <input type="text" id="search-forms" placeholder="Search forms..." class="form-control" style="width: 250px;">
                    </div>
                </div>

                <div class="forms-grid" id="forms-grid">
                    <?php foreach ($forms as $form): ?>
                        <div class="form-card" data-form-title="<?php echo strtolower(htmlspecialchars($form['title'])); ?>">
                            <div class="form-card-header">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="form-card-title"><?php echo htmlspecialchars($form['title']); ?></h3>
                                    <div class="d-flex align-items-center">
                                        <span class="status-badge <?php echo $form['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $form['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                        <span class="submissions-count">
                                            <?php echo $form['submission_count']; ?> submissions
                                        </span>
                                    </div>
                                </div>
                                <?php if ($form['description']): ?>
                                    <p class="form-card-description"><?php echo htmlspecialchars($form['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-card-meta">
                                <div class="mb-2">
                                    <strong>Share Link:</strong> 
                                    <span class="share-link"><?php echo htmlspecialchars($form['share_link']); ?></span>
                                    <button class="btn btn-xs btn-outline" onclick="copyShareLink('<?php echo $form['share_link']; ?>')">Copy</button>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Created: <?php echo date('M j, Y', strtotime($form['created_at'])); ?></span>
                                    <span>Updated: <?php echo date('M j, Y', strtotime($form['updated_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="form-card-actions">
                                <a href="form-builder.php?id=<?php echo $form['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                <a href="../forms/view.php?link=<?php echo $form['share_link']; ?>" class="btn btn-outline btn-sm" target="_blank">Preview</a>
                                <a href="submissions.php?form_id=<?php echo $form['id']; ?>" class="btn btn-secondary btn-sm">
                                    Submissions (<?php echo $form['submission_count']; ?>)
                                </a>
                                
                                <div class="dropdown" style="position: relative; display: inline-block;">
                                    <button class="btn btn-outline btn-sm" onclick="toggleDropdown('<?php echo $form['id']; ?>')">More ▼</button>
                                    <div class="dropdown-menu" id="dropdown-<?php echo $form['id']; ?>" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 9999; min-width: 150px; white-space: nowrap;">
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                            <input type="hidden" name="action" value="duplicate">
                                            <button type="submit" class="dropdown-item">Duplicate</button>
                                        </form>
                                        
                                        <form method="POST" style="margin: 0;">
                                            <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="is_active" value="<?php echo $form['is_active'] ? '0' : '1'; ?>">
                                            <button type="submit" class="dropdown-item">
                                                <?php echo $form['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this form? This action cannot be undone.');">
                                            <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="dropdown-item text-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .dropdown-item {
            display: block;
            width: 100%;
            padding: 12px 16px;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .dropdown-item.text-danger {
            color: #dc3545;
        }
        
        .dropdown-item.text-danger:hover {
            background: #f8d7da;
        }
        
        /* Ensure dropdown appears above everything */
        .dropdown {
            position: relative;
            z-index: 1;
        }
        
        .dropdown-menu {
            z-index: 99999 !important;
        }
        
        /* Better button styling */
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
            background: #007cba;
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            color: #007cba;
            border: 1px solid #007cba;
        }
        
        .btn-outline:hover {
            background: #007cba;
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            min-height: 32px;
        }
    </style>

    <script>
        // Search functionality
        document.getElementById('search-forms').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const formCards = document.querySelectorAll('.form-card');
            
            formCards.forEach(card => {
                const title = card.dataset.formTitle;
                if (title.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Dropdown functionality
        function toggleDropdown(formId) {
            const dropdown = document.getElementById('dropdown-' + formId);
            const isVisible = dropdown.style.display === 'block';
            
            // Close all dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            
            // Toggle current dropdown
            dropdown.style.display = isVisible ? 'none' : 'block';
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
        
        // Copy share link functionality
        function copyShareLink(shareLink) {
            const fullUrl = window.location.origin + '/forms/view.php?link=' + shareLink;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(fullUrl).then(() => {
                    alert('Share link copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = fullUrl;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Share link copied to clipboard!');
            }
        }
    </script>
</body>
</html>