<?php
/**
 * Authentication Functions for BC Management System
 * This file contains all authentication-related functions
 */

// Authentication Check Functions
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function isMemberLoggedIn() {
    return isset($_SESSION['member_id']);
}

function isLoggedIn() {
    return isAdminLoggedIn() || isMemberLoggedIn();
}

function isSuperAdminLoggedIn() {
    return isset($_SESSION['super_admin_id']);
}

function isClientAdminLoggedIn() {
    return isset($_SESSION['client_admin_id']) && isset($_SESSION['client_id']);
}

// Login Requirement Functions
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function requireMemberLogin() {
    if (!isMemberLoggedIn()) {
        header('Location: ../auth/member_login.php');
        exit;
    }
}

function requireSuperAdminLogin() {
    if (!isSuperAdminLoggedIn()) {
        header('Location: ../auth/super_admin_login.php');
        exit;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../auth/login.php');
        exit;
    }
}

// Login Functions
function adminLogin($username, $password) {
    $pdo = getDB();

    // First try client admin login (multi-tenant)
    $stmt = $pdo->prepare("
        SELECT ca.*, c.client_name, c.status as client_status
        FROM client_admins ca
        JOIN clients c ON ca.client_id = c.id
        WHERE ca.username = ? AND ca.status = 'active' AND c.status = 'active'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['client_admin_id'] = $user['id'];
        $_SESSION['client_id'] = $user['client_id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['client_admin_name'] = $user['full_name'];
        $_SESSION['client_name'] = $user['client_name'];
        $_SESSION['user_type'] = 'admin';

        // Update last login
        $updateStmt = $pdo->prepare("UPDATE client_admins SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        return true;
    }

    // Fallback to legacy admin_users table for backward compatibility
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['user_type'] = 'admin';
        $_SESSION['client_id'] = 1; // Default client for legacy users
        return true;
    }

    return false;
}

function memberLogin($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT m.*, g.group_name, g.client_id, c.client_name
        FROM members m
        JOIN bc_groups g ON m.group_id = g.id
        JOIN clients c ON g.client_id = c.id
        WHERE m.username = ? AND m.status = 'active' AND c.status = 'active'
    ");
    $stmt->execute([$username]);
    $member = $stmt->fetch();

    if ($member && password_verify($password, $member['password'])) {
        $_SESSION['member_id'] = $member['id'];
        $_SESSION['member_name'] = $member['member_name'];
        $_SESSION['group_id'] = $member['group_id'];
        $_SESSION['group_name'] = $member['group_name'];
        $_SESSION['client_id'] = $member['client_id'];
        $_SESSION['client_name'] = $member['client_name'];
        $_SESSION['user_type'] = 'member';
        return true;
    }

    return false;
}

function superAdminLogin($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM super_admins WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['super_admin_id'] = $user['id'];
        $_SESSION['super_admin_name'] = $user['full_name'];
        $_SESSION['user_type'] = 'super_admin';
        return true;
    }
    return false;
}

function clientAdminLogin($username, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT ca.*, c.client_name, c.status as client_status
        FROM client_admins ca
        JOIN clients c ON ca.client_id = c.id
        WHERE ca.username = ? AND ca.status = 'active' AND c.status = 'active'
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['client_admin_id'] = $user['id'];
        $_SESSION['client_id'] = $user['client_id'];
        $_SESSION['client_admin_name'] = $user['full_name'];
        $_SESSION['client_name'] = $user['client_name'];
        $_SESSION['user_type'] = 'client_admin';

        // Update last login
        $updateStmt = $pdo->prepare("UPDATE client_admins SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        return true;
    }
    return false;
}

// Logout Function
function logout() {
    $userType = $_SESSION['user_type'] ?? 'admin';
    session_destroy();

    // Redirect based on user type
    switch ($userType) {
        case 'super_admin':
            header('Location: ../auth/super_admin_login.php');
            break;
        case 'member':
            header('Location: ../auth/member_login.php');
            break;
        case 'admin':
        default:
            header('Location: ../auth/login.php');
            break;
    }
    exit;
}
?>
