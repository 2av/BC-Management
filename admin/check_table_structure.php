<?php
require_once '../config/config.php';

echo "<h2>Table Structure Check</h2>";

try {
    $pdo = getDB();
    
    echo "<h3>Members Table Columns:</h3>";
    $stmt = $pdo->query("SHOW COLUMNS FROM members");
    $columns = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
    
    echo "<h3>Group Members Table Columns:</h3>";
    $stmt = $pdo->query("SHOW COLUMNS FROM group_members");
    $columns = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>" . $column['Field'] . "</strong> - " . $column['Type'] . "</li>";
    }
    echo "</ul>";
    
    echo "<h3>Specific Column Checks:</h3>";
    
    // Check for group_id
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
    $hasGroupId = $stmt->rowCount() > 0;
    echo "<p>members.group_id exists: " . ($hasGroupId ? "YES" : "NO") . "</p>";
    
    // Check for member_number
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'member_number'");
    $hasMemberNumber = $stmt->rowCount() > 0;
    echo "<p>members.member_number exists: " . ($hasMemberNumber ? "YES" : "NO") . "</p>";
    
    // Check group_members data
    $stmt = $pdo->query("SELECT COUNT(*) FROM group_members");
    $groupMembersCount = $stmt->fetchColumn();
    echo "<p>group_members records: " . $groupMembersCount . "</p>";
    
    // Check members data
    $stmt = $pdo->query("SELECT COUNT(*) FROM members");
    $membersCount = $stmt->fetchColumn();
    echo "<p>members records: " . $membersCount . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
