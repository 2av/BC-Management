<?php
// Test Login Script - Use this to verify login credentials work

require_once 'config.php';

echo "<h2>Testing Login Credentials</h2>";

try {
    $pdo = getDB();
    
    // Test admin login
    echo "<h3>Testing Admin Login:</h3>";
    $username = 'admin';
    $password = 'admin123';
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ Admin user found: " . $user['full_name'] . "<br>";
        if (password_verify($password, $user['password'])) {
            echo "✅ Admin password verification: SUCCESS<br>";
        } else {
            echo "❌ Admin password verification: FAILED<br>";
        }
    } else {
        echo "❌ Admin user not found<br>";
    }
    
    echo "<br>";
    
    // Test member login
    echo "<h3>Testing Member Login:</h3>";
    $username = 'akhilesh';
    $password = 'member123';
    
    $stmt = $pdo->prepare("SELECT m.*, g.group_name FROM members m JOIN bc_groups g ON m.group_id = g.id WHERE m.username = ? AND m.status = 'active'");
    $stmt->execute([$username]);
    $member = $stmt->fetch();
    
    if ($member) {
        echo "✅ Member found: " . $member['member_name'] . " (Group: " . $member['group_name'] . ")<br>";
        if (password_verify($password, $member['password'])) {
            echo "✅ Member password verification: SUCCESS<br>";
        } else {
            echo "❌ Member password verification: FAILED<br>";
        }
    } else {
        echo "❌ Member not found<br>";
    }
    
    echo "<br>";
    
    // Show all members
    echo "<h3>All Members in Database:</h3>";
    $stmt = $pdo->query("SELECT username, member_name FROM members WHERE username IS NOT NULL");
    $members = $stmt->fetchAll();
    
    if ($members) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Username</th><th>Member Name</th></tr>";
        foreach ($members as $member) {
            echo "<tr><td>" . htmlspecialchars($member['username']) . "</td><td>" . htmlspecialchars($member['member_name']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "No members found with usernames.";
    }
    
    echo "<br><br>";
    echo "<h3>Quick Links:</h3>";
    echo "<a href='login.php' style='margin-right: 10px;'>Admin Login</a>";
    echo "<a href='member_login.php' style='margin-right: 10px;'>Member Login</a>";
    echo "<a href='fix_passwords.php'>Fix Passwords</a>";
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage();
    echo "<br><br>Make sure you have run the complete_database.sql script first.";
}
?>
