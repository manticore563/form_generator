# Deployment Guide

## System Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7+ or MariaDB 10.2+
- **Web Server**: Apache or Nginx
- **Storage**: Minimum 100MB free space
- **Memory**: 128MB PHP memory limit (recommended: 256MB)

### PHP Extensions Required
- PDO and PDO_MySQL
- GD or Imagick (for image processing)
- JSON
- Session
- Filter
- Fileinfo

### File Permissions
- Web root: 755
- Upload directories: 755 (with write access)
- Config files: 644
- PHP files: 644

## Installation Steps

### 1. Download and Extract
1. Download the platform package
2. Extract to your web server directory
3. Ensure all files are uploaded correctly

### 2. Set Directory Permissions
```bash
# Set basic permissions
chmod -R 755 /path/to/platform
chmod -R 755 uploads/
chmod -R 755 logs/
chmod 644 config/config-template.php
```

### 3. Create Database
1. Create a new MySQL database
2. Create a database user with full privileges on the database
3. Note down the database credentials

### 4. Run Installer
1. Navigate to `http://yourdomain.com/install/`
2. Follow the installation wizard:
   - Enter database connection details
   - Create admin account
   - Configure system settings
3. Delete the `/install/` directory after successful installation

### 5. Verify Installation
1. Access admin panel at `http://yourdomain.com/admin/`
2. Login with created admin credentials
3. Create a test form to verify functionality

## Configuration

### Environment Configuration
The system automatically generates `config/config.php` during installation. Key settings include:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');

// Security Settings
define('CSRF_SECRET', 'generated_secret_key');
define('SESSION_TIMEOUT', 3600); // 1 hour

// File Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('UPLOAD_PATH', 'uploads/files/');
```

### Web Server Configuration

#### Apache (.htaccess)
The platform includes `.htaccess` files for security. Ensure mod_rewrite is enabled.

#### Nginx
Add this configuration to your server block:
```nginx
location ~ /\. {
    deny all;
}

location ~* \.(log|sql|md)$ {
    deny all;
}

location /uploads/ {
    location ~ \.php$ {
        deny all;
    }
}
```

## Security Considerations

### File Security
- Upload directories should not execute PHP files
- Sensitive files are protected by .htaccess rules
- File access is controlled through the application

### Database Security
- Use strong database passwords
- Limit database user privileges
- Regular database backups recommended

### SSL/HTTPS
- **Strongly recommended** for production use
- Required for handling sensitive data like Aadhar numbers
- Configure SSL certificate on your web server

## Backup and Maintenance

### Regular Backups
1. **Database**: Export MySQL database regularly
2. **Files**: Backup uploaded files in `/uploads/files/`
3. **Configuration**: Backup `config/config.php`

### Maintenance Tasks
- Monitor disk space for uploaded files
- Review error logs regularly
- Update PHP and MySQL as needed
- Clean up temporary export files

### Log Files
- Application logs: `/logs/`
- Error logs: Check PHP error logs
- Access logs: `/logs/access.log`

## Troubleshooting

### Common Issues

#### Installation Fails
- Check PHP version and extensions
- Verify database credentials
- Ensure write permissions on directories

#### File Uploads Not Working
- Check PHP upload settings (`upload_max_filesize`, `post_max_size`)
- Verify directory permissions
- Check disk space

#### Forms Not Loading
- Check database connection
- Verify .htaccess rules
- Check PHP error logs

#### Image Cropping Issues
- Ensure GD or Imagick extension is installed
- Check browser JavaScript console for errors
- Verify Canvas API support

### Performance Optimization

#### Database
- Add indexes for frequently queried fields
- Regular database optimization
- Consider connection pooling for high traffic

#### File Storage
- Implement CDN for file serving
- Regular cleanup of orphaned files
- Consider external storage for large deployments

#### Caching
- Enable PHP OPcache
- Implement browser caching for static assets
- Consider Redis for session storage

## Scaling Considerations

### High Traffic Deployments
- Load balancer configuration
- Database replication setup
- Shared file storage solutions
- Session storage externalization

### Multi-Server Setup
- Shared database server
- Centralized file storage (NFS/S3)
- Load balancer configuration
- Session synchronization

## Support and Updates

### Getting Help
- Check error logs first
- Review this documentation
- Test in a staging environment

### Updates
- Always backup before updates
- Test updates in staging environment
- Follow semantic versioning for compatibility

### Monitoring
- Set up health check endpoints
- Monitor disk usage
- Track error rates
- Monitor database performance