<?php
/**
 * Subscription Management Cron Job
 * This script should be run daily to:
 * 1. Check for expired subscriptions
 * 2. Send expiry notifications
 * 3. Update subscription statuses
 */

require_once 'config.php';
require_once 'subscription_functions.php';

// Set execution time limit for long-running script
set_time_limit(300); // 5 minutes

$logFile = 'logs/subscription_cron_' . date('Y-m-d') . '.log';

function logMessage($message) {
    global $logFile;
    
    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also output to console if running from command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

try {
    logMessage("Starting subscription management cron job");
    
    // 1. Check and update expired subscriptions
    logMessage("Checking for expired subscriptions...");
    $expiredCount = checkExpiredSubscriptions();
    logMessage("Updated $expiredCount expired subscriptions");
    
    // 2. Send pending notifications
    logMessage("Processing pending notifications...");
    $pendingNotifications = getPendingNotifications();
    $sentCount = 0;
    
    foreach ($pendingNotifications as $notification) {
        try {
            // Send notification (email, SMS, etc.)
            $success = sendSubscriptionNotification($notification);
            
            if ($success) {
                markNotificationSent($notification['id']);
                $sentCount++;
                logMessage("Sent notification to {$notification['client_name']} ({$notification['notification_type']})");
            } else {
                logMessage("Failed to send notification to {$notification['client_name']}");
            }
        } catch (Exception $e) {
            logMessage("Error sending notification to {$notification['client_name']}: " . $e->getMessage());
        }
    }
    
    logMessage("Sent $sentCount notifications");
    
    // 3. Generate daily subscription report
    logMessage("Generating daily subscription report...");
    generateDailySubscriptionReport();
    
    logMessage("Subscription management cron job completed successfully");
    
} catch (Exception $e) {
    logMessage("Cron job failed: " . $e->getMessage());
    
    // Send alert to super admin about cron failure
    try {
        sendCronFailureAlert($e->getMessage());
    } catch (Exception $alertException) {
        logMessage("Failed to send cron failure alert: " . $alertException->getMessage());
    }
}

/**
 * Send subscription notification via email
 */
function sendSubscriptionNotification($notification) {
    // This is a basic implementation - you can enhance with proper email templates
    $to = $notification['email'];
    $subject = "BC Management - Subscription " . ucfirst(str_replace('_', ' ', $notification['notification_type']));
    
    $message = "Dear {$notification['client_name']},\n\n";
    $message .= $notification['message'] . "\n\n";
    
    switch ($notification['notification_type']) {
        case 'expiry_warning':
            $message .= "Please renew your subscription to continue using our services without interruption.\n";
            $message .= "You can renew your subscription by contacting our support team.\n\n";
            break;
            
        case 'expired':
            $message .= "Your subscription has expired. Please renew to restore access to all features.\n";
            $message .= "Contact our support team for immediate renewal.\n\n";
            break;
            
        case 'renewal_reminder':
            $message .= "This is a friendly reminder to renew your subscription.\n";
            $message .= "Renew now to avoid any service interruption.\n\n";
            break;
    }
    
    $message .= "If you have any questions, please contact our support team.\n\n";
    $message .= "Best regards,\n";
    $message .= "BC Management Team";
    
    $headers = "From: noreply@bcmanagement.com\r\n";
    $headers .= "Reply-To: support@bcmanagement.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Use mail() function - in production, use a proper email service
    return mail($to, $subject, $message, $headers);
}

/**
 * Generate daily subscription report
 */
function generateDailySubscriptionReport() {
    $pdo = getDB();
    
    // Get today's statistics
    $today = date('Y-m-d');
    
    // New subscriptions today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM client_subscriptions WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $newSubscriptions = $stmt->fetch()['count'];
    
    // Expired subscriptions today
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM client_subscriptions WHERE DATE(end_date) = ? AND status = 'expired'");
    $stmt->execute([$today]);
    $expiredToday = $stmt->fetch()['count'];
    
    // Revenue today
    $stmt = $pdo->prepare("SELECT SUM(payment_amount) as revenue FROM client_subscriptions WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $revenueToday = $stmt->fetch()['revenue'] ?? 0;
    
    // Expiring in next 7 days
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM client_subscriptions 
        WHERE status = 'active' 
        AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $expiringSoon = $stmt->fetch()['count'];
    
    // Save report to database or file
    $report = [
        'date' => $today,
        'new_subscriptions' => $newSubscriptions,
        'expired_subscriptions' => $expiredToday,
        'revenue' => $revenueToday,
        'expiring_soon' => $expiringSoon,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    // Save to file
    $reportFile = 'reports/subscription_report_' . $today . '.json';
    $reportDir = dirname($reportFile);
    if (!is_dir($reportDir)) {
        mkdir($reportDir, 0755, true);
    }
    
    file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
    
    logMessage("Daily report generated: $newSubscriptions new, $expiredToday expired, " . formatCurrency($revenueToday) . " revenue");
}

/**
 * Send cron failure alert to super admin
 */
function sendCronFailureAlert($errorMessage) {
    $pdo = getDB();
    
    // Get super admin email
    $stmt = $pdo->query("SELECT email FROM super_admins WHERE status = 'active' LIMIT 1");
    $superAdmin = $stmt->fetch();
    
    if ($superAdmin) {
        $to = $superAdmin['email'];
        $subject = "BC Management - Subscription Cron Job Failed";
        
        $message = "The subscription management cron job has failed.\n\n";
        $message .= "Error: $errorMessage\n\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Please check the system immediately.\n\n";
        $message .= "BC Management System";
        
        $headers = "From: system@bcmanagement.com\r\n";
        $headers .= "X-Priority: 1\r\n"; // High priority
        
        mail($to, $subject, $message, $headers);
    }
}

// If running from web browser, show simple output
if (php_sapi_name() !== 'cli') {
    echo "<h2>Subscription Cron Job</h2>";
    echo "<p>Job completed. Check log file: $logFile</p>";
    echo "<p><a href='super_admin_dashboard.php'>Back to Dashboard</a></p>";
}
?>
