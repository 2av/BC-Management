<?php
/**
 * Subscription Management Functions
 * Handles all subscription-related operations
 */

require_once __DIR__ . '/../config/config.php';

// Get all subscription plans
function getAllSubscriptionPlans($activeOnly = false) {
    $pdo = getDB();
    $sql = "SELECT * FROM subscription_plans";
    if ($activeOnly) {
        $sql .= " WHERE is_active = TRUE";
    }
    $sql .= " ORDER BY duration_months ASC, price ASC";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

// Get subscription plan by ID
function getSubscriptionPlan($planId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
    $stmt->execute([$planId]);
    return $stmt->fetch();
}

// Create new subscription plan
function createSubscriptionPlan($data) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        INSERT INTO subscription_plans 
        (plan_name, duration_months, price, currency, description, features, 
         is_promotional, promotional_discount, max_groups, max_members_per_group, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $features = is_array($data['features']) ? json_encode($data['features']) : $data['features'];
    
    return $stmt->execute([
        $data['plan_name'],
        $data['duration_months'],
        $data['price'],
        $data['currency'] ?? 'INR',
        $data['description'],
        $features,
        $data['is_promotional'] ?? false,
        $data['promotional_discount'] ?? 0.00,
        $data['max_groups'] ?? null,
        $data['max_members_per_group'] ?? null,
        $_SESSION['super_admin_id'] ?? 1
    ]);
}

// Update subscription plan
function updateSubscriptionPlan($planId, $data) {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        UPDATE subscription_plans 
        SET plan_name = ?, duration_months = ?, price = ?, currency = ?, 
            description = ?, features = ?, is_promotional = ?, 
            promotional_discount = ?, max_groups = ?, max_members_per_group = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $features = is_array($data['features']) ? json_encode($data['features']) : $data['features'];
    
    return $stmt->execute([
        $data['plan_name'],
        $data['duration_months'],
        $data['price'],
        $data['currency'] ?? 'INR',
        $data['description'],
        $features,
        $data['is_promotional'] ?? false,
        $data['promotional_discount'] ?? 0.00,
        $data['max_groups'] ?? null,
        $data['max_members_per_group'] ?? null,
        $planId
    ]);
}

// Toggle plan active status
function togglePlanStatus($planId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE subscription_plans SET is_active = NOT is_active WHERE id = ?");
    return $stmt->execute([$planId]);
}

// Get client's current subscription
function getClientSubscription($clientId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT cs.*, sp.plan_name, sp.features 
        FROM client_subscriptions cs
        LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
        WHERE cs.client_id = ? AND cs.status = 'active'
        ORDER BY cs.end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$clientId]);
    return $stmt->fetch();
}

// Create client subscription
function createClientSubscription($clientId, $planId, $paymentData = []) {
    $pdo = getDB();
    
    // Get plan details
    $plan = getSubscriptionPlan($planId);
    if (!$plan) {
        throw new Exception("Invalid subscription plan");
    }
    
    // Calculate dates
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+{$plan['duration_months']} months"));
    
    // Store plan snapshot (preserves plan details even if plan changes later)
    $planSnapshot = json_encode([
        'plan_name' => $plan['plan_name'],
        'duration_months' => $plan['duration_months'],
        'price' => $plan['price'],
        'currency' => $plan['currency'],
        'features' => json_decode($plan['features'], true),
        'max_groups' => $plan['max_groups'],
        'max_members_per_group' => $plan['max_members_per_group']
    ]);
    
    try {
        $pdo->beginTransaction();
        
        // Deactivate any existing active subscriptions
        $stmt = $pdo->prepare("UPDATE client_subscriptions SET status = 'expired' WHERE client_id = ? AND status = 'active'");
        $stmt->execute([$clientId]);
        
        // Create new subscription
        $stmt = $pdo->prepare("
            INSERT INTO client_subscriptions 
            (client_id, plan_id, plan_snapshot, start_date, end_date, payment_amount, 
             payment_method, payment_reference, payment_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $stmt->execute([
            $clientId,
            $planId,
            $planSnapshot,
            $startDate,
            $endDate,
            $paymentData['amount'] ?? $plan['price'],
            $paymentData['method'] ?? 'manual',
            $paymentData['reference'] ?? null,
            $paymentData['date'] ?? date('Y-m-d H:i:s')
        ]);
        
        $subscriptionId = $pdo->lastInsertId();
        
        // Update client subscription status
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET subscription_status = 'active', 
                subscription_end_date = ?, 
                current_subscription_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$endDate, $subscriptionId, $clientId]);
        
        // Schedule notifications
        scheduleSubscriptionNotifications($clientId, $subscriptionId, $endDate);
        
        $pdo->commit();
        return $subscriptionId;
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

// Schedule subscription notifications
function scheduleSubscriptionNotifications($clientId, $subscriptionId, $endDate) {
    $pdo = getDB();
    
    $notifications = [
        ['type' => 'expiry_warning', 'days' => 7, 'message' => 'Your subscription expires in 7 days'],
        ['type' => 'expiry_warning', 'days' => 3, 'message' => 'Your subscription expires in 3 days'],
        ['type' => 'expiry_warning', 'days' => 1, 'message' => 'Your subscription expires tomorrow'],
        ['type' => 'expired', 'days' => 0, 'message' => 'Your subscription has expired']
    ];
    
    foreach ($notifications as $notification) {
        $notificationDate = date('Y-m-d', strtotime($endDate . " -{$notification['days']} days"));
        
        $stmt = $pdo->prepare("
            INSERT INTO subscription_notifications 
            (client_id, subscription_id, notification_type, notification_date, 
             days_before_expiry, message) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $clientId,
            $subscriptionId,
            $notification['type'],
            $notificationDate,
            $notification['days'],
            $notification['message']
        ]);
    }
}

// Check and update expired subscriptions
function checkExpiredSubscriptions() {
    $pdo = getDB();
    
    // Get expired subscriptions
    $stmt = $pdo->query("
        SELECT cs.*, c.client_name 
        FROM client_subscriptions cs
        JOIN clients c ON cs.client_id = c.id
        WHERE cs.status = 'active' AND cs.end_date < CURDATE()
    ");
    
    $expiredSubscriptions = $stmt->fetchAll();
    
    foreach ($expiredSubscriptions as $subscription) {
        // Update subscription status
        $stmt = $pdo->prepare("UPDATE client_subscriptions SET status = 'expired' WHERE id = ?");
        $stmt->execute([$subscription['id']]);
        
        // Update client status
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET subscription_status = 'expired', current_subscription_id = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$subscription['client_id']]);
    }
    
    return count($expiredSubscriptions);
}

