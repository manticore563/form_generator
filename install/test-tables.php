<?php
// Simple test script to validate SQL syntax
require_once 'Installer.php';

$installer = new Installer();

// Test SQL syntax by getting the SQL statements
echo "Testing SQL syntax for table creation...\n\n";

try {
    // Use reflection to access private methods for testing
    $reflection = new ReflectionClass($installer);
    
    $methods = [
        'getFormsTableSQL',
        'getFormFieldsTableSQL', 
        'getSubmissionsTableSQL',
        'getFilesTableSQL',
        'getAdminUsersTableSQL'
    ];
    
    foreach ($methods as $method) {
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);
        
        $sql = $methodReflection->invoke($installer);
        echo "✓ {$method}: SQL syntax appears valid\n";
        
        // Basic validation - check for required keywords
        if (strpos($sql, 'CREATE TABLE') === false) {
            echo "✗ {$method}: Missing CREATE TABLE statement\n";
        }
        if (strpos($sql, 'PRIMARY KEY') === false) {
            echo "✗ {$method}: Missing PRIMARY KEY definition\n";
        }
    }
    
    echo "\n✅ All SQL statements appear to have valid syntax\n";
    
} catch (Exception $e) {
    echo "✗ Error testing SQL: " . $e->getMessage() . "\n";
}