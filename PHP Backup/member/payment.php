<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('member');

$member = getCurrentMember();
$groupId = $_SESSION['group_id'];
$group = getGroupById($groupId);
$monthNumber = (int)($_GET['month'] ?? 0);

// If no month specified, show month selection page
if (!$monthNumber) {
    // Get all groups this member belongs to
    $memberGroups = getMemberGroups($member['id']);

    // Set page title for the header
    $page_title = 'Select Payment Month';

    // Include the member header
    require_once 'includes/header.php';
    ?>

    <!-- Month Selection Page -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-credit-card"></i> Select Month for Payment
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Choose a month to make payment for:</p>

                    <?php foreach ($memberGroups as $groupInfo): ?>
                        <?php
                        $gId = $groupInfo['id'];
                        $gMonthlyBids = getMonthlyBids($gId);
                        $gMemberPayments = getMemberPayments($gId);

                        // Get member's payments for this group
                        $memberPaymentsInGroup = array_filter($gMemberPayments,
                            fn($p) => $p['member_name'] === $member['member_name']
                        );
                        $paymentsByMonth = [];
                        foreach ($memberPaymentsInGroup as $payment) {
                            $paymentsByMonth[$payment['month_number']] = $payment;
                        }
                        ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-users"></i> <?= htmlspecialchars($groupInfo['group_name']) ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php for ($month = 1; $month <= $groupInfo['total_members']; $month++): ?>
                                        <?php
                                        $payment = $paymentsByMonth[$month] ?? null;
                                        $bid = array_filter($gMonthlyBids, fn($b) => $b['month_number'] == $month);
                                        $bid = reset($bid);
                                        $canPay = $bid && !$payment; // Can pay if bid exists and not already paid
                                        ?>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <div class="card <?= $payment ? 'border-success' : ($bid ? 'border-warning' : 'border-light') ?>">
                                                <div class="card-body text-center p-3">
                                                    <h6 class="card-title">Month <?= $month ?></h6>
                                                    <?php if ($payment): ?>
                                                        <span class="badge bg-success mb-2">Paid</span>
                                                        <p class="small text-muted mb-2">₹<?= number_format($payment['payment_amount']) ?></p>
                                                        <small class="text-success">
                                                            <i class="fas fa-check-circle"></i>
                                                            <?= date('M j, Y', strtotime($payment['payment_date'])) ?>
                                                        </small>
                                                    <?php elseif ($bid): ?>
                                                        <span class="badge bg-warning mb-2">Pending</span>
                                                        <p class="small text-muted mb-2">₹<?= number_format($bid['gain_per_member']) ?></p>
                                                        <a href="payment.php?month=<?= $month ?>&group_id=<?= $gId ?>"
                                                           class="btn btn-success btn-sm">
                                                            <i class="fas fa-credit-card"></i> Pay Now
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary mb-2">Not Ready</span>
                                                        <p class="small text-muted mb-2">Bidding not completed</p>
                                                        <small class="text-muted">Wait for bidding</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>
    <?php exit; ?>
<?php
}

// Validate month number for specific payment
if ($monthNumber < 1 || $monthNumber > $group['total_members']) {
    setMessage('Invalid month number.', 'error');
    redirect('payment.php'); // Redirect to month selection instead of dashboard
}

// Get month details
$monthlyBids = getMonthlyBids($groupId);
$memberPayments = getMemberPayments($groupId);

// Find the specific month bid
$monthBid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $monthNumber);
$monthBid = reset($monthBid);

// Check if member already paid for this month
$existingPayment = array_filter($memberPayments, fn($p) => 
    $p['member_id'] == $member['id'] && $p['month_number'] == $monthNumber
);
$existingPayment = reset($existingPayment);

// Calculate payment amount
if ($monthBid) {
    $paymentAmount = $monthBid['gain_per_member'];
    $winnerName = $monthBid['member_name'];
    $bidAmount = $monthBid['bid_amount'];
} else {
    $paymentAmount = $group['monthly_contribution'];
    $winnerName = 'Not decided yet';
    $bidAmount = 0;
}

// Get UPI ID (use configured one or default)
$configs = getPaymentConfig();
$upiId = !empty($configs['upi_id']) ? $configs['upi_id'] : '9768985225kotak@ybl';
$payeeName = !empty($configs['bank_account_name']) ? $configs['bank_account_name'] : 'BC Group Admin';

// Note: Only UPI payments supported, no bank transfer needed

// Set page title for the header
$page_title = 'Payment - Month ' . $monthNumber;

