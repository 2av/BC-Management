<?php
session_start();
require_once '../config/config.php';

 

$pdo = getDB();
$message = '';
$messageType = '';

// Handle form submission
if ($_POST && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'assign_member') {
            $memberId = (int)$_POST['member_id'];
            $groupId = (int)$_POST['group_id'];
            $memberNumber = (int)$_POST['member_number'];
            
            // Check if assignment already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Member is already assigned to this group!");
            }
            
            // Check if member number is already taken in this group
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND member_number = ?");
            $stmt->execute([$groupId, $memberNumber]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Member number {$memberNumber} is already taken in this group!");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO group_members (group_id, member_id, member_number, joined_date, status) 
                VALUES (?, ?, ?, CURDATE(), 'active')
            ");
            $stmt->execute([$groupId, $memberId, $memberNumber]);
            
            $message = "Member assigned to group successfully!";
            $messageType = "success";
        }
        
        if ($action === 'remove_assignment') {
            $assignmentId = (int)$_POST['assignment_id'];
            $stmt = $pdo->prepare("DELETE FROM group_members WHERE id = ?");
            $stmt->execute([$assignmentId]);
            
            $message = "Member removed from group successfully!";
            $messageType = "success";
        }
        
        if ($action === 'migrate_data') {
            // Create group_members table if it doesn't exist (without foreign keys for now)
            $pdo->exec("CREATE TABLE IF NOT EXISTS group_members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                group_id INT NOT NULL,
                member_id INT NOT NULL,
                member_number INT,
                joined_date DATE DEFAULT (CURRENT_DATE),
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_group_id (group_id),
                INDEX idx_member_id (member_id),
                UNIQUE KEY unique_group_member (group_id, member_id)
            )");
            
            // First, let's see what data we have
            $stmt = $pdo->query("
                SELECT id, member_name, group_id, member_number, username,
                       ROW_NUMBER() OVER (PARTITION BY member_name ORDER BY id) as row_num
                FROM members
                WHERE group_id IS NOT NULL
                ORDER BY member_name, id
            ");
            $allMemberData = $stmt->fetchAll();

            $migratedCount = 0;
            $uniqueMembers = [];

            // Process each member record
            foreach ($allMemberData as $data) {
                $memberName = $data['member_name'];
                $groupId = $data['group_id'];
                $memberNumber = $data['member_number'];
                $originalId = $data['id'];

                // For each unique member name, use the first occurrence as the master record
                if (!isset($uniqueMembers[$memberName])) {
                    $uniqueMembers[$memberName] = $originalId;
                }

                $masterId = $uniqueMembers[$memberName];

                // Insert into group_members using the master member ID
                try {
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO group_members (group_id, member_id, member_number, status, joined_date)
                        VALUES (?, ?, ?, 'active', CURDATE())
                    ");
                    $stmt->execute([$groupId, $masterId, $memberNumber]);

                    if ($pdo->lastInsertId() > 0) {
                        $migratedCount++;
                    }
                } catch (PDOException $e) {
                    // Skip duplicates or errors
                    continue;
                }
            }
            
            $message = "Data migration completed! {$migratedCount} assignments created.";
            $messageType = "success";
        }
        
        if ($action === 'clean_members_table') {
            // First, let's see what we're working with
            $stmt = $pdo->query("SELECT COUNT(*) as total, COUNT(DISTINCT member_name) as unique_names FROM members");
            $counts = $stmt->fetch();

            // Remove duplicate member records (keep the first occurrence of each name)
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

            // Remove group_id and member_number columns if they exist
            try {
                $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
                if ($stmt->rowCount() > 0) {
                    $pdo->exec("ALTER TABLE members DROP COLUMN group_id");
                }

                $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'member_number'");
                if ($stmt->rowCount() > 0) {
                    $pdo->exec("ALTER TABLE members DROP COLUMN member_number");
                }
            } catch (PDOException $e) {
                // Columns might not exist or might have constraints
            }

            $message = "Members table cleaned successfully! Removed {$deletedCount} duplicate records.";
            $messageType = "success";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all members (unique)
$stmt = $pdo->query("
    SELECT DISTINCT id, member_name, username 
    FROM members 
    ORDER BY member_name
");
$members = $stmt->fetchAll();

// Get all groups
$stmt = $pdo->query("SELECT * FROM bc_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

// Get current assignments
$stmt = $pdo->query("
    SELECT gm.*, m.member_name, g.group_name 
    FROM group_members gm
    JOIN members m ON gm.member_id = m.id
    JOIN bc_groups g ON gm.group_id = g.id
    ORDER BY g.group_name, gm.member_number
");
$assignments = $stmt->fetchAll();

// Check if migration is needed
$stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'group_id'");
$needsMigration = $stmt->rowCount() > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Members - BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .migration-zone { border: 2px solid #fd7e14; background: #fff8f0; }
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
                        <a class="nav-link text-white" href="add_groups.php">
                            <i class="fas fa-plus"></i> Add Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="add_members.php">
                            <i class="fas fa-user-plus"></i> Add Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="assign_members.php">
                            <i class="fas fa-link"></i> Assign Members
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-link"></i> Assign Members to Groups</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($needsMigration): ?>
                <!-- Migration Section -->
                <div class="migration-zone rounded p-4 mb-4">
                    <h3><i class="fas fa-exclamation-triangle"></i> Data Migration Required</h3>
                    <p>Your members table contains group assignments. Let's migrate this to the proper structure:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="migrate_data">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('Migrate data from members table to group_members table?')">
                                    <i class="fas fa-database"></i> 1. Migrate Data
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="clean_members_table">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Clean members table (remove duplicates and group columns)?')">
                                    <i class="fas fa-broom"></i> 2. Clean Members Table
                                </button>
                            </form>
                        </div>
                    </div>
                    <p class="text-muted"><small>Run step 1 first, then step 2 to complete the migration.</small></p>
                </div>
                <?php endif; ?>

                <!-- Assign Member Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4><i class="fas fa-plus-circle"></i> Assign Member to Group</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="assign_member">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="member_id" class="form-label">Select Member *</label>
                                        <select class="form-control" id="member_id" name="member_id" required>
                                            <option value="">Choose Member...</option>
                                            <?php foreach ($members as $member): ?>
                                                <option value="<?php echo $member['id']; ?>">
                                                    <?php echo htmlspecialchars($member['member_name']); ?> (<?php echo $member['username']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="group_id" class="form-label">Select Group *</label>
                                        <select class="form-control" id="group_id" name="group_id" required>
                                            <option value="">Choose Group...</option>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?php echo $group['id']; ?>">
                                                    <?php echo htmlspecialchars($group['group_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="member_number" class="form-label">Member Number *</label>
                                        <input type="number" class="form-control" id="member_number" name="member_number" required
                                               min="1" max="50" placeholder="1-50">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-link"></i> Assign Member
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Current Assignments -->
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-list"></i> Current Assignments (<?php echo count($assignments); ?>)</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No member assignments found. Assign members to groups above!
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Member Name</th>
                                            <th>Group Name</th>
                                            <th>Member #</th>
                                            <th>Joined Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $currentGroup = '';
                                        foreach ($assignments as $assignment): 
                                            if ($currentGroup !== $assignment['group_name']) {
                                                $currentGroup = $assignment['group_name'];
                                                echo "<tr class='table-secondary'><td colspan='6'><strong>{$currentGroup}</strong></td></tr>";
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($assignment['member_name']); ?></td>
                                                <td><?php echo htmlspecialchars($assignment['group_name']); ?></td>
                                                <td><span class="badge bg-primary"><?php echo $assignment['member_number']; ?></span></td>
                                                <td><?php echo date('d-m-Y', strtotime($assignment['joined_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $assignment['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($assignment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this assignment?')">
                                                        <input type="hidden" name="action" value="remove_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-unlink"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
