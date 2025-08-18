<?php
require_once 'config.php';

if (isAdminLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (adminLogin($username, $password)) {
            setMessage('Welcome back!');
            redirect('index.php');
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
    <title>Admin Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-design.css?v=<?= time() ?>" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .login-container {
            position: relative;
            z-index: 10;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-2xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all var(--transition-normal);
            overflow: hidden;
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem 2rem 0;
        }

        .login-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse-gentle 2s infinite;
            box-shadow: var(--shadow-lg);
        }

        .login-icon i {
            font-size: 2rem;
            color: white;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 500;
        }

        .login-form {
            padding: 0 2rem 2rem;
        }

        .form-floating-modern {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-floating-modern input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            background-color: white;
            transition: all var(--transition-normal);
            outline: none;
        }

        .form-floating-modern input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }

        .form-floating-modern .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            transition: color var(--transition-normal);
        }

        .form-floating-modern input:focus + .input-icon {
            color: var(--primary-color);
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left var(--transition-slow);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .login-footer {
            text-align: center;
            padding: 1rem 2rem 2rem;
            border-top: 1px solid var(--gray-200);
            margin-top: 1rem;
        }

        .member-login-link {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition-normal);
        }

        .member-login-link:hover {
            color: var(--secondary-dark);
        }

        .alert-modern-custom {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .login-card {
                margin: 1rem;
            }

            .login-header {
                padding: 1.5rem 1.5rem 0;
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
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="login-card animate-bounceIn">
                    <div class="login-header">
                        <div class="login-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h1 class="login-title"><?= APP_NAME ?></h1>
                        <p class="login-subtitle">
                            <i class="fas fa-handshake me-2"></i>Admin Portal Access
                        </p>
                    </div>

                    <div class="login-form">
                        <?php if ($error): ?>
                            <div class="alert-modern-custom">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="animate-fadeInUp">
                            <div class="form-floating-modern">
                                <input type="text" id="username" name="username"
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                       placeholder="Enter your username" required>
                                <i class="fas fa-user input-icon"></i>
                            </div>

                            <div class="form-floating-modern">
                                <input type="password" id="password" name="password"
                                       placeholder="Enter your password" required>
                                <i class="fas fa-lock input-icon"></i>
                            </div>

                            <button type="submit" class="login-btn">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Access Admin Portal
                            </button>
                        </form>
                    </div>

                    <div class="login-footer">
                        <p class="mb-0">
                            <i class="fas fa-users me-2 text-muted"></i>
                            <span class="text-muted">Are you a member?</span>
                            <a href="member_login.php" class="member-login-link ms-1">
                                Login Here
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
