<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) {
    redirect('index.php');
}

$group = getGroupById($groupId);
if (!$group) {
    setMessage('Group not found.', 'error');
    redirect('index.php');
}

// Handle group deletion
if ($_POST['action'] ?? '' === 'delete_group') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            // Delete all related data in correct order (child tables first)

            // 1. Delete random picks
            $stmt = $pdo->prepare("DELETE FROM random_picks WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // 2. Delete member bids
            $stmt = $pdo->prepare("DELETE FROM member_bids WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // 3. Delete month bidding status
            $stmt = $pdo->prepare("DELETE FROM month_bidding_status WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // 4. Delete member payments
            $stmt = $pdo->prepare("DELETE FROM member_payments WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // 5. Delete member summary
            $stmt = $pdo->prepare("DELETE FROM member_summary WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // 6. Delete monthly bids
            $stmt = $pdo->prepare("DELETE FROM monthly_bids WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // 7. Delete group member assignments
            $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
            $stmt->execute([$groupId]);

            // 8. Finally delete the group itself
            $stmt = $pdo->prepare("DELETE FROM bc_groups WHERE id = ?");
            $stmt->execute([$groupId]);

            $pdo->commit();

            setMessage('Group and all related data have been deleted successfully!', 'success');
            redirect('manage_groups.php');

        } catch (Exception $e) {
            $pdo->rollBack();
            setMessage('Failed to delete group: ' . $e->getMessage(), 'error');
        }
    } else {
        setMessage('Group deletion cancelled - confirmation not provided.', 'info');
    }
}

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
    <title><?= htmlspecialchars($group['group_name']) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Mobile-first responsive table styling */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .spreadsheet-table {
            font-size: 12px;
            margin-bottom: 0;
            min-width: 800px; /* Ensure minimum width for proper display */
        }

        .spreadsheet-table th,
        .spreadsheet-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap; /* Prevent text wrapping */
            min-width: 80px; /* Minimum column width */
        }

        /* Sticky first column for better mobile experience */
        .spreadsheet-table .sticky-col {
            position: sticky;
            left: 0;
            background-color: #f8f9fa;
            z-index: 10;
            border-right: 2px solid #000;
            min-width: 120px;
            max-width: 120px;
            width: 120px;
            text-align: left;
            padding-left: 8px;
            padding-right: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
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
        .cell-light-blue {
            background-color: #B4C6E7;
        }
        .cell-light-green {
            background-color: #C6EFCE;
        }
        .cell-light-orange {
            background-color: #FCE4D6;
        }
        .text-right {
            text-align: right !important;
        }
        .text-left {
            text-align: left !important;
        }
        .fw-bold {
            font-weight: bold;
        }

        /* Invoice Button Styles */
        .invoice-btn {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.3s ease;
            border: 1px solid #007bff;
            color: #007bff;
            background: transparent;
        }

        .invoice-btn:hover {
            background-color: #007bff;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .invoice-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .invoice-btn i {
            font-size: 14px;
        }

        /* Mobile-specific enhancements */
        @media (max-width: 768px) {
            .spreadsheet-table {
                font-size: 10px;
            }

            .spreadsheet-table th,
            .spreadsheet-table td {
                padding: 3px 4px;
                min-width: 70px;
            }

            .sticky-col {
                min-width: 100px !important;
                max-width: 100px !important;
                width: 100px !important;
                font-size: 9px;
                padding-left: 6px !important;
                padding-right: 6px !important;
            }

            .container-fluid {
                padding-left: 5px;
                padding-right: 5px;
            }

            .table-responsive-custom {
                border-radius: 0.25rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
        }

        /* Scroll indicator for mobile */
        .scroll-indicator {
            display: none;
            background: linear-gradient(90deg, #007bff, #0056b3);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 10px;
            text-align: center;
            animation: pulse 2s infinite;
        }

        @media (max-width: 768px) {
            .scroll-indicator {
                display: block;
            }
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* Better mobile navigation */
        .mobile-nav-hint {
            display: none;
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #1976d2;
        }

        @media (max-width: 768px) {
            .mobile-nav-hint {
                display: block;
            }
        }

        /* Tooltip for truncated names */
        .name-tooltip {
            position: relative;
            cursor: pointer;
        }

        .name-tooltip:hover::after,
        .name-tooltip:focus::after {
            content: attr(data-full-name);
            position: absolute;
            bottom: 100%;
            left: 0;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .name-tooltip:hover::before,
        .name-tooltip:focus::before {
            content: '';
            position: absolute;
            bottom: 95%;
            left: 10px;
            border: 5px solid transparent;
            border-top-color: #333;
            z-index: 1000;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-handshake text-warning me-2"></i><?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-3">
        <?php $msg = getMessage(); ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Group Header Info -->
        <div class="row mb-3">
            <div class="col-12">
                <h2>
                    <?= htmlspecialchars($group['group_name']) ?>
                    <?php if ($group['status'] === 'completed'): ?>
                        <span class="badge bg-success ms-2">Completed</span>
                    <?php else: ?>
                        <span class="badge bg-primary ms-2">Active</span>
                    <?php endif; ?>
                </h2>
                <div class="d-flex gap-3 mb-3">
                    <a href="admin_bidding.php?group_id=<?= $groupId ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-gavel"></i> Manage Bidding
                    </a>
                    <a href="add_bid.php?group_id=<?= $groupId ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add Monthly Bid
                    </a>
                    <a href="add_payment.php?group_id=<?= $groupId ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-money-bill"></i> Add Payment
                    </a>
                    <a href="manage_members.php?group_id=<?= $groupId ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-users-cog"></i> Manage Members
                    </a>
                    <a href="admin_manage_random_picks.php?group_id=<?= $groupId ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-dice"></i> Manage Random Picks
                    </a>
                    <a href="edit_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-edit"></i> Edit Group
                    </a>
                    <?php if ($group['status'] === 'completed'): ?>
                        <a href="clone_group.php?id=<?= $groupId ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-copy"></i> Restart Group
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteGroupModal">
                        <i class="fas fa-trash"></i> Delete Group
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Hint -->
        <div class="mobile-nav-hint">
            <i class="fas fa-mobile-alt"></i> <strong>Mobile Tip:</strong> Swipe left/right on tables to see all columns and data.
        </div>

        <!-- Basic Info Table (like Excel header) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all data
                </div>
                <div class="table-responsive-custom">
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
        </div>

        <!-- Deposit/Bid Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="header-green text-white p-2 mb-0">Deposit/Bid Details</h4>
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Swipe to see all months
                </div>
                <div class="table-responsive-custom">
                    <table class="table table-bordered spreadsheet-table mb-0">
                    <thead>
                        <tr class="header-orange">
                            <th class="sticky-col">Month</th>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <th>Month <?= $i ?></th>
                            <?php endfor; ?>
                            <th>Total Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold sticky-col">Taken By</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? htmlspecialchars($bid['member_name']) : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold sticky-col">Is Bid</td>
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
                            <td class="fw-bold sticky-col">Bid Amount</td>
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
                            <td class="fw-bold sticky-col">Net Payable</td>
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
                            <td class="fw-bold sticky-col">Gain Per Member</td>
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
                            <td class="fw-bold sticky-col">Payment Date</td>
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
        </div>

        <!-- Transaction Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="header-green text-white p-2 mb-0">Transaction Details</h4>
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Swipe to see all member payments
                </div>
                <div class="alert alert-info py-2 px-3 mb-2" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Legend:</strong>
                    <span class="text-success fw-bold">-</span> = Payment completed |
                    <span class="text-muted">Amount</span> = Payment pending
                </div>
                <div class="table-responsive-custom">
                    <table class="table table-bordered spreadsheet-table mb-0">
                    <thead>
                        <tr class="header-orange">
                            <th class="text-left sticky-col">Member Name</th>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <th>Month <?= $i ?></th>
                            <?php endfor; ?>
                            <th>Total Paid</th>
                            <th>Given</th>
                            <th>Profit</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td class="text-left fw-bold sticky-col">
                                    <span class="name-tooltip" data-full-name="<?= htmlspecialchars($member['member_name']) ?>" title="<?= htmlspecialchars($member['member_name']) ?>">
                                        <?= htmlspecialchars($member['member_name']) ?>
                                    </span>
                                </td>
                                <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                    <td>
                                        <?php
                                        $payment = $paymentsMatrix[$member['id']][$i] ?? null;
                                        if ($payment) {
                                            // Show actual payment amount when payment is completed
                                            echo '<div class="text-success fw-bold">' . formatCurrency($payment['payment_amount']) . '</div>';
                                            // Show payment date below the amount in small font
                                            if ($payment['payment_date']) {
                                                echo '<div class="text-muted" style="font-size: 0.60rem; margin-top: 2px;">' . formatDate($payment['payment_date']) . '</div>';
                                            }
                                        } else {
                                            // Show dash (-) when payment is NOT done
                                            echo '<span class="text-muted fw-bold">-</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="fw-bold">
                                    <?php
                                    $summary = $summaryByMember[$member['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['total_paid']) : '₹0';
                                    ?>
                                </td>
                                <td class="fw-bold">
                                    <?php
                                    $summary = $summaryByMember[$member['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['given_amount']) : '₹0';
                                    ?>
                                </td>
                                <td class="fw-bold <?= ($summary && $summary['profit'] >= 0) ? 'text-success' : 'text-danger' ?>">
                                    <?php
                                    $summary = $summaryByMember[$member['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['profit']) : '₹0';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary invoice-btn" 
                                            data-member-id="<?= $member['id'] ?>"
                                            data-member-name="<?= htmlspecialchars($member['member_name']) ?>"
                                            data-group-id="<?= $groupId ?>"
                                            data-group-name="<?= htmlspecialchars($group['group_name']) ?>"
                                            title="Download Invoice for <?= htmlspecialchars($member['member_name']) ?>">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5>Total Collected</h5>
                        <h3><?= formatCurrency(array_sum(array_column($memberSummary, 'total_paid'))) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h5>Total Given</h5>
                        <h3><?= formatCurrency(array_sum(array_column($memberSummary, 'given_amount'))) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h5>Completed Months</h5>
                        <h3><?= count($monthlyBids) ?> / <?= $group['total_members'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h5>Status</h5>
                        <h3><?= ucfirst($group['status']) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Group Confirmation Modal -->
    <div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteGroupModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Delete Group
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-warning"></i> <strong>WARNING: This action cannot be undone!</strong></h6>
                        <p class="mb-0">Deleting this group will permanently remove:</p>
                    </div>

                    <ul class="list-group list-group-flush mb-3">
                        <li class="list-group-item"><i class="fas fa-users text-primary"></i> All member assignments</li>
                        <li class="list-group-item"><i class="fas fa-money-bill text-success"></i> All payment records</li>
                        <li class="list-group-item"><i class="fas fa-gavel text-warning"></i> All bidding data</li>
                        <li class="list-group-item"><i class="fas fa-chart-bar text-info"></i> All summary data</li>
                        <li class="list-group-item"><i class="fas fa-dice text-secondary"></i> All random pick data</li>
                        <li class="list-group-item"><i class="fas fa-layer-group text-danger"></i> The group itself</li>
                    </ul>

                    <div class="bg-light p-3 rounded">
                        <h6>Group to be deleted:</h6>
                        <p class="mb-1"><strong><?= htmlspecialchars($group['group_name']) ?></strong></p>
                        <p class="mb-1">Members: <?= count($members) ?></p>
                        <p class="mb-0">Status: <?= ucfirst($group['status']) ?></p>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                        <label class="form-check-label text-danger fw-bold" for="confirmDelete">
                            I understand this action cannot be undone
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_group">
                        <input type="hidden" name="confirm_delete" value="yes">
                        <button type="submit" class="btn btn-secondary" id="deleteButton" disabled title="Check the confirmation box to enable">
                            <i class="fas fa-trash"></i> Delete Group Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Enhanced mobile tooltip handling
        document.addEventListener('DOMContentLoaded', function() {
            const nameTooltips = document.querySelectorAll('.name-tooltip');

            nameTooltips.forEach(tooltip => {
                // Handle touch events for mobile
                tooltip.addEventListener('touchstart', function(e) {
                    e.preventDefault();

                    // Remove any existing active tooltips
                    document.querySelectorAll('.name-tooltip.active').forEach(t => {
                        t.classList.remove('active');
                    });

                    // Add active class to show tooltip
                    this.classList.add('active');

                    // Remove tooltip after 3 seconds
                    setTimeout(() => {
                        this.classList.remove('active');
                    }, 3000);
                });

                // Handle click outside to hide tooltip
                document.addEventListener('touchstart', function(e) {
                    if (!tooltip.contains(e.target)) {
                        tooltip.classList.remove('active');
                    }
                });
            });

            // Handle delete confirmation checkbox with improved event handling
            const confirmCheckbox = document.getElementById('confirmDelete');
            const deleteButton = document.getElementById('deleteButton');

            if (confirmCheckbox && deleteButton) {
                confirmCheckbox.addEventListener('change', function() {
                    console.log('Checkbox changed:', this.checked); // Debug log
                    deleteButton.disabled = !this.checked;

                    // Visual feedback
                    if (this.checked) {
                        deleteButton.classList.remove('btn-secondary');
                        deleteButton.classList.add('btn-danger');
                        deleteButton.title = 'Click to delete group permanently';
                    } else {
                        deleteButton.classList.remove('btn-danger');
                        deleteButton.classList.add('btn-secondary');
                        deleteButton.title = 'Check the confirmation box to enable';
                    }
                });

                // Also handle click events for better mobile support
                confirmCheckbox.addEventListener('click', function() {
                    setTimeout(() => {
                        deleteButton.disabled = !this.checked;
                    }, 10);
                });

                // Reset checkbox when modal is hidden
                const deleteModal = document.getElementById('deleteGroupModal');
                deleteModal.addEventListener('hidden.bs.modal', function() {
                    confirmCheckbox.checked = false;
                    deleteButton.disabled = true;
                    deleteButton.classList.remove('btn-danger');
                    deleteButton.classList.add('btn-secondary');
                    deleteButton.title = 'Check the confirmation box to enable';
                });

                // Also reset when modal is shown
                deleteModal.addEventListener('shown.bs.modal', function() {
                    confirmCheckbox.checked = false;
                    deleteButton.disabled = true;
                    deleteButton.classList.remove('btn-danger');
                    deleteButton.classList.add('btn-secondary');
                    deleteButton.title = 'Check the confirmation box to enable';
                });
            }

            // Handle invoice download button clicks
            document.querySelectorAll('.invoice-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const memberId = this.getAttribute('data-member-id');
                    const memberName = this.getAttribute('data-member-name');
                    const groupId = this.getAttribute('data-group-id');
                    const groupName = this.getAttribute('data-group-name');

                    // Show loading state
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    this.disabled = true;

                    // Generate and open invoice in new tab/page
                    const sessionId = '<?= session_id() ?>';
                    const url = `../member/generate_invoice.php?member_id=${memberId}&group_id=${groupId}&admin=1&session_id=${sessionId}`;
                    
                    try {
                        // Try to open in new tab first
                        const invoiceWindow = window.open(url, '_blank');
                        
                        // Check if popup was blocked
                        if (!invoiceWindow || invoiceWindow.closed || typeof invoiceWindow.closed == 'undefined') {
                            // Fallback: redirect current page to invoice
                            if (confirm('Popup blocked! Click OK to view invoice in current tab, or Cancel to allow popups and try again.')) {
                                window.location.href = url;
                            }
                        } else {
                            // Success - focus the new window
                            invoiceWindow.focus();
                        }
                    } catch (error) {
                        // Final fallback - redirect to invoice page
                        window.location.href = url;
                    }

                    // Reset button state after a short delay
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }, 1000);
                });
            });
        });
    </script>

    <style>
        /* Mobile touch tooltip styling */
        @media (max-width: 768px) {
            .name-tooltip.active::after {
                content: attr(data-full-name);
                position: absolute;
                bottom: 100%;
                left: 0;
                background: #333;
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 1000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                animation: fadeIn 0.3s ease-in-out;
            }

            .name-tooltip.active::before {
                content: '';
                position: absolute;
                bottom: 95%;
                left: 10px;
                border: 5px solid transparent;
                border-top-color: #333;
                z-index: 1000;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        }
    </style>
</body>
</html>
