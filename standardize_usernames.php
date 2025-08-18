<?php
require_once 'config.php';

try {
    $pdo = getDB();
    
    echo "<h2>Standardizing Usernames</h2>";
    
    // Step 1: Find members with same name but different usernames
    echo "<h3>Step 1: Finding members with inconsistent usernames</h3>";
    $stmt = $pdo->query("
        SELECT 
            member_name,
            GROUP_CONCAT(DISTINCT username ORDER BY username) as usernames,
            COUNT(DISTINCT username) as username_count,
            COUNT(*) as total_records
        FROM members 
        WHERE member_name IS NOT NULL AND member_name != ''
        GROUP BY member_name 
        HAVING COUNT(DISTINCT username) > 1 OR COUNT(*) > 1
        ORDER BY member_name
    ");
    
    $inconsistentMembers = $stmt->fetchAll();
    
    if (empty($inconsistentMembers)) {
        echo "<p>No inconsistent usernames found.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Member Name</th><th>Current Usernames</th><th>Username Count</th><th>Total Records</th></tr>";
        foreach ($inconsistentMembers as $member) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
            echo "<td>" . htmlspecialchars($member['usernames']) . "</td>";
            echo "<td>" . $member['username_count'] . "</td>";
            echo "<td>" . $member['total_records'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Step 2: Create preferred usernames
    echo "<h3>Step 2: Creating standardized usernames</h3>";
    
    $stmt = $pdo->query("
        SELECT 
            member_name,
            COALESCE(
                MIN(CASE WHEN username IS NOT NULL AND username != '' THEN username END),
                LOWER(REPLACE(REPLACE(member_name, ' ', ''), '.', ''))
            ) as preferred_username
        FROM members 
        WHERE member_name IS NOT NULL AND member_name != ''
        GROUP BY member_name
    ");
    
    $preferredUsernames = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Member Name</th><th>Preferred Username</th></tr>";
    foreach ($preferredUsernames as $member) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
        echo "<td>" . htmlspecialchars($member['preferred_username']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Step 3: Update usernames
    echo "<h3>Step 3: Updating usernames</h3>";
    
    $updateCount = 0;
    foreach ($preferredUsernames as $member) {
        $stmt = $pdo->prepare("
            UPDATE members 
            SET username = ? 
            WHERE member_name = ? AND (username IS NULL OR username = '' OR username != ?)
        ");
        $result = $stmt->execute([
            $member['preferred_username'], 
            $member['member_name'], 
            $member['preferred_username']
        ]);
        $updateCount += $stmt->rowCount();
    }
    
    echo "<p>Updated $updateCount records.</p>";
    
    // Step 4: Verify the changes
    echo "<h3>Step 4: Verification - Members after standardization</h3>";
    $stmt = $pdo->query("
        SELECT 
            member_name,
            username,
            COUNT(*) as record_count,
            GROUP_CONCAT(DISTINCT group_id ORDER BY group_id) as group_ids
        FROM members 
        WHERE member_name IS NOT NULL AND member_name != ''
        GROUP BY member_name, username
        ORDER BY member_name
    ");
    
    $verificationResults = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Member Name</th><th>Username</th><th>Record Count</th><th>Group IDs</th></tr>";
    foreach ($verificationResults as $member) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
        echo "<td>" . htmlspecialchars($member['username']) . "</td>";
        echo "<td>" . $member['record_count'] . "</td>";
        echo "<td>" . htmlspecialchars($member['group_ids']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Step 5: Check for any remaining duplicates
    echo "<h3>Step 5: Checking for remaining inconsistencies</h3>";
    $stmt = $pdo->query("
        SELECT 
            member_name,
            COUNT(DISTINCT username) as username_count,
            COUNT(*) as total_records
        FROM members 
        WHERE member_name IS NOT NULL AND member_name != ''
        GROUP BY member_name 
        HAVING COUNT(DISTINCT username) > 1
        ORDER BY member_name
    ");
    
    $remainingIssues = $stmt->fetchAll();
    
    if (empty($remainingIssues)) {
        echo "<p style='color: green;'><strong>✓ All usernames are now consistent!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>⚠ Still have inconsistent usernames:</strong></p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Member Name</th><th>Username Count</th><th>Total Records</th></tr>";
        foreach ($remainingIssues as $member) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
            echo "<td>" . $member['username_count'] . "</td>";
            echo "<td>" . $member['total_records'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>✓ Username standardization completed!</h3>";
    echo "<p><a href='admin_members.php'>Go back to Members Management</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
