<?php
require_once 'Installer.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Set JSON response header
header('Content-Type: application/json');

try {
    $installer = new Installer();
    
    // Check if already installed
    if ($installer->isInstalled()) {
        echo json_encode([
            'success' => false,
            'error' => 'Platform is already installed'
        ]);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'validate_admin':
            $result = validateAdminAccount($installer);
            echo json_encode($result);
            break;
            
        case 'install':
            $result = performInstallation($installer);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Installation error: ' . $e->getMessage()
    ]);
}

/**
 * Validate admin account data
 * @param Installer $installer Installer instance
 * @return array Validation result
 */
function validateAdminAccount($installer): array {
    $adminData = [
        'username' => $_POST['admin_username'] ?? '',
        'email' => $_POST['admin_email'] ?? '',
        'password' => $_POST['admin_password'] ?? '',
        'password_confirm' => $_POST['admin_password_confirm'] ?? ''
    ];
    
    $validation = $installer->validateAdminData($adminData);
    
    return [
        'success' => $validation['valid'],
        'errors' => $validation['errors'] ?? [],
        'message' => $validation['valid'] ? 'Admin account data is valid' : 'Validation failed'
    ];
}

/**
 * Perform the complete installation process
 * @param Installer $installer Installer instance
 * @return array Installation result
 */
function performInstallation($installer): array {
    // Validate input data
    $requiredFields = ['db_host', 'db_name', 'db_user', 'admin_username', 'admin_email', 'admin_password'];
    
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            return [
                'success' => false,
                'error' => "Missing required field: {$field}"
            ];
        }
    }
    
    // Validate admin password confirmation
    if ($_POST['admin_password'] !== $_POST['admin_password_confirm']) {
        return [
            'success' => false,
            'error' => 'Admin passwords do not match'
        ];
    }
    
    // Validate password strength
    if (strlen($_POST['admin_password']) < 8) {
        return [
            'success' => false,
            'error' => 'Admin password must be at least 8 characters long'
        ];
    }
    
    // Validate email format
    if (!filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'error' => 'Invalid email address format'
        ];
    }
    
    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $_POST['admin_username'])) {
        return [
            'success' => false,
            'error' => 'Username must be 3-50 characters and contain only letters, numbers, and underscores'
        ];
    }
    
    $dbConfig = [
        'host' => $_POST['db_host'],
        'name' => $_POST['db_name'],
        'user' => $_POST['db_user'],
        'pass' => $_POST['db_pass'] ?? ''
    ];
    
    try {
        // Step 1: Test database connection
        if (!$installer->testDatabaseConnection($dbConfig['host'], $dbConfig['user'], $dbConfig['pass'], $dbConfig['name'])) {
            return [
                'success' => false,
                'error' => 'Database connection failed: ' . implode(', ', $installer->getErrors())
            ];
        }
        
        // Step 2: Create database connection for installation
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        // Step 3: Create database tables
        $dropExisting = isset($_POST['drop_existing']) && $_POST['drop_existing'] === 'true';
        if (!$installer->createTables($pdo, $dropExisting)) {
            return [
                'success' => false,
                'error' => 'Database table creation failed: ' . implode(', ', $installer->getErrors())
            ];
        }
        
        // Step 4: Create admin user
        if (!$installer->createAdminUser($pdo, $_POST['admin_username'], $_POST['admin_password'], $_POST['admin_email'])) {
            return [
                'success' => false,
                'error' => 'Admin user creation failed: ' . implode(', ', $installer->getErrors())
            ];
        }
        
        // Step 5: Generate configuration file with security settings
        if (!$installer->generateConfig($dbConfig)) {
            return [
                'success' => false,
                'error' => 'Configuration file creation failed: ' . implode(', ', $installer->getErrors())
            ];
        }
        
        // Step 6: Complete installation with cleanup and security setup
        if (!$installer->completeInstallation()) {
            return [
                'success' => false,
                'error' => 'Installation completion failed: ' . implode(', ', $installer->getErrors()),
                'warnings' => $installer->getWarnings()
            ];
        }
        
        // Step 7: Get redirect URL for completion
        $redirectUrl = $installer->getRedirectUrl();
        
        return [
            'success' => true,
            'message' => 'Installation completed successfully',
            'redirect_url' => $redirectUrl,
            'warnings' => $installer->getWarnings()
        ];
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Installation error: ' . $e->getMessage()
        ];
    }
}

