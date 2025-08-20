<link href="assets/css/modern-design.css" rel="stylesheet">
<nav class="navbar navbar-expand-lg navbar-modern">
    <div class="container">
        <a class="navbar-brand-modern" href="index.php">
            <i class="fas fa-handshake"></i>
            <span><?= APP_NAME ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link-modern" href="index.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-layer-group me-1"></i>Groups
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="manage_groups.php">
                            <i class="fas fa-list me-2"></i>Manage Groups
                        </a></li>
                        <li><a class="dropdown-item" href="create_group_simple.php">
                            <i class="fas fa-plus me-2"></i>Create Group
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="create_group.php">
                            <i class="fas fa-cogs me-2"></i>Advanced Create
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-users me-1"></i>Members
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="members.php">
                            <i class="fas fa-list me-2"></i>All Members
                        </a></li>
                        <li><a class="dropdown-item" href="add_member.php">
                            <i class="fas fa-user-plus me-2"></i>Add Member
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="promptGroupId('manage_members.php')">
                            <i class="fas fa-users-cog me-2"></i>Manage Group Members
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-gavel me-1"></i>Bidding
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="bidding.php">
                            <i class="fas fa-cog me-2"></i>Manage Bidding
                        </a></li>
                        <li><a class="dropdown-item" href="manage_random_picks.php">
                            <i class="fas fa-dice me-2"></i>Random Picks
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-credit-card me-1"></i>Payments
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="payment_config.php">
                            <i class="fas fa-qrcode me-2"></i>QR Code Settings
                        </a></li>
                        <li><a class="dropdown-item" href="payment_status.php">
                            <i class="fas fa-list-check me-2"></i>Payment Status
                        </a></li>
                        <li><a class="dropdown-item" href="test_qr_image_setup.php">
                            <i class="fas fa-test-tube me-2"></i>Test QR Setup
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="test_qr_payment.php">
                            <i class="fas fa-vial me-2"></i>Test Payment System
                        </a></li>
                    </ul>
                </li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-1"></i>Admin
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="payment_config.php">
                            <i class="fas fa-qrcode me-2"></i>Payment Settings
                        </a></li>
                        <li><a class="dropdown-item" href="change_password.php">
                            <i class="fas fa-key me-2"></i>Change Password
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
function promptGroupId(page) {
    const groupId = prompt('Enter Group ID:');
    if (groupId && !isNaN(groupId) && groupId > 0) {
        window.location.href = page + '?group_id=' + groupId;
    } else if (groupId !== null) {
        alert('Please enter a valid Group ID number.');
    }
}
</script>
