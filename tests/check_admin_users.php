<?php
require_once 'config.php';

echo "<h2>BC Management System - Admin Users Check</h2>";

// Test admin login function
if (isset($_GET['test_login'])) {
    echo "<h3>Testing Admin Login Function:</h3>";

    // First, let's check the password hash
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT username, password FROM admin_users WHERE username = 'admin'");
    $stmt->execute();
    $adminUser = $stmt->fetch();

    if ($adminUser) {
        echo "<p><strong>Found admin user:</strong> " . htmlspecialchars($adminUser['username']) . "</p>";
        echo "<p><strong>Stored password hash:</strong> " . htmlspecialchars($adminUser['password']) . "</p>";

        $passwordCheck = password_verify('admin123', $adminUser['password']);
        echo "<p><strong>Password verification:</strong> " . ($passwordCheck ? 'SUCCESS' : 'FAILED') . "</p>";

        if (!$passwordCheck) {
            // Try creating a new hash
            $newHash = password_hash('admin123', PASSWORD_DEFAULT);
            echo "<p><strong>New hash for 'admin123':</strong> $newHash</p>";

            // Update the password
            $updateStmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'admin'");
            $updateResult = $updateStmt->execute([$newHash]);
            echo "<p><strong>Password update:</strong> " . ($updateResult ? 'SUCCESS' : 'FAILED') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ No admin user found!</p>";
    }

    $testResult = adminLogin('admin', 'admin123');
    echo "<p><strong>adminLogin('admin', 'admin123') result:</strong> " . ($testResult ? 'SUCCESS' : 'FAILED') . "</p>";

    if ($testResult) {
        echo "<p style='color: green;'>✅ Login function works! Session variables set:</p>";
        echo "<ul>";
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'admin') !== false || strpos($key, 'user') !== false || strpos($key, 'client') !== false) {
                echo "<li><strong>$key:</strong> " . htmlspecialchars($value) . "</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ Login function failed!</p>";
    }
    echo "<hr>";
}

try {
    $pdo = getDB();
    
    echo "<h3>1. Legacy Admin Users (admin_users table):</h3>";
    $stmt = $pdo->query("SELECT username, full_name, created_at FROM admin_users ORDER BY id");
    $adminUsers = $stmt->fetchAll();
    
    if ($adminUsers) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Username</th><th>Full Name</th><th>Created At</th></tr>";
        foreach ($adminUsers as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No users found in admin_users table.</p>";
    }
    
    echo "<h3>2. Client Admins (client_admins table):</h3>";
    try {
        $stmt = $pdo->query("SELECT ca.username, ca.full_name, ca.status, c.client_name, ca.created_at 
                            FROM client_admins ca 
                            LEFT JOIN clients c ON ca.client_id = c.id 
                            ORDER BY ca.id");
        $clientAdmins = $stmt->fetchAll();
        
        if ($clientAdmins) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Username</th><th>Full Name</th><th>Status</th><th>Client</th><th>Created At</th></tr>";
            foreach ($clientAdmins as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($user['status']) . "</td>";
                echo "<td>" . htmlspecialchars($user['client_name'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users found in client_admins table.</p>";
        }
    } catch (Exception $e) {
        echo "<p>client_admins table doesn't exist or error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>3. Super Admins (super_admins table):</h3>";
    try {
        $stmt = $pdo->query("SELECT username, full_name, email, status, created_at FROM super_admins ORDER BY id");
        $superAdmins = $stmt->fetchAll();
        
        if ($superAdmins) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Username</th><th>Full Name</th><th>Email</th><th>Status</th><th>Created At</th></tr>";
            foreach ($superAdmins as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['status']) . "</td>";
                echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No users found in super_admins table.</p>";
        }
    } catch (Exception $e) {
        echo "<p>super_admins table doesn't exist or error: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>4. Members (for reference):</h3>";
    try {
        $stmt = $pdo->query("SELECT username, member_name, status FROM members WHERE username IS NOT NULL ORDER BY id LIMIT 10");
        $members = $stmt->fetchAll();
        
        if ($members) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Username</th><th>Member Name</th><th>Status</th></tr>";
            foreach ($members as $member) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($member['username']) . "</td>";
                echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
                echo "<td>" . htmlspecialchars($member['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No members with usernames found.</p>";
        }
    } catch (Exception $e) {
        echo "<p>Error checking members: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<h3>Default Credentials:</h3>";
    echo "<ul>";
    echo "<li><strong>Legacy Admin:</strong> username = 'admin', password = 'admin123'</li>";
    echo "<li><strong>Super Admin:</strong> username = 'superadmin', password = 'superadmin123'</li>";
    echo "<li><strong>Sample Member:</strong> username = 'akhilesh', password = 'member123' (for member login)</li>";
    echo "</ul>";

    echo "<hr>";
    echo "<h3>Debug Tools:</h3>";
    echo "<p><a href='?test_login=1' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>🧪 Test Admin Login Function</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>
