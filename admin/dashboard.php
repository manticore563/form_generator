<?php
/**
 * Simple Admin Dashboard
 */

// Start session
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/simple-login.php');
    exit();
}

// Define initialization constant
if (!defined('SEFP_INIT')) {
    define('SEFP_INIT', true);
}

// Load config
require_once __DIR__ . '/../config/config.php';

$adminUsername = $_SESSION['admin_username'] ?? 'Admin';

// Get basic statistics
$totalForms = 0;
$totalSubmissions = 0;
$recentSubmissions = [];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get form count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM forms");
    $totalForms = $stmt->fetch()['count'];
    
    // Get submission count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM submissions");
    $totalSubmissions = $stmt->fetch()['count'];
    
    // Get recent submissions
    $stmt = $pdo->query("
        SELECT s.id, s.submitted_at, f.title as form_title 
        FROM submissions s 
        JOIN forms f ON s.form_id = f.id 
        ORDER BY s.submitted_at DESC 
        LIMIT 5
    ");
    $recentSubmissions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo defined('APP_NAME') ? APP_NAME : 'Student Enrollment Platform'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .nav {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav a {
            color: #2c3e50;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background 0.3s;
        }
        
        .nav a:hover {
            background: #e9ecef;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .actions {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .actions h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .recent-activity {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .recent-activity h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <?php echo defined('APP_NAME') ? APP_NAME : 'Student Enrollment Platform'; ?>
            </div>
            <nav class="nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="forms.php">Forms</a>
                <a href="submissions.php">Submissions</a>
                <a href="system-health.php">System Health</a>
                <span>Welcome, <?php echo htmlspecialchars($adminUsername); ?></span>
                <a href="../auth/logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <div class="welcome">
            <h1>Welcome to the Admin Dashboard</h1>
            <p>Manage your enrollment forms, view submissions, and monitor system health from here.</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalForms; ?></div>
                <div class="stat-label">Total Forms</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalSubmissions; ?></div>
                <div class="stat-label">Total Submissions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo count($recentSubmissions); ?></div>
                <div class="stat-label">Recent Activity</div>
            </div>
        </div>
        
        <div class="actions">
            <h3>Quick Actions</h3>
            <div class="action-buttons">
                <a href="form-builder.php" class="btn btn-success">Create New Form</a>
                <a href="forms.php" class="btn">Manage Forms</a>
                <a href="submissions.php" class="btn">View Submissions</a>
                <a href="system-health.php" class="btn btn-warning">System Health</a>
            </div>
        </div>
        
        <div class="recent-activity">
            <h3>Recent Submissions</h3>
            <?php if (empty($recentSubmissions)): ?>
                <p>No recent submissions found.</p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentSubmissions as $submission): ?>
                        <li class="activity-item">
                            <span>New submission for "<?php echo htmlspecialchars($submission['form_title']); ?>"</span>
                            <span class="activity-time"><?php echo date('M j, Y g:i A', strtotime($submission['submitted_at'])); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>