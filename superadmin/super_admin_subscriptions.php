<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
require_once '../common/subscription_functions.php';
checkRole('superadmin');

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
        } elseif ($_POST['action'] === 'extend_subscription') {
            $subscriptionId = (int)$_POST['subscription_id'];
            $months = (int)$_POST['extend_months'];
            
            if (extendSubscription($subscriptionId, $months)) {
                $message = 'Subscription extended successfully!';
            } else {
                $error = 'Failed to extend subscription.';
            }
        } elseif ($_POST['action'] === 'cancel_subscription') {
            $subscriptionId = (int)$_POST['subscription_id'];
            
            if (cancelSubscription($subscriptionId)) {
                $message = 'Subscription cancelled successfully!';
            } else {
                $error = 'Failed to cancel subscription.';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all clients with their subscription status
$pdo = getDB();
$stmt = $pdo->query("
    SELECT c.*, 
           cs.id as subscription_id,
           cs.start_date,
           cs.end_date,
           cs.status as subscription_status,
           cs.payment_amount as last_payment,
           cs.created_at as subscription_created,
           sp.plan_name as current_plan,
           sp.duration_months,
           sp.price as plan_price,
           DATEDIFF(cs.end_date, CURDATE()) as days_remaining
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

// Get clients without subscriptions for assignment
$stmt = $pdo->query("
    SELECT id, client_name, company_name 
    FROM clients 
    WHERE current_subscription_id IS NULL OR current_subscription_id = 0
    ORDER BY client_name
");
$clientsWithoutSubscription = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Subscriptions - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-link-modern {
            color: #667eea !important;
            font-weight: 600;
            padding: 0.75rem 1.5rem !important;
            border-radius: 50px;
            margin: 0 0.25rem;
            transition: all 0.3s ease;
        }

        .nav-link-modern:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white !important;
            transform: translateY(-2px);
        }

        .dashboard-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .welcome-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-title {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .stats-row {
            margin-bottom: 2rem;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            text-align: center;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .section-title {
            color: #495057;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 1.2rem;
        }

        .subscription-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .status-expiring {
            background: #fff3cd;
            color: #856404;
        }

        .status-none {
            background: #e2e3e5;
            color: #383d41;
        }

        .btn-modern {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-outline-modern {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        .btn-outline-modern:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }

        .table {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            border-color: #e9ecef;
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background: rgba(102, 126, 234, 0.05);
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .client-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .client-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .client-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .client-info {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .subscription-info {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .subscription-info h6 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .subscription-details {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .no-subscription {
            background: rgba(220, 53, 69, 0.1);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            color: #dc3545;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-modern">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-crown text-primary me-2"></i>
                <span class="fw-bold text-primary"><?= APP_NAME ?></span>
                <small class="text-muted ms-2">Super Admin</small>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link-modern" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a class="nav-link-modern" href="super_admin_subscription_plans.php">
                    <i class="fas fa-credit-card me-2"></i>Plans
                </a>
                <a class="nav-link-modern" href="super_admin_subscriptions.php">
                    <i class="fas fa-calendar-check me-2"></i>Subscriptions
                </a>
                <a class="nav-link-modern" href="super_admin_clients.php">
                    <i class="fas fa-building me-2"></i>Clients
                </a>
                <div class="dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="welcome-title">
                        <i class="fas fa-calendar-check me-3"></i>Client Subscriptions
                    </h1>
                    <p class="welcome-subtitle mb-0">
                        Manage and monitor all client subscriptions
                    </p>
                </div>
                <div>
                    <?php if (!empty($clientsWithoutSubscription)): ?>
                        <button class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#assignSubscriptionModal">
                            <i class="fas fa-plus me-2"></i>Assign Subscription
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row stats-row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= $stats['active_subscriptions'] ?></div>
                    <div class="stats-label">Active Subscriptions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number">₹<?= number_format($stats['monthly_revenue']) ?></div>
                    <div class="stats-label">Monthly Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= $stats['expiring_soon'] ?></div>
                    <div class="stats-label">Expiring Soon</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?= count($clients) ?></div>
                    <div class="stats-label">Total Clients</div>
                </div>
            </div>
        </div>

        <!-- Client Subscriptions -->
        <div class="section-card">
            <h3 class="section-title">
                <i class="fas fa-users"></i>All Client Subscriptions
            </h3>

            <?php if (empty($clients)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No clients found</h4>
                    <p class="text-muted">Add clients to start managing subscriptions.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($clients as $client): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="client-card">
                                <div class="client-name"><?= htmlspecialchars($client['client_name']) ?></div>
                                <div class="client-info">
                                    <i class="fas fa-building me-2"></i><?= htmlspecialchars($client['company_name']) ?>
                                    <br>
                                    <i class="fas fa-calendar me-2"></i>Joined <?= date('M j, Y', strtotime($client['created_at'])) ?>
                                </div>

                                <?php if ($client['subscription_status']): ?>
                                    <div class="subscription-info">
                                        <h6>
                                            <i class="fas fa-credit-card me-2"></i><?= htmlspecialchars($client['current_plan']) ?>
                                            <span class="subscription-status <?=
                                                $client['subscription_status'] === 'active' ?
                                                    ($client['days_remaining'] <= 7 ? 'status-expiring' : 'status-active') :
                                                    'status-expired'
                                            ?>">
                                                <?= ucfirst($client['subscription_status']) ?>
                                            </span>
                                        </h6>
                                        <div class="subscription-details">
                                            <div class="row">
                                                <div class="col-6">
                                                    <strong>Start:</strong><br>
                                                    <?= date('M j, Y', strtotime($client['start_date'])) ?>
                                                </div>
                                                <div class="col-6">
                                                    <strong>End:</strong><br>
                                                    <?= date('M j, Y', strtotime($client['end_date'])) ?>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <strong>Payment:</strong> ₹<?= number_format($client['last_payment']) ?>
                                                <br>
                                                <?php if ($client['subscription_status'] === 'active'): ?>
                                                    <?php if ($client['days_remaining'] > 0): ?>
                                                        <span class="text-success">
                                                            <i class="fas fa-clock me-1"></i><?= $client['days_remaining'] ?> days remaining
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-danger">
                                                            <i class="fas fa-exclamation-triangle me-1"></i>Expired
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($client['subscription_status'] === 'active'): ?>
                                            <button class="btn btn-outline-modern btn-sm"
                                                    onclick="showExtendModal(<?= $client['subscription_id'] ?>, '<?= htmlspecialchars($client['client_name']) ?>')">
                                                <i class="fas fa-calendar-plus me-1"></i>Extend
                                            </button>
                                            <button class="btn btn-outline-modern btn-sm text-danger"
                                                    onclick="showCancelModal(<?= $client['subscription_id'] ?>, '<?= htmlspecialchars($client['client_name']) ?>')">
                                                <i class="fas fa-times me-1"></i>Cancel
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-modern btn-sm"
                                                    onclick="showAssignModal(<?= $client['id'] ?>, '<?= htmlspecialchars($client['client_name']) ?>')">
                                                <i class="fas fa-plus me-1"></i>Renew
                                            </button>
                                        <?php endif; ?>
                                        <a href="super_admin_clients.php?view=<?= $client['id'] ?>" class="btn btn-outline-modern btn-sm">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="no-subscription">
                                        <i class="fas fa-exclamation-circle me-2"></i>No Active Subscription
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-modern btn-sm w-100"
                                                onclick="showAssignModal(<?= $client['id'] ?>, '<?= htmlspecialchars($client['client_name']) ?>')">
                                            <i class="fas fa-plus me-2"></i>Assign Subscription
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assign Subscription Modal -->
    <div class="modal fade" id="assignSubscriptionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Assign Subscription
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_subscription">
                        <input type="hidden" name="client_id" id="assign_client_id">

                        <div class="mb-3">
                            <label for="assign_client_select" class="form-label">Select Client</label>
                            <select class="form-select" id="assign_client_select" name="client_id" required>
                                <option value="">Choose a client...</option>
                                <?php foreach ($clientsWithoutSubscription as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['client_name']) ?> - <?= htmlspecialchars($client['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="plan_id" class="form-label">Subscription Plan</label>
                            <select class="form-select" id="plan_id" name="plan_id" required>
                                <option value="">Choose a plan...</option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>" data-price="<?= $plan['price'] ?>">
                                        <?= htmlspecialchars($plan['plan_name']) ?> - ₹<?= number_format($plan['price']) ?> (<?= $plan['duration_months'] ?> months)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_amount" class="form-label">Payment Amount (₹)</label>
                                    <input type="number" class="form-control" id="payment_amount" name="payment_amount"
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required>
                                        <option value="">Select method...</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="upi">UPI</option>
                                        <option value="cash">Cash</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="payment_reference" class="form-label">Payment Reference</label>
                            <input type="text" class="form-control" id="payment_reference" name="payment_reference"
                                   placeholder="Transaction ID, Cheque number, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-save me-2"></i>Assign Subscription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Extend Subscription Modal -->
    <div class="modal fade" id="extendSubscriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-plus me-2"></i>Extend Subscription
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="extend_subscription">
                        <input type="hidden" name="subscription_id" id="extend_subscription_id">

                        <p>Extend subscription for: <strong id="extend_client_name"></strong></p>

                        <div class="mb-3">
                            <label for="extend_months" class="form-label">Extend by (months)</label>
                            <select class="form-select" id="extend_months" name="extend_months" required>
                                <option value="">Select duration...</option>
                                <option value="1">1 Month</option>
                                <option value="3">3 Months</option>
                                <option value="6">6 Months</option>
                                <option value="12">12 Months</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-calendar-plus me-2"></i>Extend Subscription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Subscription Modal -->
    <div class="modal fade" id="cancelSubscriptionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times me-2"></i>Cancel Subscription
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel_subscription">
                        <input type="hidden" name="subscription_id" id="cancel_subscription_id">

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning!</strong> This action will immediately cancel the subscription for
                            <strong id="cancel_client_name"></strong>. This cannot be undone.
                        </div>

                        <p>Are you sure you want to cancel this subscription?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal">No, Keep Active</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Yes, Cancel Subscription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill payment amount when plan is selected
        document.getElementById('plan_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                document.getElementById('payment_amount').value = price;
            }
        });

        // Show assign modal for specific client
        function showAssignModal(clientId, clientName) {
            document.getElementById('assign_client_id').value = clientId;
            document.getElementById('assign_client_select').value = clientId;
            const modal = new bootstrap.Modal(document.getElementById('assignSubscriptionModal'));
            modal.show();
        }

        // Show extend modal
        function showExtendModal(subscriptionId, clientName) {
            document.getElementById('extend_subscription_id').value = subscriptionId;
            document.getElementById('extend_client_name').textContent = clientName;
            const modal = new bootstrap.Modal(document.getElementById('extendSubscriptionModal'));
            modal.show();
        }

        // Show cancel modal
        function showCancelModal(subscriptionId, clientName) {
            document.getElementById('cancel_subscription_id').value = subscriptionId;
            document.getElementById('cancel_client_name').textContent = clientName;
            const modal = new bootstrap.Modal(document.getElementById('cancelSubscriptionModal'));
            modal.show();
        }

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
