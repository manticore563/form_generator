# Installation Troubleshooting Guide

## Common Installation Issues and Solutions

### 1. Database Connection Issues

#### Error: "Database connection failed"
**Causes:**
- Incorrect database credentials
- Database server not running
- Network connectivity issues
- Firewall blocking connection

**Solutions:**
1. Verify database credentials are correct
2. Test connection manually:
   ```bash
   mysql -h hostname -u username -p database_name
   ```
3. Check if MySQL/MariaDB service is running:
   ```bash
   sudo systemctl status mysql
   # or
   sudo systemctl status mariadb
   ```
4. Verify database exists and user has proper permissions

#### Error: "SQLSTATE[42000]: Syntax error or access violation: 1064"
**Causes:**
- SQL syntax incompatibility between MySQL versions
- Missing database privileges
- Character encoding issues

**Solutions:**
1. Ensure database user has CREATE, ALTER, DROP, INSERT, UPDATE, DELETE privileges
2. Use MySQL 5.7+ or MariaDB 10.2+
3. Set database charset to utf8mb4:
   ```sql
   CREATE DATABASE enrollment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

### 2. File Permission Issues

#### Error: "Permission denied" or "Directory not writable"
**Causes:**
- Incorrect file/directory permissions
- Web server user doesn't have write access
- SELinux restrictions (on RHEL/CentOS)

**Solutions:**
1. Set proper permissions:
   ```bash
   chmod 755 /path/to/platform
   chmod -R 755 uploads/
   chmod -R 755 logs/
   chmod -R 755 config/
   ```

2. Set ownership to web server user:
   ```bash
   # For Apache
   chown -R www-data:www-data /path/to/platform
   
   # For Nginx
   chown -R nginx:nginx /path/to/platform
   ```

3. For SELinux systems:
   ```bash
   setsebool -P httpd_can_network_connect 1
   setsebool -P httpd_unified 1
   ```

### 3. PHP Configuration Issues

#### Error: "Required PHP extension not loaded"
**Solutions:**
1. Install missing extensions:
   ```bash
   # Ubuntu/Debian
   sudo apt-get install php-pdo php-mysql php-gd php-json php-mbstring php-fileinfo
   
   # CentOS/RHEL
   sudo yum install php-pdo php-mysql php-gd php-json php-mbstring php-fileinfo
   ```

2. Restart web server after installing extensions:
   ```bash
   sudo systemctl restart apache2
   # or
   sudo systemctl restart nginx
   ```

#### Error: "Upload max filesize too small"
**Solutions:**
1. Edit php.ini:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   max_execution_time = 60
   memory_limit = 256M
   ```

2. Restart web server after changes

### 4. Web Server Configuration Issues

#### Apache Issues
1. Ensure mod_rewrite is enabled:
   ```bash
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. Check .htaccess files are being processed:
   ```apache
   <Directory /path/to/platform>
       AllowOverride All
   </Directory>
   ```

#### Nginx Issues
1. Configure proper location blocks:
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   
   location ~ \.php$ {
       fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
       fastcgi_index index.php;
       include fastcgi_params;
   }
   ```

### 5. Installation Process Issues

#### Error: "Table creation failed"
**Solutions:**
1. Check database user privileges:
   ```sql
   GRANT ALL PRIVILEGES ON database_name.* TO 'username'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. Manually clean up partial installation:
   ```sql
   DROP DATABASE IF EXISTS enrollment_db;
   CREATE DATABASE enrollment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Remove config file and restart installation:
   ```bash
   rm config/config.php
   rm config/.installation_complete
   ```

#### Error: "Configuration file creation failed"
**Solutions:**
1. Ensure config directory is writable:
   ```bash
   chmod 755 config/
   chown www-data:www-data config/
   ```

2. Check disk space:
   ```bash
   df -h
   ```

### 6. SSL/HTTPS Issues

#### Mixed Content Warnings
**Solutions:**
1. Ensure all URLs use HTTPS in configuration
2. Update .htaccess to force HTTPS:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

