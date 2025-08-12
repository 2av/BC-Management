<?php
require_once 'config.php';
requireMemberLogin();

if (isset($_GET['logout'])) {
    logout();
}

$member = getCurrentMember();
$groupId = $_SESSION['group_id'];
$group = getGroupById($groupId);
$members = getGroupMembers($groupId);
$monthlyBids = getMonthlyBids($groupId);
$memberPayments = getMemberPayments($groupId);
$memberSummary = getMemberSummary($groupId);

// Get current member's payments
$myPayments = array_filter($memberPayments, fn($p) => $p['member_id'] == $member['id']);

// Get current member's summary
$mySummary = array_filter($memberSummary, fn($s) => $s['member_id'] == $member['id']);
$mySummary = reset($mySummary);

// Organize payments by month
$myPaymentsByMonth = [];
foreach ($myPayments as $payment) {
    $myPaymentsByMonth[$payment['month_number']] = $payment;
}

// Calculate additional statistics
$totalMonths = $group['total_members'];
$paidMonths = count(array_filter($myPayments, fn($p) => $p['payment_status'] === 'paid'));
$pendingMonths = count(array_filter($myPayments, fn($p) => $p['payment_status'] === 'pending'));
$remainingMonths = $totalMonths - $paidMonths - $pendingMonths;

// Get my bid wins
$myBidWins = array_filter($monthlyBids, fn($b) => $b['taken_by_member_id'] == $member['id']);

// Calculate payment progress data for chart
$paymentProgressData = [];
for ($month = 1; $month <= $totalMonths; $month++) {
    $payment = $myPaymentsByMonth[$month] ?? null;
    $paymentProgressData[] = [
        'month' => $month,
        'status' => $payment ? $payment['payment_status'] : 'pending',
        'amount' => $payment ? $payment['payment_amount'] : $group['monthly_contribution'],
        'date' => $payment ? $payment['payment_date'] : null
    ];
}

// Get group completion percentage
$totalBidsCompleted = count($monthlyBids);
$groupProgress = ($totalBidsCompleted / $totalMonths) * 100;

// Calculate my financial position
$totalPaid = $mySummary['total_paid'] ?? 0;
$totalReceived = $mySummary['given_amount'] ?? 0;
$netPosition = $totalReceived - $totalPaid;

// Get recent group activities
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT
        mb.month_number,
        mb.bid_amount,
        mb.net_payable,
        mb.payment_date,
        m.member_name as winner_name,
        m.member_number as winner_number
    FROM monthly_bids mb
    JOIN members m ON mb.taken_by_member_id = m.id
    WHERE mb.group_id = ?
    ORDER BY mb.month_number DESC
    LIMIT 5
