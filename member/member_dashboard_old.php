
<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('member');

// Ensure language variables are available
if (!isset($available_languages)) {
    $available_languages = [
        'en' => ['name' => 'English', 'flag' => '🇺🇸'],
        'hi' => ['name' => 'हिंदी', 'flag' => '🇮🇳']
    ];
}

// Ensure getCurrentLanguage function works
if (!function_exists('getCurrentLanguage')) {
    function getCurrentLanguage() {
        return isset($_COOKIE['language']) ? $_COOKIE['language'] : 'en';
    }
}

if (isset($_GET['logout'])) {
    logout();
}

$member = getCurrentMember();
$currentGroupId = $_SESSION['group_id'];
$currentGroup = getGroupById($currentGroupId);

// Get all groups where this member name exists
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT DISTINCT g.*, m.id as member_id, m.member_number, m.status as member_status, m.created_at as member_joined_date
    FROM bc_groups g
    JOIN members m ON g.id = m.group_id
    WHERE m.member_name = ? AND m.status = 'active'
    ORDER BY g.start_date DESC
");
$stmt->execute([$member['member_name']]);
$allMemberGroups = $stmt->fetchAll();

// Calculate comprehensive data for all groups
$groupsData = [];
foreach ($allMemberGroups as $groupInfo) {
    $gId = $groupInfo['id'];
    $gMembers = getGroupMembers($gId);
    $gMonthlyBids = getMonthlyBids($gId);
    $gMemberPayments = getMemberPayments($gId);
    $gMemberSummary = getMemberSummary($gId);

    // Calculate group progress
    $totalMonths = $groupInfo['total_members'];
    $completedMonths = count($gMonthlyBids);
    $progressPercentage = ($completedMonths / $totalMonths) * 100;

    // Calculate estimated end date
    $startDate = new DateTime($groupInfo['start_date']);
    $estimatedEndDate = clone $startDate;
    $estimatedEndDate->add(new DateInterval('P' . ($totalMonths - 1) . 'M'));

    // Get member's data in this group
    $memberInGroup = array_filter($gMembers, fn($m) => $m['member_name'] === $member['member_name']);
    $memberInGroup = reset($memberInGroup);

    $memberPaymentsInGroup = array_filter($gMemberPayments, fn($p) => $p['member_id'] == $memberInGroup['id']);
    $memberSummaryInGroup = array_filter($gMemberSummary, fn($s) => $s['member_id'] == $memberInGroup['id']);
    $memberSummaryInGroup = reset($memberSummaryInGroup);

    $groupsData[] = [
        'group' => $groupInfo,
        'member_in_group' => $memberInGroup,
        'total_months' => $totalMonths,
        'completed_months' => $completedMonths,
        'progress_percentage' => $progressPercentage,
        'estimated_end_date' => $estimatedEndDate,
        'member_payments' => $memberPaymentsInGroup,
        'member_summary' => $memberSummaryInGroup,
        'monthly_bids' => $gMonthlyBids,
        'is_current_group' => $gId == $currentGroupId
    ];
}

// For backward compatibility, keep current group data
$groupId = $currentGroupId;
$group = $currentGroup;
$members = getGroupMembers($groupId);
$monthlyBids = getMonthlyBids($groupId);
$memberPayments = getMemberPayments($groupId);
$memberSummary = getMemberSummary($groupId);

// Get current member's payments
$myPayments = array_filter($memberPayments, fn($p) => $p['member_id'] == $member['id']);

// Get current member's summary
$mySummary = array_filter($memberSummary, fn($s) => $s['member_id'] == $member['id']);
$mySummary = reset($mySummary);

// Organize payments by month
$myPaymentsByMonth = [];
foreach ($myPayments as $payment) {
    $myPaymentsByMonth[$payment['month_number']] = $payment;
}

// Calculate additional statistics
$totalMonths = $group['total_members'];
$paidMonths = count(array_filter($myPayments, fn($p) => $p['payment_status'] === 'paid'));
$pendingMonths = count(array_filter($myPayments, fn($p) => $p['payment_status'] === 'pending'));
$remainingMonths = $totalMonths - $paidMonths - $pendingMonths;

// Get my bid wins
$myBidWins = array_filter($monthlyBids, fn($b) => $b['taken_by_member_id'] == $member['id']);

// Calculate payment progress data for chart
$paymentProgressData = [];
for ($month = 1; $month <= $totalMonths; $month++) {
    $payment = $myPaymentsByMonth[$month] ?? null;
    $paymentProgressData[] = [
        'month' => $month,
        'status' => $payment ? $payment['payment_status'] : 'pending',
        'amount' => $payment ? $payment['payment_amount'] : $group['monthly_contribution'],
        'date' => $payment ? $payment['payment_date'] : null
    ];
}

// Get group completion percentage
$totalBidsCompleted = count($monthlyBids);
$groupProgress = ($totalBidsCompleted / $totalMonths) * 100;

// Calculate my financial position
$totalPaid = $mySummary['total_paid'] ?? 0;
$totalReceived = $mySummary['given_amount'] ?? 0;
$netPosition = $totalReceived - $totalPaid;

