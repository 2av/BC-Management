-- Simple BC Management Database
CREATE DATABASE IF NOT EXISTS bc_simple;
USE bc_simple;

-- BC Groups table
CREATE TABLE bc_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    total_members INT NOT NULL,
    monthly_contribution DECIMAL(10,2) NOT NULL,
    total_monthly_collection DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    status ENUM('active', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Members table
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    member_name VARCHAR(100) NOT NULL,
    member_number INT NOT NULL,
    username VARCHAR(50),
    password VARCHAR(255),
    phone VARCHAR(15),
    email VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_per_group (group_id, member_number),
    UNIQUE KEY unique_username (username)
);

-- Monthly bids table (Deposit/Bid Details)
CREATE TABLE monthly_bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    month_number INT NOT NULL,
    taken_by_member_id INT,
    is_bid ENUM('Yes', 'No') DEFAULT 'No',
    bid_amount DECIMAL(10,2) DEFAULT 0,
    net_payable DECIMAL(10,2) DEFAULT 0,
    gain_per_member DECIMAL(10,2) DEFAULT 0,
    payment_date DATE,
    FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (taken_by_member_id) REFERENCES members(id) ON DELETE SET NULL,
    UNIQUE KEY unique_month_per_group (group_id, month_number)
);

-- Member payments table (Transaction Details)
CREATE TABLE member_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    month_number INT NOT NULL,
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('paid', 'pending') DEFAULT 'pending',
    payment_date DATE,
    FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment_per_member_month (group_id, member_id, month_number)
);

-- Member summary table (for totals)
CREATE TABLE member_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    total_paid DECIMAL(10,2) DEFAULT 0,
    given_amount DECIMAL(10,2) DEFAULT 0,
    profit DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_summary_per_member (group_id, member_id)
);

-- Simple admin user
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, password, full_name) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator');

-- Sample BC Group
INSERT INTO bc_groups (group_name, total_members, monthly_contribution, total_monthly_collection, start_date) VALUES 
('Family BC Group', 9, 2000.00, 18000.00, '2025-04-01');

-- Sample Members (password for all: member123)
INSERT INTO members (group_id, member_name, member_number, username, password, phone, email) VALUES
(1, 'Akhilesh Vishwakarma', 1, 'akhilesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', 'akhilesh@example.com'),
(1, 'Ghanshyam Vishwakarma', 2, 'ghanshyam', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543211', 'ghanshyam@example.com'),
(1, 'Mohanish Patil', 3, 'mohanish', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543212', 'mohanish@example.com'),
(1, 'Pradeep Shukla', 4, 'pradeep', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543213', 'pradeep@example.com'),
(1, 'Manish Vishwakarma', 5, 'manish', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543214', 'manish@example.com'),
(1, 'Rahul Vishwakarma', 6, 'rahul', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543215', 'rahul@example.com'),
(1, 'Vishal Bhardwaj', 7, 'vishal_b', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543216', 'vishal.b@example.com'),
(1, 'Vishnu', 8, 'vishnu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543217', 'vishnu@example.com'),
(1, 'Vishal Vishwakarma', 9, 'vishal_v', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543218', 'vishal.v@example.com');

-- Sample Monthly Bids (first 4 months)
INSERT INTO monthly_bids (group_id, month_number, taken_by_member_id, is_bid, bid_amount, net_payable, gain_per_member, payment_date) VALUES 
(1, 1, 1, 'No', 0, 18000.00, 2000, '2025-04-25'),
(1, 2, 2, 'Yes', 1000, 17000.00, 1889, '2025-05-25'),
(1, 3, 3, 'Yes', 800, 17200.00, 1911, '2025-06-25'),
(1, 4, 4, 'Yes', 800, 17200.00, 1911, '2025-07-25');

-- Sample Member Payments (first 4 months)
INSERT INTO member_payments (group_id, member_id, month_number, payment_amount, payment_status, payment_date) VALUES 
-- Month 1 payments (full contribution)
(1, 1, 1, 2000, 'paid', '2025-04-25'),
(1, 2, 1, 2000, 'paid', '2025-04-25'),
(1, 3, 1, 2000, 'paid', '2025-04-25'),
(1, 4, 1, 2000, 'paid', '2025-04-25'),
(1, 5, 1, 2000, 'paid', '2025-04-25'),
(1, 6, 1, 2000, 'paid', '2025-04-25'),
(1, 7, 1, 2000, 'paid', '2025-04-25'),
(1, 8, 1, 2000, 'paid', '2025-04-25'),
(1, 9, 1, 2000, 'paid', '2025-04-25'),

-- Month 2 payments (reduced for non-winners)
(1, 1, 2, 1889, 'paid', '2025-05-25'),
(1, 2, 2, 1889, 'paid', '2025-05-25'),
(1, 3, 2, 1889, 'paid', '2025-05-25'),
(1, 4, 2, 1889, 'paid', '2025-05-25'),
(1, 5, 2, 1889, 'paid', '2025-05-25'),
(1, 6, 2, 1889, 'paid', '2025-05-25'),
(1, 7, 2, 1889, 'paid', '2025-05-25'),
(1, 8, 2, 1889, 'paid', '2025-05-25'),
(1, 9, 2, 1889, 'paid', '2025-05-25'),

-- Month 3 payments
(1, 1, 3, 1911, 'paid', '2025-06-25'),
(1, 2, 3, 1911, 'paid', '2025-06-25'),
(1, 3, 3, 1911, 'paid', '2025-06-25'),
(1, 4, 3, 1911, 'paid', '2025-06-25'),
(1, 5, 3, 1911, 'paid', '2025-06-25'),
(1, 6, 3, 1911, 'paid', '2025-06-25'),
(1, 7, 3, 1911, 'paid', '2025-06-25'),
(1, 8, 3, 1911, 'paid', '2025-06-25'),
(1, 9, 3, 1911, 'paid', '2025-06-25'),

-- Month 4 payments
(1, 1, 4, 1911, 'paid', '2025-07-25'),
(1, 2, 4, 1911, 'paid', '2025-07-25'),
(1, 3, 4, 1911, 'paid', '2025-07-25'),
(1, 4, 4, 1911, 'paid', '2025-07-25'),
(1, 5, 4, 1911, 'paid', '2025-07-25'),
(1, 6, 4, 1911, 'paid', '2025-07-25'),
(1, 7, 4, 1911, 'paid', '2025-07-25'),
(1, 8, 4, 1911, 'paid', '2025-07-25'),
(1, 9, 4, 1911, 'paid', '2025-07-25');

-- Update member summary
INSERT INTO member_summary (group_id, member_id, total_paid, given_amount, profit) VALUES 
(1, 1, 7711, 18000, 10289),
(1, 2, 7711, 17000, 9289),
(1, 3, 7711, 17200, 9489),
(1, 4, 7711, 17200, 9489),
(1, 5, 7711, 0, -7711),
(1, 6, 7711, 0, -7711),
(1, 7, 7711, 0, -7711),
(1, 8, 7711, 0, -7711),
(1, 9, 7711, 0, -7711);
