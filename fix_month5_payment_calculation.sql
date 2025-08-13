-- Fix Month 5 payment calculation
-- The gain_per_member was incorrectly calculated as bid_amount / remaining_members
-- It should be (total_collection - bid_amount) / remaining_members

USE bc_simple;

-- Check current values
SELECT 
    mb.month_number,
    mb.bid_amount,
    mb.net_payable,
    mb.gain_per_member as current_gain_per_member,
    bg.total_monthly_collection,
    bg.total_members,
    -- Calculate correct gain_per_member
    (bg.total_monthly_collection - mb.bid_amount) / (bg.total_members - 1) as correct_gain_per_member
FROM monthly_bids mb
JOIN bc_groups bg ON mb.group_id = bg.id
WHERE mb.month_number = 5;

-- Update Month 5 with correct calculation
UPDATE monthly_bids mb
JOIN bc_groups bg ON mb.group_id = bg.id
SET 
    mb.net_payable = bg.total_monthly_collection - mb.bid_amount,
    mb.gain_per_member = (bg.total_monthly_collection - mb.bid_amount) / (bg.total_members - 1)
WHERE mb.month_number = 5;

-- Verify the fix
SELECT 
    mb.month_number,
    mb.bid_amount,
    mb.net_payable,
    mb.gain_per_member,
    bg.total_monthly_collection,
    bg.total_members,
    -- Verify calculation
    ROUND((bg.total_monthly_collection - mb.bid_amount) / (bg.total_members - 1), 2) as verification
FROM monthly_bids mb
JOIN bc_groups bg ON mb.group_id = bg.id
WHERE mb.month_number = 5;
