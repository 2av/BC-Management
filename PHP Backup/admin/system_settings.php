<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$pdo = getDB();
$error = '';
$success = '';

// Create system_settings table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
        description TEXT,
        category VARCHAR(50) DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Initialize default settings if table is empty
$stmt = $pdo->query("SELECT COUNT(*) as count FROM system_settings");
if ($stmt->fetch()['count'] == 0) {
    $defaultSettings = [
        ['app_name', APP_NAME, 'text', 'Application name displayed throughout the system', 'general'],
        ['app_version', '2.1.0', 'text', 'Current application version', 'general'],
        ['default_currency', 'INR', 'text', 'Default currency for the system', 'general'],
        ['currency_symbol', '₹', 'text', 'Currency symbol to display', 'general'],
        ['max_group_members', '20', 'number', 'Maximum number of members allowed in a group', 'groups'],
        ['min_group_members', '5', 'number', 'Minimum number of members required in a group', 'groups'],
        ['default_group_duration', '18', 'number', 'Default duration for groups in months', 'groups'],
        ['enable_sms_notifications', '1', 'boolean', 'Enable SMS notifications system-wide', 'notifications'],
        ['enable_email_notifications', '1', 'boolean', 'Enable email notifications system-wide', 'notifications'],
        ['payment_reminder_days', '3', 'number', 'Days before payment due to send reminders', 'payments'],
        ['late_payment_penalty', '50', 'number', 'Late payment penalty amount', 'payments'],
        ['enable_qr_payments', '1', 'boolean', 'Enable QR code payment functionality', 'payments'],
        ['backup_frequency', 'daily', 'text', 'Automatic backup frequency (daily/weekly/monthly)', 'system'],
        ['maintenance_mode', '0', 'boolean', 'Enable maintenance mode', 'system'],
        ['session_timeout', '3600', 'number', 'Session timeout in seconds', 'security'],
        ['max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', 'security'],
        ['password_min_length', '6', 'number', 'Minimum password length requirement', 'security']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, category) VALUES (?, ?, ?, ?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'update_settings') {
                // Handle boolean values
                if (is_array($value)) {
                    $value = implode(',', $value);
                }
                
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$value, $key]);
            }
        }
        
        // Handle boolean checkboxes that weren't submitted (unchecked)
        $booleanSettings = ['enable_sms_notifications', 'enable_email_notifications', 'enable_qr_payments', 'maintenance_mode'];
        foreach ($booleanSettings as $boolSetting) {
            if (!isset($_POST[$boolSetting])) {
                $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = '0', updated_at = NOW() WHERE setting_key = ?");
                $stmt->execute([$boolSetting]);
            }
        }
        
        $pdo->commit();
        $success = 'System settings updated successfully!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to update settings. Please try again.';
    }
}

// Get all settings grouped by category
$stmt = $pdo->query("SELECT * FROM system_settings ORDER BY category, setting_key");
$allSettings = $stmt->fetchAll();

$settingsByCategory = [];
foreach ($allSettings as $setting) {
    $settingsByCategory[$setting['category']][] = $setting;
}

// Get system statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT g.id) as total_groups,
        COUNT(DISTINCT m.id) as total_members,
        SUM(CASE WHEN mp.payment_status = 'paid' THEN mp.payment_amount ELSE 0 END) as total_collected,
        COUNT(CASE WHEN mp.payment_status = 'paid' THEN 1 END) as total_payments,
        (SELECT COUNT(*) FROM admin_users) as total_admins
    FROM bc_groups g
    LEFT JOIN members m ON g.id = m.group_id AND m.status = 'active'
    LEFT JOIN member_payments mp ON m.id = mp.member_id
");
$systemStats = $stmt->fetch();

// Set page title for the header
$page_title = 'System Settings';

// Include the new header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
    .settings-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border: 1px solid #e3f2fd;
    }
    
    .system-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .stat-item {
        background: rgba(255,255,255,0.1);
        padding: 1rem;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-value {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .category-section {
        margin-bottom: 2rem;
    }
    
    .category-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        border-left: 4px solid #667eea;
    }
    
    .category-header h4 {
        margin: 0;
        color: #667eea;
    }
    
    .setting-item {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
    }
    
    .setting-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
    }
    
    .setting-description {
        font-size: 0.875rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        background: none;
    }
    
    .nav-tabs .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
    }
    
    .tab-content {
        padding: 2rem 0;
    }
    
    .maintenance-warning {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        color: white;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
    }