// Include the member header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
        .qr-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            color: white;
        }
        .qr-code {
            background: white;
            padding: 15px;
            border-radius: 10px;
            display: inline-block;
            margin: 15px 0;
        }
        .payment-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }
        .bank-details {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
        }
        .copy-btn {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .copy-btn:hover {
            transform: scale(1.1);
        }
    </style>

<!-- Page content starts here -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Header -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-credit-card"></i> Payment for Month <?= $monthNumber ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-user"></i> Member: <?= htmlspecialchars($member['member_name']) ?></h6>
                                <h6><i class="fas fa-users"></i> Group: <?= htmlspecialchars($group['group_name']) ?></h6>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-trophy"></i> Month Winner: <?= htmlspecialchars($winnerName) ?></h6>
                                <h6><i class="fas fa-money-bill"></i> Payment Amount: <span class="text-success fw-bold">₹<?= number_format($paymentAmount, 2) ?></span></h6>
                            </div>
                        </div>
                        
                        <?php if ($existingPayment): ?>
                            <div class="alert alert-success mt-3">
                                <i class="fas fa-check-circle"></i> 
                                <strong>Payment Already Made!</strong><br>
                                Amount: ₹<?= number_format($existingPayment['payment_amount'], 2) ?><br>
                                Date: <?= formatDate($existingPayment['payment_date']) ?><br>
                                Status: <span class="badge bg-success"><?= ucfirst($existingPayment['payment_status']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$existingPayment && isQrPaymentEnabled()): ?>
                    <!-- QR Code Payment Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="qr-container">
                                <h5><i class="fas fa-qrcode"></i> Scan to Pay</h5>
                                <p class="mb-2">Scan this QR code with any UPI app</p>

                                <div class="qr-code">
                                    <img src="QRCode.jpeg"
                                         alt="Payment QR Code"
                                         style="max-width: 200px; height: auto;">
                                </div>

                                <div class="mt-3">
                                    <p class="mb-2"><strong>UPI ID:</strong> <?= htmlspecialchars($upiId) ?></p>
                                    <p class="mb-2"><strong>Amount:</strong> ₹<?= number_format($paymentAmount, 2) ?></p>
                                    <p class="mb-3"><strong>Payee:</strong> <?= htmlspecialchars($payeeName) ?></p>

                                    <button class="btn btn-light btn-sm copy-btn"
                                            onclick="copyUpiId('<?= htmlspecialchars($upiId) ?>')">
                                        <i class="fas fa-copy"></i> Copy UPI ID
                                    </button>
                                    <button class="btn btn-light btn-sm copy-btn ms-2"
                                            onclick="copyText('<?= number_format($paymentAmount, 2) ?>')">
                                        <i class="fas fa-copy"></i> Copy Amount
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Payment Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="payment-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Payment For:</strong> Month <?= $monthNumber ?><br>
                                        <strong>Group:</strong> <?= htmlspecialchars($group['group_name']) ?><br>
                                        <strong>Member:</strong> <?= htmlspecialchars($member['member_name']) ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Amount:</strong> ₹<?= number_format($paymentAmount, 2) ?><br>
                                        <strong>Winner:</strong> <?= htmlspecialchars($winnerName) ?><br>
                                        <?php if ($bidAmount > 0): ?>
                                            <strong>Bid Amount:</strong> ₹<?= number_format($bidAmount, 2) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Instructions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-list-ol"></i> Payment Instructions</h5>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li><strong>Scan QR Code:</strong> Use any UPI app (PhonePe, Paytm, GPay, etc.) to scan the QR code above</li>
                                <li><strong>Verify Details:</strong> Check that the payment amount and UPI ID are correct</li>
                                <li><strong>Complete Payment:</strong> Follow your UPI app instructions to complete the payment</li>
                                <li><strong>Confirmation:</strong> After payment, contact the group admin with payment confirmation</li>
                                <li><strong>Receipt:</strong> Keep your payment receipt/screenshot for records</li>
                            </ol>

                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> Your payment status will be updated by the admin once payment is verified.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Back Button -->
                <div class="text-center mb-4">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Copied to clipboard!');
            }).catch(function() {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Copied to clipboard!');
            });
        }
        
        function copyUpiId(upiId) {
            copyText(upiId);
        }
        

        
        function showToast(message) {
            // Create a simple toast notification
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 m-3 alert alert-success alert-dismissible';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <i class="fas fa-check-circle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 3000);
        }
    </script>

<?php require_once 'includes/footer.php'; ?>
