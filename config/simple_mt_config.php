<?php
/**
 * Simple Multi-Tenant Configuration
 * This file provides a clean way to load multi-tenant functionality
 */

// Load base configuration
require_once 'config.php';

// Only add functions that are absolutely necessary and not already defined

// Super Admin Authentication
if (!function_exists('superAdminLogin')) {
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
}

// Client Admin Authentication
if (!function_exists('clientAdminLogin')) {
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
}

// Access Control Functions
if (!function_exists('requireSuperAdminLogin')) {
    function requireSuperAdminLogin() {
        if (!isSuperAdminLoggedIn()) {
            header('Location: super_admin_login.php');
            exit;
        }
    }
}

if (!function_exists('requireClientAdminLogin')) {
    function requireClientAdminLogin() {
        if (!isClientAdminLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
}

// Client Management Functions
if (!function_exists('getAllClientsSimple')) {
    function getAllClientsSimple() {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT c.*, sa.full_name as created_by_name
            FROM clients c 
            LEFT JOIN super_admins sa ON c.created_by = sa.id
            ORDER BY c.created_at DESC
        ");
        return $stmt->fetchAll();
    }
}

if (!function_exists('getClientByIdSimple')) {
    function getClientByIdSimple($id) {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT c.*, sa.full_name as created_by_name 
            FROM clients c 
            LEFT JOIN super_admins sa ON c.created_by = sa.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}

if (!function_exists('createClientSimple')) {
    function createClientSimple($data) {
        $pdo = getDB();
        
        $stmt = $pdo->prepare("
            INSERT INTO clients (client_name, company_name, contact_person, email, phone, 
                               address, city, state, country, pincode, subscription_plan, 
                               max_groups, max_members_per_group, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $data['client_name'], $data['company_name'], $data['contact_person'],
            $data['email'], $data['phone'], $data['address'], $data['city'],
            $data['state'], $data['country'], $data['pincode'], $data['subscription_plan'],
            $data['max_groups'], $data['max_members_per_group'], $_SESSION['super_admin_id']
        ]);
        
        if ($result) {
            $clientId = $pdo->lastInsertId();
            
            // Create default client admin if provided
            if (!empty($data['admin_username']) && !empty($data['admin_password'])) {
                $hashedPassword = password_hash($data['admin_password'], PASSWORD_DEFAULT);
                
                $adminStmt = $pdo->prepare("
                    INSERT INTO client_admins (client_id, username, password, full_name, email, phone, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $adminStmt->execute([
                    $clientId, $data['admin_username'], $hashedPassword, $data['contact_person'],
                    $data['email'], $data['phone'], $_SESSION['super_admin_id'] ?? null
                ]);
            }
            
            return $clientId;
        }
        
        return false;
    }
}

// Simple audit logging
if (!function_exists('logSimpleAudit')) {
    function logSimpleAudit($clientId, $userType, $userId, $action) {
        try {
            $pdo = getDB();
            
            // Check if audit_log table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
            if (!$stmt->fetch()) {
                return; // Skip if table doesn't exist
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (client_id, user_type, user_id, action, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $clientId, $userType, $userId, $action, $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            // Silently fail
        }
    }
}
?>