#### SSL Certificate Issues
**Solutions:**
1. Verify certificate is properly installed
2. Check certificate chain is complete
3. Test with SSL checker tools

### 7. Performance Issues

#### Slow Installation Process
**Solutions:**
1. Increase PHP limits:
   ```ini
   max_execution_time = 300
   memory_limit = 512M
   ```

2. Optimize database configuration:
   ```ini
   # my.cnf
   innodb_buffer_pool_size = 256M
   query_cache_size = 64M
   ```

### 8. Security Considerations

#### After Installation Security Steps
1. **Remove installer directory:**
   ```bash
   rm -rf install/
   ```

2. **Set restrictive permissions:**
   ```bash
   chmod 644 config/config.php
   chmod 600 config/.installation_complete
   ```

3. **Configure firewall:**
   ```bash
   # Allow only HTTP/HTTPS
   ufw allow 80
   ufw allow 443
   ufw enable
   ```

4. **Enable security headers in web server configuration**

### 9. Testing Installation

#### Manual Testing Steps
1. **Test database connection:**
   ```bash
   php install/test-installer.php localhost username password database_name
   ```

2. **Verify file permissions:**
   ```bash
   ls -la config/
   ls -la uploads/
   ls -la logs/
   ```

3. **Test web access:**
   - Visit: `http://yourdomain.com/admin/`
   - Check for proper redirects and login page

4. **Test form creation:**
   - Create a test form
   - Submit test data
   - Verify file uploads work

### 10. Logging and Debugging

#### Enable Debug Mode
1. Edit config/config.php:
   ```php
   define('APP_DEBUG', true);
   ```

2. Check error logs:
   ```bash
   tail -f logs/application.log
   tail -f /var/log/apache2/error.log
   ```

#### Common Log Locations
- Application logs: `logs/application.log`
- PHP errors: `/var/log/php_errors.log`
- Apache errors: `/var/log/apache2/error.log`
- Nginx errors: `/var/log/nginx/error.log`

### 11. Recovery Procedures

#### Complete Installation Reset
1. **Backup any existing data**
2. **Drop database tables:**
   ```sql
   DROP DATABASE enrollment_db;
   CREATE DATABASE enrollment_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Remove configuration:**
   ```bash
   rm config/config.php
   rm config/.installation_complete
   ```

4. **Clear uploaded files:**
   ```bash
   rm -rf uploads/files/*
   rm -rf uploads/temp/*
   ```

5. **Restart installation process**

### 12. Getting Help

#### Information to Collect Before Seeking Support
1. **System Information:**
   - Operating system and version
   - Web server (Apache/Nginx) and version
   - PHP version and loaded extensions
   - MySQL/MariaDB version

2. **Error Details:**
   - Complete error messages
   - Steps that led to the error
   - Browser console errors (if applicable)

3. **Configuration:**
   - Web server configuration files
   - PHP configuration (php.ini)
   - File permissions output

4. **Logs:**
   - Application error logs
   - Web server error logs
   - PHP error logs

#### Support Resources
- Check system health: `/admin/system-health.php`
- Review installation logs
- Test with minimal configuration
- Use browser developer tools for frontend issues

### 13. Production Deployment Checklist

#### Pre-Deployment
- [ ] Test installation in staging environment
- [ ] Verify all requirements are met
- [ ] Backup existing data (if upgrading)
- [ ] Plan maintenance window

#### During Deployment
- [ ] Upload files to production server
- [ ] Set proper file permissions
- [ ] Configure database connection
- [ ] Run installation process
- [ ] Test basic functionality

#### Post-Deployment
- [ ] Remove installer directory
- [ ] Configure SSL/HTTPS
- [ ] Set up monitoring and backups
- [ ] Test all features thoroughly
- [ ] Update DNS if necessary
- [ ] Monitor error logs for issues

This troubleshooting guide should help resolve most common installation issues. For complex problems, consider consulting with a system administrator or web hosting provider.