<?php

class Installer {
    private $errors = [];
    private $warnings = [];
    
    /**
     * Check system requirements for installation
     * @return array Array of requirement check results
     */
    public function checkRequirements(): array {
        $requirements = [
            'php_version' => $this->checkPhpVersion(),
            'extensions' => $this->checkExtensions(),
            'permissions' => $this->checkPermissions(),
            'functions' => $this->checkFunctions()
        ];
        
        return $requirements;
    }
    
    /**
     * Test database connection with provided credentials
     * @param string $host Database host
     * @param string $user Database username
     * @param string $pass Database password
     * @param string $db Database name
     * @return bool True if connection successful
     */
    public function testDatabaseConnection($host, $user, $pass, $db): bool {
        try {
            $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Database connection failed: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Check if installation is already completed
     * @return bool True if already installed
     */
    public function isInstalled(): bool {
        return file_exists('../config/config.php');
    }
    
    /**
     * Get installation errors
     * @return array Array of error messages
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Get installation warnings
     * @return array Array of warning messages
     */
    public function getWarnings(): array {
        return $this->warnings;
    }
    
    /**
     * Check PHP version requirement
     * @return array Requirement check result
     */
    private function checkPhpVersion(): array {
        $required = '7.4.0';
        $current = PHP_VERSION;
        $passed = version_compare($current, $required, '>=');
        
        if (!$passed) {
            $this->errors[] = "PHP version {$required} or higher is required. Current version: {$current}";
        }
        
        return [
            'name' => 'PHP Version',
            'required' => $required . '+',
            'current' => $current,
            'passed' => $passed,
            'critical' => true
        ];
    }
    
    /**
     * Check required PHP extensions
     * @return array Requirement check results
     */
    private function checkExtensions(): array {
        $required = [
            'pdo' => 'PDO extension for database operations',
            'pdo_mysql' => 'PDO MySQL driver for database connectivity',
            'json' => 'JSON extension for data processing',
            'mbstring' => 'Multibyte string extension for text handling',
            'fileinfo' => 'File info extension for file type detection',
            'gd' => 'GD extension for image processing'
        ];
        
        $results = [];
        
        foreach ($required as $ext => $description) {
            $loaded = extension_loaded($ext);
            
            if (!$loaded) {
                $this->errors[] = "Required PHP extension '{$ext}' is not loaded: {$description}";
            }
            
            $results[] = [
                'name' => $ext,
                'description' => $description,
                'loaded' => $loaded,
                'critical' => true
            ];
        }
        
        // Optional extensions
        $optional = [
            'curl' => 'cURL extension for HTTP requests (recommended)',
            'zip' => 'ZIP extension for archive handling (recommended)'
        ];
        
        foreach ($optional as $ext => $description) {
            $loaded = extension_loaded($ext);
            
            if (!$loaded) {
                $this->warnings[] = "Optional PHP extension '{$ext}' is not loaded: {$description}";
            }
            
            $results[] = [
                'name' => $ext,
                'description' => $description,
                'loaded' => $loaded,
                'critical' => false
            ];
        }
        
        return $results;
    }
    
    /**
     * Check directory permissions
     * @return array Permission check results
     */
    private function checkPermissions(): array {
        $directories = [
            '../config' => 'Configuration directory (write access needed)',
            '../uploads' => 'Upload directory (write access needed)',
            '../logs' => 'Log directory (write access needed)',
            '..' => 'Application root (read access needed)'
        ];
        
        $results = [];
        
        foreach ($directories as $dir => $description) {
            $exists = is_dir($dir);
            $readable = $exists && is_readable($dir);
            $writable = $exists && is_writable($dir);
            
            // Determine required permissions
            $needsWrite = in_array($dir, ['../config', '../uploads', '../logs']);
            $passed = $readable && ($needsWrite ? $writable : true);
            
            if (!$exists) {
                $this->errors[] = "Directory '{$dir}' does not exist";
            } elseif (!$readable) {
                $this->errors[] = "Directory '{$dir}' is not readable";
            } elseif ($needsWrite && !$writable) {
                $this->errors[] = "Directory '{$dir}' is not writable";
            }
            
            $results[] = [
                'name' => $dir,
                'description' => $description,
                'exists' => $exists,
                'readable' => $readable,
                'writable' => $writable,
                'needs_write' => $needsWrite,
                'passed' => $passed,
                'critical' => true
            ];
        }
        
        return $results;
    }
    
    /**
     * Check required PHP functions
     * @return array Function check results
     */
    private function checkFunctions(): array {
        $required = [
            'password_hash' => 'Password hashing function',
            'password_verify' => 'Password verification function',
            'session_start' => 'Session management function',
            'file_get_contents' => 'File reading function',
            'file_put_contents' => 'File writing function'
        ];
        
        $results = [];
        
        foreach ($required as $func => $description) {
            $exists = function_exists($func);
            
            if (!$exists) {
                $this->errors[] = "Required PHP function '{$func}' is not available: {$description}";
            }
            
            $results[] = [
                'name' => $func,
                'description' => $description,
                'exists' => $exists,
                'critical' => true
            ];
        }
        
        return $results;
    }
    
    /**
     * Create database tables with proper schema
     * @param PDO $pdo Database connection
     * @param bool $dropExisting Whether to drop existing tables
     * @return bool True if tables created successfully
     */
    public function createTables(PDO $pdo, $dropExisting = false): bool {
        // Clear previous errors
        $this->errors = [];
        
        try {
            // Check if tables already exist
            if ($this->tablesExist($pdo)) {
                if ($dropExisting) {
                    // Drop existing tables and recreate
                    if (!$this->dropExistingTables($pdo)) {
                        return false;
                    }
                } else {
                    // Check if installation is actually complete
                    if ($this->isInstallationComplete($pdo)) {
                        $this->errors[] = "Installation appears to be complete. If you need to reinstall, please use the database cleanup tool or check the 'Drop existing tables' option.";
                        return false;
                    }
                    // If installation is not complete, continue with existing tables
                    return $this->verifyAndCompleteInstallation($pdo);
                }
            }
            
            // Start transaction for rollback capability
            $pdo->beginTransaction();
            
            // Create tables in order (respecting foreign key dependencies)
            $tables = [
                'forms' => $this->getFormsTableSQL(),
                'form_fields' => $this->getFormFieldsTableSQL(),
                'submissions' => $this->getSubmissionsTableSQL(),
                'files' => $this->getFilesTableSQL(),
                'admin_users' => $this->getAdminUsersTableSQL(),
                'exports' => $this->getExportsTableSQL()
            ];
            
            foreach ($tables as $tableName => $sql) {
                try {
                    $pdo->exec($sql);
                } catch (PDOException $e) {
                    throw new PDOException("Failed to create table '{$tableName}': " . $e->getMessage());
                }
            }
            
            // Verify all tables were created successfully
            if (!$this->verifyTablesCreated($pdo)) {
                throw new PDOException("Table verification failed after creation");
            }
            
            // Commit transaction
            $pdo->commit();
            
            return true;
        } catch (PDOException $e) {
            // Rollback on error
            try {
                $pdo->rollBack();
            } catch (PDOException $rollbackError) {
                $this->errors[] = "Rollback failed: " . $rollbackError->getMessage();
            }
            
            $this->errors[] = "Database table creation failed: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Check if database tables already exist
     * @param PDO $pdo Database connection
     * @return bool True if tables exist
     */
    private function tablesExist(PDO $pdo): bool {
        $tables = ['forms', 'form_fields', 'submissions', 'files', 'admin_users'];
        
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SHOW TABLES LIKE '" . $pdo->quote($table) . "'");
                if ($stmt && $stmt->rowCount() > 0) {
                    return true;
                }
            } catch (PDOException $e) {
                // If SHOW TABLES fails, try alternative method
                try {
                    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
                    $stmt->execute([$table]);
                    if ($stmt->rowCount() > 0) {
                        return true;
                    }
                } catch (PDOException $e2) {
                    // Continue checking other tables
                    continue;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Drop existing tables in proper order
     * @param PDO $pdo Database connection
     * @return bool True if tables dropped successfully
     */
    private function dropExistingTables(PDO $pdo): bool {
        try {
            // Drop tables in reverse order to respect foreign key constraints
            $tables = ['exports', 'files', 'submissions', 'form_fields', 'forms', 'admin_users'];
            
            // Disable foreign key checks temporarily
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            foreach ($tables as $table) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                } catch (PDOException $e) {
                    // Continue with other tables even if one fails
                    $this->warnings[] = "Failed to drop table '{$table}': " . $e->getMessage();
                }
            }
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Failed to drop existing tables: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Check if installation is actually complete
     * @param PDO $pdo Database connection
     * @return bool True if installation is complete
     */
    private function isInstallationComplete(PDO $pdo): bool {
        try {
            // Check if all required tables exist with proper structure
            if (!$this->verifyTablesCreated($pdo)) {
                return false;
            }
            
            // Check if admin user exists
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_users WHERE is_active = 1");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                return false;
            }
            
            // Check if config file exists
            if (!file_exists('../config/config.php')) {
                return false;
            }
            
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Verify and complete partial installation
     * @param PDO $pdo Database connection
     * @return bool True if installation completed successfully
     */
    private function verifyAndCompleteInstallation(PDO $pdo): bool {
        try {
            // Check which tables are missing and create them
            $requiredTables = [
                'forms' => $this->getFormsTableSQL(),
                'form_fields' => $this->getFormFieldsTableSQL(),
                'submissions' => $this->getSubmissionsTableSQL(),
                'files' => $this->getFilesTableSQL(),
                'admin_users' => $this->getAdminUsersTableSQL(),
                'exports' => $this->getExportsTableSQL()
            ];
            
            $pdo->beginTransaction();
            
            foreach ($requiredTables as $tableName => $sql) {
                // Check if table exists
                $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
                $stmt->execute([$tableName]);
                
                if ($stmt->rowCount() === 0) {
                    // Table doesn't exist, create it
                    try {
                        $pdo->exec($sql);
                    } catch (PDOException $e) {
                        throw new PDOException("Failed to create missing table '{$tableName}': " . $e->getMessage());
                    }
                }
            }
            
            // Verify all tables are now present
            if (!$this->verifyTablesCreated($pdo)) {
                throw new PDOException("Table verification failed after completing installation");
            }
            
            $pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            try {
                $pdo->rollBack();
            } catch (PDOException $rollbackError) {
                $this->errors[] = "Rollback failed: " . $rollbackError->getMessage();
            }
            
            $this->errors[] = "Failed to complete installation: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Verify that all required tables were created successfully
     * @param PDO $pdo Database connection
     * @return bool True if all tables exist
     */
    private function verifyTablesCreated(PDO $pdo): bool {
        $requiredTables = ['forms', 'form_fields', 'submissions', 'files', 'admin_users'];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
                $stmt->execute([$table]);
                
                if ($stmt->rowCount() === 0) {
                    $this->errors[] = "Required table '{$table}' was not created successfully";
                    return false;
                }
            } catch (PDOException $e) {
                $this->errors[] = "Error verifying table '{$table}': " . $e->getMessage();
                return false;
            }
        }
        
        // Verify table structure by checking key columns
        if (!$this->verifyTableStructure($pdo)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify table structure by checking key columns exist
     * @param PDO $pdo Database connection
     * @return bool True if table structure is correct
     */
    private function verifyTableStructure(PDO $pdo): bool {
        $tableColumns = [
            'forms' => ['id', 'title', 'config', 'share_link', 'created_at', 'is_active'],
            'form_fields' => ['id', 'form_id', 'field_name', 'field_type', 'field_config', 'sort_order'],
            'submissions' => ['id', 'form_id', 'submission_data', 'submitted_at', 'status'],
            'files' => ['id', 'submission_id', 'field_name', 'file_path', 'file_size', 'mime_type'],
            'admin_users' => ['id', 'username', 'email', 'password_hash', 'is_active', 'role']
        ];
        
        foreach ($tableColumns as $table => $columns) {
            foreach ($columns as $column) {
                try {
                    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
                    $stmt->execute([$table, $column]);
                    
                    if ($stmt->rowCount() === 0) {
                        $this->errors[] = "Required column '{$column}' not found in table '{$table}'";
                        return false;
                    }
                } catch (PDOException $e) {
                    $this->errors[] = "Error verifying column '{$column}' in table '{$table}': " . $e->getMessage();
                    return false;
                }
            }
        }
        
        // Verify foreign key constraints exist
        if (!$this->verifyForeignKeys($pdo)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify foreign key constraints exist
     * @param PDO $pdo Database connection
     * @return bool True if foreign keys are properly set
     */
    private function verifyForeignKeys(PDO $pdo): bool {
        $foreignKeys = [
            'form_fields' => 'fk_form_fields_form_id',
            'submissions' => 'fk_submissions_form_id',
            'files' => 'fk_files_submission_id'
        ];
        
        foreach ($foreignKeys as $table => $constraintName) {
            $stmt = $pdo->prepare("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ");
            $stmt->execute([$table, $constraintName]);
            
            if ($stmt->rowCount() === 0) {
                $this->errors[] = "Foreign key constraint '{$constraintName}' not found in table '{$table}'";
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create admin user account
     * @param PDO $pdo Database connection
     * @param string $username Admin username
     * @param string $password Admin password
     * @param string $email Admin email
     * @return bool True if user created successfully
     */
    public function createAdminUser(PDO $pdo, $username, $password, $email): bool {
        try {
            // Validate input parameters
            if (empty($username) || empty($password) || empty($email)) {
                $this->errors[] = "Admin user creation failed: Missing required fields";
                return false;
            }
            
            // Validate username format
            if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
                $this->errors[] = "Admin user creation failed: Username must be 3-50 characters and contain only letters, numbers, and underscores";
                return false;
            }
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Admin user creation failed: Invalid email address format";
                return false;
            }
            
            // Validate password strength
            if (strlen($password) < 8) {
                $this->errors[] = "Admin user creation failed: Password must be at least 8 characters long";
                return false;
            }
            
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $this->errors[] = "Admin user creation failed: Username already exists";
                return false;
            }
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $this->errors[] = "Admin user creation failed: Email already exists";
                return false;
            }
            
            // Hash password securely
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert admin user with super_admin role for first user
            $stmt = $pdo->prepare("
                INSERT INTO admin_users (username, email, password_hash, role, is_active, created_at) 
                VALUES (?, ?, ?, 'super_admin', 1, NOW())
            ");
            
            $result = $stmt->execute([$username, $email, $passwordHash]);
            
            if (!$result) {
                $this->errors[] = "Admin user creation failed: Database insertion error";
                return false;
            }
            
            return true;
        } catch (PDOException $e) {
            $this->errors[] = "Admin user creation failed: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Generate configuration file with database credentials and security settings
     * @param array $dbConfig Database configuration
     * @return bool True if config file created successfully
     */
    public function generateConfig($dbConfig): bool {
        try {
            // Validate database configuration
            $requiredKeys = ['host', 'name', 'user', 'pass'];
            foreach ($requiredKeys as $key) {
                if (!isset($dbConfig[$key])) {
                    $this->errors[] = "Configuration generation failed: Missing database configuration key: {$key}";
                    return false;
                }
            }
            
            $configContent = $this->getConfigTemplate($dbConfig);
            
            $configPath = '../config/config.php';
            
            // Ensure config directory exists with proper permissions
            if (!is_dir('../config')) {
                if (!mkdir('../config', 0755, true)) {
                    $this->errors[] = "Configuration generation failed: Could not create config directory";
                    return false;
                }
            }
            
            // Check if config directory is writable
            if (!is_writable('../config')) {
                $this->errors[] = "Configuration generation failed: Config directory is not writable";
                return false;
            }
            
            // Write configuration file
            $result = file_put_contents($configPath, $configContent);
            
            if ($result === false) {
                $this->errors[] = "Configuration generation failed: Could not write configuration file";
                return false;
            }
            
            // Set proper permissions on config file
            if (!chmod($configPath, 0644)) {
                $this->warnings[] = "Could not set proper permissions on configuration file";
            }
            
            // Create .htaccess file to protect config directory
            $this->createConfigSecurity();
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Configuration file creation failed: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Clean up partial installation in case of failure
     * @param PDO $pdo Database connection
     * @return bool True if cleanup successful
     */
    public function cleanupFailedInstallation(PDO $pdo): bool {
        try {
            // Drop tables in reverse order to respect foreign key constraints
            $tables = ['files', 'submissions', 'form_fields', 'forms', 'admin_users'];
            
            foreach ($tables as $table) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                } catch (PDOException $e) {
                    // Continue with other tables even if one fails
                    $this->warnings[] = "Failed to drop table '{$table}': " . $e->getMessage();
                }
            }
            
            // Remove configuration file if it exists
            $configPath = '../config/config.php';
            if (file_exists($configPath)) {
                unlink($configPath);
            }
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Cleanup failed: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Get installation progress information
     * @param PDO $pdo Database connection
     * @return array Installation progress details
     */
    public function getInstallationProgress(PDO $pdo): array {
        $progress = [
            'tables_created' => 0,
            'total_tables' => 5,
            'tables' => [],
            'config_exists' => file_exists('../config/config.php'),
            'admin_user_exists' => false
        ];
        
        $requiredTables = ['forms', 'form_fields', 'submissions', 'files', 'admin_users'];
        
        foreach ($requiredTables as $table) {
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
                $stmt->execute([$table]);
                $exists = $stmt->rowCount() > 0;
                
                if ($exists) {
                    $progress['tables_created']++;
                }
                
                $progress['tables'][$table] = $exists;
            } catch (PDOException $e) {
                $progress['tables'][$table] = false;
            }
        }
        
        // Check if admin user exists
        if ($progress['tables']['admin_users']) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users");
                $result = $stmt->fetch();
                $progress['admin_user_exists'] = $result['count'] > 0;
            } catch (PDOException $e) {
                $progress['admin_user_exists'] = false;
            }
        }
        
        return $progress;
    }
    
    /**
     * Complete installation process with cleanup and redirection setup
     * @return bool True if completion successful
     */
    public function completeInstallation(): bool {
        try {
            // Create installation completion marker
            $completionMarker = '../config/.installation_complete';
            $completionData = [
                'completed_at' => date('Y-m-d H:i:s'),
                'version' => '1.0.0',
                'installer_version' => '1.0.0'
            ];
            
            file_put_contents($completionMarker, json_encode($completionData, JSON_PRETTY_PRINT));
            
            // Create necessary directories if they don't exist
            $this->createRequiredDirectories();
            
            // Create security files for all protected directories
            $this->createAllSecurityFiles();
            
            // Set proper file permissions
            $this->setFilePermissions();
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Installation completion failed: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Create all required directories for the application
     * @return bool True if directories created successfully
     */
    private function createRequiredDirectories(): bool {
        $directories = [
            '../uploads' => 0755,
            '../uploads/photos' => 0755,
            '../uploads/signatures' => 0755,
            '../uploads/files' => 0755,
            '../uploads/temp' => 0755,
            '../logs' => 0755,
            '../config' => 0755
        ];
        
        foreach ($directories as $dir => $permissions) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, $permissions, true)) {
                    $this->warnings[] = "Could not create directory: {$dir}";
                    return false;
                }
            }
            
            // Ensure proper permissions
            if (!chmod($dir, $permissions)) {
                $this->warnings[] = "Could not set permissions for directory: {$dir}";
            }
        }
        
        return true;
    }
    
    /**
     * Create security files for all protected directories
     * @return bool True if security files created successfully
     */
    private function createAllSecurityFiles(): bool {
        // Directories that need protection
        $protectedDirs = [
            '../uploads' => 'Upload directory - files should not be directly executable',
            '../config' => 'Configuration directory - contains sensitive information',
            '../logs' => 'Log directory - contains application logs',
            '../includes' => 'Includes directory - contains PHP libraries'
        ];
        
        foreach ($protectedDirs as $dir => $description) {
            if (is_dir($dir)) {
                $this->createDirectoryProtection($dir, $description);
            }
        }
        
        return true;
    }
    
    /**
     * Create protection files for a specific directory
     * @param string $dir Directory path
     * @param string $description Directory description
     */
    private function createDirectoryProtection($dir, $description): void {
        // Create .htaccess file
        $htaccessContent = "# {$description}
Options -Indexes
Options -ExecCGI

# Deny access to PHP files in upload directories
";
        
        if (strpos($dir, 'uploads') !== false) {
            $htaccessContent .= "<Files *.php>
    Order Deny,Allow
    Deny from all
</Files>

# Allow only specific file types
<FilesMatch \"\\.(jpg|jpeg|png|gif|pdf)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>
";
        } else {
            $htaccessContent .= "Order Deny,Allow
Deny from all
";
        }
        
        $htaccessPath = $dir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            file_put_contents($htaccessPath, $htaccessContent);
        }
        
        // Create index.php file
        $indexContent = "<?php
/**
 * {$description}
 * This file prevents directory listing and unauthorized access
 */
http_response_code(403);
exit('Access denied');
";
        
        $indexPath = $dir . '/index.php';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, $indexContent);
        }
    }
    
    /**
     * Set proper file permissions for security
     * @return bool True if permissions set successfully
     */
    private function setFilePermissions(): bool {
        $files = [
            '../config/config.php' => 0644,
            '../config/.htaccess' => 0644,
            '../config/index.php' => 0644
        ];
        
        foreach ($files as $file => $permissions) {
            if (file_exists($file)) {
                if (!chmod($file, $permissions)) {
                    $this->warnings[] = "Could not set permissions for file: {$file}";
                }
            }
        }
        
        return true;
    }
    
    /**
     * Check if installation should be redirected to admin panel
     * @return string|null Redirect URL or null if no redirect needed
     */
    public function getRedirectUrl(): ?string {
        if ($this->isInstalled() && file_exists('../config/.installation_complete')) {
            return '../admin/';
        }
        
        return null;
    }
    
    /**
     * Validate admin account creation data
     * @param array $adminData Admin account data
     * @return array Validation result with errors
     */
    public function validateAdminData($adminData): array {
        $errors = [];
        
        // Check required fields
        $requiredFields = ['username', 'email', 'password', 'password_confirm'];
        foreach ($requiredFields as $field) {
            if (empty($adminData[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        if (!empty($errors)) {
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Validate username
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $adminData['username'])) {
            $errors[] = "Username must be 3-50 characters and contain only letters, numbers, and underscores";
        }
        
        // Validate email
        if (!filter_var($adminData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address format";
        }
        
        // Validate password
        if (strlen($adminData['password']) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        // Check password confirmation
        if ($adminData['password'] !== $adminData['password_confirm']) {
            $errors[] = "Passwords do not match";
        }
        
        // Check password strength
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $adminData['password'])) {
            $errors[] = "Password must contain at least one lowercase letter, one uppercase letter, and one number";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get SQL for forms table creation
     * @return string SQL statement
     */
    private function getFormsTableSQL(): string {
        return "
            CREATE TABLE IF NOT EXISTS forms (
                id VARCHAR(36) PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                config JSON NOT NULL,
                share_link VARCHAR(100) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                INDEX idx_share_link (share_link),
                INDEX idx_created_at (created_at),
                INDEX idx_is_active (is_active),
                INDEX idx_title (title)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }
    
    /**
     * Get SQL for form_fields table creation
     * @return string SQL statement
     */
    private function getFormFieldsTableSQL(): string {
        return "
            CREATE TABLE IF NOT EXISTS form_fields (
                id INT AUTO_INCREMENT PRIMARY KEY,
                form_id VARCHAR(36) NOT NULL,
                field_name VARCHAR(100) NOT NULL,
                field_type ENUM('text', 'email', 'number', 'aadhar', 'select', 'radio', 'checkbox', 'file', 'photo', 'signature') NOT NULL,
                field_config JSON NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_required BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_form_fields_form_id FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE ON UPDATE CASCADE,
                INDEX idx_form_id (form_id),
                INDEX idx_sort_order (sort_order),
                INDEX idx_field_type (field_type),
                INDEX idx_field_name (field_name),
                UNIQUE KEY unique_form_field (form_id, field_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }
    
    /**
     * Get SQL for submissions table creation
     * @return string SQL statement
     */
    private function getSubmissionsTableSQL(): string {
        return "
            CREATE TABLE IF NOT EXISTS submissions (
                id VARCHAR(36) PRIMARY KEY,
                form_id VARCHAR(36) NOT NULL,
                submission_data JSON NOT NULL,
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT,
                status ENUM('pending', 'processed', 'archived') DEFAULT 'pending',
                CONSTRAINT fk_submissions_form_id FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE ON UPDATE CASCADE,
                INDEX idx_form_id (form_id),
                INDEX idx_submitted_at (submitted_at),
                INDEX idx_ip_address (ip_address),
                INDEX idx_status (status),
                INDEX idx_form_submitted (form_id, submitted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }
    
    /**
     * Get SQL for files table creation
     * @return string SQL statement
     */
    private function getFilesTableSQL(): string {
        return "
            CREATE TABLE IF NOT EXISTS files (
                id VARCHAR(36) PRIMARY KEY,
                submission_id VARCHAR(36) NOT NULL,
                field_name VARCHAR(100) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT UNSIGNED NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_hash VARCHAR(64),
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_processed BOOLEAN DEFAULT FALSE,
                CONSTRAINT fk_files_submission_id FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE ON UPDATE CASCADE,
                INDEX idx_submission_id (submission_id),
                INDEX idx_field_name (field_name),
                INDEX idx_uploaded_at (uploaded_at),
                INDEX idx_mime_type (mime_type),
                INDEX idx_file_hash (file_hash),
                INDEX idx_submission_field (submission_id, field_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }
    
    /**
     * Get SQL for admin_users table creation
     * @return string SQL statement
     */
    private function getAdminUsersTableSQL(): string {
        return "
            CREATE TABLE IF NOT EXISTS admin_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                login_attempts INT DEFAULT 0,
                locked_until TIMESTAMP NULL,
                is_active BOOLEAN DEFAULT TRUE,
                role ENUM('admin', 'super_admin') DEFAULT 'admin',
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_is_active (is_active),
                INDEX idx_role (role),
                INDEX idx_last_login (last_login)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }
    
    /**
     * Get exports table SQL
     * @return string SQL for creating exports table
     */
    private function getExportsTableSQL(): string {
        return "
            CREATE TABLE IF NOT EXISTS exports (
                id VARCHAR(36) PRIMARY KEY,
                form_id VARCHAR(36) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                filepath VARCHAR(500) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                downloaded_at TIMESTAMP NULL,
                download_count INT DEFAULT 0,
                INDEX idx_form_id (form_id),
                INDEX idx_expires_at (expires_at),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }
    
    /**
     * Get configuration file template with database credentials and security settings
     * @param array $dbConfig Database configuration
     * @return string Configuration file content
     */
    private function getConfigTemplate($dbConfig): string {
        // Generate secure random keys
        $securityKey = bin2hex(random_bytes(32));
        $csrfKey = bin2hex(random_bytes(16));
        $sessionKey = bin2hex(random_bytes(16));
        
        // Escape database credentials for PHP
        $host = addslashes($dbConfig['host']);
        $name = addslashes($dbConfig['name']);
        $user = addslashes($dbConfig['user']);
        $pass = addslashes($dbConfig['pass']);
        
        return "<?php
/**
 * Student Enrollment Platform Configuration
 * Generated automatically during installation on " . date('Y-m-d H:i:s') . "
 * 
 * WARNING: This file contains sensitive information. 
 * Do not share or commit this file to version control.
 */

// Prevent direct access
if (!defined('SEFP_INIT')) {
    define('SEFP_INIT', true);
}

// Database Configuration
define('DB_HOST', '{$host}');
define('DB_NAME', '{$name}');
define('DB_USER', '{$user}');
define('DB_PASS', '{$pass}');
define('DB_CHARSET', 'utf8mb4');

// Security Configuration
define('SECURITY_KEY', '{$securityKey}');
define('CSRF_SECRET_KEY', '{$csrfKey}');
define('SESSION_SECRET_KEY', '{$sessionKey}');
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CSRF_TOKEN_EXPIRY', 1800); // 30 minutes in seconds
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/uploads/');

// Image Processing Configuration
define('IMAGE_MAX_WIDTH', 1024);
define('IMAGE_MAX_HEIGHT', 1024);
define('IMAGE_QUALITY', 85);
define('THUMBNAIL_SIZE', 150);

// Application Configuration
define('APP_NAME', 'Student Enrollment Platform');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', false);
define('APP_INSTALLED', true);

// Paths Configuration
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Timezone Configuration
date_default_timezone_set('Asia/Kolkata');

// Error Reporting Configuration
if (defined('APP_DEBUG') && APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Error Log Configuration
ini_set('error_log', LOGS_PATH . '/php_errors.log');

// Session Configuration
ini_set('session.name', 'SEFP_SESSION');
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] === 'on');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_samesite', 'Strict');

// Security Headers Configuration
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Installation marker
define('SEFP_INSTALLED', true);
define('INSTALLED', true);
";
    }
    
    /**
     * Create security files to protect configuration directory
     * @return bool True if security files created successfully
     */
    private function createConfigSecurity(): bool {
        try {
            // Create .htaccess file to deny web access to config directory
            $htaccessContent = "# Deny all web access to config directory
Order Deny,Allow
Deny from all

# Prevent execution of PHP files
<Files *.php>
    Order Deny,Allow
    Deny from all
</Files>

# Prevent access to sensitive files
<FilesMatch \"\\.(conf|config|ini|log|bak|backup|old|tmp)$\">
    Order Deny,Allow
    Deny from all
</FilesMatch>
";
            
            $htaccessPath = '../config/.htaccess';
            if (!file_exists($htaccessPath)) {
                file_put_contents($htaccessPath, $htaccessContent);
            }
            
            // Create index.php to prevent directory listing
            $indexContent = "<?php
// Prevent directory listing
http_response_code(403);
exit('Access denied');
";
            
            $indexPath = '../config/index.php';
            if (!file_exists($indexPath)) {
                file_put_contents($indexPath, $indexContent);
            }
            
            return true;
        } catch (Exception $e) {
            $this->warnings[] = "Could not create security files: " . $e->getMessage();
            return false;
        }
    }
}