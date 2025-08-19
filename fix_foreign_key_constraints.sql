-- Fix Foreign Key Constraint Issues
-- Run this script to resolve foreign key constraint problems during migration

-- Step 1: Check current data state
SELECT 'Checking current data state...' as status;

-- Check if clients table exists and has data
SELECT COUNT(*) as client_count FROM clients;

-- Check bc_groups table and client_id values
SELECT 
    client_id, 
    COUNT(*) as group_count 
FROM bc_groups 
GROUP BY client_id 
ORDER BY client_id;

-- Check for orphaned records (bc_groups with client_id not in clients table)
SELECT 
    bg.client_id,
    COUNT(*) as orphaned_groups
FROM bc_groups bg
LEFT JOIN clients c ON bg.client_id = c.id
WHERE c.id IS NULL
GROUP BY bg.client_id;

-- Step 2: Create default client if it doesn't exist
INSERT IGNORE INTO clients (id, client_name, company_name, contact_person, email, phone, created_by) 
VALUES (1, 'Default Client', 'Default Company', 'Admin User', 'admin@defaultclient.com', '9999999999', 1);

-- Step 3: Update orphaned records to point to default client
UPDATE bc_groups 
SET client_id = 1 
WHERE client_id NOT IN (SELECT id FROM clients);

-- Step 4: Ensure all bc_groups have a valid client_id
UPDATE bc_groups 
SET client_id = 1 
WHERE client_id IS NULL OR client_id = 0;

-- Step 5: Now add the foreign key constraint
ALTER TABLE bc_groups ADD CONSTRAINT fk_bc_groups_client_id 
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;

-- Step 6: Fix other tables with similar issues

-- Fix monthly_bids table
UPDATE monthly_bids 
SET client_id = 1 
WHERE client_id IS NULL OR client_id = 0 OR client_id NOT IN (SELECT id FROM clients);

ALTER TABLE monthly_bids ADD CONSTRAINT fk_monthly_bids_client_id 
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;

-- Fix member_payments table
UPDATE member_payments 
SET client_id = 1 
WHERE client_id IS NULL OR client_id = 0 OR client_id NOT IN (SELECT id FROM clients);

ALTER TABLE member_payments ADD CONSTRAINT fk_member_payments_client_id 
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;

-- Fix member_summary table
UPDATE member_summary 
SET client_id = 1 
WHERE client_id IS NULL OR client_id = 0 OR client_id NOT IN (SELECT id FROM clients);

ALTER TABLE member_summary ADD CONSTRAINT fk_member_summary_client_id 
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;

-- Fix member_bids table (if exists)
UPDATE member_bids 
SET client_id = 1 
WHERE client_id IS NULL OR client_id = 0 OR client_id NOT IN (SELECT id FROM clients);

ALTER TABLE member_bids ADD CONSTRAINT fk_member_bids_client_id 
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;

-- Fix month_bidding_status table (if exists)
UPDATE month_bidding_status 
SET client_id = 1 
WHERE client_id IS NULL OR client_id = 0 OR client_id NOT IN (SELECT id FROM clients);

ALTER TABLE month_bidding_status ADD CONSTRAINT fk_month_bidding_status_client_id 
FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;

-- Step 7: Verify the fixes
SELECT 'Verification Results:' as status;

-- Check that all bc_groups now have valid client_id
SELECT 
    'bc_groups' as table_name,
    COUNT(*) as total_records,
    COUNT(CASE WHEN client_id IN (SELECT id FROM clients) THEN 1 END) as valid_client_ids
FROM bc_groups;

-- Check other tables
SELECT 
    'monthly_bids' as table_name,
    COUNT(*) as total_records,
    COUNT(CASE WHEN client_id IN (SELECT id FROM clients) THEN 1 END) as valid_client_ids
FROM monthly_bids;

SELECT 
    'member_payments' as table_name,
    COUNT(*) as total_records,
    COUNT(CASE WHEN client_id IN (SELECT id FROM clients) THEN 1 END) as valid_client_ids
FROM member_payments;

SELECT 'Foreign key constraints added successfully!' as status;
