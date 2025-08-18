<?php
/**
 * Sample Database Configuration File
 * BC Management System
 * 
 * Copy this file to 'db_config.php' and update with your actual database credentials.
 * This sample file can be safely committed to version control.
 */

// Environment setting - change to 'live' for production
define('ENVIRONMENT', 'local'); // 'local' or 'live'

// Local/Development Database Configuration
if (ENVIRONMENT === 'local') {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'bc_simple');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_PORT', 3306);
    define('DB_CHARSET', 'utf8mb4');
}

// Live/Production Database Configuration
if (ENVIRONMENT === 'live') {
    define('DB_HOST', 'your_live_host');
    define('DB_NAME', 'your_live_database');
    define('DB_USER', 'your_live_username');
    define('DB_PASS', 'your_live_password');
    define('DB_PORT', 3306);
    define('DB_CHARSET', 'utf8mb4');
}

// Database connection options
$db_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
];

// Database DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Validate required constants
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
    die('Database configuration error: Missing required database constants.');
}

/**
 * Get database connection
 * @return PDO Database connection object
 * @throws Exception If connection fails
 */
function getDatabaseConnection() {
    global $dsn, $db_options;
    
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $db_options);
        return $pdo;
    } catch (PDOException $e) {
        // Log error in production, show error in development
        if (ENVIRONMENT === 'live') {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Please contact administrator.');
        } else {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
}

/**
 * Test database connection
 * @return bool True if connection successful, false otherwise
 */
function testDatabaseConnection() {
    try {
        $pdo = getDatabaseConnection();
        $pdo->query('SELECT 1');
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get database configuration info (for debugging)
 * @return array Database configuration details (without password)
 */
function getDatabaseInfo() {
    return [
        'environment' => ENVIRONMENT,
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'charset' => DB_CHARSET,
        'connection_status' => testDatabaseConnection() ? 'Connected' : 'Failed'
    ];
}

// Optional: Auto-test connection when file is included (only in development)
if (ENVIRONMENT === 'local' && !defined('SKIP_DB_TEST')) {
    if (!testDatabaseConnection()) {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px;'>";
        echo "<strong>Database Connection Warning:</strong> Could not connect to database with current settings.<br>";
        echo "Host: " . DB_HOST . "<br>";
        echo "Database: " . DB_NAME . "<br>";
        echo "Username: " . DB_USER . "<br>";
        echo "Please check your database configuration in db_config.php";
        echo "</div>";
    }
}
?>
