<?php
require_once 'Installer.php';

$installer = new Installer();

// Check if already installed
if ($installer->isInstalled()) {
    header('Location: ../admin/');
    exit;
}

// Handle AJAX requests for requirement checking and database testing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_requirements':
            $requirements = $installer->checkRequirements();
            echo json_encode([
                'success' => true,
                'requirements' => $requirements,
                'errors' => $installer->getErrors(),
                'warnings' => $installer->getWarnings()
            ]);
            exit;
            
        case 'test_database':
            $host = $_POST['db_host'] ?? '';
            $user = $_POST['db_user'] ?? '';
            $pass = $_POST['db_pass'] ?? '';
            $name = $_POST['db_name'] ?? '';
            
            $success = $installer->testDatabaseConnection($host, $user, $pass, $name);
            echo json_encode([
                'success' => $success,
                'errors' => $installer->getErrors()
            ]);
            exit;
            
        case 'create_tables':
            $host = $_POST['db_host'] ?? '';
            $user = $_POST['db_user'] ?? '';
            $pass = $_POST['db_pass'] ?? '';
            $name = $_POST['db_name'] ?? '';
            
            try {
                $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                
                $success = $installer->createTables($pdo);
                $response = [
                    'success' => $success,
                    'errors' => $installer->getErrors(),
                    'warnings' => $installer->getWarnings()
                ];
                
                if ($success) {
                    $response['progress'] = $installer->getInstallationProgress($pdo);
                }
                
                echo json_encode($response);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'errors' => ['Database connection failed: ' . $e->getMessage()]
                ]);
            }
            exit;
            
        case 'cleanup_installation':
            $host = $_POST['db_host'] ?? '';
            $user = $_POST['db_user'] ?? '';
            $pass = $_POST['db_pass'] ?? '';
            $name = $_POST['db_name'] ?? '';
            
            try {
                $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                
                $success = $installer->cleanupFailedInstallation($pdo);
                echo json_encode([
                    'success' => $success,
                    'errors' => $installer->getErrors(),
                    'warnings' => $installer->getWarnings()
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'errors' => ['Database connection failed: ' . $e->getMessage()]
                ]);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment Platform - Installation</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .installer-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
        }
        
        .installer-header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .installer-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .installer-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .installer-content {
            padding: 40px;
        }
        
        .step {
            display: none;
        }
        
        .step.active {
            display: block;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            margin: 0 10px;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ecf0f1;
            color: #7f8c8d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .step-number.active {
            background: #3498db;
            color: white;
        }
        
        .step-number.completed {
            background: #27ae60;
            color: white;
        }
        
        .step-title {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group {
            margin-bottom: 25px;
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
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            margin-right: 10px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .requirement-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border: 2px solid #ecf0f1;
        }
        
        .requirement-item.passed {
            border-color: #27ae60;
            background: #d5f4e6;
        }
        
        .requirement-item.failed {
            border-color: #e74c3c;
            background: #fdf2f2;
        }
        
        .requirement-item.warning {
            border-color: #f39c12;
            background: #fef9e7;
        }
        
        .requirement-status {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .requirement-status.passed {
            background: #27ae60;
        }
        
        .requirement-status.failed {
            background: #e74c3c;
        }
        
        .requirement-status.warning {
            background: #f39c12;
        }
        
        .requirement-details h4 {
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .requirement-details p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d5f4e6;
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        
        .alert-error {
            background: #fdf2f2;
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }
        
        .alert-warning {
            background: #fef9e7;
            border: 1px solid #f39c12;
            color: #f39c12;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            .installer-container {
                margin: 10px;
            }
            
            .installer-content {
                padding: 20px;
            }
            
            .step-indicator {
                flex-direction: column;
                align-items: center;
            }
            
            .step-item {
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>Student Enrollment Platform</h1>
            <p>Installation Wizard</p>
        </div>
        
        <div class="installer-content">
            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step-item">
                    <div class="step-number active" id="step-1-indicator">1</div>
                    <div class="step-title">Requirements</div>
                </div>
                <div class="step-item">
                    <div class="step-number" id="step-2-indicator">2</div>
                    <div class="step-title">Database</div>
                </div>
                <div class="step-item">
                    <div class="step-number" id="step-3-indicator">3</div>
                    <div class="step-title">Admin Account</div>
                </div>
                <div class="step-item">
                    <div class="step-number" id="step-4-indicator">4</div>
                    <div class="step-title">Complete</div>
                </div>
            </div>
            
            <!-- Step 1: Requirements Check -->
            <div class="step active" id="step-1">
                <h2 style="margin-bottom: 30px; color: #2c3e50;">System Requirements Check</h2>
                
                <div id="requirements-loading" class="alert alert-warning">
                    <div class="loading"></div>
                    Checking system requirements...
                </div>
                
                <div id="requirements-results" class="hidden">
                    <div id="requirements-list"></div>
                    
                    <div id="requirements-summary"></div>
                    
                    <div style="margin-top: 30px;">
                        <button type="button" class="btn" id="btn-next-1" onclick="nextStep()" disabled>
                            Continue to Database Setup
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="checkRequirements()">
                            Recheck Requirements
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Step 2: Database Configuration -->
            <div class="step" id="step-2">
                <h2 style="margin-bottom: 30px; color: #2c3e50;">Database Configuration</h2>
                
                <form id="database-form">
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
                    
                    <div id="database-test-result"></div>
                    
                    <div style="margin-top: 30px;">
                        <button type="button" class="btn" id="btn-next-2" onclick="nextStep()" disabled>
                            Continue to Admin Setup
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="testDatabase()">
                            Test Connection
                        </button>
                        <button type="button" class="btn btn-secondary" id="btn-create-tables" onclick="createTables()" disabled>
                            Create Tables
                        </button>
                        <button type="button" class="btn btn-secondary" id="btn-cleanup" onclick="cleanupInstallation()" style="display: none; background: #e74c3c;">
                            Cleanup Failed Installation
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">
                            Back
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Step 3: Admin Account Setup -->
            <div class="step" id="step-3">
                <h2 style="margin-bottom: 30px; color: #2c3e50;">Admin Account Setup</h2>
                
                <form id="admin-form">
                    <div class="form-group">
                        <label for="admin_username">Admin Username</label>
                        <input type="text" id="admin_username" name="admin_username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Admin Email</label>
                        <input type="email" id="admin_email" name="admin_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">Admin Password</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password_confirm">Confirm Password</label>
                        <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                    </div>
                    
                    <div id="admin-validation-result"></div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <label>
                            <input type="checkbox" id="drop_existing" name="drop_existing" value="true" style="width: auto; margin-right: 8px;">
                            Drop existing tables if they exist (use this if you're reinstalling)
                        </label>
                        <small style="color: #7f8c8d; display: block; margin-top: 5px;">
                            ⚠️ Warning: This will permanently delete all existing data in the database.
                        </small>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="button" class="btn" id="btn-install" onclick="startInstallation()" disabled>
                            Install Platform
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="validateAdminForm()">
                            Validate Account
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">
                            Back
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Step 4: Installation Complete -->
            <div class="step" id="step-4">
                <h2 style="margin-bottom: 30px; color: #2c3e50;">Installation Complete</h2>
                
                <div class="alert alert-success">
                    <h3>🎉 Installation Successful!</h3>
                    <p>The Student Enrollment Platform has been successfully installed and configured.</p>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="button" class="btn" onclick="window.location.href='../admin/'">
                        Go to Admin Dashboard
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        let requirementsPassed = false;
        let databaseTested = false;
        let tablesCreated = false;
        
        // Initialize installer
        document.addEventListener('DOMContentLoaded', function() {
            checkRequirements();
        });
        
        function checkRequirements() {
            document.getElementById('requirements-loading').classList.remove('hidden');
            document.getElementById('requirements-results').classList.add('hidden');
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_requirements'
            })
            .then(response => response.json())
            .then(data => {
                displayRequirements(data);
                document.getElementById('requirements-loading').classList.add('hidden');
                document.getElementById('requirements-results').classList.remove('hidden');
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('requirements-loading').innerHTML = 
                    '<div class="alert alert-error">Error checking requirements. Please refresh and try again.</div>';
            });
        }
        
        function displayRequirements(data) {
            const container = document.getElementById('requirements-list');
            const summary = document.getElementById('requirements-summary');
            
            container.innerHTML = '';
            
            let allPassed = true;
            let hasWarnings = false;
            
            // Display PHP version
            const phpReq = data.requirements.php_version;
            container.appendChild(createRequirementItem(
                phpReq.name,
                `Required: ${phpReq.required}, Current: ${phpReq.current}`,
                phpReq.passed,
                phpReq.critical
            ));
            
            if (!phpReq.passed) allPassed = false;
            
            // Display extensions
            data.requirements.extensions.forEach(ext => {
                container.appendChild(createRequirementItem(
                    `PHP Extension: ${ext.name}`,
                    ext.description,
                    ext.loaded,
                    ext.critical
                ));
                
                if (!ext.loaded && ext.critical) allPassed = false;
                if (!ext.loaded && !ext.critical) hasWarnings = true;
            });
            
            // Display permissions
            data.requirements.permissions.forEach(perm => {
                const status = perm.passed;
                const description = `${perm.description} (${perm.needs_write ? 'Read/Write' : 'Read'})`;
                
                container.appendChild(createRequirementItem(
                    `Directory: ${perm.name}`,
                    description,
                    status,
                    perm.critical
                ));
                
                if (!status) allPassed = false;
            });
            
            // Display functions
            data.requirements.functions.forEach(func => {
                container.appendChild(createRequirementItem(
                    `PHP Function: ${func.name}`,
                    func.description,
                    func.exists,
                    func.critical
                ));
                
                if (!func.exists) allPassed = false;
            });
            
            // Display summary
            if (allPassed) {
                summary.innerHTML = '<div class="alert alert-success">✅ All requirements passed! You can proceed with the installation.</div>';
                requirementsPassed = true;
                document.getElementById('btn-next-1').disabled = false;
            } else {
                summary.innerHTML = '<div class="alert alert-error">❌ Some requirements failed. Please fix the issues above before proceeding.</div>';
                requirementsPassed = false;
                document.getElementById('btn-next-1').disabled = true;
            }
            
            if (hasWarnings && allPassed) {
                summary.innerHTML += '<div class="alert alert-warning">⚠️ Some optional features may not be available due to missing extensions.</div>';
            }
        }
        
        function createRequirementItem(name, description, passed, critical) {
            const item = document.createElement('div');
            item.className = `requirement-item ${passed ? 'passed' : (critical ? 'failed' : 'warning')}`;
            
            const status = document.createElement('div');
            status.className = `requirement-status ${passed ? 'passed' : (critical ? 'failed' : 'warning')}`;
            status.textContent = passed ? '✓' : '✗';
            
            const details = document.createElement('div');
            details.className = 'requirement-details';
            details.innerHTML = `<h4>${name}</h4><p>${description}</p>`;
            
            item.appendChild(status);
            item.appendChild(details);
            
            return item;
        }
        
        function testDatabase() {
            const form = document.getElementById('database-form');
            const formData = new FormData(form);
            formData.append('action', 'test_database');
            
            const resultDiv = document.getElementById('database-test-result');
            resultDiv.innerHTML = '<div class="alert alert-warning"><div class="loading"></div>Testing database connection...</div>';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success">✅ Database connection successful! You can now create the database tables.</div>';
                    databaseTested = true;
                    document.getElementById('btn-create-tables').disabled = false;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-error">❌ Database connection failed: ${data.errors.join(', ')}</div>`;
                    databaseTested = false;
                    document.getElementById('btn-create-tables').disabled = true;
                    document.getElementById('btn-next-2').disabled = true;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-error">Error testing database connection. Please try again.</div>';
            });
        }
        
        function createTables() {
            const form = document.getElementById('database-form');
            const formData = new FormData(form);
            formData.append('action', 'create_tables');
            
            const resultDiv = document.getElementById('database-test-result');
            const currentContent = resultDiv.innerHTML;
            resultDiv.innerHTML = currentContent + '<div class="alert alert-warning"><div class="loading"></div>Creating database tables...</div>';
            
            // Disable create button during operation
            document.getElementById('btn-create-tables').disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let successMessage = '<div class="alert alert-success">✅ Database tables created successfully!</div>';
                    
                    // Show progress if available
                    if (data.progress) {
                        successMessage += `<div class="alert alert-success">
                            📊 Progress: ${data.progress.tables_created}/${data.progress.total_tables} tables created
                        </div>`;
                    }
                    
                    // Show warnings if any
                    if (data.warnings && data.warnings.length > 0) {
                        successMessage += `<div class="alert alert-warning">
                            ⚠️ Warnings: ${data.warnings.join(', ')}
                        </div>`;
                    }
                    
                    resultDiv.innerHTML = currentContent + successMessage;
                    tablesCreated = true;
                    document.getElementById('btn-next-2').disabled = false;
                    document.getElementById('btn-create-tables').style.display = 'none';
                    document.getElementById('btn-cleanup').style.display = 'none';
                } else {
                    const errorMessage = `<div class="alert alert-error">❌ Table creation failed: ${data.errors.join(', ')}</div>`;
                    resultDiv.innerHTML = currentContent + errorMessage;
                    tablesCreated = false;
                    document.getElementById('btn-next-2').disabled = true;
                    document.getElementById('btn-create-tables').disabled = false;
                    document.getElementById('btn-cleanup').style.display = 'inline-block';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = currentContent + '<div class="alert alert-error">Error creating database tables. Please try again.</div>';
                document.getElementById('btn-create-tables').disabled = false;
                document.getElementById('btn-cleanup').style.display = 'inline-block';
            });
        }
        
        function cleanupInstallation() {
            if (!confirm('This will remove all partially created tables and configuration. Are you sure?')) {
                return;
            }
            
            const form = document.getElementById('database-form');
            const formData = new FormData(form);
            formData.append('action', 'cleanup_installation');
            
            const resultDiv = document.getElementById('database-test-result');
            const currentContent = resultDiv.innerHTML;
            resultDiv.innerHTML = currentContent + '<div class="alert alert-warning"><div class="loading"></div>Cleaning up installation...</div>';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success">✅ Installation cleanup completed. You can try creating tables again.</div>';
                    document.getElementById('btn-create-tables').disabled = false;
                    document.getElementById('btn-cleanup').style.display = 'none';
                    tablesCreated = false;
                } else {
                    resultDiv.innerHTML = currentContent + `<div class="alert alert-error">❌ Cleanup failed: ${data.errors.join(', ')}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = currentContent + '<div class="alert alert-error">Error during cleanup. Please try again.</div>';
            });
        }
        
        function nextStep() {
            if (currentStep < 4) {
                document.getElementById(`step-${currentStep}`).classList.remove('active');
                document.getElementById(`step-${currentStep}-indicator`).classList.remove('active');
                document.getElementById(`step-${currentStep}-indicator`).classList.add('completed');
                
                currentStep++;
                
                document.getElementById(`step-${currentStep}`).classList.add('active');
                document.getElementById(`step-${currentStep}-indicator`).classList.add('active');
            }
        }
        
        function prevStep() {
            if (currentStep > 1) {
                document.getElementById(`step-${currentStep}`).classList.remove('active');
                document.getElementById(`step-${currentStep}-indicator`).classList.remove('active');
                
                currentStep--;
                
                document.getElementById(`step-${currentStep}`).classList.add('active');
                document.getElementById(`step-${currentStep}-indicator`).classList.remove('completed');
                document.getElementById(`step-${currentStep}-indicator`).classList.add('active');
            }
        }
        
        function validateAdminForm() {
            const form = document.getElementById('admin-form');
            const formData = new FormData(form);
            formData.append('action', 'validate_admin');
            
            const resultDiv = document.getElementById('admin-validation-result');
            resultDiv.innerHTML = '<div class="alert alert-warning"><div class="loading"></div>Validating admin account data...</div>';
            
            fetch('install-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success">✅ Admin account data is valid. Ready to install!</div>';
                    document.getElementById('btn-install').disabled = false;
                } else {
                    const errorList = data.errors.map(error => `<li>${error}</li>`).join('');
                    resultDiv.innerHTML = `<div class="alert alert-error">❌ Validation failed:<ul>${errorList}</ul></div>`;
                    document.getElementById('btn-install').disabled = true;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-error">Validation error. Please try again.</div>';
                document.getElementById('btn-install').disabled = true;
            });
        }
        
        function startInstallation() {
            // Validate admin form first
            const form = document.getElementById('admin-form');
            const formData = new FormData(form);
            
            // Add database configuration
            const dbForm = document.getElementById('database-form');
            const dbFormData = new FormData(dbForm);
            
            for (let [key, value] of dbFormData.entries()) {
                formData.append(key, value);
            }
            
            formData.append('action', 'install');
            
            const resultDiv = document.getElementById('admin-validation-result');
            resultDiv.innerHTML = '<div class="alert alert-warning"><div class="loading"></div>Installing platform...</div>';
            
            // Disable install button
            document.getElementById('btn-install').disabled = true;
            
            fetch('install-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let successMessage = '<div class="alert alert-success">✅ Installation completed successfully!</div>';
                    
                    // Show warnings if any
                    if (data.warnings && data.warnings.length > 0) {
                        successMessage += `<div class="alert alert-warning">⚠️ Warnings: ${data.warnings.join(', ')}</div>`;
                    }
                    
                    resultDiv.innerHTML = successMessage;
                    
                    // Move to completion step
                    setTimeout(() => {
                        nextStep();
                    }, 1000);
                } else {
                    let errorMessage = `<div class="alert alert-error">❌ Installation failed: ${data.error}</div>`;
                    
                    // Add cleanup link for table existence errors
                    if (data.error.includes('tables already exist') || data.error.includes('Database tables already exist')) {
                        errorMessage += `<div class="alert alert-warning">
                            💡 <strong>Solution:</strong> You can either:
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>Check the "Drop existing tables" option above and try again</li>
                                <li>Use the <a href="cleanup-database.php" target="_blank" style="color: #856404; text-decoration: underline;">Database Cleanup Tool</a> to remove existing tables</li>
                            </ul>
                        </div>`;
                    }
                    
                    // Show warnings if any
                    if (data.warnings && data.warnings.length > 0) {
                        errorMessage += `<div class="alert alert-warning">⚠️ Warnings: ${data.warnings.join(', ')}</div>`;
                    }
                    
                    resultDiv.innerHTML = errorMessage;
                    document.getElementById('btn-install').disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="alert alert-error">Installation error. Please try again.</div>';
                document.getElementById('btn-install').disabled = false;
            });
        }
        
        // Enhanced form validation for admin account
        document.addEventListener('DOMContentLoaded', function() {
            // Real-time password confirmation validation
            document.getElementById('admin_password_confirm').addEventListener('input', function() {
                const password = document.getElementById('admin_password').value;
                const confirm = this.value;
                const resultDiv = document.getElementById('admin-validation-result');
                
                if (password && confirm) {
                    if (password === confirm) {
                        resultDiv.innerHTML = '<div class="alert alert-success">✅ Passwords match</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="alert alert-error">❌ Passwords do not match</div>';
                    }
                } else {
                    resultDiv.innerHTML = '';
                }
            });
            
            // Validate admin form on input changes
            const adminInputs = ['admin_username', 'admin_email', 'admin_password', 'admin_password_confirm'];
            adminInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('blur', function() {
                        // Only validate if all required fields are filled
                        const allFilled = adminInputs.every(id => {
                            const el = document.getElementById(id);
                            return el && el.value.trim() !== '';
                        });
                        
                        if (allFilled) {
                            validateAdminForm();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>