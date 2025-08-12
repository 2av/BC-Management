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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My BC Status - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .member-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .status-card {
            border-left: 4px solid #28a745;
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

        <!-- Member Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card member-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-1"><?= htmlspecialchars($member['member_name']) ?></h4>
                                <p class="mb-0">
                                    <strong>Group:</strong> <?= htmlspecialchars($group['group_name']) ?> | 
                                    <strong>Member #:</strong> <?= $member['member_number'] ?> | 
                                    <strong>Monthly Contribution:</strong> <?= formatCurrency($group['monthly_contribution']) ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <div class="d-flex gap-2 justify-content-md-end">
                                    <a href="member_group_view.php" class="btn btn-light btn-sm">
                                        <i class="fas fa-eye"></i> View Full Group
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
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
</body>
</html>
