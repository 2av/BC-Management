<?php
// Super Admin Navigation Bar
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
.sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 0;
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
}

.sidebar .nav-link {
    color: rgba(255,255,255,0.8);
    padding: 15px 20px;
    border-radius: 0;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    color: white;
    background: rgba(255,255,255,0.1);
    border-left-color: #ffc107;
    transform: translateX(5px);
}

.sidebar .nav-link i {
    width: 20px;
    margin-right: 10px;
}

.sidebar-header {
    padding: 20px;
    background: rgba(0,0,0,0.1);
    color: white;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .main-content {
        margin-left: 0;
    }
}
</style>

<div class="sidebar-header">
    <h4><i class="fas fa-crown me-2"></i>Super Admin</h4>
    <small><?= $_SESSION['super_admin_name'] ?? 'Administrator' ?></small>
</div>

<nav class="nav flex-column">
    <a class="nav-link <?= $currentPage == 'super_admin_dashboard.php' ? 'active' : '' ?>" 
       href="super_admin_dashboard.php">
        <i class="fas fa-tachometer-alt"></i>Dashboard
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_clients.php' ? 'active' : '' ?>" 
       href="super_admin_clients.php">
        <i class="fas fa-building"></i>Clients
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_add_client.php' ? 'active' : '' ?>" 
       href="super_admin_add_client.php">
        <i class="fas fa-plus-circle"></i>Add Client
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_subscription_plans.php' ? 'active' : '' ?>" 
       href="super_admin_subscription_plans.php">
        <i class="fas fa-credit-card"></i>Subscription Plans
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_subscriptions.php' ? 'active' : '' ?>" 
       href="super_admin_subscriptions.php">
        <i class="fas fa-calendar-check"></i>Client Subscriptions
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_payments.php' ? 'active' : '' ?>" 
       href="super_admin_payments.php">
        <i class="fas fa-money-bill-wave"></i>Payments
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_notifications.php' ? 'active' : '' ?>" 
       href="super_admin_notifications.php">
        <i class="fas fa-bell"></i>Notifications
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_reports.php' ? 'active' : '' ?>" 
       href="super_admin_reports.php">
        <i class="fas fa-chart-bar"></i>Reports
    </a>
    
    <a class="nav-link <?= $currentPage == 'super_admin_settings.php' ? 'active' : '' ?>" 
       href="super_admin_settings.php">
        <i class="fas fa-cog"></i>Settings
    </a>
    
    <hr style="border-color: rgba(255,255,255,0.2); margin: 20px;">
    
    <a class="nav-link" href="logout.php">
        <i class="fas fa-sign-out-alt"></i>Logout
    </a>
</nav>
