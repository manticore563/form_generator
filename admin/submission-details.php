<?php
/**
 * Submission Details View
 * Display detailed view of a single submission with all data and files
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/SubmissionManager.php';

// Check authentication (compatible with both login methods)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/simple-login.php');
    exit;
}

$submissionManager = new SubmissionManager();

// Get submission ID from URL
$submissionId = $_GET['id'] ?? '';
if (!$submissionId) {
    header('Location: forms.php?error=submission_not_specified');
    exit;
}

// Get submission details
$submission = $submissionManager->getSubmissionDetails($submissionId);
if (!$submission) {
    header('Location: forms.php?error=submission_not_found');
    exit;
}

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_submission') {
        if ($submissionManager->deleteSubmission($submissionId)) {
            header('Location: submissions.php?form_id=' . $submission['form_id'] . '&message=deleted');
            exit;
        } else {
            $message = 'Error deleting submission.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Details - <?php echo htmlspecialchars($submission['form_title']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .submission-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .submission-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .submission-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .meta-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        
        .submission-data {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .data-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
        }
        
        .fields-grid {
            padding: 20px;
            display: grid;
            gap: 20px;
        }
        
        .field-item {
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 6px;
            background: #fafafa;
        }
        
        .field-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .field-value {
            color: #333;
            font-size: 16px;
            line-height: 1.5;
            word-break: break-word;
        }
        
        .field-type {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
            font-style: italic;
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 500;
            color: #333;
        }
        
        .file-meta {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        
        .file-actions {
            display: flex;
            gap: 8px;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-top: 8px;
        }
        
        .array-value {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }
        
        .array-item {
            background: #e3f2fd;
            color: #1565c0;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .empty-value {
            color: #999;
            font-style: italic;
        }
        
        .actions-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .submission-meta {
                grid-template-columns: 1fr;
            }
            
            .file-preview {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Submission Details</h1>
            <div class="header-actions">
                <a href="submissions.php?form_id=<?php echo $submission['form_id']; ?>" class="btn btn-outline">← Back to Submissions</a>
                <a href="forms.php" class="btn btn-outline">All Forms</a>
                <a href="../auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <div class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Submission Header -->
            <div class="submission-header">
                <h2 class="submission-title">Submission for "<?php echo htmlspecialchars($submission['form_title']); ?>"</h2>
                <p>Submission ID: <code><?php echo htmlspecialchars($submission['id']); ?></code></p>
                
                <div class="submission-meta">
                    <div class="meta-item">
                        <div class="meta-label">Submitted At</div>
                        <div class="meta-value"><?php echo date('F j, Y g:i A', strtotime($submission['submitted_at'])); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">IP Address</div>
                        <div class="meta-value"><?php echo htmlspecialchars($submission['ip_address']); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">User Agent</div>
                        <div class="meta-value" title="<?php echo htmlspecialchars($submission['user_agent']); ?>">
                            <?php echo htmlspecialchars(substr($submission['user_agent'], 0, 50)) . (strlen($submission['user_agent']) > 50 ? '...' : ''); ?>
                        </div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-label">Files Attached</div>
                        <div class="meta-value"><?php echo count($submission['files']); ?> file(s)</div>
                    </div>
                </div>
            </div>

            <!-- Submission Data -->
            <div class="submission-data">
                <div class="data-header">
                    <h3>Form Data</h3>
                </div>
                
                <div class="fields-grid">
                    <?php foreach ($submission['form_config']['fields'] as $field): ?>
                        <?php 
                        // Try both field ID and name for compatibility
                        $fieldId = $field['id'] ?? $field['name'] ?? '';
                        $fieldName = $field['name'] ?? $field['id'] ?? '';
                        
                        // Try to get value using both keys
                        $value = $submission['submission_data'][$fieldId] ?? $submission['submission_data'][$fieldName] ?? null;
                        
                        // Get files for this field (try both field ID and name)
                        $fieldFiles = array_filter($submission['files'], function($file) use ($fieldId, $fieldName) {
                            return $file['field_name'] === $fieldId || $file['field_name'] === $fieldName;
                        });
                        
                        // Debug info
                        if (empty($value) && empty($fieldFiles)) {
                            error_log("No data found for field: {$fieldId}/{$fieldName}. Available keys: " . implode(', ', array_keys($submission['submission_data'])));
                        }
                        ?>
                        <div class="field-item">
                            <div class="field-label">
                                <?php echo htmlspecialchars($field['label']); ?>
                                <?php if ($field['required'] ?? false): ?>
                                    <span style="color: #dc3545;">*</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="field-value">
                                <?php if (empty($value) && empty($fieldFiles)): ?>
                                    <span class="empty-value">No data provided</span>
                                <?php elseif (in_array($field['type'], ['file', 'photo', 'signature'])): ?>
                                    <?php if (!empty($fieldFiles)): ?>
                                        <?php foreach ($fieldFiles as $file): ?>
                                            <div class="file-preview">
                                                <div class="file-info">
                                                    <div class="file-name"><?php echo htmlspecialchars($file['original_filename']); ?></div>
                                                    <div class="file-meta">
                                                        <?php echo number_format($file['file_size'] / 1024, 1); ?> KB • 
                                                        <?php echo htmlspecialchars($file['mime_type']); ?> • 
                                                        Uploaded <?php echo date('M j, Y g:i A', strtotime($file['uploaded_at'])); ?>
                                                    </div>
                                                </div>
                                                <div class="file-actions">
                                                    <a href="../uploads/serve.php?file=<?php echo $file['id']; ?>" 
                                                       class="btn btn-primary btn-sm" target="_blank">Download</a>
                                                    <?php if (strpos($file['mime_type'], 'image/') === 0): ?>
                                                        <a href="../uploads/serve.php?file=<?php echo $file['id']; ?>" 
                                                           class="btn btn-outline btn-sm" target="_blank">View</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (strpos($file['mime_type'], 'image/') === 0): ?>
                                                <img src="../uploads/serve.php?file=<?php echo $file['id']; ?>" 
                                                     alt="<?php echo htmlspecialchars($file['original_filename']); ?>"
                                                     class="image-preview">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="empty-value">No file uploaded</span>
                                    <?php endif; ?>
                                <?php elseif (is_array($value)): ?>
                                    <div class="array-value">
                                        <?php foreach ($value as $item): ?>
                                            <span class="array-item"><?php echo htmlspecialchars($item); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <?php echo nl2br(htmlspecialchars($value)); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="field-type">
                                Field Type: <?php echo ucfirst($field['type']); ?>
                                <?php if (!empty($field['validation'])): ?>
                                    • Validation: <?php echo htmlspecialchars(json_encode($field['validation'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions-section">
                <h3>Actions</h3>
                <div class="d-flex gap-3 mt-3">
                    <a href="submissions.php?form_id=<?php echo $submission['form_id']; ?>" 
                       class="btn btn-outline">← Back to All Submissions</a>
                    
                    <a href="../forms/view.php?link=<?php echo $submission['share_link'] ?? ''; ?>" 
                       class="btn btn-secondary" target="_blank">View Form</a>
                    
                    <form method="POST" style="display: inline;" 
                          onsubmit="return confirm('Are you sure you want to delete this submission? This action cannot be undone and will also delete all associated files.');">
                        <input type="hidden" name="action" value="delete_submission">
                        <button type="submit" class="btn btn-danger">Delete Submission</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>