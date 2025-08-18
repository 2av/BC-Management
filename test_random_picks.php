<?php
require_once 'config.php';

echo "<h2>Testing Random Picks Functionality</h2>";

try {
    $pdo = getDB();
    
    // Test 1: Check if random_picks table exists
    echo "<h3>1. Checking if random_picks table exists:</h3>";
    $stmt = $pdo->query("DESCRIBE random_picks");
    $columns = $stmt->fetchAll();
    
    if ($columns) {
        echo "<p style='color: green;'>✓ random_picks table exists!</p>";
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
    } else {
        echo "<p style='color: red;'>✗ random_picks table does not exist!</p>";
    }
    
    // Test 2: Check available members for random pick
    echo "<h3>2. Available members for random pick in Group 1:</h3>";
    $availableMembers = getAvailableMembersForRandomPick(1);
    
    if ($availableMembers) {
        echo "<p style='color: green;'>✓ Found " . count($availableMembers) . " available members:</p>";
        echo "<ul>";
        foreach ($availableMembers as $member) {
            echo "<li>ID: {$member['id']}, Name: {$member['member_name']}, Number: {$member['member_number']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠ No available members for random pick (all have already won)</p>";
    }
    
    // Test 3: Check existing random picks
    echo "<h3>3. Existing random picks for Group 1:</h3>";
    $randomPicks = getRandomPicks(1);
    
    if ($randomPicks) {
        echo "<p style='color: green;'>✓ Found " . count($randomPicks) . " existing random picks:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Month</th><th>Selected Member</th><th>Picked At</th><th>Picked By Type</th></tr>";
        foreach ($randomPicks as $pick) {
            echo "<tr>";
            echo "<td>{$pick['month_number']}</td>";
            echo "<td>{$pick['member_name']}</td>";
            echo "<td>{$pick['picked_at']}</td>";
            echo "<td>{$pick['picked_by_type']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: blue;'>ℹ No random picks have been made yet</p>";
    }
    
    // Test 4: Check monthly bids to see which months are taken
    echo "<h3>4. Monthly bids for Group 1:</h3>";
    $monthlyBids = getMonthlyBids(1);
    
    if ($monthlyBids) {
        echo "<p style='color: green;'>✓ Found " . count($monthlyBids) . " monthly bids:</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Month</th><th>Taken By</th><th>Is Bid</th><th>Bid Amount</th></tr>";
        foreach ($monthlyBids as $bid) {
            echo "<tr>";
            echo "<td>{$bid['month_number']}</td>";
            echo "<td>{$bid['member_name']}</td>";
            echo "<td>{$bid['is_bid']}</td>";
            echo "<td>" . formatCurrency($bid['bid_amount']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: blue;'>ℹ No monthly bids found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><a href='member_group_view.php?group_id=1'>← Back to Group View</a>";
?>
