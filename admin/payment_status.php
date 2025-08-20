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
        body {
            background: linear-gradient(135deg, var(--gray-50) 0%, #f8fafc 100%);
            font-family: var(--font-family-sans);
        }

        /* Payment Status Page Specific Styles */
        .payment-header {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
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
            height: 4px;
            background: linear-gradient(135deg, var(--warning-color) 0%, var(--accent-color) 100%);
        }

        .payment-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
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

        .group-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .group-stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
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
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .payment-header {
                padding: 1.5rem;
                text-align: center;
            }

            .payment-title {
                font-size: 1.75rem;
            }

            .group-stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                                    <div class="group-stat-item">
                                        <div class="group-stat-value"><?= $groupInfo['total_members'] ?></div>
                                        <div class="group-stat-label">Total Members</div>
                                    </div>
                                    <div class="group-stat-item">
                                        <div class="group-stat-value">₹<?= number_format($groupInfo['monthly_contribution'], 0) ?></div>
                                        <div class="group-stat-label">Monthly Contribution</div>
                                    </div>
                                    <div class="group-stat-item">
                                        <div class="group-stat-value">₹<?= number_format($groupInfo['total_monthly_collection'], 0) ?></div>
                                        <div class="group-stat-label">Total Collection</div>
                                    </div>
                                    <div class="group-stat-item">
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
