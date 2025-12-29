<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$groupId = (int)($_GET['group_id'] ?? 0);
if (!$groupId) {
    redirect('index.php');
}

$group = getGroupById($groupId);
if (!$group) {
    setMessage('Group not found.', 'error');
    redirect('index.php');
}

$members = getGroupMembers($groupId);

// Get existing members for suggestions (excluding those already in this group)
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT DISTINCT m1.member_name
    FROM members m1
    WHERE m1.id NOT IN (
        SELECT gm.member_id
        FROM group_members gm
        WHERE gm.group_id = ? AND gm.status = 'active'
    )
    ORDER BY m1.member_name
");
$stmt->execute([$groupId]);
$availableMembers = $stmt->fetchAll(PDO::FETCH_COLUMN);

$error = '';
$success = '';

// Handle adding new member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $memberName = trim($_POST['member_name'] ?? '');
    $newMemberName = trim($_POST['new_member_name'] ?? '');

    // Use new member name if "Add new member" was selected
    if ($memberName === '__NEW__' && $newMemberName) {
        $memberName = $newMemberName;
    }

    if ($memberName) {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            // Check if member is already in this group
            $checkStmt = $pdo->prepare("
                SELECT gm.id
                FROM group_members gm
                JOIN members m ON gm.member_id = m.id
                WHERE gm.group_id = ? AND m.member_name = ? AND gm.status = 'active'
            ");
            $checkStmt->execute([$groupId, $memberName]);
            if ($checkStmt->fetch()) {
                throw new Exception("Member '{$memberName}' is already in this group.");
            }

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

            // Check if member already exists in the system
            $stmt = $pdo->prepare("SELECT id FROM members WHERE member_name = ?");
            $stmt->execute([$memberName]);
            $existingMember = $stmt->fetch();

            if ($existingMember) {
                // Member exists, check if they're already in this group (active or inactive)
                $memberId = $existingMember['id'];
                
                $checkStmt = $pdo->prepare("
                    SELECT id, status FROM group_members 
                    WHERE group_id = ? AND member_id = ?
                ");
                $checkStmt->execute([$groupId, $memberId]);
                $groupMemberRecord = $checkStmt->fetch();
                
                if ($groupMemberRecord) {
                    if ($groupMemberRecord['status'] === 'active') {
                        throw new Exception("Member '{$memberName}' is already active in this group.");
                    } else {
                        // Member exists but is inactive, reactivate them
                        $stmt = $pdo->prepare("
                            UPDATE group_members 
                            SET status = 'active', member_number = ?, joined_date = NOW()
                            WHERE group_id = ? AND member_id = ?
                        ");
                        $stmt->execute([$nextMemberNumber, $groupId, $memberId]);
                    }
                } else {
                    // Member exists but not in this group, add them
                    $stmt = $pdo->prepare("
                        INSERT INTO group_members (group_id, member_id, member_number, status, joined_date)
                        VALUES (?, ?, ?, 'active', NOW())
                    ");
                    $stmt->execute([$groupId, $memberId, $nextMemberNumber]);
                }
            } else {
                // Create new member
                // Handle member_number column if it exists (legacy column, now stored in group_members)
                try {
                    // Check if member_number column exists and is NOT NULL
                    $checkColumnStmt = $pdo->query("SHOW COLUMNS FROM members WHERE Field = 'member_number'");
                    $columnInfo = $checkColumnStmt->fetch();
                    
                    if ($columnInfo) {
                        // Column exists - make it nullable if it's NOT NULL (since member_number is now in group_members)
                        $isNullable = ($columnInfo['Null'] === 'YES');
                        if (!$isNullable) {
                            try {
                                $pdo->exec("ALTER TABLE members MODIFY COLUMN member_number INT NULL");
                                $isNullable = true;
                            } catch (PDOException $e) {
                                // If ALTER fails, we'll use 0 as placeholder
                            }
                        }
                        
                        // Insert with NULL if nullable, otherwise use 0 (real member_number is stored in group_members table)
                        $memberNumberValue = $isNullable ? NULL : 0;
                        $stmt = $pdo->prepare("
                            INSERT INTO members (member_name, username, password, member_number, status)
                            VALUES (?, ?, ?, ?, 'active')
                        ");
                        $stmt->execute([$memberName, $username, $password, $memberNumberValue]);
                    } else {
                        // Column doesn't exist, use original INSERT
                        $stmt = $pdo->prepare("
                            INSERT INTO members (member_name, username, password, status)
                            VALUES (?, ?, ?, 'active')
                        ");
                        $stmt->execute([$memberName, $username, $password]);
                    }
                } catch (PDOException $e) {
                    // Fallback: try inserting with 0 as placeholder
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO members (member_name, username, password, member_number, status)
                            VALUES (?, ?, ?, 0, 'active')
                        ");
                        $stmt->execute([$memberName, $username, $password]);
                    } catch (PDOException $e2) {
                        // Last resort: try without member_number
                        $stmt = $pdo->prepare("
                            INSERT INTO members (member_name, username, password, status)
                            VALUES (?, ?, ?, 'active')
                        ");
                        $stmt->execute([$memberName, $username, $password]);
                    }
                }
                $memberId = $pdo->lastInsertId();
                
                // Add member to group
                $stmt = $pdo->prepare("
                    INSERT INTO group_members (group_id, member_id, member_number, status, joined_date)
                    VALUES (?, ?, ?, 'active', NOW())
                ");
                $stmt->execute([$groupId, $memberId, $nextMemberNumber]);
            }

            // Create member summary with both group_id and member_id (only if it doesn't exist)
            $checkSummaryStmt = $pdo->prepare("SELECT id FROM member_summary WHERE group_id = ? AND member_id = ?");
            $checkSummaryStmt->execute([$groupId, $memberId]);
            if (!$checkSummaryStmt->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO member_summary (group_id, member_id, total_paid, given_amount, profit)
                    VALUES (?, ?, 0, 0, 0)
                ");
                $stmt->execute([$groupId, $memberId]);
            }

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

            // Refresh data
            $group = getGroupById($groupId);
            $members = getGroupMembers($groupId);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to add member: ' . $e->getMessage();
        }
    } else {
        $error = 'Member name is required.';
    }
}

// Handle member removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $confirmName = trim($_POST['confirm_name'] ?? '');

    if ($memberId) {
        try {
            $pdo = getDB();

            // Get member details first
            $stmt = $pdo->prepare("
                SELECT m.*, gm.member_number, gm.status as assignment_status
                FROM members m
                JOIN group_members gm ON m.id = gm.member_id
                WHERE m.id = ? AND gm.group_id = ? AND gm.status = 'active'
            ");
            $stmt->execute([$memberId, $groupId]);
            $memberToRemove = $stmt->fetch();

            if (!$memberToRemove) {
                throw new Exception("Member not found.");
            }

            // Verify confirmation name matches
            if (strtolower($confirmName) !== strtolower($memberToRemove['member_name'])) {
                throw new Exception("Confirmation name does not match. Please type the exact member name to confirm removal.");
            }

            // Check if member has any payments, bids, or selections
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE group_id = ? AND taken_by_member_id = ?");
            $stmt->execute([$groupId, $memberId]);
            $bidCount = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM member_payments WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);
            $paymentCount = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM random_picks WHERE group_id = ? AND selected_member_id = ?");
            $stmt->execute([$groupId, $memberId]);
            $randomPickCount = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM member_bids WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);
            $memberBidCount = $stmt->fetchColumn();

            // Check if member has any financial activity
            $stmt = $pdo->prepare("SELECT total_paid, given_amount FROM member_summary WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);
            $memberSummary = $stmt->fetch();

            $hasFinancialActivity = false;
            $activityDetails = [];

            if ($bidCount > 0) {
                $hasFinancialActivity = true;
                $activityDetails[] = "taken {$bidCount} monthly bid(s)";
            }
            if ($paymentCount > 0) {
                $hasFinancialActivity = true;
                $activityDetails[] = "made {$paymentCount} payment(s)";
            }
            if ($randomPickCount > 0) {
                $hasFinancialActivity = true;
                $activityDetails[] = "selected in {$randomPickCount} random pick(s)";
            }
            if ($memberBidCount > 0) {
                $hasFinancialActivity = true;
                $activityDetails[] = "placed {$memberBidCount} bid(s)";
            }
            if ($memberSummary && ($memberSummary['total_paid'] > 0 || $memberSummary['given_amount'] > 0)) {
                $hasFinancialActivity = true;
                $activityDetails[] = "has financial activity (Paid: ₹{$memberSummary['total_paid']}, Given: ₹{$memberSummary['given_amount']})";
            }

            if ($hasFinancialActivity) {
                $activityText = implode(', ', $activityDetails);
                throw new Exception("Cannot remove member '{$memberToRemove['member_name']}' - they have existing activity: {$activityText}. Member removal is not allowed when there is financial history.");
            }

            $pdo->beginTransaction();

            // STEP 1: Remove from mapped tables first (in order of dependency)
            
            // 1.1 Remove from member_summary (group-specific financial summary)
            $stmt = $pdo->prepare("DELETE FROM member_summary WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);

            // 1.2 Remove any member_bids for this member in this group
            $stmt = $pdo->prepare("DELETE FROM member_bids WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);

            // 1.3 Update monthly_bids to set taken_by_member_id to NULL (if this member took any bids)
            $stmt = $pdo->prepare("UPDATE monthly_bids SET taken_by_member_id = NULL WHERE group_id = ? AND taken_by_member_id = ?");
            $stmt->execute([$groupId, $memberId]);

            // 1.4 Update random_picks to set selected_member_id to NULL (if this member was selected)
            $stmt = $pdo->prepare("UPDATE random_picks SET selected_member_id = NULL WHERE group_id = ? AND selected_member_id = ?");
            $stmt->execute([$groupId, $memberId]);

            // 1.5 Remove any member_payments for this member in this group (since no financial activity allowed)
            $stmt = $pdo->prepare("DELETE FROM member_payments WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);

            // STEP 2: Now remove from group_members (set status to inactive)
            // This only removes the member from the group, NOT from the members table (account is preserved)
            
            // First, get all existing member_numbers in this group (including inactive) to find a safe temporary range
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(member_number), 0) as max_number FROM group_members WHERE group_id = ?");
            $stmt->execute([$groupId]);
            $maxExistingNumber = $stmt->fetchColumn();
            
            // Set status to inactive and member_number to NULL to free it up
            $stmt = $pdo->prepare("UPDATE group_members SET status = 'inactive', member_number = NULL WHERE group_id = ? AND member_id = ?");
            $stmt->execute([$groupId, $memberId]);

            // Update member numbers for remaining members (renumber them sequentially)
            // Use a safer approach: update member_number using temporary values to avoid constraint conflicts
            
            // Step 1: Get all remaining active members with their data, ordered by current member_number
            $stmt = $pdo->prepare("
                SELECT gm.member_id, gm.member_number, gm.joined_date, gm.created_at
                FROM group_members gm
                WHERE gm.group_id = ? AND gm.status = 'active'
                ORDER BY gm.member_number ASC
            ");
            $stmt->execute([$groupId]);
            $remainingMembers = $stmt->fetchAll();

            if (!empty($remainingMembers)) {
                // Step 2: First, set all active member_numbers to temporary high values
                // Start from max existing + 10000 to ensure no conflicts with any existing numbers (active or inactive)
                $tempNumber = max(50000, ($maxExistingNumber + 10000));
                foreach ($remainingMembers as $member) {
                    $updateStmt = $pdo->prepare("
                        UPDATE group_members 
                        SET member_number = ? 
                        WHERE group_id = ? AND member_id = ? AND status = 'active'
                    ");
                    $updateStmt->execute([$tempNumber, $groupId, $member['member_id']]);
                    $tempNumber++;
                }
                
                // Step 3: Now update them with proper sequential numbering (1, 2, 3, ...)
                // Since all are now at high temporary values, there's no conflict with sequential numbers
                $newMemberNumber = 1;
                foreach ($remainingMembers as $member) {
                    $updateStmt = $pdo->prepare("
                        UPDATE group_members 
                        SET member_number = ? 
                        WHERE group_id = ? AND member_id = ? AND status = 'active'
                    ");
                    $updateStmt->execute([$newMemberNumber, $groupId, $member['member_id']]);
                    $newMemberNumber++;
                }
            }

            // Update group total members count
            $newTotalMembers = count($remainingMembers);
            $newTotalCollection = $newTotalMembers * $group['monthly_contribution'];

            $stmt = $pdo->prepare("
                UPDATE bc_groups
                SET total_members = ?, total_monthly_collection = ?
                WHERE id = ?
            ");
            $stmt->execute([$newTotalMembers, $newTotalCollection, $groupId]);

            // Remove any month_bidding_status entries beyond the new member count
            $stmt = $pdo->prepare("DELETE FROM month_bidding_status WHERE group_id = ? AND month_number > ?");
            $stmt->execute([$groupId, $newTotalMembers]);
            
            // Also remove any member_payments entries for months beyond the new member count
            $stmt = $pdo->prepare("DELETE FROM member_payments WHERE group_id = ? AND month_number > ?");
            $stmt->execute([$groupId, $newTotalMembers]);
            
            // Remove any monthly_bids entries for months beyond the new member count
            $stmt = $pdo->prepare("DELETE FROM monthly_bids WHERE group_id = ? AND month_number > ?");
            $stmt->execute([$groupId, $newTotalMembers]);

            $pdo->commit();
            $success = "Member '{$memberToRemove['member_name']}' has been successfully removed from the group (account preserved). All related data has been cleaned up and remaining members have been renumbered.";

            // Refresh data
            $group = getGroupById($groupId);
            $members = getGroupMembers($groupId);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to remove member: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid member selection.';
    }
}

// Handle member credential updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member'])) {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if ($memberId && $username) {
        try {
            $pdo = getDB();
            
            // First verify the member is in this group
            $stmt = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND member_id = ? AND status = 'active'");
            $stmt->execute([$groupId, $memberId]);
            if (!$stmt->fetch()) {
                throw new Exception("Member not found in this group.");
            }

            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE members SET username = ?, password = ?, phone = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $hashedPassword, $phone, $email, $memberId]);
            } else {
                $stmt = $pdo->prepare("UPDATE members SET username = ?, phone = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $phone, $email, $memberId]);
            }
            
            $success = 'Member credentials updated successfully!';
            $members = getGroupMembers($groupId); // Refresh data
            
        } catch (Exception $e) {
            $error = 'Failed to update member credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - <?= APP_NAME ?></title>
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
            <h1>Manage Members - <?= htmlspecialchars($group['group_name']) ?></h1>
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

        <!-- Add New Member Section -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus"></i> Add New Member
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <?php if (!empty($availableMembers)): ?>
                                <label for="member_name" class="form-label">Select Member</label>
                                <select class="form-select" name="member_name" id="member_name" required onchange="updateMemberInput(this)">
                                    <option value="">Select existing member</option>
                                    <?php foreach ($availableMembers as $memberName): ?>
                                        <option value="<?= htmlspecialchars($memberName) ?>">
                                            <?= htmlspecialchars($memberName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__NEW__">+ Add new member</option>
                                </select>
                                <input type="text" class="form-control mt-2" name="new_member_name"
                                       placeholder="Enter new member name" style="display: none;" id="new_member_input">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    Only showing members not already in this group
                                </small>
                            <?php else: ?>
                                <label for="member_name" class="form-label">Member Name</label>
                                <input type="text" class="form-control" name="member_name"
                                       placeholder="Enter new member name" required>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    No existing members available to add (all existing members may already be in this group)
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" name="add_member" class="btn btn-success w-100">
                                <i class="fas fa-plus"></i> Add Member
                            </button>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            New members will get auto-generated usernames and default password "member123"
                        </small>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> Member Login Credentials
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Members can use these credentials to login and view their BC status. Default password for new members is "member123".
                </div>

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
                                    <td><?= htmlspecialchars($member['member_name']) ?></td>
                                    <td>
                                        <code><?= htmlspecialchars($member['username'] ?: 'Not Set') ?></code>
                                    </td>
                                    <td><?= htmlspecialchars($member['phone'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($member['email'] ?: '-') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $member['id'] ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#removeModal<?= $member['id'] ?>">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?= $member['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Member Credentials</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Member Name</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($member['member_name']) ?>" readonly>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="username<?= $member['id'] ?>" class="form-label">Username *</label>
                                                        <input type="text" class="form-control" id="username<?= $member['id'] ?>" name="username" 
                                                               value="<?= htmlspecialchars($member['username'] ?: strtolower(str_replace(' ', '', $member['member_name']))) ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="new_password<?= $member['id'] ?>" class="form-label">New Password</label>
                                                        <input type="password" class="form-control" id="new_password<?= $member['id'] ?>" name="new_password" 
                                                               placeholder="Leave blank to keep current password">
                                                        <div class="form-text">Default password is "member123"</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="phone<?= $member['id'] ?>" class="form-label">Phone</label>
                                                        <input type="tel" class="form-control" id="phone<?= $member['id'] ?>" name="phone" 
                                                               value="<?= htmlspecialchars($member['phone'] ?: '') ?>">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="email<?= $member['id'] ?>" class="form-label">Email</label>
                                                        <input type="email" class="form-control" id="email<?= $member['id'] ?>" name="email" 
                                                               value="<?= htmlspecialchars($member['email'] ?: '') ?>">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="update_member" class="btn btn-primary">
                                                        <i class="fas fa-save"></i> Update
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Remove Confirmation Modal -->
                                <div class="modal fade" id="removeModal<?= $member['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-exclamation-triangle"></i> Remove Member
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="member_id" value="<?= $member['id'] ?>">

                                                    <div class="alert alert-danger">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        <strong>Warning!</strong> This action cannot be undone.
                                                    </div>

                                                    <p>You are about to remove <strong><?= htmlspecialchars($member['member_name']) ?></strong> from this group.</p>

                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle"></i>
                                                        <strong>What will happen:</strong>
                                                        <ul class="mb-0 mt-2">
                                                            <li><strong>Member will be removed from this group only (account will NOT be deleted)</strong></li>
                                                            <li>Member can be added back to this or other groups later</li>
                                                            <li>All related group data will be cleaned up from mapped tables</li>
                                                            <li>All remaining members will be renumbered</li>
                                                            <li>Group total member count will be updated</li>
                                                            <li><strong>Member cannot be removed if they have any financial activity (payments, bids, selections)</strong></li>
                                                        </ul>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label for="confirm_name<?= $member['id'] ?>" class="form-label">
                                                            <strong>Type the member name to confirm removal:</strong>
                                                        </label>
                                                        <input type="text" class="form-control" id="confirm_name<?= $member['id'] ?>"
                                                               name="confirm_name" placeholder="<?= htmlspecialchars($member['member_name']) ?>" required>
                                                        <div class="form-text">Type exactly: <code><?= htmlspecialchars($member['member_name']) ?></code></div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="remove_member" class="btn btn-danger">
                                                        <i class="fas fa-trash"></i> Remove Member
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Member Login Instructions -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-question-circle"></i> Member Login Instructions
                </h6>
            </div>
            <div class="card-body">
                <ol>
                    <li>Share the member login URL: <code><?= $_SERVER['HTTP_HOST'] ?>/member_login.php</code></li>
                    <li>Each member uses their assigned username and password</li>
                    <li>Members can view their payment history, group status, and profit/loss</li>
                    <li>Members have read-only access - they cannot modify any data</li>
                    <li>Default password for all new members is: <code>member123</code></li>
                </ol>
                
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Security Note:</strong> Advise members to change their passwords after first login. You can update passwords here as needed.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateMemberInput(selectElement) {
            const newMemberInput = document.getElementById('new_member_input');

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
    </script>
</body>
</html>
