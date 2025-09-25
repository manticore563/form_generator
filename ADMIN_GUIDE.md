# Administrator Guide - Student Enrollment Form Platform

## Table of Contents
1. [System Administration](#system-administration)
2. [User Management](#user-management)
3. [Security Configuration](#security-configuration)
4. [System Monitoring](#system-monitoring)
5. [Backup and Recovery](#backup-and-recovery)
6. [Performance Optimization](#performance-optimization)
7. [Troubleshooting](#troubleshooting)
8. [Maintenance Tasks](#maintenance-tasks)

## System Administration

### Initial Setup
After installation, complete these administrative tasks:

#### 1. Security Hardening
```bash
# Remove installer directory
rm -rf /path/to/platform/install/

# Set proper file permissions
chmod 644 config/config.php
chmod -R 755 uploads/
chmod -R 755 logs/
```

#### 2. Configure System Settings
Edit `config/config.php` to adjust:
- Database connection parameters
- File upload limits
- Session timeout settings
- Security keys and salts
- Error reporting levels

#### 3. SSL/HTTPS Setup
**Strongly recommended for production**:
- Install SSL certificate
- Configure web server for HTTPS
- Update application URLs in config
- Test secure connections

### Directory Structure
```
platform/
├── admin/              # Admin panel files
├── auth/               # Authentication system
├── forms/              # Public form interface
├── includes/           # Core PHP classes
├── assets/             # CSS, JS, images
├── uploads/            # User uploaded files
├── logs/               # System log files
├── config/             # Configuration files
├── tests/              # Test suite
└── install/            # Installation files (remove after setup)
```

### Configuration Files

#### Main Configuration (`config/config.php`)
```php
// Database Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'enrollment_db');
define('DB_USER', 'db_username');
define('DB_PASS', 'secure_password');

// Security Settings
define('CSRF_SECRET', 'random_32_character_string');
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx');
define('UPLOAD_PATH', 'uploads/files/');

// System Settings
define('TIMEZONE', 'Asia/Kolkata');
define('DEBUG_MODE', false); // Set to false in production
define('LOG_LEVEL', 'ERROR'); // ERROR, WARNING, INFO, DEBUG
```

## User Management

### Admin Account Management

#### Creating Admin Users
1. Access the database directly or use the admin panel
2. Use secure password hashing (bcrypt)
3. Assign appropriate permission levels

#### Password Security
- Enforce minimum password length (8+ characters)
- Require mixed case, numbers, and symbols
- Implement password expiration policies
- Prevent password reuse

#### Session Management
- Configure secure session settings
- Implement automatic logout
- Monitor concurrent sessions
- Log all authentication events

### Permission Levels
The system supports different admin permission levels:

#### Super Admin
- Full system access
- User management capabilities
- System configuration access
- Security settings control

#### Form Manager
- Create and edit forms
- View all submissions
- Export data
- Limited system access

#### Viewer
- Read-only access to submissions
- Basic reporting capabilities
- No configuration access

## Security Configuration

### Authentication Security

#### Session Configuration
```php
// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS only
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
```

#### CSRF Protection
- Enabled by default on all forms
- Tokens automatically generated and validated
- Configurable token expiration
- Protection against replay attacks

#### Input Validation
- All user input is sanitized
- SQL injection prevention
- XSS attack mitigation
- File upload validation

### File Security

#### Upload Restrictions
```php
// Configure in config.php
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
define('BLOCKED_EXTENSIONS', ['php', 'exe', 'bat', 'sh', 'js']);
```

#### File Storage Security
- Files stored outside web root
- Access controlled through application
- Automatic malware scanning (if configured)
- Regular cleanup of temporary files

### Database Security

#### Connection Security
- Use prepared statements (PDO)
- Limit database user privileges
- Enable SSL for database connections
- Regular security updates

#### Data Encryption
- Sensitive data encrypted at rest
- Secure key management
- Regular key rotation
- Compliance with data protection laws

## System Monitoring

### Health Monitoring
Access the system health dashboard at `/admin/system-health.php`

#### Key Metrics Monitored
- Database connectivity and performance
- File system permissions and disk space
- PHP configuration and extensions
- Error rates and security events
- Upload functionality and image processing

#### Automated Checks
The system performs automatic health checks:
- Database connection tests
- File permission verification
- Disk space monitoring
- Security configuration validation

### Log Monitoring

#### Log Files Location
- **Error Log**: `logs/error.log`
- **Security Log**: `logs/security.log`
- **Access Log**: `logs/access.log`
- **Database Log**: `logs/database.log`
- **File Operations**: `logs/file_operations.log`

#### Log Rotation
- Automatic log rotation when files exceed 10MB
- Keeps 5 historical versions
- Configurable retention periods
- Automatic cleanup of old logs

#### Critical Events to Monitor
- Failed login attempts
- Unauthorized access attempts
- File upload failures
- Database connection errors
- Security violations

### Performance Monitoring

#### Key Performance Indicators
- Page load times
- Database query performance
- File upload success rates
- Memory usage
- Disk space utilization

#### Optimization Recommendations
- Enable PHP OPcache
- Implement database query optimization
- Use CDN for static assets
- Configure proper caching headers
- Regular database maintenance

## Backup and Recovery

### Backup Strategy

#### What to Backup
1. **Database**: All form data and submissions
2. **Uploaded Files**: User-submitted documents and images
3. **Configuration**: System configuration files
4. **Custom Code**: Any customizations made

#### Backup Schedule
- **Daily**: Database and recent uploads
- **Weekly**: Full system backup
- **Monthly**: Archive backup for long-term storage

#### Backup Script Example
```bash
#!/bin/bash
# Daily backup script

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/enrollment_platform"
DB_NAME="enrollment_db"

# Create backup directory
mkdir -p $BACKUP_DIR/$DATE

# Database backup
mysqldump -u username -p password $DB_NAME > $BACKUP_DIR/$DATE/database.sql

# Files backup
tar -czf $BACKUP_DIR/$DATE/uploads.tar.gz uploads/
tar -czf $BACKUP_DIR/$DATE/config.tar.gz config/

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -type d -mtime +30 -exec rm -rf {} \;
```

### Recovery Procedures

#### Database Recovery
```bash
# Restore database from backup
mysql -u username -p password enrollment_db < backup_file.sql
```

#### File Recovery
```bash
# Restore uploaded files
tar -xzf uploads_backup.tar.gz -C /path/to/platform/

# Restore configuration
tar -xzf config_backup.tar.gz -C /path/to/platform/
```

#### Disaster Recovery Plan
1. **Assessment**: Determine extent of data loss
2. **Isolation**: Secure the affected system
3. **Recovery**: Restore from most recent clean backup
4. **Verification**: Test system functionality
5. **Documentation**: Record incident and lessons learned

## Performance Optimization

### Database Optimization

#### Index Management
```sql
-- Add indexes for frequently queried fields
CREATE INDEX idx_submissions_form_id ON submissions(form_id);
CREATE INDEX idx_submissions_created_at ON submissions(created_at);
CREATE INDEX idx_files_submission_id ON files(submission_id);
```

#### Query Optimization
- Use EXPLAIN to analyze slow queries
- Implement proper JOIN strategies
- Limit result sets with pagination
- Cache frequently accessed data

### File System Optimization

#### Upload Directory Management
```bash
# Regular cleanup of temporary files
find uploads/temp/ -type f -mtime +1 -delete

# Optimize uploaded images
for file in uploads/files/*.jpg; do
    jpegoptim --max=85 "$file"
done
```

#### Disk Space Management
- Monitor disk usage regularly
- Implement file archiving policies
- Compress old log files
- Clean up orphaned files

### Web Server Optimization

#### Apache Configuration
```apache
# Enable compression
LoadModule deflate_module modules/mod_deflate.so
<Location />
    SetOutputFilter DEFLATE
    SetEnvIfNoCase Request_URI \
        \.(?:gif|jpe?g|png)$ no-gzip dont-vary
</Location>

# Enable caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
</IfModule>
```

#### Nginx Configuration
```nginx
# Gzip compression
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css application/json application/javascript;

# Static file caching
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## Troubleshooting

### Common Issues

#### Database Connection Problems
**Symptoms**: "Database connection failed" errors
**Solutions**:
1. Verify database credentials in config.php
2. Check database server status
3. Test network connectivity
4. Review database user permissions

#### File Upload Issues
**Symptoms**: Upload failures or timeouts
**Solutions**:
1. Check PHP upload settings (upload_max_filesize, post_max_size)
2. Verify directory permissions (uploads/ should be writable)
3. Check disk space availability
4. Review web server timeout settings

#### Performance Problems
**Symptoms**: Slow page loads, timeouts
**Solutions**:
1. Enable PHP OPcache
2. Optimize database queries
3. Check server resources (CPU, memory)
4. Review error logs for bottlenecks

#### Security Alerts
**Symptoms**: Failed login attempts, suspicious activity
**Actions**:
1. Review security logs immediately
2. Check for brute force attacks
3. Verify system integrity
4. Update passwords if compromised

### Diagnostic Tools

#### System Health Check
Run the built-in health check: `/admin/system-health.php`

#### Log Analysis
```bash
# Check recent errors
tail -f logs/error.log

# Search for specific issues
grep "ERROR" logs/error.log | tail -20

# Monitor security events
grep "security" logs/security.log
```

#### Database Diagnostics
```sql
-- Check database size
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = 'enrollment_db';

-- Check slow queries
SHOW PROCESSLIST;
```

## Maintenance Tasks

### Daily Tasks
- [ ] Review error logs
- [ ] Check system health dashboard
- [ ] Monitor disk space usage
- [ ] Verify backup completion
- [ ] Review security alerts

### Weekly Tasks
- [ ] Analyze performance metrics
- [ ] Clean up temporary files
- [ ] Review user access logs
- [ ] Update system documentation
- [ ] Test backup restoration

### Monthly Tasks
- [ ] Security audit and review
- [ ] Database optimization
- [ ] System updates and patches
- [ ] Capacity planning review
- [ ] Disaster recovery testing

### Quarterly Tasks
- [ ] Full security assessment
- [ ] Performance benchmarking
- [ ] Backup strategy review
- [ ] User access audit
- [ ] System architecture review

### Emergency Procedures

#### Security Incident Response
1. **Immediate**: Isolate affected systems
2. **Assessment**: Determine scope of breach
3. **Containment**: Stop ongoing attack
4. **Recovery**: Restore from clean backups
5. **Analysis**: Document and learn from incident

#### System Failure Response
1. **Detection**: Monitor alerts and logs
2. **Diagnosis**: Identify root cause
3. **Communication**: Notify stakeholders
4. **Resolution**: Implement fix or restore
5. **Prevention**: Update procedures to prevent recurrence

### Contact Information
Maintain updated contact information for:
- System administrators
- Database administrators
- Security team
- Hosting provider support
- Emergency contacts

### Documentation Updates
Keep this guide updated with:
- Configuration changes
- New procedures
- Lessons learned
- System modifications
- Contact information changes