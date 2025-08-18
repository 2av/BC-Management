<?php
require_once 'config.php';

echo "<h2>🖼️ QR Code Image & UPI Setup Test</h2>";
echo "<p>Testing the local QRCode.jpeg file and default UPI ID configuration.</p>";

try {
    // Test 1: Check if QRCode.jpeg file exists
    echo "<h3>1. QR Code Image File Test</h3>";
    
    $qrImagePath = 'QRCode.jpeg';
    if (file_exists($qrImagePath)) {
        echo "<div style='color: green;'>✅ QRCode.jpeg file found</div>";
        
        // Get file info
        $fileSize = filesize($qrImagePath);
        $fileSizeKB = round($fileSize / 1024, 2);
        echo "<div>File size: {$fileSizeKB} KB</div>";
        
        // Display the image
        echo "<div style='text-align: center; margin: 20px 0; padding: 20px; background-color: #f8f9fa; border-radius: 10px;'>";
        echo "<h4>Your QR Code Image:</h4>";
        echo "<img src='{$qrImagePath}' alt='QR Code' style='max-width: 250px; border: 2px solid #007bff; padding: 10px; background: white; border-radius: 10px;'>";
        echo "<br><small style='color: #666; margin-top: 10px; display: block;'>This QR code will be shown to all members for payments</small>";
        echo "</div>";
        
    } else {
        echo "<div style='color: red;'>❌ QRCode.jpeg file not found</div>";
        echo "<div style='background-color: #f8d7da; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4>🔧 How to add your QR code:</h4>";
        echo "<ol>";
        echo "<li>Create a QR code for UPI ID: <strong>9768985225kotak@ybl</strong></li>";
        echo "<li>Save it as <strong>QRCode.jpeg</strong> in the main directory</li>";
        echo "<li>Or use any QR code generator app/website</li>";
        echo "<li>Make sure the file is named exactly: <strong>QRCode.jpeg</strong></li>";
        echo "</ol>";
        echo "</div>";
    }
    
    // Test 2: Check UPI configuration
    echo "<h3>2. UPI Configuration Test</h3>";
    
    $configs = getPaymentConfig();
    $currentUpiId = $configs['upi_id'] ?? '9768985225kotak@ybl';
    $payeeName = $configs['bank_account_name'] ?? 'BC Group Admin';
    
    echo "<div style='background-color: #e7f3ff; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>📱 Current UPI Settings:</h4>";
    echo "<p><strong>UPI ID:</strong> " . htmlspecialchars($currentUpiId) . "</p>";
    echo "<p><strong>Payee Name:</strong> " . htmlspecialchars($payeeName) . "</p>";
    echo "<p><strong>Status:</strong> " . ($currentUpiId === '9768985225kotak@ybl' ? 'Using Default UPI ID' : 'Using Custom UPI ID') . "</p>";
    echo "</div>";
    
    if ($currentUpiId === '9768985225kotak@ybl') {
        echo "<div style='color: green;'>✅ Default UPI ID is active: 9768985225kotak@ybl</div>";
    } else {
        echo "<div style='color: blue;'>ℹ️ Custom UPI ID configured: " . htmlspecialchars($currentUpiId) . "</div>";
    }
    
    // Test 3: Test member payment page
    echo "<h3>3. Member Payment Page Test</h3>";
    
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, member_name FROM members LIMIT 1");
    $testMember = $stmt->fetch();
    
    if ($testMember) {
        echo "<div style='background-color: #d4edda; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4>🧪 Test Payment Pages:</h4>";
        echo "<p><a href='member_payment.php?month=1' target='_blank' style='color: blue; font-weight: bold;'>🔗 Test Payment Page - Month 1</a></p>";
        echo "<p><a href='member_payment.php?month=2' target='_blank' style='color: blue; font-weight: bold;'>🔗 Test Payment Page - Month 2</a></p>";
        echo "<p><em>Note: Login as member first if you get redirected</em></p>";
        echo "</div>";
        
        echo "<div style='background-color: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4>👤 Member Login Info:</h4>";
        echo "<p><a href='member_login.php' target='_blank' style='color: blue;'>🔗 Member Login Page</a></p>";
        echo "<p><strong>Test Member:</strong> " . htmlspecialchars($testMember['member_name']) . "</p>";
        echo "<p><strong>Default Password:</strong> member123</p>";
        echo "</div>";
    }
    
    // Test 4: Admin configuration
    echo "<h3>4. Admin Configuration</h3>";
    
    echo "<div style='background-color: #e2e3e5; padding: 15px; margin: 10px 0; border-radius: 5px;'>";
    echo "<h4>⚙️ Admin Settings:</h4>";
    echo "<p><a href='admin_payment_config.php' target='_blank' style='color: blue; font-weight: bold;'>🔗 Payment Configuration</a></p>";
    echo "<p><strong>Current Setup:</strong></p>";
    echo "<ul>";
    echo "<li>Default UPI ID: <code>9768985225kotak@ybl</code></li>";
    echo "<li>QR Code Image: <code>QRCode.jpeg</code></li>";
    echo "<li>Admin can change UPI ID if needed</li>";
    echo "<li>QR code image remains the same</li>";
    echo "</ul>";
    echo "</div>";
    
    // Test 5: What members will see
    echo "<h3>5. Member Experience Preview</h3>";
    
    echo "<div style='background-color: #f8f9fa; padding: 20px; margin: 15px 0; border-radius: 10px; border: 2px dashed #6c757d;'>";
    echo "<h4>📱 What Members Will See:</h4>";
    echo "<div style='text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin: 10px 0;'>";
    echo "<h5><i class='fas fa-qrcode'></i> Scan to Pay</h5>";
    echo "<p>Scan this QR code with any UPI app</p>";
    
    if (file_exists($qrImagePath)) {
        echo "<div style='background: white; padding: 15px; border-radius: 10px; display: inline-block; margin: 15px 0;'>";
        echo "<img src='{$qrImagePath}' alt='QR Code' style='max-width: 150px; height: auto;'>";
        echo "</div>";
    } else {
        echo "<div style='background: white; color: #333; padding: 30px; border-radius: 10px; display: inline-block; margin: 15px 0;'>";
        echo "<p style='margin: 0;'>QR Code Image<br><small>(QRCode.jpeg not found)</small></p>";
        echo "</div>";
    }
    
    echo "<div style='margin-top: 15px;'>";
    echo "<p><strong>UPI ID:</strong> " . htmlspecialchars($currentUpiId) . "</p>";
    echo "<p><strong>Amount:</strong> ₹2,000.00 (example)</p>";
    echo "<p><strong>Payee:</strong> " . htmlspecialchars($payeeName) . "</p>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Test 6: File upload instructions
    if (!file_exists($qrImagePath)) {
        echo "<h3>6. QR Code Setup Instructions</h3>";
        
        echo "<div style='background-color: #fff3cd; padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 5px solid #ffc107;'>";
        echo "<h4>📋 Step-by-Step QR Code Setup:</h4>";
        echo "<ol style='line-height: 1.8;'>";
        echo "<li><strong>Generate QR Code:</strong>";
        echo "<ul>";
        echo "<li>Use any UPI QR code generator (PhonePe, Paytm, GPay merchant QR)</li>";
        echo "<li>Or use online QR generators for UPI ID: <code>9768985225kotak@ybl</code></li>";
        echo "<li>Or scan existing QR code and save the image</li>";
        echo "</ul></li>";
        echo "<li><strong>Save Image:</strong>";
        echo "<ul>";
        echo "<li>Save the QR code image as <strong>QRCode.jpeg</strong></li>";
        echo "<li>Place it in the main BC-Management directory</li>";
        echo "<li>Same folder where member_payment.php is located</li>";
        echo "</ul></li>";
        echo "<li><strong>Test:</strong>";
        echo "<ul>";
        echo "<li>Refresh this page to see if image loads</li>";
        echo "<li>Test member payment page</li>";
        echo "<li>Scan QR code with UPI app to verify</li>";
        echo "</ul></li>";
        echo "</ol>";
        echo "</div>";
    }
    
    echo "<h3>7. Summary</h3>";
    echo "<div style='background-color: #d1ecf1; padding: 20px; margin: 15px 0; border-radius: 10px; border-left: 5px solid #0dcaf0;'>";
    echo "<h4>🎯 QR Code & UPI Setup Status:</h4>";
    
    if (file_exists($qrImagePath)) {
        echo "<p style='color: green;'>✅ <strong>QR Code Image:</strong> Ready (QRCode.jpeg found)</p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>QR Code Image:</strong> Missing (add QRCode.jpeg file)</p>";
    }
    
    echo "<p style='color: green;'>✅ <strong>Default UPI ID:</strong> 9768985225kotak@ybl</p>";
    echo "<p style='color: green;'>✅ <strong>Admin Override:</strong> Available in Payment Settings</p>";
    echo "<p style='color: green;'>✅ <strong>Member Interface:</strong> Simplified UPI-only payments</p>";
    
    echo "<p><strong>🚀 Next Steps:</strong></p>";
    echo "<ol>";
    if (!file_exists($qrImagePath)) {
        echo "<li>Add QRCode.jpeg file to the main directory</li>";
    }
    echo "<li>Test member payment flow</li>";
    echo "<li>Configure custom UPI ID if needed (optional)</li>";
    echo "<li>Train members on QR code scanning</li>";
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
code {
    background-color: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    color: #e83e8c;
}
.fas {
    margin-right: 5px;
}
</style>
