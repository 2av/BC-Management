<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('member');

$member = getCurrentMember();
$currentGroupId = $_SESSION['group_id'];

// Get all groups the member belongs to
$memberGroups = getMemberGroups($member['id']);

// Determine which group to display
$selectedGroupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : $currentGroupId;

// Verify member has access to selected group
$hasAccess = false;
foreach ($memberGroups as $memberGroup) {
    if ($memberGroup['id'] == $selectedGroupId) {
        $hasAccess = true;
        break;
    }
}

if (!$hasAccess) {
    $selectedGroupId = $currentGroupId;
}

$group = getGroupById($selectedGroupId);
$members = getGroupMembers($selectedGroupId);
$monthlyBids = getMonthlyBids($selectedGroupId);
$memberPayments = getMemberPayments($selectedGroupId);
$memberSummary = getMemberSummary($selectedGroupId);

// Get random picks for this group
$randomPicks = getRandomPicks($selectedGroupId);

// Get current active month
$currentActiveMonth = getCurrentActiveMonthNumber($selectedGroupId);

// Pending members for spin wheel — haven't received any amount yet (no monthly_bids win)
$pendingMembersForSpin = [];
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT DISTINCT taken_by_member_id as member_id
    FROM monthly_bids
    WHERE group_id = ? AND taken_by_member_id IS NOT NULL
");
$stmt->execute([$selectedGroupId]);
$receivedMemberIds = array_column($stmt->fetchAll(), 'member_id');

foreach ($members as $memberOption) {
    if (!in_array($memberOption['id'], $receivedMemberIds)) {
        $pendingMembersForSpin[] = $memberOption;
    }
}

// Organize payments by member and month
$paymentsMatrix = [];
foreach ($memberPayments as $payment) {
    $paymentsMatrix[$payment['member_id']][$payment['month_number']] = $payment;
}

// Organize summary by member
$summaryByMember = [];
foreach ($memberSummary as $summary) {
    $summaryByMember[$summary['member_id']] = $summary;
}

// Find current member's ID in the selected group
$currentMemberInGroup = null;
foreach ($members as $memberInGroup) {
    if ($memberInGroup['member_name'] == $member['member_name']) {
        $currentMemberInGroup = $memberInGroup;
        break;
    }
}

// Check if current member is akhilesh (case-insensitive)
$isAkhilesh = (strtolower($member['member_name']) === 'akhilesh' || strtolower($member['username'] ?? '') === 'akhilesh');

// Get members who haven't received any amount (for Akhilesh custom pick tool)
$membersWhoHaventReceived = $pendingMembersForSpin;

// Handle saving selected member for random pick
$savedSelectedMemberId = null;
if ($isAkhilesh && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selected_member'])) {
    $selectedMemberId = (int)($_POST['selected_member'] ?? 0);
    if ($selectedMemberId > 0) {
        $_SESSION['akhilesh_selected_member_' . $selectedGroupId] = $selectedMemberId;
        setMessage('Selected member saved successfully!', 'success');
        redirect('group_view.php?group_id=' . $selectedGroupId);
    } else {
        setMessage('Please select a member.', 'error');
        redirect('group_view.php?group_id=' . $selectedGroupId);
    }
}

// Get saved selected member from session
$savedSelectedMemberId = null;
$savedSelectedMemberName = '';
if ($isAkhilesh && isset($_SESSION['akhilesh_selected_member_' . $selectedGroupId])) {
    $savedSelectedMemberId = $_SESSION['akhilesh_selected_member_' . $selectedGroupId];
    foreach ($pendingMembersForSpin as $m) {
        if ($m['id'] == $savedSelectedMemberId) {
            $savedSelectedMemberName = $m['member_name'];
            break;
        }
    }
}

// Set page title for the header
$page_title = htmlspecialchars($group['group_name']) . ' - Group View';

