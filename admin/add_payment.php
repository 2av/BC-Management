<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

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
$memberPayments = getMemberPayments($groupId);

// Get available months (months that have bids + upcoming months)
$monthsWithBids = array_column($monthlyBids, 'month_number');
$upcomingMonths = [];

// Add upcoming months (months without bids yet)
for ($i = 1; $i <= $group['total_members']; $i++) {
    if (!in_array($i, $monthsWithBids)) {
        $upcomingMonths[] = $i;
    }
}

// Organize existing payments
$existingPayments = [];
foreach ($memberPayments as $payment) {
    $existingPayments[$payment['member_id']][$payment['month_number']] = $payment;
}

// Check which months are fully paid (all members have paid)
$fullyPaidMonths = [];
foreach ($monthsWithBids as $monthNum) {
    $paidMembersCount = 0;
    foreach ($members as $member) {
        if (isset($existingPayments[$member['id']][$monthNum])) {
            $paidMembersCount++;
        }
    }
    if ($paidMembersCount >= count($members)) {
        $fullyPaidMonths[] = $monthNum;
    }
}

// Available months = months with bids that aren't fully paid + upcoming months
$availableMonths = array_merge(
    array_diff($monthsWithBids, $fullyPaidMonths),
    $upcomingMonths
);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $monthNumber = (int)($_POST['month_number'] ?? 0);
    $paymentAmount = (float)($_POST['payment_amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? '';
    $paymentStatus = $_POST['payment_status'] ?? 'paid';
    
    // Validation
    if (!$memberId) {
        $error = 'Please select a member.';
    } elseif (!in_array($monthNumber, $availableMonths)) {
        $error = 'This month is not available for payments or is already fully paid.';
    } elseif ($paymentAmount <= 0) {
        $error = 'Payment amount must be greater than 0.';
    } elseif (isset($existingPayments[$memberId][$monthNumber])) {
        $error = 'Payment for this member and month already exists.';
    } else {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();
            
            // Insert payment
            $stmt = $pdo->prepare("
                INSERT INTO member_payments (group_id, member_id, month_number, payment_amount, payment_status, payment_date) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $groupId, 
                $memberId, 
                $monthNumber, 
                $paymentAmount, 
                $paymentStatus, 
                $paymentDate ?: null
            ]);
            
            // Update member summary
            updateMemberSummary($groupId, $memberId);
            
            $pdo->commit();
            
            $memberName = '';
            foreach ($members as $member) {
                if ($member['id'] == $memberId) {
                    $memberName = $member['member_name'];
                    break;
                }
            }
            
            $success = "Payment for {$memberName} (Month {$monthNumber}) added successfully!";
            
            // Refresh data
            $memberPayments = getMemberPayments($groupId);
            $existingPayments = [];
            foreach ($memberPayments as $payment) {
                $existingPayments[$payment['member_id']][$payment['month_number']] = $payment;
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to add payment. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment - <?= APP_NAME ?></title>
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
            <h1>Add Member Payment</h1>
            <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Group
            </a>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><?= htmlspecialchars($group['group_name']) ?> - Add Payment</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($availableMonths)): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                No months available for payments. Please add monthly bids first.
                                <br><br>
                                <a href="add_bid.php?group_id=<?= $groupId ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Monthly Bid
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="paymentForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="member_id" class="form-label">Member *</label>
                                            <select class="form-control" id="member_id" name="member_id" required>
                                                <option value="">Select Member</option>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?= $member['id'] ?>" <?= ($_POST['member_id'] ?? '') == $member['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($member['member_name']) ?> (Member #<?= $member['member_number'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="month_number" class="form-label">Month *</label>
                                            <select class="form-control" id="month_number" name="month_number" required>
                                                <option value="">Select Month</option>

                                                <?php if (!empty(array_diff($monthsWithBids, $fullyPaidMonths))): ?>
                                                    <optgroup label="Months with Bids (Available for Payment)">
                                                        <?php foreach ($monthlyBids as $bid): ?>
                                                            <?php if (!in_array($bid['month_number'], $fullyPaidMonths)): ?>
                                                                <option value="<?= $bid['month_number'] ?>"
                                                                        data-amount="<?= $bid['gain_per_member'] ?>"
                                                                        data-type="bid"
                                                                        <?= ($_POST['month_number'] ?? '') == $bid['month_number'] ? 'selected' : '' ?>>
                                                                    Month <?= $bid['month_number'] ?> (Expected: <?= formatCurrency($bid['gain_per_member']) ?>)
                                                                </option>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endif; ?>

                                                <?php if (!empty($upcomingMonths)): ?>
                                                    <optgroup label="Upcoming Months (No Bid Yet)">
                                                        <?php foreach ($upcomingMonths as $monthNum): ?>
                                                            <option value="<?= $monthNum ?>"
                                                                    data-amount="<?= $group['monthly_contribution'] ?>"
                                                                    data-type="upcoming"
                                                                    <?= ($_POST['month_number'] ?? '') == $monthNum ? 'selected' : '' ?>>
                                                                Month <?= $monthNum ?> (Expected: <?= formatCurrency($group['monthly_contribution']) ?> - No bid yet)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endif; ?>
                                            </select>
                                            <div class="form-text">
                                                <small>
                                                    <i class="fas fa-info-circle"></i>
                                                    Months with bids show actual expected amounts. Upcoming months show base contribution.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="payment_amount" class="form-label">Payment Amount *</label>
                                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                                   value="<?= htmlspecialchars($_POST['payment_amount'] ?? '') ?>" 
                                                   min="0" step="0.01" required>
                                            <div class="form-text">Expected amount will be auto-filled when month is selected</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="payment_status" class="form-label">Payment Status *</label>
                                            <select class="form-control" id="payment_status" name="payment_status" required>
                                                <option value="paid" <?= ($_POST['payment_status'] ?? 'paid') === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                <option value="pending" <?= ($_POST['payment_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label">Payment Date</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?= htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')) ?>">
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Add Payment
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Payment Status Overview -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-pie"></i> Payment Status Overview
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($availableMonths)): ?>
                            <?php foreach ($monthlyBids as $bid): ?>
                                <div class="mb-3">
                                    <h6>Month <?= $bid['month_number'] ?></h6>
                                    <small class="text-muted">Expected: <?= formatCurrency($bid['gain_per_member']) ?></small>
                                    
                                    <?php
                                    $monthPayments = array_filter($memberPayments, fn($p) => $p['month_number'] == $bid['month_number']);
                                    $paidCount = count(array_filter($monthPayments, fn($p) => $p['payment_status'] === 'paid'));
                                    $totalMembers = count($members);
                                    ?>
                                    
                                    <div class="progress mt-1">
                                        <div class="progress-bar bg-success" style="width: <?= ($paidCount / $totalMembers) * 100 ?>%"></div>
                                    </div>
                                    <small><?= $paidCount ?> / <?= $totalMembers ?> paid</small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No months available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Add Multiple Payments -->
                <?php if (!empty($availableMonths)): ?>
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-lightning-bolt"></i> Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Add payments for all members at once:</p>
                            <?php foreach ($monthlyBids as $bid): ?>
                                <a href="bulk_payment.php?group_id=<?= $groupId ?>&month=<?= $bid['month_number'] ?>" 
                                   class="btn btn-outline-primary btn-sm d-block mb-2">
                                    <i class="fas fa-users"></i> All Members - Month <?= $bid['month_number'] ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill payment amount when month is selected
        document.getElementById('month_number').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const expectedAmount = selectedOption.getAttribute('data-amount');
            
            if (expectedAmount) {
                document.getElementById('payment_amount').value = expectedAmount;
            }
        });
        
        // Check for existing payments when member and month are selected
        function checkExistingPayment() {
            const memberId = document.getElementById('member_id').value;
            const monthNumber = document.getElementById('month_number').value;
            
            if (memberId && monthNumber) {
                // You could add AJAX call here to check if payment exists
                // For now, the server-side validation handles this
            }
        }
        
        document.getElementById('member_id').addEventListener('change', checkExistingPayment);
        document.getElementById('month_number').addEventListener('change', checkExistingPayment);
    </script>
</body>
</html>
