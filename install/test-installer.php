<?php
/**
 * Test script to verify installer functionality
 * This script can be used to test the installer without going through the web interface
 */

require_once 'Installer.php';

echo "Testing Student Enrollment Platform Installer\n";
echo str_repeat("=", 50) . "\n";

$installer = new Installer();

// Test 1: Check requirements
echo "\n1. Checking system requirements...\n";
$requirements = $installer->checkRequirements();

foreach ($requirements as $category => $checks) {
    echo "  {$category}:\n";
    if (is_array($checks) && isset($checks[0])) {
        // Multiple checks
        foreach ($checks as $check) {
            $status = $check['passed'] ?? $check['loaded'] ?? $check['exists'] ?? false;
            $statusText = $status ? "✓ PASS" : "✗ FAIL";
            echo "    - {$check['name']}: {$statusText}\n";
        }
    } else {
        // Single check
        $status = $checks['passed'] ?? false;
        $statusText = $status ? "✓ PASS" : "✗ FAIL";
        echo "    - {$checks['name']}: {$statusText}\n";
    }
}

// Test 2: Test database connection (if credentials provided)
if (isset($argv[1]) && isset($argv[2]) && isset($argv[3]) && isset($argv[4])) {
    echo "\n2. Testing database connection...\n";
    $host = $argv[1];
    $user = $argv[2];
    $pass = $argv[3];
    $db = $argv[4];
    
    echo "  Host: {$host}\n";
    echo "  Database: {$db}\n";
    echo "  User: {$user}\n";
    
    $connectionResult = $installer->testDatabaseConnection($host, $user, $pass, $db);
    $statusText = $connectionResult ? "✓ SUCCESS" : "✗ FAILED";
    echo "  Connection: {$statusText}\n";
    
    if (!$connectionResult) {
        echo "  Errors:\n";
        foreach ($installer->getErrors() as $error) {
            echo "    - {$error}\n";
        }
    }
} else {
    echo "\n2. Database connection test skipped (no credentials provided)\n";
    echo "   Usage: php test-installer.php <host> <user> <pass> <database>\n";
}

// Test 3: Check if already installed
echo "\n3. Checking installation status...\n";
$isInstalled = $installer->isInstalled();
$statusText = $isInstalled ? "✓ INSTALLED" : "✗ NOT INSTALLED";
echo "  Installation status: {$statusText}\n";

// Display any errors or warnings
$errors = $installer->getErrors();
$warnings = $installer->getWarnings();

if (!empty($errors)) {
    echo "\nERRORS:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

if (!empty($warnings)) {
    echo "\nWARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ {$warning}\n";
    }
}

echo "\nTest completed.\n";
?>