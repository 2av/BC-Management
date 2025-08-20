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
    <style>
        :root {
            /* Color System */
            --primary-color: #667eea;
            --primary-dark: #5a67d8;
            --secondary-color: #10b981;
            --secondary-dark: #059669;
            --accent-color: #f59e0b;
            --danger-color: #ef4444;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;

            /* Gradients */
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --member-gradient: linear-gradient(135deg, #667eea 0%, #10b981 100%);

            /* Gray Scale */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;

            /* Spacing */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            --space-2xl: 3rem;

            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --radius-full: 9999px;

            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);

            /* Transitions */
            --transition-fast: 0.15s ease-in-out;
            --transition-normal: 0.3s ease-in-out;
            --transition-slow: 0.5s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--member-gradient);
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
            background: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 80px 80px;
            animation: float-pattern 30s ease-in-out infinite;
            z-index: 1;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(ellipse at top, rgba(255,255,255,0.1) 0%, transparent 70%);
            z-index: 2;
        }

        @keyframes float-pattern {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(120deg); }
            66% { transform: translateY(10px) rotate(240deg); }
        }

        @keyframes pulse-gentle {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes bounce-in {
            0% { transform: scale(0.3) translateY(-50px); opacity: 0; }
            50% { transform: scale(1.05) translateY(-10px); }
            70% { transform: scale(0.9) translateY(0px); }
            100% { transform: scale(1) translateY(0px); opacity: 1; }
        }

        @keyframes fade-in-up {
            0% { transform: translateY(30px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
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
            animation: bounce-in 0.8s ease-out;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--member-gradient);
        }

        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2.5rem 2rem 0;
        }

        .login-icon {
            width: 90px;
            height: 90px;
            background: var(--member-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse-gentle 2s infinite;
            box-shadow: var(--shadow-xl);
            position: relative;
        }

        .login-icon::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            background: var(--member-gradient);
            border-radius: 50%;
            opacity: 0.3;
            animation: pulse-gentle 2s infinite reverse;
        }

        .login-icon i {
            font-size: 2.5rem;
            color: white;
            position: relative;
            z-index: 2;
        }

        .login-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            line-height: 1.2;
            background: linear-gradient(135deg, var(--gray-900) 0%, var(--gray-700) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
            font-weight: 500;
            line-height: 1.4;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .login-form {
            padding: 0 2rem 2rem;
            animation: fade-in-up 0.8s ease-out 0.2s both;
        }

        .form-floating-modern {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-floating-modern input {
            width: 100%;
            padding: 1.2rem 1rem 1.2rem 3.5rem;
            font-size: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-lg);
            background-color: white;
            transition: all var(--transition-normal);
            outline: none;
            font-weight: 500;
        }

        .form-floating-modern input:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            transform: translateY(-2px);
        }

        .form-floating-modern .input-icon {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            transition: all var(--transition-normal);
            font-size: 1.1rem;
        }

        .form-floating-modern input:focus + .input-icon {
            color: var(--secondary-color);
            transform: translateY(-50%) scale(1.1);
        }

        .login-btn {
            width: 100%;
            padding: 1.2rem;
            font-size: 1.1rem;
            font-weight: 600;
            background: var(--member-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
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
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }

        .login-btn:active {
            transform: translateY(-1px);
        }

        .login-footer {
            text-align: center;
            padding: 1.5rem 2rem 2rem;
            border-top: 1px solid var(--gray-200);
            margin-top: 1rem;
            animation: fade-in-up 0.8s ease-out 0.4s both;
        }

        .portal-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .portal-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-normal);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .portal-link:hover {
            color: var(--primary-dark);
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .alert-modern {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger-color);
            padding: 1rem 1.2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: shake 0.5s ease-in-out;
            font-weight: 500;
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
                padding: 2rem 1.5rem 0;
            }

            .login-form {
                padding: 0 1.5rem 1.5rem;
            }

            .login-footer {
                padding: 1rem 1.5rem 1.5rem;
            }

            .login-title {
                font-size: 1.8rem;
            }

            .portal-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .login-icon {
                width: 70px;
                height: 70px;
            }

            .login-icon i {
                font-size: 2rem;
            }

            .login-title {
                font-size: 1.6rem;
            }

            .login-subtitle {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="login-card">
                    <div class="login-header">
                        <div class="login-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h1 class="login-title"><?= APP_NAME ?></h1>
                        <p class="login-subtitle">
                            <i class="fas fa-users"></i>
                            Member Portal Access
                        </p>
                    </div>

                    <div class="login-form">
                        <?php if ($error): ?>
                            <div class="alert-modern">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
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
                                Access Member Portal
                            </button>
                        </form>
                    </div>

                    <div class="login-footer">
                        <div class="mb-3">
                            <small class="text-muted">Other Access Portals:</small>
                        </div>
                        <div class="portal-links">
                            <a href="login.php" class="portal-link">
                                <i class="fas fa-user-shield"></i>
                                Admin Login
                            </a>
                            <a href="super_admin_login.php" class="portal-link">
                                <i class="fas fa-crown"></i>
                                Super Admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced focus animations for inputs
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                    this.parentElement.style.transition = 'transform 0.3s ease';
                });

                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });

                // Add typing animation
                input.addEventListener('input', function() {
                    const icon = this.nextElementSibling;
                    if (icon) {
                        icon.style.transform = 'translateY(-50%) scale(1.1)';
                        setTimeout(() => {
                            icon.style.transform = 'translateY(-50%) scale(1)';
                        }, 150);
                    }
                });
            });

            // Enhanced login button with loading state
            const loginForm = document.querySelector('form');
            const loginBtn = document.querySelector('.login-btn');
            const originalBtnText = loginBtn.innerHTML;

            loginForm.addEventListener('submit', function(e) {
                // Add loading state
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';
                loginBtn.disabled = true;
                loginBtn.style.background = 'linear-gradient(135deg, #9ca3af 0%, #6b7280 100%)';

                // Add subtle shake animation to the card
                const loginCard = document.querySelector('.login-card');
                loginCard.style.animation = 'none';
                setTimeout(() => {
                    loginCard.style.animation = 'bounce-in 0.3s ease-out';
                }, 10);
            });

            // Add hover effects to portal links
            const portalLinks = document.querySelectorAll('.portal-link');
            portalLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.05)';
                });

                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add particle effect on successful login (if no errors)
            <?php if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            createSuccessParticles();
            <?php endif; ?>
        });

        // Success particle effect
        function createSuccessParticles() {
            const colors = ['#10b981', '#667eea', '#f59e0b'];
            for (let i = 0; i < 20; i++) {
                setTimeout(() => {
                    const particle = document.createElement('div');
                    particle.style.cssText = `
                        position: fixed;
                        width: 6px;
                        height: 6px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        border-radius: 50%;
                        pointer-events: none;
                        z-index: 9999;
                        left: ${Math.random() * window.innerWidth}px;
                        top: ${Math.random() * window.innerHeight}px;
                        animation: particle-float 2s ease-out forwards;
                    `;
                    document.body.appendChild(particle);

                    setTimeout(() => particle.remove(), 2000);
                }, i * 100);
            }
        }

        // Add particle animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes particle-float {
                0% { transform: translateY(0) scale(1); opacity: 1; }
                100% { transform: translateY(-100px) scale(0); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
