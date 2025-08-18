<?php
require_once 'config.php';

echo "<h2>Testing Duplicate Member Prevention Fix</h2>";

try {
    $pdo = getDB();
    
    echo "<h3>1. Issue Fixed:</h3>";
    
    echo "<div style='border: 2px solid green; padding: 15px; border-radius: 8px; background-color: #f0fff0;'>";
    echo "<h4 style='color: green;'>✅ Duplicate Member Prevention</h4>";
    echo "<ul>";
    echo "<li><strong>Problem:</strong> SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'akhilesh' for key 'unique_username'</li>";
    echo "<li><strong>Root Cause:</strong> Trying to add existing member to same group again</li>";
    echo "<li><strong>Solution:</strong> Check if member already exists in group before adding</li>";
    echo "<li><strong>UI Improvement:</strong> Filter dropdown to show only available members</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>2. Technical Fix Applied:</h3>";
    
    echo "<div style='border: 2px solid blue; padding: 15px; border-radius: 8px; background-color: #f0f8ff;'>";
    echo "<h4>🔧 Code Changes</h4>";
    echo "<ol>";
    echo "<li><strong>Database Check:</strong> Query to check if member already in group</li>";
    echo "<li><strong>Error Prevention:</strong> Throw exception if duplicate detected</li>";
    echo "<li><strong>UI Filtering:</strong> Only show available members in dropdown</li>";
    echo "<li><strong>User Feedback:</strong> Clear messages about member availability</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>3. Files Updated:</h3>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>File</th>";
    echo "<th>Fix Applied</th>";
    echo "<th>Description</th>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td><strong>manage_members.php</strong></td>";
    echo "<td>✅ Duplicate check + UI filtering</td>";
    echo "<td>Check if member exists in group + filter dropdown</td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td><strong>admin_manage_groups.php</strong></td>";
    echo "<td>✅ Duplicate check</td>";
    echo "<td>Prevent adding same member to group twice</td>";
    echo "</tr>";
    
    echo "<tr>";
    echo "<td><strong>admin_create_group_simple.php</strong></td>";
    echo "<td>✅ Safety check</td>";
    echo "<td>Skip if member already exists (safety measure)</td>";
    echo "</tr>";
    
    echo "</table>";
    
    echo "<h3>4. Before vs After:</h3>";
    
    echo "<div style='display: flex; gap: 20px;'>";
    
    // Before
    echo "<div style='flex: 1; border: 2px solid red; padding: 15px; border-radius: 8px;'>";
    echo "<h4 style='color: red;'>❌ Before (Error)</h4>";
    echo "<ul>";
    echo "<li>Shows all existing members in dropdown</li>";
    echo "<li>No check if member already in group</li>";
    echo "<li>Tries to create duplicate member record</li>";
    echo "<li>Database constraint violation error</li>";
    echo "<li>Poor user experience</li>";
    echo "</ul>";
    echo "</div>";
    
    // After
    echo "<div style='flex: 1; border: 2px solid green; padding: 15px; border-radius: 8px;'>";
    echo "<h4 style='color: green;'>✅ After (Fixed)</h4>";
    echo "<ul>";
    echo "<li>Shows only available members in dropdown</li>";
    echo "<li>Checks if member already in group</li>";
    echo "<li>Prevents duplicate member creation</li>";
    echo "<li>Clear error message if duplicate attempted</li>";
    echo "<li>Smooth user experience</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</div>";
    
    echo "<h3>5. Test Current Groups:</h3>";
    
    // Get groups and their members
    $stmt = $pdo->query("
        SELECT g.id, g.group_name, g.total_members,
               COUNT(m.id) as actual_members
        FROM bc_groups g
        LEFT JOIN members m ON g.id = m.group_id
        GROUP BY g.id, g.group_name, g.total_members
        ORDER BY g.id
    ");
    $groups = $stmt->fetchAll();
    
    if ($groups) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>Group ID</th>";
        echo "<th>Group Name</th>";
        echo "<th>Planned Members</th>";
        echo "<th>Actual Members</th>";
        echo "<th>Test Link</th>";
        echo "</tr>";
        
        foreach ($groups as $group) {
            echo "<tr>";
            echo "<td><strong>{$group['id']}</strong></td>";
            echo "<td>" . htmlspecialchars($group['group_name']) . "</td>";
            echo "<td>{$group['total_members']}</td>";
            echo "<td>{$group['actual_members']}</td>";
            echo "<td><a href='manage_members.php?group_id={$group['id']}' target='_blank'>🔗 Test Add Member</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>6. Available vs Existing Members:</h3>";
    
    // Show example for a specific group
    if ($groups) {
        $testGroupId = $groups[0]['id'];
        
        // Get all existing members
        $stmt = $pdo->query("SELECT DISTINCT member_name FROM members ORDER BY member_name");
        $allMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get members in test group
        $stmt = $pdo->prepare("SELECT member_name FROM members WHERE group_id = ? ORDER BY member_name");
        $stmt->execute([$testGroupId]);
        $groupMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get available members for test group
        $stmt = $pdo->prepare("
            SELECT DISTINCT m1.member_name 
            FROM members m1 
            WHERE m1.member_name NOT IN (
                SELECT m2.member_name 
                FROM members m2 
                WHERE m2.group_id = ?
            )
            ORDER BY m1.member_name
        ");
        $stmt->execute([$testGroupId]);
        $availableMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div style='border: 2px solid purple; padding: 15px; border-radius: 8px; background-color: #f8f0ff;'>";
        echo "<h4>📊 Example: Group {$testGroupId} (" . htmlspecialchars($groups[0]['group_name']) . ")</h4>";
        
        echo "<div style='display: flex; gap: 20px;'>";
        
        echo "<div style='flex: 1;'>";
        echo "<h5>All Existing Members (" . count($allMembers) . ")</h5>";
        if ($allMembers) {
            foreach ($allMembers as $member) {
                echo "<span class='badge bg-secondary me-1 mb-1'>" . htmlspecialchars($member) . "</span>";
            }
        } else {
            echo "<em>No existing members</em>";
        }
        echo "</div>";
        
        echo "<div style='flex: 1;'>";
        echo "<h5>Already in Group (" . count($groupMembers) . ")</h5>";
        if ($groupMembers) {
            foreach ($groupMembers as $member) {
                echo "<span class='badge bg-danger me-1 mb-1'>" . htmlspecialchars($member) . "</span>";
            }
        } else {
            echo "<em>No members in group</em>";
        }
        echo "</div>";
        
        echo "<div style='flex: 1;'>";
        echo "<h5>Available to Add (" . count($availableMembers) . ")</h5>";
        if ($availableMembers) {
            foreach ($availableMembers as $member) {
                echo "<span class='badge bg-success me-1 mb-1'>" . htmlspecialchars($member) . "</span>";
            }
        } else {
            echo "<em>No available members</em>";
        }
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }
    
    echo "<h3>7. Error Handling:</h3>";
    
    echo "<div style='border: 2px solid orange; padding: 15px; border-radius: 8px; background-color: #fff3cd;'>";
    echo "<h4>⚠️ Error Prevention Scenarios</h4>";
    echo "<ul>";
    echo "<li><strong>Duplicate Member:</strong> 'Member [name] is already in this group.'</li>";
    echo "<li><strong>No Available Members:</strong> Shows text input only with helpful message</li>";
    echo "<li><strong>Database Errors:</strong> Graceful rollback with error message</li>";
    echo "<li><strong>Invalid Input:</strong> Form validation prevents empty submissions</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>8. User Experience Improvements:</h3>";
    
    echo "<div style='border: 2px solid teal; padding: 15px; border-radius: 8px; background-color: #f0ffff;'>";
    echo "<h4>🎨 UI Enhancements</h4>";
    echo "<ul>";
    echo "<li><strong>Smart Filtering:</strong> Dropdown only shows members that can be added</li>";
    echo "<li><strong>Clear Messages:</strong> Helpful text explaining member availability</li>";
    echo "<li><strong>Visual Feedback:</strong> Success/error messages with clear explanations</li>";
    echo "<li><strong>Consistent Behavior:</strong> Same logic across all member addition methods</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><h3>Summary of Duplicate Prevention Fix:</h3>";
echo "<ul>";
echo "<li>✅ <strong>Database Check:</strong> Verify member not already in group before adding</li>";
echo "<li>✅ <strong>UI Filtering:</strong> Dropdown shows only available members</li>";
echo "<li>✅ <strong>Error Prevention:</strong> Clear error messages for duplicate attempts</li>";
echo "<li>✅ <strong>User Guidance:</strong> Helpful text explaining member availability</li>";
echo "<li>✅ <strong>Consistent Logic:</strong> Applied across all member addition methods</li>";
echo "<li>✅ <strong>Better UX:</strong> Smooth experience without database errors</li>";
echo "</ul>";
?>
