<?php
/**
 * QR Code Utilities for BC Management System
 * Handles QR code generation for payments
 */

/**
 * Get payment configuration from database
 */
function getPaymentConfig($key = null) {
    try {
        $pdo = getDB();
        
        if ($key) {
            $stmt = $pdo->prepare("SELECT config_value FROM payment_config WHERE config_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetchColumn();
        } else {
            $stmt = $pdo->query("SELECT config_key, config_value FROM payment_config");
            $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return $configs;
        }
    } catch (Exception $e) {
        return $key ? '' : [];
    }
}

/**
 * Update payment configuration
 */
function updatePaymentConfig($key, $value) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO payment_config (config_key, config_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Generate UPI payment URL
 */
function generateUpiPaymentUrl($amount, $memberName, $groupName, $monthNumber) {
    $configs = getPaymentConfig();
    
    if (empty($configs['upi_id'])) {
        return null;
    }
    
    $upiId = $configs['upi_id'];
    $payeeName = $configs['bank_account_name'] ?: 'BC Group Admin';
    $note = $configs['payment_note'] ?: 'BC Group Payment';
    
    // Create payment note with details
    $paymentNote = "{$note} - {$groupName} - Month {$monthNumber} - {$memberName}";
    
    // UPI URL format: upi://pay?pa=UPI_ID&pn=PAYEE_NAME&am=AMOUNT&cu=INR&tn=TRANSACTION_NOTE
    $upiUrl = "upi://pay?" . http_build_query([
        'pa' => $upiId,
        'pn' => $payeeName,
        'am' => number_format($amount, 2, '.', ''),
        'cu' => 'INR',
        'tn' => $paymentNote
    ]);
    
    return $upiUrl;
}

/**
 * Generate QR code URL using Google Charts API
 */
function generateQrCodeUrl($data, $size = 200) {
    $encodedData = urlencode($data);
    return "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encodedData}&choe=UTF-8";
}

/**
 * Generate payment QR code for a member
 */
function generatePaymentQrCode($memberId, $groupId, $monthNumber, $amount) {
    try {
        $pdo = getDB();
        
        // Get member and group details
        $stmt = $pdo->prepare("
            SELECT m.member_name, bg.group_name 
            FROM members m 
            JOIN bc_groups bg ON m.group_id = bg.id 
            WHERE m.id = ? AND bg.id = ?
        ");
        $stmt->execute([$memberId, $groupId]);
        $details = $stmt->fetch();
        
        if (!$details) {
            return null;
        }
        
        // Check if QR codes are enabled
        if (!getPaymentConfig('qr_enabled')) {
            return null;
        }
        
        // Generate UPI URL
        $upiUrl = generateUpiPaymentUrl(
            $amount, 
            $details['member_name'], 
            $details['group_name'], 
            $monthNumber
        );
        
        if (!$upiUrl) {
            return null;
        }
        
        // Generate QR code
        $qrCodeUrl = generateQrCodeUrl($upiUrl);
        
        return [
            'qr_code_url' => $qrCodeUrl,
            'upi_url' => $upiUrl,
            'payment_details' => [
                'member_name' => $details['member_name'],
                'group_name' => $details['group_name'],
                'month_number' => $monthNumber,
                'amount' => $amount,
                'upi_id' => getPaymentConfig('upi_id'),
                'payee_name' => getPaymentConfig('bank_account_name')
            ]
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get bank details for manual transfer
 */
function getBankDetails() {
    $configs = getPaymentConfig();
    
    return [
        'account_name' => $configs['bank_account_name'] ?? '',
        'account_number' => $configs['bank_account_number'] ?? '',
        'ifsc_code' => $configs['bank_ifsc'] ?? '',
        'bank_name' => $configs['bank_name'] ?? '',
        'upi_id' => $configs['upi_id'] ?? ''
    ];
}

/**
 * Check if QR code payments are enabled
 */
function isQrPaymentEnabled() {
    return (bool)getPaymentConfig('qr_enabled');
}

/**
 * Generate payment instructions text
 */
function getPaymentInstructions($amount, $memberName, $groupName, $monthNumber) {
    $bankDetails = getBankDetails();
    
    $instructions = "Payment Instructions for {$memberName}\n";
    $instructions .= "Group: {$groupName}\n";
    $instructions .= "Month: {$monthNumber}\n";
    $instructions .= "Amount: ₹" . number_format($amount, 2) . "\n\n";
    
    if (!empty($bankDetails['upi_id'])) {
        $instructions .= "UPI Payment:\n";
        $instructions .= "UPI ID: {$bankDetails['upi_id']}\n";
        $instructions .= "Scan the QR code above or use this UPI ID\n\n";
    }
    
    if (!empty($bankDetails['account_number'])) {
        $instructions .= "Bank Transfer:\n";
        $instructions .= "Account Name: {$bankDetails['account_name']}\n";
        $instructions .= "Account Number: {$bankDetails['account_number']}\n";
        $instructions .= "IFSC Code: {$bankDetails['ifsc_code']}\n";
        $instructions .= "Bank: {$bankDetails['bank_name']}\n\n";
    }
    
    $instructions .= "Please send payment confirmation to admin after payment.";
    
    return $instructions;
}

/**
 * Create payment config table if it doesn't exist
 */
function createPaymentConfigTable() {
    try {
        $pdo = getDB();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) UNIQUE NOT NULL,
                config_value TEXT NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default values if table is empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM payment_config");
        if ($stmt->fetchColumn() == 0) {
            $defaults = [
                ['upi_id', '9768985225kotak@ybl', 'UPI ID for receiving payments'],
                ['bank_account_name', 'BC Group Admin', 'Bank account holder name'],
                ['payment_note', 'BC Group Monthly Payment', 'Default payment note/description'],
                ['qr_enabled', '1', 'Enable/disable QR code payments (1=enabled, 0=disabled)']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO payment_config (config_key, config_value, description) VALUES (?, ?, ?)");
            foreach ($defaults as $default) {
                $stmt->execute($default);
            }
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
