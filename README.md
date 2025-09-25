# Student Enrollment Form Platform

A comprehensive web-based platform for creating and managing student enrollment forms with advanced features including image cropping, file uploads, Aadhar validation, and robust security measures.

## Features

### Core Functionality
- **Dynamic Form Builder**: Drag-and-drop interface for creating custom forms
- **Multiple Field Types**: Text, email, phone, date, dropdown, radio, checkbox, file upload, image upload
- **Aadhar Validation**: Built-in validation for Indian Aadhar numbers with checksum verification
- **Image Cropping**: Advanced image cropping functionality with preview
- **File Management**: Secure file upload and storage system
- **Data Export**: Multiple export formats (CSV, Excel, PDF, JSON)

### Security Features
- **CSRF Protection**: Cross-site request forgery protection on all forms
- **Input Validation**: Comprehensive input sanitization and validation
- **File Security**: Malware scanning and secure file storage
- **Session Management**: Secure session handling with timeout protection
- **Access Control**: Role-based access control system
- **Audit Logging**: Comprehensive logging of all system activities

### Administrative Tools
- **System Health Monitoring**: Real-time system health checks and diagnostics
- **Error Logging**: Centralized error logging and monitoring system
- **Performance Monitoring**: Database and system performance metrics
- **Backup Management**: Automated backup and recovery procedures
- **User Management**: Admin user management with permission levels

## System Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7+ or MariaDB 10.2+
- **Web Server**: Apache or Nginx
- **Storage**: Minimum 100MB free space
- **Memory**: 128MB PHP memory limit (recommended: 256MB)

### PHP Extensions
- PDO and PDO_MySQL
- GD or Imagick (for image processing)
- JSON
- Session
- Filter
- Fileinfo

## Installation

### Quick Installation
1. Download and extract the platform files to your web server
2. Navigate to `http://yourdomain.com/install/`
3. Follow the installation wizard
4. Delete the `/install/` directory after completion

### Manual Installation
1. Create a MySQL database and user
2. Copy `config/config-template.php` to `config/config.php`
3. Edit database credentials in `config/config.php`
4. Import the database schema from `install/schema.sql`
5. Set proper file permissions (see deployment guide)

## Documentation

### User Guides
- **[User Guide](USER_GUIDE.md)**: Complete guide for end users
- **[Admin Guide](ADMIN_GUIDE.md)**: Administrator documentation
- **[Deployment Guide](DEPLOYMENT.md)**: Installation and deployment instructions

### Technical Documentation
- **API Documentation**: Available in `/docs/api/`
- **Database Schema**: Available in `/docs/database/`
- **Security Guidelines**: Available in `/docs/security/`

## Directory Structure

```
platform/
├── admin/                  # Admin panel interface
│   ├── index.php          # Admin dashboard
│   ├── forms.php          # Form management
│   ├── form-builder.php   # Form builder interface
│   ├── submissions.php    # Submission management
│   ├── system-health.php  # System health monitoring
│   └── monitoring-dashboard.php # Real-time monitoring
├── auth/                   # Authentication system
│   ├── index.php          # Login interface
│   ├── AuthManager.php    # Authentication logic
│   └── SessionManager.php # Session management
├── forms/                  # Public form interface
│   ├── view.php           # Form display
│   ├── FormRenderer.php   # Form rendering engine
│   └── FormSubmissionHandler.php # Submission processing
├── includes/               # Core system files
│   ├── Database.php       # Database connection
│   ├── ErrorLogger.php    # Error logging system
│   ├── SecurityUtils.php  # Security utilities
│   ├── InputValidator.php # Input validation
│   └── AadharValidator.php # Aadhar number validation
├── uploads/                # File upload system
│   ├── FileManager.php    # File management
│   ├── ImageProcessor.php # Image processing
│   └── files/             # Uploaded files storage
├── assets/                 # Static assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── images/            # System images
├── config/                 # Configuration files
├── logs/                   # System logs
└── tests/                  # Test suite
```

## Configuration

### Basic Configuration
Edit `config/config.php` to configure:

```php
// Database Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// Security Settings
define('CSRF_SECRET', 'your_secret_key');
define('SESSION_TIMEOUT', 3600);

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('UPLOAD_PATH', 'uploads/files/');
```

### Advanced Configuration
- **Security Settings**: Configure CSRF protection, session security
- **File Upload**: Set file type restrictions, size limits
- **Image Processing**: Configure image quality, dimensions
- **Logging**: Set log levels, rotation policies
- **Performance**: Configure caching, optimization settings

## Usage

### Creating Forms
1. Access the admin panel at `/admin/`
2. Navigate to "Forms" → "Create New Form"
3. Use the drag-and-drop form builder to add fields
4. Configure field validation and properties
5. Save and publish the form

### Managing Submissions
1. Go to "Submissions" in the admin panel
2. View, filter, and search submissions
3. Export data in various formats
4. Process and manage submitted files

### System Monitoring
1. Access "System Health" for health checks
2. Use "Monitoring Dashboard" for real-time metrics
3. Review logs for errors and security events
4. Monitor performance and resource usage

## Security

### Security Features
- **Input Validation**: All user input is validated and sanitized
- **CSRF Protection**: Protection against cross-site request forgery
- **File Security**: Secure file upload with malware scanning
- **Session Security**: Secure session management with timeout
- **Access Control**: Role-based permissions system
- **Audit Logging**: Comprehensive activity logging

### Security Best Practices
- Use HTTPS in production environments
- Regularly update PHP and MySQL
- Monitor security logs for suspicious activity
- Implement strong password policies
- Regular security audits and updates

## Performance

### Optimization Features
- **Database Indexing**: Optimized database queries
- **File Caching**: Static file caching for better performance
- **Image Optimization**: Automatic image compression
- **Log Rotation**: Automatic log file management
- **Resource Monitoring**: Real-time performance monitoring

### Performance Tips
- Enable PHP OPcache for better performance
- Use a CDN for static assets
- Implement database query optimization
- Regular maintenance and cleanup tasks
- Monitor and optimize resource usage

## Testing

### Test Suite
The platform includes a comprehensive test suite:

```bash
# Run all tests
php tests/run-all-tests.php

# Run unit tests only
vendor/bin/phpunit tests/Unit/

# Run integration tests
php tests/Integration/run-integration-tests.php

# Run JavaScript tests
cd tests/js && npm test
```

### Test Coverage
- **Unit Tests**: Core functionality testing
- **Integration Tests**: End-to-end workflow testing
- **Security Tests**: Security feature validation
- **Performance Tests**: Load and performance testing

## Backup and Recovery

### Backup Strategy
- **Daily**: Database and recent uploads
- **Weekly**: Full system backup
- **Monthly**: Archive backup for long-term storage

### Recovery Procedures
1. Restore database from backup
2. Restore uploaded files
3. Verify system functionality
4. Update configuration if needed

## Support and Maintenance

### Regular Maintenance
- Monitor system health and performance
- Review and analyze log files
- Update system components
- Backup data regularly
- Security audits and updates

### Troubleshooting
- Check system health dashboard
- Review error logs for issues
- Verify configuration settings
- Test system functionality
- Contact support if needed

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## Changelog

### Version 1.0.0
- Initial release with core functionality
- Form builder with drag-and-drop interface
- Image cropping and file upload features
- Aadhar validation system
- Security and monitoring features
- Comprehensive documentation

## Support

For support and questions:
- Check the documentation in `/docs/`
- Review the troubleshooting guides
- Check system logs for error details
- Contact the development team

---

**Note**: This platform handles sensitive data including Aadhar numbers. Ensure compliance with local data protection regulations and implement appropriate security measures for production use.