// Include the member header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
        /* Mobile-first responsive table styling */
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }

        .spreadsheet-table {
            font-size: 11px;
            margin-bottom: 0;
            min-width: 800px; /* Ensure minimum width for proper display */
        }

        .spreadsheet-table th,
        .spreadsheet-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap; /* Prevent text wrapping */
            min-width: 80px; /* Minimum column width */
        }

        /* Sticky first column for better mobile experience */
        .spreadsheet-table .sticky-col {
            position: sticky;
            left: 0;
            background-color: #f8f9fa;
            z-index: 10;
            border-right: 2px solid #000;
            min-width: 120px;
            max-width: 120px;
            width: 120px;
            text-align: left;
            padding-left: 8px;
            padding-right: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .header-blue {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        .header-green {
            background-color: #70AD47;
            color: white;
            font-weight: bold;
        }
        .header-orange {
            background-color: #C65911;
            color: white;
            font-weight: bold;
        }
        .cell-yellow {
            background-color: #FFFF00;
        }
        .my-row {
            background-color: #E8F5E8 !important;
            font-weight: bold;
        }
        .text-right {
            text-align: right !important;
        }
        .text-left {
            text-align: left !important;
        }

        /* Mobile-specific enhancements */
        @media (max-width: 768px) {
            .spreadsheet-table {
                font-size: 10px;
            }

            .spreadsheet-table th,
            .spreadsheet-table td {
                padding: 3px 4px;
                min-width: 70px;
            }

            .sticky-col {
                min-width: 100px !important;
                max-width: 100px !important;
                width: 100px !important;
                font-size: 9px;
                padding-left: 6px !important;
                padding-right: 6px !important;
            }

            .container {
                padding-left: 5px;
                padding-right: 5px;
            }

            .table-responsive-custom {
                border-radius: 0.25rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
        }

        /* Scroll indicator for mobile */
        .scroll-indicator {
            display: none;
            background: linear-gradient(90deg, #28a745, #20c997);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 10px;
            text-align: center;
            animation: pulse 2s infinite;
        }

        @media (max-width: 768px) {
            .scroll-indicator {
                display: block;
            }
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* Better mobile navigation */
        .mobile-nav-hint {
            display: none;
            background: #e8f5e8;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #155724;
        }

        @media (max-width: 768px) {
            .mobile-nav-hint {
                display: block;
            }
        }

        /* Tooltip for truncated names */
        .name-tooltip {
            position: relative;
            cursor: pointer;
        }

        .name-tooltip:hover::after,
        .name-tooltip:focus::after {
            content: attr(data-full-name);
            position: absolute;
            bottom: 100%;
            left: 0;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .name-tooltip:hover::before,
        .name-tooltip:focus::before {
            content: '';
            position: absolute;
            bottom: 95%;
            left: 10px;
            border: 5px solid transparent;
            border-top-color: #333;
            z-index: 1000;
        }

        /* Custom Random Pick Feature Styles */
        #memberSelect {
            border: 2px solid #dee2e6;
        }

        #memberSelect option:checked {
            background-color: #0d6efd;
            color: white;
        }

        .random-pick-btn-saved {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .random-pick-btn-saved:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
        }

        /* Custom Pick Tool Toggle Styles */
        #toggleCustomPickTool {
            transition: all 0.3s ease;
        }

        #toggleCustomPickTool:hover {
            transform: scale(1.05);
        }

        #customPickToolContainer {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #customPickToolContainer.hide {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
        }

        /* Random Pick Button Styles */
        .random-pick-btn {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }

        .random-pick-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.4);
        }

        .random-pick-btn:disabled {
            animation: none;
            opacity: 0.6;
        }

        /* Invoice Button Styles */
        .invoice-btn {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.3s ease;
            border: 1px solid #007bff;
            color: #007bff;
            background: transparent;
        }

        .invoice-btn:hover {
            background-color: #007bff;
            color: white;
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
        }

        .invoice-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .invoice-btn i {
            font-size: 14px;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }

        /* Spin Wheel Modal */
        .spin-wheel-modal .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .spin-wheel-container {
            position: relative;
            width: 320px;
            height: 320px;
            margin: 0 auto;
        }

        @media (max-width: 576px) {
            .spin-wheel-container {
                width: 280px;
                height: 280px;
            }
        }

        .spin-wheel-canvas {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .spin-wheel-pointer {
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 14px solid transparent;
            border-right: 14px solid transparent;
            border-top: 28px solid #dc3545;
            z-index: 10;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .spin-wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #fff, #f0f0f0);
            border-radius: 50%;
            border: 3px solid #333;
            z-index: 5;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .spin-result-banner {
            display: none;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            text-align: center;
            margin-top: 16px;
            animation: fadeIn 0.5s ease-in-out;
        }

        .spin-result-banner.show {
            display: block;
        }

        .spin-result-banner .winner-name {
            font-size: 1.4rem;
            font-weight: bold;
        }

        #spinWheelBtn {
            transition: all 0.3s ease;
        }

        #spinWheelBtn:hover {
            transform: scale(1.05);
        }

        .spin-confirm-modal .modal-header {
            border-bottom: none;
        }

        .spin-confirm-modal .confirm-winner-box {
            background: #f8f9fa;
            border: 2px dashed #28a745;
            border-radius: 10px;
            padding: 16px;
            margin: 12px 0;
            font-size: 1.25rem;
            font-weight: bold;
            color: #28a745;
        }
    </style>

