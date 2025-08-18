<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-coins"></i> <?= APP_NAME ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-users"></i> Groups
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="admin_manage_groups.php">
                            <i class="fas fa-list"></i> Manage Groups
                        </a></li>
                        <li><a class="dropdown-item" href="admin_create_group_simple.php">
                            <i class="fas fa-plus"></i> Create Group
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="create_group.php">
                            <i class="fas fa-cogs"></i> Advanced Create
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-friends"></i> Members
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="admin_members.php">
                            <i class="fas fa-list"></i> All Members
                        </a></li>
                        <li><a class="dropdown-item" href="admin_add_member.php">
                            <i class="fas fa-user-plus"></i> Add Member
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="promptGroupId('manage_members.php')">
                            <i class="fas fa-users-cog"></i> Manage Group Members
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-gavel"></i> Bidding
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="admin_bidding.php">
                            <i class="fas fa-cog"></i> Manage Bidding
                        </a></li>
                        <li><a class="dropdown-item" href="admin_manage_random_picks.php">
                            <i class="fas fa-dice"></i> Random Picks
                        </a></li>
                    </ul>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield"></i> Admin
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="admin_change_password.php">
                            <i class="fas fa-key"></i> Change Password
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
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
