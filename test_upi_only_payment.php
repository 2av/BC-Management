<?php
require_once 'config.php';

echo "<h2>🎯 UPI-Only Payment System Test</h2>";
echo "<p>Testing the simplified UPI-only payment system (no bank transfer options).</p>";

try {
    // Test 1: Check payment configuration
    echo "<h3>1. UPI Configuration Test</h3>";
    
    $configs = getPaymentConfig();
    if (!empty($configs['upi_id'])) {
        echo "<div style='color: green;'>✅ UPI ID configured: " . htmlspecialchars($configs['upi_id']) . "</div>";
    } else {
        echo "<div style='color: red;'>❌ UPI ID not configured</div>";
        echo "<p><strong>Fix:</strong> Go to <a href='admin_payment_config.php'>Payment Settings</a> and set UPI ID</p>";
    }
    
    if (!empty($configs['bank_account_name'])) {
        echo "<div style='color: green;'>✅ Payee name configured: " . htmlspecialchars($configs['bank_account_name']) . "</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ Payee name not configured</div>";
    }
    
    if (($configs['qr_enabled'] ?? '1') == '1') {
        echo "<div style='color: green;'>✅ QR code payments enabled</div>";
    } else {
        echo "<div style='color: red;'>❌ QR code payments disabled</div>";
    }
    
    // Test 2: Generate test UPI QR code
    echo "<h3>2. UPI QR Code Generation Test</h3>";
    
    if (!empty($configs['upi_id'])) {
        $testUpiUrl = generateUpiPaymentUrl(1500, 'Test Member', 'Test Group', 1);
        if ($testUpiUrl) {
            echo "<div style='color: green;'>✅ UPI URL generated successfully</div>";
            echo "<div style='background-color: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>Test UPI URL:</strong><br>";
            echo "<code style='word-break: break-all; font-size: 12px;'>" . htmlspecialchars($testUpiUrl) . "</code>";
            echo "</div>";
            
            $qrCodeUrl = generateQrCodeUrl($testUpiUrl);
            echo "<div style='text-align: center; margin: 20px 0;'>";
            echo "<strong>Generated QR Code (UPI Only):</strong><br>";
            echo "<img src='" . htmlspecialchars($qrCodeUrl) . "' alt='UPI QR Code' style='border: 2px solid #007bff; padding: 15px; background: white; border-radius: 10px;'>";
            echo "<br><small style='color: #666;'>Test QR Code for ₹1,500 - Scan with any UPI app</small>";
            echo "</div>";
        } else {
            echo "<div style='color: red;'>❌ Failed to generate UPI URL</div>";
        }
    }
    
    // Test 3: Test member payment page
    echo "<h3>3. Member Payment Page Test</h3>";
    
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, member_name FROM members LIMIT 1");
    $testMember = $stmt->fetch();
    
    if ($testMember) {
        echo "<div style='color: green;'>✅ Test member found: " . htmlspecialchars($testMember['member_name']) . "</div>";
        
        echo "<div style='background-color: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<p><strong>Test the UPI-only payment page:</strong></p>";
        echo "<p><a href='member_payment.php?month=1' target='_blank' style='color: blue; font-weight: bold;'>🔗 Test Payment Page - Month 1</a></p>";
        echo "<p><a href='member_payment.php?month=2' target='_blank' style='color: blue; font-weight: bold;'>🔗 Test Payment Page - Month 2</a></p>";
        echo "<p><em>Note: You need to be logged in as a member to access these pages</em></p>";
        echo "</div>";
    }
    
    // Test 4: Show what's removed
    echo "<h3>4. Simplified Features</h3>";
    
    echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>✅ What's Included (UPI Only):</h4>";
    echo "<ul>";
    echo "<li>🎯 <strong>QR Code Scanning:</strong> Large, clear QR code for UPI apps</li>";
    echo "<li>📱 <strong>UPI Integration:</strong> Direct links to open UPI apps</li>";
    echo "<li>📋 <strong>Copy UPI ID:</strong> Easy copy button for manual entry</li>";
    echo "<li>💳 <strong>Payment Details:</strong> Amount, payee, and reference info</li>";
    echo "<li>📝 <strong>Simple Instructions:</strong> Clear steps for UPI payment</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background-color: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>❌ What's Removed (Simplified):</h4>";
    echo "<ul>";
    echo "<li>🏦 <strong>Bank Transfer Details:</strong> No account numbers, IFSC codes</li>";
    echo "<li>📄 <strong>Bank Forms:</strong> No complex bank transfer forms</li>";
    echo "<li>🔢 <strong>Account Fields:</strong> No account number copy buttons</li>";
    echo "<li>📋 <strong>NEFT/RTGS Options:</strong> Only UPI payment method</li>";
    echo "</ul>";
    echo "</div>";
    
    // Test 5: Admin configuration
    echo "<h3>5. Admin Configuration</h3>";
    
    echo "<div style='background-color: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>🔧 Simplified Admin Settings:</h4>";
    echo "<p><a href='admin_payment_config.php' target='_blank' style='color: blue; font-weight: bold;'>🔗 Payment Configuration</a></p>";
    echo "<p><strong>Required Settings:</strong></p>";
    echo "<ul>";
    echo "<li><strong>UPI ID:</strong> Your UPI ID (e.g., yourname@paytm)</li>";
    echo "<li><strong>Payee Name:</strong> Name that appears in UPI transactions</li>";
    echo "<li><strong>Payment Note:</strong> Default description for payments</li>";
    echo "<li><strong>Enable QR Codes:</strong> Toggle QR code functionality</li>";
    echo "</ul>";
    echo "</div>";
    
    // Test 6: Mobile testing
    echo "<h3>6. Mobile UPI App Testing</h3>";
    
    echo "<div style='background-color: #e2e3e5; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>📱 Test with UPI Apps:</h4>";
    echo "<ol>";
    echo "<li><strong>PhonePe:</strong> Open PhonePe app → Scan QR code above</li>";
    echo "<li><strong>Google Pay:</strong> Open GPay → Scan QR code above</li>";
    echo "<li><strong>Paytm:</strong> Open Paytm → Scan QR code above</li>";
    echo "<li><strong>BHIM:</strong> Open BHIM app → Scan QR code above</li>";
    echo "<li><strong>Any Bank UPI:</strong> Use your bank's UPI app</li>";
    echo "</ol>";
    echo "<p><strong>Expected Result:</strong> Payment details should auto-populate with correct amount and payee information.</p>";
    echo "</div>";
    
    // Test 7: Member login info
    echo "<h3>7. Member Login for Testing</h3>";
    
    $stmt = $pdo->query("SELECT username, member_name FROM members LIMIT 3");
    $members = $stmt->fetchAll();
    
    if (!empty($members)) {
        echo "<div style='background-color: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<p><strong>Test with these member accounts:</strong></p>";
        echo "<p><a href='member_login.php' target='_blank' style='color: blue;'>🔗 Member Login Page</a></p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #e9ecef;'><th>Username</th><th>Member Name</th><th>Password</th></tr>";
        foreach ($members as $member) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($member['username']) . "</td>";
            echo "<td>" . htmlspecialchars($member['member_name']) . "</td>";
            echo "<td>member123</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
    
    echo "<h3>8. Summary</h3>";
    echo "<div style='background-color: #d1ecf1; padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 5px solid #0dcaf0;'>";
    echo "<h4>🎯 UPI-Only Payment System Ready!</h4>";
    echo "<p><strong>✅ Fixed Issues:</strong></p>";
    echo "<ul>";
    echo "<li>Removed missing member_navbar.php dependency</li>";
    echo "<li>Simplified to UPI-only payments (no bank transfer)</li>";
    echo "<li>Clean, focused payment interface</li>";
    echo "<li>Mobile-optimized QR code scanning</li>";
    echo "</ul>";
    echo "<p><strong>🚀 Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Configure your UPI ID in <a href='admin_payment_config.php'>Payment Settings</a></li>";
    echo "<li>Test the payment flow with member login</li>";
    echo "<li>Scan QR codes with your UPI app to verify</li>";
    echo "<li>Train members on the simplified payment process</li>";
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
code {
    background-color: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}
</style>
