-- Create random_picks table to store random member selections for each month
-- This table tracks when someone uses the "Random Pick" feature

USE bc_simple;

-- Create the random_picks table
CREATE TABLE IF NOT EXISTS random_picks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    month_number INT NOT NULL,
    selected_member_id INT NOT NULL,
    picked_by INT,
    picked_by_type ENUM('admin', 'member') NOT NULL,
    picked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_random_pick_per_month (group_id, month_number),
    INDEX idx_group_month (group_id, month_number),
    INDEX idx_selected_member (selected_member_id)
);

-- Add some sample data for testing (optional)
-- You can remove this section if you don't want sample data

-- Example: Random pick for Month 6 in Family BC Group
-- INSERT INTO random_picks (group_id, month_number, selected_member_id, picked_by, picked_by_type)
-- SELECT 
--     bg.id as group_id,
--     6 as month_number,
--     m.id as selected_member_id,
--     1 as picked_by,
--     'admin' as picked_by_type
-- FROM bc_groups bg
-- CROSS JOIN members m
-- JOIN group_members gm ON m.id = gm.member_id AND gm.group_id = bg.id
-- WHERE bg.group_name = 'Family BC Group'
-- AND m.member_name = 'Priyank Vaghashiya'
-- LIMIT 1;

-- Verify the table was created
DESCRIBE random_picks;

-- Show current structure
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'bc_simple' 
AND TABLE_NAME = 'random_picks'
ORDER BY ORDINAL_POSITION;
