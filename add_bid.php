<?php
require_once 'config.php';
requireAdminLogin();

$groupId = (int)($_GET['group_id'] ?? 0);
if (!$groupId) {
    redirect('index.php');
}

$group = getGroupById($groupId);
if (!$group) {
    setMessage('Group not found.', 'error');
    redirect('index.php');
}

$members = getGroupMembers($groupId);
$monthlyBids = getMonthlyBids($groupId);
$usedMonths = array_column($monthlyBids, 'month_number');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monthNumber = (int)($_POST['month_number'] ?? 0);
    $takenByMemberId = (int)($_POST['taken_by_member_id'] ?? 0);
    $isBid = $_POST['is_bid'] ?? 'No';
    $bidAmount = (float)($_POST['bid_amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? '';
    
    // Validation
    if ($monthNumber < 1 || $monthNumber > $group['total_members']) {
        $error = 'Invalid month number.';
    } elseif (in_array($monthNumber, $usedMonths)) {
        $error = 'This month already has a bid entry.';
    } elseif (!$takenByMemberId) {
        $error = 'Please select a member.';
    } elseif ($isBid === 'Yes' && $bidAmount <= 0) {
        $error = 'Bid amount must be greater than 0 when bidding.';
    } elseif ($isBid === 'Yes' && $bidAmount >= $group['total_monthly_collection']) {
        $error = 'Bid amount must be less than total monthly collection.';
    } else {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();
            
            // Calculate net payable and gain per member
            $netPayable = $group['total_monthly_collection'] - $bidAmount;
            $gainPerMember = $netPayable / $group['total_members'];
            
            // Insert monthly bid
            $stmt = $pdo->prepare("
                INSERT INTO monthly_bids (group_id, month_number, taken_by_member_id, is_bid, bid_amount, net_payable, gain_per_member, payment_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $groupId, 
                $monthNumber, 
                $takenByMemberId, 
                $isBid, 
                $bidAmount, 
                $netPayable, 
                $gainPerMember, 
                $paymentDate ?: null
            ]);
            
            // Add payments for all members for this month
            $stmt = $pdo->prepare("
                INSERT INTO member_payments (group_id, member_id, month_number, payment_amount, payment_status, payment_date) 
                VALUES (?, ?, ?, ?, 'paid', ?)
            ");
            
            foreach ($members as $member) {
                $stmt->execute([
                    $groupId, 
                    $member['id'], 
                    $monthNumber, 
                    $gainPerMember, 
                    $paymentDate ?: null
                ]);
            }
            
            // Update member summaries
            foreach ($members as $member) {
                updateMemberSummary($groupId, $member['id']);
            }
            
            $pdo->commit();
            
            setMessage("Monthly bid for Month {$monthNumber} added successfully!");
            redirect("view_group.php?id={$groupId}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to add bid. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Monthly Bid - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins"></i> <?= APP_NAME ?>
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Add Monthly Bid</h1>
            <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Group
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?= htmlspecialchars($group['group_name']) ?> - Monthly Bid</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="bidForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="month_number" class="form-label">Month Number *</label>
                                        <select class="form-control" id="month_number" name="month_number" required>
                                            <option value="">Select Month</option>
                                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                                <?php if (!in_array($i, $usedMonths)): ?>
                                                    <option value="<?= $i ?>" <?= ($_POST['month_number'] ?? '') == $i ? 'selected' : '' ?>>
                                                        Month <?= $i ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="taken_by_member_id" class="form-label">Taken By Member *</label>
                                        <select class="form-control" id="taken_by_member_id" name="taken_by_member_id" required>
                                            <option value="">Select Member</option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?= $member['id'] ?>" <?= ($_POST['taken_by_member_id'] ?? '') == $member['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($member['member_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="is_bid" class="form-label">Is Bid? *</label>
                                        <select class="form-control" id="is_bid" name="is_bid" required>
                                            <option value="No" <?= ($_POST['is_bid'] ?? 'No') === 'No' ? 'selected' : '' ?>>No</option>
                                            <option value="Yes" <?= ($_POST['is_bid'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="bid_amount" class="form-label">Bid Amount</label>
                                        <input type="number" class="form-control" id="bid_amount" name="bid_amount" 
                                               value="<?= htmlspecialchars($_POST['bid_amount'] ?? '') ?>" 
                                               min="0" step="50" placeholder="0">
                                        <div class="form-text">Enter 0 if no bid (organizer takes)</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="payment_date" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                       value="<?= htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')) ?>">
                            </div>
                            
                            <!-- Calculated Values -->
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h6>Calculated Values</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong>Total Collection:</strong>
                                            <div class="text-primary"><?= formatCurrency($group['total_monthly_collection']) ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Net Payable:</strong>
                                            <div class="text-success" id="netPayable"><?= formatCurrency($group['total_monthly_collection']) ?></div>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Gain Per Member:</strong>
                                            <div class="text-info" id="gainPerMember"><?= formatCurrency($group['monthly_contribution']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Bid
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateCalculations() {
            const totalCollection = <?= $group['total_monthly_collection'] ?>;
            const totalMembers = <?= $group['total_members'] ?>;
            const bidAmount = parseFloat(document.getElementById('bid_amount').value) || 0;
            
            const netPayable = totalCollection - bidAmount;
            const gainPerMember = netPayable / totalMembers;
            
            document.getElementById('netPayable').textContent = '₹' + netPayable.toLocaleString();
            document.getElementById('gainPerMember').textContent = '₹' + gainPerMember.toLocaleString();
        }
        
        document.getElementById('bid_amount').addEventListener('input', updateCalculations);
        document.getElementById('is_bid').addEventListener('change', function() {
            const bidAmountField = document.getElementById('bid_amount');
            if (this.value === 'No') {
                bidAmountField.value = '0';
                bidAmountField.disabled = true;
            } else {
                bidAmountField.disabled = false;
            }
            updateCalculations();
        });
        
        // Initial setup
        updateCalculations();
        if (document.getElementById('is_bid').value === 'No') {
            document.getElementById('bid_amount').disabled = true;
        }
    </script>
</body>
</html>
