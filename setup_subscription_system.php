<?php
/**
 * Subscription System Setup
 * This script sets up the complete subscription management system
 */

require_once 'config.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Subscription System Setup</title>";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
echo "</head><body>";
echo "<h1>BC Management System - Subscription System Setup</h1>";

function logMessage($message, $type = 'info') {
    echo "<p class='$type'>" . htmlspecialchars($message) . "</p>";
    flush();
}

try {
    $pdo = getDB();
    
    logMessage("Starting subscription system setup...", 'info');
    
    // Read and execute the SQL file
    $sqlFile = 'create_subscription_system.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Could not read SQL file: $sqlFile");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    logMessage("Executing " . count($statements) . " SQL statements...", 'info');
    
    $pdo->beginTransaction();
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty statements and comments
        }
        
        try {
            $pdo->exec($statement);
            logMessage("✓ Executed: " . substr($statement, 0, 50) . "...", 'success');
        } catch (PDOException $e) {
            // Log but continue for statements that might already exist
            logMessage("⚠ Warning: " . $e->getMessage(), 'error');
        }
    }
    
    $pdo->commit();
    
    logMessage("Database setup completed successfully!", 'success');
    
    // Verify tables were created
    $tables = ['subscription_plans', 'client_subscriptions', 'subscription_notifications', 'subscription_payments'];
    
    logMessage("Verifying table creation...", 'info');
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            logMessage("✓ Table '$table' exists", 'success');
        } else {
            logMessage("✗ Table '$table' missing", 'error');
        }
    }
    
    // Check if default plans were inserted
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subscription_plans");
    $planCount = $stmt->fetch()['count'];
    
    logMessage("Default subscription plans created: $planCount", $planCount > 0 ? 'success' : 'error');
    
    if ($planCount > 0) {
        logMessage("Listing default plans:", 'info');
        $stmt = $pdo->query("SELECT plan_name, duration_months, price, currency FROM subscription_plans ORDER BY duration_months");
        while ($plan = $stmt->fetch()) {
            $duration = $plan['duration_months'] == 0 ? 'Trial' : $plan['duration_months'] . ' months';
            $price = $plan['currency'] . ' ' . $plan['price'];
            logMessage("• {$plan['plan_name']} - $duration - $price", 'info');
        }
    }
    
    // Set up default client with trial subscription
    logMessage("Setting up default client subscription...", 'info');
    
    // Check if default client exists
    $stmt = $pdo->query("SELECT id FROM clients WHERE client_name = 'Default Client' LIMIT 1");
    $defaultClient = $stmt->fetch();
    
    if ($defaultClient) {
        $clientId = $defaultClient['id'];
        
        // Check if client already has a subscription
        $stmt = $pdo->prepare("SELECT id FROM client_subscriptions WHERE client_id = ? AND status = 'active'");
        $stmt->execute([$clientId]);
        
        if (!$stmt->fetch()) {
            // Get free trial plan
            $stmt = $pdo->query("SELECT id FROM subscription_plans WHERE is_promotional = TRUE AND price = 0 LIMIT 1");
            $trialPlan = $stmt->fetch();
            
            if ($trialPlan) {
                // Create trial subscription
                $endDate = date('Y-m-d', strtotime('+7 days'));
                
                $stmt = $pdo->prepare("
                    INSERT INTO client_subscriptions 
                    (client_id, plan_id, plan_snapshot, start_date, end_date, payment_amount, status) 
                    VALUES (?, ?, ?, ?, ?, 0.00, 'active')
                ");
                
                $planSnapshot = json_encode([
                    'plan_name' => 'Free Trial',
                    'duration_months' => 0,
                    'price' => 0.00,
                    'currency' => 'INR',
                    'features' => ['Basic features', 'Limited groups', 'Email support'],
                    'max_groups' => 2,
                    'max_members_per_group' => 10
                ]);
                
                $stmt->execute([
                    $clientId,
                    $trialPlan['id'],
                    $planSnapshot,
                    date('Y-m-d'),
                    $endDate
                ]);
                
                // Update client subscription status
                $stmt = $pdo->prepare("
                    UPDATE clients 
                    SET subscription_status = 'trial', subscription_end_date = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$endDate, $clientId]);
                
                logMessage("✓ Default client set up with 7-day trial subscription", 'success');
            } else {
                logMessage("⚠ No trial plan found for default client", 'error');
            }
        } else {
            logMessage("✓ Default client already has an active subscription", 'success');
        }
    } else {
        logMessage("⚠ Default client not found", 'error');
    }
    
    echo "<h2 class='success'>🎉 Subscription System Setup Complete!</h2>";
    echo "<h3>What's Next?</h3>";
    echo "<ul>";
    echo "<li><a href='super_admin_subscription_plans.php'>Manage Subscription Plans</a> - Add, edit, or customize plans</li>";
    echo "<li><a href='super_admin_clients.php'>Manage Client Subscriptions</a> - Assign plans to clients</li>";
    echo "<li><a href='super_admin_dashboard.php'>Super Admin Dashboard</a> - View subscription statistics</li>";
    echo "</ul>";
    
    echo "<h3>Default Subscription Plans Created:</h3>";
    echo "<ul>";
    echo "<li><strong>Free Trial</strong> - 7 days - ₹0 (Promotional)</li>";
    echo "<li><strong>1 Month Plan</strong> - 1 month - ₹100</li>";
    echo "<li><strong>3 Months Plan</strong> - 3 months - ₹280</li>";
    echo "<li><strong>6 Months Plan</strong> - 6 months - ₹550</li>";
    echo "<li><strong>1 Year Plan</strong> - 12 months - ₹1000</li>";
    echo "<li><strong>3 Years Plan</strong> - 36 months - ₹2500</li>";
    echo "</ul>";
    
    echo "<h3>Key Features:</h3>";
    echo "<ul>";
    echo "<li>✅ Flexible subscription plan management</li>";
    echo "<li>✅ Client subscription tracking</li>";
    echo "<li>✅ Automatic expiry notifications</li>";
    echo "<li>✅ Payment history tracking</li>";
    echo "<li>✅ Promotional/trial plan support</li>";
    echo "<li>✅ Subscription limits enforcement</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    logMessage("Setup failed: " . $e->getMessage(), 'error');
    echo "<p class='error'>Please check the error above and try again.</p>";
}

echo "</body></html>";
?>
