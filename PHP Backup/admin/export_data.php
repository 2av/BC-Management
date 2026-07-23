<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

// Handle export requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    $exportType = $_POST['export_type'];
    $groupId = (int)($_POST['group_id'] ?? 0);
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    
    $pdo = getDB();
    $filename = '';
    $data = [];
    
    switch ($exportType) {
        case 'members':
            $filename = 'members_export_' . date('Y-m-d') . '.csv';
            $whereClause = $groupId ? "WHERE gm.group_id = $groupId" : "";

            $stmt = $pdo->query("
                SELECT
                    m.member_name,
                    gm.member_number,
                    m.phone,
                    m.email,
                    g.group_name,
                    g.monthly_contribution,
                    m.status,
                    m.created_at,
                    COALESCE(ms.total_paid, 0) as total_paid,
                    COALESCE(ms.given_amount, 0) as given_amount,
                    COALESCE(ms.profit, 0) as profit
                FROM members m
                JOIN group_members gm ON m.id = gm.member_id AND gm.status = 'active'
                JOIN bc_groups g ON gm.group_id = g.id
                LEFT JOIN member_summary ms ON m.id = ms.member_id AND g.id = ms.group_id
                $whereClause
                ORDER BY g.group_name, gm.member_number
            ");
            $data = $stmt->fetchAll();
            break;
            
        case 'payments':
            $filename = 'payments_export_' . date('Y-m-d') . '.csv';
            $whereClause = [];
            $params = [];
            
            if ($groupId) {
                $whereClause[] = "mp.group_id = ?";
                $params[] = $groupId;
            }
            if ($dateFrom) {
                $whereClause[] = "mp.payment_date >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo) {
                $whereClause[] = "mp.payment_date <= ?";
                $params[] = $dateTo;
            }
            
            $whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';
            
            $stmt = $pdo->prepare("
                SELECT 
                    g.group_name,
                    m.member_name,
                    m.member_number,
                    mp.month_number,
                    mp.payment_amount,
                    mp.payment_date,
                    mp.payment_status,
                    mp.payment_method,
                    winner.member_name as winner_name,
                    mb.bid_amount,
                    mb.gain_per_member
                FROM member_payments mp
                JOIN members m ON mp.member_id = m.id
                JOIN bc_groups g ON mp.group_id = g.id
                LEFT JOIN monthly_bids mb ON mp.group_id = mb.group_id AND mp.month_number = mb.month_number
                LEFT JOIN members winner ON mb.taken_by_member_id = winner.id
                $whereSQL
                ORDER BY g.group_name, mp.month_number, m.member_number
            ");
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            break;
            
        case 'groups':
            $filename = 'groups_export_' . date('Y-m-d') . '.csv';
            
            $stmt = $pdo->query("
                SELECT 
                    g.group_name,
                    g.monthly_contribution,
                    g.total_members,
                    g.status,
                    g.start_date,
                    g.created_at,
                    (g.monthly_contribution * g.total_members) as total_monthly_collection,
                    COUNT(DISTINCT m.id) as actual_members,
                    SUM(CASE WHEN mp.payment_status = 'paid' THEN mp.payment_amount ELSE 0 END) as total_collected,
                    COUNT(CASE WHEN mp.payment_status = 'paid' THEN 1 END) as paid_payments,
                    COUNT(CASE WHEN mp.payment_status = 'pending' THEN 1 END) as pending_payments
                FROM bc_groups g
                LEFT JOIN members m ON g.id = m.group_id AND m.status = 'active'
                LEFT JOIN member_payments mp ON m.id = mp.member_id
                GROUP BY g.id
                ORDER BY g.group_name
            ");
            $data = $stmt->fetchAll();
            break;
            
        case 'bids':
            $filename = 'bids_export_' . date('Y-m-d') . '.csv';
            $whereClause = $groupId ? "WHERE mb.group_id = $groupId" : "";
            
            $stmt = $pdo->query("
                SELECT 
                    g.group_name,
                    mb.month_number,
                    winner.member_name as winner_name,
                    mb.bid_amount,
                    mb.net_payable,
                    mb.gain_per_member,
                    mb.payment_date,
                    mb.payment_status,
                    (g.monthly_contribution * g.total_members) as total_collection,
                    (g.monthly_contribution * g.total_members - mb.bid_amount) as profit_distributed
                FROM monthly_bids mb
                JOIN bc_groups g ON mb.group_id = g.id
                JOIN members winner ON mb.taken_by_member_id = winner.id
                $whereClause
                ORDER BY g.group_name, mb.month_number
            ");
            $data = $stmt->fetchAll();
            break;
    }
    
    if (!empty($data)) {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Write CSV headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        exit;
    }
}

// Get all groups for filter dropdown
$groups = getAllGroups();

// Set page title for the header
$page_title = 'Export Data';

// Include the new header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
    .export-card {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        border: 1px solid #e3f2fd;
        transition: all 0.3s ease;
    }
    
    .export-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .export-option {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px solid #dee2e6;
        border-radius: 10px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .export-option:hover {
        border-color: #667eea;
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    }
    
    .export-option.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .export-icon {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: #667eea;
    }
    
    .export-option.selected .export-icon {
        color: white;
    }
    
    .filter-section {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
        margin-top: 1rem;
        display: none;
    }
    
    .filter-section.show {
        display: block;
    }
</style>

<!-- Page content starts here -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-download text-primary me-2"></i>Export Data</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="export-card">
        <h3><i class="fas fa-file-export me-2"></i>Data Export Options</h3>
        <p class="text-muted mb-4">Select the type of data you want to export and configure your export settings.</p>
        
        <form method="POST" id="exportForm">
            <!-- Export Type Selection -->
            <div class="row">
                <div class="col-md-6 col-lg-3">
                    <div class="export-option" data-type="members">
                        <div class="text-center">
                            <i class="fas fa-users export-icon"></i>
                            <h5>Members</h5>
                            <p class="mb-0">Export member details, contact info, and financial summary</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="export-option" data-type="payments">
                        <div class="text-center">
                            <i class="fas fa-money-bill-wave export-icon"></i>
                            <h5>Payments</h5>
                            <p class="mb-0">Export payment records, status, and transaction details</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="export-option" data-type="groups">
                        <div class="text-center">
                            <i class="fas fa-layer-group export-icon"></i>
                            <h5>Groups</h5>
                            <p class="mb-0">Export group information and collection statistics</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="export-option" data-type="bids">
                        <div class="text-center">
                            <i class="fas fa-gavel export-icon"></i>
                            <h5>Bids</h5>
                            <p class="mb-0">Export bidding records and winner information</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="export_type" id="export_type" required>
            
            <!-- Filter Options -->
            <div class="filter-section" id="filterSection">
                <h5><i class="fas fa-filter me-2"></i>Export Filters</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="group_id" class="form-label">Group (Optional)</label>
                        <select name="group_id" id="group_id" class="form-select">
                            <option value="">All Groups</option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= $group['id'] ?>">
                                    <?= htmlspecialchars($group['group_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4" id="dateFromContainer">
                        <label for="date_from" class="form-label">From Date (Optional)</label>
                        <input type="date" name="date_from" id="date_from" class="form-control">
                    </div>
                    <div class="col-md-4" id="dateToContainer">
                        <label for="date_to" class="form-label">To Date (Optional)</label>
                        <input type="date" name="date_to" id="date_to" class="form-control">
                    </div>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-download me-2"></i>Export to CSV
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetForm()">
                        <i class="fas fa-undo me-2"></i>Reset
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Export Information -->
    <div class="export-card">
        <h4><i class="fas fa-info-circle me-2"></i>Export Information</h4>
        <div class="row">
            <div class="col-md-6">
                <h6>File Format</h6>
                <p>All exports are generated in CSV (Comma Separated Values) format, which can be opened in Excel, Google Sheets, or any spreadsheet application.</p>
                
                <h6>Data Included</h6>
                <ul>
                    <li><strong>Members:</strong> Personal details, group membership, financial summary</li>
                    <li><strong>Payments:</strong> Payment history, amounts, dates, status</li>
                    <li><strong>Groups:</strong> Group details, member counts, collection statistics</li>
                    <li><strong>Bids:</strong> Bidding history, winners, amounts, profit distribution</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Privacy & Security</h6>
                <p>Exported data contains sensitive financial information. Please ensure:</p>
                <ul>
                    <li>Files are stored securely</li>
                    <li>Access is limited to authorized personnel</li>
                    <li>Files are deleted when no longer needed</li>
                    <li>Data protection regulations are followed</li>
                </ul>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Important:</strong> Exported files contain personal and financial data. Handle with care.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const exportOptions = document.querySelectorAll('.export-option');
        const exportTypeInput = document.getElementById('export_type');
        const filterSection = document.getElementById('filterSection');
        const dateFromContainer = document.getElementById('dateFromContainer');
        const dateToContainer = document.getElementById('dateToContainer');
        
        exportOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                exportOptions.forEach(opt => opt.classList.remove('selected'));
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Set export type
                const exportType = this.dataset.type;
                exportTypeInput.value = exportType;
                
                // Show filter section
                filterSection.classList.add('show');
                
                // Show/hide date filters based on export type
                if (exportType === 'payments' || exportType === 'bids') {
                    dateFromContainer.style.display = 'block';
                    dateToContainer.style.display = 'block';
                } else {
                    dateFromContainer.style.display = 'none';
                    dateToContainer.style.display = 'none';
                }
            });
        });
    });
    
    function resetForm() {
        document.querySelectorAll('.export-option').forEach(opt => opt.classList.remove('selected'));
        document.getElementById('export_type').value = '';
        document.getElementById('filterSection').classList.remove('show');
        document.getElementById('exportForm').reset();
    }
</script>

<?php require_once 'includes/footer.php'; ?>
