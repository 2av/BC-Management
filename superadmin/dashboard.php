<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
require_once '../common/subscription_functions.php';
checkRole('superadmin');

// Get platform statistics
$pdo = getDB();

// Total clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients");
$totalClients = $stmt->fetch()['total'];

// Active clients
$stmt = $pdo->query("SELECT COUNT(*) as active FROM clients WHERE status = 'active'");
$activeClients = $stmt->fetch()['active'];

// Total groups across all clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bc_groups");
$totalGroups = $stmt->fetch()['total'];

// Total members across all clients (count unique members from group_members)
$stmt = $pdo->query("SELECT COUNT(DISTINCT member_id) as total FROM group_members WHERE status = 'active'");
$totalMembers = $stmt->fetch()['total'];

// Monthly collection across all clients
$stmt = $pdo->query("SELECT SUM(total_monthly_collection) as total FROM bc_groups WHERE status = 'active'");
$monthlyCollection = $stmt->fetch()['total'] ?? 0;

// Recent clients
$stmt = $pdo->query("
    SELECT c.*,
           COUNT(DISTINCT bg.id) as group_count,
           COUNT(DISTINCT gm.member_id) as member_count
    FROM clients c
    LEFT JOIN bc_groups bg ON c.id = bg.client_id
    LEFT JOIN group_members gm ON bg.id = gm.group_id AND gm.status = 'active'
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 5
");
$recentClients = $stmt->fetchAll();

// Get subscription statistics
$subscriptionStats = getSubscriptionStats();

$message = getMessage();

// Ensure subscription stats has default values
if (!$subscriptionStats) {
    $subscriptionStats = [
        'active_subscriptions' => 0,
        'monthly_revenue' => 0,
        'expiring_soon' => 0,
        'plan_popularity' => []
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            margin: 2rem;
            padding: 2.5rem;
            min-height: calc(100vh - 4rem);
        }
        
        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .welcome-title {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .welcome-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--card-gradient);
        }
        
        .stats-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.2);
        }
        
        .stats-card.clients { --card-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-card.groups { --card-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stats-card.members { --card-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stats-card.revenue { --card-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stats-card.subscription { --card-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        
        .stats-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: var(--card-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-label {
            font-size: 1.1rem;
            color: #6c757d;
            font-weight: 600;
        }
        
        .stats-icon {
            position: absolute;
            top: 2rem;
            right: 2rem;
            font-size: 3rem;
            opacity: 0.1;
            background: var(--card-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .section-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .client-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid #667eea;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .client-item:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            transform: translateX(10px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .client-name {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .client-details {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
            padding: 1.2rem 2rem;
            margin-bottom: 1rem;
            width: 100%;
            text-align: left;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            display: block;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .quick-action-btn i {
            margin-right: 1rem;
            width: 25px;
        }
        
        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            margin: 1rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
        }
        
        .nav-link-modern {
            color: #6c757d !important;
            font-weight: 600;
            transition: all 0.3s ease;
            border-radius: 10px;
            margin: 0 0.5rem;
            padding: 0.75rem 1.5rem !important;
        }
        
        .nav-link-modern:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea !important;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Modern Navigation -->
    <nav class="navbar navbar-expand-lg navbar-modern">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="super_admin_dashboard.php" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                <i class="fas fa-crown me-2"></i><?= APP_NAME ?> - Super Admin
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link-modern" href="super_admin_subscription_plans.php">
                    <i class="fas fa-credit-card me-2"></i>Plans
                </a>
                <a class="nav-link-modern" href="super_admin_subscriptions.php">
                    <i class="fas fa-calendar-check me-2"></i>Subscriptions
                </a>
                <a class="nav-link-modern" href="super_admin_clients.php">
                    <i class="fas fa-building me-2"></i>Clients
                </a>
                <a class="nav-link-modern" href="super_admin_payments.php">
                    <i class="fas fa-money-bill-wave me-2"></i>Payments
                </a>
                <div class="dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
                        <i class="fas fa-crown me-3"></i>Welcome back, Super Administrator!
                    </h1>
                    <p class="welcome-subtitle mb-0">
                        Platform overview and subscription management dashboard
                    </p>
                </div>
                <div class="text-end">
                    <div class="text-white-50">
                        <i class="fas fa-calendar me-2"></i><?= date('F d, Y') ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $message['type'] === 'success' ? 'success' : ($message['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible fade show">
                <i class="fas fa-<?= $message['type'] === 'success' ? 'check-circle' : ($message['type'] === 'error' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i><?= htmlspecialchars($message['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Platform Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card clients">
                    <i class="fas fa-building stats-icon"></i>
                    <div class="stats-number"><?= $totalClients ?></div>
                    <div class="stats-label">Total Clients</div>
                    <small class="text-muted d-block mt-2"><?= $activeClients ?> Active</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card groups">
                    <i class="fas fa-layer-group stats-icon"></i>
                    <div class="stats-number"><?= $totalGroups ?></div>
                    <div class="stats-label">Total Groups</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card members">
                    <i class="fas fa-users stats-icon"></i>
                    <div class="stats-number"><?= $totalMembers ?></div>
                    <div class="stats-label">Total Members</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card revenue">
                    <i class="fas fa-rupee-sign stats-icon"></i>
                    <div class="stats-number"><?= formatCurrency($monthlyCollection) ?></div>
                    <div class="stats-label">Monthly Collection</div>
                </div>
            </div>
        </div>

        <!-- Subscription Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card subscription">
                    <i class="fas fa-credit-card stats-icon"></i>
                    <div class="stats-number"><?= $subscriptionStats['active_subscriptions'] ?></div>
                    <div class="stats-label">Active Subscriptions</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card subscription">
                    <i class="fas fa-chart-line stats-icon"></i>
                    <div class="stats-number"><?= formatCurrency($subscriptionStats['monthly_revenue'], 'INR', 2) ?></div>
                    <div class="stats-label">Monthly Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card subscription">
                    <i class="fas fa-exclamation-triangle stats-icon"></i>
                    <div class="stats-number"><?= $subscriptionStats['expiring_soon'] ?></div>
                    <div class="stats-label">Expiring Soon</div>
                    <small class="text-muted d-block mt-2">Next 7 days</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card subscription">
                    <i class="fas fa-star stats-icon"></i>
                    <div class="stats-number"><?= count($subscriptionStats['plan_popularity']) ?></div>
                    <div class="stats-label">Subscription Plans</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Clients -->
            <div class="col-md-8">
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-building"></i>Recent Clients
                    </h3>

                    <?php if (!empty($recentClients)): ?>
                        <?php foreach ($recentClients as $client): ?>
                            <div class="client-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="client-name"><?= htmlspecialchars($client['client_name'] ?? 'Unknown Client') ?></div>
                                        <div class="client-details">
                                            <i class="fas fa-building me-2"></i><?= htmlspecialchars($client['company_name'] ?? 'No Company') ?><br>
                                            <i class="fas fa-layer-group me-2"></i><?= $client['group_count'] ?? 0 ?> Groups •
                                            <i class="fas fa-users me-2"></i><?= $client['member_count'] ?? 0 ?> Members
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="status-badge status-active"><?= ucfirst($client['status'] ?? 'unknown') ?></span>
                                        <div class="mt-2">
                                            <small class="text-muted"><?= isset($client['created_at']) ? formatDate($client['created_at']) : 'Unknown' ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No clients found</h5>
                            <p class="text-muted">Add your first client to get started</p>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mt-3">
                        <a href="super_admin_clients.php" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>View All Clients
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="section-card">
                    <h3 class="section-title">
                        <i class="fas fa-bolt"></i>Quick Actions
                    </h3>

                    <a href="super_admin_add_client.php" class="quick-action-btn">
                        <i class="fas fa-plus-circle"></i>Add New Client
                    </a>

                    <a href="super_admin_subscription_plans.php" class="quick-action-btn">
                        <i class="fas fa-credit-card"></i>Manage Plans
                    </a>

                    <a href="super_admin_subscriptions.php" class="quick-action-btn">
                        <i class="fas fa-calendar-check"></i>View Subscriptions
                    </a>

                    <a href="super_admin_analytics.php" class="quick-action-btn">
                        <i class="fas fa-chart-bar"></i>View Analytics
                    </a>

                    <a href="super_admin_settings.php" class="quick-action-btn">
                        <i class="fas fa-cog"></i>System Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
