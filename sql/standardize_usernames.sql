-- SQL script to standardize usernames for members with the same name
-- This will help consolidate members into single rows

-- Step 1: Find members with same name but different usernames
SELECT 
    member_name,
    GROUP_CONCAT(DISTINCT username ORDER BY username) as usernames,
    COUNT(DISTINCT username) as username_count,
    COUNT(*) as total_records
FROM members 
WHERE member_name IS NOT NULL AND member_name != ''
GROUP BY member_name 
HAVING COUNT(DISTINCT username) > 1 OR COUNT(*) > 1
ORDER BY member_name;

-- Step 2: Update usernames to be consistent for each member
-- We'll use the first non-null, non-empty username for each member name

-- Create a temporary table with the preferred username for each member
CREATE TEMPORARY TABLE preferred_usernames AS
SELECT 
    member_name,
    COALESCE(
        MIN(CASE WHEN username IS NOT NULL AND username != '' THEN username END),
        LOWER(REPLACE(REPLACE(member_name, ' ', ''), '.', ''))
    ) as preferred_username
FROM members 
WHERE member_name IS NOT NULL AND member_name != ''
GROUP BY member_name;

-- Update all members to use the preferred username
UPDATE members m
JOIN preferred_usernames pu ON m.member_name = pu.member_name
SET m.username = pu.preferred_username
WHERE m.member_name IS NOT NULL AND m.member_name != '';

-- Step 3: Verify the changes
SELECT 
    member_name,
    username,
    COUNT(*) as record_count,
    GROUP_CONCAT(DISTINCT group_id ORDER BY group_id) as group_ids
FROM members 
WHERE member_name IS NOT NULL AND member_name != ''
GROUP BY member_name, username
ORDER BY member_name;

-- Step 4: Check for any remaining duplicates
SELECT 
    member_name,
    COUNT(DISTINCT username) as username_count,
    COUNT(*) as total_records
FROM members 
WHERE member_name IS NOT NULL AND member_name != ''
GROUP BY member_name 
HAVING COUNT(DISTINCT username) > 1
ORDER BY member_name;

-- Clean up
DROP TEMPORARY TABLE preferred_usernames;
