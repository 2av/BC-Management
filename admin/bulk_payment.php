<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$groupId = (int)($_GET['group_id'] ?? 0);
$monthNumber = (int)($_GET['month'] ?? 0);

if (!$groupId || !$monthNumber) {
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

// Get the specific month bid (if exists)
$currentBid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $monthNumber);
$currentBid = reset($currentBid);

// If no bid exists, this is an upcoming month
$isUpcomingMonth = !$currentBid;
$expectedAmount = $isUpcomingMonth ? $group['monthly_contribution'] : $currentBid['gain_per_member'];
$winnerName = $isUpcomingMonth ? 'No bid yet' : $currentBid['member_name'];

// Get existing payments for this month
$existingPayments = [];
foreach ($memberPayments as $payment) {
    if ($payment['month_number'] == $monthNumber) {
        $existingPayments[$payment['member_id']] = $payment;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $memberPayments = $_POST['member_payments'] ?? [];
    
    if (empty($memberPayments)) {
        $error = 'Please select at least one member to add payments for.';
    } else {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();
            
            $addedCount = 0;
            $skippedCount = 0;
            
            foreach ($memberPayments as $memberId => $paymentData) {
                $memberId = (int)$memberId;
                $amount = (float)($paymentData['amount'] ?? 0);
                $status = $paymentData['status'] ?? 'paid';
                
                // Skip if payment already exists
                if (isset($existingPayments[$memberId])) {
                    $skippedCount++;
                    continue;
                }
                
                // Skip if amount is 0 or negative
                if ($amount <= 0) {
                    continue;
                }
                
                // Insert payment
                $stmt = $pdo->prepare("
                    INSERT INTO member_payments (group_id, member_id, month_number, payment_amount, payment_status, payment_date) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$groupId, $memberId, $monthNumber, $amount, $status, $paymentDate]);
                
                // Update member summary
                updateMemberSummary($groupId, $memberId);
                
                $addedCount++;
            }
            
            $pdo->commit();
            
            $success = "Successfully added {$addedCount} payments for Month {$monthNumber}.";
            if ($skippedCount > 0) {
                $success .= " Skipped {$skippedCount} members who already have payments.";
            }
            
            // Refresh existing payments
            $memberPayments = getMemberPayments($groupId);
            $existingPayments = [];
            foreach ($memberPayments as $payment) {
                if ($payment['month_number'] == $monthNumber) {
                    $existingPayments[$payment['member_id']] = $payment;
                }
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to add bulk payments. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Payment - Month <?= $monthNumber ?> - <?= APP_NAME ?></title>
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
            <h1>Bulk Payment - Month <?= $monthNumber ?></h1>
            <div>
                <a href="add_payment.php?group_id=<?= $groupId ?>" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-user"></i> Single Payment
                </a>
                <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Group
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?= htmlspecialchars($group['group_name']) ?> - Month <?= $monthNumber ?> Payments
                        </h5>
                        <small class="text-muted">
                            Winner: <?= htmlspecialchars($winnerName) ?> |
                            Expected Amount: <?= formatCurrency($expectedAmount) ?> per member
                            <?php if ($isUpcomingMonth): ?>
                                <span class="badge bg-warning ms-2">Upcoming Month</span>
                            <?php endif; ?>
                        </small>
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
                        
                        <form method="POST" id="bulkPaymentForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="payment_date" class="form-label">Payment Date</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?= htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')) ?>">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-primary" onclick="selectAll()">
                                            <i class="fas fa-check-square"></i> Select All
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="selectNone()">
                                            <i class="fas fa-square"></i> Select None
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="fillExpectedAmounts()">
                                            <i class="fas fa-fill"></i> Fill Expected Amounts
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                            </th>
                                            <th>Member</th>
                                            <th>Expected Amount</th>
                                            <th>Payment Amount</th>
                                            <th>Status</th>
                                            <th>Current Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($members as $member): ?>
                                            <?php $hasPayment = isset($existingPayments[$member['id']]); ?>
                                            <tr class="<?= $hasPayment ? 'table-success' : '' ?>">
                                                <td>
                                                    <?php if (!$hasPayment): ?>
                                                        <input type="checkbox" name="member_payments[<?= $member['id'] ?>][selected]" 
                                                               value="1" class="member-checkbox">
                                                    <?php else: ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($member['member_name']) ?></strong>
                                                    <br><small class="text-muted">Member #<?= $member['member_number'] ?></small>
                                                </td>
                                                <td>
                                                    <span class="text-primary fw-bold">
                                                        <?= formatCurrency($expectedAmount) ?>
                                                    </span>
                                                    <?php if ($isUpcomingMonth): ?>
                                                        <br><small class="text-muted">Base contribution</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$hasPayment): ?>
                                                        <input type="number"
                                                               name="member_payments[<?= $member['id'] ?>][amount]"
                                                               class="form-control form-control-sm payment-amount"
                                                               value="<?= $expectedAmount ?>"
                                                               min="0" step="0.01" style="width: 120px;">
                                                    <?php else: ?>
                                                        <span class="text-success fw-bold">
                                                            <?= formatCurrency($existingPayments[$member['id']]['payment_amount']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!$hasPayment): ?>
                                                        <select name="member_payments[<?= $member['id'] ?>][status]" 
                                                                class="form-select form-select-sm" style="width: 100px;">
                                                            <option value="paid">Paid</option>
                                                            <option value="pending">Pending</option>
                                                        </select>
                                                    <?php else: ?>
                                                        <span class="badge bg-<?= $existingPayments[$member['id']]['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                                            <?= ucfirst($existingPayments[$member['id']]['payment_status']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($hasPayment): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> Already Paid
                                                        </span>
                                                        <br><small class="text-muted">
                                                            <?= formatDate($existingPayments[$member['id']]['payment_date']) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-clock"></i> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div>
                                    <span class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        <?= count($existingPayments) ?> of <?= count($members) ?> members have already paid for this month
                                    </span>
                                </div>
                                <div>
                                    <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Add Selected Payments
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(checkbox) {
            const memberCheckboxes = document.querySelectorAll('.member-checkbox');
            memberCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }
        
        function selectAll() {
            const memberCheckboxes = document.querySelectorAll('.member-checkbox');
            memberCheckboxes.forEach(cb => {
                cb.checked = true;
            });
            document.getElementById('selectAllCheckbox').checked = true;
        }
        
        function selectNone() {
            const memberCheckboxes = document.querySelectorAll('.member-checkbox');
            memberCheckboxes.forEach(cb => {
                cb.checked = false;
            });
            document.getElementById('selectAllCheckbox').checked = false;
        }
        
        function fillExpectedAmounts() {
            const expectedAmount = <?= $expectedAmount ?>;
            const amountInputs = document.querySelectorAll('.payment-amount');
            amountInputs.forEach(input => {
                input.value = expectedAmount;
            });
        }
        
        // Update select all checkbox when individual checkboxes change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('member-checkbox')) {
                const memberCheckboxes = document.querySelectorAll('.member-checkbox');
                const checkedBoxes = document.querySelectorAll('.member-checkbox:checked');
                const selectAllCheckbox = document.getElementById('selectAllCheckbox');
                
                if (checkedBoxes.length === memberCheckboxes.length) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (checkedBoxes.length === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            }
        });
    </script>
</body>
</html>
