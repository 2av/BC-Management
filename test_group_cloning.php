<?php
require_once 'config.php';

echo "<h2>Group Cloning Functionality Test</h2>";

try {
    $pdo = getDB();
    
    // Test 1: Check if we have any completed groups
    echo "<h3>1. Checking for Completed Groups</h3>";
    $stmt = $pdo->query("
        SELECT bg.*, 
               COUNT(m.id) as actual_members,
               COUNT(mb.id) as completed_months
        FROM bc_groups bg
        LEFT JOIN members m ON bg.id = m.group_id AND m.status = 'active'
        LEFT JOIN monthly_bids mb ON bg.id = mb.group_id
        WHERE bg.status = 'completed'
        GROUP BY bg.id
        ORDER BY bg.created_at DESC
    ");
    $completedGroups = $stmt->fetchAll();
    
    if (empty($completedGroups)) {
        echo "<div style='color: orange;'>No completed groups found. Creating a test completed group...</div>";
        
        // Create a test completed group
        $stmt = $pdo->prepare("
            INSERT INTO bc_groups (group_name, total_members, monthly_contribution, total_monthly_collection, start_date, status) 
            VALUES (?, ?, ?, ?, ?, 'completed')
        ");
        $stmt->execute(['Test Completed Group', 5, 1000.00, 5000.00, '2024-01-01']);
        $testGroupId = $pdo->lastInsertId();
        
        // Add some test members
        $testMembers = ['John Doe', 'Jane Smith', 'Bob Johnson', 'Alice Brown', 'Charlie Wilson'];
        $memberStmt = $pdo->prepare("
            INSERT INTO members (group_id, member_name, member_number, username, password, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        
        foreach ($testMembers as $index => $memberName) {
            $username = strtolower(str_replace(' ', '', $memberName));
            $password = password_hash('member123', PASSWORD_DEFAULT);
            $memberStmt->execute([$testGroupId, $memberName, $index + 1, $username, $password]);
        }
        
        echo "<div style='color: green;'>✅ Test completed group created with ID: {$testGroupId}</div>";
        
        // Refresh the completed groups list
        $stmt = $pdo->query("
            SELECT bg.*, 
                   COUNT(m.id) as actual_members,
                   COUNT(mb.id) as completed_months
            FROM bc_groups bg
            LEFT JOIN members m ON bg.id = m.group_id AND m.status = 'active'
            LEFT JOIN monthly_bids mb ON bg.id = mb.group_id
            WHERE bg.status = 'completed'
            GROUP BY bg.id
            ORDER BY bg.created_at DESC
        ");
        $completedGroups = $stmt->fetchAll();
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>Group Name</th><th>Members</th><th>Contribution</th><th>Status</th><th>Action</th>";
    echo "</tr>";
    
    foreach ($completedGroups as $group) {
        echo "<tr>";
        echo "<td>{$group['id']}</td>";
        echo "<td>" . htmlspecialchars($group['group_name']) . "</td>";
        echo "<td>{$group['actual_members']}/{$group['total_members']}</td>";
        echo "<td>₹" . number_format($group['monthly_contribution'], 2) . "</td>";
        echo "<td><span style='color: green; font-weight: bold;'>{$group['status']}</span></td>";
        echo "<td><a href='clone_group.php?id={$group['id']}' target='_blank' style='color: blue;'>🔗 Test Clone</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test 2: Check clone functionality components
    echo "<h3>2. Testing Clone Functionality Components</h3>";
    
    // Check if month_bidding_status table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'month_bidding_status'")->fetch();
    if ($tableExists) {
        echo "<div style='color: green;'>✅ month_bidding_status table exists</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ month_bidding_status table not found (optional feature)</div>";
    }
    
    // Check if member_summary table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'member_summary'")->fetch();
    if ($tableExists) {
        echo "<div style='color: green;'>✅ member_summary table exists</div>";
    } else {
        echo "<div style='color: red;'>❌ member_summary table not found (required)</div>";
    }
    
    // Test 3: Check existing members for suggestions
    echo "<h3>3. Available Members for Cloning</h3>";
    $stmt = $pdo->query("SELECT DISTINCT member_name FROM members ORDER BY member_name LIMIT 10");
    $existingMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($existingMembers)) {
        echo "<div style='color: green;'>✅ Found " . count($existingMembers) . " existing members:</div>";
        echo "<ul>";
        foreach (array_slice($existingMembers, 0, 5) as $memberName) {
            echo "<li>" . htmlspecialchars($memberName) . "</li>";
        }
        if (count($existingMembers) > 5) {
            echo "<li>... and " . (count($existingMembers) - 5) . " more</li>";
        }
        echo "</ul>";
    } else {
        echo "<div style='color: orange;'>⚠️ No existing members found</div>";
    }
    
    // Test 4: Check required functions
    echo "<h3>4. Testing Required Functions</h3>";
    
    if (function_exists('getGroupById')) {
        echo "<div style='color: green;'>✅ getGroupById() function exists</div>";
    } else {
        echo "<div style='color: red;'>❌ getGroupById() function missing</div>";
    }
    
    if (function_exists('getGroupMembers')) {
        echo "<div style='color: green;'>✅ getGroupMembers() function exists</div>";
    } else {
        echo "<div style='color: red;'>❌ getGroupMembers() function missing</div>";
    }
    
    if (function_exists('formatCurrency')) {
        echo "<div style='color: green;'>✅ formatCurrency() function exists</div>";
    } else {
        echo "<div style='color: red;'>❌ formatCurrency() function missing</div>";
    }
    
    echo "<h3>5. Test Instructions</h3>";
    echo "<div style='background-color: #e7f3ff; padding: 15px; border-left: 4px solid #2196F3;'>";
    echo "<strong>To test the group cloning functionality:</strong><br>";
    echo "1. Click on any 'Test Clone' link above to open the clone group page<br>";
    echo "2. Modify the group name (e.g., add '- Cycle 2')<br>";
    echo "3. Select/deselect members from the original group<br>";
    echo "4. Add new members if desired<br>";
    echo "5. Set a start date<br>";
    echo "6. Click 'Create New Group' to test the cloning<br>";
    echo "7. Verify the new group is created with correct members and settings<br>";
    echo "</div>";
    
    echo "<h3>6. Navigation Links</h3>";
    echo "<div style='margin: 10px 0;'>";
    echo "<a href='admin_manage_groups.php' target='_blank' style='margin-right: 10px; color: blue;'>🔗 Manage Groups</a>";
    echo "<a href='view_group.php?id=" . ($completedGroups[0]['id'] ?? 1) . "' target='_blank' style='margin-right: 10px; color: blue;'>🔗 View Group</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