");
$stmt->execute([$groupId]);
$recentActivities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My BC Status - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .member-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
        }
        .status-card {
            border-left: 4px solid #28a745;
            transition: transform 0.2s;
        }
        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
        }
        .stat-card-info {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
        }
        .stat-card-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
            margin: 0 auto;
        }
        .progress-ring {
            width: 120px;
            height: 120px;
        }
        .progress-ring-circle {
            transition: stroke-dasharray 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .activity-item {
            border-left: 3px solid #28a745;
            padding-left: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 5px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #28a745;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="member_dashboard.php">
                <i class="fas fa-user-circle"></i> Member Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?= htmlspecialchars($_SESSION['member_name']) ?>
                </span>
                <a class="nav-link text-white" href="member_change_password.php">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a class="nav-link text-white" href="?logout=1">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php $msg = getMessage(); ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Member Profile Header -->
        <div class="card member-card mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="member-avatar">
                            <?= strtoupper(substr($member['member_name'], 0, 1)) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h3 class="mb-1">Welcome, <?= htmlspecialchars($member['member_name']) ?>!</h3>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-1">
                                    <i class="fas fa-users"></i> <strong>Group:</strong> <?= htmlspecialchars($group['group_name']) ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-hashtag"></i> <strong>Member #:</strong> <?= $member['member_number'] ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1">
                                    <i class="fas fa-rupee-sign"></i> <strong>Monthly:</strong> <?= formatCurrency($group['monthly_contribution']) ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-calendar"></i> <strong>Started:</strong> <?= formatDate($group['start_date']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="d-flex flex-column gap-2">
                            <a href="member_group_view.php" class="btn btn-light">
                                <i class="fas fa-eye"></i> View Full Group
                            </a>
                            <a href="member_change_password.php" class="btn btn-outline-light">
                                <i class="fas fa-key"></i> Change Password
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-money-bill fa-2x mb-2"></i>
                        <h4><?= formatCurrency($totalPaid) ?></h4>
                        <p class="mb-0">Total Paid</p>
                        <small><?= $paidMonths ?> of <?= $totalMonths ?> months</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-info">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                        <h4><?= formatCurrency($totalReceived) ?></h4>
                        <p class="mb-0">Amount Received</p>
                        <small><?= count($myBidWins) ?> bid(s) won</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-<?= $netPosition >= 0 ? 'warning' : 'danger' ?>">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                        <h4><?= formatCurrency(abs($netPosition)) ?></h4>
                        <p class="mb-0"><?= $netPosition >= 0 ? 'Net Gain' : 'Net Investment' ?></p>
                        <small><?= $netPosition >= 0 ? 'Profit so far' : 'Amount invested' ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-percentage fa-2x mb-2"></i>
                        <h4><?= number_format(($paidMonths / $totalMonths) * 100, 1) ?>%</h4>
                        <p class="mb-0">Payment Progress</p>
                        <small><?= $remainingMonths ?> months remaining</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Progress Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line"></i> My Payment Progress
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="paymentProgressChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie"></i> Group Progress
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="chart-container">
                            <canvas id="groupProgressChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6><?= number_format($groupProgress, 1) ?>% Complete</h6>
                            <p class="text-muted mb-0"><?= $totalBidsCompleted ?> of <?= $totalMonths ?> months completed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Overview and Recent Activities -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-wallet"></i> My Financial Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="financialChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h6 class="text-success"><?= formatCurrency($totalPaid) ?></h6>
                                    <small class="text-muted">Paid</small>
                                </div>
                                <div class="col-4">
                                    <h6 class="text-info"><?= formatCurrency($totalReceived) ?></h6>
                                    <small class="text-muted">Received</small>
                                </div>
                                <div class="col-4">
                                    <h6 class="text-<?= $netPosition >= 0 ? 'warning' : 'danger' ?>">
                                        <?= formatCurrency(abs($netPosition)) ?>
                                    </h6>
                                    <small class="text-muted"><?= $netPosition >= 0 ? 'Gain' : 'Investment' ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> Recent Group Activities
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 350px; overflow-y: auto;">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-trophy text-warning"></i>
                                                Month <?= $activity['month_number'] ?> Winner
                                            </h6>
                                            <p class="mb-1">
                                                <strong><?= htmlspecialchars($activity['winner_name']) ?></strong>
                                                (Member #<?= $activity['winner_number'] ?>)
                                            </p>
                                            <small class="text-muted">
                                                Bid: <?= formatCurrency($activity['bid_amount']) ?> |
                                                Received: <?= formatCurrency($activity['net_payable']) ?>
                                            </small>
                                        </div>
                                        <small class="text-muted">
                                            <?= formatDate($activity['payment_date']) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Bids Won Section -->
        <?php if (!empty($myBidWins)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy"></i> My Winning Bids
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($myBidWins as $bid): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                                                <h5>Month <?= $bid['month_number'] ?></h5>
                                                <p class="mb-1">
                                                    <strong>Bid Amount:</strong> <?= formatCurrency($bid['bid_amount']) ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Received:</strong> <?= formatCurrency($bid['net_payable']) ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?= formatDate($bid['payment_date']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment Status Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-rupee-sign fa-2x text-success mb-2"></i>
                        <h4><?= $mySummary ? formatCurrency($mySummary['total_paid']) : '₹0' ?></h4>
                        <small class="text-muted">Total Paid</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-gift fa-2x text-primary mb-2"></i>
                        <h4><?= $mySummary ? formatCurrency($mySummary['given_amount']) : '₹0' ?></h4>
                        <small class="text-muted">Amount Received</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x <?= ($mySummary && $mySummary['profit'] >= 0) ? 'text-success' : 'text-danger' ?> mb-2"></i>
                        <h4 class="<?= ($mySummary && $mySummary['profit'] >= 0) ? 'text-success' : 'text-danger' ?>">
                            <?= $mySummary ? formatCurrency($mySummary['profit']) : '₹0' ?>
                        </h4>
                        <small class="text-muted">Profit/Loss</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                        <h4><?= count($myPayments) ?> / <?= $group['total_members'] ?></h4>
                        <small class="text-muted">Months Paid</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Payment History -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> My Payment History
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Payment Amount</th>
                                        <th>Payment Date</th>
                                        <th>Status</th>
                                        <th>Month Winner</th>
                                        <th>Bid Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                        <?php
                                        $payment = $myPaymentsByMonth[$i] ?? null;
                                        $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                        $bid = reset($bid);
                                        ?>
                                        <tr>
                                            <td><strong>Month <?= $i ?></strong></td>
                                            <td>
                                                <?php if ($payment): ?>
                                                    <?= formatCurrency($payment['payment_amount']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <?= $bid ? formatCurrency($bid['gain_per_member']) : formatCurrency($group['monthly_contribution']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $payment && $payment['payment_date'] ? formatDate($payment['payment_date']) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if ($payment): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php elseif ($bid): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not Started</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $bid ? htmlspecialchars($bid['member_name']) : '-' ?>
                                                <?php if ($bid && $bid['taken_by_member_id'] == $member['id']): ?>
                                                    <span class="badge bg-primary ms-1">You Won!</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $bid ? formatCurrency($bid['bid_amount']) : '-' ?>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group Summary -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle"></i> Group Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Group Name:</strong></td>
                                <td><?= htmlspecialchars($group['group_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Total Members:</strong></td>
                                <td><?= $group['total_members'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Monthly Contribution:</strong></td>
                                <td><?= formatCurrency($group['monthly_contribution']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Total Collection:</strong></td>
                                <td><?= formatCurrency($group['total_monthly_collection']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Start Date:</strong></td>
                                <td><?= formatDate($group['start_date']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($group['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-pie"></i> Progress Overview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <small>Completed Months</small>
                                <small><?= count($monthlyBids) ?> / <?= $group['total_members'] ?></small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= (count($monthlyBids) / $group['total_members']) * 100 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <small>My Payments</small>
                                <small><?= count($myPayments) ?> / <?= count($monthlyBids) ?></small>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: <?= count($monthlyBids) > 0 ? (count($myPayments) / count($monthlyBids)) * 100 : 0 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <?php if (count($monthlyBids) < $group['total_members']): ?>
                                    <?= $group['total_members'] - count($monthlyBids) ?> months remaining
                                <?php else: ?>
                                    BC Group Completed!
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart.js configuration
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.color = '#666';

        // Payment Progress Chart
        const paymentData = <?= json_encode($paymentProgressData) ?>;
        const paymentCtx = document.getElementById('paymentProgressChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'bar',
            data: {
                labels: paymentData.map(p => `Month ${p.month}`),
                datasets: [{
                    label: 'Payment Amount',
                    data: paymentData.map(p => p.amount),
                    backgroundColor: paymentData.map(p => {
                        switch(p.status) {
                            case 'paid': return '#28a745';
                            case 'pending': return '#ffc107';
                            default: return '#e9ecef';
                        }
                    }),
                    borderColor: paymentData.map(p => {
                        switch(p.status) {
                            case 'paid': return '#1e7e34';
                            case 'pending': return '#e0a800';
                            default: return '#dee2e6';
                        }
                    }),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const payment = paymentData[context.dataIndex];
                                return `Status: ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Group Progress Pie Chart
        const groupProgress = <?= $groupProgress ?>;
        const groupCtx = document.getElementById('groupProgressChart').getContext('2d');
        new Chart(groupCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Remaining'],
                datasets: [{
                    data: [groupProgress, 100 - groupProgress],
                    backgroundColor: ['#28a745', '#e9ecef'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Financial Overview Chart
        const totalPaid = <?= $totalPaid ?>;
        const totalReceived = <?= $totalReceived ?>;
        const financialCtx = document.getElementById('financialChart').getContext('2d');
        new Chart(financialCtx, {
            type: 'doughnut',
            data: {
                labels: ['Amount Paid', 'Amount Received'],
                datasets: [{
                    data: [totalPaid, totalReceived],
                    backgroundColor: ['#dc3545', '#17a2b8'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₹' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Add hover effects to cards
        document.querySelectorAll('.status-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            });
        });

        // Auto-refresh dashboard every 2 minutes
        setInterval(() => {
            console.log('Auto-refreshing member dashboard...');
            // In a real application, you'd fetch updated data via AJAX here
        }, 120000); // 2 minutes

        // Add smooth scrolling for any anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation for buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.href && !this.href.includes('#')) {
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.add('fa-spin');
                    }
                }
            });
        });

        // Initialize tooltips if any
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