// Get subscription statistics
function getSubscriptionStats() {
    $pdo = getDB();
    
    $stats = [];
    
    // Total active subscriptions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM client_subscriptions WHERE status = 'active'");
    $stats['active_subscriptions'] = $stmt->fetch()['count'];
    
    // Total revenue this month
    $stmt = $pdo->query("
        SELECT SUM(payment_amount) as revenue 
        FROM client_subscriptions 
        WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $stats['monthly_revenue'] = $stmt->fetch()['revenue'] ?? 0;
    
    // Expiring soon (next 7 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM client_subscriptions 
        WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $stats['expiring_soon'] = $stmt->fetch()['count'];
    
    // Plan popularity
    $stmt = $pdo->query("
        SELECT sp.plan_name, COUNT(cs.id) as subscription_count
        FROM subscription_plans sp
        LEFT JOIN client_subscriptions cs ON sp.id = cs.plan_id AND cs.status = 'active'
        GROUP BY sp.id, sp.plan_name
        ORDER BY subscription_count DESC
    ");
    $stats['plan_popularity'] = $stmt->fetchAll();
    
    return $stats;
}

// Note: formatCurrency function is defined in config.php

// Get pending notifications
function getPendingNotifications() {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT sn.*, c.client_name, c.email 
        FROM subscription_notifications sn
        JOIN clients c ON sn.client_id = c.id
        WHERE sn.is_sent = FALSE AND sn.notification_date <= CURDATE()
        ORDER BY sn.notification_date ASC
    ");
    return $stmt->fetchAll();
}

// Mark notification as sent
function markNotificationSent($notificationId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE subscription_notifications SET is_sent = TRUE, sent_at = NOW() WHERE id = ?");
    return $stmt->execute([$notificationId]);
}

// Check if client subscription is valid
function isClientSubscriptionValid($clientId) {
    $subscription = getClientSubscription($clientId);
    return $subscription && $subscription['status'] === 'active' && $subscription['end_date'] >= date('Y-m-d');
}

// Get client subscription limits
function getClientSubscriptionLimits($clientId) {
    $subscription = getClientSubscription($clientId);
    if (!$subscription) {
        return ['max_groups' => 2, 'max_members_per_group' => 10]; // Default trial limits
    }

    $planSnapshot = json_decode($subscription['plan_snapshot'], true);
    return [
        'max_groups' => $planSnapshot['max_groups'] ?? null,
        'max_members_per_group' => $planSnapshot['max_members_per_group'] ?? null
    ];
}

// Check if client can create more groups
function canClientCreateGroup($clientId) {
    $limits = getClientSubscriptionLimits($clientId);
    if ($limits['max_groups'] === null) {
        return true; // Unlimited
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bc_groups WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $currentGroups = $stmt->fetch()['count'];

    return $currentGroups < $limits['max_groups'];
}

// Check if group can add more members
function canGroupAddMember($groupId) {
    $pdo = getDB();

    // Get group's client
    $stmt = $pdo->prepare("SELECT client_id FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();

    if (!$group) {
        return false;
    }

    $limits = getClientSubscriptionLimits($group['client_id']);
    if ($limits['max_members_per_group'] === null) {
        return true; // Unlimited
    }

    // Count current members in group
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM members WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $currentMembers = $stmt->fetch()['count'];

    return $currentMembers < $limits['max_members_per_group'];
}
?>
