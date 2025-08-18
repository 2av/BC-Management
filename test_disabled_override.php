<?php
require_once 'config.php';

echo "<h2>Testing Disabled Override Functionality</h2>";

try {
    $pdo = getDB();
    $groupId = 1;
    
    // Get data
    $randomPicks = getRandomPicks($groupId);
    $monthlyBids = getMonthlyBids($groupId);
    
    echo "<h3>1. Random Picks vs Monthly Bids Status:</h3>";
    
    if ($randomPicks) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        echo "<th>Month</th>";
        echo "<th>Random Pick</th>";
        echo "<th>Admin Override</th>";
        echo "<th>Monthly Bid Status</th>";
        echo "<th>Override Allowed?</th>";
        echo "<th>Reason</th>";
        echo "</tr>";
        
        foreach ($randomPicks as $pick) {
            $hasBid = array_filter($monthlyBids, fn($bid) => $bid['month_number'] == $pick['month_number']);
            $bid = reset($hasBid);
            
            $overrideAllowed = !$hasBid;
            $reason = $hasBid ? "Amount already taken by " . $bid['member_name'] : "Available for override";
            
            echo "<tr>";
            echo "<td><strong>Month {$pick['month_number']}</strong></td>";
            echo "<td>🎲 {$pick['member_name']}</td>";
            echo "<td>" . ($pick['admin_override_member_name'] ? "👨‍💼 {$pick['admin_override_member_name']}" : '-') . "</td>";
            echo "<td>";
            if ($hasBid) {
                echo "<span style='color: green; font-weight: bold;'>✓ COMPLETED</span><br>";
                echo "<small>Winner: {$bid['member_name']}<br>";
                echo "Amount: " . formatCurrency($bid['net_payable']) . "</small>";
            } else {
                echo "<span style='color: orange;'>⏳ PENDING</span>";
            }
            echo "</td>";
            echo "<td>";
            if ($overrideAllowed) {
                echo "<span style='color: green; font-weight: bold;'>✓ YES</span>";
            } else {
                echo "<span style='color: red; font-weight: bold;'>✗ NO</span>";
            }
            echo "</td>";
            echo "<td><small>{$reason}</small></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No random picks found.</p>";
    }
    
    echo "<h3>2. Admin Management Page Sections:</h3>";
    
    // Separate random picks into available and completed
    $availableMonthsForOverride = [];
    $completedMonths = [];
    foreach ($randomPicks as $pick) {
        $hasBid = array_filter($monthlyBids, fn($bid) => $bid['month_number'] == $pick['month_number']);
        if (!$hasBid) {
            $availableMonthsForOverride[] = $pick;
        } else {
            $completedMonths[] = $pick;
        }
    }
    
    echo "<div style='display: flex; gap: 20px;'>";
    
    // Available section
    echo "<div style='flex: 1; border: 2px solid green; padding: 15px; border-radius: 8px;'>";
    echo "<h4 style='color: green;'>✅ Available for Override (" . count($availableMonthsForOverride) . ")</h4>";
    if ($availableMonthsForOverride) {
        echo "<ul>";
        foreach ($availableMonthsForOverride as $pick) {
            echo "<li>Month {$pick['month_number']} - {$pick['member_name']}";
            if ($pick['admin_override_member_name']) {
                echo " → {$pick['admin_override_member_name']}";
            }
            echo "</li>";
        }
        echo "</ul>";
        echo "<p><strong>Actions Available:</strong></p>";
        echo "<ul>";
        echo "<li>✓ Apply admin override</li>";
        echo "<li>✓ Remove existing override</li>";
        echo "<li>✓ Change override selection</li>";
        echo "</ul>";
    } else {
        echo "<p>No months available for override.</p>";
    }
    echo "</div>";
    
    // Completed section
    echo "<div style='flex: 1; border: 2px solid #ccc; padding: 15px; border-radius: 8px; background-color: #f8f9fa;'>";
    echo "<h4 style='color: #666;'>🔒 Completed (Disabled) (" . count($completedMonths) . ")</h4>";
    if ($completedMonths) {
        echo "<ul>";
        foreach ($completedMonths as $pick) {
            $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $pick['month_number']);
            $bid = reset($bid);
            echo "<li>Month {$pick['month_number']} - Final: {$bid['member_name']} (" . formatCurrency($bid['net_payable']) . ")</li>";
        }
        echo "</ul>";
        echo "<p><strong>Actions Disabled:</strong></p>";
        echo "<ul>";
        echo "<li>✗ Cannot apply override</li>";
        echo "<li>✗ Cannot remove override</li>";
        echo "<li>✗ Cannot modify selection</li>";
        echo "</ul>";
        echo "<p><small><strong>Reason:</strong> Amount has already been distributed to the winner.</small></p>";
    } else {
        echo "<p>No completed months yet.</p>";
    }
    echo "</div>";
    
    echo "</div>";
    
    echo "<h3>3. Security Validation:</h3>";
    echo "<div style='border: 2px solid orange; padding: 15px; border-radius: 8px; background-color: #fff3cd;'>";
    echo "<h4>🛡️ Backend Protection</h4>";
    echo "<ul>";
    echo "<li><strong>Override Attempt on Completed Month:</strong> Returns error message</li>";
    echo "<li><strong>Remove Override on Completed Month:</strong> Returns error message</li>";
    echo "<li><strong>UI Prevention:</strong> Completed months shown in disabled section</li>";
    echo "<li><strong>Database Integrity:</strong> Monthly bids table checked before any override operation</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>4. Test Links:</h3>";
    echo "<ul>";
    echo "<li><a href='admin_manage_random_picks.php?group_id={$groupId}' target='_blank'>🎲 Admin Manage Random Picks</a></li>";
    echo "<li><a href='member_group_view.php?group_id={$groupId}' target='_blank'>👥 Member Group View</a></li>";
    echo "<li><a href='add_bid.php?group_id={$groupId}' target='_blank'>💰 Add Monthly Bid (to test completion)</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><h3>How to Test Disabled Functionality:</h3>";
echo "<ol>";
echo "<li><strong>Make Random Picks:</strong> Use 🎲 Pick buttons for some months</li>";
echo "<li><strong>Add Monthly Bid:</strong> Create a monthly bid for one of the random picked months</li>";
echo "<li><strong>Check Admin Page:</strong> See how completed months appear in disabled section</li>";
echo "<li><strong>Try Override:</strong> Attempt to override a completed month (should show error)</li>";
echo "<li><strong>Verify Protection:</strong> Confirm backend prevents modifications to completed months</li>";
echo "</ol>";
?>
