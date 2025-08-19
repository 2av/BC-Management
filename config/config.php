<?php
// BC Management System Configuration

// Database Configuration
 
require_once '../db_config.php';
// Application Configuration
define('APP_NAME', 'BC Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/BC-Management');
define('ADMIN_EMAIL', 'admin@bcmanagement.com');

// Security Configuration
define('HASH_ALGO', PASSWORD_DEFAULT);
define('SESSION_LIFETIME', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');

// File Upload Configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');

// Pagination
define('RECORDS_PER_PAGE', 10);

// Date/Time Configuration
date_default_timezone_set('Asia/Kolkata');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y H:i');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Currency Configuration
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');
?>
