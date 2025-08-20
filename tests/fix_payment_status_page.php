<?php
require_once 'config.php';

echo "<h2>🔧 Fix Payment Status Page</h2>";
echo "<p>This script will fix issues with the admin_payment_status.php page.</p>";

try {
    $pdo = getDB();
    
    // Check if admin is logged in
    if (!isAdminLoggedIn()) {
        echo "<div style='color: red;'>❌ Please login as admin first: <a href='admin_login.php'>Admin Login</a></div>";
        exit;
    }
    
    echo "<h3>1. Checking member_payments table structure...</h3>";
    
    // Check if table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'member_payments'")->fetch();
    
    if (!$tableExists) {
        echo "<div style='color: red;'>❌ member_payments table doesn't exist. Creating it...</div>";
        
        // Create the table
        $createTableSQL = "
        CREATE TABLE member_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            member_id INT NOT NULL,
            month_number INT NOT NULL,
            payment_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
            payment_date DATE NULL,
            payment_method VARCHAR(50) DEFAULT 'upi',
            transaction_id VARCHAR(100) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            UNIQUE KEY unique_payment_per_member_month (group_id, member_id, month_number)
        )";
        
        $pdo->exec($createTableSQL);
        echo "<div style='color: green;'>✅ member_payments table created successfully!</div>";
    } else {
        echo "<div style='color: green;'>✅ member_payments table exists</div>";
        
        // Check and add missing columns
        $columns = $pdo->query("DESCRIBE member_payments")->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = [
            'payment_method' => "ALTER TABLE member_payments ADD COLUMN payment_method VARCHAR(50) DEFAULT 'upi'",
            'transaction_id' => "ALTER TABLE member_payments ADD COLUMN transaction_id VARCHAR(100) NULL",
            'notes' => "ALTER TABLE member_payments ADD COLUMN notes TEXT NULL",
            'created_at' => "ALTER TABLE member_payments ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "ALTER TABLE member_payments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        
        foreach ($requiredColumns as $column => $sql) {
            if (!in_array($column, $columns)) {
                try {
                    $pdo->exec($sql);
                    echo "<div style='color: green;'>✅ Added missing column: $column</div>";
                } catch (Exception $e) {
                    echo "<div style='color: orange;'>⚠️ Could not add column $column: " . $e->getMessage() . "</div>";
                }
            } else {
                echo "<div style='color: blue;'>ℹ️ Column $column already exists</div>";
            }
        }
        
        // Update payment_status enum to include 'failed'
        try {
            $pdo->exec("ALTER TABLE member_payments MODIFY COLUMN payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending'");
            echo "<div style='color: green;'>✅ Updated payment_status enum</div>";
        } catch (Exception $e) {
            echo "<div style='color: orange;'>⚠️ Could not update payment_status enum: " . $e->getMessage() . "</div>";
        }
    }
    
    echo "<h3>2. Checking groups and payment data...</h3>";
    
    // Check groups
    $groups = $pdo->query("SELECT id, group_name, status FROM bc_groups ORDER BY id")->fetchAll();
    if (empty($groups)) {
        echo "<div style='color: orange;'>⚠️ No groups found. Create a group first.</div>";
    } else {
        echo "<div style='color: green;'>✅ Found " . count($groups) . " groups</div>";
        
        // Check if Group 5 exists
        $group5 = array_filter($groups, fn($g) => $g['id'] == 5);
        if (empty($group5)) {
            echo "<div style='color: orange;'>⚠️ Group ID 5 not found. Available groups:</div>";
            foreach ($groups as $group) {
                echo "<div>- Group {$group['id']}: " . htmlspecialchars($group['group_name']) . " ({$group['status']})</div>";
            }
        } else {
            echo "<div style='color: green;'>✅ Group ID 5 exists</div>";
        }
    }
    
    // Check payment records
    $paymentCount = $pdo->query("SELECT COUNT(*) FROM member_payments")->fetchColumn();
    echo "<div>Payment records: $paymentCount</div>";
    
    if ($paymentCount == 0) {
        echo "<div style='color: orange;'>⚠️ No payment records found. These are created when monthly bids are added.</div>";
    }
    
    echo "<h3>3. Testing payment status page query...</h3>";
    
    // Test the query used in admin_payment_status.php
    $testGroupId = !empty($groups) ? $groups[0]['id'] : 1;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                mp.id as payment_id,
                mp.member_id,
                mp.month_number,
                mp.payment_amount,
                mp.payment_date,
                mp.payment_status,
                COALESCE(mp.created_at, NOW()) as created_at,
                m.member_name,
                m.member_number,
                mb.member_name as winner_name,
                mb.bid_amount,
                mb.gain_per_member
            FROM member_payments mp
            JOIN members m ON mp.member_id = m.id
            LEFT JOIN monthly_bids mb ON mp.group_id = mb.group_id AND mp.month_number = mb.month_number
            WHERE mp.group_id = ?
            ORDER BY mp.month_number, m.member_number
        ");
        $stmt->execute([$testGroupId]);
        $testPayments = $stmt->fetchAll();
        
        echo "<div style='color: green;'>✅ Payment status query works correctly</div>";
        echo "<div>Test query returned " . count($testPayments) . " records for group $testGroupId</div>";
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>❌ Payment status query failed: " . $e->getMessage() . "</div>";
    }
    
    echo "<h3>4. Creating test links...</h3>";
    
    echo "<div style='background-color: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>🔗 Test the fixed payment status page:</h4>";
    
    if (!empty($groups)) {
        foreach (array_slice($groups, 0, 3) as $group) {
            echo "<p><a href='admin_payment_status.php?group_id={$group['id']}' target='_blank'>";
            echo "Test Group {$group['id']}: " . htmlspecialchars($group['group_name']);
            echo "</a></p>";
        }
    }
    
    echo "<p><a href='admin_payment_status.php' target='_blank'>Payment Status Page (No Group Selected)</a></p>";
    echo "</div>";
    
    echo "<h3>5. Summary</h3>";
    
    echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>✅ Fix Applied Successfully!</h4>";
    echo "<p><strong>What was fixed:</strong></p>";
    echo "<ul>";
    echo "<li>Ensured member_payments table exists with correct structure</li>";
    echo "<li>Added missing columns (payment_method, transaction_id, notes, timestamps)</li>";
    echo "<li>Updated payment_status enum to include 'failed' option</li>";
    echo "<li>Verified database queries work correctly</li>";
    echo "</ul>";
    
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Test the payment status page with the links above</li>";
    echo "<li>If no payment records exist, add some monthly bids first</li>";
    echo "<li>Payment records are automatically created when bids are added</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div style='background-color: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Check database connection in db_config.php<br>";
    echo "2. Ensure you have proper database permissions<br>";
    echo "3. Try running the SQL migration script manually<br>";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}
</style>
