<?php
session_start();
require_once '../config/config.php';

$pdo = getDB();
$message = '';
$messageType = '';

// Handle actions
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'migrate_win_data') {
            // First, check if win columns exist
            $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'has_won_month'");
            if ($stmt->rowCount() == 0) {
                throw new Exception("No 'has_won_month' column found in members table. Nothing to migrate!");
            }

            // Get win data from members table
            $stmt = $pdo->query("
                SELECT id, member_name, has_won_month, won_amount
                FROM members
                WHERE has_won_month IS NOT NULL AND has_won_month != 0
            ");
            $winData = $stmt->fetchAll();
            
            $migratedCount = 0;
            foreach ($winData as $data) {
                // Check if group_members table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'group_members'");
                if ($stmt->rowCount() > 0) {
                    // Use group_members table
                    $stmt = $pdo->prepare("
                        SELECT gm.id as group_member_id, gm.group_id
                        FROM group_members gm
                        WHERE gm.member_id = ?
                    ");
                    $stmt->execute([$data['id']]);
                    $assignments = $stmt->fetchAll();

                    foreach ($assignments as $assignment) {
                        // Create a monthly_bid record for this win
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO monthly_bids
                            (group_id, month_number, taken_by_member_id, net_payable)
                            VALUES (?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $assignment['group_id'],
                            $data['has_won_month'],
                            $assignment['group_member_id'],
                            $data['won_amount']
                        ]);

                        if ($pdo->lastInsertId() > 0) {
                            $migratedCount++;
                        }
                    }
                } else {
                    // Use members table directly (old structure)
                    $stmt = $pdo->prepare("
                        SELECT group_id FROM members WHERE id = ?
                    ");
                    $stmt->execute([$data['id']]);
                    $member = $stmt->fetch();

                    if ($member && $member['group_id']) {
                        // Create a monthly_bid record for this win
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO monthly_bids
                            (group_id, month_number, taken_by_member_id, net_payable)
                            VALUES (?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $member['group_id'],
                            $data['has_won_month'],
                            $data['id'], // Use member_id directly
                            $data['won_amount']
                        ]);

                        if ($pdo->lastInsertId() > 0) {
                            $migratedCount++;
                        }
                    }
                }
            }
            
            $message = "Migrated {$migratedCount} win records to monthly_bids table!";
            $messageType = "success";
        }
        
        if ($action === 'remove_win_columns') {
            // Remove the win columns from members table
            $columnsToRemove = ['has_won_month', 'won_amount'];
            
            foreach ($columnsToRemove as $column) {
                $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE '{$column}'");
                if ($stmt->rowCount() > 0) {
                    $pdo->exec("ALTER TABLE members DROP COLUMN {$column}");
                }
            }
            
            $message = "Win columns removed from members table successfully!";
            $messageType = "success";
        }
        
        if ($action === 'clean_all_win_columns') {
            // Remove all win-related columns that shouldn't be in members table
            $possibleWinColumns = [
                'has_won_month', 'won_amount', 'win_month', 'win_amount', 
                'bid_amount', 'net_payable', 'month_year', 'bid_status'
            ];
            
            $removedColumns = [];
            foreach ($possibleWinColumns as $column) {
                $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE '{$column}'");
                if ($stmt->rowCount() > 0) {
                    try {
                        $pdo->exec("ALTER TABLE members DROP COLUMN {$column}");
                        $removedColumns[] = $column;
                    } catch (PDOException $e) {
                        // Column might have constraints
                    }
                }
            }
            
            $message = "Removed columns: " . implode(', ', $removedColumns);
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Check current structure
$stmt = $pdo->query("DESCRIBE members");
$memberColumns = $stmt->fetchAll();

// Check for win-related columns
$winColumns = [];
foreach ($memberColumns as $col) {
    $colName = strtolower($col['Field']);
    if (strpos($colName, 'win') !== false || strpos($colName, 'bid') !== false || strpos($colName, 'month') !== false) {
        $winColumns[] = $col;
    }
}

// Get sample win data (check if columns exist first)
$sampleWinData = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'has_won_month'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT id, member_name, has_won_month, won_amount
            FROM members
            WHERE has_won_month IS NOT NULL AND has_won_month != 0
            LIMIT 10
        ");
        $sampleWinData = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Columns don't exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Win Columns - BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .problem-zone { border: 2px solid #dc3545; background: #fff5f5; }
        .solution-zone { border: 2px solid #198754; background: #f0fff4; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3">
                <h5><i class="fas fa-cogs"></i> Data Management</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="data_management.php">
                            <i class="fas fa-arrow-left"></i> Back to Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="assign_members.php">
                            <i class="fas fa-link"></i> Assign Members
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-exclamation-triangle"></i> Fix Win Columns Issue</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Current Members Table Structure -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-table"></i> Current Members Table Structure</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Column Name</th><th>Type</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memberColumns as $col): ?>
                                        <?php
                                        $colName = strtolower($col['Field']);
                                        $isWinColumn = (strpos($colName, 'win') !== false || strpos($colName, 'bid') !== false || strpos($colName, 'month') !== false);
                                        $rowClass = $isWinColumn ? 'table-warning' : '';
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td><code><?php echo $col['Field']; ?></code></td>
                                            <td><?php echo $col['Type']; ?></td>
                                            <td>
                                                <?php if ($isWinColumn): ?>
                                                    <span class="badge bg-warning">Should be in monthly_bids</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Correct</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Problem Explanation -->
                <div class="problem-zone rounded p-4 mb-4">
                    <h3><i class="fas fa-bug"></i> Analysis</h3>

                    <?php if (!empty($winColumns)): ?>
                        <p class="text-warning">⚠️ Found win-related columns in members table that should be moved:</p>
                        <ul>
                            <?php foreach ($winColumns as $col): ?>
                                <li><code><?php echo $col['Field']; ?></code> → Should be in <strong>monthly_bids table</strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-success">✅ No win-related columns found in members table! Your structure looks correct.</p>
                        <p class="text-info">If you're seeing this, your database structure is already properly normalized.</p>
                    <?php endif; ?>
                </div>

                <!-- Sample Win Data -->
                <?php if (!empty($sampleWinData)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-trophy"></i> Sample Win Data Found</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Member Name</th>
                                        <th>Won Month</th>
                                        <th>Won Amount</th>
                                        <th>Action Needed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sampleWinData as $data): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($data['member_name']); ?></td>
                                            <td><?php echo $data['has_won_month']; ?></td>
                                            <td>₹<?php echo number_format($data['won_amount']); ?></td>
                                            <td>Move to monthly_bids table</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Solution -->
                <div class="solution-zone rounded p-4 mb-4">
                    <h3><i class="fas fa-tools"></i> Solution</h3>
                    <p>Follow these steps to fix the structure:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="migrate_win_data">
                                <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Migrate win data to monthly_bids table?')">
                                    <i class="fas fa-database"></i> 1. Migrate Win Data
                                </button>
                                <small class="text-muted">Move win records to proper monthly_bids table</small>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="clean_all_win_columns">
                                <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Remove all win columns from members table?')">
                                    <i class="fas fa-broom"></i> 2. Clean Members Table
                                </button>
                                <small class="text-muted">Remove win columns from members table</small>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Correct Structure -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-check-circle"></i> Correct Database Structure</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h5>members table</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">id</li>
                                    <li class="list-group-item">member_name</li>
                                    <li class="list-group-item">username</li>
                                    <li class="list-group-item">email</li>
                                    <li class="list-group-item">phone</li>
                                    <li class="list-group-item">password</li>
                                    <li class="list-group-item">role</li>
                                    <li class="list-group-item">created_at</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h5>group_members table</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">id</li>
                                    <li class="list-group-item">group_id</li>
                                    <li class="list-group-item">member_id</li>
                                    <li class="list-group-item">member_number</li>
                                    <li class="list-group-item">joined_date</li>
                                    <li class="list-group-item">status</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h5>monthly_bids table</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">id</li>
                                    <li class="list-group-item">group_id</li>
                                    <li class="list-group-item">month_year</li>
                                    <li class="list-group-item">taken_by_member_id</li>
                                    <li class="list-group-item bg-warning">has_won_month ← HERE</li>
                                    <li class="list-group-item bg-warning">won_amount ← HERE</li>
                                    <li class="list-group-item">bid_amount</li>
                                    <li class="list-group-item">net_payable</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
