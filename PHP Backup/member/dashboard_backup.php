<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('member');

// Get current member information
$member = getCurrentMember();
if (!$member) {
    setMessage('Member session expired. Please login again.', 'error');
    redirect('../auth/member_login.php');
}

$pdo = getDB();

// Get all groups this member belongs to with additional details
$memberGroups = getMemberGroups($member['id']);

// Enhance group data with additional information
foreach ($memberGroups as &$group) {
    // Get member count for this group using group_members table
    $stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND status = 'active'");
    $stmt->execute([$group['id']]);
    $memberCount = $stmt->fetchColumn();
    $group['actual_member_count'] = $memberCount;

    // Get completed months count
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_months FROM monthly_bids WHERE group_id = ?");
    $stmt->execute([$group['id']]);
    $completedMonths = $stmt->fetchColumn();
    $group['completed_months'] = $completedMonths;

    // Calculate progress percentage
    $group['progress_percentage'] = $group['total_members'] > 0 ? round(($completedMonths / $group['total_members']) * 100, 1) : 0;

    // Calculate estimated end date
    $startDate = new DateTime($group['start_date']);
    $estimatedEndDate = clone $startDate;
    $estimatedEndDate->add(new DateInterval('P' . ($group['total_members'] - 1) . 'M'));
    $group['estimated_end_date'] = $estimatedEndDate->format('Y-m-d');
}

// Get current group information
// Check if group_id is provided in URL, otherwise use session
if (isset($_GET['group_id']) && is_numeric($_GET['group_id'])) {
    $requestedGroupId = (int)$_GET['group_id'];

    // Verify the member belongs to this group
    $memberBelongsToGroup = false;
    foreach ($memberGroups as $group) {
        if ($group['id'] == $requestedGroupId) {
            $memberBelongsToGroup = true;
            break;
        }
    }

    if ($memberBelongsToGroup) {
        $currentGroupId = $requestedGroupId;
        $_SESSION['group_id'] = $currentGroupId; // Update session
    } else {
        // If member doesn't belong to requested group, use session or first group
        $currentGroupId = $_SESSION['group_id'] ?? null;
    }
} else {
    // No group_id in URL, use session
    $currentGroupId = $_SESSION['group_id'] ?? null;
}

$currentGroup = null;
foreach ($memberGroups as $group) {
    if ($group['id'] == $currentGroupId) {
        $currentGroup = $group;
        break;
    }
}

// If current group not found, use first available group
if (!$currentGroup && !empty($memberGroups)) {
    $currentGroup = $memberGroups[0];
    $currentGroupId = $currentGroup['id'];
    $_SESSION['group_id'] = $currentGroupId;
}

// Member statistics
$totalGroups = count($memberGroups);
$totalPaidAmount = 0;
$totalReceivedAmount = 0;
$totalPendingPayments = 0;

// Calculate member's financial summary across all groups
foreach ($memberGroups as $group) {
    // Get member summary for this group using the correct member ID
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(payment_amount), 0) as total_paid
        FROM member_payments
        WHERE member_id = ? AND group_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$member['id'], $group['id']]);
    $groupPaid = $stmt->fetchColumn();
    $totalPaidAmount += $groupPaid;

    // Get received amount (if member won any month)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(net_payable), 0) as total_received
        FROM monthly_bids
        WHERE taken_by_member_id = ? AND group_id = ?
    ");
    $stmt->execute([$member['id'], $group['id']]);
    $groupReceived = $stmt->fetchColumn();
    $totalReceivedAmount += $groupReceived;

    // Calculate pending payments for current month
    $currentMonth = getCurrentActiveMonthNumber($group['id']);
    if ($currentMonth) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM member_payments
            WHERE member_id = ? AND group_id = ? AND month_number = ? AND payment_status = 'pending'
        ");
        $stmt->execute([$member['id'], $group['id'], $currentMonth]);
        if ($stmt->fetchColumn() > 0) {
            $totalPendingPayments += $group['monthly_contribution'];
        }
    }
}

