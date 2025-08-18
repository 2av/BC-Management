-- Migration script to update member_payments table structure
-- Run this if you have an existing BC Management system

USE bc_simple;

-- Check if member_payments table exists
SELECT 'Checking member_payments table...' as 'Status';

-- Add missing columns if they don't exist
-- Note: MySQL will ignore ADD COLUMN if column already exists in newer versions

-- Add payment_method column
ALTER TABLE member_payments 
ADD COLUMN payment_method VARCHAR(50) DEFAULT 'upi' AFTER payment_date;

-- Add transaction_id column
ALTER TABLE member_payments 
ADD COLUMN transaction_id VARCHAR(100) NULL AFTER payment_method;

-- Add notes column
ALTER TABLE member_payments 
ADD COLUMN notes TEXT NULL AFTER transaction_id;

-- Add created_at column
ALTER TABLE member_payments 
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes;

-- Add updated_at column
ALTER TABLE member_payments 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Modify payment_status to include 'failed' option
ALTER TABLE member_payments 
MODIFY COLUMN payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending';

-- Modify payment_amount to have default value
ALTER TABLE member_payments 
MODIFY COLUMN payment_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Modify payment_date to allow NULL
ALTER TABLE member_payments 
MODIFY COLUMN payment_date DATE NULL;

-- Show updated table structure
SELECT 'Updated member_payments table structure:' as 'Status';
DESCRIBE member_payments;

-- Show sample data
SELECT 'Sample member_payments data:' as 'Status';
SELECT 
    mp.id,
    mp.group_id,
    m.member_name,
    mp.month_number,
    mp.payment_amount,
    mp.payment_status,
    mp.payment_date
FROM member_payments mp
LEFT JOIN members m ON mp.member_id = m.id
LIMIT 5;

SELECT 'Migration completed successfully!' as 'Status';
