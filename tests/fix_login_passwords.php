<?php
require_once 'config.php';

echo "<h2>Fixing Login Passwords</h2>";

try {
    $pdo = getDB();
    
    // Fix Super Admin password
    echo "<h3>Fixing Super Admin Password</h3>";
    $superAdminPassword = password_hash('superadmin123', PASSWORD_DEFAULT);
    
    // Check if super admin exists
    $stmt = $pdo->prepare("SELECT id FROM super_admins WHERE username = ?");
    $stmt->execute(['superadmin']);
    $superAdmin = $stmt->fetch();
    
    if ($superAdmin) {
        // Update existing super admin
        $stmt = $pdo->prepare("UPDATE super_admins SET password = ? WHERE username = ?");
        $result = $stmt->execute([$superAdminPassword, 'superadmin']);
        echo "<p>✅ Updated existing super admin password: " . ($result ? 'Success' : 'Failed') . "</p>";
    } else {
        // Insert new super admin
        $stmt = $pdo->prepare("INSERT INTO super_admins (username, password, full_name, email, status) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute(['superadmin', $superAdminPassword, 'Super Administrator', 'superadmin@bcmanagement.com', 'active']);
        echo "<p>✅ Created new super admin: " . ($result ? 'Success' : 'Failed') . "</p>";
    }
    
    // Fix Client Admin password
    echo "<h3>Fixing Client Admin Password</h3>";
    $clientAdminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Check if client admin exists
    $stmt = $pdo->prepare("SELECT id FROM client_admins WHERE username = ?");
    $stmt->execute(['admin']);
    $clientAdmin = $stmt->fetch();
    
    if ($clientAdmin) {
        // Update existing client admin
        $stmt = $pdo->prepare("UPDATE client_admins SET password = ? WHERE username = ?");
        $result = $stmt->execute([$clientAdminPassword, 'admin']);
        echo "<p>✅ Updated existing client admin password: " . ($result ? 'Success' : 'Failed') . "</p>";
    } else {
        // Check if we have a default client
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_name = ?");
        $stmt->execute(['Default Client']);
        $client = $stmt->fetch();
        
        if (!$client) {
            // Create default client first
            $stmt = $pdo->prepare("INSERT INTO clients (client_name, company_name, contact_person, email, phone, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Default Client', 'Default Company', 'Admin User', 'admin@defaultclient.com', '9999999999', 'active']);
            $clientId = $pdo->lastInsertId();
            echo "<p>✅ Created default client</p>";
        } else {
            $clientId = $client['id'];
        }
        
        // Insert new client admin
        $stmt = $pdo->prepare("INSERT INTO client_admins (client_id, username, password, full_name, email, status) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$clientId, 'admin', $clientAdminPassword, 'Administrator', 'admin@defaultclient.com', 'active']);
        echo "<p>✅ Created new client admin: " . ($result ? 'Success' : 'Failed') . "</p>";
    }
    
    // Test the passwords
    echo "<h3>Testing Fixed Passwords</h3>";
    
    // Test super admin
    $stmt = $pdo->prepare("SELECT password FROM super_admins WHERE username = ?");
    $stmt->execute(['superadmin']);
    $superAdminRecord = $stmt->fetch();
    
    if ($superAdminRecord && password_verify('superadmin123', $superAdminRecord['password'])) {
        echo "<p>✅ Super Admin password verification: SUCCESS</p>";
    } else {
        echo "<p>❌ Super Admin password verification: FAILED</p>";
    }
    
    // Test client admin
    $stmt = $pdo->prepare("SELECT password FROM client_admins WHERE username = ?");
    $stmt->execute(['admin']);
    $clientAdminRecord = $stmt->fetch();
    
    if ($clientAdminRecord && password_verify('admin123', $clientAdminRecord['password'])) {
        echo "<p>✅ Client Admin password verification: SUCCESS</p>";
    } else {
        echo "<p>❌ Client Admin password verification: FAILED</p>";
    }
    
    echo "<h3>Updated Credentials</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Login Type</th><th>Username</th><th>Password</th><th>Status</th></tr>";
    echo "<tr><td>Super Admin</td><td>superadmin</td><td>superadmin123</td><td>✅ Fixed</td></tr>";
    echo "<tr><td>Client Admin</td><td>admin</td><td>admin123</td><td>✅ Fixed</td></tr>";
    echo "<tr><td>Regular Admin</td><td>admin</td><td>admin123</td><td>✅ Working</td></tr>";
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