<!-- Page content starts here -->
        <!-- Group Header Info -->
        <div class="row mb-3">
            <div class="col-12">
                <h3><?= htmlspecialchars($group['group_name']) ?> - Complete View</h3>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    This is a read-only view of your BC group. Your row is highlighted in green.
                </div>

                <!-- Mobile Navigation Hint -->
                <div class="mobile-nav-hint">
                    <i class="fas fa-mobile-alt"></i> <strong>Mobile Tip:</strong> Swipe left/right on tables to see all columns. Your data is highlighted in green.
                </div>
            </div>
        </div>

        <!-- Custom Random Pick Toggle Icon for Akhilesh -->
        <?php if ($isAkhilesh && !empty($membersWhoHaventReceived)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <button type="button" class="btn btn-sm btn-outline-primary" id="toggleCustomPickTool" title="Show/Hide Custom Random Pick Tool">
                    <i class="fas fa-random me-1"></i>
                    <span id="toggleText">Show Custom Pick Tool</span>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Custom Random Pick Feature for Akhilesh -->
        <?php if ($isAkhilesh && !empty($membersWhoHaventReceived)): ?>
        <div class="row mb-4" id="customPickToolContainer" style="display: none;">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-random me-2"></i>Custom Random Pick Tool
                        </h5>
                        <button type="button" class="btn btn-sm btn-light" id="closeCustomPickTool" title="Hide Tool">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Select members who haven't received any amount yet. Click "Save" to store your selection, then use the "Pick" button in Deposit/Bid Details section.
                        </div>
                        
                        <?php if ($savedSelectedMemberId): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Saved Member:</strong> 
                            <?php 
                            $savedMember = null;
                            foreach ($membersWhoHaventReceived as $m) {
                                if ($m['id'] == $savedSelectedMemberId) {
                                    $savedMember = $m;
                                    break;
                                }
                            }
                            if ($savedMember) {
                                echo htmlspecialchars($savedMember['member_name']);
                                if ($savedMember['member_number']) {
                                    echo ' (#' . $savedMember['member_number'] . ')';
                                }
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="save_selected_member" value="1">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Select Member (who hasn't received amount):</label>
                                    <select name="selected_member" id="memberSelect" class="form-select" required>
                                        <option value="">-- Select a Member --</option>
                                        <?php foreach ($membersWhoHaventReceived as $memberOption): ?>
                                            <option value="<?= $memberOption['id'] ?>" 
                                                    <?= $savedSelectedMemberId == $memberOption['id'] ? 'selected' : '' ?>
                                                    data-member-name="<?= htmlspecialchars($memberOption['member_name']) ?>">
                                                <?= htmlspecialchars($memberOption['member_name']) ?>
                                                <?php if ($memberOption['member_number']): ?>
                                                    (#<?= $memberOption['member_number'] ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Select one member who hasn't received any amount yet
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-warning btn-lg" id="spinWheelBtn">
                                            <i class="fas fa-dharmachakra me-2"></i>Spin to Pick
                                        </button>
                                        <button type="submit" class="btn btn-success btn-lg">
                                            <i class="fas fa-save me-2"></i>Save Member
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" onclick="clearSelection()">
                                            <i class="fas fa-times me-2"></i>Clear Selection
                                        </button>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <strong>Total Available:</strong> <?= count($membersWhoHaventReceived) ?> member(s)
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($isAkhilesh && empty($membersWhoHaventReceived)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    All members have already received their amount. No members available for random pick.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Spin Wheel Modal -->
        <?php if ($currentActiveMonth && !empty($pendingMembersForSpin)): ?>
        <div class="modal fade spin-wheel-modal" id="spinWheelModal" tabindex="-1" aria-labelledby="spinWheelModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="spinWheelModalLabel">
                            <i class="fas fa-dharmachakra me-2"></i>Spin to Pick a Member
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <p class="text-muted mb-3" id="spinWheelSubtitle">Spin the wheel to randomly select a pending member.</p>
                        <div class="spin-wheel-container">
                            <div class="spin-wheel-pointer"></div>
                            <canvas id="spinWheelCanvas" class="spin-wheel-canvas" width="320" height="320"></canvas>
                            <div class="spin-wheel-center"></div>
                        </div>
                        <div class="spin-result-banner" id="spinResultBanner">
                            <div><i class="fas fa-trophy me-1"></i> Selected Winner</div>
                            <div class="winner-name" id="spinWinnerName"></div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-warning btn-lg px-4" id="spinBtn">
                            <i class="fas fa-play me-2"></i>SPIN
                        </button>
                        <button type="button" class="btn btn-success btn-lg px-4 d-none" id="useSpinResultBtn">
                            <i class="fas fa-check me-2"></i><span id="useSpinResultBtnText">Confirm</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spin Confirmation Modal -->
        <div class="modal fade spin-confirm-modal" id="spinConfirmModal" tabindex="-1" aria-labelledby="spinConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="spinConfirmModalLabel">
                            <i class="fas fa-question-circle me-2"></i><span id="spinConfirmTitle">Confirm</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p id="spinConfirmMessage" class="mb-0"></p>
                        <div class="confirm-winner-box d-none" id="spinConfirmWinnerBox">
                            <i class="fas fa-user-check me-2"></i>
                            <span id="spinConfirmWinnerName"></span>
                        </div>
                        <p id="spinConfirmSubtext" class="text-muted small mt-2 mb-0"></p>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary px-4" id="spinConfirmOkBtn">
                            <i class="fas fa-check me-2"></i>Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Group Selector -->
        <?php if (count($memberGroups) > 1): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-exchange-alt me-2"></i>Switch Group View
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="d-flex align-items-center gap-3">
                            <label for="group_id" class="form-label mb-0 fw-bold">Select Group:</label>
                            <select name="group_id" id="group_id" class="form-select" style="width: auto;" onchange="this.form.submit()">
                                <?php foreach ($memberGroups as $memberGroup): ?>
                                    <option value="<?= $memberGroup['id'] ?>"
                                            <?= $selectedGroupId == $memberGroup['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($memberGroup['group_name']) ?>
                                        <?php if ($memberGroup['id'] == $currentGroupId): ?>
                                            (Current Group)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                View complete details for any of your groups
                            </small>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Basic Info Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Swipe left/right to see all data
                </div>
                <div class="table-responsive-custom">
                    <table class="table table-bordered spreadsheet-table">
                    <tr>
                        <td class="header-blue">Total Members</td>
                        <td class="fw-bold"><?= $group['total_members'] ?></td>
                        <td class="header-blue">Monthly Contribution</td>
                        <td class="fw-bold"><?= formatCurrency($group['monthly_contribution']) ?></td>
                        <td class="header-blue">Total Monthly Contribution</td>
                        <td class="fw-bold"><?= formatCurrency($group['total_monthly_collection']) ?></td>
                    </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Deposit/Bid Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="header-green text-white p-2 mb-0">Deposit/Bid Details</h5>
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Swipe to see all months
                </div>
                <div class="table-responsive-custom">
                    <table class="table table-bordered spreadsheet-table mb-0">
                    <thead>
                        <tr class="header-orange">
                            <th class="sticky-col">Month</th>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <th>Month <?= $i ?></th>
                            <?php endfor; ?>
                            <th>Total Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold sticky-col">Taken By</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    // Check for monthly bid first
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);

                                    // Check for random pick
                                    $randomPick = array_filter($randomPicks, fn($rp) => $rp['month_number'] == $i);
                                    $randomPick = reset($randomPick);

                                    if ($bid) {
                                        $name = htmlspecialchars($bid['member_name']);
                                        if ($currentMemberInGroup && $bid['taken_by_member_id'] == $currentMemberInGroup['id']) {
                                            echo '<strong style="color: green;">' . $name . ' (You)</strong>';
                                        } else {
                                            echo $name;
                                        }
                                    } elseif ($randomPick) {
                                        // Check if there's an admin override
                                        if ($randomPick['admin_override_member_id']) {
                                            $overrideName = htmlspecialchars($randomPick['admin_override_member_name']);
                                            $randomName = htmlspecialchars($randomPick['member_name']);

                                            // If admin selected the same person as random pick, show only once
                                            if ($randomPick['admin_override_member_id'] == $randomPick['selected_member_id']) {
                                                if ($currentMemberInGroup && $randomPick['admin_override_member_id'] == $currentMemberInGroup['id']) {
                                                    echo '<strong style="color: purple;">' . $overrideName . ' (You) 🎲👨‍💼</strong>';
                                                } else {
                                                    echo '<span style="color: purple;">' . $overrideName . ' 🎲👨‍💼</span>';
                                                }
                                            } else {
                                                // Show both random pick and admin override
                                                echo '<div style="font-size: 11px; line-height: 1.2;">';
                                                echo '<div style="color: #888; text-decoration: line-through;">🎲 ' . $randomName . '</div>';
                                                if ($currentMemberInGroup && $randomPick['admin_override_member_id'] == $currentMemberInGroup['id']) {
                                                    echo '<div style="color: green;"><strong>👨‍💼 ' . $overrideName . ' (You)</strong></div>';
                                                } else {
                                                    echo '<div style="color: green;">👨‍💼 ' . $overrideName . '</div>';
                                                }
                                                echo '</div>';
                                            }
                                        } else {
                                            // Only random pick, no admin override
                                            $name = htmlspecialchars($randomPick['member_name']);
                                            if ($currentMemberInGroup && $randomPick['selected_member_id'] == $currentMemberInGroup['id']) {
                                                echo '<strong style="color: blue;">' . $name . ' (You) 🎲</strong>';
                                            } else {
                                                echo '<span style="color: blue;">' . $name . ' 🎲</span>';
                                            }
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold sticky-col">Random Pick</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    // Check if month already has a winner (bid or random pick)
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);

                                    $randomPick = array_filter($randomPicks, fn($rp) => $rp['month_number'] == $i);
                                    $randomPick = reset($randomPick);

                                    if ($bid || $randomPick) {
                                        echo '<span class="text-muted">Done</span>';
                                    } elseif ($i == 1) {
                                        // Month 1 is always taken by organizer, no random pick needed
                                        echo '<span class="text-muted" title="Month 1 is reserved for organizer">👑 Organizer</span>';
                                    } elseif ($currentActiveMonth && $i == $currentActiveMonth) {
                                        // Only show random pick button for current active month (excluding month 1)
                                        // For akhilesh, use saved selection if available
                                        $useSavedSelection = $isAkhilesh && $savedSelectedMemberId;
                                        $buttonClass = $useSavedSelection ? 'random-pick-btn-saved' : 'random-pick-btn';
                                        $savedMemberName = '';
                                        if ($useSavedSelection && $savedSelectedMemberId) {
                                            foreach ($membersWhoHaventReceived as $m) {
                                                if ($m['id'] == $savedSelectedMemberId) {
                                                    $savedMemberName = $m['member_name'];
                                                    break;
                                                }
                                            }
                                        }
                                        $buttonTitle = $useSavedSelection 
                                            ? 'Use saved member (' . htmlspecialchars($savedMemberName) . ') for Month ' . $i . ' (Current Month)' 
                                            : 'Randomly select a member for Month ' . $i . ' (Current Month)';
                                        echo '<button class="btn btn-sm btn-warning ' . $buttonClass . '"
                                                data-group-id="' . $selectedGroupId . '"
                                                data-month="' . $i . '"
                                                data-use-saved="' . ($useSavedSelection ? '1' : '0') . '"
                                                data-saved-member-id="' . ($savedSelectedMemberId ?: '') . '"
                                                data-saved-member-name="' . htmlspecialchars($savedMemberName) . '"
                                                title="' . $buttonTitle . '">
                                                🎲 Pick
                                              </button>';
                                    } elseif ($i < $currentActiveMonth) {
                                        // Past months
                                        echo '<span class="text-muted" title="Past month - cannot pick">🔒 Past</span>';
                                    } else {
                                        // Future months
                                        echo '<span class="text-muted" title="Future month - not available yet">⏳ Future</span>';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold sticky-col">Is Bid</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? $bid['is_bid'] : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold sticky-col">Bid Amount</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? formatCurrency($bid['bid_amount']) : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold sticky-col">Net Payable</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td class="cell-yellow">
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid ? formatCurrency($bid['net_payable']) : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td class="cell-yellow fw-bold">
                                <?php
                                $totalNetPayable = array_sum(array_column($monthlyBids, 'net_payable'));
                                echo formatCurrency($totalNetPayable);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold sticky-col">Gain Per Member</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    if ($bid) {
                                        echo formatCurrency($bid['gain_per_member']);
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td class="fw-bold sticky-col">Payment Date</td>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <td>
                                    <?php
                                    $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                    $bid = reset($bid);
                                    echo $bid && $bid['payment_date'] ? formatDate($bid['payment_date']) : '-';
                                    ?>
                                </td>
                            <?php endfor; ?>
                            <td>-</td>
                        </tr>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transaction Details Table -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="header-green text-white p-2 mb-0">Transaction Details</h5>
                <div class="scroll-indicator">
                    <i class="fas fa-arrows-alt-h"></i> Swipe to see all member payments
                </div>
                <div class="table-responsive-custom">
                    <table class="table table-bordered spreadsheet-table mb-0">
                    <thead>
                        <tr class="header-orange">
                            <th class="text-left sticky-col">Member Name</th>
                            <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                <th>Month <?= $i ?></th>
                            <?php endfor; ?>
                            <th>Total Paid</th>
                            <th>Given</th>
                            <th>Profit</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $memberRow): ?>
                            <tr class="<?= ($currentMemberInGroup && $memberRow['id'] == $currentMemberInGroup['id']) ? 'my-row' : '' ?>">
                                <td class="text-left fw-bold sticky-col">
                                    <span class="name-tooltip" data-full-name="<?= htmlspecialchars($memberRow['member_name']) ?>" title="<?= htmlspecialchars($memberRow['member_name']) ?>">
                                        <?= htmlspecialchars($memberRow['member_name']) ?>
                                    </span>
                                    <?php if ($currentMemberInGroup && $memberRow['id'] == $currentMemberInGroup['id']): ?>
                                        <span class="badge bg-success ms-1">You</span>
                                    <?php endif; ?>
                                </td>
                                <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                    <td>
                                        <?php
                                        $payment = $paymentsMatrix[$memberRow['id']][$i] ?? null;
                                        if ($payment) {
                                            // Show actual payment amount when payment is completed
                                            echo '<div class="text-success fw-bold">' . formatCurrency($payment['payment_amount']) . '</div>';
                                            // Show payment date below the amount in small font
                                            if ($payment['payment_date']) {
                                                echo '<div class="text-muted" style="font-size: 0.60rem; margin-top: 2px;">' . formatDate($payment['payment_date']) . '</div>';
                                            }
                                        } else {
                                            // Show expected amount or pending indicator
                                            $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                            $bid = reset($bid);
                                            if ($bid) {
                                                $expectedAmount = $bid['gain_per_member'];
                                                echo '<span class="text-muted">' . formatCurrency($expectedAmount) . '</span>';
                                            } else {
                                                echo '<span class="text-muted">-</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                <?php endfor; ?>
                                <td class="fw-bold">
                                    <?php
                                    $summary = $summaryByMember[$memberRow['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['total_paid']) : '₹0';
                                    ?>
                                </td>
                                <td class="fw-bold">
                                    <?php
                                    $summary = $summaryByMember[$memberRow['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['given_amount']) : '₹0';
                                    ?>
                                </td>
                                <td class="fw-bold <?= ($summary && $summary['profit'] >= 0) ? 'text-success' : 'text-danger' ?>">
                                    <?php
                                    $summary = $summaryByMember[$memberRow['id']] ?? null;
                                    echo $summary ? formatCurrency($summary['profit']) : '₹0';
                                    ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary invoice-btn" 
                                            data-member-id="<?= $memberRow['id'] ?>"
                                            data-member-name="<?= htmlspecialchars($memberRow['member_name']) ?>"
                                            data-group-id="<?= $selectedGroupId ?>"
                                            data-group-name="<?= htmlspecialchars($group['group_name']) ?>"
                                            title="Download Invoice for <?= htmlspecialchars($memberRow['member_name']) ?>">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>

                    <!-- Legend -->
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Legend:</strong>
                            <span class="badge bg-success me-2">Green</span> = Paid
                            <span class="badge bg-warning me-2">Yellow</span> = Pending Payment
                            <span class="text-muted me-2">"-"</span> = Amount not yet decided
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h6>Total Collected</h6>
                        <h4><?= formatCurrency(array_sum(array_column($memberSummary, 'total_paid'))) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h6>Total Given</h6>
                        <h4><?= formatCurrency(array_sum(array_column($memberSummary, 'given_amount'))) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h6>Completed Months</h6>
                        <h4><?= count($monthlyBids) ?> / <?= $group['total_members'] ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h6>Status</h6>
                        <h4><?= ucfirst($group['status']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Random Pick functionality — opens spin wheel first
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.random-pick-btn, .random-pick-btn-saved').forEach(button => {
                button.addEventListener('click', function() {
                    const groupId = this.getAttribute('data-group-id');
                    const monthNumber = this.getAttribute('data-month');

                    if (typeof window.openSpinWheelForPick === 'function') {
                        window.openSpinWheelForPick({
                            groupId: groupId,
                            monthNumber: monthNumber,
                            pickButton: this
                        });
                    } else {
                        alert('Spin wheel is not available. No pending members available for random pick.');
                    }
                });
            });
        });

        // Custom Random Pick Feature for Akhilesh - Clear selection
        function clearSelection() {
            const select = document.getElementById('memberSelect');
            select.value = '';
        }

        // Toggle Custom Pick Tool visibility
        <?php if ($isAkhilesh && !empty($membersWhoHaventReceived)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleCustomPickTool');
            const toolContainer = document.getElementById('customPickToolContainer');
            const closeBtn = document.getElementById('closeCustomPickTool');
            const toggleText = document.getElementById('toggleText');

            if (toggleBtn && toolContainer) {
                // Toggle button click
                toggleBtn.addEventListener('click', function() {
                    if (toolContainer.style.display === 'none') {
                        toolContainer.style.display = 'block';
                        toolContainer.classList.remove('hide');
                        toggleText.textContent = 'Hide Custom Pick Tool';
                        toggleBtn.classList.add('btn-primary');
                        toggleBtn.classList.remove('btn-outline-primary');
                    } else {
                        toolContainer.classList.add('hide');
                        setTimeout(() => {
                            toolContainer.style.display = 'none';
                        }, 300);
                        toggleText.textContent = 'Show Custom Pick Tool';
                        toggleBtn.classList.remove('btn-primary');
                        toggleBtn.classList.add('btn-outline-primary');
                    }
                });

                // Close button click
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        toolContainer.classList.add('hide');
                        setTimeout(() => {
                            toolContainer.style.display = 'none';
                        }, 300);
                        toggleText.textContent = 'Show Custom Pick Tool';
                        toggleBtn.classList.remove('btn-primary');
                        toggleBtn.classList.add('btn-outline-primary');
                    });
                }
            }
        });
        <?php endif; ?>

        // Spin Wheel Feature
        <?php if ($currentActiveMonth && !empty($pendingMembersForSpin)): ?>
        (function() {
            const pendingMembers = <?= json_encode(array_map(function($m) {
                return [
                    'id' => $m['id'],
                    'name' => $m['member_name'],
                    'number' => $m['member_number'] ?? null
                ];
            }, $pendingMembersForSpin)) ?>;

            const savedCustomPickMemberId = <?= $savedSelectedMemberId ? (int)$savedSelectedMemberId : 'null' ?>;
            const savedCustomPickMemberName = <?= json_encode($savedSelectedMemberName) ?>;

            const wheelColors = [
                '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
                '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
                '#F8B500', '#00CED1', '#FF69B4', '#32CD32', '#FF8C00'
            ];

            let spinMembers = [];
            let spinMode = 'pick';
            let pickContext = null;
            let predeterminedMemberId = null;
            let currentRotation = 0;
            let isSpinning = false;
            let selectedWinner = null;

            const canvas = document.getElementById('spinWheelCanvas');
            const ctx = canvas.getContext('2d');
            const spinBtn = document.getElementById('spinBtn');
            const useResultBtn = document.getElementById('useSpinResultBtn');
            const useResultBtnText = document.getElementById('useSpinResultBtnText');
            const resultBanner = document.getElementById('spinResultBanner');
            const winnerNameEl = document.getElementById('spinWinnerName');
            const spinSubtitle = document.getElementById('spinWheelSubtitle');
            const spinModal = document.getElementById('spinWheelModal');
            const spinWheelBtn = document.getElementById('spinWheelBtn');
            const confirmModal = document.getElementById('spinConfirmModal');
            const confirmTitle = document.getElementById('spinConfirmTitle');
            const confirmMessage = document.getElementById('spinConfirmMessage');
            const confirmWinnerBox = document.getElementById('spinConfirmWinnerBox');
            const confirmWinnerName = document.getElementById('spinConfirmWinnerName');
            const confirmSubtext = document.getElementById('spinConfirmSubtext');
            const confirmOkBtn = document.getElementById('spinConfirmOkBtn');

            let confirmCallback = null;
            const spinWheelModalInstance = new bootstrap.Modal(spinModal);
            const spinConfirmModalInstance = new bootstrap.Modal(confirmModal);

            function showSpinConfirm(options) {
                confirmTitle.textContent = options.title || 'Confirm';
                confirmMessage.textContent = options.message || '';
                confirmSubtext.textContent = options.subtext || '';

                if (options.winnerName) {
                    confirmWinnerBox.classList.remove('d-none');
                    confirmWinnerName.textContent = options.winnerName;
                } else {
                    confirmWinnerBox.classList.add('d-none');
                    confirmWinnerName.textContent = '';
                }

                confirmOkBtn.className = 'btn px-4 ' + (options.okClass || 'btn-primary');
                confirmOkBtn.innerHTML = '<i class="fas fa-check me-2"></i>' + (options.okText || 'Confirm');
                confirmCallback = options.onConfirm || null;
                spinConfirmModalInstance.show();
            }

            confirmOkBtn.addEventListener('click', function() {
                spinConfirmModalInstance.hide();
                if (typeof confirmCallback === 'function') {
                    confirmCallback();
                }
                confirmCallback = null;
            });

            function drawWheel(rotation) {
                if (!spinMembers.length) return;

                const size = canvas.width;
                const center = size / 2;
                const radius = center - 4;
                const sliceAngle = (2 * Math.PI) / spinMembers.length;

                ctx.clearRect(0, 0, size, size);
                ctx.save();
                ctx.translate(center, center);
                ctx.rotate(rotation);

                spinMembers.forEach((member, i) => {
                    const startAngle = i * sliceAngle;
                    const endAngle = startAngle + sliceAngle;

                    ctx.beginPath();
                    ctx.moveTo(0, 0);
                    ctx.arc(0, 0, radius, startAngle, endAngle);
                    ctx.closePath();
                    ctx.fillStyle = wheelColors[i % wheelColors.length];
                    ctx.fill();
                    ctx.strokeStyle = '#fff';
                    ctx.lineWidth = 2;
                    ctx.stroke();

                    ctx.save();
                    ctx.rotate(startAngle + sliceAngle / 2);
                    ctx.textAlign = 'right';
                    ctx.fillStyle = '#fff';
                    ctx.font = 'bold 12px Arial, sans-serif';
                    ctx.shadowColor = 'rgba(0,0,0,0.5)';
                    ctx.shadowBlur = 3;

                    let displayName = member.name;
                    if (displayName.length > 12) {
                        displayName = displayName.substring(0, 11) + '…';
                    }
                    ctx.fillText(displayName, radius - 14, 5);
                    ctx.restore();
                });

                ctx.restore();

                ctx.beginPath();
                ctx.arc(center, center, 24, 0, 2 * Math.PI);
                ctx.fillStyle = '#fff';
                ctx.fill();
                ctx.strokeStyle = '#333';
                ctx.lineWidth = 2;
                ctx.stroke();
            }

            function getCustomToolSelectedMemberId() {
                const select = document.getElementById('memberSelect');
                if (select && select.value) {
                    return parseInt(select.value, 10);
                }
                return savedCustomPickMemberId || null;
            }

            function resolveWinnerIndex() {
                let targetId = predeterminedMemberId;

                if (!targetId && spinMode === 'selection') {
                    targetId = getCustomToolSelectedMemberId();
                }

                if (targetId) {
                    const index = spinMembers.findIndex(m => m.id === targetId);
                    if (index !== -1) {
                        return index;
                    }
                }

                return Math.floor(Math.random() * spinMembers.length);
            }

            function spin() {
                if (isSpinning || spinMembers.length === 0) return;

                isSpinning = true;
                spinBtn.disabled = true;
                spinBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Spinning...';
                resultBanner.classList.remove('show');
                useResultBtn.classList.add('d-none');
                selectedWinner = null;

                const winnerIndex = resolveWinnerIndex();
                const sliceAngle = (2 * Math.PI) / spinMembers.length;
                const extraSpins = 5 + Math.floor(Math.random() * 3);
                const targetAngle = extraSpins * 2 * Math.PI + (3 * Math.PI / 2 - winnerIndex * sliceAngle - sliceAngle / 2);
                const startRotation = currentRotation;
                const totalRotation = targetAngle - (startRotation % (2 * Math.PI));
                const duration = 4000;
                const startTime = performance.now();

                function animate(now) {
                    const elapsed = now - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easeOut = 1 - Math.pow(1 - progress, 4);
                    currentRotation = startRotation + totalRotation * easeOut;
                    drawWheel(currentRotation);

                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        isSpinning = false;
                        spinBtn.disabled = false;
                        spinBtn.innerHTML = '<i class="fas fa-redo me-2"></i>SPIN AGAIN';
                        selectedWinner = spinMembers[winnerIndex];
                        winnerNameEl.textContent = selectedWinner.name +
                            (selectedWinner.number ? ' (#' + selectedWinner.number + ')' : '');
                        resultBanner.classList.add('show');
                        useResultBtn.classList.remove('d-none');
                    }
                }

                requestAnimationFrame(animate);
            }

            function submitMonthPick() {
                if (!selectedWinner || !pickContext) return;

                const pickButton = pickContext.pickButton;
                if (pickButton) {
                    pickButton.disabled = true;
                    pickButton.innerHTML = '🎲 Picking...';
                }

                fetch('../admin/random_pick_member_custom.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `group_id=${pickContext.groupId}&month_number=${pickContext.monthNumber}&selected_member_id=${selectedWinner.id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        spinWheelModalInstance.hide();
                        alert(`Random pick successful! ${selectedWinner.name} has been selected for Month ${pickContext.monthNumber}.`);
                        window.location.reload();
                    } else {
                        alert(`Error: ${data.message}`);
                        if (pickButton) {
                            pickButton.disabled = false;
                            pickButton.innerHTML = '🎲 Pick';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while making the random pick. Please try again.');
                    if (pickButton) {
                        pickButton.disabled = false;
                        pickButton.innerHTML = '🎲 Pick';
                    }
                });
            }

            function useSpinResult() {
                if (!selectedWinner) return;

                const winnerLabel = selectedWinner.name +
                    (selectedWinner.number ? ' (#' + selectedWinner.number + ')' : '');

                if (spinMode === 'selection') {
                    showSpinConfirm({
                        title: 'Confirm Selected Member',
                        message: 'Do you want to use this member from the spin result?',
                        winnerName: winnerLabel,
                        subtext: 'This will select the member in the dropdown. Click Save Member to store your choice.',
                        okText: 'Yes, Use This Member',
                        okClass: 'btn-success',
                        onConfirm: function() {
                            const select = document.getElementById('memberSelect');
                            if (select) {
                                select.value = selectedWinner.id;
                                select.dispatchEvent(new Event('change'));
                            }
                            spinWheelModalInstance.hide();
                        }
                    });
                } else {
                    showSpinConfirm({
                        title: 'Confirm Random Pick',
                        message: `Confirm this member for Month ${pickContext.monthNumber}?`,
                        winnerName: winnerLabel,
                        subtext: 'This action cannot be undone.',
                        okText: 'Yes, Confirm Pick',
                        okClass: 'btn-success',
                        onConfirm: submitMonthPick
                    });
                }
            }

            function resetSpinModal() {
                spinBtn.disabled = false;
                spinBtn.innerHTML = '<i class="fas fa-play me-2"></i>SPIN';
                resultBanner.classList.remove('show');
                useResultBtn.classList.add('d-none');
                selectedWinner = null;
                isSpinning = false;
            }

            function openSpinWheel(options) {
                spinMode = options.mode || 'pick';
                spinMembers = pendingMembers;
                pickContext = options.pickContext || null;
                predeterminedMemberId = options.predeterminedMemberId || null;

                if (!spinMembers.length) {
                    alert('No pending members available for spin.');
                    return;
                }

                const hasSavedPick = predeterminedMemberId || (spinMode === 'selection' && getCustomToolSelectedMemberId());
                const savedName = predeterminedMemberId
                    ? (spinMembers.find(m => m.id === predeterminedMemberId)?.name || savedCustomPickMemberName)
                    : (spinMode === 'selection' && getCustomToolSelectedMemberId()
                        ? spinMembers.find(m => m.id === getCustomToolSelectedMemberId())?.name
                        : null);

                if (spinMode === 'selection') {
                    spinSubtitle.textContent = hasSavedPick && savedName
                        ? `Spin the wheel — result will be your chosen member: ${savedName}`
                        : 'Spin the wheel to select a pending member who hasn\'t received amount yet.';
                    useResultBtnText.textContent = 'Use This Member';
                } else if (hasSavedPick && savedName) {
                    spinSubtitle.textContent = `Spin the wheel for Month ${pickContext.monthNumber} — result will be: ${savedName}`;
                    useResultBtnText.textContent = 'Confirm Pick';
                } else {
                    spinSubtitle.textContent = `Spin the wheel to randomly pick a pending member for Month ${pickContext.monthNumber}.`;
                    useResultBtnText.textContent = 'Confirm Pick';
                }

                spinWheelModalInstance.show();
            }

            window.openSpinWheelForPick = function(options) {
                const useSaved = options.pickButton?.getAttribute('data-use-saved') === '1';
                const savedMemberId = useSaved
                    ? parseInt(options.pickButton.getAttribute('data-saved-member-id') || '0', 10)
                    : 0;

                openSpinWheel({
                    mode: 'pick',
                    predeterminedMemberId: savedMemberId || savedCustomPickMemberId || null,
                    pickContext: {
                        groupId: options.groupId,
                        monthNumber: options.monthNumber,
                        pickButton: options.pickButton
                    }
                });
            };

            spinBtn.addEventListener('click', spin);
            useResultBtn.addEventListener('click', useSpinResult);

            if (spinWheelBtn) {
                spinWheelBtn.addEventListener('click', function() {
                    openSpinWheel({ mode: 'selection' });
                });
            }

            spinModal.addEventListener('shown.bs.modal', function() {
                const container = document.querySelector('.spin-wheel-container');
                const containerWidth = container ? container.offsetWidth : 320;
                canvas.width = containerWidth;
                canvas.height = containerWidth;
                currentRotation = 0;
                drawWheel(0);
                resetSpinModal();
            });

            spinModal.addEventListener('hidden.bs.modal', function() {
                resetSpinModal();
                pickContext = null;
                predeterminedMemberId = null;
            });
        })();
        <?php endif; ?>

        // Enhanced mobile tooltip handling
        document.addEventListener('DOMContentLoaded', function() {
            const nameTooltips = document.querySelectorAll('.name-tooltip');

            nameTooltips.forEach(tooltip => {
                // Handle touch events for mobile
                tooltip.addEventListener('touchstart', function(e) {
                    e.preventDefault();

                    // Remove any existing active tooltips
                    document.querySelectorAll('.name-tooltip.active').forEach(t => {
                        t.classList.remove('active');
                    });

                    // Add active class to show tooltip
                    this.classList.add('active');

                    // Remove tooltip after 3 seconds
                    setTimeout(() => {
                        this.classList.remove('active');
                    }, 3000);
                });

                // Handle click outside to hide tooltip
                document.addEventListener('touchstart', function(e) {
                    if (!tooltip.contains(e.target)) {
                        tooltip.classList.remove('active');
                    }
                });
            });

            // Handle invoice download button clicks
            document.querySelectorAll('.invoice-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const memberId = this.getAttribute('data-member-id');
                    const memberName = this.getAttribute('data-member-name');
                    const groupId = this.getAttribute('data-group-id');
                    const groupName = this.getAttribute('data-group-name');

                    // Show loading state
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    this.disabled = true;

                    // Generate and open invoice in new tab/page
                    const sessionId = '<?= session_id() ?>';
                    const url = `generate_invoice.php?member_id=${memberId}&group_id=${groupId}&session_id=${sessionId}`;
                    
                    try {
                        // Try to open in new tab first
                        const invoiceWindow = window.open(url, '_blank');
                        
                        // Check if popup was blocked
                        if (!invoiceWindow || invoiceWindow.closed || typeof invoiceWindow.closed == 'undefined') {
                            // Fallback: redirect current page to invoice
                            if (confirm('Popup blocked! Click OK to view invoice in current tab, or Cancel to allow popups and try again.')) {
                                window.location.href = url;
                            }
                        } else {
                            // Success - focus the new window
                            invoiceWindow.focus();
                        }
                    } catch (error) {
                        // Final fallback - redirect to invoice page
                        window.location.href = url;
                    }

                    // Reset button state after a short delay
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }, 1000);
                });
            });
        });
    </script>

    <style>
        /* Mobile touch tooltip styling */
        @media (max-width: 768px) {
            .name-tooltip.active::after {
                content: attr(data-full-name);
                position: absolute;
                bottom: 100%;
                left: 0;
                background: #333;
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 1000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                animation: fadeIn 0.3s ease-in-out;
            }

            .name-tooltip.active::before {
                content: '';
                position: absolute;
                bottom: 95%;
                left: 10px;
                border: 5px solid transparent;
                border-top-color: #333;
                z-index: 1000;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        }
    </style>

<?php require_once 'includes/footer.php'; ?>
