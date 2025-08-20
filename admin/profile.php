<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$pdo = getDB();
$error = '';
$success = '';

// Create admin_preferences table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admin_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        timezone VARCHAR(100) DEFAULT 'Asia/Kolkata',
        language VARCHAR(10) DEFAULT 'en',
        email_notifications BOOLEAN DEFAULT TRUE,
        sms_notifications BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_admin (admin_id)
    )
");

// Get current admin details
$adminId = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: ../auth/admin_login.php');
    exit;
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if (empty($fullName)) {
            $error = 'Full name is required.';
        } else {
            try {
                // Check if email already exists for another admin
                if (!empty($email)) {
                    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $adminId]);
                    if ($stmt->fetch()) {
                        $error = 'Email address is already in use by another admin.';
                    }
                }
                
                if (empty($error)) {
                    $stmt = $pdo->prepare("
                        UPDATE admin_users 
                        SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$fullName, $email, $phone, $address, $adminId]);
                    
                    // Update session name
                    $_SESSION['admin_name'] = $fullName;
                    
                    $success = 'Profile updated successfully!';
                    
                    // Refresh admin data
                    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $admin = $stmt->fetch();
                }
            } catch (Exception $e) {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    } elseif (isset($_POST['update_preferences'])) {
        $timezone = $_POST['timezone'];
        $language = $_POST['language'];
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO admin_preferences (admin_id, timezone, language, email_notifications, sms_notifications)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                timezone = VALUES(timezone),
                language = VALUES(language),
                email_notifications = VALUES(email_notifications),
                sms_notifications = VALUES(sms_notifications),
                updated_at = NOW()
            ");
            $stmt->execute([$adminId, $timezone, $language, $emailNotifications, $smsNotifications]);
            
            $success = 'Preferences updated successfully!';
        } catch (Exception $e) {
            $error = 'Failed to update preferences. Please try again.';
        }
    }
}

// Get admin preferences
$stmt = $pdo->prepare("SELECT * FROM admin_preferences WHERE admin_id = ?");
$stmt->execute([$adminId]);
$preferences = $stmt->fetch();

// Default preferences if none exist
if (!$preferences) {
    $preferences = [
        'timezone' => 'Asia/Kolkata',
        'language' => 'en',
        'email_notifications' => 1,
        'sms_notifications' => 1
    ];
}

// Get admin statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT g.id) as total_groups,
        COUNT(DISTINCT m.id) as total_members,
        SUM(CASE WHEN mp.payment_status = 'paid' THEN mp.payment_amount ELSE 0 END) as total_collected,
        COUNT(CASE WHEN mp.payment_status = 'paid' THEN 1 END) as total_payments
    FROM bc_groups g
    LEFT JOIN members m ON g.id = m.group_id AND m.status = 'active'
    LEFT JOIN member_payments mp ON m.id = mp.member_id
");
$stats = $stmt->fetch();

// Set page title for the header
$page_title = 'Profile Settings';

// Include the new header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
    .profile-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border: 1px solid #e3f2fd;
    }
    
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 3rem;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 2rem;
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
    
    .form-section {
        margin-bottom: 2rem;
    }
    
    .form-section h4 {
        color: #667eea;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e3f2fd;
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
</style>

<!-- Page content starts here -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-user-cog text-primary me-2"></i>Profile Settings</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fas fa-user"></i>
        </div>
        <h2><?= htmlspecialchars($admin['full_name']) ?></h2>
        <p class="mb-0">Administrator</p>
        <p class="mb-0"><small>Member since <?= date('F Y', strtotime($admin['created_at'])) ?></small></p>
        
        <!-- Admin Statistics -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?= number_format($stats['total_groups']) ?></div>
                <div class="stat-label">Groups Managed</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($stats['total_members']) ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">₹<?= number_format($stats['total_collected']) ?></div>
                <div class="stat-label">Total Collected</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= number_format($stats['total_payments']) ?></div>
                <div class="stat-label">Payments Processed</div>
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

    <!-- Profile Settings Tabs -->
    <div class="profile-card">
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                    <i class="fas fa-user me-2"></i>Profile Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                    <i class="fas fa-cog me-2"></i>Preferences
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                    <i class="fas fa-shield-alt me-2"></i>Security
                </button>
            </li>
        </ul>

        <div class="tab-content" id="profileTabContent">
            <!-- Profile Information Tab -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                <form method="POST">
                    <div class="form-section">
                        <h4><i class="fas fa-user me-2"></i>Personal Information</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?= htmlspecialchars($admin['full_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($admin['username']) ?>" disabled>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($admin['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($admin['phone'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($admin['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                </form>
            </div>

            <!-- Preferences Tab -->
            <div class="tab-pane fade" id="preferences" role="tabpanel">
                <form method="POST">
                    <div class="form-section">
                        <h4><i class="fas fa-cog me-2"></i>System Preferences</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="Asia/Kolkata" <?= $preferences['timezone'] === 'Asia/Kolkata' ? 'selected' : '' ?>>Asia/Kolkata (IST)</option>
                                        <option value="UTC" <?= $preferences['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                        <option value="America/New_York" <?= $preferences['timezone'] === 'America/New_York' ? 'selected' : '' ?>>America/New_York (EST)</option>
                                        <option value="Europe/London" <?= $preferences['timezone'] === 'Europe/London' ? 'selected' : '' ?>>Europe/London (GMT)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language" class="form-label">Language</label>
                                    <select class="form-select" id="language" name="language">
                                        <option value="en" <?= $preferences['language'] === 'en' ? 'selected' : '' ?>>English</option>
                                        <option value="hi" <?= $preferences['language'] === 'hi' ? 'selected' : '' ?>>हिंदी (Hindi)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4><i class="fas fa-bell me-2"></i>Notification Preferences</h4>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                   <?= $preferences['email_notifications'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="email_notifications">
                                Email Notifications
                            </label>
                            <div class="form-text">Receive email notifications for important events</div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" 
                                   <?= $preferences['sms_notifications'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="sms_notifications">
                                SMS Notifications
                            </label>
                            <div class="form-text">Receive SMS notifications for urgent alerts</div>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_preferences" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Preferences
                    </button>
                </form>
            </div>

            <!-- Security Tab -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="form-section">
                    <h4><i class="fas fa-shield-alt me-2"></i>Security Settings</h4>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Password Security:</strong> For security reasons, password changes are handled separately.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-key text-primary mb-3" style="font-size: 2rem;"></i>
                                    <h5>Change Password</h5>
                                    <p class="text-muted">Update your account password</p>
                                    <a href="change_password.php" class="btn btn-primary">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6><i class="fas fa-clock me-2"></i>Account Information</h6>
                                    <p><strong>Account Created:</strong> <?= date('F j, Y', strtotime($admin['created_at'])) ?></p>
                                    <p><strong>Last Updated:</strong> <?= $admin['updated_at'] ? date('F j, Y', strtotime($admin['updated_at'])) : 'Never' ?></p>
                                    <p><strong>Account Status:</strong> <span class="badge bg-success">Active</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
