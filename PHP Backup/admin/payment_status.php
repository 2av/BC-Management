<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
require_once '../common/qr_utils.php';
checkRole('admin');

$error = '';
$success = '';
$selectedGroupId = (int)($_GET['group_id'] ?? $_POST['group_id'] ?? 0);

// Handle payment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $paymentId = (int)$_POST['payment_id'];
    $newStatus = $_POST['payment_status'];
    $paymentAmount = (float)$_POST['payment_amount'];

    if (in_array($newStatus, ['pending', 'paid', 'failed'])) {
        try {
            $pdo = getDB();

            // Check if member_payments table has updated_at column
            $columns = $pdo->query("SHOW COLUMNS FROM member_payments LIKE 'updated_at'")->fetch();

            if ($columns) {
                $stmt = $pdo->prepare("
                    UPDATE member_payments
                    SET payment_status = ?, payment_amount = ?, updated_at = NOW()
                    WHERE id = ?
                ");
            } else {
                $stmt = $pdo->prepare("
                    UPDATE member_payments
                    SET payment_status = ?, payment_amount = ?
                    WHERE id = ?
                ");
            }
            $stmt->execute([$newStatus, $paymentAmount, $paymentId]);
            $success = 'Payment status updated successfully!';
        } catch (Exception $e) {
            $error = 'Failed to update payment status: ' . $e->getMessage();
        }
    }
}

// Get database connection and ensure member_payments table exists
$pdo = getDB();

// Check if member_payments table exists, create if missing
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'member_payments'")->fetch();
    if (!$tableExists) {
        $createTableSQL = "
        CREATE TABLE member_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            member_id INT NOT NULL,
            month_number INT NOT NULL,
            payment_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
            payment_date DATE NULL,
            payment_method VARCHAR(50) DEFAULT 'upi',
            transaction_id VARCHAR(100) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES bc_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            UNIQUE KEY unique_payment_per_member_month (group_id, member_id, month_number)
        )";
        $pdo->exec($createTableSQL);
        $success = 'member_payments table created successfully!';
    }
} catch (Exception $e) {
    $error = 'Database setup error: ' . $e->getMessage();
}

// Get all groups
$groups = $pdo->query("SELECT id, group_name, status FROM bc_groups ORDER BY created_at DESC")->fetchAll();

