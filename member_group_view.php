<?php
require_once 'config.php';
requireMemberLogin();

$member = getCurrentMember();
$groupId = $_SESSION['group_id'];
$group = getGroupById($groupId);
$members = getGroupMembers($groupId);
$monthlyBids = getMonthlyBids($groupId);
$memberPayments = getMemberPayments($groupId);
$memberSummary = getMemberSummary($groupId);

// Organize payments by member and month
$paymentsMatrix = [];
foreach ($memberPayments as $payment) {
    $paymentsMatrix[$payment['member_id']][$payment['month_number']] = $payment;
}

// Organize summary by member
$summaryByMember = [];
foreach ($memberSummary as $summary) {
    $summaryByMember[$summary['member_id']] = $summary;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($group['group_name']) ?> - Member View</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .spreadsheet-table {
            font-size: 11px;
        }
        .spreadsheet-table th,
        .spreadsheet-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            text-align: center;
            vertical-align: middle;
        }
        .header-blue {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        .header-green {
            background-color: #70AD47;
            color: white;
            font-weight: bold;
        }
        .header-orange {
            background-color: #C65911;
            color: white;
            font-weight: bold;
        }
        .cell-yellow {
            background-color: #FFFF00;
        }
        .my-row {
            background-color: #E8F5E8 !important;
            font-weight: bold;
        }
        .text-right {
            text-align: right !important;
        }
        .text-left {
            text-align: left !important;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="member_dashboard.php">
                <i class="fas fa-user-circle"></i> Member Portal
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <?= htmlspecialchars($_SESSION['member_name']) ?>
                </span>
                <a class="nav-link text-white" href="member_dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <!-- Group Header Info -->
        <div class="row mb-3">
            <div class="col-12">
                <h3><?= htmlspecialchars($group['group_name']) ?> - Complete View</h3>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    This is a read-only view of your BC group. Your row is highlighted in green.
                </div>
            </div>
        </div>

        <!-- Basic Info Table -->
        <div class="row mb-4">
            <div class="col-12">
                <table class="table table-bordered spreadsheet-table">
                    <tr>
                        <td class="header-blue">Total Members</td>
                        <td class="fw-bold"><?= $group['total_members'] ?></td>
                        <td class="header-blue">Monthly Contribution</td>
                        <td class="fw-bold"><?= formatCurrency($group['monthly_contribution']) ?></td>
                        <td class="header-blue">Total Monthly Contribution</td>
                        <td class="fw-bold"><?= formatCurrency($group['total_monthly_collection']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Deposit/Bid Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="header-green text-white p-2 mb-0">Deposit/Bid Details</h5>
                <table class="table table-bordered spreadsheet-table mb-0">
                    <thead>
                        <tr class="header-orange">
                            <th>Month</th>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <th>Month <?= $i ?></th>
                            <?php endfor; ?>
                            <th>Total Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold">Taken By</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    if ($bid) {
                                        $name = htmlspecialchars($bid['member_name']);
                                        if ($bid['taken_by_member_id'] == $member['id']) {
                                            echo '<strong style="color: green;">' . $name . ' (You)</strong>';
                                        } else {
                                            echo $name;
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Is Bid</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? $bid['is_bid'] : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Bid Amount</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? formatCurrency($bid['bid_amount']) : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Net Payable</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td class="cell-yellow">
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? formatCurrency($bid['net_payable']) : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td class="cell-yellow fw-bold">
                                <?php
                                $totalNetPayable = array_sum(array_column($monthlyBids, 'net_payable'));
                                echo formatCurrency($totalNetPayable);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Gain Per Member</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? formatCurrency($bid['gain_per_member']) : formatCurrency($group['monthly_contribution']);
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Payment Date</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid && $bid['payment_date'] ? formatDate($bid['payment_date']) : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transaction Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="header-green text-white p-2 mb-0">Transaction Details</h5>
                <table class="table table-bordered spreadsheet-table mb-0">
                    <thead>
                        <tr class="header-orange">
                            <th class="text-left">Member Name</th>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <th>Month <?= $i ?></th>
                            <?php endfor; ?>
                            <th>Total Paid</th>
                            <th>Given</th>
                            <th>Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $memberRow): ?>
                            <tr class="<?= $memberRow['id'] == $member['id'] ? 'my-row' : '' ?>">
                                <td class="text-left fw-bold">
                                    <?= htmlspecialchars($memberRow['member_name']) ?>
                                    <?php if ($memberRow['id'] == $member['id']): ?>
                                        <span class="badge bg-success ms-1">You</span>
                                    <?php endif; ?>
                                </td>
                                <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                    <td>
                                        <?php
                                        $payment = $paymentsMatrix[$memberRow['id']][$i] ?? null;
                                        if ($payment) {
                                            echo formatCurrency($payment['payment_amount']);
                                        } else {
                                            // Show expected amount
                                            $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                            $bid = reset($bid);
                                            $expectedAmount = $bid ? $bid['gain_per_member'] : $group['monthly_contribution'];
                                            echo '<span class="text-muted">' . formatCurrency($expectedAmount) . '</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="fw-bold">
                                    <?php
                                    $summary = $summaryByMember[$memberRow['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['total_paid']) : '₹0';
                                    ?>
                                </td>
                                <td class="fw-bold">
                                    <?php
                                    $summary = $summaryByMember[$memberRow['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['given_amount']) : '₹0';
                                    ?>
                                </td>
                                <td class="fw-bold <?= ($summary && $summary['profit'] >= 0) ? 'text-success' : 'text-danger' ?>">
                                    <?php
                                    $summary = $summaryByMember[$memberRow['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['profit']) : '₹0';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h6>Total Collected</h6>
                        <h4><?= formatCurrency(array_sum(array_column($memberSummary, 'total_paid'))) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h6>Total Given</h6>
                        <h4><?= formatCurrency(array_sum(array_column($memberSummary, 'given_amount'))) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h6>Completed Months</h6>
                        <h4><?= count($monthlyBids) ?> / <?= $group['total_members'] ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h6>Status</h6>
                        <h4><?= ucfirst($group['status']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
