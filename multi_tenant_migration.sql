-- Multi-Tenant BC Management System Migration
-- This script adds Super Admin and Client functionality to the existing BC Management System
-- Run this script after backing up your existing database

-- Create Super Admin table
CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Clients table
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(150),
    contact_person VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    country VARCHAR(50) DEFAULT 'India',
    pincode VARCHAR(10),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    subscription_plan ENUM('basic', 'premium', 'enterprise') DEFAULT 'basic',
    max_groups INT DEFAULT 10,
    max_members_per_group INT DEFAULT 50,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES super_admins(id) ON DELETE RESTRICT,
    INDEX idx_client_status (status),
    INDEX idx_client_created_by (created_by)
);

-- Create Client Admins table (replaces current admin_users for client context)
CREATE TABLE IF NOT EXISTS client_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(15),
    role ENUM('admin', 'manager') DEFAULT 'admin',
    permissions JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES super_admins(id) ON DELETE SET NULL,
    UNIQUE KEY unique_username_per_client (client_id, username),
    INDEX idx_client_admin_status (status),
    INDEX idx_client_admin_client (client_id)
);

-- Add client_id to existing tables for data isolation

-- Add client_id to bc_groups table
ALTER TABLE bc_groups ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE bc_groups ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
ALTER TABLE bc_groups ADD INDEX idx_bc_groups_client (client_id);

-- Add client_id to members table (derived from group)
-- Note: We'll use a view or join to get client_id for members through their group

-- Add client_id to monthly_bids table
ALTER TABLE monthly_bids ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE monthly_bids ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
ALTER TABLE monthly_bids ADD INDEX idx_monthly_bids_client (client_id);

-- Add client_id to member_payments table
ALTER TABLE member_payments ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE member_payments ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
ALTER TABLE member_payments ADD INDEX idx_member_payments_client (client_id);

-- Add client_id to member_summary table
ALTER TABLE member_summary ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE member_summary ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
ALTER TABLE member_summary ADD INDEX idx_member_summary_client (client_id);

-- Add client_id to member_bids table (if exists)
ALTER TABLE member_bids ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE member_bids ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
ALTER TABLE member_bids ADD INDEX idx_member_bids_client (client_id);

-- Add client_id to month_bidding_status table (if exists)
ALTER TABLE month_bidding_status ADD COLUMN client_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE month_bidding_status ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE;
ALTER TABLE month_bidding_status ADD INDEX idx_month_bidding_status_client (client_id);

-- Create payment_config table with client support (if not exists)
CREATE TABLE IF NOT EXISTS payment_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    config_key VARCHAR(50) NOT NULL,
    config_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_config_per_client (client_id, config_key),
    INDEX idx_payment_config_client (client_id)
);

-- Insert default Super Admin (password: superadmin123)
INSERT INTO super_admins (username, password, full_name, email) VALUES 
('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'superadmin@bcmanagement.com');

-- Insert default client (for existing data)
INSERT INTO clients (client_name, company_name, contact_person, email, phone, created_by) VALUES 
('Default Client', 'Default Company', 'Admin User', 'admin@defaultclient.com', '9999999999', 1);

-- Migrate existing admin to client admin
INSERT INTO client_admins (client_id, username, password, full_name, email, created_by)
SELECT 1, username, password, full_name, 'admin@defaultclient.com', 1
FROM admin_users 
WHERE username = 'admin';

-- Update all existing records to belong to default client (client_id = 1)
UPDATE bc_groups SET client_id = 1 WHERE client_id = 0 OR client_id IS NULL;
UPDATE monthly_bids SET client_id = 1 WHERE client_id = 0 OR client_id IS NULL;
UPDATE member_payments SET client_id = 1 WHERE client_id = 0 OR client_id IS NULL;
UPDATE member_summary SET client_id = 1 WHERE client_id = 0 OR client_id IS NULL;
UPDATE member_bids SET client_id = 1 WHERE client_id = 0 OR client_id IS NULL;
UPDATE month_bidding_status SET client_id = 1 WHERE client_id = 0 OR client_id IS NULL;

-- Insert default payment config for default client
INSERT INTO payment_config (client_id, config_key, config_value, description) VALUES
(1, 'upi_id', '9768985225kotak@ybl', 'UPI ID for receiving payments'),
(1, 'bank_account_name', 'BC Group Admin', 'Bank account holder name'),
(1, 'payment_note', 'BC Group Monthly Payment', 'Default payment note/description'),
(1, 'qr_enabled', '1', 'Enable/disable QR code payments (1=enabled, 0=disabled)');

-- Create views for easier data access
CREATE OR REPLACE VIEW member_client_view AS
SELECT m.*, g.client_id
FROM members m
JOIN bc_groups g ON m.group_id = g.id;

-- Create audit log table for tracking changes
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    user_type ENUM('super_admin', 'client_admin', 'member') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    INDEX idx_audit_client (client_id),
    INDEX idx_audit_user (user_type, user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_created (created_at)
);

-- Create system settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description, is_public) VALUES
('app_name', 'BC Management System', 'Application name', TRUE),
('app_version', '2.0.0', 'Application version', TRUE),
('maintenance_mode', '0', 'Maintenance mode (1=enabled, 0=disabled)', FALSE),
('max_clients', '100', 'Maximum number of clients allowed', FALSE),
('default_client_max_groups', '10', 'Default maximum groups per client', FALSE),
('default_client_max_members', '50', 'Default maximum members per group', FALSE);

-- Create client statistics view
CREATE OR REPLACE VIEW client_statistics AS
SELECT
    c.id as client_id,
    c.client_name,
    c.status,
    COUNT(DISTINCT g.id) as total_groups,
    COUNT(DISTINCT m.id) as total_members,
    SUM(g.monthly_contribution * g.total_members) as total_monthly_collection,
    COUNT(DISTINCT CASE WHEN g.status = 'active' THEN g.id END) as active_groups,
    COUNT(DISTINCT CASE WHEN g.status = 'completed' THEN g.id END) as completed_groups
FROM clients c
LEFT JOIN bc_groups g ON c.id = g.client_id
LEFT JOIN members m ON g.id = m.group_id AND m.status = 'active'
GROUP BY c.id, c.client_name, c.status;

-- Migration complete message
SELECT 'Multi-tenant migration completed successfully!' as message;
