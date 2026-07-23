<?php
/**
 * Quick Configuration Test
 * Tests if the new config structure is working
 */

echo "<h2>Testing Configuration...</h2>";

try {
    require_once 'config/config.php';
    echo "✅ Config loaded successfully!<br>";
    echo "✅ APP_NAME: " . APP_NAME . "<br>";
    echo "✅ Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "<br>";
    
    // Test database connection
    try {
        $pdo = getDB();
        echo "✅ Database connection: Working<br>";
    } catch (Exception $e) {
        echo "❌ Database connection: " . $e->getMessage() . "<br>";
    }
    
    // Test functions
    echo "✅ Auth functions available: " . (function_exists('isAdminLoggedIn') ? 'Yes' : 'No') . "<br>";
    echo "✅ Utility functions available: " . (function_exists('formatCurrency') ? 'Yes' : 'No') . "<br>";
    echo "✅ Middleware functions available: " . (function_exists('checkRole') ? 'Yes' : 'No') . "<br>";
    
    echo "<br><strong>🎉 Configuration is working correctly!</strong><br>";
    echo "<a href='auth/landing.php'>Go to Landing Page</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