</style>

<!-- Page content starts here -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-cogs text-primary me-2"></i>System Settings</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <!-- System Header -->
    <div class="system-header">
        <h2><i class="fas fa-server me-2"></i>BC Management System</h2>
        <p class="mb-0">Version <?= getSetting('app_version', '2.1.0') ?></p>
        
        <!-- System Statistics -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($systemStats['total_groups']) ?></div>
                <div class="stat-label">Total Groups</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($systemStats['total_members']) ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">₹<?= number_format($systemStats['total_collected']) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($systemStats['total_payments']) ?></div>
                <div class="stat-label">Payments</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($systemStats['total_admins']) ?></div>
                <div class="stat-label">Administrators</div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Maintenance Mode Warning -->
    <?php if (getSetting('maintenance_mode', '0') === '1'): ?>
        <div class="maintenance-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Maintenance Mode Active:</strong> The system is currently in maintenance mode. Regular users cannot access the application.
        </div>
    <?php endif; ?>

    <!-- Settings Form -->
    <div class="settings-card">
        <form method="POST">
            <!-- Settings Tabs -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="fas fa-cog me-2"></i>General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="groups-tab" data-bs-toggle="tab" data-bs-target="#groups" type="button" role="tab">
                        <i class="fas fa-users me-2"></i>Groups
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                        <i class="fas fa-money-bill-wave me-2"></i>Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                        <i class="fas fa-bell me-2"></i>Notifications
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                        <i class="fas fa-server me-2"></i>System
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="settingsTabContent">
                <?php
                $categoryIcons = [
                    'general' => 'fas fa-cog',
                    'groups' => 'fas fa-users',
                    'payments' => 'fas fa-money-bill-wave',
                    'notifications' => 'fas fa-bell',
                    'security' => 'fas fa-shield-alt',
                    'system' => 'fas fa-server'
                ];
                
                $activeClass = 'show active';
                foreach ($settingsByCategory as $category => $settings):
                ?>
                    <div class="tab-pane fade <?= $activeClass ?>" id="<?= $category ?>" role="tabpanel">
                        <div class="category-section">
                            <div class="category-header">
                                <h4><i class="<?= $categoryIcons[$category] ?? 'fas fa-cog' ?> me-2"></i><?= ucfirst($category) ?> Settings</h4>
                            </div>
                            
                            <?php foreach ($settings as $setting): ?>
                                <div class="setting-item">
                                    <div class="setting-label"><?= ucwords(str_replace('_', ' ', $setting['setting_key'])) ?></div>
                                    <div class="setting-description"><?= htmlspecialchars($setting['description']) ?></div>
                                    
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="<?= $setting['setting_key'] ?>" 
                                                   name="<?= $setting['setting_key'] ?>" value="1"
                                                   <?= $setting['setting_value'] === '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="<?= $setting['setting_key'] ?>">
                                                <?= $setting['setting_value'] === '1' ? 'Enabled' : 'Disabled' ?>
                                            </label>
                                        </div>
                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                        <input type="number" class="form-control" id="<?= $setting['setting_key'] ?>" 
                                               name="<?= $setting['setting_key'] ?>" value="<?= htmlspecialchars($setting['setting_value']) ?>">
                                    <?php else: ?>
                                        <input type="text" class="form-control" id="<?= $setting['setting_key'] ?>" 
                                               name="<?= $setting['setting_key'] ?>" value="<?= htmlspecialchars($setting['setting_value']) ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php $activeClass = ''; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" name="update_settings" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Save All Settings
                </button>
                <button type="button" class="btn btn-outline-secondary ms-2" onclick="location.reload()">
                    <i class="fas fa-undo me-2"></i>Reset Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Helper function to get setting value
function getSetting($key, $default = '') {
    global $pdo;
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings[$key] ?? $default;
}
?>

<?php require_once 'includes/footer.php'; ?>
