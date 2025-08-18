<?php
require_once 'config.php';
requireAdminLogin();

// Get existing members for suggestions
$pdo = getDB();
$stmt = $pdo->query("SELECT DISTINCT member_name FROM members ORDER BY member_name");
$existingMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$success = '';

// Handle group actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_member'])) {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $memberName = trim($_POST['member_name'] ?? '');
        $newMemberName = trim($_POST['new_member_name'] ?? '');

        // Use new member name if "Add new member" was selected
        if ($memberName === '__NEW__' && $newMemberName) {
            $memberName = $newMemberName;
        }

        if ($groupId && $memberName) {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                // Check if member is already in this group
                $checkStmt = $pdo->prepare("SELECT id FROM members WHERE group_id = ? AND member_name = ?");
                $checkStmt->execute([$groupId, $memberName]);
                if ($checkStmt->fetch()) {
                    throw new Exception("Member '{$memberName}' is already in this group.");
                }

                // Get group info
                $group = getGroupById($groupId);
                $members = getGroupMembers($groupId);

                // Get next member number
                $nextMemberNumber = count($members) + 1;

                // Create simple username from member name
                $cleanName = strtolower(str_replace([' ', '.', '-'], '', $memberName));
                $username = $cleanName;

                // Check if username already exists, if so reuse the existing member's credentials
                $checkStmt = $pdo->prepare("SELECT username, password FROM members WHERE member_name = ? LIMIT 1");
                $checkStmt->execute([$memberName]);
                $existingMember = $checkStmt->fetch();

                if ($existingMember) {
                    // Reuse existing username and password
                    $username = $existingMember['username'];
                    $password = $existingMember['password'];
                } else {
                    // Create new password for new member
                    $password = password_hash('member123', PASSWORD_DEFAULT);

                    // Ensure username is unique
                    $counter = 1;
                    $originalUsername = $username;
                    while (true) {
                        $checkUsernameStmt = $pdo->prepare("SELECT id FROM members WHERE username = ?");
                        $checkUsernameStmt->execute([$username]);
                        if (!$checkUsernameStmt->fetch()) {
                            break; // Username is unique
                        }
                        $username = $originalUsername . $counter;
                        $counter++;
                    }
                }

                // Insert member
                $stmt = $pdo->prepare("
                    INSERT INTO members (group_id, member_name, member_number, username, password, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([$groupId, $memberName, $nextMemberNumber, $username, $password]);

                $memberId = $pdo->lastInsertId();

                // Create member summary with both group_id and member_id
                $stmt = $pdo->prepare("
                    INSERT INTO member_summary (group_id, member_id, total_paid, given_amount, profit)
                    VALUES (?, ?, 0, 0, 0)
                ");
                $stmt->execute([$groupId, $memberId]);
                
                // Update group total members if needed
                if ($nextMemberNumber > $group['total_members']) {
                    $newTotalCollection = $nextMemberNumber * $group['monthly_contribution'];
                    $stmt = $pdo->prepare("
                        UPDATE bc_groups 
                        SET total_members = ?, total_monthly_collection = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$nextMemberNumber, $newTotalCollection, $groupId]);
                    
                    // Add new month bidding status if needed
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO month_bidding_status (group_id, month_number, bidding_status) 
                        VALUES (?, ?, 'not_started')
                    ");
                    $stmt->execute([$groupId, $nextMemberNumber]);
                }
                
                $pdo->commit();
                $success = "Member '{$memberName}' added successfully! Username: {$username}, Password: member123";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to add member: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['update_group'])) {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $groupName = trim($_POST['group_name'] ?? '');
        $monthlyContribution = (float)($_POST['monthly_contribution'] ?? 0);
        $totalMembers = (int)($_POST['total_members'] ?? 0);
        
        if ($groupId && $groupName && $monthlyContribution > 0 && $totalMembers > 0) {
            try {
                $pdo = getDB();
                $newTotalCollection = $totalMembers * $monthlyContribution;
                
                $stmt = $pdo->prepare("
                    UPDATE bc_groups 
                    SET group_name = ?, monthly_contribution = ?, total_members = ?, total_monthly_collection = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$groupName, $monthlyContribution, $totalMembers, $newTotalCollection, $groupId]);
                
                $success = 'Group updated successfully!';
                
            } catch (Exception $e) {
                $error = 'Failed to update group: ' . $e->getMessage();
            }
        }
    }
}

