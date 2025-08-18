<?php
require_once 'config.php';
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
    <title><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
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
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-users"></i> Members
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="admin_members.php">
                                <i class="fas fa-list"></i> All Members
                            </a></li>
                            <li><a class="dropdown-item" href="admin_add_member.php">
                                <i class="fas fa-user-plus"></i> Add Member
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin_bulk_import.php">
                                <i class="fas fa-upload"></i> Bulk Import
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-layer-group"></i> Groups
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="index.php">
                                <i class="fas fa-list"></i> All Groups
                            </a></li>
                            <li><a class="dropdown-item" href="admin_create_group_simple.php">
                                <i class="fas fa-plus"></i> Create Group
                            </a></li>
                            <li><a class="dropdown-item" href="admin_manage_groups.php">
                                <i class="fas fa-cogs"></i> Manage Groups
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="create_group.php">
                                <i class="fas fa-tools"></i> Advanced Create
                            </a></li>
                        </ul>
                    </li>
                </ul>

                <div class="navbar-nav ms-auto">
                    <span class="navbar-text me-3">
                        Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?>
                    </span>
                    <a class="nav-link text-white" href="admin_change_password.php">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                    <a class="nav-link text-white" href="?logout=1">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php $msg = getMessage(); ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-users-cog text-primary me-2"></i>Mitra Niidhi Samooh Dashboard</h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-calendar-alt text-info"></i> <?= date('l, F j, Y') ?> |
                    <i class="fas fa-clock text-success"></i> <?= date('g:i A') ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="admin_members.php" class="btn btn-outline-success btn-interactive">
                    <i class="fas fa-users-cog me-1"></i> Manage Members
                </a>
                <a href="admin_change_password.php" class="btn btn-outline-primary btn-interactive">
                    <i class="fas fa-shield-alt me-1"></i> Change Password
                </a>
                <a href="admin_create_group_simple.php" class="btn btn-primary btn-interactive">
                    <i class="fas fa-plus-circle me-1"></i> Create New Group
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <a href="#groupsList" class="text-decoration-none" onclick="scrollToGroups()">
                    <div class="card stat-card dashboard-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h3><?= $totalGroups ?></h3>
                            <p class="mb-0">Total Groups</p>
                            <small><?= $activeGroups ?> Active, <?= $completedGroups ?> Completed</small>
                            <div class="mt-2">
                                <i class="fas fa-arrow-down"></i> <small>Click to view</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="admin_members.php" class="text-decoration-none">
                    <div class="card stat-card-success dashboard-card">
                        <div class="card-body text-center text-white">
                            <i class="fas fa-user-friends fa-2x mb-2"></i>
                            <h3><?= $totalMembers ?></h3>
                            <p class="mb-0">Total Members</p>
                            <small>Across all groups</small>
                            <div class="mt-2">
                                <i class="fas fa-external-link-alt"></i> <small>Manage members</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-info dashboard-card" style="cursor: pointer;" onclick="showCollectionDetails()">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                        <h3><?= formatCurrency($totalCollected) ?></h3>
                        <p class="mb-0">Total Collected</p>
                        <small>All payments received</small>
                        <div class="mt-2">
                            <i class="fas fa-info-circle"></i> <small>Click for details</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-warning dashboard-card" style="cursor: pointer;" onclick="showDistributionDetails()">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                        <h3><?= formatCurrency($totalDistributed) ?></h3>
                        <p class="mb-0">Total Distributed</p>
                        <small>Amount given to winners</small>
                        <div class="mt-2">
                            <i class="fas fa-info-circle"></i> <small>Click for details</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="card mb-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-1"><i class="fas fa-bolt text-warning"></i> Quick Actions</h6>
                        <p class="text-muted mb-0">Frequently used admin functions</p>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="admin_add_member.php" class="btn btn-sm btn-outline-success" title="Add New Member">
                                <i class="fas fa-user-plus"></i>
                            </a>
                            <a href="admin_payment_config.php" class="btn btn-sm btn-outline-primary" title="QR Code Settings">
                                <i class="fas fa-qrcode"></i>
                            </a>
                            <a href="admin_payment_status.php" class="btn btn-sm btn-outline-warning" title="Payment Status">
                                <i class="fas fa-credit-card"></i>
                            </a>
                            <a href="admin_bulk_import.php" class="btn btn-sm btn-outline-info" title="Bulk Import">
                                <i class="fas fa-upload"></i>
                            </a>
                            <a href="admin_create_group_simple.php" class="btn btn-sm btn-outline-primary" title="Create Group">
                                <i class="fas fa-plus"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard()" title="Refresh Data">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group-wise Pending Payments Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle text-warning"></i> Group-wise Pending Payments
                                </h5>
                                <small class="text-muted">Click on groups to expand and view month-wise details</small>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="loadPendingPayments()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="pendingPaymentsContent">
                            <div class="text-center text-muted py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading group-wise pending payments...</p>
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
                            <i class="fas fa-chart-line"></i> Monthly Collection Trend
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
                            <i class="fas fa-chart-pie"></i> Group Status
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
                            <i class="fas fa-chart-bar"></i> Group Progress
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
                            <i class="fas fa-clock"></i> Recent Activities
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
        <div class="card dashboard-card" id="groupsList">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> All BC Groups
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary" onclick="filterGroups('all')" id="filterAll">
                        All
                    </button>
                    <button class="btn btn-sm btn-outline-success" onclick="filterGroups('active')" id="filterActive">
                        Active
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="filterGroups('completed')" id="filterCompleted">
                        Completed
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (!empty($groups)): ?>
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-6 col-lg-4 mb-3" data-group-status="<?= $group['status'] ?>">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($group['group_name']) ?></h5>
                                <p class="card-text">
                                    <strong>Members:</strong> <?= $group['total_members'] ?><br>
                                    <strong>Monthly:</strong> <?= formatCurrency($group['monthly_contribution']) ?><br>
                                    <strong>Total Collection:</strong> <?= formatCurrency($group['total_monthly_collection']) ?><br>
                                    <strong>Start Date:</strong> <?= formatDate($group['start_date']) ?><br>
                                    <span class="badge bg-<?= $group['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($group['status']) ?>
                                    </span>
                                </p>
                                <a href="view_group.php?id=<?= $group['id'] ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Details
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
                .then(response => response.text())
                .then(data => {
                    contentDiv.innerHTML = data;
                })
                .catch(error => {
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Error loading pending payments data. Please try again.
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
</body>
</html>
