<?php
// Fix Password Hashes - Run this once to fix login issues

require_once 'config.php';

try {
    $pdo = getDB();
    
    // Generate correct password hashes
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $memberPassword = password_hash('member123', PASSWORD_DEFAULT);
    
    echo "<h2>Fixing Password Hashes...</h2>";
    
    // Update admin password
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$adminPassword]);
    echo "✅ Admin password updated<br>";
    
    // Update all member passwords
    $stmt = $pdo->prepare("UPDATE members SET password = ? WHERE password IS NOT NULL");
    $stmt->execute([$memberPassword]);
    echo "✅ All member passwords updated<br>";
    
    echo "<br><h3>Login Credentials:</h3>";
    echo "<strong>Admin Login:</strong><br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br><br>";
    
    echo "<strong>Member Login (any member):</strong><br>";
    echo "Username: akhilesh (or any member username)<br>";
    echo "Password: member123<br><br>";
    
    echo "<h3>Test Links:</h3>";
    echo "<a href='login.php'>Admin Login</a> | ";
    echo "<a href='member_login.php'>Member Login</a><br><br>";
    
    echo "<strong>✅ Password fix completed successfully!</strong><br>";
    echo "You can now login with the credentials above.";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
