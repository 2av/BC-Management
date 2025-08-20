<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
require_once '../common/subscription_functions.php';
checkRole('superadmin');

$message = '';
$error = '';

// Handle client management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_client':
                $clientData = [
                    'client_name' => $_POST['client_name'],
                    'company_name' => $_POST['company_name'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone'],
                    'address' => $_POST['address'],
                    'city' => $_POST['city'],
                    'state' => $_POST['state'],
                    'pincode' => $_POST['pincode'],
                    'contact_person' => $_POST['contact_person'],
                    'contact_phone' => $_POST['contact_phone'],
                    'business_type' => $_POST['business_type'],
                    'notes' => $_POST['notes']
                ];
                
                if (createClient($clientData)) {
                    $message = 'Client created successfully!';
                } else {
                    $error = 'Failed to create client.';
                }
                break;
                
            case 'update_client':
                $clientData = [
                    'client_name' => $_POST['client_name'],
                    'company_name' => $_POST['company_name'],
                    'email' => $_POST['email'],
                    'phone' => $_POST['phone'],
                    'address' => $_POST['address'],
                    'city' => $_POST['city'],
                    'state' => $_POST['state'],
                    'pincode' => $_POST['pincode'],
                    'contact_person' => $_POST['contact_person'],
                    'contact_phone' => $_POST['contact_phone'],
                    'business_type' => $_POST['business_type'],
                    'notes' => $_POST['notes']
                ];
                
                if (updateClient($_POST['client_id'], $clientData)) {
                    $message = 'Client updated successfully!';
                } else {
                    $error = 'Failed to update client.';
                }
                break;
                
            case 'toggle_status':
                if (toggleClientStatus($_POST['client_id'])) {
                    $message = 'Client status updated successfully!';
                } else {
                    $error = 'Failed to update client status.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all clients with their subscription information
$pdo = getDB();
$stmt = $pdo->query("
    SELECT c.*, 
           cs.id as subscription_id,
           cs.start_date,
           cs.end_date,
           cs.status as subscription_status,
           cs.payment_amount as last_payment,
           sp.plan_name as current_plan,
           sp.duration_months,
           DATEDIFF(cs.end_date, CURDATE()) as days_remaining,
           (SELECT COUNT(*) FROM bc_groups WHERE client_id = c.id) as total_groups,
           (SELECT COUNT(*) FROM members m JOIN bc_groups g ON m.group_id = g.id WHERE g.client_id = c.id AND m.status = 'active') as total_members
    FROM clients c
    LEFT JOIN client_subscriptions cs ON c.current_subscription_id = cs.id
    LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
    ORDER BY c.created_at DESC
");
$clients = $stmt->fetchAll();

// Get client for editing if requested
$editClient = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editClient = $stmt->fetch();
}

// Get client for viewing if requested
$viewClient = null;
$clientGroups = [];
$clientMembers = [];
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               cs.id as subscription_id,
               cs.start_date,
               cs.end_date,
               cs.status as subscription_status,
               cs.payment_amount as last_payment,
               sp.plan_name as current_plan,
               sp.duration_months,
               DATEDIFF(cs.end_date, CURDATE()) as days_remaining
        FROM clients c
        LEFT JOIN client_subscriptions cs ON c.current_subscription_id = cs.id
        LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
        WHERE c.id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $viewClient = $stmt->fetch();
    
    if ($viewClient) {
        // Get client's groups
        $stmt = $pdo->prepare("
            SELECT * FROM bc_groups 
            WHERE client_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$_GET['view']]);
        $clientGroups = $stmt->fetchAll();
        
        // Get client's members
        $stmt = $pdo->prepare("
            SELECT m.*, g.group_name 
            FROM members m 
            JOIN bc_groups g ON m.group_id = g.id 
            WHERE g.client_id = ? 
            ORDER BY m.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$_GET['view']]);
        $clientMembers = $stmt->fetchAll();
    }
}

