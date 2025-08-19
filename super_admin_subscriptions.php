<?php
require_once 'simple_mt_config.php';
require_once 'subscription_functions.php';
requireSuperAdminLogin();

$message = '';
$error = '';

// Handle subscription assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'assign_subscription') {
            $clientId = (int)$_POST['client_id'];
            $planId = (int)$_POST['plan_id'];
            
            $paymentData = [
                'amount' => (float)$_POST['payment_amount'],
                'method' => $_POST['payment_method'],
                'reference' => $_POST['payment_reference'],
                'date' => date('Y-m-d H:i:s')
            ];
            
            $subscriptionId = createClientSubscription($clientId, $planId, $paymentData);
            $message = 'Subscription assigned successfully!';
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all clients with their subscription status
$pdo = getDB();
$stmt = $pdo->query("
    SELECT c.*, 
           cs.end_date as subscription_end_date,
           cs.status as subscription_status,
           sp.plan_name as current_plan,
           cs.payment_amount as last_payment
    FROM clients c
    LEFT JOIN client_subscriptions cs ON c.current_subscription_id = cs.id
    LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
    ORDER BY c.created_at DESC
");
$clients = $stmt->fetchAll();

// Get all active subscription plans
$plans = getAllSubscriptionPlans(true);

// Get subscription statistics
$stats = getSubscriptionStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Subscriptions - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .subscription-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-trial { background: #fff3cd; color: #856404; }
        .status-suspended { background: #e2e3e5; color: #383d41; }
        
        .client-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .client-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .expiry-warning {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            padding: 0.5rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <?php include 'super_admin_navbar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-calendar-check me-3"></i>Client Subscriptions</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                        <i class="fas fa-plus me-2"></i>Assign Subscription
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h3><?= $stats['active_subscriptions'] ?></h3>
                                <p class="mb-0">Active Subscriptions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h3><?= formatCurrency($stats['monthly_revenue'], 'INR', 2) ?></h3>
                                <p class="mb-0">Monthly Revenue</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h3><?= $stats['expiring_soon'] ?></h3>
                                <p class="mb-0">Expiring Soon</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h3><?= count($clients) ?></h3>
                                <p class="mb-0">Total Clients</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expiring Soon Warning -->
                <?php if ($stats['expiring_soon'] > 0): ?>
                    <div class="expiry-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Attention:</strong> <?= $stats['expiring_soon'] ?> subscription(s) expiring within 7 days!
                    </div>
                <?php endif; ?>

                <!-- Clients List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Client Subscription Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Current Plan</th>
                                        <th>Status</th>
                                        <th>End Date</th>
                                        <th>Last Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?= htmlspecialchars($client['client_name']) ?></strong><br>
                                                    <small class="text-muted"><?= htmlspecialchars($client['company_name']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($client['current_plan']): ?>
                                                    <?= htmlspecialchars($client['current_plan']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No active plan</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="subscription-status status-<?= $client['subscription_status'] ?? 'expired' ?>">
                                                    <?= ucfirst($client['subscription_status'] ?? 'No Subscription') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($client['subscription_end_date']): ?>
                                                    <?= date('M d, Y', strtotime($client['subscription_end_date'])) ?>
                                                    <?php 
                                                    $daysLeft = (strtotime($client['subscription_end_date']) - time()) / (60 * 60 * 24);
                                                    if ($daysLeft <= 7 && $daysLeft > 0): 
                                                    ?>
                                                        <br><small class="text-warning">
                                                            <i class="fas fa-clock"></i> <?= ceil($daysLeft) ?> days left
                                                        </small>
                                                    <?php elseif ($daysLeft <= 0): ?>
                                                        <br><small class="text-danger">
                                                            <i class="fas fa-exclamation-triangle"></i> Expired
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($client['last_payment']): ?>
                                                    <?= formatCurrency($client['last_payment'], 'INR', 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="assignSubscription(<?= $client['id'] ?>, '<?= htmlspecialchars($client['client_name']) ?>')">
                                                    <i class="fas fa-plus"></i> Assign Plan
                                                </button>
                                                <a href="super_admin_client_details.php?id=<?= $client['id'] ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Subscription Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Subscription Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_subscription">
                        <input type="hidden" name="client_id" id="modalClientId">
                        
                        <div class="mb-3">
                            <label class="form-label">Client</label>
                            <input type="text" id="modalClientName" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subscription Plan</label>
                            <select name="plan_id" class="form-control" required onchange="updatePlanPrice()">
                                <option value="">Select a plan...</option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>" data-price="<?= $plan['price'] ?>">
                                        <?= htmlspecialchars($plan['plan_name']) ?> -
                                        <?= formatCurrency($plan['price'], $plan['currency'], 2) ?>
                                        (<?= $plan['duration_months'] ?> months)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" name="payment_amount" id="paymentAmount" class="form-control" 
                                   step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-control" required>
                                <option value="manual">Manual Entry</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="upi">UPI</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Reference</label>
                            <input type="text" name="payment_reference" class="form-control" 
                                   placeholder="Transaction ID, Receipt number, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Subscription</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function assignSubscription(clientId, clientName) {
            document.getElementById('modalClientId').value = clientId;
            document.getElementById('modalClientName').value = clientName;
            new bootstrap.Modal(document.getElementById('assignModal')).show();
        }
        
        function updatePlanPrice() {
            const planSelect = document.querySelector('select[name="plan_id"]');
            const selectedOption = planSelect.options[planSelect.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            
            if (price) {
                document.getElementById('paymentAmount').value = price;
            }
        }
    </script>
</body>
</html>
