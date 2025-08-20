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
    } else {
        // Validate that the selected member belongs to this group
        $memberExists = false;
        foreach ($members as $member) {
            if ($member['id'] == $takenByMemberId) {
                $memberExists = true;
                break;
            }
        }
        if (!$memberExists) {
            $error = 'Selected member does not belong to this group.';
        } else {
            // Check if the selected member has already won a month
            $selectedMember = null;
            foreach ($members as $member) {
                if ($member['id'] == $takenByMemberId) {
                    $selectedMember = $member;
                    break;
                }
            }
            if ($selectedMember && isset($selectedMember['has_won_month']) && $selectedMember['has_won_month']) {
                $error = "Selected member has already won in Month {$selectedMember['has_won_month']}. Each member can only win once.";
            }
        }
    }

    if (!$error && $isBid === 'Yes' && $bidAmount <= 0) {
        $error = 'Bid amount must be greater than 0 when bidding.';
    } elseif (!$error && $isBid === 'Yes' && $bidAmount >= $group['total_monthly_collection']) {
        $error = 'Bid amount must be less than total monthly collection.';
    }

    if (!$error) {
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
            
            // Check if payments already exist for this month
            $stmt = $pdo->prepare("SELECT member_id FROM member_payments WHERE group_id = ? AND month_number = ?");
            $stmt->execute([$groupId, $monthNumber]);
            $existingPayments = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Add payments for all members for this month (only if they don't already exist)
            $stmt = $pdo->prepare("
                INSERT INTO member_payments (group_id, member_id, month_number, payment_amount, payment_status, payment_date)
                VALUES (?, ?, ?, ?, 'paid', ?)
            ");

            foreach ($members as $member) {
                // Skip if payment already exists for this member and month
                if (!in_array($member['id'], $existingPayments)) {
                    $stmt->execute([
                        $groupId,
                        $member['id'],
                        $monthNumber,
                        $gainPerMember,
                        $paymentDate ?: null
                    ]);
                }
            }
            
            // Update the winner member's won status
            $stmt = $pdo->prepare("UPDATE members SET has_won_month = ?, won_amount = ? WHERE id = ?");
            $stmt->execute([$monthNumber, $netPayable, $takenByMemberId]);

            // Update member summaries
            foreach ($members as $member) {
                updateMemberSummary($groupId, $member['id']);
            }
            
            $pdo->commit();
            
            setMessage("Monthly bid for Month {$monthNumber} added successfully!");
            redirect("view_group.php?id={$groupId}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            // Better error handling for debugging
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'unique_month_per_group') !== false) {
                    $error = 'This month already has a bid entry. Please refresh the page and try again.';
                } elseif (strpos($e->getMessage(), 'unique_payment_per_member_month') !== false) {
                    $error = 'Payment records for this month already exist. This might happen if the bid was partially added before.';
                } else {
                    $error = 'Duplicate entry detected. Please check if this bid already exists.';
                }
            } else {
                // Log the actual error for debugging (in production, log to file)
                error_log("Add bid error: " . $e->getMessage());
                $error = 'Failed to add bid. Error: ' . $e->getMessage();
            }
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

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong>First Month Guidelines:</strong>
                            <ul class="mb-0 mt-2">
                                <li>For the first month, typically the organizer takes the money (no bidding)</li>
                                <li>Set "Is Bid?" to "No" and "Bid Amount" to 0</li>
                                <li>Select the organizer as the member who takes the money</li>
                                <li>Members who have already won are shown in gray and cannot be selected again</li>
                            </ul>
                        </div>
                        
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
                                                <?php
                                                $hasWon = isset($member['has_won_month']) && $member['has_won_month'];
                                                $optionText = "#" . $member['member_number'] . " - " . htmlspecialchars($member['member_name']);
                                                if ($hasWon) {
                                                    $optionText .= " (Already won Month " . $member['has_won_month'] . ")";
                                                }
                                                ?>
                                                <option value="<?= $member['id'] ?>"
                                                        <?= ($_POST['taken_by_member_id'] ?? '') == $member['id'] ? 'selected' : '' ?>
                                                        <?= $hasWon ? 'style="color: #6c757d; font-style: italic;"' : '' ?>>
                                                    <?= $optionText ?>
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
