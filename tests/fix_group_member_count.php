<?php
require_once 'config.php';

try {
    $pdo = getDB();
    
    echo "<h2>Fixing Group Member Count</h2>";
    
    // Get current group data
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = 1");
    $stmt->execute();
    $group = $stmt->fetch();
    
    // Get actual member count
    $stmt = $pdo->prepare("SELECT COUNT(*) as actual_count FROM members WHERE group_id = 1");
    $stmt->execute();
    $actualCount = $stmt->fetch()['actual_count'];
    
    echo "<h3>Before Fix:</h3>";
    echo "<p><strong>Group table total_members:</strong> {$group['total_members']}</p>";
    echo "<p><strong>Actual members in database:</strong> {$actualCount}</p>";
    
    if ($group['total_members'] != $actualCount) {
        echo "<p style='color: orange;'>⚠️ Mismatch detected! Fixing...</p>";
        
        // Update group table with correct member count
        $stmt = $pdo->prepare("UPDATE bc_groups SET total_members = ? WHERE id = 1");
        $result = $stmt->execute([$actualCount]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Group member count updated successfully!</p>";
            
            // Now recalculate Month 5 payment with correct member count
            echo "<h3>Recalculating Month 5 Payment:</h3>";
            
            $stmt = $pdo->prepare("
                UPDATE monthly_bids mb
                JOIN bc_groups bg ON mb.group_id = bg.id
                SET 
                    mb.gain_per_member = (bg.total_monthly_collection - mb.bid_amount) / (bg.total_members - 1)
                WHERE mb.month_number = 5 AND mb.group_id = 1
            ");
            $result2 = $stmt->execute();
            
            if ($result2) {
                echo "<p style='color: green;'>✅ Month 5 payment calculation updated!</p>";
                
                // Show final result
                $stmt = $pdo->prepare("
                    SELECT 
                        mb.month_number,
                        mb.bid_amount,
                        mb.net_payable,
                        mb.gain_per_member,
                        bg.total_monthly_collection,
                        bg.total_members
                    FROM monthly_bids mb
                    JOIN bc_groups bg ON mb.group_id = bg.id
                    WHERE mb.month_number = 5
                ");
                $stmt->execute();
                $result = $stmt->fetch();
                
                echo "<h3>Final Result:</h3>";
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                echo "<tr><th>Field</th><th>Value</th></tr>";
                echo "<tr><td>Bid Amount</td><td>₹" . number_format($result['bid_amount']) . "</td></tr>";
                echo "<tr><td>Winner Gets (Net Payable)</td><td>₹" . number_format($result['net_payable']) . "</td></tr>";
                echo "<tr><td>Total Members</td><td>{$result['total_members']}</td></tr>";
                echo "<tr><td>Remaining Members (pay)</td><td>" . ($result['total_members'] - 1) . "</td></tr>";
                echo "<tr><td>Each Member Pays</td><td>₹" . number_format($result['gain_per_member']) . "</td></tr>";
                echo "</table>";
                
                echo "<h3>Verification:</h3>";
                $verification = $result['net_payable'] / ($result['total_members'] - 1);
                echo "<p><strong>Manual calculation:</strong> ₹" . number_format($result['net_payable']) . " ÷ " . ($result['total_members'] - 1) . " = ₹" . number_format($verification) . "</p>";
                
                if (abs($result['gain_per_member'] - $verification) < 0.01) {
                    echo "<p style='color: green;'>✅ Calculation is correct!</p>";
                } else {
                    echo "<p style='color: red;'>❌ Calculation mismatch!</p>";
                }
                
            } else {
                echo "<p style='color: red;'>❌ Failed to update Month 5 calculation!</p>";
            }
            
        } else {
            echo "<p style='color: red;'>❌ Failed to update group member count!</p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ Member counts already match!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
