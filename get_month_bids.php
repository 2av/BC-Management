<?php
require_once 'config.php';
requireMemberLogin();

$groupId = (int)($_GET['group_id'] ?? 0);
$monthNumber = (int)($_GET['month'] ?? 0);

if (!$groupId || !$monthNumber) {
    echo '<div class="alert alert-danger">Invalid parameters</div>';
    exit;
}

// Verify member has access to this group
$member = getCurrentMember();
if ($member['group_id'] != $groupId) {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

// Get bidding status
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM month_bidding_status WHERE group_id = ? AND month_number = ?");
$stmt->execute([$groupId, $monthNumber]);
$biddingStatus = $stmt->fetch();

// Get all bids for this month
$stmt = $pdo->prepare("
    SELECT mb.*, m.member_name, m.member_number
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
    <div class="col-md-4">
        <strong>Bidding Status:</strong>
        <span class="badge bg-<?= $biddingStatus['bidding_status'] === 'open' ? 'success' : ($biddingStatus['bidding_status'] === 'closed' ? 'warning' : 'secondary') ?>">
            <?= ucfirst($biddingStatus['bidding_status']) ?>
        </span>
    </div>
    <div class="col-md-4">
        <strong>Total Collection:</strong> ₹<?= number_format($group['total_monthly_collection']) ?>
    </div>
    <div class="col-md-4">
        <strong>Bid Range:</strong>
        <?php if ($biddingStatus['minimum_bid_amount'] > 0 || $biddingStatus['maximum_bid_amount'] > 0): ?>
            ₹<?= number_format($biddingStatus['minimum_bid_amount']) ?> - ₹<?= number_format($biddingStatus['maximum_bid_amount']) ?>
        <?php else: ?>
            <span class="text-muted">Not set</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($biddingStatus['winner_member_id']): ?>
    <div class="alert alert-success">
        <i class="fas fa-trophy"></i> <strong>Winner Declared!</strong><br>
        Winner: Member #<?= $biddingStatus['winner_member_id'] ?> with bid of ₹<?= number_format($biddingStatus['winning_bid_amount']) ?>
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
                    <th><i class="fas fa-user"></i> Member</th>
                    <th><i class="fas fa-money-bill"></i> Bid Amount</th>
                    <th><i class="fas fa-calculator"></i> Net Amount</th>
                    <th><i class="fas fa-clock"></i> Bid Time</th>
                    <th><i class="fas fa-check-circle"></i> Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($bids as $bid): 
                    $netAmount = $group['total_monthly_collection'] - $bid['bid_amount'];
                    $isWinner = ($biddingStatus['winner_member_id'] == $bid['member_id']);
                    $isCurrentMember = ($bid['member_id'] == $member['id']);
                    
                    $rowClass = '';
                    if ($isWinner) $rowClass = 'table-success';
                    elseif ($isCurrentMember) $rowClass = 'table-warning';
                ?>
                    <tr class="<?= $rowClass ?>">
                        <td>
                            <?php if ($isWinner): ?>
                                <i class="fas fa-crown text-warning"></i> #<?= $rank ?>
                            <?php else: ?>
                                #<?= $rank ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($bid['member_name']) ?></strong>
                            <br><small class="text-muted">Member #<?= $bid['member_number'] ?></small>
                            <?php if ($isCurrentMember): ?>
                                <span class="badge bg-warning text-dark ms-1">You</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-primary">₹<?= number_format($bid['bid_amount']) ?></strong>
                        </td>
                        <td>
                            <strong class="text-success">₹<?= number_format($netAmount) ?></strong>
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
                    </tr>
                <?php 
                    $rank++;
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>

    <?php if ($biddingStatus['bidding_status'] === 'open'): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <strong>Bidding Rules:</strong>
            <ul class="mb-0 mt-2">
                <li>Lowest bid wins the month</li>
                <li>Winner receives: Total Collection - Bid Amount</li>
                <li>All other members pay: (Total Collection - Bid Amount) ÷ Remaining Members</li>
                <li>Members who have already won cannot bid again</li>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($biddingStatus['bidding_status'] === 'closed'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-hourglass-half"></i> Bidding is closed. Waiting for admin approval.
    </div>
<?php endif; ?>
