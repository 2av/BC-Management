<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

// Get filter parameters
$selectedGroupId = (int)($_GET['group_id'] ?? 0);
$reportType = $_GET['report_type'] ?? 'overview';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-t'); // Last day of current month

$pdo = getDB();

// Get all groups for filter dropdown
$groups = getAllGroups();

// Initialize report data
$reportData = [];

if ($reportType === 'overview') {
    // Overall financial overview
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT g.id) as total_groups,
            COUNT(DISTINCT CASE WHEN g.status = 'active' THEN g.id END) as active_groups,
            COUNT(DISTINCT m.id) as total_members,
            SUM(g.monthly_contribution * g.total_members) as total_monthly_collection,
            SUM(CASE WHEN mp.payment_status = 'paid' THEN mp.payment_amount ELSE 0 END) as total_collected,
            SUM(CASE WHEN mp.payment_status = 'pending' THEN g.monthly_contribution ELSE 0 END) as total_pending,
            COUNT(CASE WHEN mp.payment_status = 'paid' THEN 1 END) as paid_payments,
            COUNT(CASE WHEN mp.payment_status = 'pending' THEN 1 END) as pending_payments
        FROM bc_groups g
        LEFT JOIN members m ON g.id = m.group_id AND m.status = 'active'
        LEFT JOIN member_payments mp ON m.id = mp.member_id
    ");
    $reportData['overview'] = $stmt->fetch();
    
    // Monthly collection trend (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(mp.payment_date, '%Y-%m') as month,
            SUM(mp.payment_amount) as total_amount,
            COUNT(*) as payment_count
        FROM member_payments mp
        WHERE mp.payment_status = 'paid' 
        AND mp.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(mp.payment_date, '%Y-%m')
        ORDER BY month DESC
    ");
    $reportData['monthly_trend'] = $stmt->fetchAll();
    
    // Group-wise summary
    $stmt = $pdo->query("
        SELECT 
            g.id,
            g.group_name,
            g.monthly_contribution,
            g.total_members,
            g.status,
            (g.monthly_contribution * g.total_members) as expected_monthly,
            SUM(CASE WHEN mp.payment_status = 'paid' THEN mp.payment_amount ELSE 0 END) as total_collected,
            COUNT(CASE WHEN mp.payment_status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN mp.payment_status = 'pending' THEN 1 END) as pending_count,
            SUM(mb.bid_amount) as total_bids_amount,
            SUM(mb.net_payable) as total_distributed
        FROM bc_groups g
        LEFT JOIN members m ON g.id = m.group_id AND m.status = 'active'
        LEFT JOIN member_payments mp ON m.id = mp.member_id
        LEFT JOIN monthly_bids mb ON g.id = mb.group_id
        GROUP BY g.id
        ORDER BY g.group_name
    ");
    $reportData['group_summary'] = $stmt->fetchAll();

} elseif ($reportType === 'payments' && $selectedGroupId) {
    // Detailed payment report for specific group
    $group = getGroupById($selectedGroupId);
    $reportData['group'] = $group;
    
    // Payment details
    $stmt = $pdo->prepare("
        SELECT 
            mp.*,
            m.member_name,
            m.member_number,
            mb.bid_amount,
            mb.gain_per_member,
            winner.member_name as winner_name
        FROM member_payments mp
        JOIN members m ON mp.member_id = m.id
        LEFT JOIN monthly_bids mb ON mp.group_id = mb.group_id AND mp.month_number = mb.month_number
        LEFT JOIN members winner ON mb.taken_by_member_id = winner.id
        WHERE mp.group_id = ?
        AND (mp.payment_date BETWEEN ? AND ? OR mp.payment_date IS NULL)
        ORDER BY mp.month_number, m.member_number
    ");
    $stmt->execute([$selectedGroupId, $dateFrom, $dateTo]);
    $reportData['payments'] = $stmt->fetchAll();
    
    // Monthly summary for the group
    $stmt = $pdo->prepare("
        SELECT 
            mp.month_number,
            COUNT(*) as total_members,
            SUM(CASE WHEN mp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_members,
            SUM(CASE WHEN mp.payment_status = 'paid' THEN mp.payment_amount ELSE 0 END) as collected_amount,
            mb.bid_amount,
            mb.net_payable,
            winner.member_name as winner_name
        FROM member_payments mp
        LEFT JOIN monthly_bids mb ON mp.group_id = mb.group_id AND mp.month_number = mb.month_number
        LEFT JOIN members winner ON mb.taken_by_member_id = winner.id
        WHERE mp.group_id = ?
        GROUP BY mp.month_number
        ORDER BY mp.month_number
    ");
    $stmt->execute([$selectedGroupId]);
    $reportData['monthly_summary'] = $stmt->fetchAll();

} elseif ($reportType === 'bids') {
    // Bidding analysis report
    $whereClause = $selectedGroupId ? "WHERE mb.group_id = $selectedGroupId" : "";
    
    $stmt = $pdo->query("
        SELECT 
            mb.*,
            g.group_name,
            g.monthly_contribution,
            g.total_members,
            winner.member_name as winner_name,
            (g.monthly_contribution * g.total_members) as total_collection,
            (g.monthly_contribution * g.total_members - mb.bid_amount) as profit_distributed
        FROM monthly_bids mb
        JOIN bc_groups g ON mb.group_id = g.id
        JOIN members winner ON mb.taken_by_member_id = winner.id
        $whereClause
        ORDER BY mb.group_id, mb.month_number
    ");
    $reportData['bids'] = $stmt->fetchAll();
    
    // Bidding statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_bids,
            AVG(mb.bid_amount) as avg_bid_amount,
            MIN(mb.bid_amount) as min_bid_amount,
            MAX(mb.bid_amount) as max_bid_amount,
            SUM(mb.bid_amount) as total_bid_amount,
            SUM(mb.net_payable) as total_distributed,
            AVG(mb.gain_per_member) as avg_gain_per_member
        FROM monthly_bids mb
        JOIN bc_groups g ON mb.group_id = g.id
        $whereClause
    ");
    $reportData['bid_stats'] = $stmt->fetch();
}

// Set page title for the header
$page_title = 'Financial Reports';

// Include the new header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
    .report-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border: 1px solid #e3f2fd;
        transition: all 0.3s ease;
    }
    
    .report-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-item {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 10px;
        text-align: center;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }
    
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .filter-section {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    
    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .table th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        font-weight: 600;
    }
    
    .badge-paid {
        background: #28a745;
    }
    
    .badge-pending {
        background: #ffc107;
        color: #000;
    }
    
    .chart-container {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
    }
</style>

<!-- Page content starts here -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-chart-line text-primary me-2"></i>Financial Reports</h1>
        <div>
            <button onclick="window.print()" class="btn btn-outline-primary">
                <i class="fas fa-print me-1"></i> Print Report
            </button>
            <a href="index.php" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="report_type" class="form-label">Report Type</label>
                <select name="report_type" id="report_type" class="form-select" onchange="toggleGroupFilter()">
                    <option value="overview" <?= $reportType === 'overview' ? 'selected' : '' ?>>Financial Overview</option>
                    <option value="payments" <?= $reportType === 'payments' ? 'selected' : '' ?>>Payment Details</option>
                    <option value="bids" <?= $reportType === 'bids' ? 'selected' : '' ?>>Bidding Analysis</option>
                </select>
            </div>
            <div class="col-md-3" id="group_filter">
                <label for="group_id" class="form-label">Group</label>
                <select name="group_id" id="group_id" class="form-select">
                    <option value="">All Groups</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= $group['id'] ?>" <?= $selectedGroupId == $group['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($group['group_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" name="date_from" id="date_from" class="form-control" value="<?= $dateFrom ?>">
            </div>
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" name="date_to" id="date_to" class="form-control" value="<?= $dateTo ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block w-100">
                    <i class="fas fa-search me-1"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <?php if ($reportType === 'overview'): ?>
        <!-- Financial Overview Report -->
        <div class="report-card">
            <h3><i class="fas fa-chart-pie me-2"></i>Financial Overview</h3>

            <!-- Key Statistics -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($reportData['overview']['total_groups']) ?></div>
                    <div class="stat-label">Total Groups</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= number_format($reportData['overview']['total_members']) ?></div>
                    <div class="stat-label">Total Members</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">₹<?= number_format($reportData['overview']['total_collected']) ?></div>
                    <div class="stat-label">Total Collected</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">₹<?= number_format($reportData['overview']['total_pending']) ?></div>
                    <div class="stat-label">Pending Amount</div>
                </div>
            </div>

            <!-- Group-wise Summary -->
            <h4>Group-wise Financial Summary</h4>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Group Name</th>
                            <th>Status</th>
                            <th>Members</th>
                            <th>Monthly Contribution</th>
                            <th>Expected Monthly</th>
                            <th>Total Collected</th>
                            <th>Collection Rate</th>
                            <th>Total Distributed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['group_summary'] as $group): ?>
                            <tr>
                                <td>
                                    <a href="?report_type=payments&group_id=<?= $group['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($group['group_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge <?= $group['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= ucfirst($group['status']) ?>
                                    </span>
                                </td>
                                <td><?= number_format($group['total_members']) ?></td>
                                <td>₹<?= number_format($group['monthly_contribution']) ?></td>
                                <td>₹<?= number_format($group['expected_monthly']) ?></td>
                                <td>₹<?= number_format($group['total_collected']) ?></td>
                                <td>
                                    <?php
                                    $rate = $group['expected_monthly'] > 0 ? ($group['total_collected'] / $group['expected_monthly']) * 100 : 0;
                                    $badgeClass = $rate >= 80 ? 'bg-success' : ($rate >= 60 ? 'bg-warning' : 'bg-danger');
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= number_format($rate, 1) ?>%</span>
                                </td>
                                <td>₹<?= number_format($group['total_distributed'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Monthly Trend -->
        <?php if (!empty($reportData['monthly_trend'])): ?>
            <div class="report-card">
                <h4><i class="fas fa-chart-line me-2"></i>Monthly Collection Trend</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Total Amount</th>
                                <th>Payment Count</th>
                                <th>Average Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['monthly_trend'] as $trend): ?>
                                <tr>
                                    <td><?= date('F Y', strtotime($trend['month'] . '-01')) ?></td>
                                    <td>₹<?= number_format($trend['total_amount']) ?></td>
                                    <td><?= number_format($trend['payment_count']) ?></td>
                                    <td>₹<?= number_format($trend['total_amount'] / max(1, $trend['payment_count'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($reportType === 'payments' && $selectedGroupId): ?>
        <!-- Payment Details Report -->
        <div class="report-card">
            <h3><i class="fas fa-money-bill-wave me-2"></i>Payment Details - <?= htmlspecialchars($reportData['group']['group_name']) ?></h3>

            <!-- Monthly Summary -->
            <?php if (!empty($reportData['monthly_summary'])): ?>
                <h4>Monthly Summary</h4>
                <div class="table-responsive mb-4">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Winner</th>
                                <th>Bid Amount</th>
                                <th>Net Payable</th>
                                <th>Members Paid</th>
                                <th>Amount Collected</th>
                                <th>Collection Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['monthly_summary'] as $summary): ?>
                                <tr>
                                    <td>Month <?= $summary['month_number'] ?></td>
                                    <td><?= htmlspecialchars($summary['winner_name'] ?? 'No winner yet') ?></td>
                                    <td>₹<?= number_format($summary['bid_amount'] ?? 0) ?></td>
                                    <td>₹<?= number_format($summary['net_payable'] ?? 0) ?></td>
                                    <td><?= $summary['paid_members'] ?>/<?= $summary['total_members'] ?></td>
                                    <td>₹<?= number_format($summary['collected_amount']) ?></td>
                                    <td>
                                        <?php
                                        $rate = ($summary['paid_members'] / max(1, $summary['total_members'])) * 100;
                                        $badgeClass = $rate >= 80 ? 'bg-success' : ($rate >= 60 ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= number_format($rate, 1) ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Detailed Payment List -->
            <h4>Detailed Payment Records</h4>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Month</th>
                            <th>Expected Amount</th>
                            <th>Paid Amount</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                            <th>Winner</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['payments'] as $payment): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment['member_name']) ?> (#<?= $payment['member_number'] ?>)</td>
                                <td>Month <?= $payment['month_number'] ?></td>
                                <td>₹<?= number_format($payment['gain_per_member'] ?? $reportData['group']['monthly_contribution']) ?></td>
                                <td>₹<?= number_format($payment['payment_amount']) ?></td>
                                <td><?= $payment['payment_date'] ? date('d M Y', strtotime($payment['payment_date'])) : '-' ?></td>
                                <td>
                                    <span class="badge badge-<?= $payment['payment_status'] === 'paid' ? 'paid' : 'pending' ?>">
                                        <?= ucfirst($payment['payment_status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($payment['winner_name'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($reportType === 'bids'): ?>
        <!-- Bidding Analysis Report -->
        <div class="report-card">
            <h3><i class="fas fa-gavel me-2"></i>Bidding Analysis</h3>

            <!-- Bidding Statistics -->
            <?php if ($reportData['bid_stats']): ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?= number_format($reportData['bid_stats']['total_bids']) ?></div>
                        <div class="stat-label">Total Bids</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">₹<?= number_format($reportData['bid_stats']['avg_bid_amount']) ?></div>
                        <div class="stat-label">Average Bid</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">₹<?= number_format($reportData['bid_stats']['total_distributed']) ?></div>
                        <div class="stat-label">Total Distributed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">₹<?= number_format($reportData['bid_stats']['avg_gain_per_member']) ?></div>
                        <div class="stat-label">Avg Gain/Member</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Detailed Bid Records -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Group</th>
                            <th>Month</th>
                            <th>Winner</th>
                            <th>Bid Amount</th>
                            <th>Total Collection</th>
                            <th>Net Payable</th>
                            <th>Profit Distributed</th>
                            <th>Gain per Member</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['bids'] as $bid): ?>
                            <tr>
                                <td><?= htmlspecialchars($bid['group_name']) ?></td>
                                <td>Month <?= $bid['month_number'] ?></td>
                                <td><?= htmlspecialchars($bid['winner_name']) ?></td>
                                <td>₹<?= number_format($bid['bid_amount']) ?></td>
                                <td>₹<?= number_format($bid['total_collection']) ?></td>
                                <td>₹<?= number_format($bid['net_payable']) ?></td>
                                <td>₹<?= number_format($bid['profit_distributed']) ?></td>
                                <td>₹<?= number_format($bid['gain_per_member']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleGroupFilter() {
        const reportType = document.getElementById('report_type').value;
        const groupFilter = document.getElementById('group_filter');

        if (reportType === 'payments') {
            groupFilter.style.display = 'block';
            document.getElementById('group_id').required = true;
        } else {
            groupFilter.style.display = 'block'; // Show for all report types
            document.getElementById('group_id').required = false;
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleGroupFilter();
    });
</script>

<?php require_once 'includes/footer.php'; ?>
