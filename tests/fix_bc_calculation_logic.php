<?php
require_once 'config.php';

try {
    $pdo = getDB();
    
    echo "<h2>Fixing BC Calculation Logic</h2>";
    
    // Get current data
    $stmt = $pdo->prepare("
        SELECT 
            mb.*,
            bg.total_members,
            bg.total_monthly_collection
        FROM monthly_bids mb
        JOIN bc_groups bg ON mb.group_id = bg.id
        WHERE mb.month_number = 5
    ");
    $stmt->execute();
    $monthlyBid = $stmt->fetch();
    
    if ($monthlyBid) {
        echo "<h3>Current (Incorrect) Calculation:</h3>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Bid Amount</td><td>₹" . number_format($monthlyBid['bid_amount']) . "</td></tr>";
        echo "<tr><td>Net Payable (Winner Gets)</td><td>₹" . number_format($monthlyBid['net_payable']) . "</td></tr>";
        echo "<tr><td>Current Gain Per Member</td><td>₹" . number_format($monthlyBid['gain_per_member']) . "</td></tr>";
        echo "<tr><td>Total Members</td><td>{$monthlyBid['total_members']}</td></tr>";
        echo "</table>";
        
        echo "<h3>Correct BC System Logic:</h3>";
        $bidAmount = $monthlyBid['bid_amount'];
        $totalCollection = $monthlyBid['total_monthly_collection'];
        $totalMembers = $monthlyBid['total_members'];
        
        // Correct BC calculation:
        // 1. Winner gets: Total Collection - Bid Amount
        // 2. Each member pays: Bid Amount ÷ Total Members (including winner)
        $winnerGets = $totalCollection - $bidAmount;
        $eachMemberPays = $bidAmount / $totalMembers;
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Calculation Step</th><th>Value</th><th>Explanation</th></tr>";
        echo "<tr><td>Total Collection</td><td>₹" . number_format($totalCollection) . "</td><td>Monthly collection from all members</td></tr>";
        echo "<tr><td>Winning Bid</td><td>₹" . number_format($bidAmount) . "</td><td>Amount winner bid</td></tr>";
        echo "<tr><td>Winner Gets</td><td>₹" . number_format($winnerGets) . "</td><td>Total Collection - Bid Amount</td></tr>";
        echo "<tr><td>Total Members</td><td>{$totalMembers}</td><td>All members (including winner)</td></tr>";
        echo "<tr><td>Each Member Pays</td><td>₹" . number_format($eachMemberPays) . "</td><td>Bid Amount ÷ Total Members</td></tr>";
        echo "</table>";
        
        echo "<h3>Financial Flow Verification:</h3>";
        $totalCollected = $eachMemberPays * $totalMembers;
        $totalPaidOut = $winnerGets;
        $difference = $totalCollected - $totalPaidOut;
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Flow</th><th>Amount</th></tr>";
        echo "<tr><td>Total Collected ({$totalMembers} × ₹" . number_format($eachMemberPays) . ")</td><td>₹" . number_format($totalCollected) . "</td></tr>";
        echo "<tr><td>Paid to Winner</td><td>₹" . number_format($totalPaidOut) . "</td></tr>";
        echo "<tr><td>Difference (Should be Bid Amount)</td><td>₹" . number_format($difference) . "</td></tr>";
        echo "<tr><td>Expected Difference (Bid Amount)</td><td>₹" . number_format($bidAmount) . "</td></tr>";
        echo "</table>";
        
        if (abs($difference - $bidAmount) < 0.01) {
            echo "<p style='color: green;'>✅ Financial flow is correct!</p>";
        } else {
            echo "<p style='color: red;'>❌ Financial flow mismatch!</p>";
        }
        
        echo "<h3>Updating Database with Correct Values:</h3>";
        
        // Update with correct calculation
        $stmt = $pdo->prepare("
            UPDATE monthly_bids 
            SET 
                net_payable = ?,
                gain_per_member = ?
            WHERE group_id = 1 AND month_number = 5
        ");
        $result = $stmt->execute([$winnerGets, $eachMemberPays]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Database updated successfully!</p>";
            
            echo "<h3>Final Correct Values:</h3>";
            echo "<ul>";
            echo "<li><strong>Winner gets:</strong> ₹" . number_format($winnerGets) . "</li>";
            echo "<li><strong>Each member pays:</strong> ₹" . number_format($eachMemberPays) . "</li>";
            echo "<li><strong>Winner also pays:</strong> ₹" . number_format($eachMemberPays) . " (like everyone else)</li>";
            echo "<li><strong>Winner's net benefit:</strong> ₹" . number_format($winnerGets - $eachMemberPays) . "</li>";
            echo "</ul>";
            
        } else {
            echo "<p style='color: red;'>❌ Failed to update database!</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ No Month 5 record found!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
