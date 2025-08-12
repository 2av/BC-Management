<?php
require_once 'config.php';
requireAdminLogin();

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) {
    redirect('index.php');
}

$group = getGroupById($groupId);
if (!$group) {
    setMessage('Group not found.', 'error');
    redirect('index.php');
}

$members = getGroupMembers($groupId);
$monthlyBids = getMonthlyBids($groupId);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_group'])) {
        // Update group basic info
        $groupName = trim($_POST['group_name'] ?? '');
        $startDate = $_POST['start_date'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (empty($groupName)) {
            $error = 'Group name is required.';
        } elseif (empty($startDate)) {
            $error = 'Start date is required.';
        } else {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("UPDATE bc_groups SET group_name = ?, start_date = ?, status = ? WHERE id = ?");
                $stmt->execute([$groupName, $startDate, $status, $groupId]);
                
                $success = 'Group information updated successfully!';
                $group = getGroupById($groupId); // Refresh data
                
            } catch (Exception $e) {
                $error = 'Failed to update group information.';
            }
        }
    }
    
    if (isset($_POST['add_member'])) {
        // Add new member
        $memberName = trim($_POST['new_member_name'] ?? '');
        
        if (empty($memberName)) {
            $error = 'Member name is required.';
        } else {
            try {
                $pdo = getDB();
                
                // Get next member number
                $stmt = $pdo->prepare("SELECT MAX(member_number) as max_num FROM members WHERE group_id = ?");
                $stmt->execute([$groupId]);
                $maxNum = $stmt->fetchColumn() ?: 0;
                $nextMemberNumber = $maxNum + 1;
                
                // Generate username
                $cleanName = strtolower(str_replace(' ', '', $memberName));
                $username = $cleanName . $nextMemberNumber;
                $password = password_hash('member123', PASSWORD_DEFAULT);
                
                // Insert new member
                $stmt = $pdo->prepare("INSERT INTO members (group_id, member_name, member_number, username, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$groupId, $memberName, $nextMemberNumber, $username, $password]);
                
                // Update group total members and monthly collection
                $newTotalMembers = count($members) + 1;
                $newTotalCollection = $newTotalMembers * $group['monthly_contribution'];
                
                $stmt = $pdo->prepare("UPDATE bc_groups SET total_members = ?, total_monthly_collection = ? WHERE id = ?");
                $stmt->execute([$newTotalMembers, $newTotalCollection, $groupId]);
                
                $success = "Member '{$memberName}' added successfully! Username: {$username}, Password: member123";
                $group = getGroupById($groupId); // Refresh data
                $members = getGroupMembers($groupId); // Refresh members
                
            } catch (Exception $e) {
                $error = 'Failed to add member. Username might already exist.';
            }
        }
    }
    
    if (isset($_POST['update_member'])) {
        // Update member info
        $memberId = (int)($_POST['member_id'] ?? 0);
        $memberName = trim($_POST['member_name'] ?? '');
        
        if (!$memberId || empty($memberName)) {
            $error = 'Member ID and name are required.';
        } else {
            try {
                $pdo = getDB();
                $stmt = $pdo->prepare("UPDATE members SET member_name = ? WHERE id = ? AND group_id = ?");
                $stmt->execute([$memberName, $memberId, $groupId]);
                
                $success = 'Member information updated successfully!';
                $members = getGroupMembers($groupId); // Refresh members
                
            } catch (Exception $e) {
                $error = 'Failed to update member information.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Group - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins"></i> <?= APP_NAME ?>
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Edit Group</h1>
            <a href="view_group.php?id=<?= $groupId ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Group
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Group Information -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-edit"></i> Group Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="group_name" class="form-label">Group Name *</label>
                                <input type="text" class="form-control" id="group_name" name="group_name" 
                                       value="<?= htmlspecialchars($group['group_name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                       value="<?= $group['start_date'] ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="active" <?= $group['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="completed" <?= $group['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Total Members</label>
                                    <input type="text" class="form-control" value="<?= $group['total_members'] ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Monthly Contribution</label>
                                    <input type="text" class="form-control" value="<?= formatCurrency($group['monthly_contribution']) ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Total members and monthly contribution cannot be changed after group creation.
                                </small>
                            </div>
                            
                            <div class="d-grid mt-3">
                                <button type="submit" name="update_group" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Group Information
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Add New Member -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user-plus"></i> Add New Member
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($monthlyBids) > 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Warning:</strong> This group already has bid entries. Adding new members may affect calculations.
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="new_member_name" class="form-label">Member Name *</label>
                                <input type="text" class="form-control" id="new_member_name" name="new_member_name" 
                                       placeholder="Enter new member name" required>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <strong>Auto-generated credentials:</strong><br>
                                    Username: [name][number] (e.g., john5)<br>
                                    Password: member123
                                </small>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="add_member" class="btn btn-success">
                                    <i class="fas fa-user-plus"></i> Add Member
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Current Members -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-users"></i> Current Members (<?= count($members) ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Member Name</th>
                                        <th>Username</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td><?= $member['member_number'] ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control" name="member_name" 
                                                               value="<?= htmlspecialchars($member['member_name']) ?>" required>
                                                        <button type="submit" name="update_member" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td>
                                                <code><?= htmlspecialchars($member['username'] ?: 'Not Set') ?></code>
                                            </td>
                                            <td><?= htmlspecialchars($member['phone'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($member['email'] ?: '-') ?></td>
                                            <td>
                                                <a href="manage_members.php?group_id=<?= $groupId ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-cog"></i> Manage
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <a href="manage_members.php?group_id=<?= $groupId ?>" class="btn btn-primary">
                                <i class="fas fa-users-cog"></i> Manage All Member Credentials
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Group Statistics -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar"></i> Group Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary"><?= count($members) ?></h4>
                                    <small class="text-muted">Total Members</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success"><?= formatCurrency($group['total_monthly_collection']) ?></h4>
                                    <small class="text-muted">Monthly Collection</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info"><?= count($monthlyBids) ?></h4>
                                    <small class="text-muted">Completed Months</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-warning"><?= count($members) - count($monthlyBids) ?></h4>
                                    <small class="text-muted">Remaining Months</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= count($members) > 0 ? (count($monthlyBids) / count($members)) * 100 : 0 ?>%"></div>
                            </div>
                            <small class="text-muted">Group Progress: <?= count($monthlyBids) ?> / <?= count($members) ?> months completed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
