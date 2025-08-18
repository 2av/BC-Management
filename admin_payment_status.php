<?php
require_once 'config.php';
requireAdminLogin();

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
            $stmt = $pdo->prepare("
                UPDATE member_payments 
                SET payment_status = ?, payment_amount = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $paymentAmount, $paymentId]);
            $success = 'Payment status updated successfully!';
        } catch (Exception $e) {
            $error = 'Failed to update payment status: ' . $e->getMessage();
        }
    }
}

// Get all groups
$pdo = getDB();
$groups = $pdo->query("SELECT id, group_name, status FROM bc_groups ORDER BY created_at DESC")->fetchAll();

// Get payment data for selected group
$payments = [];
$groupInfo = null;
if ($selectedGroupId) {
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = ?");
    $stmt->execute([$selectedGroupId]);
    $groupInfo = $stmt->fetch();
    
    if ($groupInfo) {
        $stmt = $pdo->prepare("
            SELECT 
                mp.id as payment_id,
                mp.member_id,
                mp.month_number,
                mp.payment_amount,
                mp.payment_date,
                mp.payment_status,
                mp.created_at,
                m.member_name,
                m.member_number,
                mb.member_name as winner_name,
                mb.bid_amount,
                mb.gain_per_member
            FROM member_payments mp
            JOIN members m ON mp.member_id = m.id
            LEFT JOIN monthly_bids mb ON mp.group_id = mb.group_id AND mp.month_number = mb.month_number
            WHERE mp.group_id = ?
            ORDER BY mp.month_number, m.member_number
        ");
        $stmt->execute([$selectedGroupId]);
        $payments = $stmt->fetchAll();
    }
}

// Get payment configuration
$configs = getPaymentConfig();
$upiId = !empty($configs['upi_id']) ? $configs['upi_id'] : '9768985225kotak@ybl';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status Management - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-list-check"></i> Payment Status Management
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Group Selection -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form method="GET" class="d-flex">
                                    <select name="group_id" class="form-select me-2" required>
                                        <option value="">Select a Group</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?= $group['id'] ?>" <?= $selectedGroupId == $group['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($group['group_name']) ?> 
                                                (<?= ucfirst($group['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> View
                                    </button>
                                </form>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group">
                                    <a href="admin_payment_config.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-cog"></i> QR Settings
                                    </a>
                                    <a href="test_qr_image_setup.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-test-tube"></i> Test QR
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Current QR Code Info -->
                        <div class="alert alert-info">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6><i class="fas fa-qrcode"></i> Current Payment Setup:</h6>
                                    <p class="mb-0">
                                        <strong>UPI ID:</strong> <?= htmlspecialchars($upiId) ?> | 
                                        <strong>QR Image:</strong> QRCode.jpeg | 
                                        <strong>Status:</strong> <?= isQrPaymentEnabled() ? '<span class="text-success">Enabled</span>' : '<span class="text-danger">Disabled</span>' ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php if (file_exists('QRCode.jpeg')): ?>
                                        <img src="QRCode.jpeg" alt="QR Code" style="max-width: 60px; border: 1px solid #ddd; border-radius: 5px;">
                                    <?php else: ?>
                                        <span class="text-muted">QR Image Missing</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($groupInfo): ?>
                            <!-- Group Info -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-users"></i> <?= htmlspecialchars($groupInfo['group_name']) ?>
                                        <span class="badge bg-<?= $groupInfo['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
                                            <?= ucfirst($groupInfo['status']) ?>
                                        </span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Total Members:</strong> <?= $groupInfo['total_members'] ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Monthly Contribution:</strong> ₹<?= number_format($groupInfo['monthly_contribution'], 2) ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total Collection:</strong> ₹<?= number_format($groupInfo['total_monthly_collection'], 2) ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Start Date:</strong> <?= formatDate($groupInfo['start_date']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Status Table -->
                            <?php if (!empty($payments)): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="fas fa-table"></i> Payment Status 
                                            <span class="badge bg-info ms-2"><?= count($payments) ?> Records</span>
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead class="table-dark">
                                                    <tr>
                                                        <th>Month</th>
                                                        <th>Member</th>
                                                        <th>Winner</th>
                                                        <th>Expected Amount</th>
                                                        <th>Paid Amount</th>
                                                        <th>Status</th>
                                                        <th>Payment Date</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($payments as $payment): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-primary">Month <?= $payment['month_number'] ?></span>
                                                            </td>
                                                            <td>
                                                                <strong><?= htmlspecialchars($payment['member_name']) ?></strong><br>
                                                                <small class="text-muted">#<?= $payment['member_number'] ?></small>
                                                            </td>
                                                            <td>
                                                                <?= $payment['winner_name'] ? htmlspecialchars($payment['winner_name']) : '<span class="text-muted">Not decided</span>' ?>
                                                            </td>
                                                            <td>
                                                                ₹<?= number_format($payment['gain_per_member'] ?? $groupInfo['monthly_contribution'], 2) ?>
                                                            </td>
                                                            <td>
                                                                ₹<?= number_format($payment['payment_amount'], 2) ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= 
                                                                    $payment['payment_status'] === 'paid' ? 'success' : 
                                                                    ($payment['payment_status'] === 'failed' ? 'danger' : 'warning') 
                                                                ?>">
                                                                    <?= ucfirst($payment['payment_status']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?= $payment['payment_date'] ? formatDate($payment['payment_date']) : '<span class="text-muted">-</span>' ?>
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#updateModal<?= $payment['payment_id'] ?>">
                                                                    <i class="fas fa-edit"></i> Update
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
