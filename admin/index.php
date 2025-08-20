<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

if (isset($_GET['logout'])) {
    logout();
}

$groups = getAllGroups();

// Get dashboard statistics
$pdo = getDB();

// Total statistics
$totalGroups = count($groups);
$activeGroups = count(array_filter($groups, fn($g) => $g['status'] === 'active'));
$completedGroups = count(array_filter($groups, fn($g) => $g['status'] === 'completed'));

// Total members across all groups
$stmt = $pdo->query("SELECT COUNT(*) FROM members");
$totalMembers = $stmt->fetchColumn();

// Total money collected
$stmt = $pdo->query("SELECT SUM(payment_amount) FROM member_payments WHERE payment_status = 'paid'");
$totalCollected = $stmt->fetchColumn() ?: 0;

// Total money distributed
$stmt = $pdo->query("SELECT SUM(net_payable) FROM monthly_bids");
$totalDistributed = $stmt->fetchColumn() ?: 0;

// Monthly collection data for chart
$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(payment_amount) as total_amount
    FROM member_payments
    WHERE payment_status = 'paid' AND payment_date IS NOT NULL
    GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$monthlyData = $stmt->fetchAll();

// Group progress data
$groupProgressData = [];
foreach ($groups as $group) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE group_id = ?");
    $stmt->execute([$group['id']]);
    $completedMonths = $stmt->fetchColumn();

    $groupProgressData[] = [
        'name' => $group['group_name'],
        'completed' => $completedMonths,
        'total' => $group['total_members'],
        'percentage' => ($completedMonths / $group['total_members']) * 100
    ];
}

