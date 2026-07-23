<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$message = '';
$messageType = '';

if ($_POST['action'] ?? '' === 'run_migration') {
    try {
        $pdo = getDB();
        $results = [];

        // Step 1: Verify group_members table exists and has data
        $results[] = ['Step' => 'Checking group_members table'];

        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'group_members'");
        $hasGroupMembers = $stmt->fetchColumn() > 0;
        $results[] = ['Check' => $hasGroupMembers ? 'group_members table exists' : 'ERROR: group_members table missing'];

        if (!$hasGroupMembers) {
            throw new Exception('group_members table does not exist. Please run data migration first.');
        }

        $stmt = $pdo->query("SELECT COUNT(*) FROM group_members");
        $groupMemberCount = $stmt->fetchColumn();
        $results[] = ['Data Check' => "group_members has $groupMemberCount records"];

        if ($groupMemberCount === 0) {
            throw new Exception('group_members table is empty. Please migrate data first.');
        }

        // Step 2: Check if group_id column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
        $hasGroupId = $stmt->rowCount() > 0;

        if (!$hasGroupId) {
            $results[] = ['Status' => 'group_id column already removed - migration not needed'];
            $message = 'Migration not needed - group_id column already removed!';
            $messageType = 'info';
        } else {
            // Step 3: Remove foreign key constraint
            $results[] = ['Step' => 'Removing foreign key constraint'];
            try {
                $pdo->exec("ALTER TABLE members DROP FOREIGN KEY members_ibfk_1");
                $results[] = ['Success' => 'Foreign key constraint removed'];
            } catch (PDOException $e) {
                $results[] = ['Info' => 'Foreign key constraint not found or already removed: ' . $e->getMessage()];
            }

            // Step 4: Remove unique constraint
            $results[] = ['Step' => 'Removing unique constraint'];
            try {
                $pdo->exec("ALTER TABLE members DROP INDEX unique_member_per_group");
                $results[] = ['Success' => 'Unique constraint removed'];
            } catch (PDOException $e) {
                $results[] = ['Info' => 'Unique constraint not found or already removed: ' . $e->getMessage()];
            }

            // Step 5: Remove the group_id column
            $results[] = ['Step' => 'Removing group_id column'];
            $pdo->exec("ALTER TABLE members DROP COLUMN group_id");
            $results[] = ['Success' => 'group_id column removed successfully'];

            // Step 6: Verify removal
            $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
            $stillExists = $stmt->rowCount() > 0;
            $results[] = ['Verification' => $stillExists ? 'ERROR: group_id column still exists' : 'SUCCESS: group_id column removed'];

            $message = 'Migration completed successfully!';
            $messageType = 'success';
        }

    } catch (Exception $e) {
        $message = 'Migration failed: ' . $e->getMessage();
        $messageType = 'error';
        $results[] = ['Error' => $e->getMessage()];
    }
}

// Check current status
$pdo = getDB();

// Check if group_id column exists in members table
$stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
$hasGroupId = $stmt->rowCount() > 0;

// Check if group_members table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'group_members'");
$hasGroupMembers = $stmt->rowCount() > 0;

// Count records
$memberCount = 0;
$groupMemberCount = 0;

if ($hasGroupId) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM members WHERE group_id IS NOT NULL");
    $memberCount = $stmt->fetchColumn();
}

if ($hasGroupMembers) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM group_members");
    $groupMemberCount = $stmt->fetchColumn();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-database"></i> Database Migration - Remove group_id Column</h4>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Current Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between">
                                                <span>members.group_id exists:</span>
                                                <span class="badge bg-<?= $hasGroupId ? 'warning' : 'success' ?>">
                                                    <?= $hasGroupId ? 'YES' : 'NO' ?>
                                                </span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between">
                                                <span>group_members table exists:</span>
                                                <span class="badge bg-<?= $hasGroupMembers ? 'success' : 'danger' ?>">
                                                    <?= $hasGroupMembers ? 'YES' : 'NO' ?>
                                                </span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between">
                                                <span>Members with group_id:</span>
                                                <span class="badge bg-info"><?= $memberCount ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between">
                                                <span>Group member assignments:</span>
                                                <span class="badge bg-info"><?= $groupMemberCount ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Migration Action</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($hasGroupId && $hasGroupMembers && $groupMemberCount > 0): ?>
                                            <p class="text-success">✅ Ready to migrate! The group_members table exists and has data.</p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="run_migration">
                                                <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure you want to run the migration? This will remove the group_id column from the members table.')">
                                                    <i class="fas fa-play"></i> Run Migration
                                                </button>
                                            </form>
                                        <?php elseif (!$hasGroupMembers): ?>
                                            <p class="text-danger">❌ group_members table does not exist. Please run the data migration first.</p>
                                            <a href="assign_members.php" class="btn btn-warning">
                                                <i class="fas fa-arrow-right"></i> Go to Member Assignment
                                            </a>
                                        <?php elseif ($groupMemberCount === 0): ?>
                                            <p class="text-warning">⚠️ group_members table exists but has no data. Please migrate data first.</p>
                                            <a href="assign_members.php" class="btn btn-warning">
                                                <i class="fas fa-arrow-right"></i> Go to Member Assignment
                                            </a>
                                        <?php else: ?>
                                            <p class="text-success">✅ Migration already completed!</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($results) && !empty($results)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5>Migration Results</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($results as $index => $result): ?>
                                        <div class="mb-3">
                                            <h6>Result <?= $index + 1 ?>:</h6>
                                            <pre class="bg-light p-2"><?= htmlspecialchars(print_r($result, true)) ?></pre>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
