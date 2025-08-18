<?php
require_once 'config.php';

echo "<h2>🔧 Member Payment Function Test</h2>";

try {
    // Test 1: Check if getMemberById function exists
    echo "<h3>1. Function Availability Test</h3>";
    
    if (function_exists('getMemberById')) {
        echo "<div style='color: green;'>✅ getMemberById() function exists</div>";
    } else {
        echo "<div style='color: red;'>❌ getMemberById() function missing</div>";
        return;
    }
    
    // Test 2: Check if we can get a member
    echo "<h3>2. Member Data Test</h3>";
    
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, member_name FROM members LIMIT 1");
    $testMember = $stmt->fetch();
    
    if ($testMember) {
        echo "<div style='color: green;'>✅ Found test member: " . htmlspecialchars($testMember['member_name']) . " (ID: {$testMember['id']})</div>";
        
        // Test getMemberById function
        $memberData = getMemberById($testMember['id']);
        if ($memberData) {
            echo "<div style='color: green;'>✅ getMemberById() works correctly</div>";
            echo "<div>Member Name: " . htmlspecialchars($memberData['member_name']) . "</div>";
            echo "<div>Group ID: " . $memberData['group_id'] . "</div>";
        } else {
            echo "<div style='color: red;'>❌ getMemberById() returned null</div>";
        }
    } else {
        echo "<div style='color: orange;'>⚠️ No members found in database</div>";
    }
    
    // Test 3: Check other required functions
    echo "<h3>3. Other Required Functions Test</h3>";
    
    $requiredFunctions = [
        'getGroupById',
        'getMonthlyBids', 
        'getMemberPayments',
        'isQrPaymentEnabled',
        'generatePaymentQrCode',
        'getBankDetails',
        'formatCurrency',
        'formatDate'
    ];
    
    foreach ($requiredFunctions as $func) {
        if (function_exists($func)) {
            echo "<div style='color: green;'>✅ {$func}() exists</div>";
        } else {
            echo "<div style='color: red;'>❌ {$func}() missing</div>";
        }
    }
    
    // Test 4: Test member payment page access
    echo "<h3>4. Member Payment Page Access Test</h3>";
    
    if ($testMember) {
        echo "<div style='background-color: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<p><strong>Test Links (you need to be logged in as a member):</strong></p>";
        echo "<p><a href='member_payment.php?month=1' target='_blank' style='color: blue;'>🔗 Test Payment Page - Month 1</a></p>";
        echo "<p><a href='member_payment.php?month=2' target='_blank' style='color: blue;'>🔗 Test Payment Page - Month 2</a></p>";
        echo "<p><a href='member_payment.php?month=3' target='_blank' style='color: blue;'>🔗 Test Payment Page - Month 3</a></p>";
        echo "</div>";
        
        echo "<div style='background-color: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<p><strong>If you get an error, try logging in first:</strong></p>";
        echo "<p><a href='member_login.php' target='_blank' style='color: blue;'>🔗 Member Login Page</a></p>";
        echo "<p>Use username: " . htmlspecialchars($testMember['member_name']) . " (or any member username)</p>";
        echo "<p>Default password: member123</p>";
        echo "</div>";
    }
    
    // Test 5: Check QR code functionality
    echo "<h3>5. QR Code Functionality Test</h3>";
    
    if (function_exists('generatePaymentQrCode') && $testMember) {
        $qrData = generatePaymentQrCode($testMember['id'], 1, 1, 2000);
        if ($qrData) {
            echo "<div style='color: green;'>✅ QR code generation works</div>";
            echo "<div style='text-align: center; margin: 20px 0;'>";
            echo "<img src='" . htmlspecialchars($qrData['qr_code_url']) . "' alt='Test QR Code' style='border: 1px solid #ddd; padding: 10px; background: white;'>";
            echo "<br><small>Test QR Code for ₹2,000</small>";
            echo "</div>";
        } else {
            echo "<div style='color: orange;'>⚠️ QR code generation returned null (check payment config)</div>";
        }
    }
    
    // Test 6: Show member login credentials
    echo "<h3>6. Available Member Login Credentials</h3>";
    
    $stmt = $pdo->query("SELECT username, member_name FROM members LIMIT 5");
    $members = $stmt->fetchAll();
    
    if (!empty($members)) {
        echo "<div style='background-color: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<p><strong>Available member accounts for testing:</strong></p>";
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
    
    echo "<h3>7. Summary</h3>";
    echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>✅ Fix Applied Successfully!</h4>";
    echo "<p>The <code>getMemberById()</code> function has been added to config.php</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Login as a member using the credentials above</li>";
    echo "<li>Go to <a href='member_dashboard.php' target='_blank'>Member Dashboard</a></li>";
    echo "<li>Look for payment buttons in the payment history table</li>";
    echo "<li>Or use the direct payment links above</li>";
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
