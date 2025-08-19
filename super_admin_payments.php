<?php
require_once 'mt_config.php';
require_once 'subscription_functions.php';
requireSuperAdminLogin();

$pdo = getDB();

// Get filter parameters
$clientFilter = $_GET['client_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$monthFilter = $_GET['month'] ?? '';
$yearFilter = $_GET['year'] ?? date('Y');
$searchTerm = $_GET['search'] ?? '';

// Build the query
$whereConditions = [];
$params = [];

if ($clientFilter) {
    $whereConditions[] = "c.id = ?";
    $params[] = $clientFilter;
}

if ($statusFilter) {
    $whereConditions[] = "mp.payment_status = ?";
    $params[] = $statusFilter;
}

if ($monthFilter) {
    $whereConditions[] = "mp.month_number = ?";
    $params[] = $monthFilter;
}

if ($yearFilter) {
    $whereConditions[] = "YEAR(mp.payment_date) = ?";
    $params[] = $yearFilter;
}

if ($searchTerm) {
    $whereConditions[] = "(m.member_name LIKE ? OR bg.group_name LIKE ? OR c.client_name LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get payments with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$query = "
    SELECT mp.*,
           m.member_name, m.member_number,
           bg.group_name, bg.monthly_contribution,
           c.client_name, c.company_name,
           CASE
               WHEN mp.payment_status = 'paid' THEN mp.payment_amount
               ELSE bg.monthly_contribution
           END as expected_amount
    FROM member_payments mp
    JOIN members m ON mp.member_id = m.id
    JOIN bc_groups bg ON mp.group_id = bg.id
    JOIN clients c ON bg.client_id = c.id
    $whereClause
    ORDER BY mp.payment_date DESC, mp.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM member_payments mp
    JOIN members m ON mp.member_id = m.id
    JOIN bc_groups bg ON mp.group_id = bg.id
    JOIN clients c ON bg.client_id = c.id
    $whereClause
";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalPayments = $countStmt->fetch()['total'];
$totalPages = ceil($totalPayments / $perPage);

// Get summary statistics
$summaryQuery = "
    SELECT
        COUNT(*) as total_payments,
        SUM(CASE WHEN mp.payment_status = 'paid' THEN mp.payment_amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN mp.payment_status = 'pending' THEN bg.monthly_contribution ELSE 0 END) as total_pending,
        COUNT(CASE WHEN mp.payment_status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN mp.payment_status = 'pending' THEN 1 END) as pending_count
    FROM member_payments mp
    JOIN members m ON mp.member_id = m.id
    JOIN bc_groups bg ON mp.group_id = bg.id
    JOIN clients c ON bg.client_id = c.id
    $whereClause
";

$summaryStmt = $pdo->prepare($summaryQuery);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

// Get clients for filter dropdown
$clientsStmt = $pdo->query("SELECT id, client_name, company_name FROM clients ORDER BY client_name");
$clients = $clientsStmt->fetchAll();

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - <?= APP_NAME ?></title>
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
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .page-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-left: 4px solid var(--accent-color);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .stats-card.total { --accent-color: #667eea; }
        .stats-card.paid { --accent-color: #43e97b; }
        .stats-card.pending { --accent-color: #f093fb; }
        .stats-card.amount { --accent-color: #4facfe; }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 0.25rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .payments-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            font-weight: 600;
            color: #495057;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            border-color: #f1f3f4;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-paid {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .status-overdue {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .btn-modern {
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            color: white;
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
        
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 0.25rem;
            border: none;
            color: #667eea;
            font-weight: 600;
        }
        
        .pagination .page-link:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
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
                <a class="nav-link-modern" href="super_admin_dashboard.php">
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
                <a class="nav-link-modern active" href="super_admin_payments.php">
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
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-money-bill-wave me-3"></i>Payment Management
                    </h1>
                    <p class="page-subtitle mb-0">
                        Monitor and manage payments across all clients and groups
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

        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card total">
                    <div class="stats-number"><?= number_format($summary['total_payments']) ?></div>
                    <div class="stats-label">Total Payments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card paid">
                    <div class="stats-number"><?= number_format($summary['paid_count']) ?></div>
                    <div class="stats-label">Paid Payments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card pending">
                    <div class="stats-number"><?= number_format($summary['pending_count']) ?></div>
                    <div class="stats-label">Pending Payments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card amount">
                    <div class="stats-number"><?= formatCurrency($summary['total_paid']) ?></div>
                    <div class="stats-label">Total Amount Collected</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Payments</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-select">
                        <option value="">All Months</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $monthFilter == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Member, Group, or Client..." value="<?= htmlspecialchars($searchTerm) ?>">
                        <button type="submit" class="btn btn-primary-modern">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
            <?php if ($clientFilter || $statusFilter || $monthFilter || $searchTerm): ?>
                <div class="mt-3">
                    <a href="super_admin_payments.php" class="btn btn-outline-secondary btn-modern">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payments Table -->
        <div class="payments-table">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Group</th>
                            <th>Client</th>
                            <th>Month</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($payment['member_name']) ?></div>
                                        <small class="text-muted">Member #<?= $payment['member_number'] ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($payment['group_name']) ?></div>
                                        <small class="text-muted">₹<?= number_format($payment['monthly_contribution']) ?>/month</small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($payment['client_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($payment['company_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">Month <?= $payment['month_number'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= formatCurrency($payment['payment_amount'] ?? $payment['expected_amount']) ?></div>
                                        <?php if ($payment['payment_status'] === 'pending'): ?>
                                            <small class="text-muted">Expected: <?= formatCurrency($payment['expected_amount']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $payment['payment_status'] ?>">
                                            <?= ucfirst($payment['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($payment['payment_date']): ?>
                                            <div><?= formatDate($payment['payment_date']) ?></div>
                                            <small class="text-muted"><?= date('H:i', strtotime($payment['payment_date'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary btn-modern" onclick="viewPaymentDetails(<?= $payment['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($payment['payment_status'] === 'pending'): ?>
                                                <button class="btn btn-outline-success btn-modern" onclick="markAsPaid(<?= $payment['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No payments found</h5>
                                    <p class="text-muted">Try adjusting your filters or check back later</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Details Modal -->
    <div class="modal fade" id="paymentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="paymentDetailsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPaymentDetails(paymentId) {
            // For now, show basic info - can be enhanced later
            alert('Payment details for ID: ' + paymentId + '\n\nThis feature can be enhanced to show detailed payment information.');
        }

        function markAsPaid(paymentId) {
            if (confirm('Mark this payment as paid?')) {
                // For now, just reload - can be enhanced with AJAX later
                alert('This feature will be implemented to mark payments as paid.');
                // location.reload();
            }
        }
    </script>
</body>
</html>
