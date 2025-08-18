<?php
require_once 'config.php';

echo "<h2>🔍 QR Code Payment Button Location Guide</h2>";
echo "<p>This guide will help you find the QR code payment buttons in the member dashboard.</p>";

try {
    // Check if user is logged in as member
    if (!isMemberLoggedIn()) {
        echo "<div style='background-color: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #ffc107;'>";
        echo "<h3>⚠️ You need to be logged in as a member first!</h3>";
        echo "<p><strong>Steps to test:</strong></p>";
        echo "<ol>";
        echo "<li>Go to <a href='member_login.php' target='_blank'>Member Login Page</a></li>";
        echo "<li>Login with any member credentials (e.g., username: akhilesh, password: member123)</li>";
        echo "<li>Then go to <a href='member_dashboard.php' target='_blank'>Member Dashboard</a></li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='color: green;'>✅ You are logged in as a member</div>";
    }
    
    // Check payment configuration
    echo "<h3>1. Payment System Status</h3>";
    
    $configs = getPaymentConfig();
    if (empty($configs)) {
        echo "<div style='color: red;'>❌ Payment configuration not found</div>";
        echo "<p><strong>Fix:</strong> Run the database migration script first:</p>";
        echo "<code>SOURCE add_payment_config_table.sql;</code>";
    } else {
        echo "<div style='color: green;'>✅ Payment configuration exists</div>";
        
        if (($configs['qr_enabled'] ?? '1') == '1') {
            echo "<div style='color: green;'>✅ QR code payments are enabled</div>";
        } else {
            echo "<div style='color: orange;'>⚠️ QR code payments are disabled</div>";
            echo "<p><strong>Fix:</strong> Go to <a href='admin_payment_config.php' target='_blank'>Admin > Payment Settings</a> and enable QR codes</p>";
        }
        
        if (!empty($configs['upi_id'])) {
            echo "<div style='color: green;'>✅ UPI ID configured: " . htmlspecialchars($configs['upi_id']) . "</div>";
        } else {
            echo "<div style='color: orange;'>⚠️ UPI ID not configured</div>";
            echo "<p><strong>Fix:</strong> Go to <a href='admin_payment_config.php' target='_blank'>Admin > Payment Settings</a> and set UPI ID</p>";
        }
    }
    
    // Check for groups and bids
    echo "<h3>2. Group and Bidding Status</h3>";
    
    $pdo = getDB();
    
    // Check if there are any groups
    $stmt = $pdo->query("SELECT COUNT(*) FROM bc_groups");
    $groupCount = $stmt->fetchColumn();
    
    if ($groupCount == 0) {
        echo "<div style='color: red;'>❌ No groups found</div>";
        echo "<p><strong>Fix:</strong> Create a group first using <a href='admin_create_group_simple.php' target='_blank'>Admin > Create Group</a></p>";
    } else {
        echo "<div style='color: green;'>✅ Found {$groupCount} group(s)</div>";
        
        // Check if there are any monthly bids
        $stmt = $pdo->query("SELECT COUNT(*) FROM monthly_bids");
        $bidCount = $stmt->fetchColumn();
        
        if ($bidCount == 0) {
            echo "<div style='color: orange;'>⚠️ No monthly bids found</div>";
            echo "<p><strong>Note:</strong> Payment buttons only appear when there are monthly bids (winners decided)</p>";
            echo "<p><strong>Fix:</strong> Add monthly bids using <a href='add_bid.php' target='_blank'>Admin > Add Monthly Bid</a></p>";
        } else {
            echo "<div style='color: green;'>✅ Found {$bidCount} monthly bid(s)</div>";
        }
    }
    
    echo "<h3>3. Where to Find Payment Buttons</h3>";
    
    echo "<div style='background-color: #e7f3ff; padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 5px solid #007bff;'>";
    echo "<h4>📍 Location 1: Current Month Payment Section</h4>";
    echo "<p><strong>Look for:</strong> A section titled 'Current Month Payment Status'</p>";
    echo "<p><strong>Button appears when:</strong> There's a pending payment for the current month</p>";
    echo "<p><strong>Button text:</strong> <span style='background-color: #28a745; color: white; padding: 5px 10px; border-radius: 3px;'><i class='fas fa-qrcode'></i> Pay Now</span></p>";
    echo "<p><strong>What it shows:</strong> 'Awaiting Payment' badge with a green 'Pay Now' button below it</p>";
    echo "</div>";
    
    echo "<div style='background-color: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 5px solid #6c757d;'>";
    echo "<h4>📍 Location 2: Payment History Table</h4>";
    echo "<p><strong>Look for:</strong> A table titled 'My Payment History'</p>";
    echo "<p><strong>Button appears when:</strong> There's a month with a winner but you haven't paid yet</p>";
    echo "<p><strong>Button text:</strong> <span style='background-color: #28a745; color: white; padding: 5px 10px; border-radius: 3px;'><i class='fas fa-qrcode'></i> Pay</span></p>";
    echo "<p><strong>Location:</strong> In the 'Action' column of the payment history table</p>";
    echo "</div>";
    
    echo "<h3>4. Step-by-Step Instructions</h3>";
    
    echo "<div style='background-color: #d1ecf1; padding: 20px; margin: 15px 0; border-radius: 10px;'>";
    echo "<ol style='font-size: 16px; line-height: 1.6;'>";
    echo "<li><strong>Login as Member:</strong> Go to <a href='member_login.php' target='_blank'>Member Login</a></li>";
    echo "<li><strong>Open Dashboard:</strong> Go to <a href='member_dashboard.php' target='_blank'>Member Dashboard</a></li>";
    echo "<li><strong>Scroll Down:</strong> Look for 'Current Month Payment Status' section</li>";
    echo "<li><strong>Check Status:</strong> If it shows 'Awaiting Payment', you'll see a green 'Pay Now' button</li>";
    echo "<li><strong>Alternative:</strong> Scroll further down to 'My Payment History' table</li>";
    echo "<li><strong>Look for Action Column:</strong> Any unpaid months will have a green 'Pay' button</li>";
    echo "<li><strong>Click Button:</strong> This will take you to the QR code payment page</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>5. What the Payment Page Contains</h3>";
    
    echo "<div style='background-color: #d4edda; padding: 20px; margin: 15px 0; border-radius: 10px;'>";
    echo "<h4>🎯 When you click 'Pay Now' or 'Pay', you'll see:</h4>";
    echo "<ul style='font-size: 16px; line-height: 1.6;'>";
    echo "<li>📱 <strong>QR Code:</strong> Large QR code to scan with UPI apps</li>";
    echo "<li>💳 <strong>UPI Details:</strong> UPI ID and payment amount</li>";
    echo "<li>🏦 <strong>Bank Details:</strong> Account number, IFSC for bank transfer</li>";
    echo "<li>📋 <strong>Copy Buttons:</strong> To copy UPI ID, account details</li>";
    echo "<li>📱 <strong>'Open UPI App' Button:</strong> Direct link to UPI apps</li>";
    echo "<li>📝 <strong>Payment Instructions:</strong> Step-by-step guide</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>6. Troubleshooting</h3>";
    
    echo "<div style='background-color: #f8d7da; padding: 20px; margin: 15px 0; border-radius: 10px;'>";
    echo "<h4>❓ If you don't see payment buttons:</h4>";
    echo "<ol>";
    echo "<li><strong>Check Login:</strong> Make sure you're logged in as a member (not admin)</li>";
    echo "<li><strong>Check Bids:</strong> Payment buttons only appear when monthly bids exist</li>";
    echo "<li><strong>Check Payment Status:</strong> Buttons don't appear for already paid months</li>";
    echo "<li><strong>Check Configuration:</strong> QR codes must be enabled in admin settings</li>";
    echo "<li><strong>Refresh Page:</strong> Sometimes a simple refresh helps</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>7. Quick Test Links</h3>";
    
    echo "<div style='background-color: #fff3cd; padding: 20px; margin: 15px 0; border-radius: 10px;'>";
    echo "<h4>🔗 Test These Links:</h4>";
    echo "<p><a href='member_login.php' target='_blank' style='color: #007bff; font-weight: bold;'>👤 Member Login Page</a></p>";
    echo "<p><a href='member_dashboard.php' target='_blank' style='color: #007bff; font-weight: bold;'>📊 Member Dashboard</a></p>";
    echo "<p><a href='member_payment.php?month=1' target='_blank' style='color: #007bff; font-weight: bold;'>💳 Direct Payment Page (Month 1)</a></p>";
    echo "<p><a href='admin_payment_config.php' target='_blank' style='color: #007bff; font-weight: bold;'>⚙️ Admin Payment Settings</a></p>";
    echo "<p><a href='test_qr_payment.php' target='_blank' style='color: #007bff; font-weight: bold;'>🧪 Test QR Payment System</a></p>";
    echo "</div>";
    
    // Show sample member credentials
    echo "<h3>8. Sample Member Login Credentials</h3>";
    
    $stmt = $pdo->query("SELECT username, member_name FROM members LIMIT 5");
    $sampleMembers = $stmt->fetchAll();
    
    if (!empty($sampleMembers)) {
        echo "<div style='background-color: #e2e3e5; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<p><strong>Try logging in with these member credentials:</strong></p>";
        echo "<ul>";
        foreach ($sampleMembers as $member) {
            echo "<li><strong>Username:</strong> " . htmlspecialchars($member['username']) . " | <strong>Password:</strong> member123 | <strong>Name:</strong> " . htmlspecialchars($member['member_name']) . "</li>";
        }
        echo "</ul>";
        echo "<p><em>Default password for all members is: member123</em></p>";
        echo "</div>";
    }
    
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
h2, h3, h4 {
    color: #333;
}
code {
    background-color: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}
a {
    color: #007bff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>
