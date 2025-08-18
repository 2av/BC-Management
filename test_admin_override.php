<?php
require_once 'config.php';

echo "<h2>Testing Admin Override Functionality</h2>";

try {
    $pdo = getDB();
    $groupId = 1;
    
    // Test 1: Check updated table structure
    echo "<h3>1. Updated Random Picks Table Structure:</h3>";
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
    
    // Test 2: Check random picks with admin override data
    echo "<h3>2. Random Picks with Admin Override Information:</h3>";
    $randomPicks = getRandomPicks($groupId);
    
    if ($randomPicks) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Month</th><th>Random Pick</th><th>Admin Override</th><th>Final Selection</th><th>Status</th></tr>";
        foreach ($randomPicks as $pick) {
            $finalMember = $pick['admin_override_member_name'] ?: $pick['member_name'];
            $status = $pick['admin_override_member_id'] ? 'Admin Override' : 'Random Pick Only';
            
            echo "<tr>";
            echo "<td>Month {$pick['month_number']}</td>";
            echo "<td>🎲 {$pick['member_name']}</td>";
            echo "<td>" . ($pick['admin_override_member_name'] ? "👨‍💼 {$pick['admin_override_member_name']}" : '-') . "</td>";
            echo "<td><strong>{$finalMember}</strong></td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No random picks found. Please make some random picks first.</p>";
    }
    
    // Test 3: Available members for random pick
    echo "<h3>3. Available Members for Random Pick:</h3>";
    $availableMembers = getAvailableMembersForRandomPick($groupId);
    
    if ($availableMembers) {
        echo "<p>Found " . count($availableMembers) . " available members:</p>";
        echo "<ul>";
        foreach ($availableMembers as $member) {
            echo "<li>#{$member['member_number']} - {$member['member_name']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No available members (all have already won or been selected).</p>";
    }
    
    // Test 4: Display logic simulation
    echo "<h3>4. Display Logic Simulation:</h3>";
    echo "<p>This shows how names will be displayed in member_group_view.php:</p>";
    
    if ($randomPicks) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Month</th><th>Display Output</th><th>Logic</th></tr>";
        
        foreach ($randomPicks as $pick) {
            echo "<tr>";
            echo "<td>Month {$pick['month_number']}</td>";
            echo "<td>";
            
            if ($pick['admin_override_member_id']) {
                if ($pick['admin_override_member_id'] == $pick['selected_member_id']) {
                    // Same person selected by both random and admin
                    echo '<span style="color: purple;">' . htmlspecialchars($pick['admin_override_member_name']) . ' 🎲👨‍💼</span>';
                    $logic = "Same person - show once with both icons";
                } else {
                    // Different people
                    echo '<div style="font-size: 11px; line-height: 1.2;">';
                    echo '<div style="color: #888; text-decoration: line-through;">🎲 ' . htmlspecialchars($pick['member_name']) . '</div>';
                    echo '<div style="color: green;">👨‍💼 ' . htmlspecialchars($pick['admin_override_member_name']) . '</div>';
                    echo '</div>';
                    $logic = "Different people - show both (random crossed out, admin active)";
                }
            } else {
                // Only random pick
                echo '<span style="color: blue;">' . htmlspecialchars($pick['member_name']) . ' 🎲</span>';
                $logic = "Random pick only - show with dice icon";
            }
            
            echo "</td>";
            echo "<td>{$logic}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 5: Show admin management links
    echo "<h3>5. Admin Management Links:</h3>";
    echo "<ul>";
    echo "<li><a href='admin_manage_random_picks.php?group_id={$groupId}' target='_blank'>🎲 Manage Random Picks</a></li>";
    echo "<li><a href='view_group.php?id={$groupId}' target='_blank'>👨‍💼 Admin Group View</a></li>";
    echo "<li><a href='member_group_view.php?group_id={$groupId}' target='_blank'>👥 Member Group View</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><h3>How to Test:</h3>";
echo "<ol>";
echo "<li><strong>Make Random Picks:</strong> Go to member view and click 🎲 Pick buttons</li>";
echo "<li><strong>Admin Override:</strong> Go to admin management page and override some picks</li>";
echo "<li><strong>View Results:</strong> Check member view to see the display logic in action</li>";
echo "<li><strong>Test Same Selection:</strong> Admin selects the same person as random pick</li>";
echo "<li><strong>Test Different Selection:</strong> Admin selects different person than random pick</li>";
echo "</ol>";
?>
