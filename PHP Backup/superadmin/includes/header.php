<?php
// Ensure this file is included only once
if (defined('SUPER_ADMIN_HEADER_INCLUDED')) {
    return;
}
define('SUPER_ADMIN_HEADER_INCLUDED', true);

// Required includes - adjust paths based on where this header is included from
if (!defined('CONFIG_INCLUDED')) {
    require_once '../config/config.php';
}
if (!defined('MIDDLEWARE_INCLUDED')) {
    require_once '../common/middleware.php';
}

// Check super admin role
checkRole('superadmin');

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Function to determine active nav item
function isActiveSuperAdminNav($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (is_array($page)) {
        return in_array($currentPage, $page) ? 'active' : '';
    }
    return $currentPage === $page ? 'active' : '';
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get super admin info
$superAdminName = $_SESSION['super_admin_name'] ?? 'Super Administrator';
$superAdminEmail = $_SESSION['super_admin_email'] ?? '';

// Get notification count (placeholder - implement as needed)
$notificationCount = 0;

// Get quick stats for header display
$pdo = getDB();
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE status = 'active'");
    $activeClients = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM client_payments WHERE status = 'pending'");
    $pendingPayments = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $activeClients = 0;
    $pendingPayments = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' . APP_NAME . ' Super Admin' : APP_NAME . ' - Super Admin' ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Modern Horizontal Menu Header */
        .super-admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 0;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 60px;
        }

        .navbar-brand-super {
            font-size: 1.3rem;
            font-weight: 700;
            color: white !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 0.8rem 0;
        }

        .navbar-brand-super:hover {
            color: white !important;
        }

        .navbar-brand-super .crown-icon {
            color: #ffd700;
            margin-right: 0.5rem;
            font-size: 1.4rem;
        }

        .nav-link-super {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
            padding: 0.6rem 1rem !important;
            position: relative;
            white-space: nowrap;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }

        .nav-link-super:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-link-super.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
            font-weight: 600;
        }

        .nav-link-super i {
            margin-right: 0.5rem;
            font-size: 0.9rem;
        }

        /* Professional Notification Badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4); }
            50% { transform: scale(1.1); box-shadow: 0 4px 12px rgba(255, 107, 107, 0.6); }
            100% { transform: scale(1); box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4); }
        }

        /* Professional Badge Variants */
        .badge-warning-professional {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #1e3c72;
            font-weight: 700;
            border-radius: 12px;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }

        /* Header Right Section */
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-info {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-info i {
            color: rgba(255, 255, 255, 0.6);
        }

        /* Modern Dropdown Styles */
        .dropdown-menu-super {
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 0.5rem 0;
            margin-top: 0.2rem;
            min-width: 200px;
        }

        .dropdown-item-super {
            color: #333;
            padding: 0.6rem 1.2rem;
            transition: all 0.2s ease;
            border: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .dropdown-item-super:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .dropdown-item-super i {
            width: 18px;
            margin-right: 0.6rem;
            color: #667eea;
        }

        /* User Profile Section */
        .user-dropdown {
            position: relative;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.6rem;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .user-avatar:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .user-info {
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 400;
        }

        .user-dropdown-toggle {
            background: rgba(255, 255, 255, 0.1) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 6px !important;
            color: white !important;
            padding: 0.4rem 0.8rem !important;
            transition: all 0.3s ease;
        }

        .user-dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.3) !important;
            color: white !important;
        }

        /* Mobile Responsive */
        @media (max-width: 992px) {
            .navbar-nav-super {
                background: rgba(30, 60, 114, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 15px;
                margin: 1rem 0;
                padding: 1rem;
                border: 2px solid rgba(255, 215, 0, 0.3);
            }

            .nav-link-super {
                margin: 0.3rem 0;
                text-align: center;
            }

            .header-stats {
                display: none;
            }

            .navbar-brand-super {
                font-size: 1.2rem;
            }

            .user-dropdown .d-none.d-md-block {
                display: none !important;
            }
        }

        @media (max-width: 576px) {
            .super-admin-header {
                padding: 0.5rem 1rem;
            }

            .navbar-brand-super {
                font-size: 1.1rem;
            }

            .navbar-brand-super .crown-icon {
                font-size: 1.3rem;
                margin-right: 0.5rem;
            }
        }

        /* Navbar Toggle Button */
        .navbar-toggler-super {
            border: 2px solid #ffd700;
            border-radius: 8px;
            padding: 0.5rem;
            background: rgba(255, 215, 0, 0.1);
            transition: all 0.3s ease;
        }

        .navbar-toggler-super:hover {
            background: rgba(255, 215, 0, 0.2);
            border-color: #ffed4e;
        }

        .navbar-toggler-super .fa-bars {
            color: #ffd700;
            font-size: 1.2rem;
        }

        /* Content Container */
        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            margin: 0 1rem 2rem 1rem;
            padding: 2.5rem;
            min-height: calc(100vh - 200px);
        }

        /* Loading Animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Modern Horizontal Menu Header -->
    <header class="super-admin-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid px-3">
                <!-- Brand -->
                <a class="navbar-brand-super" href="super_admin_dashboard.php">
                    <i class="fas fa-crown crown-icon"></i>
                    <span><?= APP_NAME ?></span>
                </a>

                <!-- Mobile Toggle -->
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#superAdminNav" style="color: white;">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="collapse navbar-collapse" id="superAdminNav">
                    <!-- Horizontal Navigation Menu -->
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link-super <?= isActiveSuperAdminNav('super_admin_dashboard.php') ?>" href="super_admin_dashboard.php">
                                <i class="fas fa-chart-pie"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link-super dropdown-toggle <?= isActiveSuperAdminNav(['super_admin_clients.php']) ?>" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-users"></i>Members
                            </a>
                            <ul class="dropdown-menu dropdown-menu-super">
                                <li><a class="dropdown-item-super" href="super_admin_clients.php">
                                    <i class="fas fa-building"></i>Clients
                                </a></li>
                                <li><a class="dropdown-item-super" href="super_admin_users.php">
                                    <i class="fas fa-user"></i>Users
                                </a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link-super dropdown-toggle <?= isActiveSuperAdminNav(['super_admin_subscription_plans.php', 'super_admin_subscriptions.php']) ?>" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-layer-group"></i>Groups
                            </a>
                            <ul class="dropdown-menu dropdown-menu-super">
                                <li><a class="dropdown-item-super" href="super_admin_subscription_plans.php">
                                    <i class="fas fa-tags"></i>Plans
                                </a></li>
                                <li><a class="dropdown-item-super" href="super_admin_subscriptions.php">
                                    <i class="fas fa-calendar-check"></i>Subscriptions
                                </a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link-super dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-file-alt"></i>Reports
                            </a>
                            <ul class="dropdown-menu dropdown-menu-super">
                                <li><a class="dropdown-item-super" href="super_admin_payments.php">
                                    <i class="fas fa-money-bill-wave"></i>Payments
                                    <?php if ($pendingPayments > 0): ?>
                                        <span class="badge bg-danger ms-2"><?= $pendingPayments ?></span>
                                    <?php endif; ?>
                                </a></li>
                                <li><a class="dropdown-item-super" href="super_admin_analytics.php">
                                    <i class="fas fa-chart-bar"></i>Analytics
                                </a></li>
                            </ul>
                        </li>
                    </ul>

                    <!-- Right Side Menu -->
                    <div class="header-right d-none d-lg-flex">
                        <!-- Language Selector -->
                        <div class="dropdown">
                            <a class="nav-link-super dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-globe"></i>US English
                            </a>
                            <ul class="dropdown-menu dropdown-menu-super">
                                <li><a class="dropdown-item-super" href="#" onclick="changeLanguage('en')">
                                    <i class="fas fa-flag-usa"></i>English
                                </a></li>
                                <li><a class="dropdown-item-super" href="#" onclick="changeLanguage('hi')">
                                    <i class="fas fa-flag"></i>हिंदी
                                </a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="user-dropdown ms-2">
                        <div class="dropdown">
                            <a class="d-flex align-items-center text-decoration-none dropdown-toggle user-dropdown-toggle" href="#"
                               role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar">
                                    <?= strtoupper(substr($superAdminName, 0, 1)) ?>
                                </div>
                                <div class="d-none d-md-block user-info">
                                    <div><?= htmlspecialchars($superAdminName) ?></div>
                                    <div class="user-role">Admin</div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-super">
                                <li><a class="dropdown-item-super" href="super_admin_profile.php">
                                    <i class="fas fa-user"></i>Profile
                                </a></li>
                                <li><a class="dropdown-item-super" href="super_admin_settings.php">
                                    <i class="fas fa-cog"></i>Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item-super" href="logout.php" onclick="showLoading()">
                                    <i class="fas fa-sign-out-alt"></i>Logout
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content Container -->
    <div class="main-content">
        <?php
        // Display any messages
        $message = getMessage();
        if ($message):
        ?>
            <div class="alert alert-<?= $message['type'] === 'success' ? 'success' : ($message['type'] === 'error' ? 'danger' : 'info') ?> alert-dismissible fade show"
                 style="border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <i class="fas fa-<?= $message['type'] === 'success' ? 'check-circle' : ($message['type'] === 'error' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($message['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

    <script>
        // Loading overlay functions
        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }

        // Auto-hide loading on page load
        window.addEventListener('load', function() {
            hideLoading();
        });

        // Show loading on navigation
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.nav-link-super, .dropdown-item-super');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.href && !this.href.includes('#') && !this.href.includes('javascript:')) {
                        showLoading();
                    }
                });
            });
        });

        // Enhanced dropdown behavior
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.dropdown-toggle');
            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('show.bs.dropdown', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                dropdown.addEventListener('hide.bs.dropdown', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });

        // Notification system (placeholder for future implementation)
        function showNotification(message, type = 'info') {
            // Implementation for showing notifications
            console.log(`${type}: ${message}`);
        }

        // Auto-refresh stats (optional)
        function refreshHeaderStats() {
            // Implementation for refreshing header statistics
            // This could make an AJAX call to get updated counts
        }

        // Refresh stats every 5 minutes
        setInterval(refreshHeaderStats, 300000);
    </script>
