<?php
/**
 * Super Admin Logout Page
 * BC Management System
 * 
 * Handles logout for super admin users
 */

session_start();

// Check if super admin is logged in
$isSuperAdmin = isset($_SESSION['super_admin_id']);
$superAdminName = $_SESSION['super_admin_name'] ?? 'Super Administrator';

// Log the logout action if possible
if ($isSuperAdmin) {
    try {
        require_once '../config/config.php';
        
        // Log audit action if function exists
        if (function_exists('logAuditAction')) {
            logAuditAction(null, 'super_admin', $_SESSION['super_admin_id'], 'logout');
        }
    } catch (Exception $e) {
        // Continue with logout even if logging fails
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a success message for the login page
session_start();
$_SESSION['logout_message'] = 'Super Admin logged out successfully!';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .logout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 500px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .logout-container::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .logout-icon {
            font-size: 5rem;
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .logout-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logout-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        
        .success-alert {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: none;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: #155724;
            box-shadow: 0 8px 25px rgba(67, 233, 123, 0.2);
        }
        
        .countdown {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            color: #667eea;
        }
        
        .countdown-number {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-group-custom {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-super {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2rem;
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-super:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-outline-super {
            background: transparent;
            border: 2px solid #667eea;
            border-radius: 15px;
            padding: 1rem 2rem;
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-outline-super:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .security-note {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .logout-container {
                margin: 1rem;
                padding: 2rem;
            }
            
            .logout-title {
                font-size: 2rem;
            }
            
            .logout-icon {
                font-size: 4rem;
            }
            
            .btn-group-custom {
                flex-direction: column;
                align-items: center;
            }
            
            .btn-super, .btn-outline-super {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-crown"></i>
        </div>
        
        <h1 class="logout-title">Logout Successful</h1>
        
        <?php if ($isSuperAdmin): ?>
            <p class="logout-subtitle">
                Super Administrator <strong><?= htmlspecialchars($superAdminName) ?></strong> session has been terminated successfully.
            </p>
        <?php else: ?>
            <p class="logout-subtitle">
                You have been logged out successfully.
            </p>
        <?php endif; ?>
        
        <div class="success-alert">
            <i class="fas fa-shield-alt me-2"></i>
            Your session has been securely ended and all data has been cleared.
        </div>
        
        <div class="countdown">
            <div>Redirecting to login page in</div>
            <div class="countdown-number" id="countdown">5</div>
            <div><small>Click anywhere to redirect immediately</small></div>
        </div>
        
        <div class="btn-group-custom">
            <a href="../auth/super_admin_login.php" class="btn-super">
                <i class="fas fa-crown me-2"></i>Super Admin Login
            </a>
            <a href="../auth/login.php" class="btn-outline-super">
                <i class="fas fa-user-shield me-2"></i>Client Admin Login
            </a>
            <a href="../auth/member_login.php" class="btn-outline-super">
                <i class="fas fa-user me-2"></i>Member Login
            </a>
        </div>
        
        <div class="security-note">
            <i class="fas fa-info-circle me-2"></i>
            For security reasons, please close your browser if you're using a shared computer.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Countdown timer
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '../auth/super_admin_login.php';
            }
        }, 1000);
        
        // Allow user to click anywhere to redirect immediately
        document.addEventListener('click', function(e) {
            // Don't redirect if clicking on a link
            if (e.target.tagName !== 'A' && !e.target.closest('a')) {
                clearInterval(timer);
                window.location.href = '../auth/super_admin_login.php';
            }
        });
        
        // Prevent back button from working
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
        
        // Clear any cached data
        if ('caches' in window) {
            caches.keys().then(function(names) {
                names.forEach(function(name) {
                    caches.delete(name);
                });
            });
        }
        
        // Clear localStorage and sessionStorage
        try {
            localStorage.clear();
            sessionStorage.clear();
        } catch (e) {
            console.log('Storage clearing failed:', e);
        }
    </script>
</body>
</html>
