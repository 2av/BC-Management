<?php
session_start();
require_once '../config/config.php';

$pdo = getDB();
$message = '';
$messageType = '';

// Migration status tracking
function getMigrationStatus($pdo) {
    $status = [
        'group_members_table_exists' => false,
        'members_has_group_columns' => false,
        'members_has_win_columns' => false,
        'data_migrated' => false,
        'pages_affected' => []
    ];
    
    // Check if group_members table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'group_members'");
    $status['group_members_table_exists'] = $stmt->rowCount() > 0;
    
    // Check if members has group columns
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
    $status['members_has_group_columns'] = $stmt->rowCount() > 0;
    
    // Check if members has win columns
    $winColumns = ['has_won_month', 'won_amount', 'win_month', 'win_amount'];
    foreach ($winColumns as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE '{$col}'");
        if ($stmt->rowCount() > 0) {
            $status['members_has_win_columns'] = true;
            break;
        }
    }
    
    // Check if data is migrated
    if ($status['group_members_table_exists']) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM group_members");
        $status['data_migrated'] = $stmt->fetchColumn() > 0;
    }
    
    // Affected pages and functions
    $status['pages_affected'] = [
        'config/main_config.php' => 'Core functions (getGroupMembers, memberLogin, etc.)',
        'config/multi_tenant_config.php' => 'Multi-tenant functions',
        'common/functions.php' => 'Helper functions',
        'admin/dashboard.php' => 'Group member listings',
        'admin/manage_groups.php' => 'Group management',
        'admin/members.php' => 'Member management',
        'member/dashboard.php' => 'Member dashboard',
        'member/member_dashboard_old.php' => 'Old member dashboard'
    ];
    
    return $status;
}

