<?php
require_once 'config.php';
requireAdminLogin();

// Get all existing members for selection
$pdo = getDB();
$stmt = $pdo->query("SELECT DISTINCT member_name FROM members ORDER BY member_name");
$existingMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupName = trim($_POST['group_name'] ?? '');
    $monthlyContribution = (float)($_POST['monthly_contribution'] ?? 0);
    $startDate = $_POST['start_date'] ?? '';
    $estimatedMembers = (int)($_POST['estimated_members'] ?? 0);
    $selectedMembers = $_POST['selected_members'] ?? [];
    $addMembersNow = isset($_POST['add_members_now']);
    
    // Basic validation
    if (empty($groupName)) {
        $error = 'Group name is required.';
    } elseif ($monthlyContribution <= 0) {
        $error = 'Monthly contribution must be greater than 0.';
    } elseif (empty($startDate)) {
        $error = 'Start date is required.';
    } elseif ($estimatedMembers < 2 || $estimatedMembers > 50) {
        $error = 'Estimated members must be between 2 and 50.';
    } else {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();
            
            // Create group with estimated members (can be changed later)
            $totalMonthlyCollection = $estimatedMembers * $monthlyContribution;
            
            $stmt = $pdo->prepare("
                INSERT INTO bc_groups (group_name, total_members, monthly_contribution, total_monthly_collection, start_date, status) 
                VALUES (?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$groupName, $estimatedMembers, $monthlyContribution, $totalMonthlyCollection, $startDate]);
            
            $groupId = $pdo->lastInsertId();

            // Add selected members if any
            $addedMembersCount = 0;
            if ($addMembersNow && !empty($selectedMembers)) {
                $stmt = $pdo->prepare("
                    INSERT INTO members (group_id, member_name, member_number, username, password, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                ");

                foreach ($selectedMembers as $index => $memberName) {
                    if (!empty(trim($memberName))) {
                        $memberNumber = $index + 1;
                        $memberName = trim($memberName);

                        // Check if member is already in this group (shouldn't happen in creation, but safety check)
                        $checkStmt = $pdo->prepare("SELECT id FROM members WHERE group_id = ? AND member_name = ?");
                        $checkStmt->execute([$groupId, $memberName]);
                        if ($checkStmt->fetch()) {
                            continue; // Skip if already exists
                        }

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

                        $stmt->execute([$groupId, $memberName, $memberNumber, $username, $password]);
                        $addedMembersCount++;

                        // Create member summary with both group_id and member_id
                        $memberId = $pdo->lastInsertId();
                        $summaryStmt = $pdo->prepare("
                            INSERT INTO member_summary (group_id, member_id, total_paid, given_amount, profit)
                            VALUES (?, ?, 0, 0, 0)
                        ");
                        $summaryStmt->execute([$groupId, $memberId]);
                    }
                }

                // Update group with actual member count if members were added
                if ($addedMembersCount > 0) {
                    $actualTotalMembers = max($estimatedMembers, $addedMembersCount);
                    $actualTotalCollection = $actualTotalMembers * $monthlyContribution;

                    $updateStmt = $pdo->prepare("
                        UPDATE bc_groups
                        SET total_members = ?, total_monthly_collection = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$actualTotalMembers, $actualTotalCollection, $groupId]);

                    $estimatedMembers = $actualTotalMembers;
                }
            }

            // Initialize month bidding status for all potential months
            $stmt = $pdo->prepare("
                INSERT INTO month_bidding_status (group_id, month_number, bidding_status)
                VALUES (?, ?, 'not_started')
            ");

            for ($month = 1; $month <= $estimatedMembers; $month++) {
                $stmt->execute([$groupId, $month]);
            }

            $pdo->commit();

            if ($addedMembersCount > 0) {
                $success = "BC Group '{$groupName}' created successfully with {$addedMembersCount} members! You can add more members or start the BC process.";
            } else {
                $success = "BC Group '{$groupName}' created successfully! You can now add members to this group.";
            }

            // Clear form
            $groupName = $monthlyContribution = $startDate = $estimatedMembers = '';
            $selectedMembers = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to create group: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Group - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>Create New BC Group
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                                <div class="mt-2">
                                    <a href="admin_manage_groups.php" class="btn btn-success btn-sm">
                                        <i class="fas fa-list"></i> View All Groups
                                    </a>
                                    <a href="admin_add_member.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-user-plus"></i> Add Members
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Simplified Group Creation:</strong><br>
                            • Create the group with basic information<br>
                            • Add members later using the "Add Member" feature<br>
                            • Adjust total members as needed<br>
                            • No need to enter all member names upfront
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="group_name" class="form-label">
                                    <i class="fas fa-users"></i> Group Name *
                                </label>
                                <input type="text" class="form-control" id="group_name" name="group_name" 
                                       value="<?= htmlspecialchars($groupName ?? '') ?>" 
                                       placeholder="e.g., Family BC Group, Office BC Group" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="monthly_contribution" class="form-label">
                                            <i class="fas fa-rupee-sign"></i> Monthly Contribution *
                                        </label>
                                        <input type="number" class="form-control" id="monthly_contribution" 
                                               name="monthly_contribution" value="<?= htmlspecialchars($monthlyContribution ?? '') ?>" 
                                               min="100" step="50" placeholder="2000" required>
                                        <div class="form-text">Amount each member contributes monthly</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="estimated_members" class="form-label">
                                            <i class="fas fa-calculator"></i> Estimated Members *
                                        </label>
                                        <input type="number" class="form-control" id="estimated_members" 
                                               name="estimated_members" value="<?= htmlspecialchars($estimatedMembers ?? '') ?>" 
                                               min="2" max="50" placeholder="9" required>
                                        <div class="form-text">Can be adjusted later when adding members</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="start_date" class="form-label">
                                    <i class="fas fa-calendar"></i> Start Date *
                                </label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                       value="<?= htmlspecialchars($startDate ?? date('Y-m-d')) ?>" required>
                                <div class="form-text">When the BC group will begin</div>
                            </div>

                            <!-- Optional Member Addition -->
                            <div class="card bg-light">
                                <div class="card-header">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="add_members_now"
                                               name="add_members_now" onchange="toggleMemberSelection()">
                                        <label class="form-check-label" for="add_members_now">
                                            <i class="fas fa-users"></i> <strong>Add Members Now (Optional)</strong>
                                        </label>
                                    </div>
                                    <small class="text-muted">You can add members now or later using the group management page</small>
                                </div>
                                <div class="card-body" id="memberSelectionArea" style="display: none;">
                                    <?php if (!empty($existingMembers)): ?>
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-user-check"></i> Select from Existing Members
                                            </label>
                                            <div class="row" id="existingMembersArea">
                                                <?php foreach ($existingMembers as $index => $memberName): ?>
                                                    <div class="col-md-6 col-lg-4 mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input member-checkbox" type="checkbox"
                                                                   id="member_<?= $index ?>" name="selected_members[]"
                                                                   value="<?= htmlspecialchars($memberName) ?>"
                                                                   onchange="updateMemberCount()">
                                                            <label class="form-check-label" for="member_<?= $index ?>">
                                                                <?= htmlspecialchars($memberName) ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllMembers()">
                                                    <i class="fas fa-check-double"></i> Select All
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllMembers()">
                                                    <i class="fas fa-times"></i> Clear All
                                                </button>
                                                <span class="ms-3 text-muted">
                                                    Selected: <span id="selectedCount">0</span> members
                                                </span>
                                            </div>
                                        </div>

                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Auto-generated credentials:</strong> Selected members will get usernames like
                                            "membername1", "membername2" with password "member123"
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <strong>No existing members found.</strong><br>
                                            You can add members after creating the group using the "Add Member" feature.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="admin_dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create Group
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-list-ol"></i> Next Steps:</h6>
                                <ol class="small">
                                    <li>Create the group (this form)</li>
                                    <li>Add members one by one</li>
                                    <li>Adjust group settings if needed</li>
                                    <li>Start the BC process</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-tools"></i> Quick Actions:</h6>
                                <div class="d-grid gap-2">
                                    <a href="admin_add_member.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-user-plus"></i> Add Member
                                    </a>
                                    <a href="admin_manage_groups.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-list"></i> Manage Groups
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleMemberSelection() {
            const checkbox = document.getElementById('add_members_now');
            const memberArea = document.getElementById('memberSelectionArea');

            if (checkbox.checked) {
                memberArea.style.display = 'block';
                updateMemberCount();
            } else {
                memberArea.style.display = 'none';
                clearAllMembers();
            }
        }

        function updateMemberCount() {
            const checkboxes = document.querySelectorAll('.member-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;

            // Update estimated members if members are selected
            const estimatedInput = document.getElementById('estimated_members');
            if (count > 0 && count > parseInt(estimatedInput.value)) {
                estimatedInput.value = count;
            }
        }

        function selectAllMembers() {
            const checkboxes = document.querySelectorAll('.member-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateMemberCount();
        }

        function clearAllMembers() {
            const checkboxes = document.querySelectorAll('.member-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateMemberCount();
        }

        // Auto-update estimated members when manually changed
        document.getElementById('estimated_members').addEventListener('input', function() {
            const selectedCount = document.querySelectorAll('.member-checkbox:checked').length;
            if (selectedCount > 0 && selectedCount > parseInt(this.value)) {
                this.value = selectedCount;
            }
        });
    </script>
</body>
</html>