$totalProfit = $totalReceivedAmount - $totalPaidAmount;

// Get recent payments across all groups
$stmt = $pdo->prepare("
    SELECT mp.*, g.group_name, g.monthly_contribution
    FROM member_payments mp
    JOIN bc_groups g ON mp.group_id = g.id
    WHERE mp.member_id = ? AND mp.payment_status = 'paid'
    ORDER BY mp.payment_date DESC
    LIMIT 5
");
$stmt->execute([$member['id']]);
$recentPayments = $stmt->fetchAll();

// Get upcoming payments (pending)
$stmt = $pdo->prepare("
    SELECT mp.*, g.group_name, g.monthly_contribution
    FROM member_payments mp
    JOIN bc_groups g ON mp.group_id = g.id
    WHERE mp.member_id = ? AND mp.payment_status = 'pending'
    ORDER BY mp.month_number ASC
    LIMIT 5
");
$stmt->execute([$member['id']]);
$upcomingPayments = $stmt->fetchAll();

// Set page title for the header
$page_title = 'Member Dashboard';

// Include the member header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
        /* Global text color fix */
        select{
            color: black !important;
        }

        /* Professional Dashboard Styles */
        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            margin-bottom: var(--space-6);
            transition: var(--transition-normal);
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(20px);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stats-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-2xl);
        }

        .stats-card.groups {
            background: var(--primary-gradient);
            color: white !important;
        }

        .stats-card.groups * {
            color: white !important;
        }

        .stats-card.payments {
            background: var(--success-gradient);
            color: white !important;
        }

        .stats-card.payments * {
            color: white !important;
        }

        .stats-card.received {
            background: var(--warning-gradient);
            color: var(--gray-800) !important;
        }

        .stats-card.received * {
            color: var(--gray-800) !important;
        }

        .stats-card.pending {
            background: var(--danger-gradient);
            color: white !important;
        }

        .stats-card.pending * {
            color: white !important;
        }

        .stats-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: var(--space-2);
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-label {
            font-size: 1.1rem;
            opacity: 0.95;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-icon {
            font-size: 4rem;
            opacity: 0.2;
            position: absolute;
            right: var(--space-6);
            top: var(--space-6);
            transform: rotate(-15deg);
        }

        .member-actions {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            box-shadow: var(--shadow-xl);
            margin-bottom: var(--space-6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .action-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-xl);
            padding: var(--space-4) var(--space-6);
            margin: var(--space-2);
            transition: var(--transition-normal);
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition-normal);
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            transform: translateY(-4px) scale(1.05);
            box-shadow: var(--shadow-xl);
            color: white;
        }

        .recent-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            box-shadow: var(--shadow-xl);
            margin-bottom: var(--space-6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .item-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,250,252,0.9) 100%);
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            margin-bottom: var(--space-4);
            border: 1px solid rgba(255,255,255,0.3);
            transition: var(--transition-normal);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-md);
        }

        .item-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-xl);
            border-color: rgba(59, 130, 246, 0.3);
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: var(--radius-2xl);
            padding: var(--space-8);
            margin-bottom: var(--space-8);
            position: relative;
            overflow: hidden;
            color: white;
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 2;
            gap: var(--space-8);
        }

        .welcome-text {
            flex: 2;
            max-width: 60%;
        }

        .greeting {
            margin-bottom: var(--space-2);
        }

        .greeting-time {
            display: block;
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .member-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            background: linear-gradient(45deg, #ffffff, #f0f9ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
            margin: 0;
            font-weight: 400;
        }



        /* Welcome Info Row */
        .welcome-info-row {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: var(--space-3);
            align-items: flex-end;
            justify-content: flex-start;
            max-width: 35%;
            margin-top: var(--space-2);
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.3s ease;
            white-space: nowrap;
            flex-shrink: 0;
            justify-content: flex-end;
            text-align: right;
        }

        .info-item:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .info-item.urgent {
            background: rgba(245, 87, 108, 0.2);
            border-color: rgba(245, 87, 108, 0.3);
            animation: pulse 2s infinite;
        }

        .info-item i {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .info-item strong {
            font-weight: 600;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 0.9;
            }
            50% {
                opacity: 1;
                box-shadow: 0 0 20px rgba(245, 87, 108, 0.3);
            }
        }

        .welcome-decoration {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            pointer-events: none;
            z-index: 1;
        }

        .floating-shapes {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        .shape-1 {
            width: 100px;
            height: 100px;
            top: 20%;
            right: 10%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 60px;
            height: 60px;
            top: 60%;
            right: 20%;
            animation-delay: 2s;
        }

        .shape-3 {
            width: 80px;
            height: 80px;
            top: 10%;
            right: 30%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.1;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.2;
            }
        }



        .group-selector {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-xl);
            padding: var(--space-5);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .section-title i {
            background: var(--primary-gradient);
            color: white;
            padding: var(--space-2);
            border-radius: var(--radius-lg);
            font-size: 1.2rem;
        }

        .form-label {
            color: var(--gray-800) !important;
            font-weight: 600;
            margin-bottom: var(--space-2);
        }

        .form-select {
            background-color: white;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: var(--space-3);
            color: var(--gray-800);
            font-weight: 500;
        }

        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Ensure all text is visible */
        .text-muted {
            color: var(--gray-600) !important;
        }

        .member-actions h5,
        .recent-card h5 {
            color: var(--gray-800) !important;
            font-weight: 600;
        }

        .action-btn {
            color: black !important;
            text-decoration: none;
            display: block;
            margin-bottom: var(--space-3);
        }

        .action-btn:hover {
            color: black !important;
            text-decoration: none;
        }

        .item-card {
            color: var(--gray-800);
        }

        .item-card .text-muted {
            color: var(--gray-600) !important;
        }

        .btn {
            color: var(--gray-800);
        }

        .btn-outline-success {
            color: var(--success-color) !important;
            border-color: var(--success-color);
        }

        .btn-outline-success:hover {
            background-color: var(--success-color);
            color: black !important;
        }

        /* Group Selection Cards Styles */
        .group-selection-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-xl);
            padding: var(--space-5);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .selection-title {
            color: var(--gray-800) !important;
            font-weight: 600;
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
        }

        .groups-grid {
            display: flex;
            flex-direction: column;
            gap: var(--space-5);
        }

        .group-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            transition: all 0.3s ease;
            position: relative;
        }

        .group-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }

        .group-card.active {
            border-color: #3b82f6;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        /* Group Header */
        .group-header {
            margin-bottom: var(--space-3);
        }

        .group-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .group-title h6 {
            margin: 0;
            font-weight: 600;
            color: var(--gray-800) !important;
            font-size: 1rem;
        }

        .group-actions {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .view-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
            padding: var(--space-1) var(--space-3);
            background: var(--primary-gradient);
            color: white !important;
            border: none;
            border-radius: var(--radius-lg);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            color: white !important;
            text-decoration: none;
        }

        .view-btn:focus, .view-btn:active {
            color: white !important;
            text-decoration: none;
        }

        .view-btn i {
            font-size: 0.75rem;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            font-size: 0.6rem;
        }

        .status-indicator.status-active {
            color: #10b981 !important;
        }

        .status-indicator.status-completed {
            color: #f59e0b !important;
        }

        /* Group Details */
        .group-details {
            display: flex;
            gap: var(--space-4);
            margin-bottom: var(--space-3);
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }

        .detail-icon {
            color: #6b7280 !important;
            font-size: 0.8rem;
            width: 16px;
        }

        .detail-value {
            font-size: 0.85rem;
            color: var(--gray-700) !important;
            font-weight: 500;
        }

        /* Timeline */
        .group-timeline {
            margin-bottom: var(--space-3);
        }

        .timeline-dates {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .start-date, .end-date {
            display: flex;
            align-items: center;
            gap: var(--space-1);
            font-size: 0.8rem;
            color: var(--gray-600) !important;
        }

        .start-date i {
            color: #3b82f6 !important;
        }

        .end-date i {
            color: #f59e0b !important;
        }

        /* Road Progress Design */
        .road-progress {
            margin-top: var(--space-3);
            padding: var(--space-3) var(--space-3);
        }

        .road-container {
            position: relative;
            height: 60px;
            width: 100%;
        }

        .road-track {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 12px;
            background: #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .road-completed {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #34d399 50%, #6ee7b7 100%);
            border-radius: 6px;
            position: relative;
            transition: width 0.8s ease;
        }

        .road-completed::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
            animation: roadShine 2s infinite;
        }

        @keyframes roadShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .road-markings {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
        }

        .road-dash {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 2px;
            background: white;
            border-radius: 1px;
            opacity: 0.8;
        }

        .cycle-container {
            position: absolute;
            top: 8px;
            transform: translateX(-50%);
            transition: left 0.8s ease;
        }

        .cycle-icon {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
            animation: cycleMove 3s infinite ease-in-out;
            position: relative;
            z-index: 2;
        }

        .cycle-shadow {
            position: absolute;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            width: 20px;
            height: 4px;
            background: rgba(0,0,0,0.2);
            border-radius: 50%;
            filter: blur(2px);
        }

        @keyframes cycleMove {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-2px) rotate(5deg); }
        }

        .progress-labels {
            position: absolute;
            top: 40px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .start-label, .end-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            font-size: 0.7rem;
            color: var(--gray-600) !important;
        }

        .start-label i {
            color: #10b981 !important;
            font-size: 0.8rem;
        }

        .end-label i {
            color: #f59e0b !important;
            font-size: 0.8rem;
        }

        .progress-center {
            display: flex;
           
            gap: 10px;
        }

        .progress-percent {
            font-weight: 700;
            color: var(--primary-color) !important;
            font-size: 0.9rem;
        }

        .progress-count {
            font-size: 0.7rem;
            color: var(--gray-600) !important;
            background: rgba(59, 130, 246, 0.1);
            padding: 2px 6px;
            border-radius: 8px;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        /* Mobile Optimized Design */
        @media (max-width: 768px) {
            .welcome-section {
                padding: var(--space-6);
            }

            .welcome-content {
                flex-direction: column;
                gap: var(--space-4);
                text-align: center;
            }

            .welcome-text {
                max-width: 100%;
            }

            .member-name {
                font-size: 2rem;
            }



            .welcome-info-row {
                max-width: 100%;
                align-items: center;
                margin-top: var(--space-4);
                flex-direction: column;
                gap: var(--space-2);
            }

            .info-item {
                justify-content: center;
                text-align: center;
                font-size: 0.85rem;
                white-space: normal;
                min-width: 200px;
            }

            .group-selection-container {
                padding: var(--space-3);
            }

            .groups-grid {
                gap: var(--space-4);
            }

            .group-card {
                padding: var(--space-3);
                display: grid;
                grid-template-columns: 1fr auto;
                grid-template-rows: auto auto auto;
                gap: var(--space-2);
                align-items: start;
            }

            .group-header {
                grid-column: 1 / -1;
                margin-bottom: 0;
            }

            .group-title {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                gap: var(--space-2);
            }

            .group-title h6 {
                font-size: 1rem;
                margin: 0;
            }

            .view-btn {
                padding: var(--space-1) var(--space-2);
                font-size: 0.75rem;
                white-space: nowrap;
            }

            .group-details {
                grid-column: 1;
                grid-row: 2;
                display: flex;
                flex-direction: column;
                gap: var(--space-1);
            }

            .group-timeline {
                grid-column: 1;
                grid-row: 3;
                margin-bottom: 0;
            }

            .timeline-dates {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: var(--space-2);
            }

            .start-date, .end-date {
                font-size: 0.75rem;
                gap: 4px;
            }

            .road-progress {
                grid-column: 1 / -1;
                grid-row: 4;
                margin-top: var(--space-2);
                padding: var(--space-2) var(--space-2);
            }

            .road-container {
                height: 45px;
            }

            .cycle-icon {
                width: 22px;
                height: 22px;
                font-size: 11px;
            }

            .progress-labels {
                top: 30px;
            }

            .start-label, .end-label {
                font-size: 0.65rem;
            }

            .progress-percent {
                font-size: 0.85rem;
            }

            .progress-count {
                font-size: 0.65rem;
            }

            .detail-row {
                gap: var(--space-1);
            }

            .detail-value {
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .welcome-section {
                padding: var(--space-4);
            }

            .member-name {
                font-size: 1.8rem;
            }

            .welcome-subtitle {
                font-size: 1rem;
            }



            .info-item {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }

            .group-selection-container {
                padding: var(--space-2);
            }

            .groups-grid {
                gap: var(--space-3);
            }

            .group-card {
                padding: var(--space-2);
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto auto;
            }

            .group-header {
                grid-column: 1;
                grid-row: 1;
            }

            .group-title {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-1);
            }

            .group-title h6 {
                font-size: 0.95rem;
            }

            .group-actions {
                align-self: flex-start;
            }

            .view-btn {
                padding: 4px var(--space-2);
                font-size: 0.7rem;
            }

            .group-details {
                grid-column: 1;
                grid-row: 2;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: var(--space-1);
            }

            .group-timeline {
                grid-column: 1;
                grid-row: 3;
                margin-bottom: var(--space-1);
            }

            .timeline-dates {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: var(--space-1);
                text-align: center;
            }

            .start-date, .end-date {
                font-size: 0.7rem;
                justify-content: center;
            }

            .road-progress {
                grid-column: 1;
                grid-row: 4;
                margin-top: var(--space-1);
                padding: var(--space-1) var(--space-2);
            }

            .road-container {
                height: 40px;
            }

            .road-track {
                top: 15px;
                height: 10px;
            }

            .cycle-container {
                top: 6px;
            }

            .cycle-icon {
                width: 20px;
                height: 20px;
                font-size: 10px;
            }

            .progress-labels {
                top: 25px;
            }

            .start-label span, .end-label span {
                display: none;
            }

            .progress-percent {
                font-size: 0.8rem;
            }

            .progress-count {
                font-size: 0.6rem;
                padding: 1px 4px;
            }

            .road-dash {
                width: 6px;
                height: 1px;
            }

            .detail-value {
                font-size: 0.75rem;
            }

            .detail-icon {
                font-size: 0.7rem;
            }
        }











    </style>

<!-- Page content starts here -->

        <!-- Welcome Header -->
        <div class="welcome-section">
            <div class="welcome-content">
                <div class="welcome-text">
                    <div class="greeting">
                        <span class="greeting-time" id="greetingTime"></span>
                        <h1 class="member-name"><?= htmlspecialchars($member['member_name']) ?>!</h1>
                    </div>
                    <p class="welcome-subtitle">Ready to manage your BC groups and payments</p>
                </div>


                <!-- Additional Info Row -->
                <div class="welcome-info-row">
                    <?php
                    $nextPaymentDue = null;
                    if (!empty($upcomingPayments)) {
                        $nextPaymentDue = $upcomingPayments[0];
                    }
                    if ($nextPaymentDue):
                    ?>
                    <div class="info-item urgent">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Next Due: <strong>Month <?= $nextPaymentDue['month_number'] ?></strong> - ₹<?= number_format($nextPaymentDue['monthly_contribution']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php
                    $totalMonthlyPayment = 0;
                    foreach ($memberGroups as $group) {
                        if ($group['status'] === 'active') {
                            $totalMonthlyPayment += $group['monthly_contribution'];
                        }
                    }
                    if ($totalMonthlyPayment > 0):
                    ?>
                    <div class="info-item">
                        <i class="fas fa-rupee-sign"></i>
                        <span>Monthly Payment: <strong>₹<?= number_format($totalMonthlyPayment) ?></strong></span>
                    </div>
                    <?php endif; ?>

                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Last Login: <strong><?= date('M j, Y g:i A') ?></strong></span>
                    </div>
                </div>
            </div>
            <div class="welcome-decoration">
                <div class="floating-shapes">
                    <div class="shape shape-1"></div>
                    <div class="shape shape-2"></div>
                    <div class="shape shape-3"></div>
                </div>
            </div>
        </div>

      

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card groups position-relative">
                    <i class="fas fa-layer-group stats-icon"></i>
                    <div class="stats-number"><?= $totalGroups ?></div>
                    <div class="stats-label">My Groups</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card payments position-relative">
                    <i class="fas fa-rupee-sign stats-icon"></i>
                    <div class="stats-number">₹<?= number_format($totalPaidAmount) ?></div>
                    <div class="stats-label">Total Paid</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card received position-relative">
                    <i class="fas fa-hand-holding-usd stats-icon"></i>
                    <div class="stats-number">₹<?= number_format($totalReceivedAmount) ?></div>
                    <div class="stats-label">Total Received</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card pending position-relative">
                    <i class="fas fa-clock stats-icon"></i>
                    <div class="stats-number"><?= $totalPendingPayments ?></div>
                    <div class="stats-label">Pending Payments</div>
                </div>
            </div>
        </div>

          <!-- Group Selection Cards -->
        <?php if (count($memberGroups) > 1): ?>
        <div class="group-selection-container">
            <h5 class="selection-title">
                <i class="fas fa-layer-group me-2"></i>My Group:
            </h5>
            <div class="groups-grid">
                <?php foreach ($memberGroups as $group): ?>
                <div class="group-card <?= $group['id'] == $currentGroupId ? 'active' : '' ?>"
                     data-group-id="<?= $group['id'] ?>">

                    <!-- Group Header -->
                    <div class="group-header">
                        <div class="group-title">
                            <h6><?= htmlspecialchars($group['group_name']) ?></h6>
                            <div class="group-actions">
                                <span class="status-indicator status-<?= $group['status'] ?>">
                                    <i class="fas fa-circle"></i>
                                </span>
                                <a href="group_view.php?group_id=<?= $group['id'] ?>" class="view-btn">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Group Details -->
                    <div class="group-details">
                        <div class="detail-row">
                            <span class="detail-icon"><i class="fas fa-users"></i></span>
                            <span class="detail-value"><?= $group['total_members'] ?> Members</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-icon"><i class="fas fa-rupee-sign"></i></span>
                            <span class="detail-value">₹<?= number_format($group['monthly_contribution']) ?>/month</span>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="group-timeline">
                        <div class="timeline-dates">
                            <span class="start-date">
                                <i class="fas fa-play"></i> <?= date('M Y', strtotime($group['start_date'])) ?>
                            </span>
                            <span class="end-date">
                                <i class="fas fa-flag"></i> <?= date('M Y', strtotime($group['estimated_end_date'])) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Road Progress -->
                    <div class="road-progress">
                        <div class="road-container">
                            <!-- Road Track -->
                            <div class="road-track">
                                <div class="road-completed" style="width: <?= $group['progress_percentage'] ?>%"></div>
                                <div class="road-remaining"></div>

                                <!-- Road Markings -->
                                <div class="road-markings">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <div class="road-dash" style="left: <?= ($i * 20) - 10 ?>%"></div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <!-- Cycle Icon -->
                            <div class="cycle-container" style="left: <?= min($group['progress_percentage'], 95) ?>%">
                                <div class="cycle-icon">
                                    <i class="fas fa-bicycle"></i>
                                </div>
                                <div class="cycle-shadow"></div>
                            </div>

                            <!-- Progress Info -->
                            <div class="progress-labels">
                                <div class="start-label">
                                    <i class="fas fa-flag"></i>
                                    <span>Start</span>
                                </div>
                                <div class="progress-center">
                                    <span class="progress-percent"><?= $group['progress_percentage'] ?>%</span>
                                    <span class="progress-count"><?= $group['completed_months'] ?>/<?= $group['total_members'] ?> months</span>
                                </div>
                                <div class="end-label">
                                    <i class="fas fa-trophy"></i>
                                    <span>Finish</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Member Actions -->
            <div class="col-md-4">
                <div class="member-actions">
                    <h5 class="mb-3">
                        <i class="fas fa-bolt me-2 text-warning"></i>Quick Actions
                    </h5>

                    <a href="group_view.php" class="action-btn w-100">
                        <i class="fas fa-layer-group me-2"></i>View My Groups
                    </a>

                    <a href="bidding.php" class="action-btn w-100">
                        <i class="fas fa-gavel me-2"></i>Participate in Bidding
                    </a>

                    <a href="payment.php" class="action-btn w-100">
                        <i class="fas fa-credit-card me-2"></i>Payment Status
                    </a>

                    <a href="edit_profile.php" class="action-btn w-100">
                        <i class="fas fa-user-edit me-2"></i>Edit Profile
                    </a>

                    <a href="change_password.php" class="action-btn w-100">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="col-md-4">
                <div class="recent-card">
                    <h5 class="mb-3">
                        <i class="fas fa-credit-card me-2 text-success"></i>Recent Payments
                    </h5>

                    <?php if (empty($recentPayments)): ?>
                        <p class="text-muted text-center py-3">No payments made yet.</p>
                        <div class="text-center">
                            <a href="payment.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-credit-card me-2"></i>Make First Payment
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="item-card">
                                <h6 class="mb-1">Month <?= $payment['month_number'] ?> - <?= htmlspecialchars($payment['group_name']) ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-rupee-sign me-1"></i>₹<?= number_format($payment['payment_amount']) ?>
                                    <i class="fas fa-calendar ms-2 me-1"></i><?= date('M j, Y', strtotime($payment['payment_date'])) ?>
                                    <span class="badge bg-success ms-2">Paid</span>
                                </small>
                            </div>
                        <?php endforeach; ?>

                        <div class="text-center mt-3">
                            <a href="payment.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-eye me-2"></i>View All Payments
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Payments -->
            <div class="col-md-4">
                <div class="recent-card">
                    <h5 class="mb-3">
                        <i class="fas fa-clock me-2 text-warning"></i>Upcoming Payments
                    </h5>

                    <?php if (empty($upcomingPayments)): ?>
                        <p class="text-muted text-center py-3">No pending payments.</p>
                        <div class="text-center">
                            <a href="group_view.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-layer-group me-2"></i>View Groups
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcomingPayments as $payment): ?>
                            <div class="item-card">
                                <h6 class="mb-1">Month <?= $payment['month_number'] ?> - <?= htmlspecialchars($payment['group_name']) ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-rupee-sign me-1"></i>₹<?= number_format($payment['monthly_contribution']) ?>
                                    <span class="badge bg-warning ms-2">Pending</span>
                                </small>
                            </div>
                        <?php endforeach; ?>

                        <div class="text-center mt-3">
                            <a href="payment.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-credit-card me-2"></i>Make Payment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Set dynamic greeting
            setGreeting();

            // Initialize group selection if available
            if (typeof initializeGroupSelection === 'function') {
                initializeGroupSelection();
            }
        });

        function setGreeting() {
            const greetingElement = document.getElementById('greetingTime');
            if (!greetingElement) return;

            const now = new Date();
            const hour = now.getHours();
            let greeting;

            if (hour < 12) {
                greeting = 'Good Morning';
            } else if (hour < 17) {
                greeting = 'Good Afternoon';
            } else {
                greeting = 'Good Evening';
            }

            greetingElement.textContent = greeting;
        }
    </script>

<?php require_once 'includes/footer.php'; ?>
