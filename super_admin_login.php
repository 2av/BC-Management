<?php
require_once 'simple_mt_config.php';

if (isSuperAdminLoggedIn()) {
    redirect('super_admin_dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (superAdminLogin($username, $password)) {
            setMessage('Welcome back, Super Admin!');
            redirect('super_admin_dashboard.php');
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
    <title>Super Admin Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #e74c3c;
            --accent-color: #f39c12;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #34495e;
            --light-color: #ecf0f1;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 450px;
            width: 100%;
            margin: 0 auto;
        }

        .login-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .login-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            line-height: 1.2;
            word-wrap: break-word;
        }

        .login-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 1;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .super-admin-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }

        .login-form {
            padding: 2rem;
        }

        .form-floating-modern {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-floating-modern input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-floating-modern input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 2;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(44, 62, 80, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-left: 4px solid #c0392b;
        }

        .login-footer {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .login-footer a:hover {
            color: var(--secondary-color);
        }

        .animate-bounceIn {
            animation: bounceIn 0.8s ease-out;
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes fadeInUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .login-container {
                padding: 1rem;
            }

            .login-card {
                margin: 1rem;
                max-width: 100%;
            }

            .login-header {
                padding: 1.5rem;
            }

            .login-title {
                font-size: 1.5rem;
            }

            .login-form {
                padding: 0 1.5rem 1.5rem;
            }

            .login-footer {
                padding: 1rem 1.5rem 1.5rem;
            }
        }


    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-card animate-bounceIn">
                    <div class="login-header">
                        <div class="login-icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h1 class="login-title"><?= APP_NAME ?></h1>
                        <p class="login-subtitle">
                            <i class="fas fa-shield-alt me-2"></i>Super Admin Portal
                        </p>
                        <span class="super-admin-badge">Platform Owner Access</span>
                    </div>

                    <div class="login-form">
                        <?php if ($error): ?>
                            <div class="alert alert-modern">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="animate-fadeInUp">
                            <div class="form-floating-modern">
                                <input type="text" id="username" name="username"
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                       placeholder="Enter your username" required>
                                <i class="fas fa-user-crown input-icon"></i>
                            </div>

                            <div class="form-floating-modern">
                                <input type="password" id="password" name="password"
                                       placeholder="Enter your password" required>
                                <i class="fas fa-key input-icon"></i>
                            </div>

                            <button type="submit" class="login-btn">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Access Super Admin Portal
                            </button>
                        </form>
                    </div>

                    <div class="login-footer">
                        <div class="mb-2">
                            <small class="text-muted">Other Access Portals:</small>
                        </div>
                        <a href="login.php" class="me-3">
                            <i class="fas fa-user-shield me-1"></i>Admin Login
                        </a>
                        <a href="member_login.php">
                            <i class="fas fa-users me-1"></i>Member Login
                        </a>
                    </div>
                </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Focus animation for inputs
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });

                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Add loading state to login button
            const loginForm = document.querySelector('form');
            const loginBtn = document.querySelector('.login-btn');

            loginForm.addEventListener('submit', function() {
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
                loginBtn.disabled = true;
            });
        });
    </script>
</body>
</html>
