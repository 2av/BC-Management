<?php
require_once 'config.php';
requireAdminLogin();

$memberId = (int)($_GET['id'] ?? 0);

if ($memberId <= 0) {
    redirect('admin_members.php');
}

$pdo = getDB();

// Get member details with group info
$stmt = $pdo->prepare("
    SELECT m.*, g.group_name, g.monthly_contribution, g.total_members, g.status as group_status,
           ms.total_paid, ms.given_amount, ms.profit
    FROM members m 
    JOIN bc_groups g ON m.group_id = g.id 
    LEFT JOIN member_summary ms ON m.id = ms.member_id
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    setMessage('Member not found.', 'error');
    redirect('admin_members.php');
}

// Get member's payment history
$stmt = $pdo->prepare("
    SELECT mp.*, mb.month_number, mb.bid_amount, mb.net_payable
    FROM member_payments mp
    LEFT JOIN monthly_bids mb ON mp.group_id = mb.group_id AND mp.month_number = mb.month_number
    WHERE mp.member_id = ?
    ORDER BY mp.month_number
");
$stmt->execute([$memberId]);
$payments = $stmt->fetchAll();

// Get bids won by this member
$stmt = $pdo->prepare("
    SELECT mb.*, m.member_name as winner_name
    FROM monthly_bids mb
    JOIN members m ON mb.taken_by_member_id = m.id
    WHERE mb.taken_by_member_id = ?
    ORDER BY mb.month_number
");
$stmt->execute([$memberId]);
$bidsWon = $stmt->fetchAll();

// Calculate statistics
$totalMonths = $member['total_members'];
$paidMonths = count(array_filter($payments, fn($p) => $p['payment_status'] === 'paid'));
$pendingMonths = count(array_filter($payments, fn($p) => $p['payment_status'] === 'pending'));
$bidsWonCount = count($bidsWon);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Details - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins"></i> <?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="admin_members.php">
                    <i class="fas fa-arrow-left"></i> Back to Members
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Member Header -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <div class="member-avatar mx-auto">
                            <?= strtoupper(substr($member['member_name'], 0, 1)) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h2 class="mb-1"><?= htmlspecialchars($member['member_name']) ?></h2>
                        <p class="text-muted mb-2">
                            <i class="fas fa-users"></i> <?= htmlspecialchars($member['group_name']) ?> 
                            <span class="badge bg-primary ms-2">#<?= $member['member_number'] ?></span>
                        </p>
                        <div class="d-flex gap-3">
                            <?php if ($member['phone']): ?>
                                <span><i class="fas fa-phone text-success"></i> <?= htmlspecialchars($member['phone']) ?></span>
                            <?php endif; ?>
                            <?php if ($member['email']): ?>
                                <span><i class="fas fa-envelope text-info"></i> <?= htmlspecialchars($member['email']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="admin_edit_member.php?id=<?= $member['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Edit Member
                            </a>
                            <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?> fs-6">
                                <?= ucfirst($member['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-money-bill fa-2x mb-2"></i>
                        <h4><?= formatCurrency($member['total_paid'] ?: 0) ?></h4>
                        <p class="mb-0">Total Paid</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-success">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                        <h4><?= formatCurrency($member['given_amount'] ?: 0) ?></h4>
                        <p class="mb-0">Amount Received</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-<?= ($member['profit'] ?: 0) >= 0 ? 'info' : 'warning' ?>">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                        <h4><?= formatCurrency($member['profit'] ?: 0) ?></h4>
                        <p class="mb-0">Profit/Loss</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-warning">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-trophy fa-2x mb-2"></i>
                        <h4><?= $bidsWonCount ?></h4>
                        <p class="mb-0">Bids Won</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Payment History -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> Payment History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($payments)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Bid Info</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary">Month <?= $payment['month_number'] ?></span>
                                                </td>
                                                <td><?= formatCurrency($payment['payment_amount']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $payment['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                        <?= ucfirst($payment['payment_status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= $payment['payment_date'] ? formatDate($payment['payment_date']) : '-' ?>
                                                </td>
                                                <td>
                                                    <?php if ($payment['bid_amount']): ?>
                                                        <small class="text-muted">
                                                            Bid: <?= formatCurrency($payment['bid_amount']) ?><br>
                                                            Net: <?= formatCurrency($payment['net_payable']) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No payment history found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Member Info & Bids Won -->
            <div class="col-md-4">
                <!-- Member Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle"></i> Member Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Member ID:</strong></td>
                                <td><?= $member['id'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><?= htmlspecialchars($member['username'] ?: 'Not Set') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Group:</strong></td>
                                <td><?= htmlspecialchars($member['group_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Member #:</strong></td>
                                <td>#<?= $member['member_number'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Monthly Contribution:</strong></td>
                                <td><?= formatCurrency($member['monthly_contribution']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($member['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Joined:</strong></td>
                                <td><?= formatDate($member['created_at']) ?></td>
                            </tr>
                        </table>
                        
                        <?php if ($member['address']): ?>
                            <div class="mt-3">
                                <strong>Address:</strong><br>
                                <small class="text-muted"><?= nl2br(htmlspecialchars($member['address'])) ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bids Won -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-trophy"></i> Bids Won
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($bidsWon)): ?>
                            <?php foreach ($bidsWon as $bid): ?>
                                <div class="border rounded p-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="badge bg-warning">Month <?= $bid['month_number'] ?></span>
                                        <strong><?= formatCurrency($bid['net_payable']) ?></strong>
                                    </div>
                                    <small class="text-muted">
                                        Bid: <?= formatCurrency($bid['bid_amount']) ?><br>
                                        Date: <?= formatDate($bid['payment_date']) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="fas fa-trophy fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No bids won yet</p>
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
