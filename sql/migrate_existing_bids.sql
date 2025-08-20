-- Migration script for existing 4 months of bid data
-- This script will properly migrate your existing bid winners to the new bidding system

USE bc_simple;

-- First, let's get the group ID for Family BC Group
SET @group_id = (SELECT id FROM bc_groups WHERE group_name = 'Family BC Group' LIMIT 1);

-- Display the group ID for verification
SELECT @group_id as 'Group ID for Family BC Group';

-- Check current members in the group
SELECT id, member_name, member_number FROM members WHERE group_id = @group_id ORDER BY member_number;

-- Update the bidding status for completed months (1-4)
UPDATE month_bidding_status 
SET 
    bidding_status = 'completed',
    admin_approved_at = NOW()
WHERE group_id = @group_id AND month_number IN (1, 2, 3, 4);

-- Month 1: Akhilesh Vishwakarma (No bid - direct allocation)
UPDATE month_bidding_status 
SET 
    winner_member_id = (SELECT id FROM members WHERE group_id = @group_id AND member_name = 'Akhilesh Vishwakarma'),
    winning_bid_amount = 0,
    bidding_status = 'completed',
    admin_approved_at = '2025-04-25 00:00:00'
WHERE group_id = @group_id AND month_number = 1;

-- Month 2: Ghanshyam Vishwakarma (Bid: ₹1,000)
UPDATE month_bidding_status 
SET 
    winner_member_id = (SELECT id FROM members WHERE group_id = @group_id AND member_name = 'Ghanshyam Vishwakarma'),
    winning_bid_amount = 1000,
    bidding_status = 'completed',
    admin_approved_at = '2025-05-25 00:00:00'
WHERE group_id = @group_id AND month_number = 2;

-- Month 3: Mohanish Patil (Bid: ₹800)
UPDATE month_bidding_status 
SET 
    winner_member_id = (SELECT id FROM members WHERE group_id = @group_id AND member_name = 'Mohanish Patil'),
    winning_bid_amount = 800,
    bidding_status = 'completed',
    admin_approved_at = '2025-06-25 00:00:00'
WHERE group_id = @group_id AND month_number = 3;

-- Month 4: Vishal Vishwakarma (Bid: ₹800)
UPDATE month_bidding_status 
SET 
    winner_member_id = (SELECT id FROM members WHERE group_id = @group_id AND member_name = 'Vishal Vishwakarma'),
    winning_bid_amount = 800,
    bidding_status = 'completed',
    admin_approved_at = '2025-07-25 00:00:00'
WHERE group_id = @group_id AND month_number = 4;

-- Update members table with won month information
UPDATE members 
SET 
    has_won_month = 1,
    won_amount = 18000  -- Net payable for Month 1 (₹18,000 - ₹0)
WHERE group_id = @group_id AND member_name = 'Akhilesh Vishwakarma';

UPDATE members 
SET 
    has_won_month = 2,
    won_amount = 17000  -- Net payable for Month 2 (₹18,000 - ₹1,000)
WHERE group_id = @group_id AND member_name = 'Ghanshyam Vishwakarma';

UPDATE members 
SET 
    has_won_month = 3,
    won_amount = 17200  -- Net payable for Month 3 (₹18,000 - ₹800)
WHERE group_id = @group_id AND member_name = 'Mohanish Patil';

UPDATE members 
SET 
    has_won_month = 4,
    won_amount = 17200  -- Net payable for Month 4 (₹18,000 - ₹800)
WHERE group_id = @group_id AND member_name = 'Vishal Vishwakarma';

-- Create historical bid records for the winners
-- Month 1: Akhilesh Vishwakarma
INSERT INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT 
    @group_id,
    id,
    1,
    0,
    'approved',
    '2025-04-25 10:00:00',
    'Historical winner - Month 1 (Direct allocation, no bidding)'
FROM members 
WHERE group_id = @group_id AND member_name = 'Akhilesh Vishwakarma';

-- Month 2: Ghanshyam Vishwakarma
INSERT INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT 
    @group_id,
    id,
    2,
    1000,
    'approved',
    '2025-05-25 10:00:00',
    'Historical winner - Month 2 (Winning bid: ₹1,000)'
FROM members 
WHERE group_id = @group_id AND member_name = 'Ghanshyam Vishwakarma';

-- Month 3: Mohanish Patil
INSERT INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT 
    @group_id,
    id,
    3,
    800,
    'approved',
    '2025-06-25 10:00:00',
    'Historical winner - Month 3 (Winning bid: ₹800)'
FROM members 
WHERE group_id = @group_id AND member_name = 'Mohanish Patil';

-- Month 4: Vishal Vishwakarma
INSERT INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
SELECT 
    @group_id,
    id,
    4,
    800,
    'approved',
    '2025-07-25 10:00:00',
    'Historical winner - Month 4 (Winning bid: ₹800)'
FROM members 
WHERE group_id = @group_id AND member_name = 'Vishal Vishwakarma';

-- Verify the migration
SELECT 'Bidding Status Verification' as 'Check';
SELECT 
    mbs.month_number,
    mbs.bidding_status,
    m.member_name as winner_name,
    mbs.winning_bid_amount,
    mbs.admin_approved_at
FROM month_bidding_status mbs
LEFT JOIN members m ON mbs.winner_member_id = m.id
WHERE mbs.group_id = @group_id AND mbs.month_number <= 4
ORDER BY mbs.month_number;

SELECT 'Member Won Status Verification' as 'Check';
SELECT 
    member_name,
    member_number,
    has_won_month,
    won_amount
FROM members 
WHERE group_id = @group_id AND has_won_month IS NOT NULL
ORDER BY has_won_month;

SELECT 'Historical Bids Verification' as 'Check';
SELECT 
    mb.month_number,
    m.member_name,
    mb.bid_amount,
    mb.bid_status,
    mb.bid_date,
    mb.admin_notes
FROM member_bids mb
JOIN members m ON mb.member_id = m.id
WHERE mb.group_id = @group_id
ORDER BY mb.month_number;

-- Show remaining eligible members for future bidding
SELECT 'Eligible Members for Future Bidding' as 'Check';
SELECT 
    member_name,
    member_number,
    CASE 
        WHEN has_won_month IS NULL THEN 'Eligible to bid'
        ELSE CONCAT('Already won Month ', has_won_month)
    END as bidding_status
FROM members 
WHERE group_id = @group_id
ORDER BY member_number;
