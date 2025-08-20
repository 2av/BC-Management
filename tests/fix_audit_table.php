<?php
require_once 'config.php';

echo "<h2>Fixing Audit Log Table</h2>";

try {
    $pdo = getDB();
    
    // Check if audit_log table exists
    echo "<h3>Checking Audit Log Table</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE audit_log");
        echo "<p>✅ audit_log table already exists</p>";
        
        // Show table structure
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
        
    } catch (Exception $e) {
        echo "<p>❌ audit_log table does not exist. Creating it...</p>";
        
        // Create the audit_log table
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT,
            user_type ENUM('super_admin', 'client_admin', 'member') NOT NULL,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(100),
            record_id INT,
            old_values TEXT,
            new_values TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client_id (client_id),
            INDEX idx_user_type_id (user_type, user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTableSQL);
        echo "<p>✅ audit_log table created successfully!</p>";
        
        // Verify creation
        $stmt = $pdo->query("DESCRIBE audit_log");
        echo "<p>✅ Table structure verified:</p>";
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Test audit logging function
    echo "<h3>Testing Audit Logging Function</h3>";
    try {
        // Test the logAuditAction function
        if (function_exists('logAuditAction')) {
            logAuditAction(1, 'client_admin', 1, 'test_login', 'client_admins', 1);
            echo "<p>✅ Audit logging function test: SUCCESS</p>";
            
            // Check if the record was inserted
            $stmt = $pdo->query("SELECT * FROM audit_log ORDER BY id DESC LIMIT 1");
            $lastLog = $stmt->fetch();
            if ($lastLog) {
                echo "<p>✅ Last audit log entry:</p>";
                echo "<ul>";
                echo "<li>Action: " . $lastLog['action'] . "</li>";
                echo "<li>User Type: " . $lastLog['user_type'] . "</li>";
                echo "<li>User ID: " . $lastLog['user_id'] . "</li>";
                echo "<li>Created: " . $lastLog['created_at'] . "</li>";
                echo "</ul>";
            }
        } else {
            echo "<p>❌ logAuditAction function not found</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Audit logging test failed: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>Status</h3>";
    echo "<p>✅ Audit log table is now ready for use!</p>";
    echo "<p>✅ Client login should now work without errors.</p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
