<?php
/**
 * Database Cleanup Script
 * Use this script to clean up a partial installation
 */

// Only allow access during installation
if (file_exists('../config/config.php') && file_exists('../config/.installation_complete')) {
    die('Installation is already complete. This script is not needed.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['db_host'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    $name = $_POST['db_name'] ?? '';
    
    if (empty($host) || empty($user) || empty($name)) {
        $error = 'Please provide database host, username, and database name.';
    } else {
        try {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Drop tables in reverse order to respect foreign key constraints
            $tables = ['exports', 'files', 'submissions', 'form_fields', 'forms', 'admin_users'];
            
            // Disable foreign key checks temporarily
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            $droppedTables = [];
            foreach ($tables as $table) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                    $droppedTables[] = $table;
                } catch (PDOException $e) {
                    // Continue with other tables
                }
            }
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Remove configuration files
            if (file_exists('../config/config.php')) {
                unlink('../config/config.php');
            }
            if (file_exists('../config/.installation_complete')) {
                unlink('../config/.installation_complete');
            }
            
            $success = 'Database cleanup completed successfully. Dropped tables: ' . implode(', ', $droppedTables) . '. You can now restart the installation process.';
            
        } catch (PDOException $e) {
            $error = 'Database cleanup failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Cleanup - Student Enrollment Platform</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .content {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        
        .btn:hover {
            background: #c0392b;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #3498db;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Database Cleanup</h1>
            <p>Clean up partial installation to restart the process</p>
        </div>
        
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="back-link">
                    <a href="index.php">← Return to Installation</a>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    ⚠️ <strong>Warning:</strong> This will permanently delete all existing tables and data in the specified database. 
                    Only use this if you need to restart a failed installation.
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_user">Database Username</label>
                        <input type="text" id="db_user" name="db_user" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                    
                    <button type="submit" class="btn" onclick="return confirm('Are you sure you want to delete all existing tables? This action cannot be undone.')">
                        Clean Up Database
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="index.php">← Return to Installation</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>