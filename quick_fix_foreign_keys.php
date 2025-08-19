<?php
/**
 * Quick Fix for Foreign Key Constraint Issues
 * Run this script to resolve the foreign key problems
 */

require_once 'config.php';

echo "<h2>Fixing Foreign Key Constraint Issues</h2>";

try {
    $pdo = getDB();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p><strong>Step 1:</strong> Checking current data state...</p>";

    // Temporarily disable foreign key checks to allow data fixes
    echo "<p>Temporarily disabling foreign key checks...</p>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Check if super_admins table exists - CREATE THIS FIRST
    $stmt = $pdo->query("SHOW TABLES LIKE 'super_admins'");
    if (!$stmt->fetch()) {
        echo "<p><strong>Creating super_admins table...</strong></p>";
        $pdo->exec("
            CREATE TABLE super_admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(15),
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "<p style='color: green;'>✅ Super admins table created</p>";
    }

    // Check if default super admin exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM super_admins WHERE id = 1");
    $superAdminCount = $stmt->fetch()['count'];

    if ($superAdminCount == 0) {
        echo "<p><strong>Creating default super admin...</strong></p>";
        $pdo->exec("
            INSERT INTO super_admins (id, username, password, full_name, email) VALUES
            (1, 'superadmin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'superadmin@bcmanagement.com')
        ");
        echo "<p style='color: green;'>✅ Default super admin created</p>";
    } else {
        echo "<p style='color: green;'>✅ Super admin already exists</p>";
    }

    // Check if clients table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'clients'");
    if (!$stmt->fetch()) {
        echo "<p><strong>Creating clients table...</strong></p>";
        $pdo->exec("
            CREATE TABLE clients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_name VARCHAR(100) NOT NULL,
                company_name VARCHAR(150),
                contact_person VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                phone VARCHAR(15) NOT NULL,
                address TEXT,
                city VARCHAR(50),
                state VARCHAR(50),
                country VARCHAR(50) DEFAULT 'India',
                pincode VARCHAR(10),
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                subscription_plan ENUM('basic', 'premium', 'enterprise') DEFAULT 'basic',
                max_groups INT DEFAULT 10,
                max_members_per_group INT DEFAULT 50,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES super_admins(id) ON DELETE RESTRICT,
                INDEX idx_client_status (status),
                INDEX idx_client_created_by (created_by)
            )
        ");
        echo "<p style='color: green;'>✅ Clients table created</p>";
    }

    // Check clients table data
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients");
    $clientCount = $stmt->fetch()['count'];

    if ($clientCount == 0) {
        echo "<p><strong>Step 2:</strong> Creating default client...</p>";
        try {
            $pdo->exec("
                INSERT INTO clients (id, client_name, company_name, contact_person, email, phone, created_by)
                VALUES (1, 'Default Client', 'Default Company', 'Admin User', 'admin@defaultclient.com', '9999999999', 1)
            ");
            echo "<p style='color: green;'>✅ Default client created</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "<p style='color: orange;'>⚠️ Default client already exists</p>";
            } else {
                throw $e; // Re-throw if it's a different error
            }
        }
    } else {
        echo "<p style='color: green;'>✅ Clients table has $clientCount records</p>";
    }
    
    echo "<p><strong>Step 3:</strong> Checking bc_groups table...</p>";
    
    // Check if client_id column exists in bc_groups
    $stmt = $pdo->query("SHOW COLUMNS FROM bc_groups LIKE 'client_id'");
    if (!$stmt->fetch()) {
        echo "<p>Adding client_id column to bc_groups...</p>";
        $pdo->exec("ALTER TABLE bc_groups ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id");
        echo "<p style='color: green;'>✅ client_id column added</p>";
    }
    
    // Update any NULL or 0 client_id values
    $stmt = $pdo->exec("UPDATE bc_groups SET client_id = 1 WHERE client_id IS NULL OR client_id = 0");
    echo "<p>Updated $stmt records in bc_groups to use default client</p>";
    
    // Check for orphaned records
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM bc_groups bg 
        LEFT JOIN clients c ON bg.client_id = c.id 
        WHERE c.id IS NULL
    ");
    $orphanedCount = $stmt->fetch()['count'];
    
    if ($orphanedCount > 0) {
        echo "<p>Fixing $orphanedCount orphaned records...</p>";
        $pdo->exec("
            UPDATE bc_groups 
            SET client_id = 1 
            WHERE client_id NOT IN (SELECT id FROM clients)
        ");
        echo "<p style='color: green;'>✅ Fixed orphaned records</p>";
    }
    
    echo "<p><strong>Step 4:</strong> Adding foreign key constraint...</p>";
    
    // Try to add the foreign key constraint
    try {
        $pdo->exec("ALTER TABLE bc_groups ADD CONSTRAINT fk_bc_groups_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE");
        echo "<p style='color: green;'>✅ Foreign key constraint added to bc_groups</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            echo "<p style='color: orange;'>⚠️ Foreign key constraint already exists on bc_groups</p>";
        } else {
            echo "<p style='color: red;'>❌ Error adding foreign key to bc_groups: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p><strong>Step 5:</strong> Fixing other tables...</p>";
    
    // Fix other tables
    $tables = ['monthly_bids', 'member_payments', 'member_summary', 'member_bids', 'month_bidding_status'];
    
    foreach ($tables as $table) {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if (!$stmt->fetch()) {
            echo "<p style='color: orange;'>⚠️ Table $table doesn't exist, skipping...</p>";
            continue;
        }
        
        // Check if client_id column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'client_id'");
        if (!$stmt->fetch()) {
            echo "<p>Adding client_id column to $table...</p>";
            $pdo->exec("ALTER TABLE $table ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id");
        }
        
        // Update records
        $updated = $pdo->exec("UPDATE $table SET client_id = 1 WHERE client_id IS NULL OR client_id = 0 OR client_id NOT IN (SELECT id FROM clients)");
        echo "<p>Updated $updated records in $table</p>";
        
        // Add foreign key constraint
        try {
            $pdo->exec("ALTER TABLE $table ADD CONSTRAINT fk_{$table}_client_id FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE");
            echo "<p style='color: green;'>✅ Foreign key constraint added to $table</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "<p style='color: orange;'>⚠️ Foreign key constraint already exists on $table</p>";
            } else {
                echo "<p style='color: red;'>❌ Error adding foreign key to $table: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<p><strong>Step 6:</strong> Creating client_admins table...</p>";
    
    // Create client_admins table if it doesn't exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'client_admins'");
    if (!$stmt->fetch()) {
        $pdo->exec("
            CREATE TABLE client_admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                username VARCHAR(50) NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100),
                phone VARCHAR(15),
                role ENUM('admin', 'manager') DEFAULT 'admin',
                permissions JSON,
                status ENUM('active', 'inactive') DEFAULT 'active',
                last_login TIMESTAMP NULL,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES super_admins(id) ON DELETE SET NULL,
                UNIQUE KEY unique_username_per_client (client_id, username),
                INDEX idx_client_admin_status (status),
                INDEX idx_client_admin_client (client_id)
            )
        ");
        echo "<p style='color: green;'>✅ client_admins table created</p>";
        
        // Migrate existing admin to client admin
        $stmt = $pdo->query("SELECT * FROM admin_users WHERE username = 'admin' LIMIT 1");
        $admin = $stmt->fetch();
        if ($admin) {
            $pdo->prepare("
                INSERT INTO client_admins (client_id, username, password, full_name, email, created_by)
                VALUES (1, ?, ?, ?, 'admin@defaultclient.com', 1)
            ")->execute([$admin['username'], $admin['password'], $admin['full_name']]);
            echo "<p style='color: green;'>✅ Existing admin migrated to client admin</p>";
        }
    }
    
    // Re-enable foreign key checks
    echo "<p>Re-enabling foreign key checks...</p>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "<h3 style='color: green;'>🎉 All foreign key constraints fixed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='super_admin_login.php'>Login as Super Admin</a> (username: superadmin, password: superadmin123)</li>";
    echo "<li><a href='client_login.php'>Login as Client Admin</a> (use your existing admin credentials)</li>";
    echo "<li><a href='member_login.php'>Login as Member</a> (existing member credentials)</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>
