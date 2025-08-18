<?php
require_once 'config.php';
require_once 'languages/config.php';
requireAdminLogin();

if (isset($_GET['logout'])) {
    logout();
}

$groups = getAllGroups();

// Get dashboard statistics
$pdo = getDB();

// Total statistics
$totalGroups = count($groups);
$activeGroups = count(array_filter($groups, fn($g) => $g['status'] === 'active'));
$completedGroups = count(array_filter($groups, fn($g) => $g['status'] === 'completed'));

// Total members across all groups
$stmt = $pdo->query("SELECT COUNT(*) FROM members");
$totalMembers = $stmt->fetchColumn();

// Total money collected
$stmt = $pdo->query("SELECT SUM(payment_amount) FROM member_payments WHERE payment_status = 'paid'");
$totalCollected = $stmt->fetchColumn() ?: 0;

// Total money distributed
$stmt = $pdo->query("SELECT SUM(net_payable) FROM monthly_bids");
$totalDistributed = $stmt->fetchColumn() ?: 0;

// Monthly collection data for chart
$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(payment_amount) as total_amount
    FROM member_payments
    WHERE payment_status = 'paid' AND payment_date IS NOT NULL
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$monthlyData = $stmt->fetchAll();

// Group progress data
$groupProgressData = [];
foreach ($groups as $group) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE group_id = ?");
    $stmt->execute([$group['id']]);
    $completedMonths = $stmt->fetchColumn();

    $groupProgressData[] = [
        'name' => $group['group_name'],
        'completed' => $completedMonths,
        'total' => $group['total_members'],
        'percentage' => ($completedMonths / $group['total_members']) * 100
    ];
}

