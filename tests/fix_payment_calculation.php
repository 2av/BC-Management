<?php
require_once 'config.php';

try {
    $pdo = getDB();
    
    echo "<h2>Fixing Month 5 Payment Calculation</h2>";
    
    // Check current values
    echo "<h3>Before Fix:</h3>";
    $stmt = $pdo->prepare("
        SELECT 
            mb.month_number,
            mb.bid_amount,
            mb.net_payable,
            mb.gain_per_member as current_gain_per_member,
            bg.total_monthly_collection,
            bg.total_members,
            ROUND((bg.total_monthly_collection - mb.bid_amount) / (bg.total_members - 1), 2) as correct_gain_per_member
        FROM monthly_bids mb
        JOIN bc_groups bg ON mb.group_id = bg.id
        WHERE mb.month_number = 5
    ");
    $stmt->execute();
    $before = $stmt->fetch();
    
    if ($before) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Month</th><th>Bid Amount</th><th>Net Payable</th><th>Current Gain/Member</th><th>Total Collection</th><th>Total Members</th><th>Correct Gain/Member</th></tr>";
        echo "<tr>";
        echo "<td>{$before['month_number']}</td>";
        echo "<td>₹" . number_format($before['bid_amount']) . "</td>";
        echo "<td>₹" . number_format($before['net_payable']) . "</td>";
        echo "<td>₹" . number_format($before['current_gain_per_member']) . "</td>";
        echo "<td>₹" . number_format($before['total_monthly_collection']) . "</td>";
        echo "<td>{$before['total_members']}</td>";
        echo "<td>₹" . number_format($before['correct_gain_per_member']) . "</td>";
        echo "</tr>";
        echo "</table>";
        
        // Update with correct calculation
        echo "<h3>Applying Fix...</h3>";
        $stmt = $pdo->prepare("
            UPDATE monthly_bids mb
            JOIN bc_groups bg ON mb.group_id = bg.id
            SET 
                mb.net_payable = bg.total_monthly_collection - mb.bid_amount,
                mb.gain_per_member = (bg.total_monthly_collection - mb.bid_amount) / (bg.total_members - 1)
            WHERE mb.month_number = 5
        ");
        $result = $stmt->execute();
        
        if ($result) {
            echo "<p style='color: green;'>✅ Update successful!</p>";
            
            // Check after fix
            echo "<h3>After Fix:</h3>";
            $stmt = $pdo->prepare("
                SELECT 
                    mb.month_number,
                    mb.bid_amount,
                    mb.net_payable,
                    mb.gain_per_member,
                    bg.total_monthly_collection,
                    bg.total_members,
                    ROUND((bg.total_monthly_collection - mb.bid_amount) / (bg.total_members - 1), 2) as verification
                FROM monthly_bids mb
                JOIN bc_groups bg ON mb.group_id = bg.id
                WHERE mb.month_number = 5
            ");
            $stmt->execute();
            $after = $stmt->fetch();
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Month</th><th>Bid Amount</th><th>Net Payable</th><th>Gain/Member</th><th>Total Collection</th><th>Total Members</th><th>Verification</th></tr>";
            echo "<tr>";
            echo "<td>{$after['month_number']}</td>";
            echo "<td>₹" . number_format($after['bid_amount']) . "</td>";
            echo "<td>₹" . number_format($after['net_payable']) . "</td>";
            echo "<td>₹" . number_format($after['gain_per_member']) . "</td>";
            echo "<td>₹" . number_format($after['total_monthly_collection']) . "</td>";
            echo "<td>{$after['total_members']}</td>";
            echo "<td>₹" . number_format($after['verification']) . "</td>";
            echo "</tr>";
            echo "</table>";
            
            echo "<h3>Summary:</h3>";
            echo "<ul>";
            echo "<li><strong>Bid Amount:</strong> ₹" . number_format($after['bid_amount']) . "</li>";
            echo "<li><strong>Winner Gets:</strong> ₹" . number_format($after['net_payable']) . " (₹" . number_format($after['total_monthly_collection']) . " - ₹" . number_format($after['bid_amount']) . ")</li>";
            echo "<li><strong>Each Member Pays:</strong> ₹" . number_format($after['gain_per_member']) . " (₹" . number_format($after['net_payable']) . " ÷ " . ($after['total_members'] - 1) . " members)</li>";
            echo "</ul>";
            
        } else {
            echo "<p style='color: red;'>❌ Update failed!</p>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠️ No Month 5 record found in monthly_bids table.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
