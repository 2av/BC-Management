<?php
require_once 'config.php';
requireAdminLogin();

$groupId = (int)($_GET['group_id'] ?? 0);
$monthNumber = (int)($_GET['month'] ?? 0);

if (!$groupId || !$monthNumber) {
    echo '<div class="alert alert-danger">Invalid parameters</div>';
    exit;
}

// Get group info
$group = getGroupById($groupId);

// Get all bids for this month
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT mb.*, m.member_name, m.member_number
    FROM member_bids mb
    JOIN members m ON mb.member_id = m.id
    WHERE mb.group_id = ? AND mb.month_number = ?
    ORDER BY mb.bid_amount ASC, mb.bid_date ASC
");
$stmt->execute([$groupId, $monthNumber]);
$bids = $stmt->fetchAll();

if (empty($bids)) {
    echo '<div class="alert alert-warning">No bids found for this month.</div>';
    exit;
}
?>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> <strong>Selection Rules:</strong>
    <ul class="mb-0 mt-2">
        <li>Lowest bid typically wins</li>
        <li>In case of tie, earliest bid wins</li>
        <li>Winner receives: ₹<?= number_format($group['total_monthly_collection']) ?> - Bid Amount</li>
        <li>Other members pay: (Total Collection - Bid Amount) ÷ <?= $group['total_members'] - 1 ?> remaining members</li>
    </ul>
</div>

<form method="POST" action="admin_bidding.php?group_id=<?= $groupId ?>">
    <input type="hidden" name="approve_winner" value="1">
    <input type="hidden" name="month_number" value="<?= $monthNumber ?>">
    
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Select</th>
                    <th>Rank</th>
                    <th>Member</th>
                    <th>Bid Amount</th>
                    <th>Net Amount</th>
                    <th>Per Member Payment</th>
                    <th>Bid Time</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                foreach ($bids as $bid): 
                    $netAmount = $group['total_monthly_collection'] - $bid['bid_amount'];
                    $perMemberPayment = $bid['bid_amount'] / ($group['total_members'] - 1);
                    $isLowest = ($rank === 1);
                ?>
                    <tr class="<?= $isLowest ? 'table-success' : '' ?>">
                        <td>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" 
                                       name="winner_member_id" value="<?= $bid['member_id'] ?>"
                                       id="winner_<?= $bid['member_id'] ?>"
                                       <?= $isLowest ? 'checked' : '' ?>
                                       onchange="updateWinnerDetails(<?= $bid['member_id'] ?>, <?= $bid['bid_amount'] ?>)">
                                <label class="form-check-label" for="winner_<?= $bid['member_id'] ?>">
                                    <?= $isLowest ? 'Recommended' : 'Select' ?>
                                </label>
                            </div>
                        </td>
                        <td>
                            <?php if ($isLowest): ?>
                                <i class="fas fa-crown text-warning"></i> #<?= $rank ?>
                            <?php else: ?>
                                #<?= $rank ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($bid['member_name']) ?></strong>
                            <br><small class="text-muted">Member #<?= $bid['member_number'] ?></small>
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
                    </tr>
                <?php 
                    $rank++;
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>
    
    <input type="hidden" name="winning_bid_amount" id="winning_bid_amount" value="<?= $bids[0]['bid_amount'] ?>">
    
    <div class="alert alert-warning">
        <h6><i class="fas fa-exclamation-triangle"></i> Confirmation Required</h6>
        <p id="winner_summary">
            Selected Winner: <strong><?= htmlspecialchars($bids[0]['member_name']) ?></strong><br>
            Winning Bid: <strong>₹<?= number_format($bids[0]['bid_amount']) ?></strong><br>
            Amount to Winner: <strong>₹<?= number_format($group['total_monthly_collection'] - $bids[0]['bid_amount']) ?></strong><br>
            Payment per remaining member: <strong>₹<?= number_format($bids[0]['bid_amount'] / ($group['total_members'] - 1), 2) ?></strong>
        </p>
    </div>
    
    <div class="d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times"></i> Cancel
        </button>
        <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this winner? This action cannot be undone.')">
            <i class="fas fa-check"></i> Approve Winner
        </button>
    </div>
</form>

<script>
function updateWinnerDetails(memberId, bidAmount) {
    const memberName = document.querySelector(`label[for="winner_${memberId}"]`).closest('tr').querySelector('strong').textContent;
    const totalCollection = <?= $group['total_monthly_collection'] ?>;
    const totalMembers = <?= $group['total_members'] ?>;
    const netAmount = totalCollection - bidAmount;
    const perMemberPayment = bidAmount / (totalMembers - 1);
    
    document.getElementById('winning_bid_amount').value = bidAmount;
    document.getElementById('winner_summary').innerHTML = `
        Selected Winner: <strong>${memberName}</strong><br>
        Winning Bid: <strong>₹${bidAmount.toLocaleString()}</strong><br>
        Amount to Winner: <strong>₹${netAmount.toLocaleString()}</strong><br>
        Payment per remaining member: <strong>₹${perMemberPayment.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
    `;
}
</script>
