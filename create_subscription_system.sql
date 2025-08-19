-- Subscription Plans Management System
-- This creates the complete subscription infrastructure

-- 1. Subscription Plans Table
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL,
    duration_months INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    description TEXT,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    is_promotional BOOLEAN DEFAULT FALSE,
    promotional_discount DECIMAL(5,2) DEFAULT 0.00,
    max_groups INT DEFAULT NULL,
    max_members_per_group INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_active (is_active),
    INDEX idx_promotional (is_promotional),
    INDEX idx_duration (duration_months)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Client Subscriptions Table
CREATE TABLE IF NOT EXISTS client_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    plan_id INT NOT NULL,
    plan_snapshot JSON NOT NULL, -- Stores plan details at time of purchase
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled', 'suspended') DEFAULT 'active',
    payment_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    payment_date TIMESTAMP,
    auto_renewal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
    INDEX idx_client_status (client_id, status),
    INDEX idx_end_date (end_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Subscription Notifications Table
CREATE TABLE IF NOT EXISTS subscription_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    subscription_id INT NOT NULL,
    notification_type ENUM('expiry_warning', 'expired', 'renewal_reminder', 'payment_due') NOT NULL,
    notification_date DATE NOT NULL,
    days_before_expiry INT,
    message TEXT,
    is_sent BOOLEAN DEFAULT FALSE,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES client_subscriptions(id) ON DELETE CASCADE,
    INDEX idx_client_date (client_id, notification_date),
    INDEX idx_sent (is_sent),
    INDEX idx_type (notification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Payment History Table
CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    subscription_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_gateway VARCHAR(50),
    gateway_transaction_id VARCHAR(100),
    payment_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES client_subscriptions(id) ON DELETE CASCADE,
    INDEX idx_client_status (client_id, payment_status),
    INDEX idx_reference (payment_reference),
    INDEX idx_gateway_id (gateway_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Subscription Plans
INSERT INTO subscription_plans (plan_name, duration_months, price, description, features, max_groups, max_members_per_group, created_by) VALUES
('1 Month Plan', 1, 100.00, 'Perfect for trying out our BC Management System', 
 JSON_ARRAY('Basic group management', 'Member management', 'Payment tracking', 'Email support'), 
 5, 20, 1),

('3 Months Plan', 3, 280.00, 'Great for small businesses and growing communities', 
 JSON_ARRAY('All basic features', 'Advanced reporting', 'SMS notifications', 'Priority support'), 
 15, 30, 1),

('6 Months Plan', 6, 550.00, 'Ideal for established businesses with multiple groups', 
 JSON_ARRAY('All standard features', 'Custom branding', 'API access', 'Phone support'), 
 30, 50, 1),

('1 Year Plan', 12, 1000.00, 'Best value for serious BC management needs', 
 JSON_ARRAY('All premium features', 'White-label solution', 'Dedicated support', 'Custom integrations'), 
 100, 100, 1),

('3 Years Plan', 36, 2500.00, 'Ultimate package for large organizations', 
 JSON_ARRAY('All enterprise features', 'On-premise deployment option', '24/7 support', 'Custom development'), 
 NULL, NULL, 1),

('Free Trial', 0, 0.00, '7-day free trial to explore all features', 
 JSON_ARRAY('All basic features', 'Limited to 2 groups', 'Email support only'), 
 2, 10, 1);

-- Update the last plan to be promotional
UPDATE subscription_plans SET is_promotional = TRUE, duration_months = 0 WHERE plan_name = 'Free Trial';

-- Add subscription status to clients table if not exists
ALTER TABLE clients ADD COLUMN IF NOT EXISTS subscription_status ENUM('active', 'expired', 'trial', 'suspended') DEFAULT 'trial';
ALTER TABLE clients ADD COLUMN IF NOT EXISTS subscription_end_date DATE NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS current_subscription_id INT NULL;

-- Add foreign key for current subscription
ALTER TABLE clients ADD CONSTRAINT fk_current_subscription 
FOREIGN KEY (current_subscription_id) REFERENCES client_subscriptions(id) ON DELETE SET NULL;

-- Create indexes for better performance
CREATE INDEX idx_clients_subscription_status ON clients(subscription_status);
CREATE INDEX idx_clients_subscription_end ON clients(subscription_end_date);
