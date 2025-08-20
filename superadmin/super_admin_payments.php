<?php
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
    $whereConditions[] = "cp.status = ?";
    $params[] = $statusFilter;
}

if ($monthFilter) {
    $whereConditions[] = "MONTH(cp.payment_date) = ?";
    $params[] = $monthFilter;
}

if ($yearFilter) {
    $whereConditions[] = "YEAR(cp.payment_date) = ?";
    $params[] = $yearFilter;
}

if ($searchTerm) {
    $whereConditions[] = "(c.client_name LIKE ? OR c.company_name LIKE ? OR cp.transaction_id LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get payments with pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$query = "
    SELECT cp.*, 
           c.client_name, 
           c.company_name,
           cs.start_date as subscription_start,
           cs.end_date as subscription_end,
           sp.plan_name,
           sp.price as plan_price
    FROM client_payments cp
    JOIN clients c ON cp.client_id = c.id
    LEFT JOIN client_subscriptions cs ON cp.subscription_id = cs.id
    LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
    $whereClause
    ORDER BY cp.payment_date DESC, cp.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM client_payments cp
    JOIN clients c ON cp.client_id = c.id
    LEFT JOIN client_subscriptions cs ON cp.subscription_id = cs.id
    LEFT JOIN subscription_plans sp ON cs.plan_id = sp.id
    $whereClause
";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalPayments = $countStmt->fetch()['total'];
$totalPages = ceil($totalPayments / $perPage);

// Get clients for filter dropdown
$clientsStmt = $pdo->query("SELECT id, client_name, company_name FROM clients ORDER BY client_name");
$clients = $clientsStmt->fetchAll();

// Get payment statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments
    FROM client_payments cp
    JOIN clients c ON cp.client_id = c.id
    $whereClause
";

$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute($params);
$stats = $statsStmt->fetch();

// Set page title
$page_title = 'Payment Management';

// Include header
require_once 'includes/header.php';
?>

<!-- Page-specific styles -->
<style>
    .stats-card {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 1.5rem;
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
        transform: translateY(-10px);
        box-shadow: 0 25px 50px rgba(0,0,0,0.2);
    }

    .stats-card.revenue { --card-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .stats-card.pending { --card-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    .stats-card.success { --card-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .stats-card.failed { --card-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }

    .stats-number {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: var(--card-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stats-label {
        font-size: 1rem;
        color: #6c757d;
        font-weight: 600;
    }

    .filter-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        padding: 2rem;
        margin-bottom: 2rem;
        border: 1px solid rgba(255,255,255,0.2);
    }

    .payment-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-left: 5px solid #667eea;
        transition: all 0.3s ease;
    }

    .payment-card:hover {
        transform: translateX(10px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    }

    .status-badge {
        padding: 0.4rem 1rem;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .status-completed {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .status-pending {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
    }

    .status-failed {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }
</style>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-money-bill-wave me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                    Payment Management
                </h2>
                <p class="text-muted mb-0">Monitor and manage all client subscription payments</p>
            </div>
        </div>

        <!-- Payment Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card revenue">
                    <div class="stats-number"><?= formatCurrency($stats['total_revenue'] ?? 0, 'INR', 0) ?></div>
                    <div class="stats-label">Total Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card pending">
                    <div class="stats-number"><?= formatCurrency($stats['pending_amount'] ?? 0, 'INR', 0) ?></div>
                    <div class="stats-label">Pending Amount</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card success">
                    <div class="stats-number"><?= $stats['successful_payments'] ?? 0 ?></div>
                    <div class="stats-label">Successful Payments</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card failed">
                    <div class="stats-number"><?= $stats['failed_payments'] ?? 0 ?></div>
                    <div class="stats-label">Failed Payments</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="client_id" class="form-label">Client</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $clientFilter == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month">
                        <option value="">All Months</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $monthFilter == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search"
                           value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Client, Transaction ID...">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Payments List -->
        <div class="row">
            <div class="col-12">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No payments found</h5>
                        <p class="text-muted">No payments match your current filters</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h6 class="mb-1"><?= htmlspecialchars($payment['client_name']) ?></h6>
                                    <small class="text-muted"><?= htmlspecialchars($payment['company_name'] ?? 'No Company') ?></small>
                                </div>
                                <div class="col-md-2">
                                    <div class="fw-bold"><?= formatCurrency($payment['amount'], 'INR', 2) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($payment['plan_name'] ?? 'N/A') ?></small>
                                </div>
                                <div class="col-md-2">
                                    <span class="status-badge status-<?= $payment['status'] ?>">
                                        <?= ucfirst($payment['status']) ?>
                                    </span>
                                </div>
                                <div class="col-md-2">
                                    <div><?= formatDate($payment['payment_date']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($payment['payment_method'] ?? 'N/A') ?></small>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Transaction ID:</small><br>
                                    <code><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></code>
                                </div>
                                <div class="col-md-1">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="viewPaymentDetails(<?= $payment['id'] ?>)">
                                                <i class="fas fa-eye me-2"></i>View Details
                                            </a></li>
                                            <?php if ($payment['status'] === 'pending'): ?>
                                                <li><a class="dropdown-item" href="#" onclick="markAsCompleted(<?= $payment['id'] ?>)">
                                                    <i class="fas fa-check me-2"></i>Mark Completed
                                                </a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Payment pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
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

                        <div class="text-center text-muted">
                            Showing <?= ($page - 1) * $perPage + 1 ?> to <?= min($page * $perPage, $totalPayments) ?> of <?= $totalPayments ?> payments
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php
// Page-specific JavaScript
$additional_js = '
<script>
    function viewPaymentDetails(paymentId) {
        // Implementation for viewing payment details
        showNotification("Viewing payment details for ID: " + paymentId, "info");
        // Here you would typically open a modal or navigate to details page
    }

    function markAsCompleted(paymentId) {
        if (confirmAction("Mark this payment as completed?", function() {
            // Implementation for marking payment as completed
            showNotification("Payment marked as completed for ID: " + paymentId, "success");
            // Here you would make an AJAX call to update the payment status
        })) {
            showLoading();
        }
    }

    // Enhanced payment management functionality
    document.addEventListener("DOMContentLoaded", function() {
        // Add hover effects to payment cards
        const paymentCards = document.querySelectorAll(".payment-card");
        paymentCards.forEach(card => {
            card.addEventListener("mouseenter", function() {
                this.style.borderLeftWidth = "8px";
            });
            card.addEventListener("mouseleave", function() {
                this.style.borderLeftWidth = "5px";
            });
        });

        // Auto-refresh payment stats every 30 seconds
        setInterval(function() {
            // This would make an AJAX call to refresh stats
            console.log("Refreshing payment statistics...");
        }, 30000);
    });
</script>
';

// Include footer
require_once 'includes/footer.php';
?>
