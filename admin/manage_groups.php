<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

// Get existing members for suggestions
$pdo = getDB();
$stmt = $pdo->query("SELECT DISTINCT member_name FROM members ORDER BY member_name");
$existingMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$success = '';

// Handle group actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle group deletion
    if (isset($_POST['delete_group'])) {
        $groupId = (int)($_POST['group_id'] ?? 0);

        if ($groupId && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
            try {
                $pdo->beginTransaction();

                // Delete all related data in correct order (child tables first)

                // 1. Delete random picks
                $stmt = $pdo->prepare("DELETE FROM random_picks WHERE group_id = ?");
                $stmt->execute([$groupId]);

                // 2. Delete member bids
                $stmt = $pdo->prepare("DELETE FROM member_bids WHERE group_id = ?");
                $stmt->execute([$groupId]);

                // 3. Delete month bidding status
                $stmt = $pdo->prepare("DELETE FROM month_bidding_status WHERE group_id = ?");
                $stmt->execute([$groupId]);

                // 4. Delete member payments
                $stmt = $pdo->prepare("DELETE FROM member_payments WHERE group_id = ?");
                $stmt->execute([$groupId]);

                // 5. Delete member summary
                $stmt = $pdo->prepare("DELETE FROM member_summary WHERE group_id = ?");
                $stmt->execute([$groupId]);

                // 6. Delete monthly bids
                $stmt = $pdo->prepare("DELETE FROM monthly_bids WHERE group_id = ?");
                $stmt->execute([$groupId]);

                // 7. Delete group member assignments
                $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
                $stmt->execute([$groupId]);

                // 8. Finally delete the group itself
                $stmt = $pdo->prepare("DELETE FROM bc_groups WHERE id = ?");
                $stmt->execute([$groupId]);

                $pdo->commit();
                $success = 'Group and all related data have been deleted successfully!';

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to delete group: ' . $e->getMessage();
            }
        } else {
            $error = 'Group deletion cancelled - confirmation not provided.';
        }
    }
    if (isset($_POST['clone_group'])) {
        $originalGroupId = (int)($_POST['original_group_id'] ?? 0);
        $newGroupName = trim($_POST['new_group_name'] ?? '');
        $selectedMembers = $_POST['selected_members'] ?? [];
        $newMembers = array_filter(array_map('trim', $_POST['new_members'] ?? []));
        $startDate = $_POST['start_date'] ?? date('Y-m-d');

        if ($originalGroupId && $newGroupName) {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                // Get original group details
                $originalGroup = getGroupById($originalGroupId);
                if (!$originalGroup) {
                    throw new Exception("Original group not found.");
                }

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
                $success = "Group '{$newGroupName}' cloned successfully with {$totalMembers} members!";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to clone group: ' . $e->getMessage();
            }
        } else {
            $error = 'Group name is required for cloning.';
        }
    }

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
           COUNT(DISTINCT gm.member_id) as actual_members,
           COUNT(DISTINCT mb.id) as completed_months
    FROM bc_groups bg
    LEFT JOIN group_members gm ON bg.id = gm.group_id AND gm.status = 'active'
    LEFT JOIN monthly_bids mb ON bg.id = mb.group_id
    GROUP BY bg.id
    ORDER BY bg.created_at DESC
");
$groups = $stmt->fetchAll();

// Set page title for the header
$page_title = 'Manage Groups';

// Include the new header
require_once 'includes/header.php';
?>

<!-- Page content starts here -->
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
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-danger btn-sm w-100"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal<?= $group['id'] ?>">
                                    <i class="fas fa-trash"></i> Delete Group
                                </button>
                            </div>

                            <?php if ($group['status'] === 'completed'): ?>
                                <div class="mt-2">
                                    <a href="clone_group.php?id=<?= $group['id'] ?>" class="btn btn-warning btn-sm w-100">
                                        <i class="fas fa-copy"></i> Restart Group
                                    </a>
                                </div>
                            <?php endif; ?>
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

                <!-- Delete Modal -->
                <div class="modal fade" id="deleteModal<?= $group['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-exclamation-triangle"></i> Delete Group
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-danger">
                                    <h6><i class="fas fa-warning"></i> <strong>WARNING: This action cannot be undone!</strong></h6>
                                    <p class="mb-0">Deleting this group will permanently remove all related data.</p>
                                </div>

                                <div class="bg-light p-3 rounded mb-3">
                                    <h6>Group to be deleted:</h6>
                                    <p class="mb-1"><strong><?= htmlspecialchars($group['group_name']) ?></strong></p>
                                    <p class="mb-1">Members: <?= $group['actual_members'] ?></p>
                                    <p class="mb-0">Status: <?= ucfirst($group['status']) ?></p>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmDelete<?= $group['id'] ?>" required>
                                    <label class="form-check-label text-danger fw-bold" for="confirmDelete<?= $group['id'] ?>">
                                        I understand this action cannot be undone
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                    <input type="hidden" name="confirm_delete" value="yes">
                                    <button type="submit" name="delete_group" class="btn btn-secondary" id="deleteButton<?= $group['id'] ?>" disabled title="Check the confirmation box to enable">
                                        <i class="fas fa-trash"></i> Delete Permanently
                                    </button>
                                </form>
                            </div>
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


        // Reset form when modal is closed and handle delete confirmations
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

            // Handle delete confirmation checkboxes with improved event handling
            const deleteCheckboxes = document.querySelectorAll('[id^="confirmDelete"]');

            deleteCheckboxes.forEach(checkbox => {
                const groupId = checkbox.id.replace('confirmDelete', '');
                const deleteButton = document.getElementById('deleteButton' + groupId);

                if (deleteButton) {
                    // Add event listener for checkbox change
                    checkbox.addEventListener('change', function() {
                        console.log('Checkbox changed:', this.checked); // Debug log
                        deleteButton.disabled = !this.checked;

                        // Visual feedback
                        if (this.checked) {
                            deleteButton.classList.remove('btn-secondary');
                            deleteButton.classList.add('btn-danger');
                        } else {
                            deleteButton.classList.remove('btn-danger');
                            deleteButton.classList.add('btn-secondary');
                        }
                    });

                    // Also handle click events for better mobile support
                    checkbox.addEventListener('click', function() {
                        setTimeout(() => {
                            deleteButton.disabled = !this.checked;
                        }, 10);
                    });

                    // Reset checkbox when modal is hidden
                    const deleteModal = document.getElementById('deleteModal' + groupId);
                    if (deleteModal) {
                        deleteModal.addEventListener('hidden.bs.modal', function() {
                            checkbox.checked = false;
                            deleteButton.disabled = true;
                            deleteButton.classList.remove('btn-danger');
                            deleteButton.classList.add('btn-secondary');
                        });

                        // Also reset when modal is shown
                        deleteModal.addEventListener('shown.bs.modal', function() {
                            checkbox.checked = false;
                            deleteButton.disabled = true;
                            deleteButton.classList.remove('btn-danger');
                            deleteButton.classList.add('btn-secondary');
                        });
                    }
                }
            });
        });
    </script>

<?php require_once 'includes/footer.php'; ?>
