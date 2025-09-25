<?php
/**
 * Security Cleanup Script
 * Handles cleanup of old files, logs, and security maintenance
 */

define('SECURITY_INIT_ALLOWED', true);
require_once '../includes/security-init.php';
require_once '../includes/functions.php';

// Require admin authentication
requireAuth();

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateRequest($_POST)) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'cleanup_files':
            $daysOld = intval($_POST['days_old'] ?? 30);
            $fileSecurityManager = FileSecurityManager::getInstance();
            $results['file_cleanup'] = $fileSecurityManager->cleanupOldFiles($daysOld);
            break;
            
        case 'cleanup_logs':
            $daysToKeep = intval($_POST['days_to_keep'] ?? 90);
            $accessLogger = AccessLogger::getInstance();
            $results['log_cleanup'] = $accessLogger->cleanupLogs($daysToKeep);
            break;
            
        case 'get_statistics':
            $days = intval($_POST['stats_days'] ?? 7);
            $accessLogger = AccessLogger::getInstance();
            $results['statistics'] = $accessLogger->getAccessStatistics($days);
            break;
            
        case 'get_security_events':
            $limit = intval($_POST['events_limit'] ?? 50);
            $accessLogger = AccessLogger::getInstance();
            $results['security_events'] = $accessLogger->getRecentSecurityEvents($limit);
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Cleanup - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <?php echo CSRFProtection::getInstance()->getMetaTag('security_cleanup'); ?>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Security Cleanup & Monitoring</h1>
            <nav class="admin-nav">
                <a href="index.php">Dashboard</a>
                <a href="forms.php">Forms</a>
                <a href="submissions.php">Submissions</a>
                <a href="security-cleanup.php" class="active">Security</a>
            </nav>
        </header>

        <main class="admin-main">
            <?php if (!empty($results)): ?>
                <div class="results-section">
                    <h2>Operation Results</h2>
                    <?php foreach ($results as $operation => $result): ?>
                        <div class="result-item">
                            <h3><?php echo ucfirst(str_replace('_', ' ', $operation)); ?></h3>
                            <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?></pre>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="cleanup-section">
                <h2>File Cleanup</h2>
                <form method="POST" class="cleanup-form">
                    <?php echo csrfTokenInput('security_cleanup'); ?>
                    <input type="hidden" name="action" value="cleanup_files">
                    <div class="form-group">
                        <label for="days_old">Delete files older than (days):</label>
                        <input type="number" id="days_old" name="days_old" value="30" min="1" max="365">
                    </div>
                    <button type="submit" class="btn btn-warning">Cleanup Old Files</button>
                </form>
            </div>

            <div class="cleanup-section">
                <h2>Log Cleanup</h2>
                <form method="POST" class="cleanup-form">
                    <?php echo csrfTokenInput('security_cleanup'); ?>
                    <input type="hidden" name="action" value="cleanup_logs">
                    <div class="form-group">
                        <label for="days_to_keep">Keep logs for (days):</label>
                        <input type="number" id="days_to_keep" name="days_to_keep" value="90" min="7" max="365">
                    </div>
                    <button type="submit" class="btn btn-warning">Cleanup Old Logs</button>
                </form>
            </div>

            <div class="stats-section">
                <h2>Access Statistics</h2>
                <form method="POST" class="stats-form">
                    <?php echo csrfTokenInput('security_cleanup'); ?>
                    <input type="hidden" name="action" value="get_statistics">
                    <div class="form-group">
                        <label for="stats_days">Statistics for last (days):</label>
                        <input type="number" id="stats_days" name="stats_days" value="7" min="1" max="30">
                    </div>
                    <button type="submit" class="btn btn-primary">Get Statistics</button>
                </form>
            </div>

            <div class="events-section">
                <h2>Recent Security Events</h2>
                <form method="POST" class="events-form">
                    <?php echo csrfTokenInput('security_cleanup'); ?>
                    <input type="hidden" name="action" value="get_security_events">
                    <div class="form-group">
                        <label for="events_limit">Number of events:</label>
                        <input type="number" id="events_limit" name="events_limit" value="50" min="10" max="200">
                    </div>
                    <button type="submit" class="btn btn-primary">Get Security Events</button>
                </form>
            </div>
        </main>
    </div>

    <style>
        .cleanup-section, .stats-section, .events-section {
            background: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .cleanup-form, .stats-form, .events-form {
            display: flex;
            align-items: end;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-primary {
            background: #007cba;
            color: white;
        }
        
        .btn-warning {
            background: #f0ad4e;
            color: white;
        }
        
        .results-section {
            background: #e8f5e8;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            border: 1px solid #4caf50;
        }
        
        .result-item {
            margin-bottom: 20px;
        }
        
        .result-item h3 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        
        .result-item pre {
            background: white;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
    </style>
</body>
</html>
?>