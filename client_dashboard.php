<?php
require_once 'mt_config.php';
requireClientAdminLogin();

$clientId = getCurrentClientId();
$client = getCurrentClient();

// Get client statistics
$pdo = getDB();

// Get total groups for this client
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bc_groups WHERE client_id = ?");
$stmt->execute([$clientId]);
$totalGroups = $stmt->fetch()['total'];

// Get active groups for this client
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bc_groups WHERE client_id = ? AND status = 'active'");
$stmt->execute([$clientId]);
$activeGroups = $stmt->fetch()['total'];

// Get total members for this client
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM members m 
    JOIN bc_groups g ON m.group_id = g.id 
    WHERE g.client_id = ? AND m.status = 'active'
");
$stmt->execute([$clientId]);
$totalMembers = $stmt->fetch()['total'];

// Get total monthly collection for this client
$stmt = $pdo->prepare("
    SELECT SUM(monthly_contribution * total_members) as total 
    FROM bc_groups 
    WHERE client_id = ? AND status = 'active'
");
$stmt->execute([$clientId]);
$monthlyCollection = $stmt->fetch()['total'] ?? 0;

// Get recent groups
$stmt = $pdo->prepare("
    SELECT * FROM bc_groups 
    WHERE client_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$clientId]);
$recentGroups = $stmt->fetchAll();

// Get recent members
$stmt = $pdo->prepare("
    SELECT m.*, g.group_name 
    FROM members m 
    JOIN bc_groups g ON m.group_id = g.id 
    WHERE g.client_id = ? 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
$stmt->execute([$clientId]);
$recentMembers = $stmt->fetchAll();

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - <?= htmlspecialchars($client['client_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        :root {
            --client-primary: #3498db;
            --client-secondary: #2980b9;
            --client-accent: #e67e22;
        }

        .client-navbar {
            background: linear-gradient(135deg, var(--client-primary), var(--client-secondary));
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card.groups {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stats-card.members {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stats-card.collection {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stats-card.active {
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

        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--client-primary), var(--client-secondary));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin: 0.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }

        .recent-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .item-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--client-primary);
            transition: all 0.3s ease;
        }

        .item-card:hover {
            transform: translateX(5px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .client-info-card {
            background: linear-gradient(135deg, var(--client-primary), var(--client-accent));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Client Navigation -->
    <nav class="navbar navbar-expand-lg client-navbar">
        <div class="container">
            <a class="navbar-brand text-white" href="client_dashboard.php">
                <i class="fas fa-building me-2"></i>
                <span><?= htmlspecialchars($client['client_name']) ?></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="client_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="client_groups.php">
                            <i class="fas fa-layer-group me-1"></i>Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="client_members.php">
                            <i class="fas fa-users me-1"></i>Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="client_reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['client_admin_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="client_profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a></li>
                            <li><a class="dropdown-item" href="client_settings.php">
                                <i class="fas fa-cogs me-2"></i>Settings
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
        <div class="client-info-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">Welcome to <?= htmlspecialchars($client['client_name']) ?>!</h2>
                    <p class="mb-2"><?= htmlspecialchars($client['company_name']) ?></p>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-crown me-2"></i>Subscription: <?= ucfirst($client['subscription_plan']) ?>
                        <i class="fas fa-layer-group ms-3 me-2"></i>Max Groups: <?= $client['max_groups'] ?>
                        <i class="fas fa-users ms-3 me-2"></i>Max Members/Group: <?= $client['max_members_per_group'] ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-building fa-4x opacity-25"></i>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card groups position-relative">
                    <i class="fas fa-layer-group stats-icon"></i>
                    <div class="stats-number"><?= $totalGroups ?></div>
                    <div class="stats-label">Total Groups</div>
                    <small class="d-block mt-2"><?= $activeGroups ?> Active</small>
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
                <div class="stats-card collection position-relative">
                    <i class="fas fa-rupee-sign stats-icon"></i>
                    <div class="stats-number"><?= formatCurrency($monthlyCollection) ?></div>
                    <div class="stats-label">Monthly Collection</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card active position-relative">
                    <i class="fas fa-chart-line stats-icon"></i>
                    <div class="stats-number"><?= $activeGroups ?></div>
                    <div class="stats-label">Active Groups</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="quick-actions">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>
                    
                    <a href="admin_create_group_simple.php" class="action-btn w-100">
                        <i class="fas fa-plus me-2"></i>Create New Group
                    </a>
                    
                    <a href="admin_add_member.php" class="action-btn w-100">
                        <i class="fas fa-user-plus me-2"></i>Add Member
                    </a>
                    
                    <a href="admin_manage_groups.php" class="action-btn w-100">
                        <i class="fas fa-layer-group me-2"></i>Manage Groups
                    </a>
                    
                    <a href="admin_members.php" class="action-btn w-100">
                        <i class="fas fa-users me-2"></i>View All Members
                    </a>
                    
                    <a href="admin_payment_status.php" class="action-btn w-100">
                        <i class="fas fa-credit-card me-2"></i>Payment Status
                    </a>
                </div>
            </div>

            <!-- Recent Groups -->
            <div class="col-md-4">
                <div class="recent-card">
                    <h5 class="mb-3">
                        <i class="fas fa-layer-group me-2 text-primary"></i>Recent Groups
                    </h5>
                    
                    <?php if (empty($recentGroups)): ?>
                        <p class="text-muted text-center py-3">No groups created yet.</p>
                        <div class="text-center">
                            <a href="admin_create_group_simple.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus me-2"></i>Create First Group
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentGroups as $group): ?>
                            <div class="item-card">
                                <h6 class="mb-1"><?= htmlspecialchars($group['group_name']) ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i><?= $group['total_members'] ?> members
                                    <i class="fas fa-rupee-sign ms-2 me-1"></i><?= formatCurrency($group['monthly_contribution']) ?>
                                    <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
                                        <?= ucfirst($group['status']) ?>
                                    </span>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="admin_manage_groups.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye me-2"></i>View All Groups
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Members -->
            <div class="col-md-4">
                <div class="recent-card">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2 text-success"></i>Recent Members
                    </h5>
                    
                    <?php if (empty($recentMembers)): ?>
                        <p class="text-muted text-center py-3">No members added yet.</p>
                        <div class="text-center">
                            <a href="admin_add_member.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-user-plus me-2"></i>Add First Member
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentMembers as $member): ?>
                            <div class="item-card">
                                <h6 class="mb-1"><?= htmlspecialchars($member['member_name']) ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-layer-group me-1"></i><?= htmlspecialchars($member['group_name']) ?>
                                    <i class="fas fa-hashtag ms-2 me-1"></i><?= $member['member_number'] ?>
                                    <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
                                        <?= ucfirst($member['status']) ?>
                                    </span>
                                </small>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="admin_members.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-eye me-2"></i>View All Members
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
