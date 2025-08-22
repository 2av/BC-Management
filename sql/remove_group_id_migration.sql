-- Simple migration script to remove group_id column from members table
-- This script should be run AFTER all data has been migrated to group_members table

-- Step 1: Verify that group_members table exists and has data
SELECT 'Checking group_members table...' as Status;

SELECT
    CASE
        WHEN COUNT(*) > 0 THEN 'group_members table exists'
        ELSE 'ERROR: group_members table does not exist!'
    END as TableCheck
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'group_members';

SELECT
    CONCAT('group_members has ', COUNT(*), ' records') as DataCheck
FROM group_members;

-- Step 2: Verify data integrity - compare counts
SELECT 'Verifying data integrity...' as Status;

SELECT
    CONCAT('Members with group_id: ', COUNT(*)) as MembersWithGroupId
FROM members
WHERE group_id IS NOT NULL;

SELECT
    CONCAT('Group member assignments: ', COUNT(*)) as GroupMemberAssignments
FROM group_members;

-- Step 3: Remove foreign key constraint (members.group_id -> bc_groups.id)
SELECT 'Removing foreign key constraint...' as Status;

ALTER TABLE members DROP FOREIGN KEY members_ibfk_1;

-- Step 4: Remove unique constraint (group_id + member_number)
SELECT 'Removing unique constraint...' as Status;

ALTER TABLE members DROP INDEX unique_member_per_group;

-- Step 5: Remove the group_id column
SELECT 'Removing group_id column...' as Status;

ALTER TABLE members DROP COLUMN group_id;

-- Step 6: Verification - ensure column is removed
SELECT 'Verifying column removal...' as Status;

SELECT
    CASE
        WHEN COUNT(*) = 0 THEN 'SUCCESS: group_id column removed from members table'
        ELSE 'ERROR: group_id column still exists in members table'
    END as VerificationResult
FROM information_schema.columns
WHERE table_schema = DATABASE()
    AND table_name = 'members'
    AND column_name = 'group_id';

-- Step 7: Show final table structure
SELECT 'Final members table structure:' as Status;

DESCRIBE members;

SELECT 'Migration completed successfully!' as Status;
