# Client Reporting Portal - Installation Guide

## Requirements

- PHP 8.0+
- MySQL/MariaDB 5.7+ (with InnoDB support)
- Apache with mod_rewrite enabled
- XAMPP, WAMP, or similar local development environment

## Installation Steps

### 1. Database Setup

1. Open phpMyAdmin or MySQL command line
2. Run the SQL script located at `database/schema.sql`:

```sql
SOURCE /path/to/Client Reporting Portal/database/schema.sql;
```

Or copy and paste the contents of `schema.sql` into phpMyAdmin's SQL tab.

### 2. Configuration

1. Open `config/database.php`
2. Update the database credentials if different from defaults:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'client_reporting_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Directory Permissions

Ensure the `storage/reports/` directory is writable by the web server:

```bash
chmod -R 755 storage/
chmod -R 777 storage/reports/  # For development only
```

On Windows/XAMPP, this is typically already set correctly.

### 4. Access the Application

Navigate to: `http://localhost/Client%20Reporting%20Portal/public/`

### 5. Default Login

- **Email:** admin@example.com
- **Password:** password

**IMPORTANT:** Change the default password immediately after first login!

## Default Users

The schema creates one default Super Admin user. You should:

1. Log in with the default credentials
2. Go to Users management
3. Update the Super Admin password
4. Create additional users as needed

## File Structure

```
Client Reporting Portal/
├── config/
│   ├── config.php      # Application configuration
│   └── database.php    # Database connection
├── classes/
│   ├── User.php        # User model
│   ├── Company.php     # Company model
│   └── Report.php      # Report model
├── database/
│   └── schema.sql      # Database schema
├── public/             # Web-accessible files
│   ├── assets/
│   │   ├── css/style.css
│   │   └── js/app.js
│   ├── includes/
│   │   ├── header.php
│   │   └── footer.php
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── users.php
│   ├── companies.php
│   ├── reports.php
│   ├── upload.php
│   ├── view_report.php  # Secure file proxy
│   ├── my-reports.php   # Client view
│   ├── my-companies.php # Manager view
│   └── activity.php     # Activity log
└── storage/
    └── reports/        # Uploaded HTML reports (not web accessible)
```

## Security Features

1. **Secure File Storage:** Reports stored outside web root
2. **File Proxy:** Reports served through `view_report.php` with permission checks
3. **CSRF Protection:** All forms include CSRF tokens
4. **Password Hashing:** Uses bcrypt via `password_hash()`
5. **Prepared Statements:** All SQL queries use PDO prepared statements
6. **XSS Prevention:** Output escaping with `htmlspecialchars()`
7. **Session Security:** Session regeneration on login

## User Roles

- **Super Admin:** Full system access
- **Manager:** Upload/manage reports for assigned companies
- **Client:** View-only access to their company's reports

## Troubleshooting

### Reports not uploading
- Check `storage/reports/` directory permissions
- Verify PHP `upload_max_filesize` and `post_max_size` in php.ini
- Default max file size is 10MB

### Database connection errors
- Verify MySQL is running
- Check credentials in `config/database.php`
- Ensure database exists

### 403 Forbidden errors
- Check Apache mod_rewrite is enabled
- Verify .htaccess files are being processed

## Production Deployment

For production environments:

1. Enable HTTPS
2. Update session settings for security
3. Set appropriate file permissions (more restrictive)
4. Enable security headers in .htaccess
5. Configure error logging (disable display_errors)
6. Set up database backups
