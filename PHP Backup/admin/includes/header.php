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

        /* Enhanced Notification Badge */
        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            border: 2px solid white;
            box-shadow: 0 3px 12px rgba(239, 68, 68, 0.5);
            animation: notificationPulse 2s infinite;
            z-index: 15;
            font-family: 'Arial', sans-serif;
            line-height: 1;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        @keyframes notificationPulse {
            0% {
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            }
            50% {
                transform: scale(1.15);
                box-shadow: 0 4px 16px rgba(239, 68, 68, 0.6);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
            }
        }

        /* Enhanced Notification Bell */
        .nav-link .fas.fa-bell {
            font-size: 1.4rem;
            color: #ffffff;
            transition: all 0.3s ease;
            font-weight: 900;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .nav-link:hover .fas.fa-bell {
            color: #fbbf24;
            transform: rotate(15deg) scale(1.1);
            text-shadow: 0 2px 8px rgba(251, 191, 36, 0.4);
        }

        .nav-link {
            padding: 0.75rem 1rem !important;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Notification Container Enhancement */
        #notificationDropdown {
            position: relative;
            display: flex !important;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }

        #notificationDropdown .fas.fa-bell {
            position: relative;
            z-index: 1;
        }

        /* Crystal Clear SVG Bell Icon */
        .notification-bell-svg {
            width: 22px;
            height: 22px;
            color: #ffffff;
            transition: all 0.3s ease;
            filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.3));
            position: relative;
            z-index: 1;
        }

        .nav-link:hover .notification-bell-svg {
            color: #fbbf24;
            transform: rotate(15deg) scale(1.1);
            filter: drop-shadow(0 2px 8px rgba(251, 191, 36, 0.4));
        }

        /* Fallback for Font Awesome if needed */
        .notification-bell-icon {
            font-size: 1.5rem;
            color: #ffffff;
            font-weight: bold;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }

        .notification-bell-icon::before {
            content: "🔔";
            font-style: normal;
            font-family: "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", sans-serif;
        }

        /* Enhanced Notification Dropdown */
        .dropdown-menu[aria-labelledby="notificationDropdown"] {
            border: none;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            padding: 0;
            margin-top: 12px;
            background: white;
            backdrop-filter: blur(10px);
            animation: notificationDropdownSlide 0.3s ease-out;
        }

        @keyframes notificationDropdownSlide {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Enhanced Dropdown Header */
        .dropdown-header {
            padding: 1.5rem 1.5rem 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px 16px 0 0;
            margin: 0;
            font-weight: 700;
            color: #1e293b;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }

        .dropdown-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 50%, #ef4444 100%);
            border-radius: 16px 16px 0 0;
        }

        /* Enhanced Notification Items */
        .dropdown-item {
            padding: 1rem 1.5rem;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            transition: width 0.3s ease;
        }

        .dropdown-item:hover::before {
            width: 4px;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
            transform: translateX(4px);
        }

        .notification-icon-wrapper {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .notification-icon-wrapper::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            transform: rotate(45deg);
            animation: iconShine 3s infinite;
        }

        @keyframes iconShine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        }

        .notification-icon-wrapper.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(217, 119, 6, 0.15) 100%);
            color: #f59e0b;
        }

        .notification-icon-wrapper.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.15) 100%);
            color: #10b981;
        }

        .notification-icon-wrapper.info {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.15) 0%, rgba(8, 145, 178, 0.15) 100%);
            color: #06b6d4;
        }

        .notification-icon-wrapper.danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.15) 100%);
            color: #ef4444;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
            line-height: 1.3;
        }

        .notification-subtitle {
            color: #64748b;
            font-size: 0.8rem;
            line-height: 1.3;
            margin: 0;
        }

        .notification-time {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 0.25rem;
        }

        /* Enhanced Divider */
        .dropdown-divider {
            margin: 0;
            border-color: #e2e8f0;
            opacity: 0.6;
        }

        /* Enhanced View All Button */
        .dropdown-item.text-center {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: #3b82f6;
            font-weight: 600;
            border-radius: 0 0 16px 16px;
            margin: 0;
            justify-content: center;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .dropdown-item.text-center:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            transform: none;
        }

        .dropdown-item.text-center:hover::before {
            width: 0;
        }

        /* Badge in header */
        .dropdown-header .badge {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.75rem;
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
                    <!-- Enhanced Notifications -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link position-relative d-flex align-items-center justify-content-center" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 48px; height: 48px;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="notification-bell-svg">
                                <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
                            </svg>
                            <span class="notification-badge">3</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown" style="min-width: 350px;">
                            <li>
                                <div class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">
                                        <i class="fas fa-bell me-2"></i>Notifications
                                    </span>
                                    <span class="badge">3</span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>

                            <!-- Enhanced Notification Items -->
                            <li><a class="dropdown-item" href="#">
                                <div class="notification-icon-wrapper warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">Pending Payments Alert</div>
                                    <div class="notification-subtitle">5 members have pending payments for this month</div>
                                    <div class="notification-time">2 hours ago</div>
                                </div>
                            </a></li>

                            <li><a class="dropdown-item" href="#">
                                <div class="notification-icon-wrapper success">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">New Member Added</div>
                                    <div class="notification-subtitle">John Doe has been successfully added to Group Alpha</div>
                                    <div class="notification-time">4 hours ago</div>
                                </div>
                            </a></li>

                            <li><a class="dropdown-item" href="#">
                                <div class="notification-icon-wrapper info">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">Bid Winner Announced</div>
                                    <div class="notification-subtitle">Month 3 bidding completed for Group Beta</div>
                                    <div class="notification-time">1 day ago</div>
                                </div>
                            </a></li>

                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="notifications.php">
                                <i class="fas fa-eye me-2"></i>View All Notifications
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
