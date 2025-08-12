<?php
require_once 'config.php';
requireAdminLogin();

$error = '';
$success = '';

// Get all groups for selection
$pdo = getDB();
$stmt = $pdo->query("SELECT * FROM bc_groups WHERE status = 'active' ORDER BY group_name");
$groups = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberName = trim($_POST['member_name'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);
    $memberNumber = (int)($_POST['member_number'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? 'member123';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
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
            
            // Check if member number already exists in the group
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE group_id = ? AND member_number = ?");
            $stmt->execute([$groupId, $memberNumber]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Member number {$memberNumber} already exists in this group.");
            }
            
            // Check if username already exists (if provided)
            if (!empty($username)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE username = ?");
                $stmt->execute([$username]);
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
            
            // Insert new member
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO members (group_id, member_name, member_number, username, password, phone, email, address, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $groupId, 
                $memberName, 
                $memberNumber, 
                $username ?: null, 
                $hashedPassword, 
                $phone ?: null, 
                $email ?: null, 
                $address ?: null
            ]);
            
            $memberId = $pdo->lastInsertId();
            
            // Create member summary record
            $stmt = $pdo->prepare("
                INSERT INTO member_summary (member_id, total_paid, given_amount, profit) 
                VALUES (?, 0, 0, 0)
            ");
            $stmt->execute([$memberId]);
            
            $pdo->commit();
            $success = 'Member added successfully!';
            
            // Clear form data
            $memberName = $memberNumber = $username = $phone = $email = $address = '';
            
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
    <title>Add New Member - <?= APP_NAME ?></title>
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
                            <i class="fas fa-user-plus fa-3x mb-3"></i>
                            <h3>Add New Member</h3>
                            <p class="mb-0">Add a new member to an existing BC group</p>
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
                                               name="member_name" value="<?= htmlspecialchars($memberName ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="group_id" class="form-label">
                                            <i class="fas fa-users"></i> BC Group *
                                        </label>
                                        <select class="form-select" id="group_id" name="group_id" required onchange="updateMemberNumbers()">
                                            <option value="">Select Group</option>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?= $group['id'] ?>" 
                                                        data-total-members="<?= $group['total_members'] ?>"
                                                        <?= (isset($groupId) && $groupId == $group['id']) ? 'selected' : '' ?>>
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
                                               name="member_number" value="<?= htmlspecialchars($memberNumber ?? '') ?>" 
                                               min="1" required>
                                        <div class="form-text text-light">
                                            <small id="memberNumberHelp">Select a group first to see available numbers</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-at"></i> Username (Optional)
                                        </label>
                                        <input type="text" class="form-control" id="username" 
                                               name="username" value="<?= htmlspecialchars($username ?? '') ?>">
                                        <div class="form-text text-light">
                                            <small>For member login (leave empty to use member name)</small>
                                        </div>
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
                                               name="phone" value="<?= htmlspecialchars($phone ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <input type="email" class="form-control" id="email" 
                                               name="email" value="<?= htmlspecialchars($email ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($address ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-key"></i> Initial Password
                                </label>
                                <input type="text" class="form-control" id="password" 
                                       name="password" value="member123">
                                <div class="form-text text-light">
                                    <small>Default password that member can change later</small>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-light btn-lg">
                                    <i class="fas fa-save"></i> Add Member
                                </button>
                                <a href="admin_members.php" class="btn btn-outline-light">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateMemberNumbers() {
            const groupSelect = document.getElementById('group_id');
            const memberNumberInput = document.getElementById('member_number');
            const helpText = document.getElementById('memberNumberHelp');
            
            if (groupSelect.value) {
                const selectedOption = groupSelect.options[groupSelect.selectedIndex];
                const totalMembers = selectedOption.getAttribute('data-total-members');
                
                memberNumberInput.max = totalMembers;
                helpText.textContent = `Choose a number between 1 and ${totalMembers}`;
                
                // Suggest next available number
                fetch(`get_next_member_number.php?group_id=${groupSelect.value}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.next_number) {
                            memberNumberInput.value = data.next_number;
                            helpText.textContent = `Suggested: ${data.next_number} (next available number)`;
                        }
                    })
                    .catch(() => {
                        // Fallback if API fails
                        helpText.textContent = `Choose a number between 1 and ${totalMembers}`;
                    });
            } else {
                memberNumberInput.max = '';
                memberNumberInput.value = '';
                helpText.textContent = 'Select a group first to see available numbers';
            }
        }
    </script>
</body>
</html>
