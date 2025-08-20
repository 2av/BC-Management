<?php
require_once '../config/config.php';

// Check if user is already logged in and redirect appropriately
if (isset($_SESSION['super_admin_id'])) {
    redirect('../superadmin/dashboard.php');
} elseif (isset($_SESSION['client_admin_id']) || (isset($_SESSION['admin_id']) && isset($_SESSION['client_id']))) {
    redirect('../admin/index.php');
} elseif (isset($_SESSION['member_id'])) {
    redirect('../member/dashboard.php');
}

// Check if multi-tenant system is set up
$pdo = getDB();
$multiTenantEnabled = false;

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'super_admins'");
    $multiTenantEnabled = $stmt->fetch() !== false;
} catch (Exception $e) {
    // Multi-tenant tables don't exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Multi-Tenant BC Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .landing-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .landing-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 800px;
            width: 100%;
        }

        .landing-header {
            background: var(--primary-gradient);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
        }

        .landing-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .landing-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .landing-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .landing-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .landing-body {
            padding: 3rem 2rem;
        }

        .login-option {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .login-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
            color: inherit;
            text-decoration: none;
        }

        .login-option.super-admin {
            border-left: 5px solid #e74c3c;
        }

        .login-option.client {
            border-left: 5px solid #3498db;
        }

        .login-option.member {
            border-left: 5px solid #27ae60;
        }

        .option-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .option-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .option-description {
            color: #6c757d;
            margin: 0;
        }

        .migration-notice {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .migration-notice h4 {
            margin-bottom: 1rem;
        }

        .btn-migrate {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid white;
            color: white;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-migrate:hover {
            background: white;
            color: #f39c12;
        }

        .system-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }

        .animate-fadeIn {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container landing-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="landing-card animate-fadeIn">
                    <div class="landing-header">
                        <div class="landing-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h1 class="landing-title"><?= APP_NAME ?></h1>
                        <p class="landing-subtitle">Multi-Tenant Business Chit Management Platform</p>
                    </div>

                    <div class="landing-body">
                        <?php if (!$multiTenantEnabled): ?>
                            <div class="migration-notice">
                                <h4><i class="fas fa-rocket me-2"></i>Upgrade to Multi-Tenant System</h4>
                                <p class="mb-3">Your BC Management System can be upgraded to support multiple clients and organizations. This will enable you to manage multiple companies from a single platform.</p>
                                <a href="run_multi_tenant_migration.php" class="btn btn-migrate">
                                    <i class="fas fa-arrow-up me-2"></i>Upgrade Now
                                </a>
                            </div>
                            
                            <div class="text-center">
                                <h5 class="mb-3">Or continue with current system:</h5>
                                <a href="login.php" class="btn btn-primary me-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                                </a>
                                <a href="member_login.php" class="btn btn-success">
                                    <i class="fas fa-users me-2"></i>Member Login
                                </a>
                            </div>
                        <?php else: ?>
                            <h3 class="text-center mb-4">Choose Your Access Portal</h3>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <a href="super_admin_login.php" class="login-option super-admin">
                                        <div class="text-center">
                                            <div class="option-icon text-danger">
                                                <i class="fas fa-crown"></i>
                                            </div>
                                            <div class="option-title">Super Admin</div>
                                            <p class="option-description">Platform owner access to manage all clients and system settings</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-4">
                                    <a href="login.php" class="login-option client">
                                        <div class="text-center">
                                            <div class="option-icon text-primary">
                                                <i class="fas fa-user-shield"></i>
                                            </div>
                                            <div class="option-title">Admin Portal</div>
                                            <p class="option-description">Admin and client access to manage BC groups, members, and payments</p>
                                        </div>
                                    </a>
                                </div>
                                
                                <div class="col-md-4">
                                    <a href="member_login.php" class="login-option member">
                                        <div class="text-center">
                                            <div class="option-icon text-success">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <div class="option-title">Member Portal</div>
                                            <p class="option-description">Member access to view your BC group details and make payments</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="system-info">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-2"></i>
                                <?= APP_NAME ?> v2.0 - Multi-Tenant Business Chit Management System
                                <br>
                                Secure • Scalable • Multi-Organization Support
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
