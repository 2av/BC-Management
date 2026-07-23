<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$originalGroupId = (int)($_GET['id'] ?? 0);
if (!$originalGroupId) {
    setMessage('Group ID is required.', 'error');
    redirect('admin_manage_groups.php');
}

$originalGroup = getGroupById($originalGroupId);
if (!$originalGroup) {
    setMessage('Group not found.', 'error');
    redirect('admin_manage_groups.php');
}

// Get existing members for suggestions
$pdo = getDB();
$stmt = $pdo->query("SELECT DISTINCT member_name FROM members ORDER BY member_name");
$allExistingMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$originalMembers = getGroupMembers($originalGroupId);
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newGroupName = trim($_POST['new_group_name'] ?? '');
    $selectedMembers = $_POST['selected_members'] ?? [];
    $newMembers = array_filter(array_map('trim', $_POST['new_members'] ?? []));
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    
    if (empty($newGroupName)) {
        $error = 'Group name is required.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Combine selected existing members and new members
            $allMembers = [];
            
            // Add selected existing members
            if (!empty($selectedMembers)) {
                $placeholders = str_repeat('?,', count($selectedMembers) - 1) . '?';
                $stmt = $pdo->prepare("SELECT member_name FROM members WHERE id IN ($placeholders)");
                $stmt->execute($selectedMembers);
                $existingMemberNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $allMembers = array_merge($allMembers, $existingMemberNames);
            }
            
            // Add new members
            $allMembers = array_merge($allMembers, $newMembers);
            
            if (empty($allMembers)) {
                throw new Exception("At least one member is required for the new group.");
            }
            
            $totalMembers = count($allMembers);
            $totalMonthlyCollection = $totalMembers * $originalGroup['monthly_contribution'];
            
            // Create new group
            $stmt = $pdo->prepare("
                INSERT INTO bc_groups (group_name, total_members, monthly_contribution, total_monthly_collection, start_date, status) 
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$newGroupName, $totalMembers, $originalGroup['monthly_contribution'], $totalMonthlyCollection, $startDate]);
            
            $newGroupId = $pdo->lastInsertId();
            
            // Add members to new group
            $memberStmt = $pdo->prepare("
                INSERT INTO members (group_id, member_name, member_number, username, password, status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            
            $summaryStmt = $pdo->prepare("
                INSERT INTO member_summary (group_id, member_id, total_paid, given_amount, profit)
                VALUES (?, ?, 0, 0, 0)
            ");
            
            foreach ($allMembers as $index => $memberName) {
                $memberNumber = $index + 1;
                
                // Create username
                $cleanName = strtolower(str_replace([' ', '.', '-'], '', $memberName));
                $username = $cleanName;
                
                // Check if this member already exists in system
                $checkStmt = $pdo->prepare("SELECT username, password FROM members WHERE member_name = ? LIMIT 1");
                $checkStmt->execute([$memberName]);
                $existingMember = $checkStmt->fetch();
                
                if ($existingMember) {
                    // Reuse existing credentials
                    $username = $existingMember['username'];
                    $password = $existingMember['password'];
                } else {
                    // Create new credentials
                    $password = password_hash('member123', PASSWORD_DEFAULT);
                    
                    // Ensure username is unique
                    $counter = 1;
                    $originalUsername = $username;
                    while (true) {
                        $checkUsernameStmt = $pdo->prepare("SELECT id FROM members WHERE username = ?");
                        $checkUsernameStmt->execute([$username]);
                        if (!$checkUsernameStmt->fetch()) {
                            break;
                        }
                        $username = $originalUsername . $counter;
                        $counter++;
                    }
                }
                
                // Insert member
                $memberStmt->execute([$newGroupId, $memberName, $memberNumber, $username, $password]);
                $memberId = $pdo->lastInsertId();
                
                // Create member summary
                $summaryStmt->execute([$newGroupId, $memberId]);
            }
            
            // Initialize month bidding status for all months (if table exists)
            $tableExists = $pdo->query("SHOW TABLES LIKE 'month_bidding_status'")->fetch();
            if ($tableExists) {
                $biddingStmt = $pdo->prepare("
                    INSERT INTO month_bidding_status (group_id, month_number, bidding_status)
                    VALUES (?, ?, 'not_started')
                ");
                
                for ($month = 1; $month <= $totalMembers; $month++) {
                    $biddingStmt->execute([$newGroupId, $month]);
                }
            }
            
            $pdo->commit();
            setMessage("Group '{$newGroupName}' created successfully with {$totalMembers} members!", 'success');
            redirect("view_group.php?id={$newGroupId}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to clone group: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restart Group - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h4 class="mb-0">
                            <i class="fas fa-copy"></i> Restart Group: <?= htmlspecialchars($originalGroup['group_name']) ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Original Group Details:</strong><br>
                            Monthly Contribution: <?= formatCurrency($originalGroup['monthly_contribution']) ?><br>
                            Total Members: <?= $originalGroup['total_members'] ?><br>
                            Status: <?= ucfirst($originalGroup['status']) ?>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">New Group Name *</label>
                                <input type="text" class="form-control" name="new_group_name" 
                                       value="<?= htmlspecialchars($originalGroup['group_name']) ?> - Cycle 2" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" class="form-control" name="start_date" 
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Select Members from Previous Group</label>
                                <?php if (!empty($originalMembers)): ?>
                                    <div class="row">
                                        <div class="col-12 mb-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllMembers(true)">
                                                Select All
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllMembers(false)">
                                                Deselect All
                                            </button>
                                        </div>
                                        <?php foreach ($originalMembers as $member): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input member-checkbox" type="checkbox" 
                                                           name="selected_members[]" value="<?= $member['id'] ?>"
                                                           id="member_<?= $member['id'] ?>" checked>
                                                    <label class="form-check-label" for="member_<?= $member['id'] ?>">
                                                        <?= htmlspecialchars($member['member_name']) ?>
                                                        <small class="text-muted">(#<?= $member['member_number'] ?>)</small>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No members found in original group.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Add New Members</label>
                                <div id="newMembersContainer">
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" name="new_members[]" 
                                               placeholder="Enter new member name" list="memberSuggestions">
                                        <button type="button" class="btn btn-outline-success" onclick="addNewMemberField()">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Datalist for member suggestions -->
                                <datalist id="memberSuggestions">
                                    <?php foreach ($allExistingMembers as $memberName): ?>
                                        <option value="<?= htmlspecialchars($memberName) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="admin_manage_groups.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Groups
                                </a>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-copy"></i> Create New Group
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addNewMemberField() {
            const container = document.getElementById('newMembersContainer');
            const newField = document.createElement('div');
            newField.className = 'input-group mb-2';
            newField.innerHTML = `
                <input type="text" class="form-control" name="new_members[]" 
                       placeholder="Enter new member name" list="memberSuggestions">
                <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
                    <i class="fas fa-minus"></i>
                </button>
            `;
            container.appendChild(newField);
        }
        
        function selectAllMembers(select) {
            const checkboxes = document.querySelectorAll('.member-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = select;
            });
        }
    </script>
</body>
</html>
