<?php
require_once 'config.php';
requireAdminLogin();

$memberId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($memberId <= 0) {
    redirect('admin_members.php');
}

$pdo = getDB();

// Get member details
$stmt = $pdo->prepare("
    SELECT m.*, g.group_name, g.total_members 
    FROM members m 
    JOIN bc_groups g ON m.group_id = g.id 
    WHERE m.id = ?
");
$stmt->execute([$memberId]);
$member = $stmt->fetch();

if (!$member) {
    setMessage('Member not found.', 'error');
    redirect('admin_members.php');
}

// Get all groups for selection
$stmt = $pdo->query("SELECT * FROM bc_groups WHERE status = 'active' ORDER BY group_name");
$groups = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberName = trim($_POST['member_name'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $memberNumber = (int)($_POST['member_number'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $resetPassword = isset($_POST['reset_password']);
    
    // Validation
    if (empty($memberName)) {
        $error = 'Member name is required.';
    } elseif ($groupId <= 0) {
        $error = 'Please select a valid group.';
    } elseif ($memberNumber <= 0) {
        $error = 'Member number must be greater than 0.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if member number already exists in the group (excluding current member)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE group_id = ? AND member_number = ? AND id != ?");
            $stmt->execute([$groupId, $memberNumber, $memberId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Member number {$memberNumber} already exists in this group.");
            }
            
            // Check if username already exists (excluding current member)
            if (!empty($username)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE username = ? AND id != ?");
                $stmt->execute([$username, $memberId]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Username '{$username}' already exists.");
                }
            }
            
            // Get group details for validation
            $stmt = $pdo->prepare("SELECT total_members FROM bc_groups WHERE id = ?");
            $stmt->execute([$groupId]);
            $totalMembers = $stmt->fetchColumn();
            
            if ($memberNumber > $totalMembers) {
                throw new Exception("Member number cannot be greater than total members ({$totalMembers}) in the group.");
            }
            
            // Update member
            $updateSql = "
                UPDATE members 
                SET group_id = ?, member_name = ?, member_number = ?, username = ?, 
                    phone = ?, email = ?, address = ?, status = ?, updated_at = NOW()
            ";
            $params = [
                $groupId, 
                $memberName, 
                $memberNumber, 
                $username ?: null, 
                $phone ?: null, 
                $email ?: null, 
                $address ?: null,
                $status
            ];
            
            // Add password reset if requested
            if ($resetPassword) {
                $updateSql .= ", password = ?";
                $params[] = password_hash('member123', PASSWORD_DEFAULT);
            }
            
            $updateSql .= " WHERE id = ?";
            $params[] = $memberId;
            
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($params);
            
            $pdo->commit();
            $success = 'Member updated successfully!' . ($resetPassword ? ' Password reset to "member123".' : '');
            
            // Refresh member data
            $stmt = $pdo->prepare("
                SELECT m.*, g.group_name, g.total_members 
                FROM members m 
                JOIN bc_groups g ON m.group_id = g.id 
                WHERE m.id = ?
            ");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins"></i> <?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="admin_members.php">
                    <i class="fas fa-arrow-left"></i> Back to Members
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card form-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-edit fa-3x mb-3"></i>
                            <h3>Edit Member</h3>
                            <p class="mb-0">Update member information</p>
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
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="member_name" class="form-label">
                                            <i class="fas fa-user"></i> Member Name *
                                        </label>
                                        <input type="text" class="form-control" id="member_name" 
                                               name="member_name" value="<?= htmlspecialchars($member['member_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="group_id" class="form-label">
                                            <i class="fas fa-users"></i> BC Group *
                                        </label>
                                        <select class="form-select" id="group_id" name="group_id" required>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?= $group['id'] ?>" 
                                                        data-total-members="<?= $group['total_members'] ?>"
                                                        <?= $member['group_id'] == $group['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($group['group_name']) ?> 
                                                    (<?= $group['total_members'] ?> members)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="member_number" class="form-label">
                                            <i class="fas fa-hashtag"></i> Member Number *
                                        </label>
                                        <input type="number" class="form-control" id="member_number" 
                                               name="member_number" value="<?= $member['member_number'] ?>" 
                                               min="1" max="<?= $member['total_members'] ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-at"></i> Username
                                        </label>
                                        <input type="text" class="form-control" id="username" 
                                               name="username" value="<?= htmlspecialchars($member['username'] ?: '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">
                                            <i class="fas fa-phone"></i> Phone Number
                                        </label>
                                        <input type="tel" class="form-control" id="phone" 
                                               name="phone" value="<?= htmlspecialchars($member['phone'] ?: '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <input type="email" class="form-control" id="email" 
                                               name="email" value="<?= htmlspecialchars($member['email'] ?: '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($member['address'] ?: '') ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">
                                            <i class="fas fa-toggle-on"></i> Status
                                        </label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fas fa-key"></i> Password Reset
                                        </label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="reset_password" name="reset_password">
                                            <label class="form-check-label" for="reset_password">
                                                Reset password to "member123"
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-light btn-lg">
                                    <i class="fas fa-save"></i> Update Member
                                </button>
                                <a href="admin_members.php" class="btn btn-outline-light">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Member Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle"></i> Member Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Member ID:</strong> <?= $member['id'] ?><br>
                                <strong>Created:</strong> <?= formatDate($member['created_at']) ?><br>
                                <strong>Last Updated:</strong> <?= $member['updated_at'] ? formatDate($member['updated_at']) : 'Never' ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Current Group:</strong> <?= htmlspecialchars($member['group_name']) ?><br>
                                <strong>Member Number:</strong> #<?= $member['member_number'] ?><br>
                                <strong>Status:</strong> 
                                <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($member['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
