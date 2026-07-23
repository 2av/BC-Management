<?php
/**
 * Application Constants for BC Management System
 * This file contains only application constants and configuration values
 */

// Application Configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', 'BC Management System');
}

// Version Information
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.0');
}

// Default Currency
if (!defined('DEFAULT_CURRENCY')) {
    define('DEFAULT_CURRENCY', 'INR');
}

// Pagination Settings
if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', 20);
}

// File Upload Settings
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
}

// Allowed File Types
if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
}

// Date Format
if (!defined('DATE_FORMAT')) {
    define('DATE_FORMAT', 'd/m/Y');
}

// Time Zone
if (!defined('DEFAULT_TIMEZONE')) {
    define('DEFAULT_TIMEZONE', 'Asia/Kolkata');
}

// Set default timezone
date_default_timezone_set(DEFAULT_TIMEZONE);
?>
