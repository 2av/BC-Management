<?php
require_once 'config.php';

try {
    $pdo = getDB();
    
    echo "<h2>Checking Group Data</h2>";
    
    // Check group information
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = 1");
    $stmt->execute();
    $group = $stmt->fetch();
    
    echo "<h3>Group Information:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Group Name</td><td>{$group['group_name']}</td></tr>";
    echo "<tr><td>Total Members</td><td>{$group['total_members']}</td></tr>";
    echo "<tr><td>Monthly Collection</td><td>₹" . number_format($group['total_monthly_collection']) . "</td></tr>";
    echo "<tr><td>Monthly Contribution</td><td>₹" . number_format($group['monthly_contribution']) . "</td></tr>";
    echo "</table>";
    
    // Check actual members count
    $stmt = $pdo->prepare("SELECT COUNT(*) as actual_count FROM members WHERE group_id = 1");
    $stmt->execute();
    $actualCount = $stmt->fetch()['actual_count'];
    
    echo "<h3>Actual Members Count:</h3>";
    echo "<p><strong>Members in database:</strong> {$actualCount}</p>";
    
    // List all members
    $stmt = $pdo->prepare("SELECT member_number, member_name FROM members WHERE group_id = 1 ORDER BY member_number");
    $stmt->execute();
    $members = $stmt->fetchAll();
    
    echo "<h3>All Members:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Member #</th><th>Name</th></tr>";
    foreach ($members as $member) {
        echo "<tr><td>{$member['member_number']}</td><td>{$member['member_name']}</td></tr>";
    }
    echo "</table>";
    
    // Check Month 5 calculation
    echo "<h3>Month 5 Calculation Analysis:</h3>";
    $bidAmount = 600;
    $totalCollection = $group['total_monthly_collection'];
    $totalMembers = $group['total_members'];
    $actualMembers = $actualCount;
    
    $winnerAmount = $totalCollection - $bidAmount;
    $remainingMembers = $totalMembers - 1; // Winner doesn't pay
    $actualRemainingMembers = $actualMembers - 1; // Winner doesn't pay
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Description</th><th>Value</th></tr>";
    echo "<tr><td>Bid Amount</td><td>₹" . number_format($bidAmount) . "</td></tr>";
    echo "<tr><td>Total Collection</td><td>₹" . number_format($totalCollection) . "</td></tr>";
    echo "<tr><td>Winner Gets</td><td>₹" . number_format($winnerAmount) . "</td></tr>";
    echo "<tr><td>Total Members (from group table)</td><td>{$totalMembers}</td></tr>";
    echo "<tr><td>Actual Members (from members table)</td><td>{$actualMembers}</td></tr>";
    echo "<tr><td>Remaining Members (group table - 1)</td><td>{$remainingMembers}</td></tr>";
    echo "<tr><td>Actual Remaining Members (actual - 1)</td><td>{$actualRemainingMembers}</td></tr>";
    echo "<tr><td>Payment per Member (using group table)</td><td>₹" . number_format($winnerAmount / $remainingMembers) . "</td></tr>";
    echo "<tr><td>Payment per Member (using actual count)</td><td>₹" . number_format($winnerAmount / $actualRemainingMembers) . "</td></tr>";
    echo "</table>";
    
    echo "<h3>Recommendation:</h3>";
    if ($totalMembers != $actualMembers) {
        echo "<p style='color: orange;'>⚠️ <strong>Mismatch found!</strong></p>";
        echo "<ul>";
        echo "<li>Group table says: {$totalMembers} members</li>";
        echo "<li>Members table has: {$actualMembers} members</li>";
        echo "<li>We should use the <strong>actual member count</strong> for calculations</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>✅ Member counts match!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
