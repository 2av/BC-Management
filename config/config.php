<?php
/**
 * Main Configuration File for BC Management System
 * This file serves as the entry point for all configurations
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/db_config.php';

// Include application constants from config folder
require_once __DIR__ . '/constants.php';

// Include common functions
require_once __DIR__ . '/../common/functions.php';

// Include authentication functions
require_once __DIR__ . '/../common/auth.php';

// Include middleware
require_once __DIR__ . '/../common/middleware.php';

// Include QR code utilities
require_once __DIR__ . '/../common/qr_utils.php';

// Application Configuration (override if needed)
if (!defined('APP_NAME')) {
    define('APP_NAME', 'BC Management System');
}

// Multi-tenant support - include if available
$mtConfigFiles = [
    __DIR__ . '/simple_mt_config.php',
    __DIR__ . '/mt_config.php',
    __DIR__ . '/multi_tenant_config.php'
];

foreach ($mtConfigFiles as $mtConfig) {
    if (file_exists($mtConfig)) {
        require_once $mtConfig;
        break; // Only include one multi-tenant config
    }
}

// Handle logout request
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    logout();
}

// Language handling
if (isset($_GET['change_language'])) {
    // This is handled in functions.php
}
?>
