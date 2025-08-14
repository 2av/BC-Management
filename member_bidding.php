
<?php
require_once 'config.php';
requireMemberLogin();

$member = getCurrentMember();

// Get all groups this member belongs to
$allMemberGroups = getMemberGroups($member['id']);

// Determine which group to use (from URL parameter or default to first group)
$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

// If no group selected or invalid group, use the first available group
if (!$selectedGroupId || !in_array($selectedGroupId, array_column($allMemberGroups, 'id'))) {
    $selectedGroupId = $allMemberGroups[0]['id'] ?? null;
}

if (!$selectedGroupId) {
    setMessage("You are not a member of any group.", 'error');
    redirect('member_dashboard.php');
}

$groupId = $selectedGroupId;
$group = getGroupById($groupId);

// Handle bid submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bid'])) {
    $monthNumber = (int)$_POST['month_number'];
    $bidAmount = (float)$_POST['bid_amount'];
    
    // Validate bid
    $errors = [];

    if ($bidAmount <= 0) {
        $errors[] = "Bid amount must be greater than 0";
    }

    // Check if member has already won in this group
    $pdo = getDB();
    $groupMembers = getGroupMembers($groupId);
    $memberInGroup = array_filter($groupMembers, fn($m) => $m['member_name'] === $member['member_name']);
    $memberInGroup = reset($memberInGroup);

    if (!$memberInGroup) {
        $errors[] = "You are not a member of this group";
    } else {
        // Check if member has already won in this group
        $stmt = $pdo->prepare("SELECT month_number FROM monthly_bids WHERE group_id = ? AND taken_by_member_id = ?");
        $stmt->execute([$groupId, $memberInGroup['id']]);
        $wonMonth = $stmt->fetch();

        if ($wonMonth) {
            $errors[] = "You have already won in month " . $wonMonth['month_number'] . " in this group";
        }
    }

    // Check if this month already has a winner
    $stmt = $pdo->prepare("SELECT * FROM monthly_bids WHERE group_id = ? AND month_number = ?");
    $stmt->execute([$groupId, $monthNumber]);
    $existingBid = $stmt->fetch();

    if ($existingBid) {
        $errors[] = "This month already has a winner";
    }

    // Get bid limits from month_bidding_status table
    $stmt = $pdo->prepare("SELECT minimum_bid_amount, maximum_bid_amount FROM month_bidding_status WHERE group_id = ? AND month_number = ?");
    $stmt->execute([$groupId, $monthNumber]);
    $bidLimits = $stmt->fetch();

    // Basic bid validation
    if ($bidAmount >= $group['total_monthly_collection']) {
        $errors[] = "Bid amount must be less than total monthly collection (₹" . number_format($group['total_monthly_collection']) . ")";
    }

    // Validate against bid limits if they exist
    if ($bidLimits) {
        if ($bidLimits['minimum_bid_amount'] > 0 && $bidAmount < $bidLimits['minimum_bid_amount']) {
            $errors[] = "Bid amount must be at least ₹" . number_format($bidLimits['minimum_bid_amount']);
        }
        if ($bidLimits['maximum_bid_amount'] > 0 && $bidAmount > $bidLimits['maximum_bid_amount']) {
            $errors[] = "Bid amount cannot exceed ₹" . number_format($bidLimits['maximum_bid_amount']);
        }
    }
    
    // Check if member already placed a bid for this month (only if member is valid)
    if ($memberInGroup && empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM member_bids WHERE group_id = ? AND member_id = ? AND month_number = ?");
        $stmt->execute([$groupId, $memberInGroup['id'], $monthNumber]);
        if ($stmt->fetch()) {
            $errors[] = "You have already placed a bid for this month";
        }
    }

    if (empty($errors) && $memberInGroup) {
        // Insert the bid
        $stmt = $pdo->prepare("
            INSERT INTO member_bids (group_id, member_id, month_number, bid_amount)
            VALUES (?, ?, ?, ?)
        ");

        if ($stmt->execute([$groupId, $memberInGroup['id'], $monthNumber, $bidAmount])) {
            setMessage("Your bid of ₹" . number_format($bidAmount) . " for Month $monthNumber has been submitted successfully!", 'success');
            redirect('member_bidding.php?group_id=' . $groupId);
        } else {
            $errors[] = "Failed to submit bid. Please try again.";
        }
    }
    
    if (!empty($errors)) {
        setMessage(implode('<br>', $errors), 'error');
    }
}

