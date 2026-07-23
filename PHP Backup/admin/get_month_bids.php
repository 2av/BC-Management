<?php
require_once 'config.php';
requireAdminLogin();

$groupId = (int)($_GET['group_id'] ?? 0);
$monthNumber = (int)($_GET['month'] ?? 0);

if (!$groupId || !$monthNumber) {
    echo '<div class="alert alert-danger">Invalid parameters</div>';
    exit;
}

// Get bidding status
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM month_bidding_status WHERE group_id = ? AND month_number = ?");
$stmt->execute([$groupId, $monthNumber]);
$biddingStatus = $stmt->fetch();

// Get all bids for this month
$stmt = $pdo->prepare("
    SELECT mb.*, m.member_name, m.member_number, m.phone, m.email
    FROM member_bids mb
    JOIN members m ON mb.member_id = m.id
    WHERE mb.group_id = ? AND mb.month_number = ?
    ORDER BY mb.bid_amount ASC, mb.bid_date ASC
");
$stmt->execute([$groupId, $monthNumber]);
$bids = $stmt->fetchAll();

// Get group info
$group = getGroupById($groupId);
?>

<div class="row mb-3">
    <div class="col-md-3">
        <strong>Bidding Status:</strong>
        <span class="badge bg-<?= $biddingStatus['bidding_status'] === 'open' ? 'success' : ($biddingStatus['bidding_status'] === 'closed' ? 'warning' : ($biddingStatus['bidding_status'] === 'completed' ? 'info' : 'secondary')) ?>">
            <?= ucfirst(str_replace('_', ' ', $biddingStatus['bidding_status'])) ?>
        </span>
    </div>
    <div class="col-md-3">
        <strong>Total Collection:</strong> ₹<?= number_format($group['total_monthly_collection']) ?>
    </div>
    <div class="col-md-3">
        <strong>Bid Range:</strong>
        <?php if ($biddingStatus['minimum_bid_amount'] > 0 || $biddingStatus['maximum_bid_amount'] > 0): ?>
            ₹<?= number_format($biddingStatus['minimum_bid_amount']) ?> - ₹<?= number_format($biddingStatus['maximum_bid_amount']) ?>
        <?php else: ?>
            <span class="text-muted">Not set</span>
        <?php endif; ?>
    </div>
    <div class="col-md-3">
        <strong>Total Bids:</strong> <?= count($bids) ?>
    </div>
</div>

<?php if ($biddingStatus['bidding_start_date']): ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <strong>Bidding Started:</strong> <?= date('d/m/Y', strtotime($biddingStatus['bidding_start_date'])) ?>
        </div>
        <?php if ($biddingStatus['bidding_end_date']): ?>
            <div class="col-md-6">
                <strong>Bidding Ends:</strong> <?= date('d/m/Y', strtotime($biddingStatus['bidding_end_date'])) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($biddingStatus['winner_member_id']): ?>
    <div class="alert alert-success">
        <i class="fas fa-trophy"></i> <strong>Winner Declared!</strong><br>
        <?php
        $stmt = $pdo->prepare("SELECT member_name, member_number FROM members WHERE id = ?");
        $stmt->execute([$biddingStatus['winner_member_id']]);
        $winner = $stmt->fetch();
        ?>
        Winner: <?= htmlspecialchars($winner['member_name']) ?> (Member #<?= $winner['member_number'] ?>) 
        with bid of ₹<?= number_format($biddingStatus['winning_bid_amount']) ?><br>
        <small>Approved on: <?= date('d/m/Y H:i', strtotime($biddingStatus['admin_approved_at'])) ?></small>
    </div>
<?php endif; ?>

<?php if (empty($bids)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No bids placed yet for this month.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><i class="fas fa-trophy"></i> Rank</th>
                    <th><i class="fas fa-user"></i> Member Details</th>
                    <th><i class="fas fa-money-bill"></i> Bid Amount</th>
                    <th><i class="fas fa-calculator"></i> Net Amount</th>
                    <th><i class="fas fa-users"></i> Per Member</th>
                    <th><i class="fas fa-clock"></i> Bid Time</th>
                    <th><i class="fas fa-check-circle"></i> Status</th>
                    <?php if ($biddingStatus['bidding_status'] === 'closed'): ?>
                        <th><i class="fas fa-cog"></i> Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($bids as $bid): 
                    $netAmount = $group['total_monthly_collection'] - $bid['bid_amount'];
                    $perMemberPayment = $bid['bid_amount'] / ($group['total_members'] - 1);
                    $isWinner = ($biddingStatus['winner_member_id'] == $bid['member_id']);
                    
                    $rowClass = '';
                    if ($isWinner) $rowClass = 'table-success';
                    elseif ($rank === 1 && !$biddingStatus['winner_member_id']) $rowClass = 'table-warning';
                ?>
                    <tr class="<?= $rowClass ?>">
                        <td>
                            <?php if ($isWinner): ?>
                                <i class="fas fa-crown text-warning"></i> #<?= $rank ?>
                            <?php elseif ($rank === 1 && !$biddingStatus['winner_member_id']): ?>
                                <i class="fas fa-star text-warning"></i> #<?= $rank ?>
                            <?php else: ?>
                                #<?= $rank ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($bid['member_name']) ?></strong>
                            <br><small class="text-muted">Member #<?= $bid['member_number'] ?></small>
                            <?php if ($bid['phone']): ?>
                                <br><small><i class="fas fa-phone"></i> <?= htmlspecialchars($bid['phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-primary">₹<?= number_format($bid['bid_amount']) ?></strong>
                        </td>
                        <td>
                            <strong class="text-success">₹<?= number_format($netAmount) ?></strong>
                        </td>
                        <td>
                            <span class="text-info">₹<?= number_format($perMemberPayment, 2) ?></span>
                        </td>
                        <td>
                            <small><?= date('d/m/Y H:i', strtotime($bid['bid_date'])) ?></small>
                        </td>
                        <td>
                            <?php if ($bid['bid_status'] === 'approved'): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check"></i> Approved
                                </span>
                            <?php elseif ($bid['bid_status'] === 'rejected'): ?>
                                <span class="badge bg-danger">
                                    <i class="fas fa-times"></i> Rejected
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php if ($biddingStatus['bidding_status'] === 'closed'): ?>
                            <td>
                                <?php if (!$biddingStatus['winner_member_id']): ?>
                                    <button class="btn btn-sm btn-success" 
                                            onclick="selectAsWinner(<?= $bid['member_id'] ?>, <?= $bid['bid_amount'] ?>, '<?= htmlspecialchars($bid['member_name']) ?>')">
                                        <i class="fas fa-crown"></i> Select
                                    </button>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php 
                    $rank++;
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> <strong>Bidding Summary:</strong>
        <ul class="mb-0 mt-2">
            <li><strong>Lowest Bid:</strong> ₹<?= number_format($bids[0]['bid_amount']) ?> by <?= htmlspecialchars($bids[0]['member_name']) ?></li>
            <li><strong>Highest Bid:</strong> ₹<?= number_format($bids[count($bids)-1]['bid_amount']) ?> by <?= htmlspecialchars($bids[count($bids)-1]['member_name']) ?></li>
            <li><strong>Average Bid:</strong> ₹<?= number_format(array_sum(array_column($bids, 'bid_amount')) / count($bids), 2) ?></li>
        </ul>
    </div>
<?php endif; ?>

<?php if ($biddingStatus['bidding_status'] === 'open'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-hourglass-half"></i> Bidding is currently open. Members can still place bids.
    </div>
<?php elseif ($biddingStatus['bidding_status'] === 'closed' && !$biddingStatus['winner_member_id']): ?>
    <div class="alert alert-info">
        <i class="fas fa-hand-point-up"></i> Bidding is closed. Please select a winner from the bids above.
    </div>
<?php endif; ?>

<script>
function selectAsWinner(memberId, bidAmount, memberName) {
    if (confirm(`Are you sure you want to select ${memberName} as the winner with bid amount ₹${bidAmount.toLocaleString()}?`)) {
        // Close this modal and open winner selection
        bootstrap.Modal.getInstance(document.getElementById('viewBidsModal')).hide();
        
        // Trigger winner selection
        setTimeout(() => {
            selectWinner(<?= $monthNumber ?>);
        }, 500);
    }
}
</script>
