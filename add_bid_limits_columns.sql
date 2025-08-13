-- Add minimum and maximum bid amount columns to existing month_bidding_status table
-- Run this if you already have the bidding system tables but need to add bid limits

USE bc_simple;

-- Add the new columns if they don't exist
ALTER TABLE month_bidding_status 
ADD COLUMN IF NOT EXISTS minimum_bid_amount DECIMAL(10,2) DEFAULT 0 AFTER bidding_end_date,
ADD COLUMN IF NOT EXISTS maximum_bid_amount DECIMAL(10,2) DEFAULT 0 AFTER minimum_bid_amount;

-- Set default values for existing records
-- You can customize these values based on your group's needs

-- For Family BC Group (assuming total collection is ₹18,000)
UPDATE month_bidding_status mbs
JOIN bc_groups bg ON mbs.group_id = bg.id
SET 
    mbs.minimum_bid_amount = 500,  -- Minimum bid of ₹500
    mbs.maximum_bid_amount = bg.total_monthly_collection - 1  -- Maximum just below total collection
WHERE bg.group_name = 'Family BC Group'
AND mbs.minimum_bid_amount = 0;

-- For other groups, set reasonable defaults
UPDATE month_bidding_status mbs
JOIN bc_groups bg ON mbs.group_id = bg.id
SET 
    mbs.minimum_bid_amount = GREATEST(100, ROUND(bg.total_monthly_collection * 0.05, 0)),  -- 5% of total or ₹100 minimum
    mbs.maximum_bid_amount = bg.total_monthly_collection - 1
WHERE mbs.minimum_bid_amount = 0;

-- Verify the changes
SELECT 
    bg.group_name,
    mbs.month_number,
    mbs.bidding_status,
    mbs.minimum_bid_amount,
    mbs.maximum_bid_amount,
    bg.total_monthly_collection
FROM month_bidding_status mbs
JOIN bc_groups bg ON mbs.group_id = bg.id
ORDER BY bg.group_name, mbs.month_number;
