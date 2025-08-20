<?php
require_once 'config.php';

echo "<h2>Running Random Picks Table Migration</h2>";

try {
    $pdo = getDB();
    
    echo "<p>Starting migration...</p>";
    
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE random_picks");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('admin_override_member_id', $columns)) {
        echo "<p style='color: orange;'>⚠ Migration already applied!</p>";
    } else {
        // Add columns for admin override functionality
        echo "<p>Adding admin override columns...</p>";
        $pdo->exec("
            ALTER TABLE random_picks 
            ADD COLUMN admin_override_member_id INT NULL AFTER selected_member_id,
            ADD COLUMN admin_override_by INT NULL AFTER admin_override_member_id,
            ADD COLUMN admin_override_at TIMESTAMP NULL AFTER admin_override_by,
            ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER admin_override_at
        ");
        
        echo "<p>Adding foreign key constraints...</p>";
        $pdo->exec("
            ALTER TABLE random_picks 
            ADD CONSTRAINT fk_random_picks_admin_override_member 
                FOREIGN KEY (admin_override_member_id) REFERENCES members(id) ON DELETE SET NULL,
            ADD CONSTRAINT fk_random_picks_admin_override_by 
                FOREIGN KEY (admin_override_by) REFERENCES admin_users(id) ON DELETE SET NULL
        ");
        
        echo "<p>Adding indexes...</p>";
        $pdo->exec("CREATE INDEX idx_random_picks_group_month ON random_picks(group_id, month_number)");
        $pdo->exec("CREATE INDEX idx_random_picks_admin_override ON random_picks(admin_override_member_id)");
        
        echo "<p style='color: green;'>✓ Migration completed successfully!</p>";
    }
    
    // Show updated table structure
    echo "<h3>Updated Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE random_picks");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><a href='admin_manage_random_picks.php?group_id=1'>→ Go to Admin Random Picks Management</a>";
echo "<br><a href='member_group_view.php?group_id=1'>→ Go to Member Group View</a>";
?>