// Recent activities
$stmt = $pdo->query("
    SELECT
        'payment' as type,
        mp.payment_date as date,
        m.member_name,
        g.group_name,
        mp.payment_amount as amount
    FROM member_payments mp
    JOIN members m ON mp.member_id = m.id
    JOIN bc_groups g ON mp.group_id = g.id
    WHERE mp.payment_date IS NOT NULL

    UNION ALL

    SELECT
        'bid' as type,
        mb.payment_date as date,
        m.member_name,
        g.group_name,
        mb.net_payable as amount
    FROM monthly_bids mb
    JOIN members m ON mb.taken_by_member_id = m.id
    JOIN bc_groups g ON mb.group_id = g.id
    WHERE mb.payment_date IS NOT NULL

    ORDER BY date DESC
    LIMIT 10
");
$recentActivities = $stmt->fetchAll();

// Set page title for header
$page_title = 'Admin Dashboard';

// Include header
require_once 'includes/header.php';
?>

<style>
    /* Dashboard-specific styles */
    .dashboard-header {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 25px;
        padding: 3rem 2.5rem;
        margin-bottom: 3rem;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        position: relative;
        overflow: hidden;
        z-index: 1;
    }

    .dashboard-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #f5576c);
        background-size: 300% 100%;
        animation: gradientShift 3s ease infinite;
    }

    @keyframes gradientShift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    .dashboard-title {
        font-size: 3rem;
        font-weight: 900;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .dashboard-subtitle {
        color: #64748b;
        font-size: 1.2rem;
        margin-bottom: 0;
        font-weight: 500;
    }

    .dashboard-actions {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }

    /* Enhanced Stat Cards */
    .stat-card-modern-enhanced {
        background: white;
        border-radius: 20px;
        padding: 2.5rem 2rem;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: none;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        cursor: pointer;
        min-height: 280px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        z-index: 2;
    }

    .stat-card-modern-enhanced::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .stat-card-modern-enhanced:hover::before {
        opacity: 1;
    }

    .stat-card-modern-enhanced:hover {
        transform: translateY(-8px) scale(1.01);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        z-index: 3;
    }

    .stat-card-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }

    .stat-card-secondary {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border: none;
    }

    .stat-card-accent {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        border: none;
    }

    .stat-card-info {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        border: none;
    }

        .stat-icon-modern {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            animation: float 3s ease-in-out infinite;
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.1);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .stat-number-modern {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            line-height: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-label-modern {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-sublabel-modern {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
            line-height: 1.4;
        }

        .stat-action-modern {
            font-size: 0.9rem;
            opacity: 0.95;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            transition: all 0.3s ease;
        }

        .stat-action-modern:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        /* Quick Actions Section */
        .quick-actions {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .quick-actions::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .quick-actions h5 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }

        .quick-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.75rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #475569;
            text-decoration: none;
            border-radius: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .quick-action-btn:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            border-color: transparent;
        }

        /* Groups Section */
        .groups-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 25px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .groups-header {
            background: linear-gradient(135deg, #f1f5f9 0%, #ffffff 100%);
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }

        .groups-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .groups-header h4 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .group-card-modern {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .group-card-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .group-card-modern:hover::before {
            opacity: 1;
        }

        .group-card-modern:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
            z-index: 3;
        }

        .group-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .group-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .group-meta {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .group-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .group-action-btn {
            padding: 0.75rem 1.25rem;
            font-size: 0.9rem;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .group-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Chart Container */
        .chart-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .chart-header {
            margin-bottom: 2rem;
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .chart-subtitle {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 2rem 1.5rem;
                text-align: center;
            }

            .dashboard-title {
                font-size: 2.5rem;
            }

            .dashboard-actions {
                justify-content: center;
                margin-top: 1.5rem;
                gap: 1rem;
            }

            .stat-card-modern-enhanced {
                padding: 2rem 1.5rem;
                min-height: 240px;
            }

            .stat-number-modern {
                font-size: 3rem;
            }

            .stat-icon-modern {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }

            .quick-actions {
                padding: 1.5rem;
            }

            .quick-action-btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.85rem;
            }

            .group-card-modern {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .dashboard-title {
                font-size: 2rem;
            }

            .stat-number-modern {
                font-size: 2.5rem;
            }

            .dashboard-actions {
                flex-direction: column;
                align-items: center;
            }

            .quick-actions .d-flex {
                flex-direction: column;
                gap: 1rem;
            }
        }

        .dashboard-card {
            transition: transform 0.2s;
            position: relative;
            z-index: 1;
            margin-bottom: 20px;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
        }

        /* Prevent overlapping of pending payments section */
        #pendingPaymentsContent {
            position: relative;
            z-index: 5;
        }

        /* Ensure proper spacing between sections */
        .row.mb-5 {
            margin-bottom: 3rem !important;
        }

        /* Language switcher styling */
        .language-flag {
            font-size: 1.2em;
            margin-right: 0.5rem;
        }



        .chart-container {
            position: relative;
            height: 300px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stat-card-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Interactive button effects */
        .btn-interactive {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-interactive:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-interactive:active {
            transform: translateY(0);
        }

        .btn-interactive::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-interactive:hover::before {
            left: 100%;
        }

        /* Enhanced navbar */
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        /* Modern Professional Navbar Styling */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            border-bottom: none;
            padding: 0.75rem 0;
            backdrop-filter: blur(10px);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            color: #fbbf24 !important;
        }

        .navbar-brand .fas {
            font-size: 1.75rem;
            animation: pulse-gentle 2s infinite;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 600;
            padding: 0.75rem 1.25rem !important;
            margin: 0 0.25rem;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .navbar-nav .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .navbar-nav .nav-link:hover::before {
            left: 100%;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }

        .navbar-text {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            margin: 0 0.75rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(5px);
        }

        /* Modern Dropdown Styling */
        .dropdown-menu {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 0.75rem 0;
            margin-top: 0.5rem;
            min-width: 200px;
            animation: dropdownFadeIn 0.3s ease-out;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: #374151;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }

        .dropdown-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            font-size: 0.9rem;
        }

        .dropdown-divider {
            margin: 0.5rem 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Language switcher styling */
        .language-flag {
            font-size: 1.2em;
            margin-right: 0.5rem;
        }

        .dropdown-toggle::after {
            margin-left: 0.5rem;
            transition: transform 0.3s ease;
        }

        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        /* Profile Avatar Styling */
        .avatar-sm {
            width: 40px;
            height: 40px;
        }

        .dropdown-item-text {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 10px;
            margin: 0.5rem;
        }

        .dropdown-item.text-danger:hover {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Notification Dropdown Styling */
        .dropdown-header {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 10px;
            margin: 0.5rem;
            font-weight: 600;
            color: #1e293b;
        }



        /* Mobile navbar improvements */
        .navbar-toggler {
            padding: 0.5rem;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Responsive navbar adjustments */
        @media (max-width: 991.98px) {
            .navbar {
                padding: 0.5rem 0;
            }

            .navbar-nav {
                padding-top: 1rem;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 15px;
                margin-top: 1rem;
                backdrop-filter: blur(10px);
            }

            .navbar-nav .nav-link {
                padding: 1rem 1.5rem;
                margin: 0.25rem 0.5rem;
                border-radius: 10px;
            }

            .navbar-brand {
                font-size: 1.3rem;
            }

            .dropdown-menu {
                background: rgba(255, 255, 255, 0.98);
                margin-top: 0.25rem;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 1.1rem;
            }

            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .navbar-text {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }
        }

        /* Icon animations */
        .fas, .far {
            transition: all 0.3s ease;
        }

        .btn:hover .fas,
        .btn:hover .far {
            transform: scale(1.1);
        }

        /* Card hover effects */
        .card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .card:hover {
            transform: translateY(-4px) scale(1.005);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
            border-color: #667eea;
            z-index: 3;
        }

        .card-header {
            background: linear-gradient(135deg, #f1f5f9 0%, #ffffff 100%);
            border-bottom: 1px solid #e2e8f0;
            border-radius: 20px 20px 0 0 !important;
            padding: 1.5rem 2rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Modern container spacing */
        .container {
            max-width: 1400px;
        }

        /* Enhanced spacing and layout management */
        .mb-4 {
            margin-bottom: 2.5rem !important;
        }

        .mb-5 {
            margin-bottom: 3.5rem !important;
        }

        /* Prevent overlapping issues */
        .row {
            position: relative;
            z-index: 1;
        }

        .container-fluid {
            position: relative;
            z-index: 1;
        }

        /* Dashboard sections spacing */
        .dashboard-section {
            margin-bottom: 3rem;
            position: relative;
            z-index: 1;
        }
    </style>

<!-- Dashboard Header -->

        <!-- Dashboard Header -->
        <div class="dashboard-header animate-fadeInUp">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1 class="dashboard-title">
                        <i class="fas fa-tachometer-alt text-gradient-primary me-3"></i>
                        <?= t('admin_dashboard') ?>
                    </h1>
                    <p class="dashboard-subtitle">
                        <i class="fas fa-calendar-alt me-2"></i><?= date('l, F j, Y') ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-clock me-2"></i><?= date('g:i A') ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-shield me-2"></i><?= t('welcome') ?> back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
                    </p>
                </div>
                <div class="dashboard-actions">
                    <a href="members.php" class="btn btn-outline-modern">
                        <i class="fas fa-users-cog me-2"></i>Manage Members
                    </a>
                    <a href="change_password.php" class="btn btn-outline-modern">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </a>
                    <a href="create_group_simple.php" class="btn btn-primary-modern">
                        <i class="fas fa-plus-circle me-2"></i>Create Group
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4 animate-stagger">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-primary" onclick="scrollToGroups()">
                    <div class="stat-icon-modern">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number-modern"><?= $totalGroups ?></div>
                    <div class="stat-label-modern"><?= t('total_groups') ?></div>
                    <div class="stat-sublabel-modern"><?= $activeGroups ?> <?= t('active_groups') ?> • <?= $completedGroups ?> <?= t('completed_groups') ?></div>
                    <div class="stat-action-modern">
                        <i class="fas fa-arrow-down"></i>
                        <span>Click to view groups</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-secondary" onclick="window.location.href='admin_members.php'">
                    <div class="stat-icon-modern">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-number-modern"><?= $totalMembers ?></div>
                    <div class="stat-label-modern"><?= t('total_members') ?></div>
                    <div class="stat-sublabel-modern">Across all groups</div>
                    <div class="stat-action-modern">
                        <i class="fas fa-external-link-alt"></i>
                        <span>Manage members</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-info" onclick="showCollectionDetails()">
                    <div class="stat-icon-modern">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-number-modern"><?= formatCurrency($totalCollected) ?></div>
                    <div class="stat-label-modern"><?= t('total_collected') ?></div>
                    <div class="stat-sublabel-modern">All payments received</div>
                    <div class="stat-action-modern">
                        <i class="fas fa-info-circle"></i>
                        <span>View details</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card-modern-enhanced stat-card-accent" onclick="showDistributionDetails()">
                    <div class="stat-icon-modern">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-number-modern"><?= formatCurrency($totalDistributed) ?></div>
                    <div class="stat-label-modern"><?= t('total_distributed') ?></div>
                    <div class="stat-sublabel-modern">Amount given to winners</div>
                    <div class="stat-action-modern">
                        <i class="fas fa-info-circle"></i>
                        <span>View details</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Bar -->
        <div class="quick-actions animate-slideInRight">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5><i class="fas fa-bolt text-warning me-2"></i>Quick Actions</h5>
                    <p class="text-muted mb-0">Frequently used admin functions</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="add_member.php" class="quick-action-btn" title="Add New Member">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Member</span>
                    </a>
                    <a href="payment_config.php" class="quick-action-btn" title="QR Code Settings">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Settings</span>
                    </a>
                    <a href="payment_status.php" class="quick-action-btn" title="Payment Status">
                        <i class="fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="bulk_import.php" class="quick-action-btn" title="Bulk Import">
                        <i class="fas fa-upload"></i>
                        <span>Import</span>
                    </a>
                    <button class="quick-action-btn" onclick="refreshDashboard()" title="Refresh Data">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Group-wise Pending Payments Section -->
        <div class="row mb-5">
            <div class="col-12">
                <div class="card dashboard-card animate-fadeInUp">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= t('pending_payments') ?>
                                </h4>
                                <p class="mb-0 mt-1 small"><?= t('view_month_details') ?></p>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-dark btn-sm" onclick="loadPendingPayments()">
                                    <i class="fas fa-sync-alt me-1"></i>Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="min-height: 200px; max-height: 600px; overflow-y: auto;">
                        <div id="pendingPaymentsContent">
                            <div class="text-center text-muted py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2"><?= t('loading_pending_payments') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line"></i> <?= t('monthly_collection_trend') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie"></i> <?= t('group_status') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="groupStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group Progress and Recent Activities -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar"></i> <?= t('group_progress') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="groupProgressChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> <?= t('recent_activities') ?>
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="d-flex align-items-center mb-2 p-2 bg-light rounded">
                                    <div class="me-3">
                                        <?php if ($activity['type'] === 'payment'): ?>
                                            <i class="fas fa-money-bill text-success"></i>
                                        <?php else: ?>
                                            <i class="fas fa-trophy text-warning"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?= htmlspecialchars($activity['member_name']) ?></div>
                                        <small class="text-muted">
                                            <?= $activity['type'] === 'payment' ? 'Payment' : 'Won bid' ?> in <?= htmlspecialchars($activity['group_name']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-primary"><?= formatCurrency($activity['amount']) ?></div>
                                        <small class="text-muted"><?= formatDate($activity['date']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- BC Groups List -->
        <div class="groups-section animate-fadeInUp" id="groupsList">
            <div class="groups-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h4><i class="fas fa-layer-group me-2"></i>All BC Groups</h4>
                        <p class="text-muted mb-0">Manage and monitor all your BC groups</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-outline-modern btn-sm" onclick="filterGroups('all')" id="filterAll">
                            <i class="fas fa-list me-1"></i>All
                        </button>
                        <button class="btn btn-outline-modern btn-sm" onclick="filterGroups('active')" id="filterActive">
                            <i class="fas fa-play me-1"></i>Active
                        </button>
                        <button class="btn btn-outline-modern btn-sm" onclick="filterGroups('completed')" id="filterCompleted">
                            <i class="fas fa-check me-1"></i>Completed
                        </button>
                    </div>
                </div>
            </div>
            <div class="p-3">
                <div class="row">
                    <?php if (!empty($groups)): ?>
                <?php foreach ($groups as $group): ?>
                    <div class="col-md-6 col-lg-4 mb-4" data-group-status="<?= $group['status'] ?>">
                        <div class="group-card-modern">
                            <div class="group-status-badge">
                                <span class="badge badge-<?= $group['status'] === 'active' ? 'success' : 'secondary' ?>-modern">
                                    <i class="fas fa-<?= $group['status'] === 'active' ? 'play' : 'check' ?> me-1"></i>
                                    <?= ucfirst($group['status']) ?>
                                </span>
                            </div>

                            <div class="group-name"><?= htmlspecialchars($group['group_name']) ?></div>

                            <div class="group-meta">
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="fw-bold text-primary"><?= $group['total_members'] ?></div>
                                        <small class="text-muted">Members</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-success"><?= formatCurrency($group['monthly_contribution']) ?></div>
                                        <small class="text-muted">Monthly</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold text-info"><?= formatCurrency($group['total_monthly_collection']) ?></div>
                                        <small class="text-muted">Total</small>
                                    </div>
                                </div>
                                <div class="text-center mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Started: <?= formatDate($group['start_date']) ?>
                                    </small>
                                </div>
                            </div>

                            <div class="group-actions">
                                <a href="view_group.php?id=<?= $group['id'] ?>" class="btn btn-primary-modern group-action-btn">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                <a href="admin_bidding.php?group_id=<?= $group['id'] ?>" class="btn btn-outline-modern group-action-btn">
                                    <i class="fas fa-gavel me-1"></i>Bidding
                                </a>
                                <a href="admin_payment_status.php?group_id=<?= $group['id'] ?>" class="btn btn-outline-modern group-action-btn">
                                    <i class="fas fa-credit-card me-1"></i>Payments
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No BC Groups Yet</h4>
                        <p class="text-muted">Create your first BC group to get started.</p>
                        <a href="create_group_simple.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create BC Group
                        </a>
                    </div>
                </div>
            <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js configuration
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.color = '#666';

        // Monthly Trend Chart
        const monthlyData = <?= json_encode(array_reverse($monthlyData)) ?>;
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Monthly Collection',
                    data: monthlyData.map(item => item.total_amount),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Group Status Pie Chart
        const statusCtx = document.getElementById('groupStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Groups', 'Completed Groups'],
                datasets: [{
                    data: [<?= $activeGroups ?>, <?= $completedGroups ?>],
                    backgroundColor: ['#11998e', '#f5576c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Group Progress Bar Chart
        const progressData = <?= json_encode($groupProgressData) ?>;
        const progressCtx = document.getElementById('groupProgressChart').getContext('2d');
        new Chart(progressCtx, {
            type: 'bar',
            data: {
                labels: progressData.map(item => item.name.length > 15 ? item.name.substring(0, 15) + '...' : item.name),
                datasets: [{
                    label: 'Completed Months',
                    data: progressData.map(item => item.completed),
                    backgroundColor: '#38ef7d',
                    borderRadius: 5
                }, {
                    label: 'Remaining Months',
                    data: progressData.map(item => item.total - item.completed),
                    backgroundColor: '#e9ecef',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Enhanced dashboard functions
        function scrollToGroups() {
            document.getElementById('groupsList').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function showCollectionDetails() {
            const totalCollected = <?= $totalCollected ?>;
            const totalMembers = <?= $totalMembers ?>;
            const avgPerMember = totalMembers > 0 ? (totalCollected / totalMembers) : 0;

            alert(`Collection Details:\n\n` +
                  `Total Collected: ₹${totalCollected.toLocaleString()}\n` +
                  `Total Members: ${totalMembers}\n` +
                  `Average per Member: ₹${avgPerMember.toFixed(2)}\n\n` +
                  `Click on "Manage Members" to see detailed payment history.`);
        }

        function showDistributionDetails() {
            const totalDistributed = <?= $totalDistributed ?>;
            const totalCollected = <?= $totalCollected ?>;
            const remaining = totalCollected - totalDistributed;

            alert(`Distribution Details:\n\n` +
                  `Total Distributed: ₹${totalDistributed.toLocaleString()}\n` +
                  `Total Collected: ₹${totalCollected.toLocaleString()}\n` +
                  `Remaining Balance: ₹${remaining.toLocaleString()}\n\n` +
                  `This shows amounts given to bid winners.`);
        }

        function filterGroups(status) {
            const groupCards = document.querySelectorAll('[data-group-status]');
            const filterButtons = document.querySelectorAll('[id^="filter"]');

            // Reset button styles
            filterButtons.forEach(btn => {
                btn.classList.remove('btn-primary', 'btn-success', 'btn-secondary');
                btn.classList.add('btn-outline-primary', 'btn-outline-success', 'btn-outline-secondary');
            });

            // Highlight active filter
            const activeButton = document.getElementById('filter' + status.charAt(0).toUpperCase() + status.slice(1));
            if (activeButton) {
                activeButton.classList.remove('btn-outline-primary', 'btn-outline-success', 'btn-outline-secondary');
                activeButton.classList.add(status === 'active' ? 'btn-success' :
                                          status === 'completed' ? 'btn-secondary' : 'btn-primary');
            }

            // Filter groups
            groupCards.forEach(card => {
                if (status === 'all' || card.getAttribute('data-group-status') === status) {
                    card.style.display = 'block';
                    card.style.animation = 'fadeIn 0.3s ease-in';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function refreshDashboard() {
            const refreshBtn = document.querySelector('[onclick="refreshDashboard()"]');
            const icon = refreshBtn.querySelector('i');

            // Add spinning animation
            icon.classList.add('fa-spin');
            refreshBtn.disabled = true;

            // Simulate refresh (in real app, you'd reload data via AJAX)
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        function loadPendingPayments() {
            const contentDiv = document.getElementById('pendingPaymentsContent');

            // Show loading
            contentDiv.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading group-wise pending payments...</p>
                </div>
            `;

            // Fetch pending payments summary
            fetch(`get_pending_payments.php?action=summary`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    if (data.trim() === '') {
                        contentDiv.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle"></i>
                                <strong>No data received</strong>
                                <p class="mb-0 mt-2">The server returned empty data. This could mean:</p>
                                <ul class="mb-0 mt-1">
                                    <li>No groups exist in the system</li>
                                    <li>No bidding processes have been started</li>
                                    <li>All payments are up to date</li>
                                </ul>
                            </div>
                        `;
                    } else {
                        contentDiv.innerHTML = data;
                    }
                })
                .catch(error => {
                    console.error('Error loading pending payments:', error);
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Error loading data</strong>
                            <p class="mb-0 mt-2">Failed to load pending payments: ${error.message}</p>
                            <button class="btn btn-sm btn-outline-danger mt-2" onclick="loadPendingPayments()">
                                <i class="fas fa-retry"></i> Try Again
                            </button>
                        </div>
                    `;
                });
        }

        // Load pending payments on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPendingPayments();
        });
    </script>

<?php require_once 'includes/footer.php'; ?>
