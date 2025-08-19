<?php
require_once 'config.php';
requireAdminLogin();

$groupId = (int)($_GET['group_id'] ?? 0);
if (!$groupId) {
    setMessage('Group ID is required.', 'error');
    redirect('index.php');
}

$group = getGroupById($groupId);
if (!$group) {
    setMessage('Group not found.', 'error');
    redirect('index.php');
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDB();
    
    if (isset($_POST['open_bidding'])) {
        $monthNumber = (int)$_POST['month_number'];
        $endDate = $_POST['end_date'];
        $minBidAmount = (float)$_POST['min_bid_amount'];
        $maxBidAmount = (float)$_POST['max_bid_amount'];

        // Validate bid amounts
        if ($minBidAmount < 0) {
            setMessage("Minimum bid amount cannot be negative", 'error');
        } elseif ($maxBidAmount <= $minBidAmount) {
            setMessage("Maximum bid amount must be greater than minimum bid amount", 'error');
        } else {
            $stmt = $pdo->prepare("
                UPDATE month_bidding_status
                SET bidding_status = 'open',
                    bidding_start_date = CURDATE(),
                    bidding_end_date = ?,
                    minimum_bid_amount = ?,
                    maximum_bid_amount = ?
                WHERE group_id = ? AND month_number = ?
            ");

            if ($stmt->execute([$endDate, $minBidAmount, $maxBidAmount, $groupId, $monthNumber])) {
                setMessage("Bidding opened for Month $monthNumber with bid range ₹" . number_format($minBidAmount) . " - ₹" . number_format($maxBidAmount), 'success');
            } else {
                setMessage("Failed to open bidding", 'error');
            }
        }
    }
    
    if (isset($_POST['close_bidding'])) {
        $monthNumber = (int)$_POST['month_number'];
        
        $stmt = $pdo->prepare("
            UPDATE month_bidding_status 
            SET bidding_status = 'closed'
            WHERE group_id = ? AND month_number = ?
        ");
        
        if ($stmt->execute([$groupId, $monthNumber])) {
            setMessage("Bidding closed for Month $monthNumber", 'success');
        } else {
            setMessage("Failed to close bidding", 'error');
        }
    }
    
    if (isset($_POST['approve_winner'])) {
        $monthNumber = (int)$_POST['month_number'];
        $winnerMemberId = (int)$_POST['winner_member_id'];
        $winningBidAmount = (float)$_POST['winning_bid_amount'];
        
        $pdo->beginTransaction();
        try {
            // Update bidding status
            $stmt = $pdo->prepare("
                UPDATE month_bidding_status 
                SET bidding_status = 'completed', 
                    winner_member_id = ?, 
                    winning_bid_amount = ?,
                    admin_approved_by = ?,
                    admin_approved_at = NOW()
                WHERE group_id = ? AND month_number = ?
            ");
            $stmt->execute([$winnerMemberId, $winningBidAmount, $_SESSION['admin_id'], $groupId, $monthNumber]);
            
            // Update member's won status
            $stmt = $pdo->prepare("
                UPDATE members 
                SET has_won_month = ?, won_amount = ?
                WHERE id = ?
            ");
            $stmt->execute([$monthNumber, $winningBidAmount, $winnerMemberId]);
            
            // Update monthly_bids table for compatibility
            $netPayable = $group['total_monthly_collection'] - $winningBidAmount;
            $gainPerMember = $netPayable / $group['total_members'];
            
            $stmt = $pdo->prepare("
                INSERT INTO monthly_bids 
                (group_id, month_number, taken_by_member_id, is_bid, bid_amount, net_payable, gain_per_member, payment_date)
                VALUES (?, ?, ?, 'Yes', ?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE
                taken_by_member_id = VALUES(taken_by_member_id),
                is_bid = VALUES(is_bid),
                bid_amount = VALUES(bid_amount),
                net_payable = VALUES(net_payable),
                gain_per_member = VALUES(gain_per_member),
                payment_date = VALUES(payment_date)
            ");
            $stmt->execute([$groupId, $monthNumber, $winnerMemberId, $winningBidAmount, $netPayable, $gainPerMember]);
            
            // Approve the winning bid
            $stmt = $pdo->prepare("
                UPDATE member_bids 
                SET bid_status = 'approved'
                WHERE group_id = ? AND month_number = ? AND member_id = ?
            ");
            $stmt->execute([$groupId, $monthNumber, $winnerMemberId]);
            
            // Reject other bids
            $stmt = $pdo->prepare("
                UPDATE member_bids 
                SET bid_status = 'rejected'
                WHERE group_id = ? AND month_number = ? AND member_id != ?
            ");
            $stmt->execute([$groupId, $monthNumber, $winnerMemberId]);
            
            $pdo->commit();
            setMessage("Winner approved for Month $monthNumber", 'success');
        } catch (Exception $e) {
            $pdo->rollback();
            setMessage("Failed to approve winner: " . $e->getMessage(), 'error');
        }
    }
    
    redirect("admin_bidding.php?group_id=$groupId");
}

// Get bidding status for all months
function getBiddingStatus($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT mbs.*, 
               m.member_name as winner_name,
               m.member_number as winner_number,
               (SELECT COUNT(*) FROM member_bids mb WHERE mb.group_id = mbs.group_id AND mb.month_number = mbs.month_number) as total_bids
        FROM month_bidding_status mbs
        LEFT JOIN members m ON mbs.winner_member_id = m.id
        WHERE mbs.group_id = ?
        ORDER BY mbs.month_number
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

// Get bids for a specific month
function getMonthBids($groupId, $monthNumber) {
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

$biddingStatus = getBiddingStatus($groupId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bidding Management - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-not-started { background: #f8f9fa; }
        .status-open { background: #d4edda; }
        .status-closed { background: #fff3cd; }
        .status-completed { background: #d1ecf1; }
        
        .bid-card {
            transition: all 0.3s ease;
        }
        .bid-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="fas fa-handshake text-warning me-2"></i><?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="view_group.php?id=<?= $groupId ?>">
                    <i class="fas fa-arrow-left"></i> Back to Group
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
                <h2><i class="fas fa-gavel text-primary me-2"></i>Bidding Management</h2>
                <p class="text-muted">Manage monthly bidding for <?= htmlspecialchars($group['group_name']) ?></p>
            </div>
        </div>

        <!-- Group Summary -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>Total Members:</strong> <?= $group['total_members'] ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Monthly Collection:</strong> ₹<?= number_format($group['total_monthly_collection']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Monthly Contribution:</strong> ₹<?= number_format($group['monthly_contribution']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Status:</strong> <?= ucfirst($group['status']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bidding Status Cards -->
        <div class="row">
            <?php foreach ($biddingStatus as $month): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card bid-card status-<?= str_replace('_', '-', $month['bidding_status']) ?>">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Month <?= $month['month_number'] ?>
                                <span class="badge bg-primary ms-2"><?= $month['total_bids'] ?> bids</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?= $month['bidding_status'] === 'open' ? 'success' : ($month['bidding_status'] === 'closed' ? 'warning' : ($month['bidding_status'] === 'completed' ? 'info' : 'secondary')) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $month['bidding_status'])) ?>
                                </span>
                            </p>
                            
                            <?php if ($month['winner_member_id']): ?>
                                <p><strong>Winner:</strong> <?= htmlspecialchars($month['winner_name']) ?> (Member #<?= $month['winner_number'] ?>)</p>
                                <p><strong>Winning Bid:</strong> ₹<?= number_format($month['winning_bid_amount']) ?></p>
                                <p><strong>Net Amount:</strong> ₹<?= number_format($group['total_monthly_collection'] - $month['winning_bid_amount']) ?></p>
                            <?php endif; ?>
                            
                            <div class="btn-group-vertical w-100 gap-2">
                                <?php if ($month['bidding_status'] === 'not_started'): ?>
                                    <button class="btn btn-success btn-sm" onclick="openBidding(<?= $month['month_number'] ?>)">
                                        <i class="fas fa-play"></i> Open Bidding
                                    </button>
                                <?php elseif ($month['bidding_status'] === 'open'): ?>
                                    <button class="btn btn-warning btn-sm" onclick="closeBidding(<?= $month['month_number'] ?>)">
                                        <i class="fas fa-stop"></i> Close Bidding
                                    </button>
                                <?php elseif ($month['bidding_status'] === 'closed'): ?>
                                    <button class="btn btn-primary btn-sm" onclick="selectWinner(<?= $month['month_number'] ?>)">
                                        <i class="fas fa-crown"></i> Select Winner
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($month['total_bids'] > 0): ?>
                                    <button class="btn btn-outline-primary btn-sm" onclick="viewBids(<?= $month['month_number'] ?>)">
                                        <i class="fas fa-eye"></i> View Bids (<?= $month['total_bids'] ?>)
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Winner Selection Modal -->
    <div class="modal fade" id="winnerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-crown"></i> Select Winner - <span id="winnerModalTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="winnerModalBody">
                    <!-- Winner selection content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- View Bids Modal -->
    <div class="modal fade" id="viewBidsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-list"></i> All Bids - <span id="viewBidsModalTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewBidsModalBody">
                    <!-- Bids content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openBidding(monthNumber) {
            // Create a modal for bid settings
            const modalHtml = `
                <div class="modal fade" id="openBiddingModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Open Bidding for Month ${monthNumber}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="open_bidding" value="1">
                                    <input type="hidden" name="month_number" value="${monthNumber}">

                                    <div class="mb-3">
                                        <label class="form-label">Bidding End Date</label>
                                        <input type="date" name="end_date" class="form-control"
                                               min="${new Date(Date.now() + 86400000).toISOString().split('T')[0]}"
                                               value="${new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0]}" required>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Minimum Bid Amount (₹)</label>
                                                <input type="number" name="min_bid_amount" class="form-control"
                                                       min="0" step="1" value="500" required>
                                                <small class="form-text text-muted">Lowest amount members can bid</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Maximum Bid Amount (₹)</label>
                                                <input type="number" name="max_bid_amount" class="form-control"
                                                       min="1" step="1" value="17999" required>
                                                <small class="form-text text-muted">Highest amount members can bid</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i> <strong>Bidding Rules:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Members can bid between the minimum and maximum amounts</li>
                                            <li>Lowest bid typically wins the month</li>
                                            <li>Winner receives: Total Collection - Bid Amount</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-play"></i> Open Bidding
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            const existingModal = document.getElementById('openBiddingModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('openBiddingModal'));
            modal.show();
        }
        
        function closeBidding(monthNumber) {
            if (confirm('Are you sure you want to close bidding for Month ' + monthNumber + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="close_bidding" value="1">
                    <input type="hidden" name="month_number" value="${monthNumber}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function selectWinner(monthNumber) {
            document.getElementById('winnerModalTitle').textContent = 'Month ' + monthNumber;

            // Load winner selection via AJAX
            fetch('admin_get_winner_selection.php?group_id=<?= $groupId ?>&month=' + monthNumber)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('winnerModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('winnerModal')).show();
                })
                .catch(error => {
                    document.getElementById('winnerModalBody').innerHTML =
                        '<div class="alert alert-danger">Error loading winner selection</div>';
                    new bootstrap.Modal(document.getElementById('winnerModal')).show();
                });
        }

        function viewBids(monthNumber) {
            document.getElementById('viewBidsModalTitle').textContent = 'Month ' + monthNumber;

            // Load bids via AJAX
            fetch('admin_get_month_bids.php?group_id=<?= $groupId ?>&month=' + monthNumber)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('viewBidsModalBody').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('viewBidsModal')).show();
                })
                .catch(error => {
                    document.getElementById('viewBidsModalBody').innerHTML =
                        '<div class="alert alert-danger">Error loading bids</div>';
                    new bootstrap.Modal(document.getElementById('viewBidsModal')).show();
                });
        }
    </script>
</body>
</html>
