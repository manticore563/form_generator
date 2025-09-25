<?php
// Debug script to identify HTTP 500 error causes
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Information for HTTP 500 Error</h2>";

// Check if config file exists
echo "<h3>1. Config File Check</h3>";
$config_paths = [
    '../includes/config.php',
    'includes/config.php',
    '../config.php',
    'config.php'
];

$config_found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        echo "✓ Config file found at: $path<br>";
        $config_found = true;
        
        // Try to include it safely
        try {
            include_once $path;
            echo "✓ Config file included successfully<br>";
        } catch (Exception $e) {
            echo "✗ Error including config: " . $e->getMessage() . "<br>";
        }
        break;
    }
}

if (!$config_found) {
    echo "✗ No config file found. Available paths checked:<br>";
    foreach ($config_paths as $path) {
        echo "  - $path<br>";
    }
}

// Check database constants
echo "<h3>2. Database Configuration</h3>";
$db_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($db_constants as $constant) {
    if (defined($constant)) {
        echo "✓ $constant is defined<br>";
    } else {
        echo "✗ $constant is NOT defined<br>";
    }
}

// Check database connection
echo "<h3>3. Database Connection Test</h3>";
if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "✓ Database connection successful<br>";
    } catch (PDOException $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ Cannot test database - missing constants<br>";
}

// Check file permissions
echo "<h3>4. File Permissions</h3>";
$directories = ['.', '../', '../includes', '../uploads'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir);
        echo ($writable ? "✓" : "✗") . " Directory '$dir' " . ($writable ? "is writable" : "is NOT writable") . "<br>";
    } else {
        echo "✗ Directory '$dir' does not exist<br>";
    }
}

// Check PHP version and extensions
echo "<h3>5. PHP Environment</h3>";
echo "PHP Version: " . phpversion() . "<br>";
$required_extensions = ['pdo', 'pdo_mysql', 'session'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ Extension '$ext' is loaded<br>";
    } else {
        echo "✗ Extension '$ext' is NOT loaded<br>";
    }
}

// Check session
echo "<h3>6. Session Check</h3>";
session_start();
echo "Session ID: " . session_id() . "<br>";
if (isset($_SESSION['admin_logged_in'])) {
    echo "Admin logged in: " . ($_SESSION['admin_logged_in'] ? 'Yes' : 'No') . "<br>";
} else {
    echo "Admin login status: Not set<br>";
}

echo "<h3>7. Include Path Test</h3>";
$include_files = [
    '../includes/Database.php',
    '../includes/config.php'
];

foreach ($include_files as $file) {
    if (file_exists($file)) {
        echo "✓ File exists: $file<br>";
        if (is_readable($file)) {
            echo "✓ File is readable: $file<br>";
        } else {
            echo "✗ File is NOT readable: $file<br>";
        }
    } else {
        echo "✗ File NOT found: $file<br>";
    }
}

echo "<br><strong>Next Steps:</strong><br>";
echo "1. If config file is missing, run the installation wizard at /install/<br>";
echo "2. If database connection fails, check your hosting database credentials<br>";
echo "3. If file permissions are wrong, contact your hosting provider<br>";
echo "4. Access this debug script at: https://form.examorbit.info/admin/debug-500.php<br>";
?>