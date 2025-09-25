<?php
/**
 * Submissions Management Interface
 * View and manage form submissions with filtering and export
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/FormManager.php';
require_once __DIR__ . '/SubmissionManager.php';

// Check authentication (compatible with both login methods)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/simple-login.php');
    exit;
}

$formManager = new FormManager();
$submissionManager = new SubmissionManager();

// Get form ID from URL
$formId = $_GET['form_id'] ?? '';
if (!$formId) {
    header('Location: forms.php?error=form_not_specified');
    exit;
}

// Get form details
$form = $formManager->getFormConfig($formId);
if (!$form) {
    header('Location: forms.php?error=form_not_found');
    exit;
}

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_submission':
            $submissionId = $_POST['submission_id'] ?? '';
            if ($submissionId && $submissionManager->deleteSubmission($submissionId)) {
                $message = 'Submission deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error deleting submission.';
                $messageType = 'error';
            }
            break;
            
        case 'bulk_delete':
            $submissionIds = $_POST['submission_ids'] ?? [];
            if (!empty($submissionIds)) {
                $results = $submissionManager->deleteMultipleSubmissions($submissionIds);
                $message = "Deleted {$results['success']} submissions successfully.";
                if ($results['failed'] > 0) {
                    $message .= " {$results['failed']} failed to delete.";
                }
                $messageType = $results['failed'] > 0 ? 'warning' : 'success';
            } else {
                $message = 'No submissions selected.';
                $messageType = 'error';
            }
            break;
            
        case 'export_csv':
            // Redirect to direct CSV export
            $exportUrl = 'export-csv-direct.php?' . http_build_query([
                'form_id' => $formId,
                'search' => $_POST['search'] ?? '',
                'date_from' => $_POST['date_from'] ?? '',
                'date_to' => $_POST['date_to'] ?? ''
            ]);
            
            header('Location: ' . $exportUrl);
            exit;
            break;
    }
}

// Get filters from URL
$filters = [
    'search' => $_GET['search'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Get current page
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Get submissions
$submissionData = $submissionManager->getSubmissions($formId, $filters, $page, $perPage);
$submissions = $submissionData['submissions'];
$totalSubmissions = $submissionData['total'];
$totalPages = $submissionData['total_pages'];

// Get submission statistics
$stats = $submissionManager->getSubmissionStats($formId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions - <?php echo htmlspecialchars($form['title']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .submissions-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-description {
            color: #666;
            margin-bottom: 15px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #007cba;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 150px 150px auto auto;
            gap: 15px;
            align-items: end;
        }
        
        .submissions-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bulk-actions {
            display: none;
            gap: 10px;
            align-items: center;
        }
        
        .bulk-actions.active {
            display: flex;
        }
        
        .submissions-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .submission-row {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: grid;
            grid-template-columns: 40px 1fr auto auto;
            gap: 15px;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        
        .submission-row:hover {
            background: #f8f9fa;
        }
        
        .submission-row:last-child {
            border-bottom: none;
        }
        
        .submission-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        
        .field-preview {
            font-size: 14px;
        }
        
        .field-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 2px;
        }
        
        .field-value {
            color: #333;
            word-break: break-word;
        }
        
        .submission-meta {
            font-size: 12px;
            color: #666;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
        }
        
        .pagination .current {
            background: #007cba;
            color: white;
            border-color: #007cba;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .file-link {
            color: #007cba;
            text-decoration: none;
            font-size: 12px;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .submission-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .submission-preview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Submissions</h1>
            <div class="header-actions">
                <a href="forms.php" class="btn btn-outline">← Back to Forms</a>
                <a href="index.php" class="btn btn-outline">Dashboard</a>
                <a href="../auth/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>

        <div class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Form Header -->
            <div class="submissions-header">
                <h2 class="form-title"><?php echo htmlspecialchars($form['title']); ?></h2>
                <?php if ($form['description']): ?>
                    <p class="form-description"><?php echo htmlspecialchars($form['description']); ?></p>
                <?php endif; ?>
                
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Submissions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['today']; ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['this_week']; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['this_month']; ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <?php if ($stats['first_submission']): ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo date('M j', strtotime($stats['first_submission'])); ?></div>
                        <div class="stat-label">First Submission</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($stats['last_submission']): ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo date('M j', strtotime($stats['last_submission'])); ?></div>
                        <div class="stat-label">Last Submission</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <input type="hidden" name="form_id" value="<?php echo htmlspecialchars($formId); ?>">
                    <div class="filters-grid">
                        <div class="form-group mb-0">
                            <label>Search submissions</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search in submission data..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label>From Date</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        </div>
                        <div class="form-group mb-0">
                            <label>To Date</label>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?form_id=<?php echo htmlspecialchars($formId); ?>" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Submissions Table -->
            <div class="submissions-table">
                <div class="table-header">
                    <div class="d-flex align-items-center gap-3">
                        <h3>Submissions (<?php echo $totalSubmissions; ?>)</h3>
                        <?php if ($totalSubmissions > 0): ?>
                            <label class="d-flex align-items-center gap-2">
                                <input type="checkbox" id="select-all"> Select All
                            </label>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <?php if ($totalSubmissions > 0): ?>
                            <!-- Export Form -->
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="export_csv">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>">
                                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                                <button type="submit" class="btn btn-outline">📤 Export CSV</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Bulk Actions -->
                    <div class="bulk-actions" id="bulk-actions">
                        <span id="selected-count">0 selected</span>
                        <form method="POST" style="display: inline;" onsubmit="return confirmBulkDelete()">
                            <input type="hidden" name="action" value="bulk_delete">
                            <input type="hidden" name="submission_ids" id="bulk-submission-ids">
                            <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
                        </form>
                    </div>
                </div>

                <?php if (empty($submissions)): ?>
                    <div class="empty-state">
                        <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;">📝</div>
                        <h3>No submissions found</h3>
                        <p>
                            <?php if (!empty($filters['search']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                                No submissions match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                This form hasn't received any submissions yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="submissions-list">
                        <?php foreach ($submissions as $submission): ?>
                            <div class="submission-row">
                                <input type="checkbox" class="submission-checkbox" 
                                       value="<?php echo $submission['id']; ?>">
                                
                                <div class="submission-preview">
                                    <?php 
                                    $displayCount = 0;
                                    $submissionData = $submission['submission_data'] ?? [];
                                    
                                    // Debug: Log submission data structure
                                    error_log('Submission data for ID ' . $submission['id'] . ': ' . json_encode($submissionData));
                                    
                                    foreach ($form['config']['fields'] as $field): 
                                        if ($displayCount >= 4) break; // Limit preview fields
                                        
                                        $fieldId = $field['id'] ?? $field['name'] ?? '';
                                        $fieldName = $field['name'] ?? $field['id'] ?? '';
                                        
                                        // Try both field ID and name as keys
                                        $value = $submissionData[$fieldId] ?? $submissionData[$fieldName] ?? '';
                                        
                                        if (empty($value)) continue;
                                        
                                        // Handle different field types
                                        if (is_array($value)) {
                                            $value = implode(', ', $value);
                                        } elseif (in_array($field['type'], ['file', 'photo', 'signature'])) {
                                            $value = '📎 File uploaded';
                                        }
                                        
                                        // Truncate long values
                                        if (strlen($value) > 50) {
                                            $value = substr($value, 0, 50) . '...';
                                        }
                                        
                                        $displayCount++;
                                    ?>
                                        <div class="field-preview">
                                            <div class="field-label"><?php echo htmlspecialchars($field['label']); ?></div>
                                            <div class="field-value"><?php echo htmlspecialchars($value); ?></div>
                                        </div>
                                    <?php endforeach; 
                                    
                                    // If no fields were displayed, show debug info
                                    if ($displayCount === 0): ?>
                                        <div class="field-preview">
                                            <div class="field-label">Debug Info</div>
                                            <div class="field-value">Available keys: <?php echo htmlspecialchars(implode(', ', array_keys($submissionData))); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="submission-meta">
                                    <div><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></div>
                                    <div>IP: <?php echo htmlspecialchars($submission['ip_address']); ?></div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <a href="submission-details.php?id=<?php echo $submission['id']; ?>" 
                                       class="btn btn-primary btn-sm">View Details</a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this submission?');">
                                        <input type="hidden" name="action" value="delete_submission">
                                        <input type="hidden" name="submission_id" value="<?php echo $submission['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?form_id=<?php echo $formId; ?>&page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">← Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?form_id=<?php echo $formId; ?>&page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?form_id=<?php echo $formId; ?>&page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Bulk selection functionality
        const selectAllCheckbox = document.getElementById('select-all');
        const submissionCheckboxes = document.querySelectorAll('.submission-checkbox');
        const bulkActions = document.getElementById('bulk-actions');
        const selectedCount = document.getElementById('selected-count');
        const bulkSubmissionIds = document.getElementById('bulk-submission-ids');
        
        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.submission-checkbox:checked');
            const count = checkedBoxes.length;
            
            if (count > 0) {
                bulkActions.classList.add('active');
                selectedCount.textContent = count + ' selected';
                
                // Update hidden input with selected IDs
                const ids = Array.from(checkedBoxes).map(cb => cb.value);
                bulkSubmissionIds.value = JSON.stringify(ids);
            } else {
                bulkActions.classList.remove('active');
            }
        }
        
        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                submissionCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateBulkActions();
            });
        }
        
        // Individual checkbox functionality
        submissionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkActions();
                
                // Update select all checkbox state
                if (selectAllCheckbox) {
                    const checkedCount = document.querySelectorAll('.submission-checkbox:checked').length;
                    selectAllCheckbox.checked = checkedCount === submissionCheckboxes.length;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < submissionCheckboxes.length;
                }
            });
        });
        
        function confirmBulkDelete() {
            const count = document.querySelectorAll('.submission-checkbox:checked').length;
            return confirm(`Are you sure you want to delete ${count} submission(s)? This action cannot be undone.`);
        }
    </script>
</body>
</html>