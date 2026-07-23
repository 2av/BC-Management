<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$pdo = getDB();

// Test all the updated queries
$tests = [];

try {
    // Test 1: Count total members (from admin dashboard)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT member_id) FROM group_members WHERE status = 'active'");
    $totalMembers = $stmt->fetchColumn();
    $tests['Total Members Count'] = ['result' => $totalMembers, 'status' => 'success'];
} catch (Exception $e) {
    $tests['Total Members Count'] = ['result' => $e->getMessage(), 'status' => 'error'];
}

try {
    // Test 2: Get group members for a specific group
    $stmt = $pdo->query("SELECT id FROM bc_groups LIMIT 1");
    $groupId = $stmt->fetchColumn();
    
    if ($groupId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND status = 'active'");
        $stmt->execute([$groupId]);
        $groupMemberCount = $stmt->fetchColumn();
        $tests['Group Member Count'] = ['result' => "Group $groupId has $groupMemberCount members", 'status' => 'success'];
    } else {
        $tests['Group Member Count'] = ['result' => 'No groups found', 'status' => 'warning'];
    }
} catch (Exception $e) {
    $tests['Group Member Count'] = ['result' => $e->getMessage(), 'status' => 'error'];
}

try {
    // Test 3: Get consolidated members (from admin/members.php)
    $stmt = $pdo->query("
        SELECT
            m.member_name,
            COUNT(DISTINCT gm.group_id) as total_groups,
            GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as group_names
        FROM members m
        JOIN group_members gm ON m.id = gm.member_id AND gm.status = 'active'
        JOIN bc_groups g ON gm.group_id = g.id
        WHERE m.member_name IS NOT NULL AND m.member_name != ''
        GROUP BY m.member_name
        ORDER BY m.member_name
        LIMIT 5
    ");
    $consolidatedMembers = $stmt->fetchAll();
    $tests['Consolidated Members Query'] = ['result' => count($consolidatedMembers) . ' members found', 'status' => 'success'];
} catch (Exception $e) {
    $tests['Consolidated Members Query'] = ['result' => $e->getMessage(), 'status' => 'error'];
}

try {
    // Test 4: Get member groups (from member dashboard)
    $stmt = $pdo->query("SELECT id FROM members LIMIT 1");
    $memberId = $stmt->fetchColumn();
    
    if ($memberId) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT g.*, gm.member_id, gm.id as assignment_id, gm.member_number, gm.status as member_status, gm.joined_date as member_joined_date
            FROM bc_groups g
            JOIN group_members gm ON g.id = gm.group_id
            WHERE gm.member_id = ? AND gm.status = 'active'
            ORDER BY g.start_date DESC
        ");
        $stmt->execute([$memberId]);
        $memberGroups = $stmt->fetchAll();
        $tests['Member Groups Query'] = ['result' => "Member $memberId belongs to " . count($memberGroups) . ' groups', 'status' => 'success'];
    } else {
        $tests['Member Groups Query'] = ['result' => 'No members found', 'status' => 'warning'];
    }
} catch (Exception $e) {
    $tests['Member Groups Query'] = ['result' => $e->getMessage(), 'status' => 'error'];
}

try {
    // Test 5: Check if group_id column still exists
    $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
    $hasGroupId = $stmt->rowCount() > 0;
    $tests['Group ID Column Check'] = [
        'result' => $hasGroupId ? 'group_id column still exists' : 'group_id column successfully removed', 
        'status' => $hasGroupId ? 'warning' : 'success'
    ];
} catch (Exception $e) {
    $tests['Group ID Column Check'] = ['result' => $e->getMessage(), 'status' => 'error'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Test - BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-check-circle"></i> Dashboard Functionality Test</h4>
                    </div>
                    <div class="card-body">
                        
                        <p class="mb-4">This page tests all the updated dashboard queries to ensure they work correctly after the migration.</p>

                        <div class="row">
                            <?php foreach ($tests as $testName => $test): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><?= htmlspecialchars($testName) ?></h6>
                                            <?php if ($test['status'] === 'success'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check"></i> Pass</span>
                                            <?php elseif ($test['status'] === 'warning'): ?>
                                                <span class="badge bg-warning"><i class="fas fa-exclamation"></i> Warning</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><i class="fas fa-times"></i> Fail</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-0"><?= htmlspecialchars($test['result']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4">
                            <h5>Quick Actions</h5>
                            <div class="btn-group" role="group">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="fas fa-tachometer-alt"></i> View Admin Dashboard
                                </a>
                                <a href="members.php" class="btn btn-info">
                                    <i class="fas fa-users"></i> View Members
                                </a>
                                <a href="run_migration.php" class="btn btn-warning">
                                    <i class="fas fa-database"></i> Migration Tool
                                </a>
                            </div>
                        </div>

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
