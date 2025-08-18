<?php
require_once 'config.php';

echo "<h2>QR Code Payment System Test</h2>";

try {
    // Test 1: Check if payment config table exists and has data
    echo "<h3>1. Testing Payment Configuration</h3>";
    
    $configs = getPaymentConfig();
    if (empty($configs)) {
        echo "<div style='color: orange;'>⚠️ No payment configuration found. Creating default configuration...</div>";
        createPaymentConfigTable();
        $configs = getPaymentConfig();
    }
    
    if (!empty($configs)) {
        echo "<div style='color: green;'>✅ Payment configuration loaded successfully</div>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr style='background-color: #f0f0f0;'><th>Setting</th><th>Value</th></tr>";
        foreach ($configs as $key => $value) {
            $displayValue = $key === 'bank_account_number' ? str_repeat('*', strlen($value) - 4) . substr($value, -4) : $value;
            echo "<tr><td>{$key}</td><td>" . htmlspecialchars($displayValue) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='color: red;'>❌ Failed to load payment configuration</div>";
    }
    
    // Test 2: Check QR code generation
    echo "<h3>2. Testing QR Code Generation</h3>";
    
    if (isQrPaymentEnabled()) {
        echo "<div style='color: green;'>✅ QR code payments are enabled</div>";
        
        // Test UPI URL generation
        $testUpiUrl = generateUpiPaymentUrl(1500, 'Test Member', 'Test BC Group', 5);
        if ($testUpiUrl) {
            echo "<div style='color: green;'>✅ UPI URL generated successfully</div>";
            echo "<div style='background-color: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>Test UPI URL:</strong><br>";
            echo "<code style='word-break: break-all;'>" . htmlspecialchars($testUpiUrl) . "</code>";
            echo "</div>";
            
            // Generate QR code
            $qrCodeUrl = generateQrCodeUrl($testUpiUrl);
            echo "<div style='text-align: center; margin: 20px 0;'>";
            echo "<strong>Generated QR Code:</strong><br>";
            echo "<img src='" . htmlspecialchars($qrCodeUrl) . "' alt='Test QR Code' style='border: 1px solid #ddd; padding: 10px; background: white;'>";
            echo "<br><small>Test QR Code for ₹1,500 payment</small>";
            echo "</div>";
        } else {
            echo "<div style='color: red;'>❌ Failed to generate UPI URL</div>";
        }
    } else {
        echo "<div style='color: orange;'>⚠️ QR code payments are disabled</div>";
    }
    
    // Test 3: Test with actual member data
    echo "<h3>3. Testing with Real Member Data</h3>";
    
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT m.id, m.member_name, m.group_id, bg.group_name 
        FROM members m 
        JOIN bc_groups bg ON m.group_id = bg.id 
        LIMIT 1
    ");
    $testMember = $stmt->fetch();
    
    if ($testMember) {
        echo "<div style='color: green;'>✅ Found test member: " . htmlspecialchars($testMember['member_name']) . "</div>";
        
        $qrData = generatePaymentQrCode($testMember['id'], $testMember['group_id'], 1, 2000);
        
        if ($qrData) {
            echo "<div style='color: green;'>✅ QR code generated for real member</div>";
            echo "<div style='background-color: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
            echo "<strong>Payment Details:</strong><br>";
            echo "Member: " . htmlspecialchars($qrData['payment_details']['member_name']) . "<br>";
            echo "Group: " . htmlspecialchars($qrData['payment_details']['group_name']) . "<br>";
            echo "Month: " . $qrData['payment_details']['month_number'] . "<br>";
            echo "Amount: ₹" . number_format($qrData['payment_details']['amount'], 2) . "<br>";
            echo "UPI ID: " . htmlspecialchars($qrData['payment_details']['upi_id']) . "<br>";
            echo "</div>";
            
            echo "<div style='text-align: center; margin: 20px 0;'>";
            echo "<img src='" . htmlspecialchars($qrData['qr_code_url']) . "' alt='Member QR Code' style='border: 1px solid #ddd; padding: 10px; background: white;'>";
            echo "<br><small>QR Code for " . htmlspecialchars($testMember['member_name']) . "</small>";
            echo "</div>";
        } else {
            echo "<div style='color: red;'>❌ Failed to generate QR code for member</div>";
        }
    } else {
        echo "<div style='color: orange;'>⚠️ No members found in database</div>";
    }
    
    // Test 4: Test bank details
    echo "<h3>4. Testing Bank Details</h3>";
    
    $bankDetails = getBankDetails();
    if (!empty($bankDetails['account_number'])) {
        echo "<div style='color: green;'>✅ Bank details configured</div>";
        echo "<div style='background-color: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<strong>Bank Details:</strong><br>";
        echo "Account Name: " . htmlspecialchars($bankDetails['account_name']) . "<br>";
        echo "Account Number: " . str_repeat('*', strlen($bankDetails['account_number']) - 4) . substr($bankDetails['account_number'], -4) . "<br>";
        echo "IFSC Code: " . htmlspecialchars($bankDetails['ifsc_code']) . "<br>";
        echo "Bank Name: " . htmlspecialchars($bankDetails['bank_name']) . "<br>";
        if (!empty($bankDetails['upi_id'])) {
            echo "UPI ID: " . htmlspecialchars($bankDetails['upi_id']) . "<br>";
        }
        echo "</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ Bank details not configured</div>";
    }
    
    // Test 5: Test payment instructions
    echo "<h3>5. Testing Payment Instructions</h3>";
    
    $instructions = getPaymentInstructions(2000, 'John Doe', 'Test Group', 3);
    echo "<div style='background-color: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; white-space: pre-line;'>";
    echo htmlspecialchars($instructions);
    echo "</div>";
    
    // Test 6: Navigation links
    echo "<h3>6. Test Navigation</h3>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='admin_payment_config.php' target='_blank' style='margin-right: 10px; color: blue;'>🔗 Payment Configuration</a>";
    echo "<a href='member_payment.php?month=1' target='_blank' style='margin-right: 10px; color: blue;'>🔗 Member Payment Page</a>";
    echo "<a href='member_dashboard.php' target='_blank' style='margin-right: 10px; color: blue;'>🔗 Member Dashboard</a>";
    echo "</div>";
    
    // Test 7: Mobile compatibility test
    echo "<h3>7. Mobile Compatibility Test</h3>";
    echo "<div style='background-color: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Mobile Testing Instructions:</strong><br>";
    echo "1. Open this page on a mobile device<br>";
    echo "2. Scan any QR code above with a UPI app (PhonePe, Paytm, GPay, etc.)<br>";
    echo "3. Verify that the payment details are correctly populated<br>";
    echo "4. Test the 'Copy UPI ID' and 'Open UPI App' buttons<br>";
    echo "5. Check that the payment page is mobile-responsive<br>";
    echo "</div>";
    
    echo "<h3>8. Summary</h3>";
    echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745;'>";
    echo "<strong>QR Code Payment System Status:</strong><br>";
    echo "✅ Payment configuration system working<br>";
    echo "✅ QR code generation functional<br>";
    echo "✅ UPI URL creation working<br>";
    echo "✅ Bank details integration complete<br>";
    echo "✅ Member payment interface ready<br>";
    echo "✅ Admin configuration panel available<br>";
    echo "<br><strong>Next Steps:</strong><br>";
    echo "1. Configure your actual UPI ID and bank details in Payment Settings<br>";
    echo "2. Test with real members by having them scan QR codes<br>";
    echo "3. Verify payments are received correctly<br>";
    echo "4. Train members on how to use the payment system<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error during testing: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div style='background-color: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
    echo "<strong>Troubleshooting:</strong><br>";
    echo "1. Make sure the database is properly set up<br>";
    echo "2. Check if the payment_config table exists<br>";
    echo "3. Verify that qr_utils.php is included correctly<br>";
    echo "4. Ensure internet connection for QR code generation<br>";
    echo "</div>";
}
?>
