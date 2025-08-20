<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('member');

// Get current member information
$member = getCurrentMember();
if (!$member) {
    setMessage('Member session expired. Please login again.', 'error');
    redirect('../auth/member_login.php');
}

$pdo = getDB();

// Get all groups this member belongs to
$memberGroups = getMemberGroups($member['id']);

// Get current group information
$currentGroupId = $_SESSION['group_id'];
$currentGroup = null;
foreach ($memberGroups as $group) {
    if ($group['id'] == $currentGroupId) {
        $currentGroup = $group;
        break;
    }
}

// If current group not found, use first available group
if (!$currentGroup && !empty($memberGroups)) {
    $currentGroup = $memberGroups[0];
    $currentGroupId = $currentGroup['id'];
    $_SESSION['group_id'] = $currentGroupId;
}

// Member statistics
$totalGroups = count($memberGroups);
$totalPaidAmount = 0;
$totalReceivedAmount = 0;
$totalPendingPayments = 0;

// Calculate member's financial summary across all groups
foreach ($memberGroups as $group) {
    $groupId = $group['id'];

    // Get member's payments for this group
    $stmt = $pdo->prepare("
        SELECT SUM(CASE WHEN payment_status = 'paid' THEN payment_amount ELSE 0 END) as paid_amount,
               COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count
        FROM member_payments
        WHERE member_id = ? AND group_id = ?
    ");
    $stmt->execute([$group['member_id'], $groupId]);
    $paymentSummary = $stmt->fetch();

    $totalPaidAmount += $paymentSummary['paid_amount'] ?? 0;
    $totalPendingPayments += $paymentSummary['pending_count'] ?? 0;

    // Get member's received amount (if they won any month)
    $stmt = $pdo->prepare("
        SELECT SUM(bid_amount) as received_amount
        FROM monthly_bids
        WHERE group_id = ? AND taken_by_member_id = ?
    ");
    $stmt->execute([$groupId, $group['member_id']]);
    $receivedSummary = $stmt->fetch();

    $totalReceivedAmount += $receivedSummary['received_amount'] ?? 0;
}

// Get recent payments across all groups
$stmt = $pdo->prepare("
    SELECT mp.*, g.group_name, g.monthly_contribution
    FROM member_payments mp
    JOIN bc_groups g ON mp.group_id = g.id
    JOIN members m ON mp.member_id = m.id
    WHERE m.member_name = ? AND mp.payment_status = 'paid'
    ORDER BY mp.payment_date DESC
    LIMIT 5
");
$stmt->execute([$member['member_name']]);
$recentPayments = $stmt->fetchAll();

// Get upcoming payments (pending)
$stmt = $pdo->prepare("
    SELECT mp.*, g.group_name, g.monthly_contribution
    FROM member_payments mp
    JOIN bc_groups g ON mp.group_id = g.id
    JOIN members m ON mp.member_id = m.id
    WHERE m.member_name = ? AND mp.payment_status = 'pending'
    ORDER BY mp.month_number ASC
    LIMIT 5
");
$stmt->execute([$member['member_name']]);
$upcomingPayments = $stmt->fetchAll();

// Set page title for the header
$page_title = 'Member Dashboard';

// Include the member header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
        /* Professional Dashboard Styles */
        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            margin-bottom: var(--space-6);
            transition: var(--transition-normal);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(20px);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stats-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-2xl);
        }

        .stats-card.groups {
            background: var(--primary-gradient);
            color: white;
        }

        .stats-card.payments {
            background: var(--success-gradient);
            color: white;
        }

        .stats-card.received {
            background: var(--warning-gradient);
            color: var(--gray-800);
        }

        .stats-card.pending {
            background: var(--danger-gradient);
            color: white;
        }

        .stats-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: var(--space-2);
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-label {
            font-size: 1.1rem;
            opacity: 0.95;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-icon {
            font-size: 4rem;
            opacity: 0.2;
            position: absolute;
            right: var(--space-6);
            top: var(--space-6);
            transform: rotate(-15deg);
        }

        .member-actions {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            box-shadow: var(--shadow-xl);
            margin-bottom: var(--space-6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .action-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-xl);
            padding: var(--space-4) var(--space-6);
            margin: var(--space-2);
            transition: var(--transition-normal);
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition-normal);
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: var(--shadow-xl);
            color: white;
        }

        .recent-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            box-shadow: var(--shadow-xl);
            margin-bottom: var(--space-6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .item-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.9) 100%);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-4);
            border: 1px solid rgba(255,255,255,0.3);
            transition: var(--transition-normal);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
        }

        .item-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-xl);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .welcome-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--radius-2xl);
            padding: var(--space-10);
            margin-bottom: var(--space-8);
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .group-selector {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-xl);
            padding: var(--space-5);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .section-title i {
            background: var(--primary-gradient);
            color: white;
            padding: var(--space-2);
            border-radius: var(--radius-lg);
            font-size: 1.2rem;
        }
            border-left: 4px solid var(--client-primary);
            transition: all 0.3s ease;
        }

    </style>

