<?php
require_once '../config/config.php';

$message = '';
$messageType = 'info';

if ($_POST['action'] ?? '' === 'force_remove_columns') {
    try {
        $pdo = getDB();
        $removedColumns = [];
        $errors = [];
        
        // Columns to remove from members table
        $columnsToRemove = ['group_id', 'member_number'];
        
        foreach ($columnsToRemove as $column) {
            // Check if column exists
            $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE '{$column}'");
            if ($stmt->rowCount() > 0) {
                try {
                    // Method 1: Try direct drop
                    $pdo->exec("ALTER TABLE members DROP COLUMN {$column}");
                    $removedColumns[] = $column;
                } catch (PDOException $e1) {
                    try {
                        // Method 2: Disable foreign key checks and try again
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                        $pdo->exec("ALTER TABLE members DROP COLUMN {$column}");
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        $removedColumns[] = $column;
                    } catch (PDOException $e2) {
                        try {
                            // Method 3: Drop any foreign key constraints first
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                            
                            // Get foreign key constraints
                            $stmt = $pdo->query("
                                SELECT CONSTRAINT_NAME 
                                FROM information_schema.KEY_COLUMN_USAGE 
                                WHERE TABLE_SCHEMA = DATABASE() 
                                AND TABLE_NAME = 'members' 
                                AND COLUMN_NAME = '{$column}'
                                AND REFERENCED_TABLE_NAME IS NOT NULL
                            ");
                            $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            // Drop constraints
                            foreach ($constraints as $constraint) {
                                $pdo->exec("ALTER TABLE members DROP FOREIGN KEY {$constraint}");
                            }
                            
                            // Now drop the column
                            $pdo->exec("ALTER TABLE members DROP COLUMN {$column}");
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                            $removedColumns[] = $column;
                        } catch (PDOException $e3) {
                            $errors[] = "Could not remove {$column}: " . $e3->getMessage();
                            // Ensure foreign key checks are re-enabled
                            try {
                                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                            } catch (Exception $ex) {}
                        }
                    }
                }
            } else {
                $removedColumns[] = $column . " (already removed)";
            }
        }
        
        if (!empty($removedColumns)) {
            $message = "✅ Successfully processed columns: " . implode(', ', $removedColumns);
            $messageType = 'success';
        }
        
        if (!empty($errors)) {
            $message .= (!empty($message) ? "<br>" : "") . "❌ Errors: " . implode(', ', $errors);
            $messageType = 'warning';
        }
        
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Check current status
try {
    $pdo = getDB();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
    $hasGroupId = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'member_number'");
    $hasMemberNumber = $stmt->rowCount() > 0;
    
} catch (Exception $e) {
    $hasGroupId = true;
    $hasMemberNumber = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Force Remove Columns - BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2><i class="fas fa-tools"></i> Force Remove Old Columns</h2>
        <p class="text-muted">Forcefully remove old columns from members table</p>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'warning' ? 'warning' : 'success'); ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-info-circle"></i> Current Status</h4>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        members.group_id exists
                        <?php if ($hasGroupId): ?>
                            <span class="badge bg-warning"><i class="fas fa-exclamation"></i> Yes</span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> No</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        members.member_number exists
                        <?php if ($hasMemberNumber): ?>
                            <span class="badge bg-warning"><i class="fas fa-exclamation"></i> Yes</span>
                        <?php else: ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i> No</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>

        <?php if ($hasGroupId || $hasMemberNumber): ?>
            <div class="card">
                <div class="card-header bg-warning">
                    <h4><i class="fas fa-exclamation-triangle"></i> Action Required</h4>
                </div>
                <div class="card-body">
                    <p>The following old columns still exist and need to be removed:</p>
                    <ul>
                        <?php if ($hasGroupId): ?>
                            <li><code>group_id</code> - This column is no longer needed as we use group_members table</li>
                        <?php endif; ?>
                        <?php if ($hasMemberNumber): ?>
                            <li><code>member_number</code> - This column is now stored in group_members table</li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="alert alert-info">
                        <strong>Note:</strong> This will forcefully remove these columns. Make sure you have a backup!
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('Are you sure you want to forcefully remove these columns? This cannot be undone!')">
                        <input type="hidden" name="action" value="force_remove_columns">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-hammer"></i> Force Remove Old Columns
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <h4><i class="fas fa-check-circle"></i> All Done!</h4>
                <p>All old columns have been successfully removed. You can now proceed to the code fix tool.</p>
                <a href="fix_code_structure.php" class="btn btn-success">
                    <i class="fas fa-code"></i> Go to Code Fix Tool
                </a>
            </div>
        <?php endif; ?>
        
        <div class="mt-3">
            <a href="check_table_structure.php" class="btn btn-info">
                <i class="fas fa-table"></i> Check Table Structure
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
