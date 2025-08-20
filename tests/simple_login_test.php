<?php
// Simple login test without complex dependencies
session_start();

// Basic database connection
$host = 'localhost:3306';
$dbname = 'priyank2_bc'; // or your actual database name
$username = 'priyank2';
$password = '3nS3r-L!15AxHn';
 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<h2>✅ Database Connected Successfully</h2>";
    
    // Check if admin user exists
    $stmt = $pdo->query("SELECT username, password, full_name FROM admin_users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "<h3>Creating admin user...</h3>";
        
        // Create admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name) VALUES (?, ?, ?)");
        $result = $stmt->execute(['admin', $hashedPassword, 'Administrator']);
        
        if ($result) {
            echo "✅ Admin user created<br>";
            $admin = ['username' => 'admin', 'password' => $hashedPassword, 'full_name' => 'Administrator'];
        } else {
            echo "❌ Failed to create admin user<br>";
        }
    }
    
    if ($admin) {
        echo "<h3>Admin user found: " . htmlspecialchars($admin['full_name']) . "</h3>";
        
        // Test password
        if (password_verify('admin123', $admin['password'])) {
            echo "✅ Password verification successful<br>";
            
            // Set session variables (simulate successful login)
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['client_id'] = 1;
            
            echo "<h3>✅ Login simulation successful!</h3>";
            echo "<p>Session variables set:</p>";
            echo "<ul>";
            echo "<li>admin_id: " . $_SESSION['admin_id'] . "</li>";
            echo "<li>admin_name: " . $_SESSION['admin_name'] . "</li>";
            echo "<li>user_type: " . $_SESSION['user_type'] . "</li>";
            echo "</ul>";
            
            echo "<h3>🎯 Now try these steps:</h3>";
            echo "<ol>";
            echo "<li><strong>Go to login page:</strong> <a href='login.php'>login.php</a></li>";
            echo "<li><strong>Use credentials:</strong> admin / admin123</li>";
            echo "<li><strong>Or try direct access:</strong> <a href='admin_dashboard.php'>admin_dashboard.php</a></li>";
            echo "</ol>";
            
        } else {
            echo "❌ Password verification failed - fixing password...<br>";
            
            // Fix password
            $newHash = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
            $result = $stmt->execute([$newHash]);
            
            if ($result) {
                echo "✅ Password fixed! Try again.<br>";
            }
        }
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>❌ Database Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h3>🔧 Check these settings:</h3>";
    echo "<ul>";
    echo "<li>Database name: Is it 'bc_simple' or something else?</li>";
    echo "<li>Database user: Is 'root' correct?</li>";
    echo "<li>Database password: Is it empty or do you have a password?</li>";
    echo "<li>MySQL service: Is it running?</li>";
    echo "</ul>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f8f9fa; }
h2, h3 { color: #333; }
ul, ol { background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
a { color: #007bff; text-decoration: none; font-weight: bold; }
a:hover { text-decoration: underline; }
</style>
