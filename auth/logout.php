<?php
/**
 * Logout Page
 * BC Management System
 * 
 * Handles logout for both admin and member users
 */

session_start();

// Determine user type before destroying session
$userType = '';
$redirectPage = 'index.php';

if (isset($_SESSION['admin_id'])) {
    $userType = 'Admin';
    $redirectPage = 'login.php';
} elseif (isset($_SESSION['member_id'])) {
    $userType = 'Member';
    $redirectPage = 'member_login.php';
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
if ($userType === 'Admin') {
    $_SESSION['logout_message'] = 'Admin logged out successfully!';
} elseif ($userType === 'Member') {
    $_SESSION['logout_message'] = 'Member logged out successfully!';
} else {
    $_SESSION['logout_message'] = 'Logged out successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - BC Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logout-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .logout-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        .countdown {
            font-size: 1.2rem;
            color: #6c757d;
            margin-top: 20px;
        }
        .btn-group-custom {
            margin-top: 30px;
        }
        .btn-group-custom .btn {
            margin: 5px;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h2 class="mb-3">Logout Successful</h2>
        
        <?php if ($userType): ?>
            <p class="text-muted mb-4">
                <?= htmlspecialchars($userType) ?> session has been terminated successfully.
            </p>
        <?php else: ?>
            <p class="text-muted mb-4">
                You have been logged out successfully.
            </p>
        <?php endif; ?>
        
        <div class="alert alert-success">
            <i class="fas fa-shield-alt"></i>
            Your session has been securely ended.
        </div>
        
        <div class="countdown">
            <p>Redirecting in <span id="countdown">5</span> seconds...</p>
        </div>
        
        <div class="btn-group-custom">
            <?php if ($userType === 'Admin'): ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-user-shield"></i> Admin Login
                </a>
                <a href="member_login.php" class="btn btn-outline-secondary">
                    <i class="fas fa-user"></i> Member Login
                </a>
            <?php elseif ($userType === 'Member'): ?>
                <a href="member_login.php" class="btn btn-primary">
                    <i class="fas fa-user"></i> Member Login
                </a>
                <a href="login.php" class="btn btn-outline-secondary">
                    <i class="fas fa-user-shield"></i> Admin Login
                </a>
            <?php else: ?>
                <a href="../index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="login.php" class="btn btn-outline-primary">
                    <i class="fas fa-user-shield"></i> Admin Login
                </a>
                <a href="member_login.php" class="btn btn-outline-secondary">
                    <i class="fas fa-user"></i> Member Login
                </a>
            <?php endif; ?>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i>
                For security reasons, please close your browser if using a shared computer.
            </small>
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
                // Redirect to appropriate page
                window.location.href = '<?= $redirectPage ?>';
            }
        }, 1000);
        
        // Allow user to click anywhere to redirect immediately
        document.addEventListener('click', function(e) {
            // Don't redirect if clicking on a link
            if (e.target.tagName !== 'A' && !e.target.closest('a')) {
                clearInterval(timer);
                window.location.href = '<?= $redirectPage ?>';
            }
        });
        
        // Show a confirmation if user tries to go back
        window.addEventListener('beforeunload', function(e) {
            // Most browsers will show a generic message
            e.preventDefault();
            e.returnValue = '';
        });
    </script>
</body>
</html>