// Function to get current active month
function getCurrentActiveMonth($groupId, $pdo) {
    // First, try to find an open month
    $stmt = $pdo->prepare("
        SELECT * FROM month_bidding_status
        WHERE group_id = ? AND bidding_status = 'open'
        ORDER BY month_number LIMIT 1
    ");
    $stmt->execute([$groupId]);
    $openMonth = $stmt->fetch();

    if ($openMonth) {
        return $openMonth;
    }

    // If no open month, find the next month after completed ones
    $stmt = $pdo->prepare("
        SELECT MIN(month_number) as next_month
        FROM month_bidding_status
        WHERE group_id = ? AND bidding_status = 'not_started'
    ");
    $stmt->execute([$groupId]);
    $result = $stmt->fetch();

    if ($result && $result['next_month']) {
        $stmt = $pdo->prepare("
            SELECT * FROM month_bidding_status
            WHERE group_id = ? AND month_number = ?
        ");
        $stmt->execute([$groupId, $result['next_month']]);
        return $stmt->fetch();
    }

    return null;
}

// Get current active month and payment status
$pdo = getDB();
$currentActiveMonth = getCurrentActiveMonth($groupId, $pdo);
$currentMonthPaymentInfo = null;

if ($currentActiveMonth) {
    // Check if there's a completed bid for current month
    $stmt = $pdo->prepare("
        SELECT mb.*, m.member_name as winner_name
        FROM monthly_bids mb
        LEFT JOIN members m ON mb.taken_by_member_id = m.id
        WHERE mb.group_id = ? AND mb.month_number = ?
    ");
    $stmt->execute([$groupId, $currentActiveMonth['month_number']]);
    $currentMonthBid = $stmt->fetch();

    // Check if current member has payment for this month
    $currentMonthPayment = $myPaymentsByMonth[$currentActiveMonth['month_number']] ?? null;

    $currentMonthPaymentInfo = [
        'month_number' => $currentActiveMonth['month_number'],
        'bidding_status' => $currentActiveMonth['bidding_status'],
        'bid_exists' => (bool)$currentMonthBid,
        'payment_exists' => (bool)$currentMonthPayment,
        'payment_status' => $currentMonthPayment['payment_status'] ?? 'pending',
        'payment_amount' => $currentMonthPayment['payment_amount'] ?? ($currentMonthBid['gain_per_member'] ?? $group['monthly_contribution']),
        'payment_date' => $currentMonthPayment['payment_date'] ?? null,
        'winner_name' => $currentMonthBid['winner_name'] ?? null,
        'bid_amount' => $currentMonthBid['bid_amount'] ?? null
    ];
}

// Get recent group activities
$stmt = $pdo->prepare("
    SELECT
        mb.month_number,
        mb.bid_amount,
        mb.net_payable,
        mb.payment_date,
        m.member_name as winner_name,
        m.member_number as winner_number
    FROM monthly_bids mb
    JOIN members m ON mb.taken_by_member_id = m.id
    WHERE mb.group_id = ?
    ORDER BY mb.month_number DESC
    LIMIT 5
");
$stmt->execute([$groupId]);
$recentActivities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('member_dashboard') ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css?v=<?= time() ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, var(--gray-50) 0%, #f0fdf4 100%);
            font-family: var(--font-family-sans);
        }

        /* Member Navigation */
        .member-navbar {
            background: var(--secondary-gradient);
            box-shadow: var(--shadow-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .member-navbar .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .member-navbar .navbar-brand:hover {
            color: rgba(255, 255, 255, 0.9);
        }

        .member-navbar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            transition: all var(--transition-normal);
            margin: 0 0.25rem;
        }

        .member-navbar .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .member-navbar .navbar-text {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            min-width: 160px;
            padding: 0.5rem 0;
            margin: 0;
            background-color: #fff;
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 0.375rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,.175);
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }

        .dropdown-item.active {
            background-color: var(--bs-primary);
            color: white;
        }

        .language-flag {
            font-size: 1.1em;
            margin-right: 0.25rem;
        }



        /* Language switcher styling for member navbar */
        .member-navbar .language-flag {
            font-size: 1.2em;
            margin-right: 0.5rem;
        }

        .member-navbar .dropdown-item.active {
            background-color: var(--primary-color);
            color: white;
        }

        .member-navbar .dropdown-item:hover {
            background-color: rgba(var(--primary-color-rgb), 0.1);
        }

        /* Navbar toggler styling for member navbar */
        .member-navbar .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.3);
            padding: 0.25rem 0.5rem;
        }

        .member-navbar .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
        }

        .member-navbar .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .member-navbar .navbar-nav {
                padding-top: 1rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                margin-top: 0.5rem;
            }

            .member-navbar .nav-link {
                padding: 0.75rem 1rem;
                margin: 0.25rem 0;
                border-radius: var(--radius-md);
            }

            .member-navbar .navbar-text {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                margin-bottom: 0.5rem;
            }
        }

        @media (min-width: 992px) {
            .member-navbar .navbar-nav {
                flex-direction: row;
                align-items: center;
            }
        }

        /* Member Profile Header */
        .member-profile-header {
            background: white;
            border-radius: var(--radius-2xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .member-profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--secondary-gradient);
        }

        .member-avatar-modern {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin: 0 auto 1rem;
            box-shadow: var(--shadow-lg);
            animation: pulse-gentle 2s infinite;
        }

        .member-welcome-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .member-welcome-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .member-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--secondary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--radius-lg);
            font-weight: 500;
            transition: all var(--transition-normal);
            border: none;
            margin: 0.25rem;
        }

        .member-action-btn:hover {
            background: var(--secondary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .member-action-btn.secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .member-action-btn.secondary:hover {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        /* Groups Overview */
        .groups-overview {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .groups-overview-header {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
            padding: 1.5rem;
        }

        .groups-overview-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .groups-overview-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .group-card-member {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin: 1rem;
            transition: all var(--transition-normal);
            cursor: pointer;
            position: relative;
        }

        .group-card-member:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--secondary-color);
        }

        .group-card-member.current-group {
            border-color: var(--secondary-color);
            background: rgba(16, 185, 129, 0.05);
        }

        .group-card-member.current-group::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--secondary-gradient);
        }

        .group-name-member {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .group-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .group-stat {
            text-align: center;
        }

        .group-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .group-stat-label {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }

        .group-progress-bar {
            background: var(--gray-200);
            border-radius: var(--radius-md);
            height: 8px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .group-progress-fill {
            background: var(--secondary-gradient);
            height: 100%;
            border-radius: var(--radius-md);
            transition: width var(--transition-slow);
        }

        .group-status-badge-member {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .member-profile-header {
                padding: 1.5rem;
                text-align: center;
            }

            .member-welcome-title {
                font-size: 1.75rem;
            }

            .group-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        .member-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
        }
        .status-card {
            border-left: 4px solid #28a745;
            transition: transform 0.2s;
        }
        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
        }
        .stat-card-info {
            background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);
        }
        .stat-card-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
            margin: 0 auto;
        }
        .progress-ring {
            width: 120px;
            height: 120px;
        }
        .progress-ring-circle {
            transition: stroke-dasharray 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .activity-item {
            border-left: 3px solid #28a745;
            padding-left: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 5px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #28a745;
        }

        /* Interactive enhancements */
        .member-avatar {
            transition: all 0.3s ease;
        }

        .member-avatar:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(40,167,69,0.3);
        }

        .btn {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn:active {
            transform: translateY(0);
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .fas, .far {
            transition: all 0.3s ease;
        }

        .btn:hover .fas,
        .btn:hover .far {
            transform: scale(1.1);
        }

        .card {
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .member-card {
            transition: all 0.3s ease;
        }

        .member-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        .table-responsive {
            border-radius: 0.375rem;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 1rem 0.75rem;
        }

        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .progress {
            border-radius: 10px;
        }

        .progress-bar {
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge {
            font-size: 0.75rem;
        }

        .table-success {
            background-color: rgba(25, 135, 84, 0.1) !important;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        /* Group selection styles */
        .group-row {
            transition: all 0.3s ease;
        }

        .group-row:hover {
            background-color: #f8f9fa !important;
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .group-row.table-success {
            background-color: #d1e7dd !important;
            border-left: 4px solid #28a745;
        }

        .group-row.table-success:hover {
            background-color: #c3e6cb !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg member-navbar">
        <div class="container">
            <a class="navbar-brand" href="member_dashboard.php">
                <i class="fas fa-users me-2"></i>Mitra Niidhi Samooh
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#memberNavbar" aria-controls="memberNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="memberNavbar">
                <ul class="navbar-nav ms-auto">
                    <!-- Language Switcher -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-globe me-1"></i>
                            <span class="language-flag"><?= isset($available_languages[getCurrentLanguage()]['flag']) ? $available_languages[getCurrentLanguage()]['flag'] : '🇺🇸' ?></span>
                            <span class="d-none d-md-inline ms-1"><?= isset($available_languages[getCurrentLanguage()]['name']) ? $available_languages[getCurrentLanguage()]['name'] : 'English' ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                            <?php foreach ($available_languages as $code => $language): ?>
                                <li>
                                    <a class="dropdown-item <?= getCurrentLanguage() === $code ? 'active' : '' ?>"
                                       href="?change_language=<?= $code ?>">
                                        <span class="me-2"><?= $language['flag'] ?></span>
                                        <?= $language['name'] ?>
                                        <?php if (getCurrentLanguage() === $code): ?>
                                            <i class="fas fa-check text-success ms-2"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <span class="navbar-text me-3">
                            <i class="fas fa-user-circle me-1"></i>
                            <?= t('welcome', 'Welcome') ?>, <?= htmlspecialchars($_SESSION['member_name']) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="member_edit_profile.php">
                            <i class="fas fa-user-edit"></i> <?= t('edit_profile', 'Edit Profile') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="member_change_password.php">
                            <i class="fas fa-key"></i> <?= t('change_password', 'Change Password') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="?logout=1">
                            <i class="fas fa-sign-out-alt"></i> <?= t('logout', 'Logout') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php $msg = getMessage(); ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Member Profile Header -->
        <div class="member-profile-header animate-fadeInUp">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="member-avatar-modern">
                        <?= strtoupper(substr($member['member_name'], 0, 1)) ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <h1 class="member-welcome-title">
                        <i class="fas fa-hand-holding-heart text-gradient-secondary me-2"></i>
                        <?= t('welcome') ?>, <?= htmlspecialchars($member['member_name']) ?>!
                    </h1>
                    <p class="member-welcome-subtitle">
                        <i class="fas fa-info-circle me-2"></i>
                        You are a member of <?= count($groupsData) ?> group(s)
                        <span class="mx-2">•</span>
                        <i class="fas fa-calendar-alt me-1"></i>
                        Member since <?= formatDate($member['created_at']) ?>
                    </p>
                </div>
                <div class="col-md-3">
                    <div class="d-flex flex-column gap-2">
                        <a href="member_bidding.php" class="member-action-btn">
                            <i class="fas fa-gavel"></i>
                            <span><?= t('place_bids', 'Place Bids') ?></span>
                        </a>
                        <a href="member_group_view.php" class="member-action-btn secondary">
                            <i class="fas fa-eye"></i>
                            <span><?= t('view', 'View') ?> <?= t('groups') ?></span>
                        </a>
                        <a href="member_edit_profile.php" class="member-action-btn secondary">
                            <i class="fas fa-user-edit"></i>
                            <span><?= t('edit_profile') ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Groups Information -->
        <div class="groups-overview animate-slideInRight">
            <div class="groups-overview-header">
                <h2 class="groups-overview-title">
                    <i class="fas fa-layer-group me-2"></i><?= t('my_groups', 'My Groups') ?> Overview
                </h2>
                <p class="groups-overview-subtitle mb-0">
                    Click on a group to view detailed information and manage your participation
                </p>
            </div>
            <div class="p-0">
                <div class="row g-0">
                                    <?php foreach ($groupsData as $index => $groupData): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="group-card-member <?= $groupData['is_current_group'] ? 'current-group' : '' ?> animate-fadeInUp"
                                             style="animation-delay: <?= $index * 0.1 ?>s"
                                             onclick="selectGroup(<?= $groupData['group']['id'] ?>)"
                                             title="Click to view detailed information for this group">

                                            <div class="group-status-badge-member">
                                                <?php if ($groupData['is_current_group']): ?>
                                                    <span class="badge badge-success-modern">
                                                        <i class="fas fa-check-circle me-1"></i>Selected
                                                    </span>
                                                <?php endif; ?>
                                                <span class="badge badge-<?= $groupData['group']['status'] === 'completed' ? 'success' : 'primary' ?>-modern">
                                                    <i class="fas fa-<?= $groupData['group']['status'] === 'completed' ? 'trophy' : 'play' ?> me-1"></i>
                                                    <?= ucfirst($groupData['group']['status']) ?>
                                                </span>
                                            </div>

                                            <div class="group-name-member">
                                                <?= htmlspecialchars($groupData['group']['group_name']) ?>
                                            </div>

                                            <div class="text-center mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-id-badge me-1"></i>
                                                    Member #<?= $groupData['member_in_group']['member_number'] ?>
                                                </small>
                                            </div>

                                            <div class="group-stats">
                                                <div class="group-stat">
                                                    <div class="group-stat-value"><?= formatCurrency($groupData['group']['monthly_contribution']) ?></div>
                                                    <div class="group-stat-label">Monthly</div>
                                                </div>
                                                <div class="group-stat">
                                                    <div class="group-stat-value"><?= $groupData['completed_months'] ?>/<?= $groupData['total_months'] ?></div>
                                                    <div class="group-stat-label">Progress</div>
                                                </div>
                                                <div class="group-stat">
                                                    <div class="group-stat-value"><?= number_format($groupData['progress_percentage'], 1) ?>%</div>
                                                    <div class="group-stat-label">Complete</div>
                                                </div>
                                            </div>

                                            <div class="group-progress-bar">
                                                <div class="group-progress-fill" style="width: <?= $groupData['progress_percentage'] ?>%"></div>
                                            </div>

                                            <div class="text-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar-alt me-1"></i>
                                                    Started: <?= formatDate($groupData['group']['start_date']) ?>
                                                    <span class="mx-2">•</span>
                                                    <i class="fas fa-calendar-check me-1"></i>
                                                    Est. End: <?= $groupData['estimated_end_date']->format('d/m/Y') ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

        <!-- Multi-Group Summary -->
        <?php if (count($groupsData) > 1): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Overall Summary Across All Groups
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Calculate overall statistics
                        $totalMonthlyContributions = 0;
                        $totalPaidAmount = 0;
                        $totalReceivedAmount = 0;
                        $totalActiveGroups = 0;
                        $totalCompletedGroups = 0;

                        foreach ($groupsData as $groupData) {
                            $totalMonthlyContributions += $groupData['group']['monthly_contribution'];
                            if ($groupData['member_summary']) {
                                $totalPaidAmount += $groupData['member_summary']['total_paid'];
                                $totalReceivedAmount += $groupData['member_summary']['given_amount'];
                            }
                            if ($groupData['group']['status'] === 'active') {
                                $totalActiveGroups++;
                            } else {
                                $totalCompletedGroups++;
                            }
                        }

                        $netPosition = $totalReceivedAmount - $totalPaidAmount;
                        ?>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                    <h6>Total Groups</h6>
                                    <h4 class="text-primary"><?= count($groupsData) ?></h4>
                                    <small class="text-muted"><?= $totalActiveGroups ?> Active, <?= $totalCompletedGroups ?> Completed</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-coins fa-2x text-warning mb-2"></i>
                                    <h6>Monthly Contributions</h6>
                                    <h4 class="text-warning"><?= formatCurrency($totalMonthlyContributions) ?></h4>
                                    <small class="text-muted">Combined across all groups</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-arrow-up fa-2x text-danger mb-2"></i>
                                    <h6>Total Paid</h6>
                                    <h4 class="text-danger"><?= formatCurrency($totalPaidAmount) ?></h4>
                                    <small class="text-muted">Amount contributed</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <i class="fas fa-arrow-down fa-2x text-success mb-2"></i>
                                    <h6>Total Received</h6>
                                    <h4 class="text-success"><?= formatCurrency($totalReceivedAmount) ?></h4>
                                    <small class="text-muted">Amount received</small>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="text-center p-3 <?= $netPosition >= 0 ? 'bg-success' : 'bg-danger' ?> text-white rounded">
                                    <i class="fas fa-balance-scale fa-2x mb-2"></i>
                                    <h6>Net Position</h6>
                                    <h4><?= formatCurrency(abs($netPosition)) ?></h4>
                                    <small>
                                        <?php if ($netPosition > 0): ?>
                                            You have received ₹<?= number_format($netPosition) ?> more than you have paid
                                        <?php elseif ($netPosition < 0): ?>
                                            You have paid ₹<?= number_format(abs($netPosition)) ?> more than you have received
                                        <?php else: ?>
                                            Your payments and receipts are balanced
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Group Selector for Detailed View -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-filter me-2"></i>Detailed View - Select Group
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="d-flex align-items-center gap-3">
                            <label for="selected_group" class="form-label mb-0 fw-bold">View Details for:</label>
                            <select name="selected_group" id="selected_group" class="form-select" style="width: auto;" onchange="this.form.submit()">
                                <?php foreach ($groupsData as $groupData): ?>
                                    <option value="<?= $groupData['group']['id'] ?>"
                                            <?= (isset($_GET['selected_group']) ? $_GET['selected_group'] : $currentGroupId) == $groupData['group']['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($groupData['group']['group_name']) ?>
                                        <?php if ($groupData['is_current_group']): ?>
                                            (Current Group)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">
                                The sections below will show data for the selected group only
                            </small>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Determine which group to show detailed data for
        $selectedGroupId = isset($_GET['selected_group']) ? (int)$_GET['selected_group'] : $currentGroupId;
        $selectedGroupData = null;
        foreach ($groupsData as $groupData) {
            if ($groupData['group']['id'] == $selectedGroupId) {
                $selectedGroupData = $groupData;
                break;
            }
        }

        // If selected group data found, override the current group variables for detailed sections
        if ($selectedGroupData) {
            $groupId = $selectedGroupData['group']['id'];
            $group = $selectedGroupData['group'];
            $members = getGroupMembers($groupId);
            $monthlyBids = getMonthlyBids($groupId);
            $memberPayments = getMemberPayments($groupId);
            $memberSummary = getMemberSummary($groupId);

            // Get the member's ID in the selected group
            $memberInSelectedGroup = $selectedGroupData['member_in_group'];
            $memberIdInSelectedGroup = $memberInSelectedGroup ? $memberInSelectedGroup['id'] : null;

            if ($memberIdInSelectedGroup) {
                // Recalculate member-specific data for selected group
                $myPayments = array_filter($memberPayments, fn($p) => $p['member_id'] == $memberIdInSelectedGroup);
                $mySummary = array_filter($memberSummary, fn($s) => $s['member_id'] == $memberIdInSelectedGroup);
                $mySummary = reset($mySummary);

                // Organize payments by month
                $myPaymentsByMonth = [];
                foreach ($myPayments as $payment) {
                    $myPaymentsByMonth[$payment['month_number']] = $payment;
                }

                // Calculate additional statistics
                $totalMonths = $group['total_members'];
                $paidMonths = count(array_filter($myPayments, fn($p) => $p['payment_status'] === 'paid'));
                $pendingMonths = count(array_filter($myPayments, fn($p) => $p['payment_status'] === 'pending'));
                $remainingMonths = $totalMonths - $paidMonths - $pendingMonths;

                // Get my bid wins
                $myBidWins = array_filter($monthlyBids, fn($b) => $b['taken_by_member_id'] == $memberIdInSelectedGroup);

                // Calculate payment progress data for chart
                $paymentProgressData = [];
                for ($month = 1; $month <= $totalMonths; $month++) {
                    $payment = $myPaymentsByMonth[$month] ?? null;
                    $paymentProgressData[] = [
                        'month' => $month,
                        'status' => $payment ? $payment['payment_status'] : 'pending',
                        'amount' => $payment ? $payment['payment_amount'] : $group['monthly_contribution'],
                        'date' => $payment ? $payment['payment_date'] : null
                    ];
                }

                // Get recent activities for selected group
                $stmt = $pdo->prepare("
                    SELECT
                        mb.month_number,
                        mb.bid_amount,
                        mb.net_payable,
                        mb.payment_date,
                        m.member_name as winner_name,
                        m.member_number as winner_number
                    FROM monthly_bids mb
                    JOIN members m ON mb.taken_by_member_id = m.id
                    WHERE mb.group_id = ?
                    ORDER BY mb.month_number DESC
                    LIMIT 5
                ");
                $stmt->execute([$groupId]);
                $recentActivities = $stmt->fetchAll();
            }
        }
        ?>

        <!-- Current Month Payment Status for Selected Group -->
        <?php
        // Get current month payment info for selected group
        $selectedCurrentMonthPaymentInfo = null;
        if ($selectedGroupData && isset($memberIdInSelectedGroup)) {
            $selectedCurrentMonthPaymentInfo = getCurrentMonthPaymentInfo($selectedGroupId, $memberIdInSelectedGroup);
        }
        ?>
        <?php if ($selectedCurrentMonthPaymentInfo): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-<?= $selectedCurrentMonthPaymentInfo['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                    <div class="card-header bg-<?= $selectedCurrentMonthPaymentInfo['payment_status'] === 'paid' ? 'success' : 'warning' ?> text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check me-2"></i>Current Month Payment Status - Month <?= $selectedCurrentMonthPaymentInfo['month_number'] ?>
                            <small class="ms-2">(<?= htmlspecialchars($selectedGroupData['group']['group_name']) ?>)</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <?php if ($selectedCurrentMonthPaymentInfo['bidding_status'] === 'completed' && $selectedCurrentMonthPaymentInfo['bid_exists']): ?>
                                    <?php if ($selectedCurrentMonthPaymentInfo['payment_status'] === 'paid'): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-1 text-success">Payment Completed</h6>
                                                <p class="mb-1">You have paid <strong><?= formatCurrency($selectedCurrentMonthPaymentInfo['payment_amount']) ?></strong> for Month <?= $selectedCurrentMonthPaymentInfo['month_number'] ?></p>
                                                <small class="text-muted">
                                                    Paid on: <?= $selectedCurrentMonthPaymentInfo['payment_date'] ? formatDate($selectedCurrentMonthPaymentInfo['payment_date']) : 'Date not recorded' ?>
                                                    <?php if ($selectedCurrentMonthPaymentInfo['winner_name']): ?>
                                                        | Winner: <?= htmlspecialchars($selectedCurrentMonthPaymentInfo['winner_name']) ?>
                                                        (Bid: <?= formatCurrency($selectedCurrentMonthPaymentInfo['bid_amount']) ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-exclamation-triangle text-warning fa-2x me-3"></i>
                                            <div>
                                                <h6 class="mb-1 text-warning">Payment Pending</h6>
                                                <p class="mb-1">Amount due: <strong><?= formatCurrency($selectedCurrentMonthPaymentInfo['payment_amount']) ?></strong> for Month <?= $selectedCurrentMonthPaymentInfo['month_number'] ?></p>
                                                <small class="text-muted">
                                                    Bid has been confirmed.
                                                    <?php if ($selectedCurrentMonthPaymentInfo['winner_name']): ?>
                                                        Winner: <?= htmlspecialchars($selectedCurrentMonthPaymentInfo['winner_name']) ?>
                                                        (Bid: <?= formatCurrency($selectedCurrentMonthPaymentInfo['bid_amount']) ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-clock text-info fa-2x me-3"></i>
                                        <div>
                                            <h6 class="mb-1 text-info">Bidding in Progress</h6>
                                            <p class="mb-1">Month <?= $selectedCurrentMonthPaymentInfo['month_number'] ?> - <?= ucfirst(str_replace('_', ' ', $selectedCurrentMonthPaymentInfo['bidding_status'])) ?></p>
                                            <small class="text-muted">Payment amount will be determined after bid confirmation</small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php if ($selectedCurrentMonthPaymentInfo['bidding_status'] === 'open' && $selectedGroupId == $currentGroupId): ?>
                                    <a href="member_bidding.php" class="btn btn-primary">
                                        <i class="fas fa-gavel me-1"></i> Place Bid
                                    </a>
                                <?php elseif ($selectedCurrentMonthPaymentInfo['payment_status'] === 'pending' && $selectedCurrentMonthPaymentInfo['bid_exists']): ?>
                                    <div class="text-center">
                                        <div class="badge bg-warning text-dark fs-6 p-2 mb-2">
                                            <i class="fas fa-hourglass-half me-1"></i>
                                            Awaiting Payment
                                        </div>
                                        <br>
                                        <a href="member_payment.php?month=<?= $selectedCurrentMonthPaymentInfo['month_number'] ?>"
                                           class="btn btn-success btn-sm">
                                            <i class="fas fa-qrcode me-1"></i> Pay Now
                                        </a>
                                    </div>
                                <?php elseif ($selectedGroupId != $currentGroupId): ?>
                                    <div class="text-center">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Viewing different group
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Calculate statistics for selected group (needed for charts and other sections)
        $selectedTotalPaid = $mySummary ? $mySummary['total_paid'] : 0;
        $selectedTotalReceived = $mySummary ? $mySummary['given_amount'] : 0;
        $selectedNetPosition = $selectedTotalReceived - $selectedTotalPaid;
        ?>

        <!-- Charts and Progress Section for Selected Group -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line"></i> My Payment Progress
                            <small class="text-muted ms-2">(<?= htmlspecialchars($selectedGroupData['group']['group_name']) ?>)</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="paymentProgressChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie"></i> Group Progress
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="chart-container">
                            <canvas id="groupProgressChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <h6><?= number_format($groupProgress, 1) ?>% Complete</h6>
                            <p class="text-muted mb-0"><?= $totalBidsCompleted ?> of <?= $totalMonths ?> months completed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Overview and Recent Activities -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-wallet"></i> My Financial Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="financialChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h6 class="text-success"><?= formatCurrency($selectedTotalPaid) ?></h6>
                                    <small class="text-muted">Paid</small>
                                </div>
                                <div class="col-4">
                                    <h6 class="text-info"><?= formatCurrency($selectedTotalReceived) ?></h6>
                                    <small class="text-muted">Received</small>
                                </div>
                                <div class="col-4">
                                    <h6 class="text-<?= $selectedNetPosition >= 0 ? 'warning' : 'danger' ?>">
                                        <?= formatCurrency(abs($selectedNetPosition)) ?>
                                    </h6>
                                    <small class="text-muted"><?= $selectedNetPosition >= 0 ? 'Gain' : 'Investment' ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock"></i> Recent Group Activities
                            <small class="text-muted ms-2">(<?= htmlspecialchars($selectedGroupData['group']['group_name']) ?>)</small>
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 350px; overflow-y: auto;">
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-trophy text-warning"></i>
                                                Month <?= $activity['month_number'] ?> Winner
                                            </h6>
                                            <p class="mb-1">
                                                <strong><?= htmlspecialchars($activity['winner_name']) ?></strong>
                                                (Member #<?= $activity['winner_number'] ?>)
                                            </p>
                                            <small class="text-muted">
                                                Bid: <?= formatCurrency($activity['bid_amount']) ?> |
                                                Received: <?= formatCurrency($activity['net_payable']) ?>
                                            </small>
                                        </div>
                                        <small class="text-muted">
                                            <?= formatDate($activity['payment_date']) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Bids Won Section for Selected Group -->
        <?php if (!empty($myBidWins)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-trophy"></i> My Winning Bids
                                <small class="text-muted ms-2">(<?= htmlspecialchars($selectedGroupData['group']['group_name']) ?>)</small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($myBidWins as $bid): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card bg-light">
                                            <div class="card-body text-center">
                                                <i class="fas fa-trophy fa-2x text-warning mb-2"></i>
                                                <h5>Month <?= $bid['month_number'] ?></h5>
                                                <p class="mb-1">
                                                    <strong>Bid Amount:</strong> <?= formatCurrency($bid['bid_amount']) ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Received:</strong> <?= formatCurrency($bid['net_payable']) ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?= formatDate($bid['payment_date']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment Status Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-rupee-sign fa-2x text-success mb-2"></i>
                        <h4><?= formatCurrency($selectedTotalPaid) ?></h4>
                        <small class="text-muted">Total Paid</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-gift fa-2x text-primary mb-2"></i>
                        <h4><?= formatCurrency($selectedTotalReceived) ?></h4>
                        <small class="text-muted">Amount Received</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line fa-2x <?= ($mySummary && $mySummary['profit'] >= 0) ? 'text-success' : 'text-danger' ?> mb-2"></i>
                        <h4 class="<?= ($mySummary && $mySummary['profit'] >= 0) ? 'text-success' : 'text-danger' ?>">
                            <?= $mySummary ? formatCurrency($mySummary['profit']) : '₹0' ?>
                        </h4>
                        <small class="text-muted">Profit/Loss</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card status-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                        <h4><?= count($myPayments) ?> / <?= $group['total_members'] ?></h4>
                        <small class="text-muted">Months Paid</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Payment History -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> My Payment History
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Payment Amount</th>
                                        <th>Payment Date</th>
                                        <th>Status</th>
                                        <th>Month Winner</th>
                                        <th>Bid Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($i = 1; $i <= $group['total_members']; $i++): ?>
                                        <?php
                                        $payment = $myPaymentsByMonth[$i] ?? null;
                                        $bid = array_filter($monthlyBids, fn($b) => $b['month_number'] == $i);
                                        $bid = reset($bid);
                                        ?>
                                        <tr>
                                            <td><strong>Month <?= $i ?></strong></td>
                                            <td>
                                                <?php if ($payment): ?>
                                                    <?= formatCurrency($payment['payment_amount']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <?= $bid ? formatCurrency($bid['gain_per_member']) : formatCurrency($group['monthly_contribution']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $payment && $payment['payment_date'] ? formatDate($payment['payment_date']) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if ($payment): ?>
                                                    <span class="badge bg-success">Paid</span>
                                                <?php elseif ($bid): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not Started</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $bid ? htmlspecialchars($bid['member_name']) : '-' ?>
                                                <?php if ($bid && $bid['taken_by_member_id'] == $member['id']): ?>
                                                    <span class="badge bg-primary ms-1">You Won!</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $bid ? formatCurrency($bid['bid_amount']) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if (!$payment && $bid): ?>
                                                    <a href="member_payment.php?month=<?= $i ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-qrcode"></i> Pay
                                                    </a>
                                                <?php elseif ($payment): ?>
                                                    <span class="text-success">
                                                        <i class="fas fa-check-circle"></i> Paid
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Group Summary -->

    </div>

    <script>
        // Chart.js configuration
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.color = '#666';

        // Payment Progress Chart
        const paymentData = <?= json_encode($paymentProgressData) ?>;
        const paymentCtx = document.getElementById('paymentProgressChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'bar',
            data: {
                labels: paymentData.map(p => `Month ${p.month}`),
                datasets: [{
                    label: 'Payment Amount',
                    data: paymentData.map(p => p.amount),
                    backgroundColor: paymentData.map(p => {
                        switch(p.status) {
                            case 'paid': return '#28a745';
                            case 'pending': return '#ffc107';
                            default: return '#e9ecef';
                        }
                    }),
                    borderColor: paymentData.map(p => {
                        switch(p.status) {
                            case 'paid': return '#1e7e34';
                            case 'pending': return '#e0a800';
                            default: return '#dee2e6';
                        }
                    }),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const payment = paymentData[context.dataIndex];
                                return `Status: ${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}`;
                            }
                        }
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

        // Group Progress Pie Chart
        const groupProgress = <?= $groupProgress ?>;
        const groupCtx = document.getElementById('groupProgressChart').getContext('2d');
        new Chart(groupCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Remaining'],
                datasets: [{
                    data: [groupProgress, 100 - groupProgress],
                    backgroundColor: ['#28a745', '#e9ecef'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Financial Overview Chart
        const totalPaid = <?= $totalPaid ?>;
        const totalReceived = <?= $totalReceived ?>;
        const financialCtx = document.getElementById('financialChart').getContext('2d');
        new Chart(financialCtx, {
            type: 'doughnut',
            data: {
                labels: ['Amount Paid', 'Amount Received'],
                datasets: [{
                    data: [totalPaid, totalReceived],
                    backgroundColor: ['#dc3545', '#17a2b8'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₹' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Add hover effects to cards
        document.querySelectorAll('.status-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            });
        });

        // Auto-refresh dashboard every 2 minutes
        setInterval(() => {
            console.log('Auto-refreshing member dashboard...');
            // In a real application, you'd fetch updated data via AJAX here
        }, 120000); // 2 minutes

        // Add smooth scrolling for any anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation for buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.href && !this.href.includes('#')) {
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.classList.add('fa-spin');
                    }
                }
            });
        });

        // Initialize tooltips if any
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Group selection function
        function selectGroup(groupId) {
            window.location.href = 'member_dashboard.php?selected_group=' + groupId;
        }

        // Add hover effect to group rows
        document.addEventListener('DOMContentLoaded', function() {
            const groupRows = document.querySelectorAll('.group-row');
            groupRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('table-success')) {
                        this.style.backgroundColor = '';
                    }
                });
            });
        });
    </script>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Language Switcher Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize language dropdown
            const languageDropdown = document.getElementById('languageDropdown');
            if (languageDropdown) {
                // Remove any existing event listeners
                languageDropdown.removeAttribute('data-bs-toggle');

                // Add manual click handler
                languageDropdown.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const dropdownMenu = this.nextElementSibling;
                    if (dropdownMenu) {
                        // Close all other dropdowns
                        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                            if (menu !== dropdownMenu) {
                                menu.classList.remove('show');
                            }
                        });

                        // Toggle current dropdown
                        const isOpen = dropdownMenu.classList.contains('show');
                        if (isOpen) {
                            dropdownMenu.classList.remove('show');
                            this.setAttribute('aria-expanded', 'false');
                        } else {
                            dropdownMenu.classList.add('show');
                            this.setAttribute('aria-expanded', 'true');
                        }
                    }
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!languageDropdown.contains(e.target)) {
                        const dropdownMenu = languageDropdown.nextElementSibling;
                        if (dropdownMenu && dropdownMenu.classList.contains('show')) {
                            dropdownMenu.classList.remove('show');
                            languageDropdown.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>

