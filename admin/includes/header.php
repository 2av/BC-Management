<?php
// Ensure this file is included only once
if (defined('HEADER_INCLUDED')) {
    return;
}
define('HEADER_INCLUDED', true);

// Required includes - adjust paths based on where this header is included from
if (!defined('CONFIG_INCLUDED')) {
    require_once '../config/config.php';
}
if (!defined('MIDDLEWARE_INCLUDED')) {
    require_once '../common/middleware.php';
}

// Check admin role
checkRole('admin');

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Get available languages for language switcher
$available_languages = getAvailableLanguages();

// Function to determine active nav item
function isActiveNav($page) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (is_array($page)) {
        return in_array($currentPage, $page) ? 'active' : '';
    }
    return $currentPage === $page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --accent-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Enhanced navbar */
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        /* Modern Professional Navbar Styling */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            border-bottom: none;
            padding: 0.75rem 0;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1060 !important;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            color: #fbbf24 !important;
        }

        .navbar-brand .fas {
            font-size: 1.75rem;
            animation: pulse-gentle 2s infinite;
        }

        @keyframes pulse-gentle {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.95) !important;
            font-weight: 600;
            padding: 0.75rem 1.25rem !important;
            margin: 0 0.25rem;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .navbar-nav .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .navbar-nav .nav-link:hover::before {
            left: 100%;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .navbar-nav .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
        }

        .navbar-text {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            margin: 0 0.75rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(5px);
        }

        /* Modern Dropdown Styling */
        .dropdown-menu {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 0.75rem 0;
            margin-top: 0.5rem;
            min-width: 200px;
            animation: dropdownFadeIn 0.3s ease-out;
            z-index: 1070 !important;
            position: absolute !important;
        }

        .dropdown-menu.show {
            display: block !important;
            z-index: 1070 !important;
        }

        /* Ensure dropdown parent has proper z-index */
        .nav-item.dropdown {
            position: relative;
            z-index: 1065 !important;
        }

        /* Ensure navbar dropdowns stay above all content */
        .navbar .dropdown-menu {
            z-index: 1070 !important;
        }

        /* Ensure main content doesn't interfere with navbar */
        .container {
            position: relative;
            z-index: 1 !important;
        }

        /* Prevent any content from overlapping navbar */
        body > .container,
        body > .container-fluid {
            z-index: 1 !important;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .dropdown-item {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: #374151;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateX(5px);
        }

        .dropdown-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            font-size: 0.9rem;
        }

        .dropdown-divider {
            margin: 0.5rem 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Language switcher styling */
        .language-flag {
            font-size: 1.2em;
            margin-right: 0.5rem;
        }

        .dropdown-toggle::after {
            margin-left: 0.5rem;
            transition: transform 0.3s ease;
        }

        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        /* Profile Avatar Styling */
        .avatar-sm {
            width: 40px;
            height: 40px;
        }

        .dropdown-item-text {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 10px;
            margin: 0.5rem;
        }

        .dropdown-item.text-danger:hover {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Notification Dropdown Styling */
        .dropdown-header {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 10px;
            margin: 0.5rem;
            font-weight: 600;
            color: #1e293b;
        }

        /* Mobile navbar improvements */
        .navbar-toggler {
            padding: 0.5rem;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.8%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Responsive navbar adjustments */
        @media (max-width: 991.98px) {
            .navbar {
                padding: 0.5rem 0;
            }

            .navbar-nav {
                padding-top: 1rem;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 15px;
                margin-top: 1rem;
                backdrop-filter: blur(10px);
            }

            .navbar-nav .nav-link {
                padding: 1rem 1.5rem;
                margin: 0.25rem 0.5rem;
                border-radius: 10px;
            }

            .navbar-brand {
                font-size: 1.3rem;
            }

            .dropdown-menu {
                background: rgba(255, 255, 255, 0.98);
                margin-top: 0.25rem;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 1.1rem;
            }

            .navbar-nav .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }

            .navbar-text {
                font-size: 0.85rem;
                padding: 0.5rem 0.75rem;
            }
        }

        /* Alert Styling */
        .alert-success-modern {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: white;
            border-radius: 15px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .alert-danger-modern {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border: none;
            color: white;
            border-radius: 15px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .alert-info-modern {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            color: white;
            border-radius: 15px;
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-handshake text-warning"></i>
                <span><?= APP_NAME ?></span>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    style="box-shadow: none; background: rgba(255,255,255,0.1); border-radius: 10px;">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= isActiveNav(['index.php', 'dashboard.php']) ?>" href="index.php">
                            <i class="fas fa-tachometer-alt me-2"></i><?= t('dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-users me-2"></i><?= t('members') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="members.php">
                                <i class="fas fa-list"></i><?= t('all_members') ?>
                            </a></li>
                            <li><a class="dropdown-item" href="add_member.php">
                                <i class="fas fa-user-plus"></i><?= t('add_member') ?>
                            </a></li>
                            
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-layer-group me-2"></i><?= t('groups') ?>
                        </a>
                        <ul class="dropdown-menu">
                           
                            <li><a class="dropdown-item" href="create_group_simple.php">
                                <i class="fas fa-plus"></i><?= t('create_group') ?>
                            </a></li>
                            <li><a class="dropdown-item" href="manage_groups.php">
                                <i class="fas fa-cogs"></i><?= t('manage_groups') ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="create_group.php">
                                <i class="fas fa-tools"></i><?= t('advanced_create') ?>
                            </a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="payment_status.php">
                                <i class="fas fa-credit-card"></i>Payment Status
                            </a></li>
                            <li><a class="dropdown-item" href="financial_report.php">
                                <i class="fas fa-chart-line"></i>Financial Reports
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="export_data.php">
                                <i class="fas fa-download"></i>Export Data
                            </a></li>
                        </ul>
                    </li>
                </ul>

                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Notifications -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="min-width: 300px;">
                            <li>
                                <div class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Notifications</span>
                                    <span class="badge bg-primary">3</span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-exclamation-triangle text-warning"></i>
                                <div class="ms-2">
                                    <div class="fw-bold">Pending Payments</div>
                                    <small class="text-muted">5 members have pending payments</small>
                                </div>
                            </a></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-user-plus text-success"></i>
                                <div class="ms-2">
                                    <div class="fw-bold">New Member Added</div>
                                    <small class="text-muted">John Doe joined Group A</small>
                                </div>
                            </a></li>
                            <li><a class="dropdown-item" href="#">
                                <i class="fas fa-trophy text-info"></i>
                                <div class="ms-2">
                                    <div class="fw-bold">Bid Winner</div>
                                    <small class="text-muted">Month 3 winner announced</small>
                                </div>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="notifications.php">
                                <small>View All Notifications</small>
                            </a></li>
                        </ul>
                    </li>

                    <!-- Language Switcher -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-globe me-2"></i>
                            <span class="language-flag"><?= $available_languages[getCurrentLanguage()]['flag'] ?></span>
                            <span class="d-none d-lg-inline"><?= $available_languages[getCurrentLanguage()]['name'] ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                            <?php foreach ($available_languages as $code => $language): ?>
                                <li>
                                    <a class="dropdown-item <?= getCurrentLanguage() === $code ? 'active' : '' ?>"
                                       href="?change_language=<?= $code ?>">
                                        <span class="language-flag"><?= $language['flag'] ?></span>
                                        <?= $language['name'] ?>
                                        <?php if (getCurrentLanguage() === $code): ?>
                                            <i class="fas fa-check ms-auto"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>

                    <!-- Admin Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-shield me-2"></i>
                            <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                            <li>
                                <div class="dropdown-item-text">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-user-shield text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
                                            <small class="text-muted">Administrator</small>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user-cog"></i>Profile Settings
                            </a></li>
                            <li><a class="dropdown-item" href="change_password.php">
                                <i class="fas fa-key"></i><?= t('change_password') ?>
                            </a></li>
                            <li><a class="dropdown-item" href="system_settings.php">
                                <i class="fas fa-cogs"></i>System Settings
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="?logout=1">
                                <i class="fas fa-sign-out-alt"></i><?= t('logout') ?>
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php $msg = getMessage(); ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] === 'success' ? 'success-modern' : ($msg['type'] === 'error' ? 'danger-modern' : 'info-modern') ?>">
                <i class="fas fa-<?= $msg['type'] === 'success' ? 'check-circle' : ($msg['type'] === 'error' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
