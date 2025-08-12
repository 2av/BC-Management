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
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins"></i> <?= APP_NAME ?>
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
                            <li><a class="dropdown-item" href="create_group.php">
                                <i class="fas fa-plus"></i> Create Group
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
            <h1>BC Management Dashboard</h1>
            <div class="d-flex gap-2">
                <a href="admin_change_password.php" class="btn btn-outline-primary">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="create_group.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Group
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card dashboard-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h3><?= $totalGroups ?></h3>
                        <p class="mb-0">Total Groups</p>
                        <small><?= $activeGroups ?> Active, <?= $completedGroups ?> Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-success dashboard-card">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-user-friends fa-2x mb-2"></i>
                        <h3><?= $totalMembers ?></h3>
                        <p class="mb-0">Total Members</p>
                        <small>Across all groups</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-info dashboard-card">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                        <h3><?= formatCurrency($totalCollected) ?></h3>
                        <p class="mb-0">Total Collected</p>
                        <small>All payments received</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card-warning dashboard-card">
                    <div class="card-body text-center text-white">
                        <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                        <h3><?= formatCurrency($totalDistributed) ?></h3>
                        <p class="mb-0">Total Distributed</p>
                        <small>Amount given to winners</small>
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
        <div class="card dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> All BC Groups
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (!empty($groups)): ?>
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-6 col-lg-4 mb-3">
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
                        <a href="create_group.php" class="btn btn-primary">
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
    </script>
</body>
</html>
