
<?php
require_once 'config.php';
require_once 'languages/config.php';
requireAdminLogin();

$action = $_GET['action'] ?? 'summary';
$pdo = getDB();

if ($action === 'summary') {
    // Show group-wise summary of all pending payments
    $groups = getAllGroups();
    $groupSummaryData = [];
    $totalPendingAmount = 0;
    $totalPendingMembers = 0;

    foreach ($groups as $group) {
        $groupId = $group['id'];

        // Get all months with confirmed bids for this group
        $stmt = $pdo->prepare("
            SELECT mb.month_number, mb.gain_per_member, mb.bid_amount, m.member_name as winner_name
            FROM monthly_bids mb
            LEFT JOIN members m ON mb.taken_by_member_id = m.id
            WHERE mb.group_id = ?
            ORDER BY mb.month_number
        ");
        $stmt->execute([$groupId]);
        $confirmedMonths = $stmt->fetchAll();

        if (empty($confirmedMonths)) {
            continue;
        }

        $groupTotalPending = 0;
        $groupTotalPendingMembers = 0;
        $monthsWithPending = [];

        foreach ($confirmedMonths as $monthBid) {
            $monthNumber = $monthBid['month_number'];

            // Get all members in this group
            $members = getGroupMembers($groupId);

            // Get payments for this month
            $stmt = $pdo->prepare("
                SELECT mp.*, m.member_name, m.member_number
                FROM member_payments mp
                JOIN members m ON mp.member_id = m.id
                WHERE mp.group_id = ? AND mp.month_number = ?
            ");
            $stmt->execute([$groupId, $monthNumber]);
            $payments = $stmt->fetchAll();

            // Organize payments by member
            $paymentsByMember = [];
            foreach ($payments as $payment) {
                $paymentsByMember[$payment['member_id']] = $payment;
            }

            // Count pending members for this month
            $monthPendingAmount = 0;
            $monthPendingCount = 0;

            foreach ($members as $member) {
                $payment = $paymentsByMember[$member['id']] ?? null;

                if (!$payment || $payment['payment_status'] === 'pending') {
                    $monthPendingAmount += $monthBid['gain_per_member'];
                    $monthPendingCount++;
                }
            }

            if ($monthPendingCount > 0) {
                $monthsWithPending[] = [
                    'month_number' => $monthNumber,
                    'pending_amount' => $monthPendingAmount,
                    'pending_count' => $monthPendingCount,
                    'total_members' => count($members),
                    'winner_name' => $monthBid['winner_name'],
                    'bid_amount' => $monthBid['bid_amount']
                ];

                $groupTotalPending += $monthPendingAmount;
                $groupTotalPendingMembers += $monthPendingCount;
            }
        }

        if (!empty($monthsWithPending)) {
            $groupSummaryData[] = [
                'group' => $group,
                'total_pending_amount' => $groupTotalPending,
                'total_pending_members' => $groupTotalPendingMembers,
                'months_with_pending' => $monthsWithPending,
                'months_count' => count($monthsWithPending)
            ];

            $totalPendingAmount += $groupTotalPending;
            $totalPendingMembers += $groupTotalPendingMembers;
        }
    }

    if (empty($groupSummaryData)) {
        echo '<div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>No pending payments found across all groups</strong>
            <p class="mb-0 mt-2">This could mean:</p>
            <ul class="mb-0 mt-1">
                <li>No groups have confirmed bids yet</li>
                <li>All members have completed their payments</li>
                <li>No bidding processes have started</li>
            </ul>
        </div>';
        exit;
    }
    ?>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h5 class="text-warning mb-1">
                        <i class="fas fa-exclamation-triangle"></i> Total Pending
                    </h5>
                    <h3 class="text-dark"><?= formatCurrency($totalPendingAmount) ?></h3>
                    <small class="text-muted"><?= $totalPendingMembers ?> members</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h5 class="text-info mb-1">
                        <i class="fas fa-users"></i> Active Groups
                    </h5>
                    <h3 class="text-dark"><?= count($groupSummaryData) ?></h3>
                    <small class="text-muted">with pending payments</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h5 class="text-success mb-1">
                        <i class="fas fa-calendar-alt"></i> Total Months
                    </h5>
                    <h3 class="text-dark"><?= array_sum(array_column($groupSummaryData, 'months_count')) ?></h3>
                    <small class="text-muted">with pending payments</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Group-wise Direct Display -->
    <?php foreach ($groupSummaryData as $index => $groupData): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i><?= htmlspecialchars($groupData['group']['group_name']) ?>
                        </h5>
                        <small><?= count($groupData['months_with_pending']) ?> months with pending payments</small>
                    </div>
                    <div class="text-end">
                        <div class="h4 mb-0"><?= formatCurrency($groupData['total_pending_amount']) ?></div>
                        <small><?= $groupData['total_pending_members'] ?> pending members</small>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php foreach ($groupData['months_with_pending'] as $monthData): ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-primary mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Month <?= $monthData['month_number'] ?>
                            </h6>
                            <span class="badge bg-warning text-dark">
                                <?= $monthData['pending_count'] ?>/<?= $monthData['total_members'] ?> pending
                            </span>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <small class="text-muted">Winner:</small><br>
                                <strong><?= htmlspecialchars($monthData['winner_name']) ?></strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Bid Amount:</small><br>
                                <strong><?= formatCurrency($monthData['bid_amount']) ?></strong>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Pending Amount:</small><br>
                                <strong class="text-warning"><?= formatCurrency($monthData['pending_amount']) ?></strong>
                            </div>
                        </div>

                        <!-- Show Pending Members Directly -->
                        <?php
                        // Get pending members for this month
                        $groupId = $groupData['group']['id'];
                        $monthNumber = $monthData['month_number'];

                        // Get all members in this group
                        $members = getGroupMembers($groupId);

                        // Get payments for this month
                        $stmt = $pdo->prepare("
                            SELECT mp.*, m.member_name, m.member_number
                            FROM member_payments mp
                            JOIN members m ON mp.member_id = m.id
                            WHERE mp.group_id = ? AND mp.month_number = ?
                        ");
                        $stmt->execute([$groupId, $monthNumber]);
                        $payments = $stmt->fetchAll();

                        // Organize payments by member
                        $paymentsByMember = [];
                        foreach ($payments as $payment) {
                            $paymentsByMember[$payment['member_id']] = $payment;
                        }

                        // Get monthly bid details
                        $stmt = $pdo->prepare("
                            SELECT gain_per_member FROM monthly_bids
                            WHERE group_id = ? AND month_number = ?
                        ");
                        $stmt->execute([$groupId, $monthNumber]);
                        $monthBid = $stmt->fetch();
                        $expectedAmount = $monthBid ? $monthBid['gain_per_member'] : 0;

                        // Separate pending members
                        $pendingMembers = [];
                        foreach ($members as $member) {
                            $payment = $paymentsByMember[$member['id']] ?? null;
                            if (!$payment || $payment['payment_status'] === 'pending') {
                                $pendingMembers[] = [
                                    'member' => $member,
                                    'payment' => $payment,
                                    'expected_amount' => $expectedAmount
                                ];
                            }
                        }
                        ?>

                        <?php if (!empty($pendingMembers)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead class="table-warning">
                                        <tr>
                                            <th><i class="fas fa-user me-1"></i>Member</th>
                                            <th><i class="fas fa-id-badge me-1"></i>Number</th>
                                            <th><i class="fas fa-money-bill me-1"></i>Expected Amount</th>
                                            <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                            <th><i class="fas fa-cog me-1"></i>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingMembers as $memberData): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($memberData['member']['member_name']) ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">#<?= $memberData['member']['member_number'] ?></span>
                                                </td>
                                                <td>
                                                    <span class="text-warning fw-bold"><?= formatCurrency($memberData['expected_amount']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $memberData['payment'] ? 'warning' : 'secondary' ?>">
                                                        <?= $memberData['payment'] ? 'Pending' : 'Not Recorded' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="add_payment.php?group_id=<?= $groupId ?>&member_id=<?= $memberData['member']['id'] ?>&month=<?= $monthNumber ?>"
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-plus"></i> Add Payment
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($monthData !== end($groupData['months_with_pending'])): ?>
                            <hr>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="text-center border-top pt-3">
                    <a href="view_group.php?id=<?= $groupData['group']['id'] ?>" class="btn btn-outline-success me-2">
                        <i class="fas fa-chart-bar"></i> Group Dashboard
                    </a>
                    <a href="admin_members.php?group_filter=<?= $groupData['group']['id'] ?>" class="btn btn-outline-info me-2">
                        <i class="fas fa-users"></i> All Members
                    </a>
                    <a href="bulk_payment.php?group_id=<?= $groupData['group']['id'] ?>" class="btn btn-outline-warning">
                        <i class="fas fa-plus"></i> Add Bulk Payments
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>


<?php
} else if ($action === 'month_details') {
    // Show member details for a specific group and month
    $groupId = (int)($_GET['group_id'] ?? 0);
    $monthNumber = (int)($_GET['month'] ?? 0);

    if (!$groupId || !$monthNumber) {
        echo '<div class="alert alert-danger">Invalid group or month parameters</div>';
        exit;
    }

    $group = getGroupById($groupId);
    if (!$group) {
        echo '<div class="alert alert-danger">Group not found</div>';
        exit;
    }

    // Get the monthly bid for this month
    $stmt = $pdo->prepare("
        SELECT mb.*, m.member_name as winner_name
        FROM monthly_bids mb
        LEFT JOIN members m ON mb.taken_by_member_id = m.id
        WHERE mb.group_id = ? AND mb.month_number = ?
    ");
    $stmt->execute([$groupId, $monthNumber]);
    $monthlyBid = $stmt->fetch();

    // Initialize default values
    $paidMembers = [];
    $pendingMembers = [];
    $paidCount = 0;
    $pendingCount = 0;

    if (!$monthlyBid) {
        echo '<div class="alert alert-warning">No confirmed bid found for this month</div>';
        // Set default values for display
        $monthlyBid = ['gain_per_member' => 0];
    } else {
        // Get all members in this group
        $members = getGroupMembers($groupId);

        // Get payments for this month
        $stmt = $pdo->prepare("
            SELECT mp.*, m.member_name, m.member_number
            FROM member_payments mp
            JOIN members m ON mp.member_id = m.id
            WHERE mp.group_id = ? AND mp.month_number = ?
            ORDER BY m.member_number
        ");
        $stmt->execute([$groupId, $monthNumber]);
        $payments = $stmt->fetchAll();

        // Organize payments by member
        $paymentsByMember = [];
        foreach ($payments as $payment) {
            $paymentsByMember[$payment['member_id']] = $payment;
        }

        // Separate paid and pending members
        foreach ($members as $member) {
            $payment = $paymentsByMember[$member['id']] ?? null;

            if ($payment && $payment['payment_status'] === 'paid') {
                $paidMembers[] = [
                    'member' => $member,
                    'payment' => $payment
                ];
            } else {
                $pendingMembers[] = [
                    'member' => $member,
                    'payment' => $payment,
                    'expected_amount' => $monthlyBid['gain_per_member']
                ];
            }
        }

        // Count members for display
        $paidCount = count($paidMembers);
        $pendingCount = count($pendingMembers);
    }
    ?>

    <!-- Month Details Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-1">
                <i class="fas fa-users text-primary"></i> <?= htmlspecialchars($group['group_name']) ?>
                <span class="text-muted">- Month <?= $monthNumber ?></span>
            </h5>
            <small class="text-muted">Winner: <?= htmlspecialchars($monthlyBid['winner_name']) ?> | Bid: <?= formatCurrency($monthlyBid['bid_amount']) ?></small>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="loadPendingPayments()">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </button>
    </div>

    <!-- Payment Status Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="text-success mb-1"><i class="fas fa-check-circle"></i> Paid</h6>
                    <h4 class="text-dark"><?= $paidCount ?></h4>
                    <small class="text-muted">members</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="text-warning mb-1"><i class="fas fa-clock"></i> Pending</h6>
                    <h4 class="text-dark"><?= $pendingCount ?></h4>
                    <small class="text-muted">members</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="text-info mb-1"><i class="fas fa-money-bill"></i> Per Member</h6>
                    <h4 class="text-dark"><?= formatCurrency($monthlyBid['gain_per_member']) ?></h4>
                    <small class="text-muted">amount</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Member Lists -->
    <div class="row">
        <!-- Pending Members -->
        <?php if (!empty($pendingMembers)): ?>
        <div class="col-md-6">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Pending Payments (<?= count($pendingMembers) ?>)
                    </h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($pendingMembers as $memberData): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-light rounded">
                            <div>
                                <strong><?= htmlspecialchars($memberData['member']['member_name']) ?></strong>
                                <small class="text-muted d-block">#<?= $memberData['member']['member_number'] ?></small>
                            </div>
                            <div class="text-end">
                                <div class="text-warning fw-bold"><?= formatCurrency($memberData['expected_amount']) ?></div>
                                <small class="badge bg-<?= $memberData['payment'] ? 'warning' : 'secondary' ?>">
                                    <?= $memberData['payment'] ? 'Pending' : 'Not Recorded' ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer text-center">
                    <a href="bulk_payment.php?group_id=<?= $groupId ?>&month=<?= $monthNumber ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-plus"></i> Add Bulk Payments
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Paid Members -->
        <?php if (!empty($paidMembers)): ?>
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-check-circle"></i> Completed Payments (<?= count($paidMembers) ?>)
                    </h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($paidMembers as $memberData): ?>
                        <div class="d-flex justify-content-between align-items-center p-2 mb-2 bg-light rounded">
                            <div>
                                <strong><?= htmlspecialchars($memberData['member']['member_name']) ?></strong>
                                <small class="text-muted d-block">#<?= $memberData['member']['member_number'] ?></small>
                            </div>
                            <div class="text-end">
                                <div class="text-success fw-bold"><?= formatCurrency($memberData['payment']['payment_amount']) ?></div>
                                <small class="text-muted">
                                    <?= $memberData['payment']['payment_date'] ? formatDate($memberData['payment']['payment_date']) : 'No date' ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php
}