// Get all groups
$pdo = getDB();
$stmt = $pdo->query("
    SELECT bg.*, 
           COUNT(m.id) as actual_members,
           COUNT(mb.id) as completed_months
    FROM bc_groups bg
    LEFT JOIN members m ON bg.id = m.group_id AND m.status = 'active'
    LEFT JOIN monthly_bids mb ON bg.id = mb.group_id
    GROUP BY bg.id
    ORDER BY bg.created_at DESC
");
$groups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Groups - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users-cog"></i> Manage BC Groups</h2>
            <a href="admin_create_group_simple.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Group
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
            <?php foreach ($groups as $group): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-users"></i> <?= htmlspecialchars($group['group_name']) ?>
                                <span class="badge bg-light text-primary ms-2"><?= $group['status'] ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Members</small><br>
                                    <strong><?= $group['actual_members'] ?> / <?= $group['total_members'] ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Completed</small><br>
                                    <strong><?= $group['completed_months'] ?> months</strong>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">Monthly Contribution</small><br>
                                <strong><?= formatCurrency($group['monthly_contribution']) ?></strong>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">Total Collection</small><br>
                                <strong><?= formatCurrency($group['total_monthly_collection']) ?></strong>
                            </div>
                            
                            <!-- Quick Add Member -->
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                <div class="input-group input-group-sm">
                                    <?php if (!empty($existingMembers)): ?>
                                        <select class="form-select" name="member_name" required onchange="updateMemberInput(this, <?= $group['id'] ?>)">
                                            <option value="">Select existing member</option>
                                            <?php foreach ($existingMembers as $memberName): ?>
                                                <option value="<?= htmlspecialchars($memberName) ?>">
                                                    <?= htmlspecialchars($memberName) ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="__NEW__">+ Add new member</option>
                                        </select>
                                        <input type="text" class="form-control" name="new_member_name"
                                               placeholder="Enter new member name" style="display: none;">
                                    <?php else: ?>
                                        <input type="text" class="form-control" name="member_name"
                                               placeholder="Add member name" required>
                                    <?php endif; ?>
                                    <button type="submit" name="add_member" class="btn btn-success">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer">
                            <div class="btn-group w-100" role="group">
                                <a href="view_group.php?id=<?= $group['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                        data-bs-toggle="modal" data-bs-target="#editModal<?= $group['id'] ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="admin_add_member.php?group_id=<?= $group['id'] ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-user-plus"></i> Members
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?= $group['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Group</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Group Name</label>
                                        <input type="text" class="form-control" name="group_name" 
                                               value="<?= htmlspecialchars($group['group_name']) ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Monthly Contribution</label>
                                        <input type="number" class="form-control" name="monthly_contribution" 
                                               value="<?= $group['monthly_contribution'] ?>" min="100" step="50" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Total Members</label>
                                        <input type="number" class="form-control" name="total_members" 
                                               value="<?= $group['total_members'] ?>" min="2" max="50" required>
                                        <div class="form-text">Current actual members: <?= $group['actual_members'] ?></div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="update_group" class="btn btn-primary">Update Group</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($groups)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Groups Found</h4>
                <p class="text-muted">Create your first BC group to get started.</p>
                <a href="admin_create_group_simple.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create First Group
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateMemberInput(selectElement, groupId) {
            const container = selectElement.parentElement;
            const newMemberInput = container.querySelector('input[name="new_member_name"]');

            if (selectElement.value === '__NEW__') {
                selectElement.style.display = 'none';
                newMemberInput.style.display = 'block';
                newMemberInput.focus();
                newMemberInput.required = true;
                selectElement.required = false;
            } else {
                selectElement.style.display = 'block';
                newMemberInput.style.display = 'none';
                newMemberInput.required = false;
                selectElement.required = true;
            }
        }

        // Reset form when modal is closed
        document.addEventListener('DOMContentLoaded', function() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function() {
                    const selects = this.querySelectorAll('select[name="member_name"]');
                    const inputs = this.querySelectorAll('input[name="new_member_name"]');

                    selects.forEach(select => {
                        select.value = '';
                        select.style.display = 'block';
                        select.required = true;
                    });

                    inputs.forEach(input => {
                        input.value = '';
                        input.style.display = 'none';
                        input.required = false;
                    });
                });
            });
        });
    </script>
</body>
</html>
