<?php
require_once 'config.php';
requireMemberLogin();

$member = getCurrentMember();
$currentGroupId = $_SESSION['group_id'];

// Get all groups the member belongs to
$memberGroups = getMemberGroups($member['id']);

// Determine which group to display
$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : $currentGroupId;

// Verify member has access to selected group
$hasAccess = false;
foreach ($memberGroups as $memberGroup) {
    if ($memberGroup['id'] == $selectedGroupId) {
        $hasAccess = true;
        break;
    }
}

if (!$hasAccess) {
    $selectedGroupId = $currentGroupId;
}

$group = getGroupById($selectedGroupId);
$members = getGroupMembers($selectedGroupId);
$monthlyBids = getMonthlyBids($selectedGroupId);
$memberPayments = getMemberPayments($selectedGroupId);
$memberSummary = getMemberSummary($selectedGroupId);

// Get random picks for this group
$randomPicks = getRandomPicks($selectedGroupId);

// Get current active month
$currentActiveMonth = getCurrentActiveMonthNumber($selectedGroupId);

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

// Find current member's ID in the selected group
$currentMemberInGroup = null;
foreach ($members as $memberInGroup) {
    if ($memberInGroup['member_name'] == $member['member_name']) {
        $currentMemberInGroup = $memberInGroup;
        break;
    }
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
        /* Mobile-first responsive table styling */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .spreadsheet-table {
            font-size: 11px;
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

            .container {
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
            background: linear-gradient(90deg, #28a745, #20c997);
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
            background: #e8f5e8;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #155724;
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

        /* Random Pick Button Styles */
        .random-pick-btn {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }

        .random-pick-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.4);
        }

        .random-pick-btn:disabled {
            animation: none;
            opacity: 0.6;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container-fluid">
            <a class="navbar-brand" href="member_dashboard.php">
                <i class="fas fa-users text-warning me-2"></i>Mitra Niidhi Samooh
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

                <!-- Mobile Navigation Hint -->
                <div class="mobile-nav-hint">
                    <i class="fas fa-mobile-alt"></i> <strong>Mobile Tip:</strong> Swipe left/right on tables to see all columns. Your data is highlighted in green.
                </div>
            </div>
        </div>

        <!-- Group Selector -->
        <?php if (count($memberGroups) > 1): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exchange-alt me-2"></i>Switch Group View
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="d-flex align-items-center gap-3">
                            <label for="group_id" class="form-label mb-0 fw-bold">Select Group:</label>
                            <select name="group_id" id="group_id" class="form-select" style="width: auto;" onchange="this.form.submit()">
                                <?php foreach ($memberGroups as $memberGroup): ?>
                                    <option value="<?= $memberGroup['id'] ?>"
                                            <?= $selectedGroupId == $memberGroup['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($memberGroup['group_name']) ?>
                                        <?php if ($memberGroup['id'] == $currentGroupId): ?>
                                            (Current Group)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                View complete details for any of your groups
                            </small>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Basic Info Table -->
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
                <h5 class="header-green text-white p-2 mb-0">Deposit/Bid Details</h5>
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
                                    // Check for monthly bid first
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);

                                    // Check for random pick
                                    $randomPick = array_filter($randomPicks, fn($rp) => $rp['month_number'] == $i);
                                    $randomPick = reset($randomPick);

                                    if ($bid) {
                                        $name = htmlspecialchars($bid['member_name']);
                                        if ($currentMemberInGroup && $bid['taken_by_member_id'] == $currentMemberInGroup['id']) {
                                            echo '<strong style="color: green;">' . $name . ' (You)</strong>';
                                        } else {
                                            echo $name;
                                        }
                                    } elseif ($randomPick) {
                                        // Check if there's an admin override
                                        if ($randomPick['admin_override_member_id']) {
                                            $overrideName = htmlspecialchars($randomPick['admin_override_member_name']);
                                            $randomName = htmlspecialchars($randomPick['member_name']);

                                            // If admin selected the same person as random pick, show only once
                                            if ($randomPick['admin_override_member_id'] == $randomPick['selected_member_id']) {
                                                if ($currentMemberInGroup && $randomPick['admin_override_member_id'] == $currentMemberInGroup['id']) {
                                                    echo '<strong style="color: purple;">' . $overrideName . ' (You) 🎲👨‍💼</strong>';
                                                } else {
                                                    echo '<span style="color: purple;">' . $overrideName . ' 🎲👨‍💼</span>';
                                                }
                                            } else {
                                                // Show both random pick and admin override
                                                echo '<div style="font-size: 11px; line-height: 1.2;">';
                                                echo '<div style="color: #888; text-decoration: line-through;">🎲 ' . $randomName . '</div>';
                                                if ($currentMemberInGroup && $randomPick['admin_override_member_id'] == $currentMemberInGroup['id']) {
                                                    echo '<div style="color: green;"><strong>👨‍💼 ' . $overrideName . ' (You)</strong></div>';
                                                } else {
                                                    echo '<div style="color: green;">👨‍💼 ' . $overrideName . '</div>';
                                                }
                                                echo '</div>';
                                            }
                                        } else {
                                            // Only random pick, no admin override
                                            $name = htmlspecialchars($randomPick['member_name']);
                                            if ($currentMemberInGroup && $randomPick['selected_member_id'] == $currentMemberInGroup['id']) {
                                                echo '<strong style="color: blue;">' . $name . ' (You) 🎲</strong>';
                                            } else {
                                                echo '<span style="color: blue;">' . $name . ' 🎲</span>';
                                            }
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
                            <td class="fw-bold sticky-col">Random Pick</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    // Check if month already has a winner (bid or random pick)
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);

                                    $randomPick = array_filter($randomPicks, fn($rp) => $rp['month_number'] == $i);
                                    $randomPick = reset($randomPick);

                                    if ($bid || $randomPick) {
                                        echo '<span class="text-muted">Done</span>';
                                    } elseif ($currentActiveMonth && $i == $currentActiveMonth) {
                                        // Only show random pick button for current active month
                                        echo '<button class="btn btn-sm btn-warning random-pick-btn"
                                                data-group-id="' . $selectedGroupId . '"
                                                data-month="' . $i . '"
                                                title="Randomly select a member for Month ' . $i . ' (Current Month)">
                                                🎲 Pick
                                              </button>';
                                    } elseif ($i < $currentActiveMonth) {
                                        // Past months
                                        echo '<span class="text-muted" title="Past month - cannot pick">🔒 Past</span>';
                                    } else {
                                        // Future months
                                        echo '<span class="text-muted" title="Future month - not available yet">⏳ Future</span>';
                                    }
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
                                    if ($bid) {
                                        echo formatCurrency($bid['gain_per_member']);
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
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
                <h5 class="header-green text-white p-2 mb-0">Transaction Details</h5>
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Swipe to see all member payments
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $memberRow): ?>
                            <tr class="<?= ($currentMemberInGroup && $memberRow['id'] == $currentMemberInGroup['id']) ? 'my-row' : '' ?>">
                                <td class="text-left fw-bold sticky-col">
                                    <span class="name-tooltip" data-full-name="<?= htmlspecialchars($memberRow['member_name']) ?>" title="<?= htmlspecialchars($memberRow['member_name']) ?>">
                                        <?= htmlspecialchars($memberRow['member_name']) ?>
                                    </span>
                                    <?php if ($currentMemberInGroup && $memberRow['id'] == $currentMemberInGroup['id']): ?>
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
                                            // Show expected amount or pending indicator
                                            $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                            $bid = reset($bid);
                                            if ($bid) {
                                                $expectedAmount = $bid['gain_per_member'];
                                                echo '<span class="text-muted">' . formatCurrency($expectedAmount) . '</span>';
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
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

                    <!-- Legend -->
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Legend:</strong>
                            <span class="badge bg-success me-2">Green</span> = Paid
                            <span class="badge bg-warning me-2">Yellow</span> = Pending Payment
                            <span class="text-muted me-2">"-"</span> = Amount not yet decided
                        </small>
                    </div>
                </div>
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

    <script>
        // Random Pick functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle random pick button clicks
            document.querySelectorAll('.random-pick-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const groupId = this.getAttribute('data-group-id');
                    const monthNumber = this.getAttribute('data-month');

                    // Confirm action
                    if (!confirm(`Are you sure you want to randomly pick a member for Month ${monthNumber}? This action cannot be undone.`)) {
                        return;
                    }

                    // Disable button and show loading
                    this.disabled = true;
                    this.innerHTML = '🎲 Picking...';

                    // Make AJAX request
                    fetch('random_pick_member.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `group_id=${groupId}&month_number=${monthNumber}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            alert(`Random pick successful! ${data.selected_member.name} has been selected for Month ${monthNumber}.`);
                            // Reload page to show updated data
                            window.location.reload();
                        } else {
                            // Show error message
                            alert(`Error: ${data.message}`);
                            // Re-enable button
                            this.disabled = false;
                            this.innerHTML = '🎲 Pick';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while making the random pick. Please try again.');
                        // Re-enable button
                        this.disabled = false;
                        this.innerHTML = '🎲 Pick';
                    });
                });
            });
        });

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
