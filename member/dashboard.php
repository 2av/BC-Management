
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

// Get all groups this member belongs to
$memberGroups = getMemberGroups($member['id']);

// Calculate financial summary
$totalPaidAmount = 0;
$totalReceivedAmount = 0;
$totalPendingPayments = 0;
$totalProfit = 0;

foreach ($memberGroups as $group) {
    // Get member summary for this group
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

// Get current group ID for selection
$currentGroupId = $_GET['group_id'] ?? ($memberGroups[0]['id'] ?? null);

// Set page title for the header
$page_title = 'Member Dashboard';

// Include the member header
require_once 'includes/header.php';
?>

<style>
    :root {
        /* Professional Color Palette */
        --primary-50: #f8fafc;
        --primary-100: #f1f5f9;
        --primary-200: #e2e8f0;
        --primary-300: #cbd5e1;
        --primary-400: #94a3b8;
        --primary-500: #64748b;
        --primary-600: #475569;
        --primary-700: #334155;
        --primary-800: #1e293b;
        --primary-900: #0f172a;

        /* Accent Colors */
        --accent-blue: #3b82f6;
        --accent-green: #10b981;
        --accent-orange: #f59e0b;
        --accent-red: #ef4444;
        --accent-purple: #8b5cf6;

        /* Gradients */
        --primary-gradient: linear-gradient(135deg, #64748b 0%, #475569 100%);
        --accent-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);

        /* Shadows */
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --focus-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);

        /* Text Colors */
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --text-muted: #94a3b8;

        /* Background Colors */
        --bg-primary: #ffffff;
        --bg-secondary: #f8fafc;
        --bg-muted: #f1f5f9;
    }

    .dashboard-container {
      
        min-height: 100vh;
        padding: 2rem 0;
    }

    .welcome-banner {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%);
        border-radius: 20px;
        padding: 2rem;
        margin-bottom: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
    }

    .welcome-content {
        position: relative;
        z-index: 2;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .welcome-text .greeting {
        font-size: 1.1rem;
        opacity: 0.9;
        margin-bottom: 0.25rem;
    }

    .welcome-text .member-name {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .welcome-text .welcome-subtitle {
        font-size: 1rem;
        opacity: 0.8;
    }

    .welcome-info {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        align-items: flex-end;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        backdrop-filter: blur(10px);
        font-size: 0.9rem;
    }

    .welcome-decoration {
        position: absolute;
        top: 0;
        right: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }

    .decoration-circle {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
    }

    .circle-1 {
        width: 150px;
        height: 150px;
        top: -50px;
        right: -50px;
        animation: float 6s ease-in-out infinite;
    }

    .circle-2 {
        width: 100px;
        height: 100px;
        top: 50%;
        right: 10%;
        animation: float 8s ease-in-out infinite reverse;
    }

    .circle-3 {
        width: 80px;
        height: 80px;
        bottom: -20px;
        right: 20%;
        animation: float 7s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-20px) rotate(180deg); }
    }

    .stats-section {
        margin-bottom: 2rem;
    }

    .stats-card {
        border-radius: 30px;
        padding: 2rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: none;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        color: white;
    }

    /* Individual card gradient backgrounds */
    .stats-card:nth-child(1) .stats-card,
    .col-lg-3:nth-child(1) .stats-card {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    }

    .col-lg-3:nth-child(2) .stats-card {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    }

    .col-lg-3:nth-child(3) .stats-card {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .col-lg-3:nth-child(4) .stats-card {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--accent-gradient);
    }

    .stats-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--hover-shadow);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 1rem;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: white;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .groups-section {
        background: var(--primary-gradient);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
        margin-bottom: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .groups-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        pointer-events: none;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: black;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: relative;
        z-index: 2;
    }

    .section-title i {
        color: rgba(255, 255, 255, 0.9);
    }

    .section-count {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        position: relative;
        z-index: 2;
    }

    .count-badge {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 700;
        font-size: 1.1rem;
        min-width: 50px;
        text-align: center;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .count-label {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
        font-weight: 500;
    }

    .group-card {
        background: var(--bg-primary);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid var(--primary-200);
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .group-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--accent-gradient);
    }

    .group-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--hover-shadow);
        border-color: var(--accent-blue);
    }

    .group-card.active {
        border-color: var(--accent-blue);
        background: var(--accent-gradient);
        color: white;
        box-shadow: var(--focus-shadow), var(--hover-shadow);
        transform: translateY(-2px);
    }

    .group-card.active .group-title {
        color: white;
    }

    .group-card.active .detail-item {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
    }

    .group-card.active .detail-item:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .group-card.active .detail-value {
        color: white;
    }

    .group-card.active .detail-label {
        color: rgba(255, 255, 255, 0.8);
    }

    .group-card.active .detail-value i {
        color: rgba(255, 255, 255, 0.9);
    }

    .group-header {
        margin-bottom: 1.5rem;
        padding-right: 90px; /* Make space for the view button */
    }

    .group-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: #2d3748;
    }

    .group-dates {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
    }

    .date-item {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.85rem;
        color: #64748b;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .date-item i {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.8rem;
    }

    .group-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.875rem;
        background: var(--bg-muted);
        border-radius: 10px;
        transition: all 0.3s ease;
        border: 1px solid var(--primary-200);
    }

    .detail-item:hover {
        background: var(--primary-100);
        transform: translateY(-1px);
        box-shadow: var(--card-shadow);
        border-color: var(--primary-300);
    }

    .detail-value {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .detail-value i {
        color: var(--accent-blue);
        font-size: 0.85rem;
    }

    .detail-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge {
        padding: 0.375rem 0.875rem;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: 1px solid transparent;
    }

    .status-active {
        background: var(--success-gradient);
        color: white;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
    }

    .status-completed {
        background: var(--accent-gradient);
        color: white;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }

    .status-pending {
        background: var(--warning-gradient);
        color: white;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
    }

    .view-btn {
        background: var(--accent-gradient);
        color: white;
        border: none;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.8rem;
        transition: all 0.3s ease;
        position: absolute;
        top: 1.2rem;
        right: 1.2rem;
        z-index: 10;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }

    .view-btn:hover {
        transform: scale(1.05);
        color: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    }



    /* Quick Actions */
    .quick-actions-section {
        margin-bottom: 2rem;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 1rem;
        background: var(--bg-primary);
        border-radius: 12px;
        text-decoration: none;
        color: var(--text-primary);
        transition: all 0.3s ease;
        box-shadow: var(--card-shadow);
        border: 1px solid var(--primary-200);
        height: 100px;
        position: relative;
        overflow: hidden;
    }

    .quick-action-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--accent-gradient);
    }

    .quick-action-btn:hover {
        transform: translateY(-3px);
        box-shadow: var(--hover-shadow);
        color: var(--accent-blue);
        text-decoration: none;
    }

    .quick-action-btn i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--accent-blue);
    }

    .quick-action-btn span {
        font-size: 0.85rem;
        font-weight: 600;
        text-align: center;
    }

    .quick-action-btn.logout-btn::before {
        background: var(--danger-gradient);
    }

    .quick-action-btn.logout-btn:hover {
        color: var(--accent-red);
    }

    .quick-action-btn.logout-btn i {
        color: var(--accent-red);
    }

    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-active {
        background: #c6f6d5;
        color: #22543d;
    }

    .status-completed {
        background: #bee3f8;
        color: #2a4365;
    }

    .status-pending {
        background: #fef5e7;
        color: #744210;
    }

    .quick-actions {
        background: white;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--card-shadow);
    }

    .action-btn {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 25px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0.25rem;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .action-btn.secondary {
        background: var(--info-gradient);
    }

    .action-btn.warning {
        background: var(--warning-gradient);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 1rem 0;
        }

        .welcome-banner {
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .welcome-content {
            flex-direction: column;
            text-align: center;
            gap: 1.5rem;
        }

        .welcome-text .member-name {
            font-size: 1.5rem;
        }

        .welcome-info {
            align-items: center;
            width: 100%;
        }

        .info-item {
            justify-content: center;
            width: 100%;
            max-width: 250px;
        }

        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .section-count {
            align-self: flex-end;
        }

        .groups-section {
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .view-btn {
            top: 1rem;
            right: 1rem;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }

        .stats-card {
            margin-bottom: 0.5rem;
            padding: 1rem;
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .group-details {
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .detail-item {
            padding: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            margin: 0.25rem 0;
            width: 100%;
            justify-content: center;
        }
    }

    /* Animation for loading */
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Design Toggle Buttons */
    .design-toggle {
        display: flex;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem;
        border-radius: 25px;
        backdrop-filter: blur(10px);
    }

    .toggle-btn {
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.7);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .toggle-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .toggle-btn.active {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    /* Design Views */
    .design-view {
        display: none;
    }

    .design-view.active {
        display: block;
    }

    /* Enhanced Cards Design */
    .enhanced-group-card {
        background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 20px;
        padding: 0;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.4s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .enhanced-group-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #6366f1, #8b5cf6, #a855f7);
    }

    .enhanced-group-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .enhanced-group-card.active {
        border-color: #6366f1;
        background: linear-gradient(145deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
        transform: translateY(-5px);
    }

    .card-header-section {
        padding: 1.5rem 1.5rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .group-info .group-name {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: #1e293b;
    }

    .enhanced-group-card.active .group-name {
        color: white;
    }

    .group-amount {
        font-size: 1.1rem;
        font-weight: 600;
        color: #6366f1;
    }

    .enhanced-group-card.active .group-amount {
        color: rgba(255, 255, 255, 0.9);
    }

    .group-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.75rem;
    }

    .status-indicator {
        padding: 0.375rem 0.875rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .action-view-btn {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .action-view-btn:hover {
        transform: scale(1.1);
        color: white;
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
    }

    .progress-section {
        padding: 0 1.5rem 1rem;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .progress-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
    }

    .enhanced-group-card.active .progress-label {
        color: rgba(255, 255, 255, 0.8);
    }

    .progress-text {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6366f1;
    }

    .enhanced-group-card.active .progress-text {
        color: white;
    }

    .progress-bar-container {
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .enhanced-group-card.active .progress-bar-container {
        background: rgba(255, 255, 255, 0.2);
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .enhanced-group-card.active .progress-bar {
        background: linear-gradient(90deg, #ffffff, #f1f5f9);
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        padding: 1rem 1.5rem;
        background: rgba(248, 250, 252, 0.5);
        margin: 0;
    }

    .enhanced-group-card.active .details-grid {
        background: rgba(255, 255, 255, 0.1);
    }

    .detail-box {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: white;
        border-radius: 12px;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }

    .enhanced-group-card.active .detail-box {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .detail-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .detail-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: white;
    }

    .detail-icon.members { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
    .detail-icon.paid { background: linear-gradient(135deg, #10b981, #059669); }
    .detail-icon.received { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .detail-icon.duration { background: linear-gradient(135deg, #06b6d4, #0891b2); }

    .detail-content .detail-number {
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.125rem;
    }

    .enhanced-group-card.active .detail-number {
        color: white;
    }

    .detail-content .detail-text {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .enhanced-group-card.active .detail-text {
        color: rgba(255, 255, 255, 0.7);
    }

    .card-footer-section {
        padding: 1rem 1.5rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #e2e8f0;
        background: rgba(248, 250, 252, 0.3);
    }

    .enhanced-group-card.active .card-footer-section {
        border-top-color: rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.05);
    }

    .date-range {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 500;
    }

    .enhanced-group-card.active .date-range {
        color: rgba(255, 255, 255, 0.8);
    }

    .total-amount {
        font-size: 0.9rem;
        font-weight: 600;
        color: #6366f1;
    }

    .enhanced-group-card.active .total-amount {
        color: white;
    }

    /* Custom scrollbar */
    .groups-section::-webkit-scrollbar {
        width: 6px;
    }

    .groups-section::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .groups-section::-webkit-scrollbar-thumb {
        background: #667eea;
        border-radius: 10px;
    }

    .groups-section::-webkit-scrollbar-thumb:hover {
        background: #5a67d8;
    }

    /* List View Styles */
    .list-group-item {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .list-group-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        border-color: #6366f1;
    }

    .list-group-item.active {
        border-color: #6366f1;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
    }

    .list-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .list-title-section {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .list-group-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
        color: #1e293b;
    }

    .list-group-item.active .list-group-name {
        color: white;
    }

    .list-status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .list-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .list-amount {
        font-size: 1rem;
        font-weight: 600;
        color: #6366f1;
    }

    .list-group-item.active .list-amount {
        color: rgba(255, 255, 255, 0.9);
    }

    .list-view-btn {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .list-view-btn:hover {
        transform: scale(1.05);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    .list-progress {
        margin-bottom: 1rem;
    }

    .progress-info-list {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .list-group-item.active .progress-info-list {
        color: rgba(255, 255, 255, 0.9);
    }

    .progress-bar-list {
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .list-group-item.active .progress-bar-list {
        background: rgba(255, 255, 255, 0.2);
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .list-group-item.active .progress-fill {
        background: linear-gradient(90deg, #ffffff, #f1f5f9);
    }

    .list-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .list-detail-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        color: #64748b;
    }

    .list-group-item.active .list-detail-item {
        color: rgba(255, 255, 255, 0.8);
    }

    .list-detail-item i {
        color: #6366f1;
        width: 16px;
    }

    .list-group-item.active .list-detail-item i {
        color: rgba(255, 255, 255, 0.9);
    }

    /* Timeline View Styles */
    .timeline-container {
        position: relative;
        padding-left: 2rem;
    }

    .timeline-container::before {
        content: '';
        position: absolute;
        left: 1rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(180deg, #6366f1, #8b5cf6, #a855f7);
    }

    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
        cursor: pointer;
    }

    .timeline-marker {
        position: absolute;
        left: -2.75rem;
        top: 0.5rem;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1rem;
        z-index: 2;
        border: 3px solid white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .timeline-marker.status-active { background: linear-gradient(135deg, #10b981, #059669); }
    .timeline-marker.status-completed { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
    .timeline-marker.status-pending { background: linear-gradient(135deg, #f59e0b, #d97706); }

    .timeline-content {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        margin-left: 1rem;
    }

    .timeline-item:hover .timeline-content {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        border-color: #6366f1;
    }

    .timeline-item.active .timeline-content {
        border-color: #6366f1;
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
    }

    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }

    .timeline-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
        color: #1e293b;
    }

    .timeline-item.active .timeline-title {
        color: white;
    }

    .timeline-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.5rem;
    }

    .timeline-date {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 500;
    }

    .timeline-item.active .timeline-date {
        color: rgba(255, 255, 255, 0.8);
    }

    .timeline-status {
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .timeline-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .stat-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }

    .timeline-item.active .stat-item {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .stat-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 500;
    }

    .timeline-item.active .stat-label {
        color: rgba(255, 255, 255, 0.8);
    }

    .stat-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: #1e293b;
    }

    .timeline-item.active .stat-value {
        color: white;
    }

    .timeline-progress {
        margin-bottom: 1rem;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .timeline-item.active .progress-header {
        color: rgba(255, 255, 255, 0.9);
    }

    .timeline-progress-bar {
        height: 8px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .timeline-item.active .timeline-progress-bar {
        background: rgba(255, 255, 255, 0.2);
    }

    .timeline-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .timeline-item.active .timeline-progress-fill {
        background: linear-gradient(90deg, #ffffff, #f1f5f9);
    }

    .timeline-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }

    .timeline-item.active .timeline-footer {
        border-top-color: rgba(255, 255, 255, 0.2);
    }

    .timeline-duration {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 500;
    }

    .timeline-item.active .timeline-duration {
        color: rgba(255, 255, 255, 0.8);
    }

    .timeline-view-btn {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .timeline-view-btn:hover {
        transform: scale(1.05);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    /* Table View Styles */
    .table-responsive {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
    }

    .groups-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin: 0;
    }

    .groups-table thead {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
    }

    .groups-table th {
        padding: 1rem 0.75rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
    }

    .groups-table tbody tr {
        border-bottom: 1px solid #e2e8f0;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .groups-table tbody tr:hover {
        background: #f8fafc;
        transform: scale(1.01);
    }

    .groups-table tbody tr.selected {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: white;
    }

    .groups-table td {
        padding: 1rem 0.75rem;
        vertical-align: middle;
        border: none;
    }

    .table-group-name {
        font-weight: 600;
        color: #1e293b;
    }

    .groups-table tbody tr.selected .table-group-name {
        color: white;
    }

    .table-status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .table-members {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
    }

    .groups-table tbody tr.selected .table-members {
        color: rgba(255, 255, 255, 0.9);
    }

    .table-amount {
        font-weight: 600;
        color: #6366f1;
    }

    .groups-table tbody tr.selected .table-amount {
        color: white;
    }

    .table-progress {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 120px;
    }

    .table-progress-bar {
        height: 6px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .groups-table tbody tr.selected .table-progress-bar {
        background: rgba(255, 255, 255, 0.2);
    }

    .table-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #6366f1, #8b5cf6);
        border-radius: 10px;
        transition: width 0.6s ease;
    }

    .groups-table tbody tr.selected .table-progress-fill {
        background: linear-gradient(90deg, #ffffff, #f1f5f9);
    }

    .table-progress-text {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 500;
    }

    .groups-table tbody tr.selected .table-progress-text {
        color: rgba(255, 255, 255, 0.8);
    }

    .table-paid, .table-received {
        font-weight: 600;
        color: #1e293b;
    }

    .groups-table tbody tr.selected .table-paid,
    .groups-table tbody tr.selected .table-received {
        color: white;
    }

    .table-duration {
        color: #64748b;
        font-size: 0.85rem;
    }

    .groups-table tbody tr.selected .table-duration {
        color: rgba(255, 255, 255, 0.8);
    }

    .table-view-btn {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        border: none;
        padding: 0.5rem;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
    }

    .table-view-btn:hover {
        transform: scale(1.1);
        color: white;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    /* Responsive Design for New Views */
    @media (max-width: 768px) {
        .design-toggle {
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .toggle-btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        .enhanced-group-card {
            margin-bottom: 1rem;
        }

        .details-grid {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .list-details {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        .timeline-container {
            padding-left: 1.5rem;
        }

        .timeline-container::before {
            left: 0.75rem;
        }

        .timeline-marker {
            left: -2.25rem;
            width: 2.5rem;
            height: 2.5rem;
        }

        .timeline-stats {
            grid-template-columns: 1fr;
        }

        .groups-table {
            font-size: 0.85rem;
        }

        .groups-table th,
        .groups-table td {
            padding: 0.75rem 0.5rem;
        }

        /* Hide some columns on mobile */
        .groups-table th:nth-child(6),
        .groups-table th:nth-child(7),
        .groups-table th:nth-child(8),
        .groups-table td:nth-child(6),
        .groups-table td:nth-child(7),
        .groups-table td:nth-child(8) {
            display: none;
        }
    }

    @media (max-width: 576px) {
        .section-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .design-toggle {
            justify-content: center;
        }

        .card-header-section {
            flex-direction: column;
            gap: 1rem;
        }

        .group-actions {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
        }

        .list-header {
            flex-direction: column;
            gap: 1rem;
        }

        .list-actions {
            justify-content: space-between;
            width: 100%;
        }

        .timeline-content {
            margin-left: 0.5rem;
        }

        /* Show only essential columns on very small screens */
        .groups-table th:nth-child(3),
        .groups-table th:nth-child(4),
        .groups-table td:nth-child(3),
        .groups-table td:nth-child(4) {
            display: none;
        }
    }
</style>

<div class="dashboard-container">
    <div class="container-fluid">
        <!-- Welcome Banner -->
        <div class="welcome-banner fade-in">
            <div class="welcome-content">
                <div class="welcome-text">
                    <div class="greeting">Good <?= date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening') ?></div>
                    <div class="member-name"><?= htmlspecialchars($member['member_name']) ?>!</div>
                    <div class="welcome-subtitle">Ready to manage your BC groups and payments</div>
                </div>
                <div class="welcome-info">
                    <div class="info-item">
                        <i class="fas fa-rupee-sign"></i>
                        <span>Monthly Payment: <?= formatCurrency($totalPendingPayments) ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span>Last Login: <?= date('M d, Y g:i A') ?></span>
                    </div>
                </div>
            </div>
            <div class="welcome-decoration">
                <div class="decoration-circle circle-1"></div>
                <div class="decoration-circle circle-2"></div>
                <div class="decoration-circle circle-3"></div>
            </div>
        </div>

      

        <!-- Financial Summary Stats -->
        <div class="stats-section">
            <div class="row">
                            <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stats-card fade-in">
                        <div class="stat-icon paid">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-value"><?= count($memberGroups) ?></div>
                        <div class="stat-label">My Groups</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stats-card fade-in">
                        <div class="stat-icon paid">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-value"><?= formatCurrency($totalPaidAmount) ?></div>
                        <div class="stat-label">Total Paid</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stats-card fade-in">
                        <div class="stat-icon received">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-value"><?= formatCurrency($totalReceivedAmount) ?></div>
                        <div class="stat-label">Total Received</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                    <div class="stats-card fade-in">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?= formatCurrency($totalPendingPayments) ?></div>
                        <div class="stat-label">Pending Payments</div>
                    </div>
                </div>
                
            </div>
        </div>



        <!-- Groups Section - New Design Options -->
        <div class="groups-section fade-in">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-users"></i>
                    My Groups
                </h2>
                <div class="section-count">
                    <span class="count-badge"><?= count($memberGroups) ?></span>
                    <span class="count-label">Total Groups</span>
                </div>
                <!-- Design Toggle Buttons -->
                <div class="design-toggle">
                    <button class="toggle-btn active" onclick="switchDesign('cards')" data-design="cards">
                        <i class="fas fa-th-large"></i> Cards
                    </button>
                    <button class="toggle-btn" onclick="switchDesign('list')" data-design="list">
                        <i class="fas fa-list"></i> List
                    </button>
                    <button class="toggle-btn" onclick="switchDesign('timeline')" data-design="timeline">
                        <i class="fas fa-timeline"></i> Timeline
                    </button>
                    <button class="toggle-btn" onclick="switchDesign('table')" data-design="table">
                        <i class="fas fa-table"></i> Table
                    </button>
                </div>
            </div>

            <?php if (empty($memberGroups)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Groups Found</h4>
                    <p class="text-muted">You are not currently a member of any groups.</p>
                </div>
            <?php else: ?>

                <!-- Design 1: Enhanced Cards View (Default) -->
                <div id="cards-view" class="design-view active">
                    <div class="row">
                        <?php foreach ($memberGroups as $group):
                            // Get group status and calculations
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE group_id = ?");
                            $stmt->execute([$group['id']]);
                            $completedMonths = $stmt->fetchColumn();

                            $groupStatus = 'active';
                            if ($completedMonths >= $group['total_members']) {
                                $groupStatus = 'completed';
                            } elseif ($completedMonths == 0) {
                                $groupStatus = 'pending';
                            }

                            // Get actual member count
                            $stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND status = 'active'");
                            $stmt->execute([$group['id']]);
                            $actualMemberCount = $stmt->fetchColumn();

                            // Calculate end date
                            $startDate = new DateTime($group['start_date']);
                            $endDate = clone $startDate;
                            $endDate->add(new DateInterval('P' . ($group['total_members'] - 1) . 'M'));

                            // Get member's payment status for this group
                            $stmt = $pdo->prepare("
                                SELECT COALESCE(SUM(payment_amount), 0) as total_paid
                                FROM member_payments
                                WHERE member_id = ? AND group_id = ? AND payment_status = 'paid'
                            ");
                            $stmt->execute([$member['id'], $group['id']]);
                            $memberPaid = $stmt->fetchColumn();

                            // Get member's received amount
                            $stmt = $pdo->prepare("
                                SELECT COALESCE(SUM(net_payable), 0) as total_received
                                FROM monthly_bids
                                WHERE taken_by_member_id = ? AND group_id = ?
                            ");
                            $stmt->execute([$member['id'], $group['id']]);
                            $memberReceived = $stmt->fetchColumn();

                            // Calculate progress percentage
                            $progressPercentage = ($group['total_members'] > 0) ? ($completedMonths / $group['total_members']) * 100 : 0;
                        ?>
                            <div class="col-lg-6 col-md-12 mb-4">
                                <div class="enhanced-group-card <?= $group['id'] == $currentGroupId ? 'active' : '' ?>"
                                     onclick="selectGroup(<?= $group['id'] ?>)">

                                    <!-- Card Header -->
                                    <div class="card-header-section">
                                        <div class="group-info">
                                            <h3 class="group-name"><?= htmlspecialchars($group['group_name']) ?></h3>
                                            <div class="group-amount">₹<?= number_format($group['monthly_contribution']) ?>/month</div>
                                        </div>
                                        <div class="group-actions">
                                            <span class="status-indicator status-<?= $groupStatus ?>"><?= ucfirst($groupStatus) ?></span>
                                            <a href="group_view.php?group_id=<?= $group['id'] ?>" class="action-view-btn" onclick="event.stopPropagation();">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="progress-section">
                                        <div class="progress-info">
                                            <span class="progress-label">Progress</span>
                                            <span class="progress-text"><?= $completedMonths ?>/<?= $group['total_members'] ?> months</span>
                                        </div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?= $progressPercentage ?>%"></div>
                                        </div>
                                    </div>

                                    <!-- Details Grid -->
                                    <div class="details-grid">
                                        <div class="detail-box">
                                            <div class="detail-icon members">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-number"><?= $actualMemberCount ?></div>
                                                <div class="detail-text">Members</div>
                                            </div>
                                        </div>

                                        <div class="detail-box">
                                            <div class="detail-icon paid">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-number">₹<?= number_format($memberPaid) ?></div>
                                                <div class="detail-text">You Paid</div>
                                            </div>
                                        </div>

                                        <div class="detail-box">
                                            <div class="detail-icon received">
                                                <i class="fas fa-hand-holding-usd"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-number">₹<?= number_format($memberReceived) ?></div>
                                                <div class="detail-text">You Received</div>
                                            </div>
                                        </div>

                                        <div class="detail-box">
                                            <div class="detail-icon duration">
                                                <i class="fas fa-calendar-alt"></i>
                                            </div>
                                            <div class="detail-content">
                                                <div class="detail-number"><?= date('M Y', strtotime($group['start_date'])) ?></div>
                                                <div class="detail-text">Started</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Footer with dates -->
                                    <div class="card-footer-section">
                                        <div class="date-range">
                                            <i class="fas fa-calendar-check"></i>
                                            <span><?= date('M Y', strtotime($group['start_date'])) ?> - <?= $endDate->format('M Y') ?></span>
                                        </div>
                                        <div class="total-amount">
                                            Total: ₹<?= number_format($group['monthly_contribution'] * $group['total_members']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Design 2: List View -->
                <div id="list-view" class="design-view">
                    <?php foreach ($memberGroups as $group):
                        // Same calculations as above
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE group_id = ?");
                        $stmt->execute([$group['id']]);
                        $completedMonths = $stmt->fetchColumn();

                        $groupStatus = 'active';
                        if ($completedMonths >= $group['total_members']) {
                            $groupStatus = 'completed';
                        } elseif ($completedMonths == 0) {
                            $groupStatus = 'pending';
                        }

                        $stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND status = 'active'");
                        $stmt->execute([$group['id']]);
                        $actualMemberCount = $stmt->fetchColumn();

                        $startDate = new DateTime($group['start_date']);
                        $endDate = clone $startDate;
                        $endDate->add(new DateInterval('P' . ($group['total_members'] - 1) . 'M'));

                        $stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) as total_paid FROM member_payments WHERE member_id = ? AND group_id = ? AND payment_status = 'paid'");
                        $stmt->execute([$member['id'], $group['id']]);
                        $memberPaid = $stmt->fetchColumn();

                        $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_payable), 0) as total_received FROM monthly_bids WHERE taken_by_member_id = ? AND group_id = ?");
                        $stmt->execute([$member['id'], $group['id']]);
                        $memberReceived = $stmt->fetchColumn();

                        $progressPercentage = ($group['total_members'] > 0) ? ($completedMonths / $group['total_members']) * 100 : 0;
                    ?>
                        <div class="list-group-item <?= $group['id'] == $currentGroupId ? 'active' : '' ?>" onclick="selectGroup(<?= $group['id'] ?>)">
                            <div class="list-item-content">
                                <div class="list-header">
                                    <div class="list-title-section">
                                        <h4 class="list-group-name"><?= htmlspecialchars($group['group_name']) ?></h4>
                                        <span class="list-status-badge status-<?= $groupStatus ?>"><?= ucfirst($groupStatus) ?></span>
                                    </div>
                                    <div class="list-actions">
                                        <span class="list-amount">₹<?= number_format($group['monthly_contribution']) ?>/month</span>
                                        <a href="group_view.php?group_id=<?= $group['id'] ?>" class="list-view-btn" onclick="event.stopPropagation();">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>

                                <div class="list-progress">
                                    <div class="progress-info-list">
                                        <span><?= $completedMonths ?>/<?= $group['total_members'] ?> months completed</span>
                                        <span><?= number_format($progressPercentage, 1) ?>%</span>
                                    </div>
                                    <div class="progress-bar-list">
                                        <div class="progress-fill" style="width: <?= $progressPercentage ?>%"></div>
                                    </div>
                                </div>

                                <div class="list-details">
                                    <div class="list-detail-item">
                                        <i class="fas fa-users"></i>
                                        <span><?= $actualMemberCount ?> Members</span>
                                    </div>
                                    <div class="list-detail-item">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <span>Paid: ₹<?= number_format($memberPaid) ?></span>
                                    </div>
                                    <div class="list-detail-item">
                                        <i class="fas fa-hand-holding-usd"></i>
                                        <span>Received: ₹<?= number_format($memberReceived) ?></span>
                                    </div>
                                    <div class="list-detail-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span><?= date('M Y', strtotime($group['start_date'])) ?> - <?= $endDate->format('M Y') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Design 3: Timeline View -->
                <div id="timeline-view" class="design-view">
                    <div class="timeline-container">
                        <?php foreach ($memberGroups as $index => $group):
                            // Same calculations
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE group_id = ?");
                            $stmt->execute([$group['id']]);
                            $completedMonths = $stmt->fetchColumn();

                            $groupStatus = 'active';
                            if ($completedMonths >= $group['total_members']) {
                                $groupStatus = 'completed';
                            } elseif ($completedMonths == 0) {
                                $groupStatus = 'pending';
                            }

                            $stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND status = 'active'");
                            $stmt->execute([$group['id']]);
                            $actualMemberCount = $stmt->fetchColumn();

                            $startDate = new DateTime($group['start_date']);
                            $endDate = clone $startDate;
                            $endDate->add(new DateInterval('P' . ($group['total_members'] - 1) . 'M'));

                            $stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) as total_paid FROM member_payments WHERE member_id = ? AND group_id = ? AND payment_status = 'paid'");
                            $stmt->execute([$member['id'], $group['id']]);
                            $memberPaid = $stmt->fetchColumn();

                            $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_payable), 0) as total_received FROM monthly_bids WHERE taken_by_member_id = ? AND group_id = ?");
                            $stmt->execute([$member['id'], $group['id']]);
                            $memberReceived = $stmt->fetchColumn();

                            $progressPercentage = ($group['total_members'] > 0) ? ($completedMonths / $group['total_members']) * 100 : 0;
                        ?>
                            <div class="timeline-item <?= $group['id'] == $currentGroupId ? 'active' : '' ?>" onclick="selectGroup(<?= $group['id'] ?>)">
                                <div class="timeline-marker status-<?= $groupStatus ?>">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <h4 class="timeline-title"><?= htmlspecialchars($group['group_name']) ?></h4>
                                        <div class="timeline-meta">
                                            <span class="timeline-date"><?= date('M Y', strtotime($group['start_date'])) ?></span>
                                            <span class="timeline-status status-<?= $groupStatus ?>"><?= ucfirst($groupStatus) ?></span>
                                        </div>
                                    </div>

                                    <div class="timeline-body">
                                        <div class="timeline-stats">
                                            <div class="stat-group">
                                                <div class="stat-item">
                                                    <span class="stat-label">Monthly Contribution</span>
                                                    <span class="stat-value">₹<?= number_format($group['monthly_contribution']) ?></span>
                                                </div>
                                                <div class="stat-item">
                                                    <span class="stat-label">Members</span>
                                                    <span class="stat-value"><?= $actualMemberCount ?></span>
                                                </div>
                                            </div>
                                            <div class="stat-group">
                                                <div class="stat-item">
                                                    <span class="stat-label">You Paid</span>
                                                    <span class="stat-value">₹<?= number_format($memberPaid) ?></span>
                                                </div>
                                                <div class="stat-item">
                                                    <span class="stat-label">You Received</span>
                                                    <span class="stat-value">₹<?= number_format($memberReceived) ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="timeline-progress">
                                            <div class="progress-header">
                                                <span>Progress: <?= $completedMonths ?>/<?= $group['total_members'] ?> months</span>
                                                <span><?= number_format($progressPercentage, 1) ?>%</span>
                                            </div>
                                            <div class="timeline-progress-bar">
                                                <div class="timeline-progress-fill" style="width: <?= $progressPercentage ?>%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="timeline-footer">
                                        <span class="timeline-duration">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?= date('M Y', strtotime($group['start_date'])) ?> - <?= $endDate->format('M Y') ?>
                                        </span>
                                        <a href="group_view.php?group_id=<?= $group['id'] ?>" class="timeline-view-btn" onclick="event.stopPropagation();">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Design 4: Table View -->
                <div id="table-view" class="design-view">
                    <div class="table-responsive">
                        <table class="groups-table">
                            <thead>
                                <tr>
                                    <th>Group Name</th>
                                    <th>Status</th>
                                    <th>Members</th>
                                    <th>Monthly</th>
                                    <th>Progress</th>
                                    <th>You Paid</th>
                                    <th>You Received</th>
                                    <th>Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($memberGroups as $group):
                                    // Same calculations
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE group_id = ?");
                                    $stmt->execute([$group['id']]);
                                    $completedMonths = $stmt->fetchColumn();

                                    $groupStatus = 'active';
                                    if ($completedMonths >= $group['total_members']) {
                                        $groupStatus = 'completed';
                                    } elseif ($completedMonths == 0) {
                                        $groupStatus = 'pending';
                                    }

                                    $stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM group_members WHERE group_id = ? AND status = 'active'");
                                    $stmt->execute([$group['id']]);
                                    $actualMemberCount = $stmt->fetchColumn();

                                    $startDate = new DateTime($group['start_date']);
                                    $endDate = clone $startDate;
                                    $endDate->add(new DateInterval('P' . ($group['total_members'] - 1) . 'M'));

                                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) as total_paid FROM member_payments WHERE member_id = ? AND group_id = ? AND payment_status = 'paid'");
                                    $stmt->execute([$member['id'], $group['id']]);
                                    $memberPaid = $stmt->fetchColumn();

                                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_payable), 0) as total_received FROM monthly_bids WHERE taken_by_member_id = ? AND group_id = ?");
                                    $stmt->execute([$member['id'], $group['id']]);
                                    $memberReceived = $stmt->fetchColumn();

                                    $progressPercentage = ($group['total_members'] > 0) ? ($completedMonths / $group['total_members']) * 100 : 0;
                                ?>
                                    <tr class="table-row <?= $group['id'] == $currentGroupId ? 'selected' : '' ?>" onclick="selectGroup(<?= $group['id'] ?>)">
                                        <td>
                                            <div class="table-group-name">
                                                <strong><?= htmlspecialchars($group['group_name']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="table-status-badge status-<?= $groupStatus ?>"><?= ucfirst($groupStatus) ?></span>
                                        </td>
                                        <td>
                                            <div class="table-members">
                                                <i class="fas fa-users"></i>
                                                <?= $actualMemberCount ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-amount">
                                                ₹<?= number_format($group['monthly_contribution']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-progress">
                                                <div class="table-progress-bar">
                                                    <div class="table-progress-fill" style="width: <?= $progressPercentage ?>%"></div>
                                                </div>
                                                <span class="table-progress-text"><?= $completedMonths ?>/<?= $group['total_members'] ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-paid">
                                                ₹<?= number_format($memberPaid) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-received">
                                                ₹<?= number_format($memberReceived) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-duration">
                                                <small><?= date('M Y', strtotime($group['start_date'])) ?> - <?= $endDate->format('M Y') ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="group_view.php?group_id=<?= $group['id'] ?>" class="table-view-btn" onclick="event.stopPropagation();">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>


          <!-- Quick Actions -->
        <div class="quick-actions-section fade-in">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="fas fa-bolt"></i>
                    Quick Actions
                </h5>
            </div>
            <div class="row">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <a href="payment.php" class="quick-action-btn">
                        <i class="fas fa-credit-card"></i>
                        <span>Make Payment</span>
                    </a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <a href="bidding.php" class="quick-action-btn">
                        <i class="fas fa-gavel"></i>
                        <span>Place Bid</span>
                    </a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <a href="group_view.php" class="quick-action-btn">
                        <i class="fas fa-users"></i>
                        <span>View Groups</span>
                    </a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <a href="edit_profile.php" class="quick-action-btn">
                        <i class="fas fa-user-edit"></i>
                        <span>Edit Profile</span>
                    </a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <a href="change_password.php" class="quick-action-btn">
                        <i class="fas fa-key"></i>
                        <span>Change Password</span>
                    </a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                    <a href="../auth/member_login.php?logout=1" class="quick-action-btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Design switching functionality
function switchDesign(designType) {
    // Update toggle buttons
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-design="${designType}"]`).classList.add('active');

    // Update design views
    document.querySelectorAll('.design-view').forEach(view => {
        view.classList.remove('active');
    });
    document.getElementById(`${designType}-view`).classList.add('active');

    // Store preference in localStorage
    localStorage.setItem('preferredGroupDesign', designType);

    // Add animation effect
    const activeView = document.getElementById(`${designType}-view`);
    activeView.style.opacity = '0';
    activeView.style.transform = 'translateY(20px)';

    setTimeout(() => {
        activeView.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        activeView.style.opacity = '1';
        activeView.style.transform = 'translateY(0)';
    }, 50);
}

function selectGroup(groupId) {
    // Update URL with selected group
    const url = new URL(window.location);
    url.searchParams.set('group_id', groupId);
    window.history.pushState({}, '', url);

    // Update active state for all design views
    const selectors = [
        '.group-card',
        '.enhanced-group-card',
        '.list-group-item',
        '.timeline-item',
        '.table-row'
    ];

    selectors.forEach(selector => {
        document.querySelectorAll(selector).forEach(item => {
            item.classList.remove('active', 'selected');
        });
    });

    // Add active class to current target
    if (event.currentTarget.classList.contains('table-row')) {
        event.currentTarget.classList.add('selected');
    } else {
        event.currentTarget.classList.add('active');
    }

    // You can add more functionality here like loading group-specific data
    console.log('Selected group:', groupId);
}

// Add smooth scrolling and animations
document.addEventListener('DOMContentLoaded', function() {
    // Load preferred design from localStorage
    const preferredDesign = localStorage.getItem('preferredGroupDesign') || 'cards';
    switchDesign(preferredDesign);

    // Add fade-in animation to elements
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe all fade-in elements
    document.querySelectorAll('.fade-in').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // Add hover effects to cards (all designs)
    const cardSelectors = [
        '.group-card',
        '.enhanced-group-card',
        '.list-group-item',
        '.timeline-item'
    ];

    cardSelectors.forEach(selector => {
        document.querySelectorAll(selector).forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.style.transform.includes('scale')) {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                }
            });

            card.addEventListener('mouseleave', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'translateY(0) scale(1)';
                }
            });
        });
    });

    // Add click ripple effect
    document.querySelectorAll('.action-btn, .view-btn, .list-view-btn, .timeline-view-btn, .table-view-btn, .action-view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');

            this.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Add keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key >= '1' && e.key <= '4') {
            const designs = ['cards', 'list', 'timeline', 'table'];
            const designIndex = parseInt(e.key) - 1;
            if (designs[designIndex]) {
                switchDesign(designs[designIndex]);
            }
        }
    });

    // Add smooth transitions for design switching
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const design = this.getAttribute('data-design');
            switchDesign(design);
        });
    });
});
</script>

<style>
/* Ripple effect */
.ripple {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.6);
    transform: scale(0);
    animation: ripple-animation 0.6s linear;
    pointer-events: none;
}

@keyframes ripple-animation {
    to {
        transform: scale(4);
        opacity: 0;
    }
}

/* Additional responsive improvements */
@media (max-width: 576px) {
    .group-details {
        grid-template-columns: 1fr 1fr;
    }

    .detail-item {
        padding: 0.5rem 0.25rem;
    }

    .detail-value {
        font-size: 0.9rem;
    }

    .section-title {
        font-size: 1.25rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>


