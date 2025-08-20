<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$groupId = (int)($_GET['group_id'] ?? 0);
if (!$groupId) {
    setMessage('Group ID is required.', 'error');
    redirect('index.php');
}

$group = getGroupById($groupId);
if (!$group) {
    setMessage('Group not found.', 'error');
    redirect('index.php');
}

$message = '';
$error = '';

// Get current active month first (needed for validation)
$currentActiveMonth = getCurrentActiveMonthNumber($groupId);

// Handle admin override of random pick
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['override_random_pick'])) {
        $monthNumber = (int)$_POST['month_number'];
        $selectedMemberId = (int)$_POST['selected_member_id'];
        
        if (!$monthNumber || !$selectedMemberId) {
            $error = 'Month number and member selection are required.';
        } elseif ($currentActiveMonth && $monthNumber != $currentActiveMonth) {
            $error = "Admin overrides are only allowed for the current active month ($currentActiveMonth). Month $monthNumber is not available.";
        } else {
            try {
                $pdo = getDB();

                // Check if there's already a monthly bid for this month (amount already taken)
                $stmt = $pdo->prepare("SELECT * FROM monthly_bids WHERE group_id = ? AND month_number = ?");
                $stmt->execute([$groupId, $monthNumber]);
                $existingBid = $stmt->fetch();

                if ($existingBid) {
                    $error = "Cannot override Month $monthNumber - the amount has already been taken by {$existingBid['member_name']}.";
                } else {
                    // Check if there's already a random pick for this month
                    $stmt = $pdo->prepare("SELECT * FROM random_picks WHERE group_id = ? AND month_number = ?");
                    $stmt->execute([$groupId, $monthNumber]);
                    $existingPick = $stmt->fetch();

                    if ($existingPick) {
                        // Update existing random pick with admin override
                        $stmt = $pdo->prepare("
                            UPDATE random_picks
                            SET admin_override_member_id = ?,
                                admin_override_by = ?,
                                admin_override_at = NOW(),
                                updated_at = NOW()
                            WHERE group_id = ? AND month_number = ?
                        ");
                        $stmt->execute([$selectedMemberId, $_SESSION['admin_id'], $groupId, $monthNumber]);

                        $message = "Admin override applied successfully for Month $monthNumber!";
                    } else {
                        $error = "No random pick found for Month $monthNumber. Please use random pick first.";
                    }
                }

            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['remove_override'])) {
        $monthNumber = (int)$_POST['month_number'];

        if ($currentActiveMonth && $monthNumber != $currentActiveMonth) {
            $error = "Admin overrides can only be removed for the current active month ($currentActiveMonth). Month $monthNumber is not available.";
        } else {
            try {
                $pdo = getDB();

            // Check if there's already a monthly bid for this month (amount already taken)
            $stmt = $pdo->prepare("SELECT * FROM monthly_bids WHERE group_id = ? AND month_number = ?");
            $stmt->execute([$groupId, $monthNumber]);
            $existingBid = $stmt->fetch();

            if ($existingBid) {
                $error = "Cannot remove override for Month $monthNumber - the amount has already been taken.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE random_picks
                    SET admin_override_member_id = NULL,
                        admin_override_by = NULL,
                        admin_override_at = NULL,
                        updated_at = NOW()
                    WHERE group_id = ? AND month_number = ?
                ");
                $stmt->execute([$groupId, $monthNumber]);

                $message = "Admin override removed for Month $monthNumber!";
            }

            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get all random picks for this group
$randomPicks = getRandomPicks($groupId);
$members = getGroupMembers($groupId);
$monthlyBids = getMonthlyBids($groupId);

// Current active month already retrieved above

// Separate random picks into available and completed
$availableMonthsForOverride = [];
$completedMonths = [];
$disabledMonths = [];

foreach ($randomPicks as $pick) {
    $hasBid = array_filter($monthlyBids, fn($bid) => $bid['month_number'] == $pick['month_number']);

    if ($hasBid) {
        // Month has bid - completed
        $completedMonths[] = $pick;
    } elseif ($currentActiveMonth && $pick['month_number'] == $currentActiveMonth) {
        // Current active month - available for override
        $availableMonthsForOverride[] = $pick;
    } else {
        // Past or future month - disabled
        $disabledMonths[] = $pick;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Random Picks - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-users-cog text-warning me-2"></i><?= APP_NAME ?> - Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="view_group.php?id=<?= $groupId ?>">
                    <i class="fas fa-arrow-left"></i> Back to Group
                </a>
                <a class="nav-link" href="index.php?logout=1">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-dice text-warning me-2"></i>Manage Random Picks - <?= htmlspecialchars($group['group_name']) ?></h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Available Months for Override -->
            <?php if (!empty($availableMonthsForOverride)): ?>
                <div class="col-12 mb-4">
                    <h4 class="text-success"><i class="fas fa-edit me-2"></i>Available for Override</h4>
                </div>
                <?php foreach ($availableMonthsForOverride as $pick): ?>
                    <?php
                    // Get member details
                    $randomMember = array_filter($members, fn($m) => $m['id'] == $pick['selected_member_id']);
                    $randomMember = reset($randomMember);
                    
                    $overrideMember = null;
                    if ($pick['admin_override_member_id']) {
                        $overrideMember = array_filter($members, fn($m) => $m['id'] == $pick['admin_override_member_id']);
                        $overrideMember = reset($overrideMember);
                    }
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i>Month <?= $pick['month_number'] ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>🎲 Random Pick:</strong><br>
                                    <span class="text-primary"><?= htmlspecialchars($randomMember['member_name']) ?></span>
                                    <small class="text-muted d-block">
                                        Picked on <?= date('d/m/Y H:i', strtotime($pick['picked_at'])) ?>
                                    </small>
                                </div>
                                
                                <?php if ($overrideMember): ?>
                                    <div class="mb-3">
                                        <strong>👨‍💼 Admin Override:</strong><br>
                                        <span class="text-success"><?= htmlspecialchars($overrideMember['member_name']) ?></span>
                                        <small class="text-muted d-block">
                                            Override on <?= date('d/m/Y H:i', strtotime($pick['admin_override_at'])) ?>
                                        </small>
                                    </div>
                                    
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="month_number" value="<?= $pick['month_number'] ?>">
                                        <button type="submit" name="remove_override" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Remove admin override and revert to random pick?')">
                                            <i class="fas fa-undo"></i> Remove Override
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="month_number" value="<?= $pick['month_number'] ?>">
                                        <div class="mb-3">
                                            <label class="form-label"><strong>👨‍💼 Admin Override:</strong></label>
                                            <select name="selected_member_id" class="form-select" required>
                                                <option value="">Select member to override...</option>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?= $member['id'] ?>">
                                                        #<?= $member['member_number'] ?> - <?= htmlspecialchars($member['member_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="override_random_pick" class="btn btn-success btn-sm">
                                            <i class="fas fa-user-edit"></i> Apply Override
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Completed Months (Disabled) -->
            <?php if (!empty($completedMonths)): ?>
                <div class="col-12 mb-4 mt-4">
                    <h4 class="text-muted"><i class="fas fa-check-circle me-2"></i>Completed (Amount Already Taken)</h4>
                </div>
                <?php foreach ($completedMonths as $pick): ?>
                    <?php
                    // Get member details
                    $randomMember = array_filter($members, fn($m) => $m['id'] == $pick['selected_member_id']);
                    $randomMember = reset($randomMember);

                    $overrideMember = null;
                    if ($pick['admin_override_member_id']) {
                        $overrideMember = array_filter($members, fn($m) => $m['id'] == $pick['admin_override_member_id']);
                        $overrideMember = reset($overrideMember);
                    }

                    // Get who clicked the random pick button
                    $pickedByInfo = null;
                    if ($pick['picked_by'] && $pick['picked_by_type']) {
                        if ($pick['picked_by_type'] === 'admin') {
                            // Get admin info
                            $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
                            $stmt->execute([$pick['picked_by']]);
                            $adminInfo = $stmt->fetch();
                            if ($adminInfo) {
                                $pickedByInfo = [
                                    'name' => $adminInfo['username'],
                                    'type' => 'Admin'
                                ];
                            }
                        } elseif ($pick['picked_by_type'] === 'member') {
                            // Get member info
                            $memberInfo = array_filter($members, fn($m) => $m['id'] == $pick['picked_by']);
                            $memberInfo = reset($memberInfo);
                            if ($memberInfo) {
                                $pickedByInfo = [
                                    'name' => $memberInfo['member_name'],
                                    'type' => 'Member'
                                ];
                            }
                        }
                    }

                    // Get the bid details
                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $pick['month_number']);
                    $bid = reset($bid);
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-check me-2"></i>Month <?= $pick['month_number'] ?>
                                    <span class="badge bg-light text-success ms-2">COMPLETED</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>🎲 Random Pick:</strong><br>
                                    <span class="text-primary"><?= htmlspecialchars($randomMember['member_name']) ?></span>
                                    <small class="text-muted d-block">
                                        Picked on <?= date('d/m/Y H:i', strtotime($pick['picked_at'])) ?>
                                        <?php if ($pickedByInfo): ?>
                                            <br>by <?= htmlspecialchars($pickedByInfo['name']) ?> (<?= $pickedByInfo['type'] ?>)
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <?php if ($overrideMember): ?>
                                    <div class="mb-3">
                                        <strong>👨‍💼 Admin Override:</strong><br>
                                        <span class="text-success"><?= htmlspecialchars($overrideMember['member_name']) ?></span>
                                        <small class="text-muted d-block">
                                            Override on <?= date('d/m/Y H:i', strtotime($pick['admin_override_at'])) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <strong>💰 Final Winner:</strong><br>
                                    <span class="text-success fw-bold"><?= htmlspecialchars($bid['member_name']) ?></span>
                                    <small class="text-muted d-block">
                                        Amount: <?= formatCurrency($bid['net_payable']) ?>
                                        <?php if ($bid['payment_date']): ?>
                                            <br>Paid on: <?= date('d/m/Y', strtotime($bid['payment_date'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-lock me-2"></i>
                                    <strong>Amount Already Taken</strong><br>
                                    <small>This month cannot be modified as the amount has been distributed.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Disabled Months (Past/Future) -->
            <?php if (!empty($disabledMonths)): ?>
                <div class="col-12 mb-4 mt-4">
                    <h4 class="text-warning"><i class="fas fa-clock me-2"></i>Disabled (Past/Future Months)</h4>
                </div>
                <?php foreach ($disabledMonths as $pick): ?>
                    <?php
                    $randomMember = array_filter($members, fn($m) => $m['id'] == $pick['selected_member_id']);
                    $randomMember = reset($randomMember);

                    $overrideMember = null;
                    if ($pick['admin_override_member_id']) {
                        $overrideMember = array_filter($members, fn($m) => $m['id'] == $pick['admin_override_member_id']);
                        $overrideMember = reset($overrideMember);
                    }

                    $isPast = $currentActiveMonth && $pick['month_number'] < $currentActiveMonth;
                    $isFuture = $currentActiveMonth && $pick['month_number'] > $currentActiveMonth;
                    ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-warning">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-times me-2"></i>Month <?= $pick['month_number'] ?>
                                    <span class="badge bg-dark ms-2">
                                        <?= $isPast ? 'PAST' : 'FUTURE' ?>
                                    </span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>🎲 Random Pick:</strong><br>
                                    <span class="text-primary"><?= htmlspecialchars($randomMember['member_name']) ?></span>
                                    <small class="text-muted d-block">
                                        Picked on <?= date('d/m/Y H:i', strtotime($pick['picked_at'])) ?>
                                    </small>
                                </div>

                                <?php if ($overrideMember): ?>
                                    <div class="mb-3">
                                        <strong>👨‍💼 Admin Override:</strong><br>
                                        <span class="text-success"><?= htmlspecialchars($overrideMember['member_name']) ?></span>
                                        <small class="text-muted d-block">
                                            Override on <?= date('d/m/Y H:i', strtotime($pick['admin_override_at'])) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong><?= $isPast ? 'Past Month' : 'Future Month' ?></strong><br>
                                    <small>
                                        <?php if ($isPast): ?>
                                            This month has passed. Only current month (<?= $currentActiveMonth ?>) can be modified.
                                        <?php else: ?>
                                            This month is not active yet. Only current month (<?= $currentActiveMonth ?>) can be modified.
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- No Random Picks Message -->
            <?php if (empty($availableMonthsForOverride) && empty($completedMonths) && empty($disabledMonths)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>No random picks found.</strong><br>
                        Random picks will appear here after members use the 🎲 Pick feature for the current month (<?= $currentActiveMonth ?: 'none' ?>).
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>All Random Picks Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($randomPicks)): ?>
                            <p class="text-muted">No random picks have been made yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Random Pick</th>
                                            <th>Picked By</th>
                                            <th>Admin Override</th>
                                            <th>Final Selection</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($randomPicks as $pick): ?>
                                            <?php
                                            $randomMember = array_filter($members, fn($m) => $m['id'] == $pick['selected_member_id']);
                                            $randomMember = reset($randomMember);
                                            
                                            $overrideMember = null;
                                            if ($pick['admin_override_member_id']) {
                                                $overrideMember = array_filter($members, fn($m) => $m['id'] == $pick['admin_override_member_id']);
                                                $overrideMember = reset($overrideMember);
                                            }
                                            
                                            $hasBid = array_filter($monthlyBids, fn($bid) => $bid['month_number'] == $pick['month_number']);
                                            $finalMember = $overrideMember ?: $randomMember;

                                            // Get who clicked the random pick button for table
                                            $pickedByInfo = null;
                                            if ($pick['picked_by'] && $pick['picked_by_type']) {
                                                if ($pick['picked_by_type'] === 'admin') {
                                                    $stmt = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
                                                    $stmt->execute([$pick['picked_by']]);
                                                    $adminInfo = $stmt->fetch();
                                                    if ($adminInfo) {
                                                        $pickedByInfo = [
                                                            'name' => $adminInfo['username'],
                                                            'type' => 'Admin'
                                                        ];
                                                    }
                                                } elseif ($pick['picked_by_type'] === 'member') {
                                                    $memberInfo = array_filter($members, fn($m) => $m['id'] == $pick['picked_by']);
                                                    $memberInfo = reset($memberInfo);
                                                    if ($memberInfo) {
                                                        $pickedByInfo = [
                                                            'name' => $memberInfo['member_name'],
                                                            'type' => 'Member'
                                                        ];
                                                    }
                                                }
                                            }
                                            ?>
                                            <tr>
                                                <td><strong>Month <?= $pick['month_number'] ?></strong></td>
                                                <td>
                                                    🎲 <?= htmlspecialchars($randomMember['member_name']) ?>
                                                    <small class="text-muted d-block">
                                                        <?= date('d/m/Y', strtotime($pick['picked_at'])) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($pickedByInfo): ?>
                                                        <span class="badge bg-<?= $pickedByInfo['type'] === 'Admin' ? 'primary' : 'info' ?>">
                                                            <?= htmlspecialchars($pickedByInfo['name']) ?>
                                                        </span>
                                                        <small class="text-muted d-block"><?= $pickedByInfo['type'] ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($overrideMember): ?>
                                                        👨‍💼 <?= htmlspecialchars($overrideMember['member_name']) ?>
                                                        <small class="text-muted d-block">
                                                            <?= date('d/m/Y', strtotime($pick['admin_override_at'])) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong class="<?= $overrideMember ? 'text-success' : 'text-primary' ?>">
                                                        <?= htmlspecialchars($finalMember['member_name']) ?>
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php if ($hasBid): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>Amount Taken
                                                        </span>
                                                        <small class="text-muted d-block">
                                                            <?= formatCurrency($hasBid[0]['net_payable']) ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-edit me-1"></i>Available for Override
                                                        </span>
                                                    <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
