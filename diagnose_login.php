<?php
// Simple diagnostic script to fix login issues
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 BC Management Login Diagnostics</h2>";

try {
    // Test 1: Check if config.php loads
    echo "<h3>Test 1: Loading Configuration</h3>";
    require_once 'config.php';
    echo "✅ config.php loaded successfully<br>";
    
    // Test 2: Check database connection
    echo "<h3>Test 2: Database Connection</h3>";
    $pdo = getDB();
    echo "✅ Database connection successful<br>";
    
    // Test 3: Check if admin_users table exists
    echo "<h3>Test 3: Admin Users Table</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->fetch()) {
        echo "✅ admin_users table exists<br>";
        
        // Check admin user
        $stmt = $pdo->query("SELECT username, full_name FROM admin_users WHERE username = 'admin'");
        $admin = $stmt->fetch();
        if ($admin) {
            echo "✅ Admin user 'admin' exists: " . htmlspecialchars($admin['full_name']) . "<br>";
        } else {
            echo "❌ Admin user 'admin' not found<br>";
            
            // Create admin user
            echo "<strong>Creating admin user...</strong><br>";
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name) VALUES (?, ?, ?)");
            $result = $stmt->execute(['admin', $hashedPassword, 'Administrator']);
            
            if ($result) {
                echo "✅ Admin user created successfully<br>";
            } else {
                echo "❌ Failed to create admin user<br>";
            }
        }
    } else {
        echo "❌ admin_users table does not exist<br>";
        
        // Create admin_users table
        echo "<strong>Creating admin_users table...</strong><br>";
        $pdo->exec("
            CREATE TABLE admin_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name) VALUES (?, ?, ?)");
        $result = $stmt->execute(['admin', $hashedPassword, 'Administrator']);
        
        if ($result) {
            echo "✅ admin_users table and admin user created successfully<br>";
        } else {
            echo "❌ Failed to create admin user<br>";
        }
    }
    
    // Test 4: Test password verification
    echo "<h3>Test 4: Password Verification</h3>";
    $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE username = 'admin'");
    $stmt->execute();
    $storedHash = $stmt->fetchColumn();
    
    if ($storedHash && password_verify('admin123', $storedHash)) {
        echo "✅ Password verification successful<br>";
    } else {
        echo "❌ Password verification failed - fixing...<br>";
        
        // Fix password
        $newHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
        $result = $stmt->execute([$newHash]);
        
        if ($result) {
            echo "✅ Password fixed successfully<br>";
        } else {
            echo "❌ Failed to fix password<br>";
        }
    }
    
    // Test 5: Test adminLogin function
    echo "<h3>Test 5: Admin Login Function</h3>";
    if (function_exists('adminLogin')) {
        $loginResult = adminLogin('admin', 'admin123');
        if ($loginResult) {
            echo "✅ adminLogin function works correctly<br>";
            echo "Session variables set:<br>";
            echo "- admin_id: " . ($_SESSION['admin_id'] ?? 'not set') . "<br>";
            echo "- admin_name: " . ($_SESSION['admin_name'] ?? 'not set') . "<br>";
            echo "- user_type: " . ($_SESSION['user_type'] ?? 'not set') . "<br>";
        } else {
            echo "❌ adminLogin function failed<br>";
        }
    } else {
        echo "❌ adminLogin function not found<br>";
    }
    
    // Test 6: Check session functionality
    echo "<h3>Test 6: Session Functionality</h3>";
    if (session_status() === PHP_SESSION_ACTIVE) {
        echo "✅ Sessions are active<br>";
        echo "Session ID: " . session_id() . "<br>";
    } else {
        echo "❌ Sessions not active<br>";
    }
    
    echo "<hr>";
    echo "<h3>🎯 Final Status</h3>";
    
    if (isset($_SESSION['admin_id'])) {
        echo "<p style='color: green; font-weight: bold;'>✅ LOGIN SHOULD NOW WORK!</p>";
        echo "<p>Credentials:</p>";
        echo "<ul>";
        echo "<li><strong>Username:</strong> admin</li>";
        echo "<li><strong>Password:</strong> admin123</li>";
        echo "</ul>";
        echo "<p><a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Go to Login Page</a></p>";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>⚠️ Setup completed, but session not set. Try logging in manually.</p>";
        echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔄 Try Login Page</a></p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error Occurred</h3>";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color: red;'>File: " . $e->getFile() . "</p>";
    echo "<p style='color: red;'>Line: " . $e->getLine() . "</p>";
    
    // Try to provide a solution
    echo "<hr>";
    echo "<h3>🔧 Possible Solutions:</h3>";
    echo "<ol>";
    echo "<li>Check database connection settings in config.php</li>";
    echo "<li>Ensure MySQL/MariaDB is running</li>";
    echo "<li>Check file permissions</li>";
    echo "<li>Verify PHP version compatibility</li>";
    echo "</ol>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 900px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
    line-height: 1.6;
}
h2, h3 {
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 5px;
}
ul, ol {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}
hr {
    margin: 30px 0;
    border: none;
    border-top: 2px solid #dee2e6;
}
</style>
