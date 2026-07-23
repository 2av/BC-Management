<?php
require_once '../config/config.php';

echo "<h2>Debug Column Issues</h2>";

try {
    $pdo = getDB();
    
    echo "<h3>Database Connection Info:</h3>";
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $currentDb = $stmt->fetchColumn();
    echo "<p>Current Database: <strong>{$currentDb}</strong></p>";
    
    echo "<h3>All Tables in Database:</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>{$table}</li>";
    }
    echo "</ul>";
    
    echo "<h3>Members Table - Full Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE members");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td><strong>" . $column['Field'] . "</strong></td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Foreign Key Constraints on Members Table:</h3>";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'members' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $stmt->fetchAll();
    
    if (empty($constraints)) {
        echo "<p>No foreign key constraints found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Constraint Name</th><th>Column</th><th>References Table</th><th>References Column</th></tr>";
        foreach ($constraints as $constraint) {
            echo "<tr>";
            echo "<td>" . $constraint['CONSTRAINT_NAME'] . "</td>";
            echo "<td>" . $constraint['COLUMN_NAME'] . "</td>";
            echo "<td>" . $constraint['REFERENCED_TABLE_NAME'] . "</td>";
            echo "<td>" . $constraint['REFERENCED_COLUMN_NAME'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Test Column Existence:</h3>";
    
    // Test different ways to check column existence
    $methods = [
        'SHOW COLUMNS' => "SHOW COLUMNS FROM members LIKE 'group_id'",
        'DESCRIBE' => "DESCRIBE members",
        'INFORMATION_SCHEMA' => "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'members' AND COLUMN_NAME = 'group_id'"
    ];
    
    foreach ($methods as $method => $query) {
        echo "<h4>{$method}:</h4>";
        try {
            $stmt = $pdo->query($query);
            $result = $stmt->fetchAll();
            
            if ($method === 'DESCRIBE') {
                $hasGroupId = false;
                $hasMemberNumber = false;
                foreach ($result as $row) {
                    if ($row['Field'] === 'group_id') $hasGroupId = true;
                    if ($row['Field'] === 'member_number') $hasMemberNumber = true;
                }
                echo "<p>group_id found: " . ($hasGroupId ? "YES" : "NO") . "</p>";
                echo "<p>member_number found: " . ($hasMemberNumber ? "YES" : "NO") . "</p>";
            } else {
                echo "<p>Rows returned: " . count($result) . "</p>";
                if (!empty($result)) {
                    echo "<pre>" . print_r($result, true) . "</pre>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>Try Manual Column Drop:</h3>";
    if (isset($_GET['try_drop'])) {
        try {
            echo "<p>Attempting to drop group_id column...</p>";
            $pdo->exec("ALTER TABLE members DROP COLUMN group_id");
            echo "<p style='color: green;'>✅ Successfully dropped group_id!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Failed to drop group_id: " . $e->getMessage() . "</p>";
        }
        
        try {
            echo "<p>Attempting to drop member_number column...</p>";
            $pdo->exec("ALTER TABLE members DROP COLUMN member_number");
            echo "<p style='color: green;'>✅ Successfully dropped member_number!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Failed to drop member_number: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p><a href='?try_drop=1' onclick='return confirm(\"Try to drop columns?\")'>🔧 Try Manual Drop</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<p><a href="check_table_structure.php">🔍 Check Table Structure</a></p>
<p><a href="force_remove_columns.php">🔨 Force Remove Columns</a></p>
