<?php
require_once 'config.php';

echo "<h2>Testing Current Month Only Functionality</h2>";

try {
    $pdo = getDB();
    $groupId = 1;
    
    // Get current active month
    $currentActiveMonth = getCurrentActiveMonthNumber($groupId);
    
    echo "<h3>1. Current Active Month Analysis:</h3>";
    echo "<div style='border: 2px solid blue; padding: 15px; border-radius: 8px; background-color: #e3f2fd;'>";
    echo "<h4>📅 Current Active Month: " . ($currentActiveMonth ?: 'None (Group Complete)') . "</h4>";
    
    if ($currentActiveMonth) {
        echo "<p><strong>Logic:</strong> This is the next month after the last completed monthly bid.</p>";
        
        // Show completed months
        $stmt = $pdo->prepare("SELECT month_number, member_name FROM monthly_bids mb JOIN members m ON mb.taken_by_member_id = m.id WHERE mb.group_id = ? ORDER BY month_number");
        $stmt->execute([$groupId]);
        $completedBids = $stmt->fetchAll();
        
        if ($completedBids) {
            echo "<p><strong>Completed Months:</strong></p>";
            echo "<ul>";
            foreach ($completedBids as $bid) {
                echo "<li>Month {$bid['month_number']} - {$bid['member_name']}</li>";
            }
            echo "</ul>";
        }
        
        echo "<p><strong>Available for Random Pick:</strong> Only Month $currentActiveMonth</p>";
    } else {
        echo "<p><strong>Status:</strong> Group is complete - no more months available.</p>";
    }
    echo "</div>";
    
    // Get group info
    $group = getGroupById($groupId);
    $totalMonths = $group['total_members'];
    
    echo "<h3>2. Month Status Overview:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>Month</th>";
    echo "<th>Status</th>";
    echo "<th>Random Pick Allowed?</th>";
    echo "<th>Admin Override Allowed?</th>";
    echo "<th>Reason</th>";
    echo "</tr>";
    
    for ($month = 1; $month <= $totalMonths; $month++) {
        // Check if month has bid
        $stmt = $pdo->prepare("SELECT member_name FROM monthly_bids mb JOIN members m ON mb.taken_by_member_id = m.id WHERE mb.group_id = ? AND mb.month_number = ?");
        $stmt->execute([$groupId, $month]);
        $bid = $stmt->fetch();
        
        // Check if month has random pick
        $stmt = $pdo->prepare("SELECT member_name FROM random_picks rp JOIN members m ON rp.selected_member_id = m.id WHERE rp.group_id = ? AND rp.month_number = ?");
        $stmt->execute([$groupId, $month]);
        $randomPick = $stmt->fetch();
        
        $status = '';
        $randomAllowed = false;
        $overrideAllowed = false;
        $reason = '';
        
        if ($bid) {
            $status = "✅ COMPLETED ({$bid['member_name']})";
            $reason = "Amount already taken";
        } elseif ($randomPick) {
            if ($currentActiveMonth && $month == $currentActiveMonth) {
                $status = "🎲 RANDOM PICKED ({$randomPick['member_name']})";
                $overrideAllowed = true;
                $reason = "Current month - admin can override";
            } else {
                $status = "🎲 RANDOM PICKED ({$randomPick['member_name']})";
                $reason = $month < $currentActiveMonth ? "Past month" : "Future month";
            }
        } elseif ($currentActiveMonth && $month == $currentActiveMonth) {
            $status = "🟡 CURRENT ACTIVE";
            $randomAllowed = true;
            $overrideAllowed = false;
            $reason = "Available for random pick";
        } elseif ($month < $currentActiveMonth) {
            $status = "🔒 PAST";
            $reason = "Month has passed";
        } else {
            $status = "⏳ FUTURE";
            $reason = "Month not active yet";
        }
        
        echo "<tr>";
        echo "<td><strong>Month $month</strong></td>";
        echo "<td>$status</td>";
        echo "<td>" . ($randomAllowed ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO</span>') . "</td>";
        echo "<td>" . ($overrideAllowed ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO</span>') . "</td>";
        echo "<td><small>$reason</small></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>3. User Interface Behavior:</h3>";
    echo "<div style='display: flex; gap: 20px;'>";
    
    // Member View
    echo "<div style='flex: 1; border: 2px solid green; padding: 15px; border-radius: 8px;'>";
    echo "<h4 style='color: green;'>👥 Member Group View</h4>";
    echo "<ul>";
    echo "<li><strong>Current Month ($currentActiveMonth):</strong> Shows 🎲 Pick button</li>";
    echo "<li><strong>Past Months:</strong> Shows 🔒 Past</li>";
    echo "<li><strong>Future Months:</strong> Shows ⏳ Future</li>";
    echo "<li><strong>Completed Months:</strong> Shows Done</li>";
    echo "</ul>";
    echo "</div>";
    
    // Admin View
    echo "<div style='flex: 1; border: 2px solid blue; padding: 15px; border-radius: 8px;'>";
    echo "<h4 style='color: blue;'>👨‍💼 Admin Management</h4>";
    echo "<ul>";
    echo "<li><strong>Current Month Random Picks:</strong> Can override</li>";
    echo "<li><strong>Past/Future Random Picks:</strong> Disabled section</li>";
    echo "<li><strong>Completed Months:</strong> Locked section</li>";
    echo "<li><strong>Override Attempts:</strong> Validated server-side</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "</div>";
    
    echo "<h3>4. Security Validation:</h3>";
    echo "<div style='border: 2px solid orange; padding: 15px; border-radius: 8px; background-color: #fff3cd;'>";
    echo "<h4>🛡️ Protection Layers</h4>";
    echo "<ol>";
    echo "<li><strong>UI Level:</strong> Buttons only shown for current month</li>";
    echo "<li><strong>JavaScript Level:</strong> Client-side validation</li>";
    echo "<li><strong>Backend Level:</strong> Server validates current month</li>";
    echo "<li><strong>Database Level:</strong> Consistent state maintained</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>5. Test Links:</h3>";
    echo "<ul>";
    echo "<li><a href='member_group_view.php?group_id={$groupId}' target='_blank'>👥 Member Group View (Current Month: $currentActiveMonth)</a></li>";
    echo "<li><a href='admin_manage_random_picks.php?group_id={$groupId}' target='_blank'>👨‍💼 Admin Random Picks Management</a></li>";
    echo "<li><a href='add_bid.php?group_id={$groupId}' target='_blank'>💰 Add Monthly Bid (to advance current month)</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><br><h3>How Current Month Logic Works:</h3>";
echo "<ol>";
echo "<li><strong>Determine Current Month:</strong> Last completed bid month + 1</li>";
echo "<li><strong>Random Pick Restriction:</strong> Only current month gets 🎲 Pick button</li>";
echo "<li><strong>Admin Override Restriction:</strong> Only current month random picks can be overridden</li>";
echo "<li><strong>Past Months:</strong> Show as 🔒 Past (disabled)</li>";
echo "<li><strong>Future Months:</strong> Show as ⏳ Future (disabled)</li>";
echo "<li><strong>Completed Months:</strong> Show as Done (locked)</li>";
echo "</ol>";
?>
