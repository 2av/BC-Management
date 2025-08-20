<?php
/**
 * Middleware for Role-Based Access Control
 * BC Management System
 */

/**
 * Check if user has the required role
 * @param string $role Required role (admin, member, superadmin)
 * @param bool $redirect Whether to redirect if access denied
 * @return bool True if user has access, false otherwise
 */
function checkRole($role, $redirect = true) {
    $hasAccess = false;
    
    switch (strtolower($role)) {
        case 'admin':
            $hasAccess = isAdminLoggedIn() || isClientAdminLoggedIn();
            break;
            
        case 'member':
            $hasAccess = isMemberLoggedIn();
            break;
            
        case 'superadmin':
        case 'super_admin':
            $hasAccess = isSuperAdminLoggedIn();
            break;
            
        case 'any':
            $hasAccess = isLoggedIn() || isSuperAdminLoggedIn();
            break;
            
        default:
            $hasAccess = false;
    }
    
    if (!$hasAccess && $redirect) {
        // Determine where to redirect based on role
        switch (strtolower($role)) {
            case 'admin':
                header("Location: ../auth/login.php");
                break;
            case 'member':
                header("Location: ../auth/member_login.php");
                break;
            case 'superadmin':
            case 'super_admin':
                header("Location: ../auth/super_admin_login.php");
                break;
            default:
                header("Location: ../auth/login.php");
        }
        exit;
    }
    
    return $hasAccess;
}

/**
 * Check if user can access a specific client's data
 * @param int $clientId Client ID to check access for
 * @return bool True if user has access, false otherwise
 */
function checkClientAccess($clientId) {
    // Super admin can access all clients
    if (isSuperAdminLoggedIn()) {
        return true;
    }
    
    // Client admin can only access their own client
    if (isClientAdminLoggedIn()) {
        return $_SESSION['client_id'] == $clientId;
    }
    
    // Regular admin can access default client (legacy)
    if (isAdminLoggedIn()) {
        return $clientId == ($_SESSION['client_id'] ?? 1);
    }
    
    // Members can only access their own client
    if (isMemberLoggedIn()) {
        return $_SESSION['client_id'] == $clientId;
    }
    
    return false;
}

/**
 * Check if user can access a specific group
 * @param int $groupId Group ID to check access for
 * @return bool True if user has access, false otherwise
 */
function checkGroupAccess($groupId) {
    $pdo = getDB();
    
    // Get group's client_id
    $stmt = $pdo->prepare("SELECT client_id FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch();
    
    if (!$group) {
        return false;
    }
    
    return checkClientAccess($group['client_id']);
}

/**
 * Check if member can access a specific group
 * @param int $groupId Group ID to check access for
 * @return bool True if member has access, false otherwise
 */
function checkMemberGroupAccess($groupId) {
    if (!isMemberLoggedIn()) {
        return false;
    }
    
    // Member can only access their own group
    return $_SESSION['group_id'] == $groupId;
}

/**
 * Ensure user is logged in and has required role
 * This is a convenience function that combines authentication and authorization
 * @param string $role Required role
 */
function requireRole($role) {
    checkRole($role, true);
}

/**
 * Get current user's role
 * @return string Current user's role
 */
function getCurrentUserRole() {
    if (isSuperAdminLoggedIn()) {
        return 'superadmin';
    } elseif (isClientAdminLoggedIn()) {
        return 'client_admin';
    } elseif (isAdminLoggedIn()) {
        return 'admin';
    } elseif (isMemberLoggedIn()) {
        return 'member';
    }
    
    return 'guest';
}

/**
 * Get current user's client ID
 * @return int|null Current user's client ID
 */
function getCurrentClientId() {
    return $_SESSION['client_id'] ?? null;
}

/**
 * Check if current user is in multi-tenant mode
 * @return bool True if in multi-tenant mode
 */
function isMultiTenant() {
    return isset($_SESSION['client_id']) && $_SESSION['client_id'] > 1;
}

/**
 * Apply client filter to SQL query if needed
 * @param string $query SQL query
 * @param string $tableAlias Table alias for the groups table
 * @return string Modified query with client filter
 */
function applyClientFilter($query, $tableAlias = 'g') {
    if (isSuperAdminLoggedIn()) {
        // Super admin sees all data
        return $query;
    }
    
    $clientId = getCurrentClientId();
    if ($clientId) {
        // Add client filter
        if (stripos($query, 'WHERE') !== false) {
            $query .= " AND {$tableAlias}.client_id = {$clientId}";
        } else {
            $query .= " WHERE {$tableAlias}.client_id = {$clientId}";
        }
    }
    
    return $query;
}
?>