// Get statistics
$stats = [
    'total_clients' => count($clients),
    'active_clients' => count(array_filter($clients, fn($c) => $c['status'] === 'active')),
    'clients_with_subscriptions' => count(array_filter($clients, fn($c) => $c['subscription_status'] === 'active')),
    'total_groups' => array_sum(array_column($clients, 'total_groups')),
    'total_members' => array_sum(array_column($clients, 'total_members'))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management - <?= APP_NAME ?></title>
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

        .client-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
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

        .no-subscription {
            background: rgba(220, 53, 69, 0.1);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            color: #dc3545;
            font-weight: 600;
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

        .form-control, .form-select, .form-control:focus, .form-select:focus {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .client-details {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: #6c757d;
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
                        <i class="fas fa-building me-3"></i>Client Management
                    </h1>
                    <p class="welcome-subtitle mb-0">
                        Manage and monitor all your clients
                    </p>
                </div>
                <div>
                    <?php if (!$viewClient): ?>
                        <button class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#createClientModal">
                            <i class="fas fa-plus me-2"></i>Add New Client
                        </button>
                    <?php else: ?>
                        <a href="super_admin_clients.php" class="btn btn-modern">
                            <i class="fas fa-arrow-left me-2"></i>Back to Clients
                        </a>
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

        <?php if ($viewClient): ?>
            <!-- Client Details View -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-user"></i>Client Details
                </h3>

                <div class="client-details">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-row">
                                <span class="detail-label">Client Name:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['client_name']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Company:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['company_name']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['email']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['phone']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Contact Person:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['contact_person']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Contact Phone:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['contact_phone']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <span class="detail-label">Address:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['address']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">City:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['city']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">State:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['state']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Pincode:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['pincode']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Business Type:</span>
                                <span class="detail-value"><?= htmlspecialchars($viewClient['business_type']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Status:</span>
                                <span class="client-status <?= $viewClient['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                    <?= ucfirst($viewClient['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <?php if ($viewClient['notes']): ?>
                        <div class="mt-3">
                            <div class="detail-row">
                                <span class="detail-label">Notes:</span>
                                <span class="detail-value"><?= nl2br(htmlspecialchars($viewClient['notes'])) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Subscription Information -->
                <?php if ($viewClient['subscription_status']): ?>
                    <div class="subscription-info">
                        <h6>
                            <i class="fas fa-credit-card me-2"></i>Current Subscription: <?= htmlspecialchars($viewClient['current_plan']) ?>
                            <span class="client-status <?= $viewClient['subscription_status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                <?= ucfirst($viewClient['subscription_status']) ?>
                            </span>
                        </h6>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Start Date:</strong><br>
                                <?= date('M j, Y', strtotime($viewClient['start_date'])) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>End Date:</strong><br>
                                <?= date('M j, Y', strtotime($viewClient['end_date'])) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Payment:</strong><br>
                                ₹<?= number_format($viewClient['last_payment']) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Days Remaining:</strong><br>
                                <?= $viewClient['days_remaining'] > 0 ? $viewClient['days_remaining'] . ' days' : 'Expired' ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-subscription">
                        <i class="fas fa-exclamation-circle me-2"></i>No Active Subscription
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <a href="?edit=<?= $viewClient['id'] ?>" class="btn btn-outline-modern">
                        <i class="fas fa-edit me-2"></i>Edit Client
                    </a>
                    <a href="super_admin_subscriptions.php" class="btn btn-outline-modern">
                        <i class="fas fa-calendar-check me-2"></i>Manage Subscription
                    </a>
                </div>
            </div>

            <!-- Client Groups -->
            <?php if (!empty($clientGroups)): ?>
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-layer-group"></i>Client Groups (<?= count($clientGroups) ?>)
                    </h3>

                    <div class="row">
                        <?php foreach ($clientGroups as $group): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="client-card">
                                    <h6><?= htmlspecialchars($group['group_name']) ?></h6>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-users me-1"></i><?= $group['total_members'] ?> members
                                        <i class="fas fa-rupee-sign ms-2 me-1"></i><?= number_format($group['monthly_contribution']) ?>
                                    </p>
                                    <span class="client-status <?= $group['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <?= ucfirst($group['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Members -->
            <?php if (!empty($clientMembers)): ?>
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i>Recent Members
                    </h3>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Member Name</th>
                                    <th>Group</th>
                                    <th>Member Number</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientMembers as $member): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($member['member_name']) ?></td>
                                        <td><?= htmlspecialchars($member['group_name']) ?></td>
                                        <td>#<?= $member['member_number'] ?></td>
                                        <td>
                                            <span class="client-status <?= $member['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                                <?= ucfirst($member['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($member['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Statistics -->
            <div class="row stats-row">
                <div class="col-md-2-4">
                    <div class="stats-card">
                        <div class="stats-number"><?= $stats['total_clients'] ?></div>
                        <div class="stats-label">Total Clients</div>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stats-card">
                        <div class="stats-number"><?= $stats['active_clients'] ?></div>
                        <div class="stats-label">Active Clients</div>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stats-card">
                        <div class="stats-number"><?= $stats['clients_with_subscriptions'] ?></div>
                        <div class="stats-label">With Subscriptions</div>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stats-card">
                        <div class="stats-number"><?= $stats['total_groups'] ?></div>
                        <div class="stats-label">Total Groups</div>
                    </div>
                </div>
                <div class="col-md-2-4">
                    <div class="stats-card">
                        <div class="stats-number"><?= $stats['total_members'] ?></div>
                        <div class="stats-label">Total Members</div>
                    </div>
                </div>
            </div>

            <!-- All Clients -->
            <div class="section-card">
                <h3 class="section-title">
                    <i class="fas fa-building"></i>All Clients
                </h3>

                <?php if (empty($clients)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-building fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No clients found</h4>
                        <p class="text-muted">Create your first client to get started.</p>
                        <button class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#createClientModal">
                            <i class="fas fa-plus me-2"></i>Add First Client
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($clients as $client): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="client-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="client-name"><?= htmlspecialchars($client['client_name']) ?></div>
                                        <span class="client-status <?= $client['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                            <?= ucfirst($client['status']) ?>
                                        </span>
                                    </div>

                                    <div class="client-info">
                                        <i class="fas fa-building me-2"></i><?= htmlspecialchars($client['company_name']) ?>
                                        <br>
                                        <i class="fas fa-envelope me-2"></i><?= htmlspecialchars($client['email']) ?>
                                        <br>
                                        <i class="fas fa-phone me-2"></i><?= htmlspecialchars($client['phone']) ?>
                                        <br>
                                        <i class="fas fa-calendar me-2"></i>Joined <?= date('M j, Y', strtotime($client['created_at'])) ?>
                                    </div>

                                    <!-- Subscription Status -->
                                    <?php if ($client['subscription_status']): ?>
                                        <div class="subscription-info">
                                            <h6>
                                                <i class="fas fa-credit-card me-2"></i><?= htmlspecialchars($client['current_plan']) ?>
                                                <span class="client-status <?= $client['subscription_status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                                    <?= ucfirst($client['subscription_status']) ?>
                                                </span>
                                            </h6>
                                            <div class="text-muted small">
                                                Expires: <?= date('M j, Y', strtotime($client['end_date'])) ?>
                                                <?php if ($client['subscription_status'] === 'active' && $client['days_remaining'] > 0): ?>
                                                    (<?= $client['days_remaining'] ?> days left)
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-subscription">
                                            <i class="fas fa-exclamation-circle me-2"></i>No Active Subscription
                                        </div>
                                    <?php endif; ?>

                                    <!-- Client Stats -->
                                    <div class="row text-center mt-3 mb-3">
                                        <div class="col-4">
                                            <div class="text-muted small">Groups</div>
                                            <div class="fw-bold"><?= $client['total_groups'] ?></div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Members</div>
                                            <div class="fw-bold"><?= $client['total_members'] ?></div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Business</div>
                                            <div class="fw-bold small"><?= htmlspecialchars($client['business_type']) ?></div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="?view=<?= $client['id'] ?>" class="btn btn-outline-modern btn-sm">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <a href="?edit=<?= $client['id'] ?>" class="btn btn-outline-modern btn-sm">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                            <button type="submit" class="btn btn-outline-modern btn-sm"
                                                    onclick="return confirm('Are you sure you want to <?= $client['status'] === 'active' ? 'deactivate' : 'activate' ?> this client?')">
                                                <i class="fas fa-<?= $client['status'] === 'active' ? 'pause' : 'play' ?> me-1"></i>
                                                <?= $client['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Client Modal -->
    <div class="modal fade" id="createClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Client
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_client">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_name" class="form-label">Client Name *</label>
                                    <input type="text" class="form-control" id="client_name" name="client_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="state" name="state">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="pincode" class="form-label">Pincode</label>
                                    <input type="text" class="form-control" id="pincode" name="pincode">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="business_type" class="form-label">Business Type</label>
                            <select class="form-select" id="business_type" name="business_type">
                                <option value="">Select business type...</option>
                                <option value="Retail">Retail</option>
                                <option value="Manufacturing">Manufacturing</option>
                                <option value="Services">Services</option>
                                <option value="Technology">Technology</option>
                                <option value="Healthcare">Healthcare</option>
                                <option value="Education">Education</option>
                                <option value="Finance">Finance</option>
                                <option value="Real Estate">Real Estate</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Any additional notes about the client..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-save me-2"></i>Create Client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Client Modal -->
    <?php if ($editClient): ?>
    <div class="modal fade show" id="editClientModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Client
                    </h5>
                    <a href="super_admin_clients.php" class="btn-close btn-close-white"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_client">
                        <input type="hidden" name="client_id" value="<?= $editClient['id'] ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_client_name" class="form-label">Client Name *</label>
                                    <input type="text" class="form-control" id="edit_client_name" name="client_name"
                                           value="<?= htmlspecialchars($editClient['client_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_company_name" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="edit_company_name" name="company_name"
                                           value="<?= htmlspecialchars($editClient['company_name']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="edit_email" name="email"
                                           value="<?= htmlspecialchars($editClient['email']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_phone" class="form-label">Phone *</label>
                                    <input type="tel" class="form-control" id="edit_phone" name="phone"
                                           value="<?= htmlspecialchars($editClient['phone']) ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"><?= htmlspecialchars($editClient['address']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="edit_city" name="city"
                                           value="<?= htmlspecialchars($editClient['city']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_state" class="form-label">State</label>
                                    <input type="text" class="form-control" id="edit_state" name="state"
                                           value="<?= htmlspecialchars($editClient['state']) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_pincode" class="form-label">Pincode</label>
                                    <input type="text" class="form-control" id="edit_pincode" name="pincode"
                                           value="<?= htmlspecialchars($editClient['pincode']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="edit_contact_person" name="contact_person"
                                           value="<?= htmlspecialchars($editClient['contact_person']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_contact_phone" class="form-label">Contact Phone</label>
                                    <input type="tel" class="form-control" id="edit_contact_phone" name="contact_phone"
                                           value="<?= htmlspecialchars($editClient['contact_phone']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_business_type" class="form-label">Business Type</label>
                            <select class="form-select" id="edit_business_type" name="business_type">
                                <option value="">Select business type...</option>
                                <option value="Retail" <?= $editClient['business_type'] === 'Retail' ? 'selected' : '' ?>>Retail</option>
                                <option value="Manufacturing" <?= $editClient['business_type'] === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                                <option value="Services" <?= $editClient['business_type'] === 'Services' ? 'selected' : '' ?>>Services</option>
                                <option value="Technology" <?= $editClient['business_type'] === 'Technology' ? 'selected' : '' ?>>Technology</option>
                                <option value="Healthcare" <?= $editClient['business_type'] === 'Healthcare' ? 'selected' : '' ?>>Healthcare</option>
                                <option value="Education" <?= $editClient['business_type'] === 'Education' ? 'selected' : '' ?>>Education</option>
                                <option value="Finance" <?= $editClient['business_type'] === 'Finance' ? 'selected' : '' ?>>Finance</option>
                                <option value="Real Estate" <?= $editClient['business_type'] === 'Real Estate' ? 'selected' : '' ?>>Real Estate</option>
                                <option value="Other" <?= $editClient['business_type'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="edit_notes" name="notes" rows="3"><?= htmlspecialchars($editClient['notes']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="super_admin_clients.php" class="btn btn-outline-modern">Cancel</a>
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-save me-2"></i>Update Client
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Add custom CSS for 5-column layout
        const style = document.createElement('style');
        style.textContent = `
            .col-md-2-4 {
                flex: 0 0 auto;
                width: 20%;
            }
            @media (max-width: 768px) {
                .col-md-2-4 {
                    width: 50%;
                    margin-bottom: 1rem;
                }
            }
            @media (max-width: 576px) {
                .col-md-2-4 {
                    width: 100%;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
