<?php
require_once 'config.php';

echo "<h2>Fixing Username Constraint Issue</h2>";

try {
    $pdo = getDB();
    
    echo "<h3>Current Issue:</h3>";
    echo "<div style='border: 2px solid red; padding: 15px; border-radius: 8px; background-color: #ffe6e6;'>";
    echo "<h4 style='color: red;'>❌ Problem</h4>";
    echo "<ul>";
    echo "<li><strong>Error:</strong> SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'akhilesh' for key 'unique_username'</li>";
    echo "<li><strong>Cause:</strong> Database has UNIQUE constraint on username across ALL groups</li>";
    echo "<li><strong>Impact:</strong> Same member cannot be added to multiple groups</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>Applying Fix:</h3>";
    
    // Step 1: Check current constraints
    echo "<p><strong>Step 1:</strong> Checking current constraints...</p>";
    $stmt = $pdo->query("SHOW INDEX FROM members WHERE Key_name LIKE '%username%'");
    $currentIndexes = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($currentIndexes as $index) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
        echo "<td>" . ($index['Non_unique'] ? 'No' : 'Yes') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Step 2: Drop the unique constraint on username
    echo "<p><strong>Step 2:</strong> Removing global username unique constraint...</p>";
    try {
        $pdo->exec("ALTER TABLE members DROP INDEX unique_username");
        echo "<span style='color: green;'>✅ Successfully removed unique_username constraint</span><br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "check that column/key exists") !== false) {
            echo "<span style='color: orange;'>⚠️ unique_username constraint already removed</span><br>";
        } else {
            throw $e;
        }
    }
    
    // Step 3: Add composite unique constraint for username per group
    echo "<p><strong>Step 3:</strong> Adding username unique per group constraint...</p>";
    try {
        $pdo->exec("ALTER TABLE members ADD UNIQUE KEY unique_username_per_group (group_id, username)");
        echo "<span style='color: green;'>✅ Successfully added unique_username_per_group constraint</span><br>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "Duplicate key name") !== false) {
            echo "<span style='color: orange;'>⚠️ unique_username_per_group constraint already exists</span><br>";
        } else {
            throw $e;
        }
    }
    
    // Step 4: Verify the changes
    echo "<p><strong>Step 4:</strong> Verifying new constraints...</p>";
    $stmt = $pdo->query("SHOW INDEX FROM members WHERE Key_name LIKE '%username%'");
    $newIndexes = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($newIndexes as $index) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
        echo "<td>" . ($index['Non_unique'] ? 'No' : 'Yes') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Fix Applied Successfully!</h3>";
    
    echo "<div style='border: 2px solid green; padding: 15px; border-radius: 8px; background-color: #f0fff0;'>";
    echo "<h4 style='color: green;'>✅ Solution Implemented</h4>";
    echo "<ul>";
    echo "<li><strong>Removed:</strong> Global unique constraint on username</li>";
    echo "<li><strong>Added:</strong> Unique constraint per group (group_id, username)</li>";
    echo "<li><strong>Result:</strong> Same member can now have same username across different groups</li>";
    echo "<li><strong>Benefit:</strong> Consistent login credentials for members across all their groups</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>Current Members Status:</h3>";
    
    // Display current members
    $stmt = $pdo->query("
        SELECT 
            m.id,
            m.group_id,
            g.group_name,
            m.member_name,
            m.username,
            m.member_number
        FROM members m
        JOIN bc_groups g ON m.group_id = g.id
        ORDER BY m.member_name, m.group_id
    ");
    $members = $stmt->fetchAll();
    
    if ($members) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>Member ID</th>";
        echo "<th>Group ID</th>";
        echo "<th>Group Name</th>";
        echo "<th>Member Name</th>";
        echo "<th>Username</th>";
        echo "<th>Member #</th>";
        echo "</tr>";
        
        foreach ($members as $member) {
            echo "<tr>";
            echo "<td>{$member['id']}</td>";
            echo "<td>{$member['group_id']}</td>";
            echo "<td>" . htmlspecialchars($member['group_name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
            echo "<td><code>" . htmlspecialchars($member['username']) . "</code></td>";
            echo "<td>{$member['member_number']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Test the Fix:</h3>";
    echo "<ul>";
    echo "<li><a href='manage_members.php?group_id=4' target='_blank'>🔗 Test Group 4 Member Addition</a></li>";
    echo "<li><a href='admin_manage_groups.php' target='_blank'>📊 Manage All Groups</a></li>";
    echo "<li><a href='admin_create_group_simple.php' target='_blank'>🆕 Create New Group</a></li>";
    echo "</ul>";
    
    echo "<div style='border: 2px solid blue; padding: 15px; border-radius: 8px; background-color: #f0f8ff; margin-top: 20px;'>";
    echo "<h4>🎯 What This Fix Enables:</h4>";
    echo "<ul>";
    echo "<li><strong>Cross-Group Membership:</strong> Same member can join multiple groups</li>";
    echo "<li><strong>Consistent Credentials:</strong> Same username/password across all groups</li>";
    echo "<li><strong>No More Errors:</strong> No duplicate username constraint violations</li>";
    echo "<li><strong>Better UX:</strong> Smooth member addition process</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='border: 2px solid red; padding: 15px; border-radius: 8px; background-color: #ffe6e6;'>";
    echo "<h4 style='color: red;'>❌ Error Applying Fix</h4>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Please try running the SQL manually or contact support.</strong></p>";
    echo "</div>";
}
?>
