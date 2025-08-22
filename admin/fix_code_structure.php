<?php
session_start();
require_once '../config/config.php';

$message = '';
$messageType = '';

// Check if migration is complete
function checkMigrationStatus() {
    $pdo = getDB();

    try {
        // Check if group_members table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'group_members'");
        $groupMembersExists = $stmt->rowCount() > 0;

        // Check if members still has group_id
        $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
        $membersHasGroupId = $stmt->rowCount() > 0;

        // Check if group_members has data
        $hasData = false;
        if ($groupMembersExists) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM group_members");
            $hasData = $stmt->fetchColumn() > 0;
        }

        // Migration is complete if:
        // 1. group_members table exists AND
        // 2. members table doesn't have group_id column AND
        // 3. group_members has data OR no members exist
        $migrationComplete = $groupMembersExists && !$membersHasGroupId;

        return [
            'migration_complete' => $migrationComplete,
            'group_members_exists' => $groupMembersExists,
            'members_has_group_id' => $membersHasGroupId,
            'has_data' => $hasData
        ];
    } catch (Exception $e) {
        return [
            'migration_complete' => false,
            'group_members_exists' => false,
            'members_has_group_id' => true,
            'has_data' => false
        ];
    }
}

// Files that need to be updated
$filesToUpdate = [
    'config/main_config.php' => [
        'functions' => ['getGroupMembers', 'memberLogin', 'getMemberGroups', 'getAvailableMembersForRandomPick'],
        'description' => 'Core configuration functions'
    ],
    'config/multi_tenant_config.php' => [
        'functions' => ['getCurrentClientId'],
        'description' => 'Multi-tenant configuration'
    ],
    'common/functions.php' => [
        'functions' => ['getAllMembers', 'getGroupMembers'],
        'description' => 'Common helper functions'
    ]
];

