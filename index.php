<?php
/**
 * Student Enrollment Form Platform
 * Main entry point for the application
 */

// Start output buffering
ob_start();

// Set error reporting based on environment
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define initialization constant
if (!defined('SEFP_INIT')) {
    define('SEFP_INIT', true);
}

// Check if configuration exists
$configFile = __DIR__ . '/config/config.php';
if (!file_exists($configFile)) {
    // Redirect to installer if not configured
    header('Location: install/');
    exit();
}

// Load configuration
require_once $configFile;

// Load functions
require_once __DIR__ . '/includes/functions.php';

// Check if installation is complete
if (!defined('SEFP_INSTALLED') || !SEFP_INSTALLED) {
    header('Location: install/');
    exit();
}

// Start session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.use_strict_mode', 1);
session_name(SESSION_NAME);
session_start();

// Simple homepage - show available forms
showHomepage();

// End output buffering and send content
ob_end_flush();

/**
 * Show homepage with available forms
 */
function showHomepage() {
    try {
        // Direct database connection
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Get active forms
        $stmt = $pdo->prepare("SELECT id, title, description, share_link FROM forms WHERE is_active = 1 ORDER BY created_at DESC");
        $stmt->execute();
        $forms = $stmt->fetchAll();
        
        // Check if user is admin
        $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
        
    } catch (Exception $e) {
        // If database error, show simple homepage
        $forms = [];
        $isAdmin = false;
        error_log("Homepage database error: " . $e->getMessage());
    }
    
    // Display homepage
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo defined('APP_NAME') ? APP_NAME : 'Student Enrollment Platform'; ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                color: #333;
            }
            
            .header {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                padding: 1rem 0;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
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
            
            .nav-links {
                display: flex;
                gap: 1rem;
            }
            
            .nav-links a {
                color: #2c3e50;
                text-decoration: none;
                padding: 0.5rem 1rem;
                border-radius: 6px;
                transition: background 0.3s;
            }
            
            .nav-links a:hover {
                background: rgba(52, 152, 219, 0.1);
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 3rem 2rem;
            }
            
            .hero {
                text-align: center;
                margin-bottom: 4rem;
                color: white;
            }
            
            .hero h1 {
                font-size: 3rem;
                margin-bottom: 1rem;
                text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
            
            .hero p {
                font-size: 1.2rem;
                opacity: 0.9;
                max-width: 600px;
                margin: 0 auto;
            }
            
            .forms-section {
                background: white;
                border-radius: 12px;
                padding: 2rem;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                margin-bottom: 2rem;
            }
            
            .forms-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 2rem;
                margin-top: 2rem;
            }
            
            .form-card {
                border: 1px solid #e1e8ed;
                border-radius: 8px;
                padding: 1.5rem;
                transition: transform 0.3s, box-shadow 0.3s;
            }
            
            .form-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            .form-card h3 {
                color: #2c3e50;
                margin-bottom: 0.5rem;
            }
            
            .form-card p {
                color: #7f8c8d;
                margin-bottom: 1rem;
                line-height: 1.5;
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
            
            .btn-secondary {
                background: #95a5a6;
            }
            
            .btn-secondary:hover {
                background: #7f8c8d;
            }
            
            .empty-state {
                text-align: center;
                padding: 3rem;
                color: #7f8c8d;
            }
            
            .empty-state h3 {
                margin-bottom: 1rem;
            }
            
            .footer {
                text-align: center;
                padding: 2rem;
                color: rgba(255, 255, 255, 0.8);
            }
            
            @media (max-width: 768px) {
                .header-content {
                    flex-direction: column;
                    gap: 1rem;
                }
                
                .hero h1 {
                    font-size: 2rem;
                }
                
                .container {
                    padding: 2rem 1rem;
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
                <nav class="nav-links">
                    <?php if ($isAdmin): ?>
                        <a href="admin/dashboard.php">Admin Dashboard</a>
                        <a href="auth/logout.php">Logout</a>
                    <?php else: ?>
                        <a href="auth/simple-login.php">Admin Login</a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>
        
        <main class="container">
            <section class="hero">
                <h1>Welcome to Our Platform</h1>
                <p>Complete your enrollment forms quickly and securely. All your information is protected and processed safely.</p>
            </section>
            
            <section class="forms-section">
                <h2>Available Forms</h2>
                
                <?php if (empty($forms)): ?>
                    <div class="empty-state">
                        <h3>No Forms Available</h3>
                        <p>There are currently no active enrollment forms available.</p>
                        <?php if ($isAdmin): ?>
                            <p><a href="admin/form-builder.php" class="btn">Create New Form</a></p>
                        <?php else: ?>
                            <p>Please check back later or contact the administrator.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="forms-grid">
                        <?php foreach ($forms as $form): ?>
                            <div class="form-card">
                                <h3><?php echo htmlspecialchars($form['title']); ?></h3>
                                <?php if (!empty($form['description'])): ?>
                                    <p><?php echo htmlspecialchars($form['description']); ?></p>
                                <?php endif; ?>
                                <a href="forms/view.php?link=<?php echo urlencode($form['share_link']); ?>" class="btn">
                                    Start Form
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo defined('APP_NAME') ? APP_NAME : 'Student Enrollment Platform'; ?>. All rights reserved.</p>
        </footer>
    </body>
    </html>
    <?php
}
?>