<?php
/**
 * Common Functions for BC Management System
 * This file contains utility functions used across the application
 */

// Utility Functions
function formatCurrency($amount, $currency = 'INR', $decimals = 0) {
    switch ($currency) {
        case 'INR':
            return '₹' . number_format($amount, $decimals);
        case 'USD':
            return '$' . number_format($amount, $decimals);
        default:
            return $currency . ' ' . number_format($amount, $decimals);
    }
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function redirect($url) {
    header("Location: $url");
    exit;
}

// Message System
function setMessage($message, $type = 'success') {
    $_SESSION['message'] = [
        'text' => $message,
        'type' => $type
    ];
}

function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return [
            'message' => $message['text'],
            'type' => $message['type']
        ];
    }
    return null;
}

// Database Helper Functions
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = getDatabaseConnection();
    }

    return $pdo;
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

function getMemberById($memberId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$memberId]);
    return $stmt->fetch();
}

// Group Functions
function getAllGroups() {
    $pdo = getDB();
    
    // Check if we're in multi-tenant mode
    if (isset($_SESSION['client_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['client_id']]);
    } else {
        $stmt = $pdo->query("SELECT * FROM bc_groups ORDER BY created_at DESC");
    }
    
    return $stmt->fetchAll();
}

function getGroupById($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    return $stmt->fetch();
}

// Member Functions
function getAllMembers() {
    $pdo = getDB();
    
    // Check if we're in multi-tenant mode
    if (isset($_SESSION['client_id'])) {
        $stmt = $pdo->prepare("
            SELECT m.*, g.group_name 
            FROM members m 
            JOIN bc_groups g ON m.group_id = g.id 
            WHERE g.client_id = ? 
            ORDER BY m.member_name
        ");
        $stmt->execute([$_SESSION['client_id']]);
    } else {
        $stmt = $pdo->query("
            SELECT m.*, g.group_name 
            FROM members m 
            JOIN bc_groups g ON m.group_id = g.id 
            ORDER BY m.member_name
        ");
    }
    
    return $stmt->fetchAll();
}

function getGroupMembers($groupId) {
    $pdo = getDB();

    // Check if we're in multi-tenant mode
    if (isset($_SESSION['client_id'])) {
        $stmt = $pdo->prepare("
            SELECT m.* FROM members m
            JOIN bc_groups g ON m.group_id = g.id
            WHERE m.group_id = ? AND g.client_id = ? AND m.status = 'active'
            ORDER BY m.member_number
        ");
        $stmt->execute([$groupId, $_SESSION['client_id']]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM members
            WHERE group_id = ? AND status = 'active'
            ORDER BY member_number
        ");
        $stmt->execute([$groupId]);
    }

    return $stmt->fetchAll();
}

function getMemberGroups($memberId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.*, gm.member_id, gm.id as assignment_id, gm.member_number, gm.status as member_status, gm.joined_date as member_joined_date
        FROM bc_groups g
        JOIN group_members gm ON g.id = gm.group_id
        WHERE gm.member_id = ? AND gm.status = 'active'
        ORDER BY g.start_date DESC
    ");
    $stmt->execute([$memberId]);
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

function getRandomPicks($groupId) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT rp.*,
               m.member_name,
               om.member_name as admin_override_member_name
        FROM random_picks rp
        LEFT JOIN members m ON rp.selected_member_id = m.id
        LEFT JOIN members om ON rp.admin_override_member_id = om.id
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

    $totalMonths = $group['total_members'];

    // Get all completed months (months with confirmed bids)
    $stmt = $pdo->prepare("SELECT month_number FROM monthly_bids WHERE group_id = ? ORDER BY month_number");
    $stmt->execute([$groupId]);
    $completedMonths = array_column($stmt->fetchAll(), 'month_number');

    // Find the next month that hasn't been completed
    for ($month = 1; $month <= $totalMonths; $month++) {
        if (!in_array($month, $completedMonths)) {
            return $month;
        }
    }

    // All months completed
    return null;
}

// Language Functions
function getCurrentLanguage() {
    return $_SESSION['language'] ?? $_COOKIE['bc_language'] ?? 'en';
}

function setLanguage($language) {
    $available_languages = [
        'en' => ['name' => 'English', 'flag' => '🇺🇸'],
        'hi' => ['name' => 'हिंदी', 'flag' => '🇮🇳']
    ];

    if (array_key_exists($language, $available_languages)) {
        $_SESSION['language'] = $language;
        setcookie('bc_language', $language, time() + (86400 * 30), '/'); // 30 days
        return true;
    }
    return false;
}

// Load language file
function loadLanguage($langCode = null) {
    if ($langCode === null) {
        $langCode = getCurrentLanguage();
    }

    $langFile = __DIR__ . "/languages/{$langCode}.php";
    if (file_exists($langFile)) {
        include $langFile;
        return isset($lang) ? $lang : [];
    }

    // Fallback to English
    $fallbackFile = __DIR__ . "/languages/en.php";
    if (file_exists($fallbackFile)) {
        include $fallbackFile;
        return isset($lang) ? $lang : [];
    }

    return [];
}

// Translation function
function t($key, $default = null) {
    static $translations = null;

    if ($translations === null) {
        $translations = loadLanguage();
    }

    return $translations[$key] ?? $default ?? $key;
}

// Available languages
function getAvailableLanguages() {
    return [
        'en' => ['name' => 'English', 'flag' => '🇺🇸'],
        'hi' => ['name' => 'हिंदी', 'flag' => '🇮🇳']
    ];
}

// Handle language change
if (isset($_GET['change_language'])) {
    $newLanguage = $_GET['change_language'];
    if (setLanguage($newLanguage)) {
        // Build redirect URL without the change_language parameter
        $currentUrl = $_SERVER['REQUEST_URI'];
        $urlParts = parse_url($currentUrl);
        $path = $urlParts['path'] ?? '';

        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            unset($queryParams['change_language']);
            $queryString = http_build_query($queryParams);
            $redirectUrl = $path . ($queryString ? '?' . $queryString : '');
        } else {
            $redirectUrl = $path;
        }

        redirect($redirectUrl);
    }
}

// Member Summary Functions
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
?>