// Get available months for bidding
function getAvailableBiddingMonths($groupId, $memberId) {
    $pdo = getDB();

    // Get group info to determine total months
    $stmt = $pdo->prepare("SELECT total_members FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();

    if (!$group) {
        return [];
    }

    $totalMonths = $group['total_members'];
    $months = [];

    // Get all monthly bids to determine which months are completed
    $stmt = $pdo->prepare("SELECT month_number FROM monthly_bids WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $completedMonths = array_column($stmt->fetchAll(), 'month_number');

    for ($month = 1; $month <= $totalMonths; $month++) {
        // Determine bidding status
        $isCompleted = in_array($month, $completedMonths);

        // Get bid counts
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM member_bids WHERE group_id = ? AND month_number = ?");
        $stmt->execute([$groupId, $month]);
        $totalBids = $stmt->fetch()['count'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM member_bids WHERE group_id = ? AND month_number = ? AND member_id = ?");
        $stmt->execute([$groupId, $month, $memberId]);
        $myBidCount = $stmt->fetch()['count'];

        // Get bidding status and limits from month_bidding_status table
        $stmt = $pdo->prepare("
            SELECT * FROM month_bidding_status
            WHERE group_id = ? AND month_number = ?
        ");
        $stmt->execute([$groupId, $month]);
        $biddingStatus = $stmt->fetch();

        if ($biddingStatus) {
            $months[] = [
                'month_number' => $month,
                'group_id' => $groupId,
                'bidding_status' => $isCompleted ? 'completed' : $biddingStatus['bidding_status'],
                'minimum_bid_amount' => $biddingStatus['minimum_bid_amount'],
                'maximum_bid_amount' => $biddingStatus['maximum_bid_amount'],
                'bidding_end_date' => $biddingStatus['bidding_end_date'],
                'total_bids' => $totalBids,
                'my_bid_count' => $myBidCount
            ];
        } else {
            // Default values if no bidding status record exists
            $months[] = [
                'month_number' => $month,
                'group_id' => $groupId,
                'bidding_status' => $isCompleted ? 'completed' : 'not_started',
                'minimum_bid_amount' => 0,
                'maximum_bid_amount' => 0,
                'bidding_end_date' => null,
                'total_bids' => $totalBids,
                'my_bid_count' => $myBidCount
            ];
        }
    }

    return $months;
}

// Get current active month (next month to be bid on)
function getCurrentActiveMonth($groupId) {
    $pdo = getDB();

    // Get group info to determine total months
    $stmt = $pdo->prepare("SELECT total_members FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();

    if (!$group) {
        return null;
    }

    // Get the last completed month
    $stmt = $pdo->prepare("
        SELECT MAX(month_number) as last_month
        FROM monthly_bids
        WHERE group_id = ?
    ");
    $stmt->execute([$groupId]);
    $lastCompletedMonth = $stmt->fetch()['last_month'] ?? 0;

    // Current active month is the next month after last completed
    $currentMonth = $lastCompletedMonth + 1;

    // If current month exceeds total months, the group is complete
    if ($currentMonth > $group['total_members']) {
        return null; // No active month, group is complete
    }

    // Get bidding status and limits from month_bidding_status table
    $stmt = $pdo->prepare("
        SELECT * FROM month_bidding_status
        WHERE group_id = ? AND month_number = ?
    ");
    $stmt->execute([$groupId, $currentMonth]);
    $biddingStatus = $stmt->fetch();

    if ($biddingStatus) {
        return [
            'month_number' => $currentMonth,
            'group_id' => $groupId,
            'bidding_status' => $biddingStatus['bidding_status'],
            'minimum_bid_amount' => $biddingStatus['minimum_bid_amount'],
            'maximum_bid_amount' => $biddingStatus['maximum_bid_amount'],
            'bidding_end_date' => $biddingStatus['bidding_end_date']
        ];
    }

    return [
        'month_number' => $currentMonth,
        'group_id' => $groupId,
        'bidding_status' => 'not_started',
        'minimum_bid_amount' => 0,
        'maximum_bid_amount' => 0,
        'bidding_end_date' => null
    ];
}

// Get current bids for a specific month
function getCurrentBids($groupId, $monthNumber) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT mb.*, m.member_name, m.member_number
        FROM member_bids mb
        JOIN members m ON mb.member_id = m.id
        WHERE mb.group_id = ? AND mb.month_number = ?
        ORDER BY mb.bid_amount ASC, mb.bid_date ASC
    ");
    $stmt->execute([$groupId, $monthNumber]);
    return $stmt->fetchAll();
}

// Get member info in selected group
$groupMembers = getGroupMembers($groupId);
$memberInGroup = array_filter($groupMembers, fn($m) => $m['member_name'] === $member['member_name']);
$memberInGroup = reset($memberInGroup);

$availableMonths = getAvailableBiddingMonths($groupId, $memberInGroup['id'] ?? 0);
$currentActiveMonth = getCurrentActiveMonth($groupId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bidding Portal - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bidding-card {
            transition: all 0.3s ease;
        }
        .bidding-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .bid-status-open {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .bid-status-closed {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
            color: white;
        }
        .bid-status-completed {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
        }
        .bid-status-not_started {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        .current-month-highlight {
            background: linear-gradient(135deg, #fd7e14 0%, #e63946 100%);
            color: white;
            border: 3px solid #ffc107;
            box-shadow: 0 0 20px rgba(255, 193, 7, 0.5);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.5); }
            50% { box-shadow: 0 0 30px rgba(255, 193, 7, 0.8); }
            100% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.5); }
        }
        .current-bid {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .winning-bid {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .my-bid {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .month-status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .current-month-section {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #ffc107;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        .bid-form-highlight {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="member_dashboard.php">
                <i class="fas fa-users text-warning me-2"></i>Mitra Niidhi Samooh
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?= htmlspecialchars($_SESSION['member_name']) ?>
                </span>
                <a class="nav-link text-white" href="member_dashboard.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
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

        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-gavel text-primary me-2"></i>Monthly Bidding Portal</h2>
                <p class="text-muted">Place your bids for upcoming months. Lowest bidder wins the collection!</p>
            </div>
        </div>

        <!-- Group Selector -->
        <?php if (count($allMemberGroups) > 1): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">
                            <i class="fas fa-users text-primary me-2"></i>Select Group for Bidding
                        </h5>
                        <small class="text-muted">You are a member of multiple groups</small>
                    </div>
                    <div class="col-md-6">
                        <select class="form-select" onchange="changeGroup(this.value)">
                            <?php foreach ($allMemberGroups as $memberGroup): ?>
                                <option value="<?= $memberGroup['id'] ?>" <?= $memberGroup['id'] == $groupId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($memberGroup['group_name']) ?>
                                    (<?= $memberGroup['total_members'] ?> members - ₹<?= number_format($memberGroup['total_monthly_collection']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Group Info Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Group:</strong> <?= htmlspecialchars($group['group_name']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Monthly Collection:</strong> ₹<?= number_format($group['total_monthly_collection']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Your Member #:</strong>
                        <?php
                        // Get member number in this specific group
                        $groupMembers = getGroupMembers($groupId);
                        $memberInGroup = array_filter($groupMembers, fn($m) => $m['member_name'] === $member['member_name']);
                        $memberInGroup = reset($memberInGroup);
                        echo $memberInGroup ? $memberInGroup['member_number'] : 'N/A';
                        ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Won Month:</strong>
                        <?php
                        // Check if member has won in this group
                        $pdo = getDB();
                        $stmt = $pdo->prepare("SELECT month_number FROM monthly_bids WHERE group_id = ? AND taken_by_member_id = ?");
                        $stmt->execute([$groupId, $memberInGroup['id'] ?? 0]);
                        $wonMonth = $stmt->fetch();
                        echo $wonMonth ? "Month " . $wonMonth['month_number'] : "Not yet";
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Active Month Highlight -->
        <?php if ($currentActiveMonth): ?>
            <div class="current-month-section">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="mb-3">
                                <i class="fas fa-star text-warning me-2"></i>
                                Current Month: Month <?= $currentActiveMonth['month_number'] ?>
                                <span class="badge bg-<?= $currentActiveMonth['bidding_status'] === 'open' ? 'success' : 'info' ?> ms-2">
                                    <?= ucfirst(str_replace('_', ' ', $currentActiveMonth['bidding_status'])) ?>
                                </span>
                            </h3>

                            <?php if ($currentActiveMonth['bidding_status'] === 'open'): ?>
                                <p class="text-success mb-2">
                                    <i class="fas fa-clock text-warning"></i>
                                    <strong>Bidding is OPEN!</strong> Place your bid now to win this month's collection.
                                </p>
                                <?php if ($currentActiveMonth['bidding_end_date']): ?>
                                    <p class="text-muted mb-3">
                                        <i class="fas fa-calendar-times"></i>
                                        Bidding ends: <?= date('d/m/Y', strtotime($currentActiveMonth['bidding_end_date'])) ?>
                                    </p>
                                <?php endif; ?>
                            <?php elseif ($currentActiveMonth['bidding_status'] === 'not_started'): ?>
                                <p class="text-info mb-2">
                                    <i class="fas fa-hourglass-start"></i>
                                    <strong>Coming Soon!</strong> Admin will open bidding for this month soon.
                                </p>
                                <p class="text-muted mb-3">
                                    <i class="fas fa-bell"></i>
                                    You'll be notified when bidding opens.
                                </p>
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-4">
                                    <strong><i class="fas fa-coins text-warning"></i> Collection:</strong><br>
                                    <span class="h5 text-success">₹<?= number_format($group['total_monthly_collection']) ?></span>
                                </div>
                                <div class="col-md-4">
                                    <strong><i class="fas fa-users text-info"></i> Total Bids:</strong><br>
                                    <span class="h5 text-primary">
                                        <?php
                                        $pdo = getDB();
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM member_bids WHERE group_id = ? AND month_number = ?");
                                        $stmt->execute([$groupId, $currentActiveMonth['month_number']]);
                                        $bidCount = $stmt->fetch()['count'];
                                        echo $bidCount;
                                        ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <strong><i class="fas fa-trophy text-warning"></i> Your Status:</strong><br>
                                    <span class="h6">
                                        <?php
                                        // Check if member has won in this group
                                        $stmt = $pdo->prepare("SELECT month_number FROM monthly_bids WHERE group_id = ? AND taken_by_member_id = ?");
                                        $stmt->execute([$groupId, $memberInGroup['id'] ?? 0]);
                                        $wonMonth = $stmt->fetch();
                                        ?>
                                        <?php if ($wonMonth): ?>
                                            <span class="text-muted">Won Month <?= $wonMonth['month_number'] ?></span>
                                        <?php else: ?>
                                            <?php
                                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM member_bids WHERE group_id = ? AND month_number = ? AND member_id = ?");
                                            $stmt->execute([$groupId, $currentActiveMonth['month_number'], $memberInGroup['id'] ?? 0]);
                                            $myBidCount = $stmt->fetch()['count'];
                                            ?>
                                            <?php if ($myBidCount > 0): ?>
                                                <span class="text-success">✓ Bid Placed</span>
                                            <?php else: ?>
                                                <span class="text-warning">⏳ Not Bid Yet</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 text-center">
                            <?php
                            // Check if member has won in this group
                            $stmt = $pdo->prepare("SELECT month_number FROM monthly_bids WHERE group_id = ? AND taken_by_member_id = ?");
                            $stmt->execute([$groupId, $memberInGroup['id'] ?? 0]);
                            $memberHasWon = $stmt->fetch();
                            ?>
                            <?php if ($currentActiveMonth['bidding_status'] === 'open' && !$memberHasWon): ?>
                                <?php
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM member_bids WHERE group_id = ? AND month_number = ? AND member_id = ?");
                                $stmt->execute([$groupId, $currentActiveMonth['month_number'], $memberInGroup['id'] ?? 0]);
                                $alreadyBid = $stmt->fetch()['count'] > 0;
                                ?>

                                <?php if (!$alreadyBid): ?>
                                    <div class="bid-form-highlight">
                                        <h5 class="text-dark mb-3">
                                            <i class="fas fa-gavel text-warning"></i> Place Your Bid
                                        </h5>
                                        <form method="POST">
                                            <input type="hidden" name="month_number" value="<?= $currentActiveMonth['month_number'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label text-dark">Bid Amount (₹)</label>
                                                <?php
                                                $minBid = $currentActiveMonth['minimum_bid_amount'] > 0 ? $currentActiveMonth['minimum_bid_amount'] : 1;
                                                $maxBid = $currentActiveMonth['maximum_bid_amount'] > 0 ?
                                                         $currentActiveMonth['maximum_bid_amount'] :
                                                         ($group['total_monthly_collection'] - 1);
                                                ?>
                                                <input type="number" name="bid_amount" class="form-control form-control-lg"
                                                       min="<?= $minBid ?>" max="<?= $maxBid ?>"
                                                       step="1" required placeholder="Enter your bid"
                                                       oninput="calculateBidImpact(this.value, <?= $group['total_monthly_collection'] ?>, <?= $group['total_members'] ?>)">
                                                <small class="form-text text-muted">
                                                    Range: ₹<?= number_format($minBid) ?> - ₹<?= number_format($maxBid) ?>
                                                    <?php if ($currentActiveMonth['minimum_bid_amount'] > 0): ?>
                                                        <br><strong>Minimum:</strong> ₹<?= number_format($currentActiveMonth['minimum_bid_amount']) ?>
                                                    <?php endif; ?>
                                                </small>

                                                <!-- Bid Impact Calculator -->
                                                <div id="bidImpactCalculator" class="mt-3" style="display: none;">
                                                    <div class="card bg-light">
                                                        <div class="card-body p-3">
                                                            <h6 class="text-dark mb-2">
                                                                <i class="fas fa-calculator text-primary"></i> If you bid ₹<span id="bidAmountDisplay">0</span>:
                                                            </h6>
                                                            <div class="row text-dark">
                                                                <div class="col-6">
                                                                    <strong class="text-success">You'll receive:</strong><br>
                                                                    <span class="h5 text-success">₹<span id="winnerAmount">0</span></span>
                                                                </div>
                                                                <div class="col-6">
                                                                    <strong class="text-info">Everyone pays:</strong><br>
                                                                    <span class="h5 text-info">₹<span id="perMemberAmount">0</span></span>
                                                                </div>
                                                            </div>
                                                            <div class="row text-dark mt-2">
                                                                <div class="col-12">
                                                                    <strong class="text-warning">Your net benefit:</strong>
                                                                    <span class="h5 text-warning">₹<span id="netBenefit">0</span></span>
                                                                </div>
                                                            </div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle"></i>
                                                                Calculation: Everyone pays (Winner Amount ÷ <?= $group['total_members'] ?> members). Winner Amount = (₹<?= number_format($group['total_monthly_collection']) ?> - Your Bid)
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="submit" name="submit_bid" class="btn btn-warning btn-lg w-100">
                                                <i class="fas fa-paper-plane"></i> Submit Bid
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i>
                                        <strong>Bid Submitted!</strong><br>
                                        You have placed your bid for this month.
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($memberHasWon): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-trophy"></i>
                                    <strong>Already Won!</strong><br>
                                    You won Month <?= $memberHasWon['month_number'] ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary">
                                    <i class="fas fa-clock"></i>
                                    <strong>Bidding Not Open</strong><br>
                                    Wait for admin to open bidding.
                                </div>
                            <?php endif; ?>

                            <button class="btn btn-outline-dark btn-sm mt-2"
                                    onclick="showBids(<?= $currentActiveMonth['month_number'] ?>)">
                                <i class="fas fa-eye"></i> View All Bids
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- All Months Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <h4><i class="fas fa-calendar-alt text-primary me-2"></i>All Months Overview</h4>
                <p class="text-muted">Complete history and upcoming months</p>
            </div>
        </div>

        <div class="row">
            <?php foreach ($availableMonths as $month): ?>
                <?php
                $isCurrentMonth = $currentActiveMonth && $month['month_number'] == $currentActiveMonth['month_number'];
                $cardClass = $isCurrentMonth ? 'current-month-highlight' : 'bid-status-' . str_replace('_', '-', $month['bidding_status']);
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card bidding-card <?= $cardClass ?>"><?php if ($isCurrentMonth): ?>
                        <div class="position-absolute top-0 start-50 translate-middle">
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-star"></i> CURRENT
                            </span>
                        </div>
                    <?php endif; ?>
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Month <?= $month['month_number'] ?>
                                <span class="badge bg-light text-dark ms-2"><?= ucfirst($month['bidding_status']) ?></span>
                            </h5>
                        </div>
                        <div class="card-body text-white">
                            <p class="mb-2">
                                <i class="fas fa-users"></i> Total Bids: <?= $month['total_bids'] ?>
                            </p>
                            
                            <?php if ($isCurrentMonth && $month['bidding_status'] === 'open' && !$memberHasWon && $month['my_bid_count'] == 0): ?>
                                <!-- Bid Form -->
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="month_number" value="<?= $month['month_number'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Your Bid Amount (₹)</label>
                                        <?php
                                        $minBid = $month['minimum_bid_amount'] > 0 ? $month['minimum_bid_amount'] : 1;
                                        $maxBid = $month['maximum_bid_amount'] > 0 ?
                                                 $month['maximum_bid_amount'] :
                                                 ($group['total_monthly_collection'] - 1);
                                        ?>
                                        <input type="number" name="bid_amount" class="form-control"
                                               min="<?= $minBid ?>" max="<?= $maxBid ?>"
                                               step="1" required>
                                        <small class="form-text text-light">
                                            Range: ₹<?= number_format($minBid) ?> - ₹<?= number_format($maxBid) ?>
                                            <?php if ($month['minimum_bid_amount'] > 0): ?>
                                                <br><strong>Min:</strong> ₹<?= number_format($month['minimum_bid_amount']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <button type="submit" name="submit_bid" class="btn btn-light">
                                        <i class="fas fa-paper-plane"></i> Submit Bid
                                    </button>
                                </form>
                            <?php elseif ($month['my_bid_count'] > 0): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-check-circle"></i> You have already placed a bid for this month
                                </div>
                            <?php elseif ($memberHasWon): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-trophy"></i> You have already won in Month <?= $memberHasWon['month_number'] ?>
                                </div>
                            <?php endif; ?>
                            
                            <a href="#" class="btn btn-outline-light btn-sm" 
                               onclick="showBids(<?= $month['month_number'] ?>)">
                                <i class="fas fa-eye"></i> View All Bids
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Bid Details Modal -->
    <div class="modal fade" id="bidModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-list"></i> Current Bids - <span id="modalMonthTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bidModalBody">
                    <!-- Bid details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeGroup(groupId) {
            window.location.href = 'member_bidding.php?group_id=' + groupId;
        }

        function showBids(monthNumber) {
            document.getElementById('modalMonthTitle').textContent = 'Month ' + monthNumber;

            // Load bids via AJAX
            fetch('get_month_bids.php?group_id=<?= $groupId ?>&month=' + monthNumber)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('bidModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('bidModal')).show();
                })
                .catch(error => {
                    document.getElementById('bidModalBody').innerHTML =
                        '<div class="alert alert-danger">Error loading bids</div>';
                    new bootstrap.Modal(document.getElementById('bidModal')).show();
                });
        }

        function calculateBidImpact(bidAmount, totalCollection, totalMembers) {
            const bid = parseFloat(bidAmount);

            if (isNaN(bid) || bid <= 0) {
                document.getElementById('bidImpactCalculator').style.display = 'none';
                return;
            }

            // BC System Logic:
            // 1. Winner gets: Total Collection - Bid Amount
            // 2. Everyone pays: Winner Amount ÷ Total Members (including winner)
            // 3. Net benefit: Winner gets - What winner pays

            const winnerAmount = totalCollection - bid;
            const perMemberAmount = winnerAmount / totalMembers;
            const netBenefit = winnerAmount - perMemberAmount;

            // Update display
            document.getElementById('bidAmountDisplay').textContent = bid.toLocaleString();
            document.getElementById('winnerAmount').textContent = winnerAmount.toLocaleString();
            document.getElementById('perMemberAmount').textContent = Math.round(perMemberAmount).toLocaleString();
            document.getElementById('netBenefit').textContent = Math.round(netBenefit).toLocaleString();

            // Show calculator
            document.getElementById('bidImpactCalculator').style.display = 'block';
        }

        // Auto-calculate on page load if there's a value
        document.addEventListener('DOMContentLoaded', function() {
            const bidInput = document.querySelector('input[name="bid_amount"]');
            if (bidInput && bidInput.value) {
                const totalCollection = <?= $group['total_monthly_collection'] ?? 0 ?>;
                const totalMembers = <?= $group['total_members'] ?? 0 ?>;
                calculateBidImpact(bidInput.value, totalCollection, totalMembers);
            }
        });
    </script>
</body>
</html>

