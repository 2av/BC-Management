-- Update random_picks table to support admin overrides
USE bc_simple;

-- Add columns for admin override functionality
ALTER TABLE random_picks 
ADD COLUMN admin_override_member_id INT NULL AFTER selected_member_id,
ADD COLUMN admin_override_by INT NULL AFTER admin_override_member_id,
ADD COLUMN admin_override_at TIMESTAMP NULL AFTER admin_override_by,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER admin_override_at;

-- Add foreign key constraints
ALTER TABLE random_picks 
ADD CONSTRAINT fk_random_picks_admin_override_member 
    FOREIGN KEY (admin_override_member_id) REFERENCES members(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_random_picks_admin_override_by 
    FOREIGN KEY (admin_override_by) REFERENCES admin_users(id) ON DELETE SET NULL;

-- Add index for better performance
CREATE INDEX idx_random_picks_group_month ON random_picks(group_id, month_number);
CREATE INDEX idx_random_picks_admin_override ON random_picks(admin_override_member_id);
