-- Fix Username Constraint Issue
-- This script removes the unique constraint on username to allow same member in multiple groups

USE bc_simple;

-- Step 1: Drop the unique constraint on username
ALTER TABLE members DROP INDEX unique_username;

-- Step 2: Add a new composite unique constraint for username per group
-- This ensures username is unique within each group, but can be repeated across groups
ALTER TABLE members ADD UNIQUE KEY unique_username_per_group (group_id, username);

-- Step 3: Verify the changes
SHOW INDEX FROM members WHERE Key_name LIKE '%username%';

-- Display current members to verify
SELECT 
    m.id,
    m.group_id,
    g.group_name,
    m.member_name,
    m.username,
    m.member_number
FROM members m
JOIN bc_groups g ON m.group_id = g.id
ORDER BY m.member_name, m.group_id;

SELECT 'Username constraint fix completed successfully!' as Status,
       'Same member can now have same username across different groups' as Description;
