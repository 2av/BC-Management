<!--  --><?php
// Get current member information
$current_member = getCurrentMember();
if (!$current_member) {
    redirect('../auth/member_login.php');
}

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = 'Member Portal';
}

// Get member's groups for navigation
$member_groups = getMemberGroups($current_member['id']);
$current_group_id = $_SESSION['group_id'] ?? null;
$current_group_name = $_SESSION['group_name'] ?? 'No Group';

// Get message if any
$message = getMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Professional Color System */
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);

            /* Modern Color Palette */
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --primary-800: #1e40af;
            --primary-900: #1e3a8a;

            /* Neutral Colors */
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;

            /* Professional Spacing */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;

            /* Modern Shadows */
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);

            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --radius-2xl: 1.5rem;
            --radius-full: 9999px;

            /* Transitions */
            --transition-fast: all 0.15s ease-in-out;
            --transition-normal: all 0.3s ease-in-out;
            --transition-slow: all 0.5s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            min-height: 100vh;
            color: var(--gray-800);
            line-height: 1.6;
            font-weight: 400;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Professional Navigation */
        .member-navbar {
            background: var(--primary-gradient);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-lg);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: var(--space-3) 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--space-3);
            transition: var(--transition-fast);
        }

        .navbar-brand:hover {
            color: rgba(255,255,255,0.9) !important;
            transform: scale(1.02);
        }

        .navbar-brand i {
            font-size: 1.75rem;
            background: rgba(255, 255, 255, 0.2);
            padding: var(--space-2);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(10px);
        }

        .navbar-nav {
            gap: var(--space-2);
        }

        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            font-size: 0.95rem;
            padding: var(--space-3) var(--space-5) !important;
            border-radius: var(--radius-lg);
            margin: 0;
            transition: var(--transition-fast);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-nav .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition-normal);
        }

        .navbar-nav .nav-link:hover::before {
            left: 100%;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.25);
            font-weight: 600;
        }

        .navbar-nav .nav-link i {
            margin-right: var(--space-2);
            font-size: 1.1rem;
        }

        /* Modern Dropdown Menus */
        .dropdown-menu {
            border: none;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            padding: var(--space-4);
            margin-top: var(--space-3);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-width: 220px;
        }

        .dropdown-item {
            border-radius: var(--radius-lg);
            padding: var(--space-3) var(--space-4);
            transition: var(--transition-fast);
            font-weight: 500;
            color: var(--gray-700);
            position: relative;
            overflow: hidden;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--primary-gradient);
            transition: var(--transition-normal);
            z-index: -1;
        }

        .dropdown-item:hover::before {
            left: 0;
        }

        .dropdown-item:hover {
            color: white;
            transform: translateX(var(--space-2));
            box-shadow: var(--shadow-md);
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: var(--space-3);
            font-size: 1.1rem;
        }

        .dropdown-divider {
            margin: var(--space-3) 0;
            border-color: var(--gray-200);
        }

        /* Professional Group Selector */
        .group-selector {
            background: rgba(255,255,255,0.15);
            border-radius: var(--radius-lg);
            padding: var(--space-2) var(--space-4);
            margin: 0 var(--space-4);
            color: white;
            font-size: 0.9rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .group-selector select {
            background: transparent;
            border: none;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .group-selector select option {
            background: var(--gray-800);
            color: white;
            padding: var(--space-2);
        }

        /* Enhanced User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: white;
            text-decoration: none;
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-lg);
            transition: var(--transition-fast);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .user-profile:hover {
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            transform: scale(1.02);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-md);
        }

        /* Professional Alert Messages */
        .alert {
            border: none;
            border-radius: var(--radius-xl);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-lg);
            padding: var(--space-5) var(--space-6);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
        }

        .alert-success {
            background: var(--success-gradient);
            color: white;
            border-left: 4px solid rgba(255, 255, 255, 0.8);
        }

        .alert-danger {
            background: var(--danger-gradient);
            color: white;
            border-left: 4px solid rgba(255, 255, 255, 0.8);
        }

        .alert-warning {
            background: var(--warning-gradient);
            color: var(--gray-800);
            border-left: 4px solid rgba(0, 0, 0, 0.2);
        }

        .alert-info {
            background: var(--primary-gradient);
            color: white;
            border-left: 4px solid rgba(255, 255, 255, 0.8);
        }

        .alert i {
            margin-right: var(--space-2);
            font-size: 1.2rem;
        }

        .btn-close-white {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .btn-close-white:hover {
            opacity: 1;
        }

        /* Enhanced Main Content */
        .main-content {
            padding: var(--space-8) 0;
            min-height: calc(100vh - 200px);
        }

        /* Professional Card Styles */
        .card {
            border: none;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition-normal);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .card-header {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: var(--space-5) var(--space-6);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .card-body {
            padding: var(--space-6);
        }

        /* Professional Buttons */
        .btn {
            border-radius: var(--radius-lg);
            font-weight: 600;
            padding: var(--space-3) var(--space-5);
            transition: var(--transition-fast);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition-normal);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary-gradient);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: var(--success-gradient);
            box-shadow: var(--shadow-md);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-warning {
            background: var(--warning-gradient);
            color: var(--gray-800);
            box-shadow: var(--shadow-md);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-danger {
            background: var(--danger-gradient);
            box-shadow: var(--shadow-md);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Professional Form Styles */
        .form-control, .form-select {
            border-radius: var(--radius-lg);
            border: 2px solid var(--gray-200);
            padding: var(--space-3) var(--space-4);
            transition: var(--transition-fast);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background: white;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: var(--space-2);
        }

        /* Professional Tables */
        .table {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .table th {
            background: var(--primary-gradient);
            color: white;
            font-weight: 600;
            border: none;
            padding: var(--space-4);
        }

        .table td {
            padding: var(--space-4);
            border-color: var(--gray-200);
            vertical-align: middle;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background: rgba(59, 130, 246, 0.05);
        }

        /* Professional Badges */
        .badge {
            border-radius: var(--radius-full);
            padding: var(--space-2) var(--space-3);
            font-weight: 600;
            font-size: 0.8rem;
        }

        /* Loading States */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Professional Responsive Design */
        @media (max-width: 1200px) {
            .container {
                padding: 0 var(--space-4);
            }
        }

        @media (max-width: 768px) {
            .member-navbar {
                padding: var(--space-2) 0;
            }

            .navbar-brand {
                font-size: 1.25rem;
            }

            .navbar-brand i {
                font-size: 1.5rem;
                padding: var(--space-1);
            }

            .navbar-toggler {
                border: none !important;
                padding: 0.25rem 0.5rem;
                font-size: 1.25rem;
                color: white !important;
            }

            .navbar-toggler:focus {
                box-shadow: none;
            }

            .navbar-collapse {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
            }

            .navbar-collapse.show {
                display: block !important;
            }

            .group-selector {
                margin: var(--space-3) 0;
                text-align: center;
                width: 100%;
            }

            .navbar-nav .nav-link {
                text-align: center;
                margin: var(--space-1) 0;
                padding: var(--space-3) var(--space-4) !important;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.1);
                margin-bottom: 0.5rem;
            }

            .navbar-nav .nav-link:hover {
                background: rgba(255, 255, 255, 0.2);
            }

            .user-profile {
                justify-content: center;
                padding: var(--space-3);
            }

            .user-avatar {
                width: 35px;
                height: 35px;
            }

            .main-content {
                padding: var(--space-4) 0;
            }

            .card-body {
                padding: var(--space-4);
            }

            .btn {
                padding: var(--space-3) var(--space-4);
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand span {
                display: none;
            }

            .dropdown-menu {
                min-width: 200px;
                margin-top: var(--space-2);
            }

            .alert {
                padding: var(--space-4);
                margin-bottom: var(--space-4);
            }

            .card {
                margin-bottom: var(--space-4);
            }
        }

        /* Dark mode support (optional) */
        @media (prefers-color-scheme: dark) {
            :root {
                --gray-50: #0f172a;
                --gray-100: #1e293b;
                --gray-200: #334155;
                --gray-800: #f1f5f9;
                --gray-900: #f8fafc;
            }
        }

        /* Print styles */
        @media print {
            .member-navbar,
            .dropdown-menu,
            .btn,
            .alert {
                display: none !important;
            }

            .main-content {
                padding: 0;
            }

            .card {
                box-shadow: none;
                border: 1px solid var(--gray-300);
            }
        }

        /* Page-specific styles can be added after this */
    </style>
</head>
<body>
    <!-- Member Navigation -->
    <nav class="navbar navbar-expand-lg member-navbar">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-friends me-2"></i>
                Member Portal
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#memberNavbar" 
                    style="color: white;">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="memberNavbar">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" 
                           href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'group_view.php' ? 'active' : '' ?>" 
                           href="group_view.php">
                            <i class="fas fa-users"></i>My Groups
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'bidding.php' ? 'active' : '' ?>" 
                           href="bidding.php">
                            <i class="fas fa-gavel"></i>Bidding
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'payment.php' ? 'active' : '' ?>" 
                           href="payment.php">
                            <i class="fas fa-credit-card"></i>Payments
                        </a>
                    </li>
                </ul>

                 

                <!-- User Profile Dropdown -->
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-profile" href="#" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar">
                                <?= strtoupper(substr($current_member['member_name'], 0, 1)) ?>
                            </div>
                            <span class="d-none d-md-inline"><?= htmlspecialchars($current_member['member_name']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="edit_profile.php">
                                    <i class="fas fa-user"></i>Edit Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="change_password.php">
                                    <i class="fas fa-key"></i>Change Password
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="../auth/member_login.php?logout=1">
                                    <i class="fas fa-sign-out-alt"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="container main-content">
        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $message['type'] === 'success' ? 'check-circle' : ($message['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
                <?= htmlspecialchars($message['message']) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Content Starts Here -->
