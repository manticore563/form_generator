# Design Document

## Overview

The Student Enrollment Form Platform is a web-based application that provides a comprehensive solution for college student enrollment data collection. Built with HTML, CSS, JavaScript frontend and PHP/MySQL backend, the platform offers dynamic form creation, secure file handling with image cropping capabilities, administrative dashboard, and automated installation process. The system is designed for deployment on shared hosting platforms with minimal technical requirements.

## Architecture

### System Architecture
The platform follows a traditional three-tier architecture:

1. **Presentation Layer**: Responsive web interface built with HTML5, CSS3, and vanilla JavaScript
2. **Application Layer**: PHP-based backend handling business logic, form processing, and file management
3. **Data Layer**: MySQL database for storing form configurations, submissions, and user data

### Technology Stack
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Canvas API for image cropping
- **Backend**: PHP 7.4+, PDO for database operations
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **File Storage**: Server filesystem with organized directory structure
- **Security**: PHP sessions, input sanitization, CSRF protection

### Deployment Model
- Single-package deployment suitable for shared hosting
- Automatic installer with database setup and configuration
- Minimal server requirements (PHP, MySQL, basic file permissions)

## Components and Interfaces

### 1. Installer Module (`/install/`)
**Purpose**: Automated setup and configuration system

**Key Components**:
- `index.php`: Installation wizard interface
- `setup.php`: Database creation and initial configuration
- `config-template.php`: Configuration file template

**Interface**:
```php
class Installer {
    public function checkRequirements(): array
    public function testDatabaseConnection($host, $user, $pass, $db): bool
    public function createTables(): bool
    public function createAdminUser($username, $password, $email): bool
    public function generateConfig($dbConfig): bool
}
```

### 2. Authentication System (`/auth/`)
**Purpose**: User authentication and session management

**Key Components**:
- `login.php`: Admin login interface
- `AuthManager.php`: Authentication logic
- `session.php`: Session handling utilities

**Interface**:
```php
class AuthManager {
    public function authenticate($username, $password): bool
    public function createSession($userId): string
    public function validateSession($sessionId): bool
    public function logout(): void
}
```

### 3. Form Builder (`/admin/forms/`)
**Purpose**: Dynamic form creation and management

**Key Components**:
- `builder.php`: Drag-and-drop form builder interface
- `FormManager.php`: Form CRUD operations
- `FieldTypes.php`: Field type definitions and validation

**Interface**:
```php
class FormManager {
    public function createForm($title, $description): string
    public function addField($formId, $fieldConfig): bool
    public function updateForm($formId, $config): bool
    public function deleteForm($formId): bool
    public function getFormConfig($formId): array
    public function generateShareLink($formId): string
}
```

### 4. Form Renderer (`/forms/`)
**Purpose**: Public form display and submission handling

**Key Components**:
- `view.php`: Form rendering engine
- `submit.php`: Form submission processor
- `FormRenderer.php`: Dynamic form generation

**Interface**:
```php
class FormRenderer {
    public function renderForm($formId): string
    public function validateSubmission($formId, $data): array
    public function processSubmission($formId, $data, $files): string
    public function generateSubmissionId(): string
}
```

### 5. File Upload System (`/uploads/`)
**Purpose**: Secure file handling with image processing

**Key Components**:
- `upload.php`: File upload endpoint
- `ImageProcessor.php`: Image cropping and optimization
- `FileManager.php`: File storage and retrieval

**Interface**:
```php
class ImageProcessor {
    public function validateImage($file): bool
    public function cropImage($imagePath, $cropData): string
    public function optimizeImage($imagePath, $quality = 85): bool
    public function generateThumbnail($imagePath): string
}

class FileManager {
    public function storeFile($file, $submissionId, $fieldName): string
    public function getFileUrl($fileId): string
    public function deleteFile($fileId): bool
    public function getFilesBySubmission($submissionId): array
}
```

### 6. Admin Dashboard (`/admin/`)
**Purpose**: Administrative interface for form and data management

**Key Components**:
- `dashboard.php`: Main admin interface
- `submissions.php`: Submission viewing and management
- `export.php`: Data export functionality

