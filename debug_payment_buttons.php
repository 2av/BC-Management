<?php
require_once 'config.php';

echo "<h2>🔧 Payment Button Debug Tool</h2>";
echo "<p>This tool will help diagnose why payment buttons might not be showing.</p>";

try {
    // Check member login status
    echo "<h3>1. Login Status Check</h3>";
    if (isMemberLoggedIn()) {
        $member = getMemberById($_SESSION['member_id']);
        echo "<div style='color: green;'>✅ Logged in as member: " . htmlspecialchars($member['member_name']) . "</div>";
        echo "<div>Member ID: " . $member['id'] . "</div>";
        echo "<div>Group ID: " . $member['group_id'] . "</div>";
        
        $group = getGroupById($member['group_id']);
        echo "<div>Group Name: " . htmlspecialchars($group['group_name']) . "</div>";
    } else {
        echo "<div style='color: red;'>❌ Not logged in as member</div>";
        echo "<p><strong>Solution:</strong> <a href='member_login.php'>Login as member first</a></p>";
        return;
    }
    
    // Check payment configuration
    echo "<h3>2. Payment Configuration Check</h3>";
    $configs = getPaymentConfig();
    if (empty($configs)) {
        echo "<div style='color: red;'>❌ Payment configuration missing</div>";
        echo "<p><strong>Solution:</strong> Run add_payment_config_table.sql</p>";
    } else {
        echo "<div style='color: green;'>✅ Payment configuration exists</div>";
        
        if (($configs['qr_enabled'] ?? '1') == '1') {
            echo "<div style='color: green;'>✅ QR payments enabled</div>";
        } else {
            echo "<div style='color: red;'>❌ QR payments disabled</div>";
            echo "<p><strong>Solution:</strong> Enable in <a href='admin_payment_config.php'>Payment Settings</a></p>";
        }
    }
    
    // Check group data
    echo "<h3>3. Group Data Check</h3>";
    $groupId = $member['group_id'];
    $members = getGroupMembers($groupId);
    $monthlyBids = getMonthlyBids($groupId);
    $memberPayments = getMemberPayments($groupId);
    
    echo "<div>Total members in group: " . count($members) . "</div>";
    echo "<div>Total monthly bids: " . count($monthlyBids) . "</div>";
    echo "<div>Total member payments: " . count($memberPayments) . "</div>";
    
    if (empty($monthlyBids)) {
        echo "<div style='color: red;'>❌ No monthly bids found</div>";
        echo "<p><strong>This is why payment buttons don't appear!</strong></p>";
        echo "<p><strong>Solution:</strong> Admin needs to add monthly bids using <a href='add_bid.php?group_id={$groupId}'>Add Monthly Bid</a></p>";
        return;
    }
    
    // Check member's payment status
    echo "<h3>4. Member Payment Status</h3>";
    
    $myPayments = array_filter($memberPayments, fn($p) => $p['member_id'] == $member['id']);
    $myPaymentsByMonth = [];
    foreach ($myPayments as $payment) {
        $myPaymentsByMonth[$payment['month_number']] = $payment;
    }
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Month</th><th>Bid Exists</th><th>Winner</th><th>Payment Status</th><th>Should Show Button</th>";
    echo "</tr>";
    
    for ($i = 1; $i <= $group['total_members']; $i++) {
        $payment = $myPaymentsByMonth[$i] ?? null;
        $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
        $bid = reset($bid);
        
        $shouldShowButton = (!$payment && $bid) ? 'YES' : 'NO';
        $buttonColor = $shouldShowButton === 'YES' ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>Month {$i}</td>";
        echo "<td>" . ($bid ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($bid ? htmlspecialchars($bid['member_name']) : '-') . "</td>";
        echo "<td>" . ($payment ? ucfirst($payment['payment_status']) : 'No payment') . "</td>";
        echo "<td style='color: {$buttonColor}; font-weight: bold;'>{$shouldShowButton}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check current month payment info
    echo "<h3>5. Current Month Payment Info</h3>";
    
    // Get current active month (simplified logic)
    $currentActiveMonth = null;
    foreach ($monthlyBids as $bid) {
        $payment = $myPaymentsByMonth[$bid['month_number']] ?? null;
        if (!$payment || $payment['payment_status'] === 'pending') {
            $currentActiveMonth = [
                'month_number' => $bid['month_number'],
                'bidding_status' => 'completed' // Assuming completed since bid exists
            ];
            break;
        }
    }
    
    if ($currentActiveMonth) {
        $currentMonthBid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $currentActiveMonth['month_number']);
        $currentMonthBid = reset($currentMonthBid);
        
        $currentMonthPayment = $myPaymentsByMonth[$currentActiveMonth['month_number']] ?? null;
        
        echo "<div>Current active month: " . $currentActiveMonth['month_number'] . "</div>";
        echo "<div>Bid exists: " . ($currentMonthBid ? 'Yes' : 'No') . "</div>";
        echo "<div>Payment exists: " . ($currentMonthPayment ? 'Yes' : 'No') . "</div>";
        
        if ($currentMonthPayment) {
            echo "<div>Payment status: " . $currentMonthPayment['payment_status'] . "</div>";
        }
        
        // Check the exact condition for showing the "Pay Now" button
        $showPayNowButton = ($currentMonthPayment && $currentMonthPayment['payment_status'] === 'pending' && $currentMonthBid);
        echo "<div style='color: " . ($showPayNowButton ? 'green' : 'red') . "; font-weight: bold;'>";
        echo "Should show 'Pay Now' button: " . ($showPayNowButton ? 'YES' : 'NO');
        echo "</div>";
        
        if (!$showPayNowButton) {
            echo "<div style='background-color: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>Why 'Pay Now' button is not showing:</strong><br>";
            if (!$currentMonthBid) {
                echo "• No bid exists for current month<br>";
            }
            if (!$currentMonthPayment) {
                echo "• No payment record exists (button shows in table instead)<br>";
            }
            if ($currentMonthPayment && $currentMonthPayment['payment_status'] !== 'pending') {
                echo "• Payment is not in 'pending' status<br>";
            }
            echo "</div>";
        }
    } else {
        echo "<div style='color: orange;'>⚠️ No current active month found</div>";
    }
    
    // Show direct links to test
    echo "<h3>6. Direct Test Links</h3>";
    
    echo "<div style='background-color: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<p><strong>Test these direct links:</strong></p>";
    
    foreach ($monthlyBids as $bid) {
        $payment = $myPaymentsByMonth[$bid['month_number']] ?? null;
        if (!$payment) {
            echo "<p><a href='member_payment.php?month={$bid['month_number']}' target='_blank' style='color: green; font-weight: bold;'>";
            echo "💳 Pay for Month {$bid['month_number']} (₹" . number_format($bid['gain_per_member'], 2) . ")";
            echo "</a></p>";
        }
    }
    echo "</div>";
    
    // Show the exact HTML that should appear
    echo "<h3>7. Expected Button HTML</h3>";
    
    echo "<div style='background-color: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<p><strong>In the dashboard, you should see HTML like this:</strong></p>";
    echo "<pre style='background-color: #e9ecef; padding: 10px; border-radius: 3px; overflow-x: auto;'>";
    echo htmlspecialchars('<a href="member_payment.php?month=1" class="btn btn-success btn-sm">
    <i class="fas fa-qrcode"></i> Pay
</a>');
    echo "</pre>";
    echo "</div>";
    
    // Final recommendations
    echo "<h3>8. Recommendations</h3>";
    
    echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>🎯 To see payment buttons:</h4>";
    echo "<ol>";
    
    if (empty($monthlyBids)) {
        echo "<li><strong>Add Monthly Bids:</strong> Admin must add monthly bids first</li>";
    }
    
    $unpaidMonths = [];
    foreach ($monthlyBids as $bid) {
        $payment = $myPaymentsByMonth[$bid['month_number']] ?? null;
        if (!$payment) {
            $unpaidMonths[] = $bid['month_number'];
        }
    }
    
    if (!empty($unpaidMonths)) {
        echo "<li><strong>Check Payment History Table:</strong> Look for months " . implode(', ', $unpaidMonths) . " in the payment history table</li>";
        echo "<li><strong>Look for 'Action' Column:</strong> Payment buttons appear in the rightmost column</li>";
    }
    
    echo "<li><strong>Scroll Down:</strong> Payment history table is below the charts</li>";
    echo "<li><strong>Use Direct Links:</strong> Use the direct payment links above to test</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}
table {
    width: 100%;
    margin: 10px 0;
}
th, td {
    padding: 8px;
    text-align: left;
    border: 1px solid #ddd;
}
th {
    background-color: #f2f2f2;
}
pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>
