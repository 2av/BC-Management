<?php
/**
 * Multi-Tenant Configuration Loader
 * This file safely loads the multi-tenant configuration without conflicts
 */

// Load base configuration first
require_once 'config.php';

// Multi-tenant specific functions that extend the base config

// Super Admin Authentication Functions (only if not already defined)
if (!function_exists('isSuperAdminLoggedIn')) {
    function isSuperAdminLoggedIn() {
        return isset($_SESSION['super_admin_id']);
    }
}

if (!function_exists('isClientAdminLoggedIn')) {
    function isClientAdminLoggedIn() {
        return isset($_SESSION['client_admin_id']) && isset($_SESSION['client_id']);
    }
}

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
            header('Location: client_login.php');
            exit;
        }
    }
}

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
            
            // Log the login if audit function exists
            if (function_exists('logAuditAction')) {
                logAuditAction(null, 'super_admin', $user['id'], 'login', 'super_admins', $user['id']);
            }
            
            return true;
        }

        return false;
    }
}

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
            
            // Log the login if audit function exists
            if (function_exists('logAuditAction')) {
                logAuditAction($user['client_id'], 'client_admin', $user['id'], 'login', 'client_admins', $user['id']);
            }
            
            return true;
        }

        return false;
    }
}

// Client Management Functions (only if not already defined)
if (!function_exists('getAllClients')) {
    function getAllClients() {
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

if (!function_exists('getClientById')) {
    function getClientById($id) {
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

if (!function_exists('createClient')) {
    function createClient($data) {
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
                createClientAdmin($clientId, [
                    'username' => $data['admin_username'],
                    'password' => $data['admin_password'],
                    'full_name' => $data['contact_person'],
                    'email' => $data['email'],
                    'phone' => $data['phone']
                ]);
            }
            
            // Create default payment config
            createDefaultPaymentConfig($clientId);
            
            return $clientId;
        }
        
        return false;
    }
}

if (!function_exists('createClientAdmin')) {
    function createClientAdmin($clientId, $data) {
        $pdo = getDB();
        
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO client_admins (client_id, username, password, full_name, email, phone, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $clientId, $data['username'], $hashedPassword, $data['full_name'],
            $data['email'], $data['phone'], $_SESSION['super_admin_id'] ?? null
        ]);
    }
}

if (!function_exists('createDefaultPaymentConfig')) {
    function createDefaultPaymentConfig($clientId) {
        $pdo = getDB();
        
        $defaultConfigs = [
            ['upi_id', '', 'UPI ID for receiving payments'],
            ['bank_account_name', '', 'Bank account holder name'],
            ['payment_note', 'BC Group Monthly Payment', 'Default payment note/description'],
            ['qr_enabled', '1', 'Enable/disable QR code payments (1=enabled, 0=disabled)']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO payment_config (client_id, config_key, config_value, description) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($defaultConfigs as $config) {
            $stmt->execute([$clientId, $config[0], $config[1], $config[2]]);
        }
    }
}

// Audit Logging Function (simple version)
if (!function_exists('logAuditAction')) {
    function logAuditAction($clientId, $userType, $userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        try {
            $pdo = getDB();
            
            // Check if audit_log table exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
            if (!$stmt->fetch()) {
                return; // Skip logging if table doesn't exist
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (client_id, user_type, user_id, action, table_name, record_id, 
                                      old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $clientId, $userType, $userId, $action, $tableName, $recordId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Silently fail if logging doesn't work
            error_log("Audit logging failed: " . $e->getMessage());
        }
    }
}

// Enhanced logout function for multi-tenant
if (!function_exists('multiTenantLogout')) {
    function multiTenantLogout() {
        $userType = $_SESSION['user_type'] ?? 'client_admin';
        
        // Log the logout if possible
        if (isset($_SESSION['super_admin_id'])) {
            logAuditAction(null, 'super_admin', $_SESSION['super_admin_id'], 'logout');
        } elseif (isset($_SESSION['client_admin_id'])) {
            logAuditAction($_SESSION['client_id'], 'client_admin', $_SESSION['client_admin_id'], 'logout');
        } elseif (isset($_SESSION['member_id'])) {
            $clientId = getCurrentClientId();
            logAuditAction($clientId, 'member', $_SESSION['member_id'], 'logout');
        }
        
        session_destroy();

        // Redirect based on user type
        switch ($userType) {
            case 'super_admin':
                header('Location: super_admin_login.php');
                break;
            case 'client_admin':
                header('Location: client_login.php');
                break;
            case 'member':
                header('Location: member_login.php');
                break;
            default:
                header('Location: landing.php');
        }
        exit;
    }
}
?>