<!-- Page content starts here -->

        <!-- Welcome Section -->
        <div class="welcome-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">Welcome, <?= htmlspecialchars($member['member_name']) ?>!</h2>
                    <p class="mb-2">Member Dashboard</p>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-layer-group me-2"></i>Groups: <?= $totalGroups ?>
                        <?php if ($currentGroup): ?>
                            <i class="fas fa-users ms-3 me-2"></i>Current Group: <?= htmlspecialchars($currentGroup['group_name']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-user-friends fa-4x opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Group Selector -->
        <?php if (count($memberGroups) > 1): ?>
        <div class="group-selector">
            <label for="groupSelect" class="form-label"><strong>Select Group:</strong></label>
            <select class="form-select" id="groupSelect" onchange="switchGroup(this.value)">
                <?php foreach ($memberGroups as $group): ?>
                    <option value="<?= $group['id'] ?>" <?= $group['id'] == $currentGroupId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($group['group_name']) ?> (Member #<?= $group['member_number'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card groups position-relative">
                    <i class="fas fa-layer-group stats-icon"></i>
                    <div class="stats-number"><?= $totalGroups ?></div>
                    <div class="stats-label">My Groups</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card payments position-relative">
                    <i class="fas fa-rupee-sign stats-icon"></i>
                    <div class="stats-number">₹<?= number_format($totalPaidAmount) ?></div>
                    <div class="stats-label">Total Paid</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card received position-relative">
                    <i class="fas fa-hand-holding-usd stats-icon"></i>
                    <div class="stats-number">₹<?= number_format($totalReceivedAmount) ?></div>
                    <div class="stats-label">Total Received</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card pending position-relative">
                    <i class="fas fa-clock stats-icon"></i>
                    <div class="stats-number"><?= $totalPendingPayments ?></div>
                    <div class="stats-label">Pending Payments</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Member Actions -->
            <div class="col-md-4">
                <div class="member-actions">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>

                    <a href="group_view.php" class="action-btn w-100">
                        <i class="fas fa-layer-group me-2"></i>View My Groups
                    </a>

                    <a href="bidding.php" class="action-btn w-100">
                        <i class="fas fa-gavel me-2"></i>Participate in Bidding
                    </a>

                    <a href="payment.php" class="action-btn w-100">
                        <i class="fas fa-credit-card me-2"></i>Make Payment
                    </a>

                    <a href="edit_profile.php" class="action-btn w-100">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                    </a>

                    <a href="change_password.php" class="action-btn w-100">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="col-md-4">
                <div class="recent-card">
                    <h5 class="mb-3">
                        <i class="fas fa-credit-card me-2 text-success"></i>Recent Payments
                    </h5>

                    <?php if (empty($recentPayments)): ?>
                        <p class="text-muted text-center py-3">No payments made yet.</p>
                        <div class="text-center">
                            <a href="payment.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-credit-card me-2"></i>Make First Payment
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="item-card">
                                <h6 class="mb-1">Month <?= $payment['month_number'] ?> - <?= htmlspecialchars($payment['group_name']) ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-rupee-sign me-1"></i>₹<?= number_format($payment['payment_amount']) ?>
                                    <i class="fas fa-calendar ms-2 me-1"></i><?= date('M j, Y', strtotime($payment['payment_date'])) ?>
                                    <span class="badge bg-success ms-2">Paid</span>
                                </small>
                            </div>
                        <?php endforeach; ?>

                        <div class="text-center mt-3">
                            <a href="payment.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-eye me-2"></i>View All Payments
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Payments -->
            <div class="col-md-4">
                <div class="recent-card">
                    <h5 class="mb-3">
                        <i class="fas fa-clock me-2 text-warning"></i>Upcoming Payments
                    </h5>

                    <?php if (empty($upcomingPayments)): ?>
                        <p class="text-muted text-center py-3">No pending payments.</p>
                        <div class="text-center">
                            <a href="group_view.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-layer-group me-2"></i>View Groups
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingPayments as $payment): ?>
                            <div class="item-card">
                                <h6 class="mb-1">Month <?= $payment['month_number'] ?> - <?= htmlspecialchars($payment['group_name']) ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-rupee-sign me-1"></i>₹<?= number_format($payment['monthly_contribution']) ?>
                                    <span class="badge bg-warning ms-2">Pending</span>
                                </small>
                            </div>
                        <?php endforeach; ?>

                        <div class="text-center mt-3">
                            <a href="payment.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-credit-card me-2"></i>Make Payment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<?php require_once 'includes/footer.php'; ?>
