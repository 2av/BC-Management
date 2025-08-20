<?php
require_once 'config.php';

echo "<h2>🔧 Admin Login Fix Tool</h2>";

if (isset($_GET['fix'])) {
    try {
        $pdo = getDB();
        
        // Create a fresh password hash for 'admin123'
        $newPassword = 'admin123';
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        echo "<h3>🔄 Fixing Admin Password...</h3>";
        echo "<p><strong>New password:</strong> $newPassword</p>";
        echo "<p><strong>New hash:</strong> $newHash</p>";
        
        // Update the admin password
        $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
        $result = $stmt->execute([$newHash]);
        
        if ($result) {
            echo "<p style='color: green; font-weight: bold;'>✅ Password updated successfully!</p>";
            
            // Test the login
            echo "<h3>🧪 Testing Login...</h3>";
            $loginTest = adminLogin('admin', 'admin123');
            
            if ($loginTest) {
                echo "<p style='color: green; font-weight: bold;'>✅ Login test SUCCESSFUL!</p>";
                echo "<p>You can now login with:</p>";
                echo "<ul>";
                echo "<li><strong>Username:</strong> admin</li>";
                echo "<li><strong>Password:</strong> admin123</li>";
                echo "</ul>";
                echo "<p><a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Go to Login Page</a></p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>❌ Login test still failed!</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Failed to update password!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h3>🔍 Current Admin Status:</h3>";
    
    try {
        $pdo = getDB();
        
        // Check admin_users table
        $stmt = $pdo->query("SELECT username, full_name, created_at FROM admin_users WHERE username = 'admin'");
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "<p style='color: green;'>✅ Admin user exists:</p>";
            echo "<ul>";
            echo "<li><strong>Username:</strong> " . htmlspecialchars($admin['username']) . "</li>";
            echo "<li><strong>Full Name:</strong> " . htmlspecialchars($admin['full_name']) . "</li>";
            echo "<li><strong>Created:</strong> " . htmlspecialchars($admin['created_at']) . "</li>";
            echo "</ul>";
            
            // Test current password
            $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE username = 'admin'");
            $stmt->execute();
            $currentHash = $stmt->fetchColumn();
            
            $passwordWorks = password_verify('admin123', $currentHash);
            echo "<p><strong>Current password 'admin123' works:</strong> " . ($passwordWorks ? '✅ YES' : '❌ NO') . "</p>";
            
            if (!$passwordWorks) {
                echo "<p style='color: orange; font-weight: bold;'>🔧 Password needs to be fixed!</p>";
                echo "<p><a href='?fix=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Fix Admin Password</a></p>";
            } else {
                echo "<p style='color: green; font-weight: bold;'>✅ Password is working correctly!</p>";
                echo "<p>If login still doesn't work, there might be a session or redirect issue.</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ No admin user found!</p>";
            echo "<p><a href='?create=1' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>➕ Create Admin User</a></p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
    }
}

if (isset($_GET['create'])) {
    try {
        $pdo = getDB();
        
        echo "<h3>➕ Creating Admin User...</h3>";
        
        $username = 'admin';
        $password = 'admin123';
        $fullName = 'Administrator';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, full_name) VALUES (?, ?, ?)");
        $result = $stmt->execute([$username, $hashedPassword, $fullName]);
        
        if ($result) {
            echo "<p style='color: green; font-weight: bold;'>✅ Admin user created successfully!</p>";
            echo "<p>Login credentials:</p>";
            echo "<ul>";
            echo "<li><strong>Username:</strong> $username</li>";
            echo "<li><strong>Password:</strong> $password</li>";
            echo "</ul>";
            echo "<p><a href='login.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Go to Login Page</a></p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create admin user!</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f8f9fa;
}
h2, h3 {
    color: #333;
}
p {
    line-height: 1.6;
}
ul {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}
</style>