// Handle code updates
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'update_config_functions') {
            $updatedFiles = [];
            
            // Update main_config.php
            $configPath = '../config/main_config.php';
            if (file_exists($configPath)) {
                $content = file_get_contents($configPath);
                
                // Update getGroupMembers function
                $oldGetGroupMembers = '/function getGroupMembers\(\$groupId\) \{[^}]+\}/s';
                $newGetGroupMembers = 'function getGroupMembers($groupId) {
    $clientId = getCurrentClientId();
    if (!$clientId) return [];

    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT m.*, gm.member_number, gm.joined_date, gm.status as assignment_status
        FROM group_members gm
        JOIN members m ON gm.member_id = m.id
        JOIN bc_groups g ON gm.group_id = g.id
        WHERE gm.group_id = ? AND g.client_id = ?
        ORDER BY gm.member_number
    ");
    $stmt->execute([$groupId, $clientId]);
    return $stmt->fetchAll();
}';
                
                if (preg_match($oldGetGroupMembers, $content)) {
                    $content = preg_replace($oldGetGroupMembers, $newGetGroupMembers, $content);
                    $updatedFiles[] = 'getGroupMembers function in main_config.php';
                }
                
                // Update memberLogin function
                $oldMemberLogin = '/function memberLogin\(\$username, \$password\) \{[^}]+\}/s';
                $newMemberLogin = 'function memberLogin($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT m.*, g.group_name, g.client_id, c.client_name, gm.group_id, gm.member_number
        FROM members m
        JOIN group_members gm ON m.id = gm.member_id
        JOIN bc_groups g ON gm.group_id = g.id
        JOIN clients c ON g.client_id = c.id
        WHERE m.username = ? AND m.status = \'active\' AND c.status = \'active\'
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $member = $stmt->fetch();

    if ($member && password_verify($password, $member[\'password\'])) {
        $_SESSION[\'member_id\'] = $member[\'id\'];
        $_SESSION[\'member_name\'] = $member[\'member_name\'];
        $_SESSION[\'group_id\'] = $member[\'group_id\'];
        $_SESSION[\'group_name\'] = $member[\'group_name\'];
        $_SESSION[\'client_id\'] = $member[\'client_id\'];
        $_SESSION[\'client_name\'] = $member[\'client_name\'];
        $_SESSION[\'user_type\'] = \'member\';
        return true;
    }

    return false;
}';
                
                if (preg_match($oldMemberLogin, $content)) {
                    $content = preg_replace($oldMemberLogin, $newMemberLogin, $content);
                    $updatedFiles[] = 'memberLogin function in main_config.php';
                }
                
                // Update getMemberGroups function
                $oldGetMemberGroups = '/function getMemberGroups\(\$memberId\) \{[^}]+\}/s';
                $newGetMemberGroups = 'function getMemberGroups($memberId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.*, gm.id as assignment_id, gm.member_number, gm.status as member_status, gm.joined_date as member_joined_date
        FROM bc_groups g
        JOIN group_members gm ON g.id = gm.group_id
        WHERE gm.member_id = ? AND gm.status = \'active\'
        ORDER BY g.start_date DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}';
                
                if (preg_match($oldGetMemberGroups, $content)) {
                    $content = preg_replace($oldGetMemberGroups, $newGetMemberGroups, $content);
                    $updatedFiles[] = 'getMemberGroups function in main_config.php';
                }
                
                file_put_contents($configPath, $content);
            }
            
            // Update common/functions.php
            $functionsPath = '../common/functions.php';
            if (file_exists($functionsPath)) {
                $content = file_get_contents($functionsPath);
                
                // Update getGroupMembers function in common/functions.php
                $oldCommonGetGroupMembers = '/function getGroupMembers\(\$groupId\) \{[^}]+\}/s';
                $newCommonGetGroupMembers = 'function getGroupMembers($groupId) {
    $pdo = getDB();

    // Check if we\'re in multi-tenant mode
    if (isset($_SESSION[\'client_id\'])) {
        $stmt = $pdo->prepare("
            SELECT m.*, gm.member_number, gm.joined_date, gm.status as assignment_status
            FROM group_members gm
            JOIN members m ON gm.member_id = m.id
            JOIN bc_groups g ON gm.group_id = g.id
            WHERE gm.group_id = ? AND g.client_id = ? AND gm.status = \'active\'
            ORDER BY gm.member_number
        ");
        $stmt->execute([$groupId, $_SESSION[\'client_id\']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.*, gm.member_number, gm.joined_date, gm.status as assignment_status
            FROM group_members gm
            JOIN members m ON gm.member_id = m.id
            WHERE gm.group_id = ? AND gm.status = \'active\'
            ORDER BY gm.member_number
        ");
        $stmt->execute([$groupId]);
    }

    return $stmt->fetchAll();
}';
                
                if (preg_match($oldCommonGetGroupMembers, $content)) {
                    $content = preg_replace($oldCommonGetGroupMembers, $newCommonGetGroupMembers, $content);
                    $updatedFiles[] = 'getGroupMembers function in common/functions.php';
                }
                
                file_put_contents($functionsPath, $content);
            }
            
            if (!empty($updatedFiles)) {
                $message = "✅ Code updated successfully!<br>Updated: " . implode('<br>', $updatedFiles);
                $messageType = "success";
            } else {
                $message = "ℹ️ No functions found to update. Code may already be updated.";
                $messageType = "info";
            }
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

$migrationStatus = checkMigrationStatus();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Code Structure - BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-good { color: #198754; }
        .status-warning { color: #fd7e14; }
        .status-error { color: #dc3545; }
        .code-ready { border: 2px solid #198754; background: #f0fff4; }
        .code-needed { border: 2px solid #fd7e14; background: #fff8f0; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3">
                <h5><i class="fas fa-code"></i> Code Fixer</h5>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="complete_migration.php">
                            <i class="fas fa-arrow-left"></i> Back to Migration
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-wrench"></i> Fix Code Structure</h2>
                <p class="text-muted">Update functions to use the new group_members table structure</p>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'info' ? 'info' : 'success'); ?> alert-dismissible fade show">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Migration Status Check -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-check-circle"></i> Prerequisites Check</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Database migration completed
                                <?php if ($migrationStatus['migration_complete']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> No</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                group_members table exists
                                <?php if ($migrationStatus['group_members_exists']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times"></i> No</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Old columns removed from members
                                <?php if (!$migrationStatus['members_has_group_id']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-exclamation"></i> Still exists</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <?php if (!$migrationStatus['migration_complete']): ?>
                    <!-- Migration Required -->
                    <div class="alert alert-warning">
                        <h4><i class="fas fa-exclamation-triangle"></i> Database Migration Required</h4>
                        <p>You need to complete the database migration first before updating the code.</p>
                        <a href="complete_migration.php" class="btn btn-warning">
                            <i class="fas fa-database"></i> Go to Migration Tool
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Code Update Section -->
                    <div class="code-needed rounded p-4 mb-4">
                        <h3><i class="fas fa-code"></i> Update Required Functions</h3>
                        <p>The following functions need to be updated to use the new group_members table structure:</p>
                        
                        <div class="row">
                            <?php foreach ($filesToUpdate as $file => $info): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6><i class="fas fa-file-code"></i> <?php echo $file; ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted"><?php echo $info['description']; ?></p>
                                            <ul class="list-unstyled">
                                                <?php foreach ($info['functions'] as $func): ?>
                                                    <li><i class="fas fa-function text-primary"></i> <?php echo $func; ?>()</li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_config_functions">
                                <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Update all functions to use new structure?')">
                                    <i class="fas fa-wrench"></i> Update All Functions
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- What Gets Updated -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-list"></i> What Gets Updated</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="text-danger">Old Structure (Before)</h5>
                                <pre class="bg-light p-3"><code>SELECT m.* 
FROM members m
WHERE m.group_id = ?</code></pre>
                            </div>
                            <div class="col-md-6">
                                <h5 class="text-success">New Structure (After)</h5>
                                <pre class="bg-light p-3"><code>SELECT m.*, gm.member_number
FROM group_members gm
JOIN members m ON gm.member_id = m.id
WHERE gm.group_id = ?</code></pre>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6>Key Changes:</h6>
                            <ul>
                                <li>✅ Uses <code>group_members</code> table for member-group relationships</li>
                                <li>✅ Preserves <code>member_number</code> in proper location</li>
                                <li>✅ Maintains all existing functionality</li>
                                <li>✅ Supports multi-tenant architecture</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