// Recent activities
$stmt = $pdo->query("
    SELECT
        'payment' as type,
        mp.payment_date as date,
        m.member_name,
        g.group_name,
        mp.payment_amount as amount
    FROM member_payments mp
    JOIN members m ON mp.member_id = m.id
    JOIN bc_groups g ON mp.group_id = g.id
    WHERE mp.payment_date IS NOT NULL

    UNION ALL

    SELECT
        'bid' as type,
        mb.payment_date as date,
        m.member_name,
        g.group_name,
        mb.net_payable as amount
    FROM monthly_bids mb
    JOIN members m ON mb.taken_by_member_id = m.id
    JOIN bc_groups g ON mb.group_id = g.id
    WHERE mb.payment_date IS NOT NULL

    ORDER BY date DESC
    LIMIT 10
");
$recentActivities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css?v=<?= time() ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, var(--gray-50) 0%, #f8fafc 100%);
            font-family: var(--font-family-sans);
        }

        /* Modern Dashboard Header */
        .dashboard-header {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .dashboard-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .dashboard-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Enhanced Stat Cards */
        .stat-card-modern-enhanced {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .stat-card-modern-enhanced:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
        }

        .stat-card-primary {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        .stat-card-secondary {
            background: var(--secondary-gradient);
            color: white;
            border: none;
        }

        .stat-card-accent {
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-dark) 100%);
            color: white;
            border: none;
        }

        .stat-card-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #2563eb 100%);
            color: white;
            border: none;
        }

        .stat-icon-modern {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            background: rgba(255, 255, 255, 0.2);
            animation: pulse-gentle 2s infinite;
        }

        .stat-number-modern {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label-modern {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-sublabel-modern {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 1rem;
        }

        .stat-action-modern {
            font-size: 0.85rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Quick Actions Section */
        .quick-actions {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
        }

        .quick-actions h5 {
            color: var(--gray-900);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .quick-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-normal);
            font-weight: 500;
            border: 1px solid var(--gray-200);
        }

        .quick-action-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Groups Section */
        .groups-section {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .groups-header {
            background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .groups-header h4 {
            color: var(--gray-900);
            font-weight: 600;
            margin-bottom: 0;
        }

        .group-card-modern {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all var(--transition-normal);
            position: relative;
        }

        .group-card-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .group-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .group-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .group-meta {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .group-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .group-action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: var(--radius-md);
            transition: all var(--transition-normal);
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
        }

        .chart-header {
            margin-bottom: 2rem;
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .chart-subtitle {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem;
                text-align: center;
            }

            .dashboard-title {
                font-size: 2rem;
            }

            .dashboard-actions {
                justify-content: center;
                margin-top: 1rem;
            }

            .stat-card-modern-enhanced {
                padding: 1.5rem;
            }

            .stat-number-modern {
                font-size: 2.5rem;
            }
        }

        .dashboard-card {
            transition: transform 0.2s;
            position: relative;
            z-index: 1;
            margin-bottom: 20px;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
        }

        /* Prevent overlapping of pending payments section */
        #pendingPaymentsContent {
            position: relative;
            z-index: 10;
        }

        /* Ensure proper spacing between sections */
        .row.mb-5 {
            margin-bottom: 3rem !important;
        }

        /* Language switcher styling */
        .language-flag {
            font-size: 1.2em;
            margin-right: 0.5rem;
        }

        .dropdown-item.active {
            background-color: var(--bs-primary);
            color: white;
        }

        .dropdown-item:hover {
            background-color: var(--bs-light);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Interactive button effects */
        .btn-interactive {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-interactive:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-interactive:active {
            transform: translateY(0);
        }

        .btn-interactive::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-interactive:hover::before {
            left: 100%;
        }

        /* Enhanced navbar */
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        /* Icon animations */
        .fas, .far {
            transition: all 0.3s ease;
        }

        .btn:hover .fas,
        .btn:hover .far {
            transform: scale(1.1);
        }

        /* Card hover effects */
        .card {
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-handshake text-warning me-2"></i><?= APP_NAME ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-tachometer-alt"></i> <?= t('dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users"></i> <?= t('members') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin_members.php">
                                <i class="fas fa-list"></i> <?= t('all_members') ?>
                            </a></li>
                            <li><a class="dropdown-item" href="admin_add_member.php">
                                <i class="fas fa-user-plus"></i> <?= t('add_member') ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin_bulk_import.php">
                                <i class="fas fa-upload"></i> <?= t('bulk_import') ?>
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-layer-group"></i> <?= t('groups') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php">
                                <i class="fas fa-list"></i> <?= t('all_groups') ?>
                            </a></li>
                            <li><a class="dropdown-item" href="admin_create_group_simple.php">
                                <i class="fas fa-plus"></i> <?= t('create_group') ?>
                            </a></li>
                            <li><a class="dropdown-item" href="admin_manage_groups.php">
                                <i class="fas fa-cogs"></i> <?= t('manage_groups') ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="create_group.php">
                                <i class="fas fa-tools"></i> <?= t('advanced_create') ?>
                            </a></li>
                        </ul>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto">
                    <!-- Language Switcher -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-globe me-1"></i>
                            <span class="language-flag"><?= $available_languages[getCurrentLanguage()]['flag'] ?></span>
                            <span class="d-none d-md-inline ms-1"><?= $available_languages[getCurrentLanguage()]['name'] ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                            <?php foreach ($available_languages as $code => $language): ?>
                                <li>
                                    <a class="dropdown-item <?= getCurrentLanguage() === $code ? 'active' : '' ?>"
                                       href="?change_language=<?= $code ?>">
                                        <span class="me-2"><?= $language['flag'] ?></span>
                                        <?= $language['name'] ?>
                                        <?php if (getCurrentLanguage() === $code): ?>
                                            <i class="fas fa-check text-success ms-2"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <span class="navbar-text me-3">
                            <?= t('welcome') ?>, <?= htmlspecialchars($_SESSION['admin_name']) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="admin_change_password.php">
                            <i class="fas fa-key"></i> <?= t('change_password') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="?logout=1">
                            <i class="fas fa-sign-out-alt"></i> <?= t('logout') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php $msg = getMessage(); ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] === 'success' ? 'success-modern' : ($msg['type'] === 'error' ? 'danger-modern' : 'info-modern') ?>">
                <i class="fas fa-<?= $msg['type'] === 'success' ? 'check-circle' : ($msg['type'] === 'error' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dashboard Header -->
        <div class="dashboard-header animate-fadeInUp">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="dashboard-title">
                        <i class="fas fa-tachometer-alt text-gradient-primary me-3"></i>
                        <?= t('admin_dashboard') ?>
                    </h1>
                    <p class="dashboard-subtitle">
                        <i class="fas fa-calendar-alt me-2"></i><?= date('l, F j, Y') ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock me-2"></i><?= date('g:i A') ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield me-2"></i><?= t('welcome') ?> back, Admin
                    </p>
                </div>
                <div class="dashboard-actions">
                    <a href="admin_members.php" class="btn btn-outline-modern">
                        <i class="fas fa-users-cog me-2"></i>Manage Members
                    </a>
                    <a href="admin_change_password.php" class="btn btn-outline-modern">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </a>
                    <a href="admin_create_group_simple.php" class="btn btn-primary-modern">
                        <i class="fas fa-plus-circle me-2"></i>Create Group
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4 animate-stagger">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-primary" onclick="scrollToGroups()">
                    <div class="stat-icon-modern">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number-modern"><?= $totalGroups ?></div>
                    <div class="stat-label-modern"><?= t('total_groups') ?></div>
                    <div class="stat-sublabel-modern"><?= $activeGroups ?> <?= t('active_groups') ?> • <?= $completedGroups ?> <?= t('completed_groups') ?></div>
                    <div class="stat-action-modern">
                        <i class="fas fa-arrow-down"></i>
                        <span>Click to view groups</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-secondary" onclick="window.location.href='admin_members.php'">
                    <div class="stat-icon-modern">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-number-modern"><?= $totalMembers ?></div>
                    <div class="stat-label-modern"><?= t('total_members') ?></div>
                    <div class="stat-sublabel-modern">Across all groups</div>
                    <div class="stat-action-modern">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Manage members</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-info" onclick="showCollectionDetails()">
                    <div class="stat-icon-modern">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-number-modern"><?= formatCurrency($totalCollected) ?></div>
                    <div class="stat-label-modern"><?= t('total_collected') ?></div>
                    <div class="stat-sublabel-modern">All payments received</div>
                    <div class="stat-action-modern">
                        <i class="fas fa-info-circle"></i>
                        <span>View details</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-accent" onclick="showDistributionDetails()">
                    <div class="stat-icon-modern">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-number-modern"><?= formatCurrency($totalDistributed) ?></div>
                    <div class="stat-label-modern"><?= t('total_distributed') ?></div>
                    <div class="stat-sublabel-modern">Amount given to winners</div>
                    <div class="stat-action-modern">
                        <i class="fas fa-info-circle"></i>
                        <span>View details</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="quick-actions animate-slideInRight">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5><i class="fas fa-bolt text-warning me-2"></i>Quick Actions</h5>
                    <p class="text-muted mb-0">Frequently used admin functions</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="admin_add_member.php" class="quick-action-btn" title="Add New Member">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Member</span>
                    </a>
                    <a href="admin_payment_config.php" class="quick-action-btn" title="QR Code Settings">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Settings</span>
                    </a>
                    <a href="admin_payment_status.php" class="quick-action-btn" title="Payment Status">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="admin_bulk_import.php" class="quick-action-btn" title="Bulk Import">
                        <i class="fas fa-upload"></i>
                        <span>Import</span>
                    </a>
                    <button class="quick-action-btn" onclick="refreshDashboard()" title="Refresh Data">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Group-wise Pending Payments Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card dashboard-card animate-fadeInUp" style="position: relative; z-index: 10;">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= t('pending_payments') ?>
                                </h4>
                                <p class="mb-0 mt-1 small"><?= t('view_month_details') ?></p>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-dark btn-sm" onclick="loadPendingPayments()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="min-height: 200px; max-height: 600px; overflow-y: auto;">
                        <div id="pendingPaymentsContent">
                            <div class="text-center text-muted py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2"><?= t('loading_pending_payments') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line"></i> <?= t('monthly_collection_trend') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie"></i> <?= t('group_status') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="groupStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group Progress and Recent Activities -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar"></i> <?= t('group_progress') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="groupProgressChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> <?= t('recent_activities') ?>
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="d-flex align-items-center mb-2 p-2 bg-light rounded">
                                    <div class="me-3">
                                        <?php if ($activity['type'] === 'payment'): ?>
                                            <i class="fas fa-money-bill text-success"></i>
                                        <?php else: ?>
                                            <i class="fas fa-trophy text-warning"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= htmlspecialchars($activity['member_name']) ?></div>
                                        <small class="text-muted">
                                            <?= $activity['type'] === 'payment' ? 'Payment' : 'Won bid' ?> in <?= htmlspecialchars($activity['group_name']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-primary"><?= formatCurrency($activity['amount']) ?></div>
                                        <small class="text-muted"><?= formatDate($activity['date']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- BC Groups List -->
        <div class="groups-section animate-fadeInUp" id="groupsList">
            <div class="groups-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h4><i class="fas fa-layer-group me-2"></i>All BC Groups</h4>
                        <p class="text-muted mb-0">Manage and monitor all your BC groups</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-outline-modern btn-sm" onclick="filterGroups('all')" id="filterAll">
                            <i class="fas fa-list me-1"></i>All
                        </button>
                        <button class="btn btn-outline-modern btn-sm" onclick="filterGroups('active')" id="filterActive">
                            <i class="fas fa-play me-1"></i>Active
                        </button>
                        <button class="btn btn-outline-modern btn-sm" onclick="filterGroups('completed')" id="filterCompleted">
                            <i class="fas fa-check me-1"></i>Completed
                        </button>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <div class="row">
                    <?php if (!empty($groups)): ?>
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-6 col-lg-4 mb-4" data-group-status="<?= $group['status'] ?>">
                        <div class="group-card-modern">
                            <div class="group-status-badge">
                                <span class="badge badge-<?= $group['status'] === 'active' ? 'success' : 'secondary' ?>-modern">
                                    <i class="fas fa-<?= $group['status'] === 'active' ? 'play' : 'check' ?> me-1"></i>
                                    <?= ucfirst($group['status']) ?>
                                </span>
                            </div>

                            <div class="group-name"><?= htmlspecialchars($group['group_name']) ?></div>

                            <div class="group-meta">
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="fw-bold text-primary"><?= $group['total_members'] ?></div>
                                        <small class="text-muted">Members</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-success"><?= formatCurrency($group['monthly_contribution']) ?></div>
                                        <small class="text-muted">Monthly</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-info"><?= formatCurrency($group['total_monthly_collection']) ?></div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                                <div class="text-center mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Started: <?= formatDate($group['start_date']) ?>
                                    </small>
                                </div>
                            </div>

                            <div class="group-actions">
                                <a href="view_group.php?id=<?= $group['id'] ?>" class="btn btn-primary-modern group-action-btn">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                <a href="admin_bidding.php?group_id=<?= $group['id'] ?>" class="btn btn-outline-modern group-action-btn">
                                    <i class="fas fa-gavel me-1"></i>Bidding
                                </a>
                                <a href="admin_payment_status.php?group_id=<?= $group['id'] ?>" class="btn btn-outline-modern group-action-btn">
                                    <i class="fas fa-credit-card me-1"></i>Payments
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No BC Groups Yet</h4>
                        <p class="text-muted">Create your first BC group to get started.</p>
                        <a href="admin_create_group_simple.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create BC Group
                        </a>
                    </div>
                </div>
            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart.js configuration
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.color = '#666';

        // Monthly Trend Chart
        const monthlyData = <?= json_encode(array_reverse($monthlyData)) ?>;
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Monthly Collection',
                    data: monthlyData.map(item => item.total_amount),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Group Status Pie Chart
        const statusCtx = document.getElementById('groupStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Groups', 'Completed Groups'],
                datasets: [{
                    data: [<?= $activeGroups ?>, <?= $completedGroups ?>],
                    backgroundColor: ['#11998e', '#f5576c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Group Progress Bar Chart
        const progressData = <?= json_encode($groupProgressData) ?>;
        const progressCtx = document.getElementById('groupProgressChart').getContext('2d');
        new Chart(progressCtx, {
            type: 'bar',
            data: {
                labels: progressData.map(item => item.name.length > 15 ? item.name.substring(0, 15) + '...' : item.name),
                datasets: [{
                    label: 'Completed Months',
                    data: progressData.map(item => item.completed),
                    backgroundColor: '#38ef7d',
                    borderRadius: 5
                }, {
                    label: 'Remaining Months',
                    data: progressData.map(item => item.total - item.completed),
                    backgroundColor: '#e9ecef',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Add hover effects to dashboard cards
        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.boxShadow = '';
            });
        });

        // Enhanced dashboard functions
        function scrollToGroups() {
            document.getElementById('groupsList').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function showCollectionDetails() {
            const totalCollected = <?= $totalCollected ?>;
            const totalMembers = <?= $totalMembers ?>;
            const avgPerMember = totalMembers > 0 ? (totalCollected / totalMembers) : 0;

            alert(`Collection Details:\n\n` +
                  `Total Collected: ₹${totalCollected.toLocaleString()}\n` +
                  `Total Members: ${totalMembers}\n` +
                  `Average per Member: ₹${avgPerMember.toFixed(2)}\n\n` +
                  `Click on "Manage Members" to see detailed payment history.`);
        }

        function showDistributionDetails() {
            const totalDistributed = <?= $totalDistributed ?>;
            const totalCollected = <?= $totalCollected ?>;
            const remaining = totalCollected - totalDistributed;

            alert(`Distribution Details:\n\n` +
                  `Total Distributed: ₹${totalDistributed.toLocaleString()}\n` +
                  `Total Collected: ₹${totalCollected.toLocaleString()}\n` +
                  `Remaining Balance: ₹${remaining.toLocaleString()}\n\n` +
                  `This shows amounts given to bid winners.`);
        }

        function filterGroups(status) {
            const groupCards = document.querySelectorAll('[data-group-status]');
            const filterButtons = document.querySelectorAll('[id^="filter"]');

            // Reset button styles
            filterButtons.forEach(btn => {
                btn.classList.remove('btn-primary', 'btn-success', 'btn-secondary');
                btn.classList.add('btn-outline-primary', 'btn-outline-success', 'btn-outline-secondary');
            });

            // Highlight active filter
            const activeButton = document.getElementById('filter' + status.charAt(0).toUpperCase() + status.slice(1));
            if (activeButton) {
                activeButton.classList.remove('btn-outline-primary', 'btn-outline-success', 'btn-outline-secondary');
                activeButton.classList.add(status === 'active' ? 'btn-success' :
                                          status === 'completed' ? 'btn-secondary' : 'btn-primary');
            }

            // Filter groups
            groupCards.forEach(card => {
                if (status === 'all' || card.getAttribute('data-group-status') === status) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeIn 0.3s ease-in';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function refreshDashboard() {
            const refreshBtn = document.querySelector('[onclick="refreshDashboard()"]');
            const icon = refreshBtn.querySelector('i');

            // Add spinning animation
            icon.classList.add('fa-spin');
            refreshBtn.disabled = true;

            // Simulate refresh (in real app, you'd reload data via AJAX)
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Auto-refresh dashboard every 5 minutes
        setInterval(() => {
            console.log('Auto-refreshing dashboard data...');
            // In a real application, you'd fetch updated data via AJAX here
        }, 300000); // 5 minutes

        function loadPendingPayments() {
            const contentDiv = document.getElementById('pendingPaymentsContent');

            // Show loading
            contentDiv.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading group-wise pending payments...</p>
                </div>
            `;

            // Fetch pending payments summary
            fetch(`admin_get_pending_payments.php?action=summary`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    if (data.trim() === '') {
                        contentDiv.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle"></i>
                                <strong>No data received</strong>
                                <p class="mb-0 mt-2">The server returned empty data. This could mean:</p>
                                <ul class="mb-0 mt-1">
                                    <li>No groups exist in the system</li>
                                    <li>No bidding processes have been started</li>
                                    <li>All payments are up to date</li>
                                </ul>
                            </div>
                        `;
                    } else {
                        contentDiv.innerHTML = data;
                    }
                })
                .catch(error => {
                    console.error('Error loading pending payments:', error);
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Error loading pending payments data</strong>
                            <p class="mb-0 mt-2">Error details: ${error.message}</p>
                            <p class="mb-0">Please check the browser console for more details and try again.</p>
                        </div>
                    `;
                });
        }

        function loadMonthDetails(groupId, monthNumber) {
            const contentDiv = document.getElementById('pendingPaymentsContent');

            // Show loading
            contentDiv.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading member details for Month ${monthNumber}...</p>
                </div>
            `;

            // Fetch month details
            fetch(`admin_get_pending_payments.php?action=month_details&group_id=${groupId}&month=${monthNumber}`)
                .then(response => response.text())
                .then(data => {
                    contentDiv.innerHTML = data;
                })
                .catch(error => {
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Error loading month details. Please try again.
                        </div>
                    `;
                });
        }

        // Auto-load pending payments summary on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPendingPayments();
        });

        // Add fade-in animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .dashboard-card {
                transition: all 0.3s ease;
            }
            .dashboard-card:hover {
                transform: translateY(-2px);
            }
        `;
        document.head.appendChild(style);
    </script>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Language Switcher Debug Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Language switcher initializing...');

            // Check if Bootstrap is loaded
            if (typeof bootstrap !== 'undefined') {
                console.log('Bootstrap is loaded successfully');
            } else {
                console.error('Bootstrap is not loaded!');
            }

            // Find the language dropdown
            const languageDropdown = document.getElementById('languageDropdown');
            if (languageDropdown) {
                console.log('Language dropdown found');

                // Add click event listener for debugging
                languageDropdown.addEventListener('click', function(e) {
                    console.log('Language dropdown clicked');
                    e.preventDefault();

                    // Manually toggle dropdown if Bootstrap fails
                    const dropdownMenu = this.nextElementSibling;
                    if (dropdownMenu) {
                        dropdownMenu.classList.toggle('show');
                        this.setAttribute('aria-expanded', dropdownMenu.classList.contains('show'));
                    }
                });

                // Try to initialize Bootstrap dropdown manually
                try {
                    const dropdown = new bootstrap.Dropdown(languageDropdown);
                    console.log('Bootstrap dropdown initialized manually');
                } catch (error) {
                    console.error('Failed to initialize Bootstrap dropdown:', error);
                }
            } else {
                console.error('Language dropdown not found!');
            }
        });
    </script>
</body>
</html>
