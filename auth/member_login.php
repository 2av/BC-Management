<?php
require_once '../config/config.php';

if (isMemberLoggedIn()) {
    redirect('../member/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (memberLogin($username, $password)) {
            setMessage('Welcome back!');
            redirect('../member/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/login-common.css?v=<?= time() ?>" rel="stylesheet">
    <style>
        :root {
            /* Member Green Theme */
            --primary-color: #10b981;
            --primary-dark: #059669;
            --primary-rgb: 16, 185, 129;
            --primary-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
        }

        body {
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h1 class="login-title">Member Portal</h1>
                <p class="login-subtitle">Access your BC group dashboard</p>
            </div>

            <div class="login-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Username
                        </label>
                        <input type="text"
                               class="form-control"
                               id="username"
                               name="username"
                               placeholder="Enter your username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               required
                               autocomplete="username">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <input type="password"
                               class="form-control"
                               id="password"
                               name="password"
                               placeholder="Enter your password"
                               required
                               autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Access Dashboard
                    </button>
                </form>
            </div>

            <div class="login-footer">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <a href="login.php">
                        <i class="fas fa-user-shield me-1"></i>
                        Admin Login
                    </a>
                    <a href="super_admin_login.php">
                        <i class="fas fa-crown me-1"></i>
                        Super Admin
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>