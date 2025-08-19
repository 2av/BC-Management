<?php
require_once 'config.php';

echo "<h2>Database Tables Check</h2>";

try {
    $pdo = getDB();
    
    // Check if super_admins table exists
    echo "<h3>Super Admins Table:</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE super_admins");
        echo "<p>✅ super_admins table exists</p>";
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Check super admin records
        $stmt = $pdo->query("SELECT id, username, full_name, email, status FROM super_admins");
        $superAdmins = $stmt->fetchAll();
        echo "<h4>Super Admin Records:</h4>";
        if ($superAdmins) {
            echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Status</th></tr>";
            foreach ($superAdmins as $admin) {
                echo "<tr><td>{$admin['id']}</td><td>{$admin['username']}</td><td>{$admin['full_name']}</td><td>{$admin['email']}</td><td>{$admin['status']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No super admin records found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ super_admins table does not exist: " . $e->getMessage() . "</p>";
    }
    
    // Check if clients table exists
    echo "<h3>Clients Table:</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE clients");
        echo "<p>✅ clients table exists</p>";
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Check client records
        $stmt = $pdo->query("SELECT id, client_name, company_name, email, status FROM clients");
        $clients = $stmt->fetchAll();
        echo "<h4>Client Records:</h4>";
        if ($clients) {
            echo "<table border='1'><tr><th>ID</th><th>Client Name</th><th>Company</th><th>Email</th><th>Status</th></tr>";
            foreach ($clients as $client) {
                echo "<tr><td>{$client['id']}</td><td>{$client['client_name']}</td><td>{$client['company_name']}</td><td>{$client['email']}</td><td>{$client['status']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No client records found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ clients table does not exist: " . $e->getMessage() . "</p>";
    }
    
    // Check if client_admins table exists
    echo "<h3>Client Admins Table:</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE client_admins");
        echo "<p>✅ client_admins table exists</p>";
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
        
        // Check client admin records
        $stmt = $pdo->query("SELECT ca.id, ca.username, ca.full_name, ca.email, ca.status, c.client_name 
                            FROM client_admins ca 
                            LEFT JOIN clients c ON ca.client_id = c.id");
        $clientAdmins = $stmt->fetchAll();
        echo "<h4>Client Admin Records:</h4>";
        if ($clientAdmins) {
            echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Status</th><th>Client</th></tr>";
            foreach ($clientAdmins as $admin) {
                echo "<tr><td>{$admin['id']}</td><td>{$admin['username']}</td><td>{$admin['full_name']}</td><td>{$admin['email']}</td><td>{$admin['status']}</td><td>{$admin['client_name']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No client admin records found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ client_admins table does not exist: " . $e->getMessage() . "</p>";
    }
    
    // Check existing admin_users table
    echo "<h3>Admin Users Table (Legacy):</h3>";
    try {
        $stmt = $pdo->query("SELECT id, username, full_name FROM admin_users");
        $adminUsers = $stmt->fetchAll();
        echo "<h4>Admin User Records:</h4>";
        if ($adminUsers) {
            echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Full Name</th></tr>";
            foreach ($adminUsers as $admin) {
                echo "<tr><td>{$admin['id']}</td><td>{$admin['username']}</td><td>{$admin['full_name']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p>❌ No admin user records found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ admin_users table does not exist: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database connection error: " . $e->getMessage() . "</p>";
}
?>
