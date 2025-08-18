-- Migration script to add QR code payment functionality
-- Run this script if you already have the BC Management system and want to add QR payment features

USE bc_simple;

-- Create payment configuration table
CREATE TABLE IF NOT EXISTS payment_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default payment configuration (modify these values as needed)
INSERT IGNORE INTO payment_config (config_key, config_value, description) VALUES
('upi_id', '9768985225kotak@ybl', 'UPI ID for receiving payments'),
('bank_account_name', 'BC Group Admin', 'Bank account holder name'),
('payment_note', 'BC Group Monthly Payment', 'Default payment note/description'),
('qr_enabled', '1', 'Enable/disable QR code payments (1=enabled, 0=disabled)');

-- Verify the installation
SELECT 'Payment Configuration Table Created Successfully' as 'Status';

-- Show current configuration
SELECT 
    config_key as 'Setting',
    CASE 
        WHEN config_key = 'bank_account_number' THEN CONCAT(REPEAT('*', LENGTH(config_value) - 4), RIGHT(config_value, 4))
        ELSE config_value 
    END as 'Value',
    description as 'Description'
FROM payment_config 
ORDER BY 
    CASE config_key
        WHEN 'qr_enabled' THEN 1
        WHEN 'upi_id' THEN 2
        WHEN 'payment_note' THEN 3
        WHEN 'bank_account_name' THEN 4
        WHEN 'bank_account_number' THEN 5
        WHEN 'bank_ifsc' THEN 6
        WHEN 'bank_name' THEN 7
        ELSE 8
    END;

-- Instructions
SELECT 'Next Steps:' as 'Instructions';
SELECT '1. Update the payment configuration with your actual details' as 'Step';
SELECT '2. Go to Admin > Payment Settings in the web interface' as 'Step';
SELECT '3. Configure your UPI ID and bank account details' as 'Step';
SELECT '4. Test the QR code generation with test_qr_payment.php' as 'Step';
SELECT '5. Members can now use member_payment.php to scan and pay' as 'Step';