**Interface**:
```php
class SubmissionManager {
    public function getSubmissions($formId, $filters = []): array
    public function getSubmissionDetails($submissionId): array
    public function exportToCSV($formId): string
    public function deleteSubmission($submissionId): bool
}
```

## Data Models

### Database Schema

#### Forms Table
```sql
CREATE TABLE forms (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    config JSON NOT NULL,
    share_link VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);
```

#### Form Fields Table
```sql
CREATE TABLE form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id VARCHAR(36) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'email', 'number', 'aadhar', 'select', 'radio', 'checkbox', 'file', 'photo', 'signature') NOT NULL,
    field_config JSON NOT NULL,
    sort_order INT NOT NULL,
    is_required BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
);
```

#### Submissions Table
```sql
CREATE TABLE submissions (
    id VARCHAR(36) PRIMARY KEY,
    form_id VARCHAR(36) NOT NULL,
    submission_data JSON NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
);
```

#### Files Table
```sql
CREATE TABLE files (
    id VARCHAR(36) PRIMARY KEY,
    submission_id VARCHAR(36) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);
```

#### Admin Users Table
```sql
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);
```

### Data Flow Models

#### Form Creation Flow
1. Admin creates form → Form record created with unique ID
2. Admin adds fields → Field configurations stored in form_fields table
3. Form published → Share link generated and activated

#### Submission Flow
1. Student accesses form → Form config retrieved and rendered
2. Student fills form → Client-side validation performed
3. Files uploaded → Server processing and cropping applied
4. Form submitted → Data validated and stored in submissions table
5. Files linked → File records created with submission reference

## Error Handling

### Client-Side Error Handling
- **Form Validation**: Real-time field validation with user-friendly messages
- **File Upload Errors**: Clear feedback for file size, type, and upload failures
- **Network Errors**: Retry mechanisms and offline detection
- **Image Cropping Errors**: Fallback options and error recovery

### Server-Side Error Handling
- **Database Errors**: Transaction rollback and error logging
- **File System Errors**: Graceful degradation and cleanup procedures
- **Authentication Errors**: Secure error messages without information disclosure
- **Input Validation**: Comprehensive sanitization and validation

### Error Logging Strategy
```php
class ErrorLogger {
    public function logError($level, $message, $context = []): void
    public function logSecurityEvent($event, $details): void
    public function logFileOperation($operation, $result): void
}
```

### Recovery Mechanisms
- **Database Connection Failures**: Connection pooling and retry logic
- **File Upload Failures**: Chunked upload support and resume capability
- **Session Timeouts**: Automatic session extension and data preservation
- **Installation Failures**: Step-by-step recovery and rollback options

## Testing Strategy

### Unit Testing
- **PHP Classes**: PHPUnit tests for all business logic classes
- **JavaScript Functions**: Jest tests for client-side functionality
- **Database Operations**: Isolated tests with test database
- **File Operations**: Mock file system for upload/crop testing

### Integration Testing
- **Form Workflow**: End-to-end form creation to submission testing
- **File Upload Pipeline**: Complete file processing workflow testing
- **Authentication Flow**: Login, session management, and logout testing
- **Export Functionality**: CSV generation and file link validation

### Security Testing
- **Input Validation**: SQL injection and XSS prevention testing
- **File Upload Security**: Malicious file detection and prevention
- **Authentication Security**: Session hijacking and brute force protection
- **Access Control**: Unauthorized access prevention testing

### Performance Testing
- **Form Rendering**: Large form performance optimization
- **File Upload**: Multiple concurrent upload handling
- **Database Queries**: Query optimization and indexing validation
- **Export Operations**: Large dataset export performance

### Browser Compatibility Testing
- **Cross-Browser**: Chrome, Firefox, Safari, Edge compatibility
- **Mobile Devices**: iOS and Android responsive design testing
- **Image Cropping**: Canvas API compatibility across browsers
- **File Upload**: HTML5 file API support validation

### Installation Testing
- **Shared Hosting**: Various hosting provider compatibility
- **PHP Versions**: PHP 7.4+ compatibility validation
- **MySQL Versions**: Database version compatibility testing
- **Permission Requirements**: Minimal permission setup validation