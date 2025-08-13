<?php
require_once 'config.php';

try {
    $pdo = getDB();
    
    echo "<h2>Debugging Month 5 Payment Amount</h2>";
    
    // Check current monthly_bids table
    echo "<h3>Current monthly_bids table for Month 5:</h3>";
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
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($monthlyBid as $key => $value) {
            if (!is_numeric($key)) {
                echo "<tr><td>{$key}</td><td>";
                if (in_array($key, ['bid_amount', 'net_payable', 'gain_per_member', 'total_monthly_collection'])) {
                    echo "₹" . number_format($value);
                } else {
                    echo $value;
                }
                echo "</td></tr>";
            }
        }
        echo "</table>";
        
        // Manual calculation
        echo "<h3>Manual Calculation:</h3>";
        $bidAmount = $monthlyBid['bid_amount'];
        $totalCollection = $monthlyBid['total_monthly_collection'];
        $totalMembers = $monthlyBid['total_members'];
        
        $winnerAmount = $totalCollection - $bidAmount;
        $remainingMembers = $totalMembers - 1;
        $correctPayment = $winnerAmount / $remainingMembers;
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Calculation Step</th><th>Value</th></tr>";
        echo "<tr><td>Total Collection</td><td>₹" . number_format($totalCollection) . "</td></tr>";
        echo "<tr><td>Bid Amount</td><td>₹" . number_format($bidAmount) . "</td></tr>";
        echo "<tr><td>Winner Amount (Collection - Bid)</td><td>₹" . number_format($winnerAmount) . "</td></tr>";
        echo "<tr><td>Total Members</td><td>{$totalMembers}</td></tr>";
        echo "<tr><td>Remaining Members (Total - 1)</td><td>{$remainingMembers}</td></tr>";
        echo "<tr><td>Payment per Member (Winner Amount ÷ Remaining)</td><td>₹" . number_format($correctPayment) . "</td></tr>";
        echo "<tr><td>Current DB Value (gain_per_member)</td><td>₹" . number_format($monthlyBid['gain_per_member']) . "</td></tr>";
        echo "</table>";
        
        if (abs($monthlyBid['gain_per_member'] - $correctPayment) > 0.01) {
            echo "<p style='color: red;'>❌ <strong>Database value is incorrect!</strong></p>";
            echo "<p>Expected: ₹" . number_format($correctPayment) . "</p>";
            echo "<p>Current: ₹" . number_format($monthlyBid['gain_per_member']) . "</p>";
            
            // Fix it
            echo "<h3>Fixing the value...</h3>";
            $stmt = $pdo->prepare("
                UPDATE monthly_bids 
                SET gain_per_member = ? 
                WHERE group_id = 1 AND month_number = 5
            ");
            $result = $stmt->execute([$correctPayment]);
            
            if ($result) {
                echo "<p style='color: green;'>✅ Fixed! Updated gain_per_member to ₹" . number_format($correctPayment) . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to update!</p>";
            }
        } else {
            echo "<p style='color: green;'>✅ Database value is correct!</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ No Month 5 record found in monthly_bids table!</p>";
    }
    
    // Check what getMonthlyBids() function returns
    echo "<h3>What getMonthlyBids() function returns:</h3>";
    $monthlyBids = getMonthlyBids(1);
    $month5Bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == 5);
    $month5Bid = reset($month5Bid);
    
    if ($month5Bid) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($month5Bid as $key => $value) {
            if (!is_numeric($key)) {
                echo "<tr><td>{$key}</td><td>";
                if (in_array($key, ['bid_amount', 'net_payable', 'gain_per_member'])) {
                    echo "₹" . number_format($value);
                } else {
                    echo $value;
                }
                echo "</td></tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<p>No Month 5 data returned by getMonthlyBids()</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
