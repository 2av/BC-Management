<?php
require_once 'mt_config.php';
requireSuperAdminLogin();

// Get platform statistics
$pdo = getDB();

// Get total clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients");
$totalClients = $stmt->fetch()['total'];

// Get active clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE status = 'active'");
$activeClients = $stmt->fetch()['total'];

// Get total groups across all clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bc_groups");
$totalGroups = $stmt->fetch()['total'];

// Get total members across all clients
$stmt = $pdo->query("SELECT COUNT(*) as total FROM members WHERE status = 'active'");
$totalMembers = $stmt->fetch()['total'];

// Get recent clients
$stmt = $pdo->query("
    SELECT c.*, sa.full_name as created_by_name 
    FROM clients c 
    LEFT JOIN super_admins sa ON c.created_by = sa.id 
    ORDER BY c.created_at DESC 
    LIMIT 5
");
$recentClients = $stmt->fetchAll();

// Get client statistics
$stmt = $pdo->query("
    SELECT 
        c.id, c.client_name, c.status, c.subscription_plan,
        COUNT(DISTINCT g.id) as total_groups,
        COUNT(DISTINCT m.id) as total_members,
        SUM(g.monthly_contribution * g.total_members) as monthly_collection
    FROM clients c
    LEFT JOIN bc_groups g ON c.id = g.client_id
    LEFT JOIN members m ON g.id = m.group_id AND m.status = 'active'
    GROUP BY c.id
    ORDER BY monthly_collection DESC
    LIMIT 10
");
$topClients = $stmt->fetchAll();

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        :root {
            --super-admin-primary: #2c3e50;
            --super-admin-secondary: #e74c3c;
            --super-admin-accent: #f39c12;
        }

        .super-admin-navbar {
            background: linear-gradient(135deg, var(--super-admin-primary), var(--super-admin-secondary));
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card.clients {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stats-card.groups {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stats-card.members {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stats-card.revenue {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
        }

        .stats-icon {
            font-size: 3rem;
            opacity: 0.3;
            position: absolute;
            right: 1rem;
            top: 1rem;
        }

        .client-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid var(--super-admin-primary);
        }

        .client-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
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

        .status-suspended {
            background: #fff3cd;
            color: #856404;
        }

        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .action-btn {
            background: linear-gradient(135deg, var(--super-admin-primary), var(--super-admin-secondary));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Super Admin Navigation -->
    <nav class="navbar navbar-expand-lg super-admin-navbar">
        <div class="container">
            <a class="navbar-brand text-white" href="super_admin_dashboard.php">
                <i class="fas fa-crown me-2"></i>
                <span><?= APP_NAME ?> - Super Admin</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="super_admin_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="super_admin_clients.php">
                            <i class="fas fa-building me-1"></i>Manage Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="super_admin_analytics.php">
                            <i class="fas fa-chart-bar me-1"></i>Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="super_admin_settings.php">
                            <i class="fas fa-cogs me-1"></i>Settings
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-crown me-1"></i><?= htmlspecialchars($_SESSION['super_admin_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="super_admin_profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="super_admin_audit.php">
                                <i class="fas fa-history me-2"></i>Audit Log
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($message['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-6 mb-2">Welcome back, <?= htmlspecialchars($_SESSION['super_admin_name']) ?>!</h1>
                <p class="text-muted">Platform overview and client management dashboard</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card clients position-relative">
                    <i class="fas fa-building stats-icon"></i>
                    <div class="stats-number"><?= $totalClients ?></div>
                    <div class="stats-label">Total Clients</div>
                    <small class="d-block mt-2"><?= $activeClients ?> Active</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card groups position-relative">
                    <i class="fas fa-layer-group stats-icon"></i>
                    <div class="stats-number"><?= $totalGroups ?></div>
                    <div class="stats-label">Total Groups</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card members position-relative">
                    <i class="fas fa-users stats-icon"></i>
                    <div class="stats-number"><?= $totalMembers ?></div>
                    <div class="stats-label">Total Members</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card revenue position-relative">
                    <i class="fas fa-chart-line stats-icon"></i>
                    <div class="stats-number"><?= count($topClients) ?></div>
                    <div class="stats-label">Active Platforms</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Clients -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2 text-primary"></i>Recent Clients
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentClients)): ?>
                            <p class="text-muted text-center py-4">No clients found. Create your first client to get started.</p>
                        <?php else: ?>
                            <?php foreach ($recentClients as $client): ?>
                                <div class="client-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($client['client_name']) ?></h6>
                                            <p class="text-muted mb-2"><?= htmlspecialchars($client['company_name']) ?></p>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($client['contact_person']) ?>
                                                <i class="fas fa-envelope ms-3 me-1"></i><?= htmlspecialchars($client['email']) ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="status-badge status-<?= $client['status'] ?>">
                                                <?= ucfirst($client['status']) ?>
                                            </span>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <?= formatDate($client['created_at']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="super_admin_clients.php" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>View All Clients
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="quick-actions">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>
                    
                    <a href="super_admin_add_client.php" class="action-btn w-100 text-center">
                        <i class="fas fa-plus me-2"></i>Add New Client
                    </a>
                    
                    <a href="super_admin_clients.php" class="action-btn w-100 text-center">
                        <i class="fas fa-building me-2"></i>Manage Clients
                    </a>
                    
                    <a href="super_admin_analytics.php" class="action-btn w-100 text-center">
                        <i class="fas fa-chart-bar me-2"></i>View Analytics
                    </a>
                    
                    <a href="super_admin_settings.php" class="action-btn w-100 text-center">
                        <i class="fas fa-cogs me-2"></i>System Settings
                    </a>
                    
                    <a href="super_admin_audit.php" class="action-btn w-100 text-center">
                        <i class="fas fa-history me-2"></i>Audit Log
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