// Handle migration
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'complete_migration') {
            $migrationLog = [];
            $hasErrors = false;
            
            // Step 1: Create group_members table
            $migrationLog[] = "Creating group_members table...";
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    member_id INT NOT NULL,
                    member_number INT NOT NULL,
                    joined_date DATE DEFAULT (CURRENT_DATE),
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_group_member (group_id, member_id),
                    UNIQUE KEY unique_member_number_per_group (group_id, member_number),
                    INDEX idx_group_id (group_id),
                    INDEX idx_member_id (member_id)
                )");
                $migrationLog[] = "✅ group_members table created successfully";
            } catch (PDOException $e) {
                $migrationLog[] = "⚠️ group_members table creation: " . $e->getMessage();
                $hasErrors = true;
            }
            
            // Step 2: Migrate data from members table
            $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
            if ($stmt->rowCount() > 0) {
                $migrationLog[] = "Migrating member-group assignments...";
                
                // Get unique members with their group assignments
                $stmt = $pdo->query("
                    SELECT DISTINCT member_name, group_id, member_number, 
                           MIN(id) as master_id
                    FROM members 
                    WHERE group_id IS NOT NULL AND group_id > 0
                    GROUP BY member_name, group_id, member_number
                    ORDER BY member_name
                ");
                $memberData = $stmt->fetchAll();
                
                $migratedCount = 0;
                foreach ($memberData as $data) {
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO group_members 
                        (group_id, member_id, member_number, status, joined_date)
                        VALUES (?, ?, ?, 'active', CURDATE())
                    ");
                    $stmt->execute([
                        $data['group_id'],
                        $data['master_id'],
                        $data['member_number']
                    ]);
                    
                    if ($pdo->lastInsertId() > 0 || $stmt->rowCount() > 0) {
                        $migratedCount++;
                    }
                }
                $migrationLog[] = "Migrated {$migratedCount} member assignments";
            }
            
            // Step 3: Handle win data migration
            $winColumns = ['has_won_month', 'won_amount'];
            $hasWinData = false;
            foreach ($winColumns as $col) {
                $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE '{$col}'");
                if ($stmt->rowCount() > 0) {
                    $hasWinData = true;
                    break;
                }
            }
            
            if ($hasWinData) {
                $migrationLog[] = "Migrating win data to monthly_bids...";
                
                $stmt = $pdo->query("
                    SELECT m.id, m.member_name, m.has_won_month, m.won_amount, gm.group_id
                    FROM members m
                    LEFT JOIN group_members gm ON m.id = gm.member_id
                    WHERE m.has_won_month IS NOT NULL AND m.has_won_month > 0
                ");
                $winData = $stmt->fetchAll();
                
                $winMigratedCount = 0;
                foreach ($winData as $data) {
                    if ($data['group_id']) {
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO monthly_bids 
                            (group_id, month_number, taken_by_member_id, net_payable)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $data['group_id'],
                            $data['has_won_month'],
                            $data['id'],
                            $data['won_amount'] ?: 0
                        ]);
                        
                        if ($pdo->lastInsertId() > 0) {
                            $winMigratedCount++;
                        }
                    }
                }
                $migrationLog[] = "Migrated {$winMigratedCount} win records";
            }
            
            // Step 4: Remove duplicate members (keep unique names)
            $migrationLog[] = "Cleaning duplicate members...";
            $stmt = $pdo->query("
                SELECT member_name, MIN(id) as keep_id, COUNT(*) as count
                FROM members
                GROUP BY member_name
                HAVING COUNT(*) > 1
            ");
            $duplicates = $stmt->fetchAll();
            
            $deletedCount = 0;
            foreach ($duplicates as $dup) {
                $stmt = $pdo->prepare("DELETE FROM members WHERE member_name = ? AND id != ?");
                $stmt->execute([$dup['member_name'], $dup['keep_id']]);
                $deletedCount += $stmt->rowCount();
            }
            $migrationLog[] = "Removed {$deletedCount} duplicate member records";
            
            // Step 5: Remove old columns from members table
            $columnsToRemove = ['group_id', 'member_number', 'has_won_month', 'won_amount', 'win_month', 'win_amount'];
            $removedColumns = [];
            
            foreach ($columnsToRemove as $column) {
                $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE '{$column}'");
                if ($stmt->rowCount() > 0) {
                    try {
                        // First, disable foreign key checks
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                        // Drop the column
                        $pdo->exec("ALTER TABLE members DROP COLUMN {$column}");
                        $removedColumns[] = $column;

                        // Re-enable foreign key checks
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                        $migrationLog[] = "✅ Removed column: {$column}";
                    } catch (PDOException $e) {
                        $migrationLog[] = "⚠️ Could not remove column {$column}: " . $e->getMessage();
                        $hasErrors = true;

                        // Re-enable foreign key checks even if error
                        try {
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        } catch (Exception $ex) {
                            // Ignore
                        }
                    }
                } else {
                    $migrationLog[] = "ℹ️ Column {$column} already removed";
                }
            }
            
            if (!empty($removedColumns)) {
                $migrationLog[] = "✅ Successfully removed columns: " . implode(', ', $removedColumns);
            } else {
                $migrationLog[] = "ℹ️ All old columns already removed or migration complete";
            }

            if (!$hasErrors) {
                $migrationLog[] = "⚠️ IMPORTANT: Code files need to be updated to use new structure!";
                $migrationLog[] = "📝 Run the code update tool next to fix all functions.";

                $message = "✅ Database migration successful!<br>" . implode('<br>', $migrationLog);
                $messageType = "success";
            } else {
                $message = "⚠️ Migration completed with some warnings:<br>" . implode('<br>', $migrationLog);
                $messageType = "warning";
            }
        }
        
        if ($action === 'check_only') {
            $message = "Migration status checked successfully!";
            $messageType = "info";
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

$migrationStatus = getMigrationStatus($pdo);

// Check current data counts
$memberCount = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
$groupCount = $pdo->query("SELECT COUNT(*) FROM bc_groups")->fetchColumn();
$assignmentCount = 0;
if ($migrationStatus['group_members_table_exists']) {
    $assignmentCount = $pdo->query("SELECT COUNT(*) FROM group_members")->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Migration - BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-good { color: #198754; }
        .status-warning { color: #fd7e14; }
        .status-error { color: #dc3545; }
        .migration-ready { border: 2px solid #198754; background: #f0fff4; }
        .migration-needed { border: 2px solid #fd7e14; background: #fff8f0; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3">
                <h5><i class="fas fa-database"></i> Migration Tool</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
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
                <h2><i class="fas fa-rocket"></i> Complete Database Migration</h2>
                <p class="text-muted">One-click migration to fix all database structure issues</p>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'info' ? 'info' : 'success'); ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Current Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-chart-line"></i> Current Database Status</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-primary"><?php echo $memberCount; ?></h3>
                                    <p>Total Members</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-info"><?php echo $groupCount; ?></h3>
                                    <p>Total Groups</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h3 class="text-success"><?php echo $assignmentCount; ?></h3>
                                    <p>Group Assignments</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <?php if ($migrationStatus['data_migrated'] && !$migrationStatus['members_has_group_columns']): ?>
                                        <h3 class="status-good"><i class="fas fa-check-circle"></i></h3>
                                        <p>Migration Complete</p>
                                    <?php else: ?>
                                        <h3 class="status-warning"><i class="fas fa-exclamation-triangle"></i></h3>
                                        <p>Migration Needed</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Migration Status Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-list-check"></i> Migration Checklist</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                group_members table exists
                                <?php if ($migrationStatus['group_members_table_exists']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-times"></i> No</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Data migrated to group_members
                                <?php if ($migrationStatus['data_migrated']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-times"></i> No</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Old columns removed from members
                                <?php if (!$migrationStatus['members_has_group_columns'] && !$migrationStatus['members_has_win_columns']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-times"></i> No</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Migration Action -->
                <?php 
                $needsMigration = !$migrationStatus['data_migrated'] || 
                                 $migrationStatus['members_has_group_columns'] || 
                                 $migrationStatus['members_has_win_columns'];
                ?>
                
                <div class="<?php echo $needsMigration ? 'migration-needed' : 'migration-ready'; ?> rounded p-4 mb-4">
                    <?php if ($needsMigration): ?>
                        <h3><i class="fas fa-rocket"></i> Ready to Migrate</h3>
                        <p>Click the button below to perform the complete migration. This will:</p>
                        <ul>
                            <li>✅ Create proper group_members table</li>
                            <li>✅ Migrate all member-group assignments</li>
                            <li>✅ Move win data to monthly_bids table</li>
                            <li>✅ Remove duplicate member records</li>
                            <li>✅ Clean up old columns from members table</li>
                            <li>✅ Handle multiple runs safely (won't duplicate data)</li>
                        </ul>
                        
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="action" value="complete_migration">
                            <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Perform complete migration? This will restructure your database.')">
                                <i class="fas fa-rocket"></i> Run Complete Migration
                            </button>
                        </form>
                    <?php else: ?>
                        <h3><i class="fas fa-check-circle"></i> Migration Complete!</h3>
                        <p class="text-success">Your database structure is properly normalized. All systems should work correctly now.</p>
                        
                        <div class="mt-3">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                            </a>
                            <a href="assign_members.php" class="btn btn-info">
                                <i class="fas fa-link"></i> Manage Assignments
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Affected Pages -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-file-code"></i> Pages That Will Be Fixed</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($migrationStatus['pages_affected'] as $page => $description): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-file-alt text-primary me-2"></i>
                                        <div>
                                            <strong><?php echo basename($page); ?></strong><br>
                                            <small class="text-muted"><?php echo $description; ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
