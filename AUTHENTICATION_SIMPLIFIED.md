# Simplified Authentication System

## Overview
The authentication system has been simplified to use a single, consistent login method across all admin pages.

## Authentication Flow

### 1. Login
- **URL**: `/auth/simple-login.php`
- **Method**: Simple username/password form
- **Session**: Sets `$_SESSION['admin_logged_in'] = true`

### 2. Session Check
All admin pages use this check:
```php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/simple-login.php');
    exit;
}
```

### 3. Logout
- **URL**: `/auth/logout.php`
- **Action**: Destroys session and redirects to login

## Files Removed
The following complex authentication files were removed to simplify the system:

- `auth/AuthManager.php` - Complex authentication manager
- `auth/SessionManager.php` - Advanced session management
- `auth/index.php` - Complex login page with CSRF
- `auth/session.php` - Session validation utility
- `auth/session-status.php` - Session status checker
- `auth/session-timeout-include.php` - Session timeout handler
- `auth/session-timeout.js` - JavaScript session timeout
- `auth/extend-session.php` - Session extension endpoint
- `auth/test-login.php` - Login testing utility
- `auth/test-session-security.php` - Security testing utility

## Security Features Maintained

### 1. Session Security
- HTTPOnly cookies
- Secure cookies (HTTPS)
- Session regeneration on login
- Proper session destruction on logout

### 2. Password Security
- Passwords stored with `password_hash()`
- Verified with `password_verify()`
- Protection against brute force (basic)

### 3. Database Security
- PDO with prepared statements
- SQL injection protection
- Error logging

## Database Requirements

### Admin Users Table
```sql
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);
```

### Default Admin User
```sql
INSERT INTO admin_users (username, password_hash, is_active) 
VALUES ('admin', '$2y$10$example_hash_here', 1);
```

## Implementation Notes

### Session Variables Used
- `$_SESSION['admin_logged_in']` - Boolean login status
- `$_SESSION['admin_id']` - User ID (optional)
- `$_SESSION['admin_username']` - Username (optional)

### Consistent Redirect
All unauthorized access redirects to: `../auth/simple-login.php`

### Files Updated
- `admin/forms.php`
- `admin/form-builder.php`
- `admin/index.php`
- `admin/submissions.php`
- `admin/submission-details.php`
- `admin/test-export.php`
- `admin/cleanup-exports.php`
- `uploads/index.php`
- `uploads/api.php`
- `includes/functions.php`
- `auth/logout.php`

## Testing
1. Clear browser sessions/cookies
2. Access any admin page
3. Should redirect to `/auth/simple-login.php`
4. Login with valid credentials
5. Should maintain session across all admin pages
6. Logout should clear session and redirect back

## Future Enhancements
If more advanced authentication is needed later:
- Add session timeout
- Add CSRF protection
- Add rate limiting
- Add audit logging
- Add role-based permissions

## Security Considerations
- This simplified system is suitable for small-scale deployments
- For production with multiple admins, consider adding:
  - Session timeout
  - IP validation
  - User activity logging
  - Password complexity requirements
  - Account lockout after failed attempts