// Get payment data for selected group
$payments = [];
$groupInfo = null;
if ($selectedGroupId) {
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = ?");
    $stmt->execute([$selectedGroupId]);
    $groupInfo = $stmt->fetch();
    
    if ($groupInfo) {
        try {
            // Check if created_at column exists
            $hasCreatedAt = $pdo->query("SHOW COLUMNS FROM member_payments LIKE 'created_at'")->fetch();

            $createdAtField = $hasCreatedAt ? 'mp.created_at' : 'NULL as created_at';

            $stmt = $pdo->prepare("
                SELECT
                    mp.id as payment_id,
                    mp.member_id,
                    mp.month_number,
                    mp.payment_amount,
                    mp.payment_date,
                    mp.payment_status,
                    $createdAtField,
                    m.member_name,
                    m.member_number,
                    winner.member_name as winner_name,
                    mb.bid_amount,
                    mb.gain_per_member
                FROM member_payments mp
                JOIN members m ON mp.member_id = m.id
                LEFT JOIN monthly_bids mb ON mp.group_id = mb.group_id AND mp.month_number = mb.month_number
                LEFT JOIN members winner ON mb.taken_by_member_id = winner.id
                WHERE mp.group_id = ?
                ORDER BY mp.month_number, m.member_number
            ");
            $stmt->execute([$selectedGroupId]);
            $payments = $stmt->fetchAll();

            // If no payments exist but group has members and bids, create payment records
            if (empty($payments)) {
                $memberStmt = $pdo->prepare("SELECT id, member_name FROM members WHERE group_id = ? AND status = 'active'");
                $memberStmt->execute([$selectedGroupId]);
                $members = $memberStmt->fetchAll();

                $bidStmt = $pdo->prepare("SELECT month_number, COALESCE(gain_per_member, 0) as gain_per_member FROM monthly_bids WHERE group_id = ?");
                $bidStmt->execute([$selectedGroupId]);
                $bids = $bidStmt->fetchAll();

                if (!empty($members) && !empty($bids)) {
                    // Create payment records for each member and month
                    $insertStmt = $pdo->prepare("
                        INSERT IGNORE INTO member_payments (group_id, member_id, month_number, payment_amount, payment_status)
                        VALUES (?, ?, ?, ?, 'pending')
                    ");

                    foreach ($members as $member) {
                        foreach ($bids as $bid) {
                            $insertStmt->execute([
                                $selectedGroupId,
                                $member['id'],
                                $bid['month_number'],
                                $bid['gain_per_member']
                            ]);
                        }
                    }

                    // Re-fetch payments after creating them
                    $stmt->execute([$selectedGroupId]);
                    $payments = $stmt->fetchAll();
                }
            }
        } catch (Exception $e) {
            $error = 'Error fetching payment data: ' . $e->getMessage();
            $payments = [];
        }
    }
}

// Get payment configuration
$configs = getPaymentConfig();
$upiId = !empty($configs['upi_id']) ? $configs['upi_id'] : '9768985225kotak@ybl';
// Set page title for the header
$page_title = 'Payment Status Management';

// Include the new header
require_once 'includes/header.php';
?>
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-dark: #2563eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-2xl: 20px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition-fast: 0.15s ease-in-out;
            --transition-normal: 0.3s ease-in-out;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
        }

        /* Enhanced Header */
        .payment-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .payment-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--info-color) 50%, var(--success-color) 100%);
        }

        .payment-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.05) 0%, transparent 70%);
            z-index: 0;
        }

        .payment-header > * {
            position: relative;
            z-index: 1;
        }

        .payment-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--gray-900) 0%, var(--primary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .payment-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
        }

        .group-selector {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
        }

        .qr-info-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(99, 102, 241, 0.05) 100%);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .qr-info-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--info-color);
            margin-bottom: 1rem;
        }

        .qr-image {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-md);
            border: 2px solid var(--gray-300);
            object-fit: cover;
        }

        .group-info-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
        }

        .group-info-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .group-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .group-stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
        }

        .group-stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            text-align: center;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .group-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--stat-gradient, linear-gradient(135deg, var(--primary-color) 0%, var(--info-color) 100%));
        }

        .group-stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .group-stat-card:nth-child(1) { --stat-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .group-stat-card:nth-child(2) { --stat-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .group-stat-card:nth-child(3) { --stat-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .group-stat-card:nth-child(4) { --stat-gradient: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

        .group-stat-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .group-stat-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }

        .group-stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .group-stat-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payments-table-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .payments-table-header {
            background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .payments-table-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0;
        }

        .payment-row {
            transition: all var(--transition-normal);
        }

        .payment-row:hover {
            background-color: var(--gray-50);
            transform: scale(1.01);
        }

        .payment-status-badge {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .payment-status-paid {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .payment-status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .payment-status-failed {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .update-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-normal);
        }

        .update-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Enhanced Modern Components */
        .btn-outline-modern {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            transition: all var(--transition-normal);
            backdrop-filter: blur(10px);
        }

        .btn-outline-modern:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            transition: all var(--transition-normal);
            box-shadow: var(--shadow-md);
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .form-control-modern {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all var(--transition-normal);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .form-control-modern:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .alert-modern {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
        }

        .alert-success-modern {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger-modern {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .badge-primary-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .badge-info-modern {
            background: linear-gradient(135deg, var(--info-color) 0%, #0891b2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .badge-success-modern {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .badge-secondary-modern {
            background: linear-gradient(135deg, var(--gray-500) 0%, var(--gray-600) 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .table-modern {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-modern thead th {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
            color: var(--gray-700);
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1.5rem 1rem;
            border-bottom: 2px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-modern tbody td {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        /* Animation Classes */
        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }

        .animate-slideInRight {
            animation: slideInRight 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .payment-header {
                padding: 1.5rem;
                text-align: center;
            }

            .payment-title {
                font-size: 2rem;
            }

            .group-stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .group-stat-item {
                padding: 1rem 0.5rem;
            }

            .payments-table-header {
                padding: 1rem;
            }

            .table-modern thead th,
            .table-modern tbody td {
                padding: 1rem 0.5rem;
                font-size: 0.875rem;
            }

            .btn-outline-modern,
            .btn-primary-modern {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            .payment-title {
                font-size: 1.75rem;
            }

            .group-stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

<!-- Page content starts here -->
<div class="container mt-4">
        <!-- Payment Status Header -->
        <div class="payment-header animate-fadeInUp">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="payment-title">
                        <i class="fas fa-credit-card text-gradient-primary me-3"></i>
                        Payment Status Management
                    </h1>
                    <p class="payment-subtitle mb-0">
                        Monitor and manage payment status for all group members
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="admin_payment_config.php" class="btn btn-outline-modern">
                        <i class="fas fa-cog me-2"></i>QR Settings
                    </a>
                    <a href="test_qr_image_setup.php" class="btn btn-outline-modern">
                        <i class="fas fa-test-tube me-2"></i>Test QR
                    </a>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert-modern alert-danger-modern">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-modern alert-success-modern">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Group Selection -->
        <div class="group-selector animate-slideInRight">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="mb-1">
                        <i class="fas fa-filter me-2"></i>Select Group
                    </h5>
                    <p class="text-muted mb-0">Choose a group to view payment status</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="GET" class="d-flex">
                        <select name="group_id" class="form-control-modern me-2" required style="min-width: 250px;">
                            <option value="">Select a Group</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= $group['id'] ?>" <?= $selectedGroupId == $group['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($group['group_name']) ?>
                                    (<?= ucfirst($group['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary-modern">
                            <i class="fas fa-search me-1"></i>View
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Current QR Code Info -->
        <div class="qr-info-card animate-fadeInUp">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="qr-info-title">
                        <i class="fas fa-qrcode me-2"></i>Current Payment Setup
                    </h6>
                    <div class="row">
                        <div class="col-md-4">
                            <strong>UPI ID:</strong> <?= htmlspecialchars($upiId) ?>
                        </div>
                        <div class="col-md-4">
                            <strong>QR Image:</strong> QRCode.jpeg
                        </div>
                        <div class="col-md-4">
                            <strong>Status:</strong>
                            <?= isQrPaymentEnabled() ? '<span class="badge badge-success-modern">Enabled</span>' : '<span class="badge badge-danger-modern">Disabled</span>' ?>
                        </div>
                    </div>
                </div>
                <div>
                    <?php if (file_exists('QRCode.jpeg')): ?>
                        <img src="QRCode.jpeg" alt="QR Code" class="qr-image">
                    <?php else: ?>
                        <div class="qr-image d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-image text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
                        
                        <?php if ($groupInfo): ?>
                            <!-- Group Info -->
                            <div class="group-info-card animate-fadeInUp">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h2 class="group-info-title">
                                            <i class="fas fa-users me-2"></i>
                                            <?= htmlspecialchars($groupInfo['group_name']) ?>
                                        </h2>
                                    </div>
                                    <div>
                                        <span class="badge badge-<?= $groupInfo['status'] === 'active' ? 'success' : 'secondary' ?>-modern">
                                            <i class="fas fa-<?= $groupInfo['status'] === 'active' ? 'play' : 'check' ?> me-1"></i>
                                            <?= ucfirst($groupInfo['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="group-stats-grid">
                                    <div class="group-stat-card">
                                        <div class="group-stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="group-stat-value"><?= $groupInfo['total_members'] ?></div>
                                        <div class="group-stat-label">Total Members</div>
                                    </div>
                                    <div class="group-stat-card">
                                        <div class="group-stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                            <i class="fas fa-coins"></i>
                                        </div>
                                        <div class="group-stat-value">₹<?= number_format($groupInfo['monthly_contribution'], 0) ?></div>
                                        <div class="group-stat-label">Monthly Contribution</div>
                                    </div>
                                    <div class="group-stat-card">
                                        <div class="group-stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                            <i class="fas fa-wallet"></i>
                                        </div>
                                        <div class="group-stat-value">₹<?= number_format($groupInfo['total_monthly_collection'], 0) ?></div>
                                        <div class="group-stat-label">Total Collection</div>
                                    </div>
                                    <div class="group-stat-card">
                                        <div class="group-stat-icon" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="group-stat-value"><?= formatDate($groupInfo['start_date']) ?></div>
                                        <div class="group-stat-label">Start Date</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Status Table -->
                            <?php if (!empty($payments)): ?>
                                <div class="payments-table-container animate-slideInRight">
                                    <div class="payments-table-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h3 class="payments-table-title">
                                                    <i class="fas fa-table me-2"></i>Payment Status
                                                </h3>
                                                <p class="text-muted mb-0"><?= count($payments) ?> payment records</p>
                                            </div>
                                            <div>
                                                <span class="badge badge-info-modern">
                                                    <i class="fas fa-list me-1"></i><?= count($payments) ?> Records
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-0">
                                        <div class="table-responsive">
                                            <table class="table-modern mb-0">
                                                <thead>
                                                    <tr>
                                                        <th><i class="fas fa-calendar me-1"></i>Month</th>
                                                        <th><i class="fas fa-user me-1"></i>Member</th>
                                                        <th><i class="fas fa-trophy me-1"></i>Winner</th>
                                                        <th><i class="fas fa-coins me-1"></i>Expected Amount</th>
                                                        <th><i class="fas fa-money-bill me-1"></i>Paid Amount</th>
                                                        <th><i class="fas fa-check-circle me-1"></i>Status</th>
                                                        <th><i class="fas fa-calendar-check me-1"></i>Payment Date</th>
                                                        <th><i class="fas fa-cog me-1"></i>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($payments as $payment): ?>
                                                        <tr class="payment-row">
                                                            <td>
                                                                <span class="badge badge-primary-modern">
                                                                    <i class="fas fa-calendar me-1"></i>Month <?= $payment['month_number'] ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold"><?= htmlspecialchars($payment['member_name']) ?></div>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-id-badge me-1"></i>#<?= $payment['member_number'] ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <?php if ($payment['winner_name']): ?>
                                                                    <div class="fw-bold text-success">
                                                                        <i class="fas fa-crown me-1"></i>
                                                                        <?= htmlspecialchars($payment['winner_name']) ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">
                                                                        <i class="fas fa-clock me-1"></i>Not decided
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold text-primary">
                                                                    ₹<?= number_format($payment['gain_per_member'] ?? $groupInfo['monthly_contribution'], 0) ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold text-success">
                                                                    ₹<?= number_format($payment['payment_amount'], 0) ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="payment-status-badge payment-status-<?= $payment['payment_status'] ?>">
                                                                    <i class="fas fa-<?=
                                                                        $payment['payment_status'] === 'paid' ? 'check-circle' :
                                                                        ($payment['payment_status'] === 'failed' ? 'times-circle' : 'clock')
                                                                    ?> me-1"></i>
                                                                    <?= ucfirst($payment['payment_status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($payment['payment_date']): ?>
                                                                    <div class="fw-bold"><?= formatDate($payment['payment_date']) ?></div>
                                                                <?php else: ?>
                                                                    <span class="text-muted">
                                                                        <i class="fas fa-minus me-1"></i>Not set
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="update-btn"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#updateModal<?= $payment['payment_id'] ?>">
                                                                    <i class="fas fa-edit me-1"></i>Update
                                                                </button>
                                                            </td>
                                                        </tr>
                                                        
                                                        <!-- Update Modal -->
                                                        <div class="modal fade" id="updateModal<?= $payment['payment_id'] ?>" tabindex="-1">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <form method="POST">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title">Update Payment Status</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <input type="hidden" name="payment_id" value="<?= $payment['payment_id'] ?>">
                                                                            <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">
                                                                            
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Member</label>
                                                                                <input type="text" class="form-control" 
                                                                                       value="<?= htmlspecialchars($payment['member_name']) ?> - Month <?= $payment['month_number'] ?>" 
                                                                                       readonly>
                                                                            </div>
                                                                            
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Payment Amount</label>
                                                                                <input type="number" step="0.01" class="form-control" name="payment_amount" 
                                                                                       value="<?= $payment['payment_amount'] ?>" required>
                                                                            </div>
                                                                            
                                                                            <div class="mb-3">
                                                                                <label class="form-label">Payment Status</label>
                                                                                <select name="payment_status" class="form-select" required>
                                                                                    <option value="pending" <?= $payment['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                                    <option value="paid" <?= $payment['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                                                    <option value="failed" <?= $payment['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                            <button type="submit" name="update_payment_status" class="btn btn-primary">
                                                                                <i class="fas fa-save"></i> Update Status
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    No payment records found for this group. Payments are created when monthly bids are added.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-arrow-up"></i>
                                Please select a group above to view payment status.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>
