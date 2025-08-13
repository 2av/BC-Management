-- Bidding System Enhancement for Mitra Niidhi Samooh
-- This script adds the new bidding system tables

USE bc_simple;

-- Create member_bids table to store individual bids from members
CREATE TABLE IF NOT EXISTS member_bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    month_number INT NOT NULL,
    bid_amount DECIMAL(10,2) NOT NULL,
    bid_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    bid_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_group_month (group_id, month_number),
    INDEX idx_member_month (member_id, month_number),
    INDEX idx_bid_status (bid_status)
);

-- Create month_bidding_status table to track bidding periods
CREATE TABLE IF NOT EXISTS month_bidding_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    month_number INT NOT NULL,
    bidding_status ENUM('not_started', 'open', 'closed', 'completed') DEFAULT 'not_started',
    bidding_start_date DATE,
    bidding_end_date DATE,
    minimum_bid_amount DECIMAL(10,2) DEFAULT 0,
    maximum_bid_amount DECIMAL(10,2) DEFAULT 0,
    winner_member_id INT,
    winning_bid_amount DECIMAL(10,2),
    admin_approved_by INT,
    admin_approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_member_id) REFERENCES members(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_approved_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_month_bidding (group_id, month_number)
);

-- Add a column to track if member has already won in previous months
ALTER TABLE members ADD COLUMN IF NOT EXISTS has_won_month INT DEFAULT NULL;
ALTER TABLE members ADD COLUMN IF NOT EXISTS won_amount DECIMAL(10,2) DEFAULT 0;

-- Insert initial bidding status for existing groups
INSERT IGNORE INTO month_bidding_status (group_id, month_number, bidding_status)
SELECT 
    bg.id as group_id,
    numbers.month_number,
    CASE 
        WHEN mb.month_number IS NOT NULL THEN 'completed'
        ELSE 'not_started'
    END as bidding_status
FROM bc_groups bg
CROSS JOIN (
    SELECT 1 as month_number UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION 
    SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION 
    SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION 
    SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
) numbers
LEFT JOIN monthly_bids mb ON bg.id = mb.group_id AND numbers.month_number = mb.month_number
WHERE numbers.month_number <= bg.total_members;

-- Update existing monthly_bids with winner information
UPDATE month_bidding_status mbs
JOIN monthly_bids mb ON mbs.group_id = mb.group_id AND mbs.month_number = mb.month_number
SET
    mbs.winner_member_id = mb.taken_by_member_id,
    mbs.winning_bid_amount = mb.bid_amount,
    mbs.bidding_status = 'completed',
    mbs.admin_approved_at = NOW()
WHERE mb.taken_by_member_id IS NOT NULL;

-- Update members table with won month information based on existing monthly_bids
UPDATE members m
JOIN monthly_bids mb ON m.id = mb.taken_by_member_id
SET
    m.has_won_month = mb.month_number,
    m.won_amount = mb.net_payable
WHERE mb.taken_by_member_id IS NOT NULL;

-- Create historical bid records for existing winners
-- This creates a bid record for each winner based on existing monthly_bids data
INSERT INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT
    mb.group_id,
    mb.taken_by_member_id,
    mb.month_number,
    mb.bid_amount,
    'approved' as bid_status,
    mb.payment_date as bid_date,
    'Historical bid - migrated from existing data' as admin_notes
FROM monthly_bids mb
WHERE mb.taken_by_member_id IS NOT NULL
AND mb.bid_amount > 0
ON DUPLICATE KEY UPDATE
    bid_status = 'approved',
    admin_notes = 'Historical bid - migrated from existing data';

-- For your specific data, let's add the exact records based on the screenshot
-- First, let's find the group ID (assuming it's the Family BC Group)
SET @group_id = (SELECT id FROM bc_groups WHERE group_name = 'Family BC Group' LIMIT 1);

-- Insert specific historical bids based on your data
INSERT IGNORE INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT
    @group_id,
    m.id,
    1,
    0,
    'approved',
    '2025-04-25',
    'Historical winner - Month 1 (No bid, direct allocation)'
FROM members m
WHERE m.group_id = @group_id AND m.member_name = 'Akhilesh Vishwakarma';

INSERT IGNORE INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT
    @group_id,
    m.id,
    2,
    1000,
    'approved',
    '2025-05-25',
    'Historical winner - Month 2 (Bid: ₹1,000)'
FROM members m
WHERE m.group_id = @group_id AND m.member_name = 'Ghanshyam Vishwakarma';

INSERT IGNORE INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT
    @group_id,
    m.id,
    3,
    800,
    'approved',
    '2025-06-25',
    'Historical winner - Month 3 (Bid: ₹800)'
FROM members m
WHERE m.group_id = @group_id AND m.member_name = 'Mohanish Patil';

INSERT IGNORE INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT
    @group_id,
    m.id,
    4,
    800,
    'approved',
    '2025-07-25',
    'Historical winner - Month 4 (Bid: ₹800)'
FROM members m
WHERE m.group_id = @group_id AND m.member_name = 'Vishal Vishwakarma';
