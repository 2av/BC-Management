<?php
// Mitra Niidhi Samooh - Community Fund Management System
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'bc_simple');
define('DB_USER', 'root');
define('DB_PASS', '');


// Live configuration
// define('DB_HOST', 'localhost:3306');
// define('DB_NAME', 'priyank2_bc');
// define('DB_USER', 'priyank2');
// define('DB_PASS', '3nS3r-L!15AxHn');


// Application Configuration
define('APP_NAME', 'Mitra Niidhi Samooh');

// Database Connection
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Authentication Functions
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function isMemberLoggedIn() {
    return isset($_SESSION['member_id']);
}

function isLoggedIn() {
    return isAdminLoggedIn() || isMemberLoggedIn();
}

function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireMemberLogin() {
    if (!isMemberLoggedIn()) {
        header('Location: member_login.php');
        exit;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function adminLogin($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['user_type'] = 'admin';
        return true;
    }

    return false;
}

function memberLogin($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT m.*, g.group_name FROM members m JOIN bc_groups g ON m.group_id = g.id WHERE m.username = ? AND m.status = 'active'");
    $stmt->execute([$username]);
    $member = $stmt->fetch();

    if ($member && password_verify($password, $member['password'])) {
        $_SESSION['member_id'] = $member['id'];
        $_SESSION['member_name'] = $member['member_name'];
        $_SESSION['group_id'] = $member['group_id'];
        $_SESSION['group_name'] = $member['group_name'];
        $_SESSION['user_type'] = 'member';
        return true;
    }

    return false;
}

function logout() {
    $userType = $_SESSION['user_type'] ?? 'admin';
    session_destroy();

    if ($userType === 'member') {
        header('Location: member_login.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

function getCurrentMember() {
    if (!isMemberLoggedIn()) {
        return null;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$_SESSION['member_id']]);
    return $stmt->fetch();
}

// Utility Functions
function formatCurrency($amount) {
    return '₹' . number_format($amount, 0);
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function setMessage($message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'success';
        unset($_SESSION['message'], $_SESSION['message_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

// BC Functions
function getAllGroups() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM bc_groups ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getGroupById($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getGroupMembers($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM members WHERE group_id = ? ORDER BY member_number");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

function getMonthlyBids($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT mb.*, m.member_name 
        FROM monthly_bids mb 
        LEFT JOIN members m ON mb.taken_by_member_id = m.id 
        WHERE mb.group_id = ? 
        ORDER BY mb.month_number
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

function getMemberPayments($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT mp.*, m.member_name, m.member_number 
        FROM member_payments mp 
        JOIN members m ON mp.member_id = m.id 
        WHERE mp.group_id = ? 
        ORDER BY m.member_number, mp.month_number
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

function getMemberSummary($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT ms.*, m.member_name, m.member_number 
        FROM member_summary ms 
        JOIN members m ON ms.member_id = m.id 
        WHERE ms.group_id = ? 
        ORDER BY m.member_number
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

function updateMemberSummary($groupId, $memberId) {
    $pdo = getDB();
    
    // Calculate total paid
    $stmt = $pdo->prepare("SELECT SUM(payment_amount) as total FROM member_payments WHERE group_id = ? AND member_id = ? AND payment_status = 'paid'");
    $stmt->execute([$groupId, $memberId]);
    $totalPaid = $stmt->fetchColumn() ?: 0;
    
    // Calculate given amount (if member won any month)
    $stmt = $pdo->prepare("SELECT SUM(net_payable) as total FROM monthly_bids WHERE group_id = ? AND taken_by_member_id = ?");
    $stmt->execute([$groupId, $memberId]);
    $givenAmount = $stmt->fetchColumn() ?: 0;
    
    // Calculate profit
    $profit = $givenAmount - $totalPaid;
    
    // Update or insert summary
    $stmt = $pdo->prepare("
        INSERT INTO member_summary (group_id, member_id, total_paid, given_amount, profit) 
        VALUES (?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        total_paid = VALUES(total_paid), 
        given_amount = VALUES(given_amount), 
        profit = VALUES(profit)
    ");
    
    $stmt->execute([$groupId, $memberId, $totalPaid, $givenAmount, $profit]);
}

function calculateGainPerMember($totalCollection, $bidAmount, $totalMembers) {
    $netPayable = $totalCollection - $bidAmount;
    return $netPayable / $totalMembers;
}

// Bidding System Functions
function getOpenBiddingMonths($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM month_bidding_status
        WHERE group_id = ? AND bidding_status = 'open'
        ORDER BY month_number
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

function getMemberBids($groupId, $memberId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT mb.*, mbs.bidding_status
        FROM member_bids mb
        JOIN month_bidding_status mbs ON mb.group_id = mbs.group_id AND mb.month_number = mbs.month_number
        WHERE mb.group_id = ? AND mb.member_id = ?
        ORDER BY mb.month_number
    ");
    $stmt->execute([$groupId, $memberId]);
    return $stmt->fetchAll();
}

function getRandomPicks($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT rp.*,
               m.member_name,
               om.member_name as admin_override_member_name,
               au.full_name as admin_override_by_name
        FROM random_picks rp
        JOIN members m ON rp.selected_member_id = m.id
        LEFT JOIN members om ON rp.admin_override_member_id = om.id
        LEFT JOIN admin_users au ON rp.admin_override_by = au.id
        WHERE rp.group_id = ?
        ORDER BY rp.month_number
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

function getCurrentActiveMonthNumber($groupId) {
    $pdo = getDB();

    // Get group info to determine total months
    $stmt = $pdo->prepare("SELECT total_members FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();

    if (!$group) {
        return null;
    }

    // Get the last completed month from monthly_bids
    $stmt = $pdo->prepare("
        SELECT MAX(month_number) as last_month
        FROM monthly_bids
        WHERE group_id = ?
    ");
    $stmt->execute([$groupId]);
    $lastCompletedMonth = $stmt->fetch()['last_month'] ?? 0;

    // Current active month is the next month after last completed
    $currentMonth = $lastCompletedMonth + 1;

    // If current month exceeds total months, the group is complete
    if ($currentMonth > $group['total_members']) {
        return null; // No active month, group is complete
    }

    return $currentMonth;
}

function getAvailableMembersForRandomPick($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT m.id, m.member_name, m.member_number
        FROM members m
        WHERE m.group_id = ?
        AND m.status = 'active'
        AND m.id NOT IN (
            SELECT DISTINCT taken_by_member_id
            FROM monthly_bids
            WHERE group_id = ? AND taken_by_member_id IS NOT NULL
        )
        AND m.id NOT IN (
            SELECT DISTINCT COALESCE(admin_override_member_id, selected_member_id)
            FROM random_picks
            WHERE group_id = ? AND selected_member_id IS NOT NULL
        )
        ORDER BY m.member_number
    ");
    $stmt->execute([$groupId, $groupId, $groupId]);
    return $stmt->fetchAll();
}

function canMemberBid($memberId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT has_won_month FROM members WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    return !$member['has_won_month'];
}

function getBiddingStatistics($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT
            COUNT(CASE WHEN bidding_status = 'open' THEN 1 END) as open_months,
            COUNT(CASE WHEN bidding_status = 'closed' THEN 1 END) as closed_months,
            COUNT(CASE WHEN bidding_status = 'completed' THEN 1 END) as completed_months,
            COUNT(CASE WHEN bidding_status = 'not_started' THEN 1 END) as pending_months
        FROM month_bidding_status
        WHERE group_id = ?
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetch();
}

function getMemberGroups($memberId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.*, m.id as member_id, m.member_number, m.status as member_status, m.created_at as member_joined_date
        FROM bc_groups g
        JOIN members m ON g.id = m.group_id
        WHERE m.member_name = (SELECT member_name FROM members WHERE id = ?) AND m.status = 'active'
        ORDER BY g.start_date DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

function getCurrentMonthPaymentInfo($groupId, $memberInGroupId) {
    $pdo = getDB();

    // Get group info to determine total months
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();

    if (!$group) {
        return null;
    }

    // Get all monthly bids for this group to determine current month
    $stmt = $pdo->prepare("
        SELECT month_number FROM monthly_bids
        WHERE group_id = ?
        ORDER BY month_number DESC
        LIMIT 1
    ");
    $stmt->execute([$groupId]);
    $lastBidMonth = $stmt->fetch();

    // Current month is either the next month after last bid, or month 1 if no bids yet
    $currentMonth = $lastBidMonth ? $lastBidMonth['month_number'] + 1 : 1;

    // If current month exceeds total months, the group is complete
    if ($currentMonth > $group['total_members']) {
        $currentMonth = $group['total_members']; // Show last month
    }

    // Check if there's a completed bid for current month
    $stmt = $pdo->prepare("
        SELECT mb.*, m.member_name as winner_name
        FROM monthly_bids mb
        LEFT JOIN members m ON mb.taken_by_member_id = m.id
        WHERE mb.group_id = ? AND mb.month_number = ?
    ");
    $stmt->execute([$groupId, $currentMonth]);
    $currentMonthBid = $stmt->fetch();

    // Check if current member has payment for this month
    $stmt = $pdo->prepare("
        SELECT * FROM member_payments
        WHERE group_id = ? AND member_id = ? AND month_number = ?
    ");
    $stmt->execute([$groupId, $memberInGroupId, $currentMonth]);
    $currentMonthPayment = $stmt->fetch();

    // Determine bidding status
    $biddingStatus = 'open';
    if ($currentMonthBid) {
        $biddingStatus = 'completed';
    } elseif ($currentMonth > $group['total_members']) {
        $biddingStatus = 'closed';
    }

    return [
        'month_number' => $currentMonth,
        'bidding_status' => $biddingStatus,
        'bid_exists' => (bool)$currentMonthBid,
        'bid_amount' => $currentMonthBid ? $currentMonthBid['bid_amount'] : null,
        'winner_name' => $currentMonthBid ? $currentMonthBid['winner_name'] : null,
        'payment_status' => $currentMonthPayment ? $currentMonthPayment['payment_status'] : 'pending',
        'payment_amount' => $currentMonthPayment ? $currentMonthPayment['payment_amount'] : null,
        'payment_date' => $currentMonthPayment ? $currentMonthPayment['payment_date'] : null
    ];
}
?>
