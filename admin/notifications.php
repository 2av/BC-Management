<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('admin');

$pdo = getDB();

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $notificationId = (int)$_POST['notification_id'];
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
        $stmt->execute([$notificationId]);
    } elseif (isset($_POST['mark_all_read'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_type = 'admin' AND is_read = 0");
        $stmt->execute();
    } elseif (isset($_POST['delete_notification'])) {
        $notificationId = (int)$_POST['notification_id'];
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$notificationId]);
    }
}

// Create notifications table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_type ENUM('admin', 'member') NOT NULL,
        user_id INT,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        read_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_type_read (user_type, is_read),
        INDEX idx_created_at (created_at)
    )
");

// Generate sample notifications if table is empty
$stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE user_type = 'admin'");
if ($stmt->fetch()['count'] == 0) {
    $sampleNotifications = [
        ['Payment Reminder', 'Monthly payments for Group Alpha are due in 3 days.', 'warning'],
        ['New Member Added', 'John Doe has been added to Group Beta.', 'success'],
        ['Bidding Completed', 'Month 5 bidding for Group Gamma has been completed.', 'info'],
        ['System Update', 'BC Management System has been updated to version 2.1.', 'info'],
        ['Low Collection Alert', 'Group Delta has only 60% payment collection this month.', 'danger'],
        ['Group Completed', 'Group Epsilon has successfully completed all 18 months.', 'success']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_type, title, message, type) VALUES ('admin', ?, ?, ?)");
    foreach ($sampleNotifications as $notification) {
        $stmt->execute($notification);
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query based on filter
$whereClause = "WHERE user_type = 'admin'";
switch ($filter) {
    case 'unread':
        $whereClause .= " AND is_read = 0";
        break;
    case 'read':
        $whereClause .= " AND is_read = 1";
        break;
    case 'warning':
        $whereClause .= " AND type = 'warning'";
        break;
    case 'danger':
        $whereClause .= " AND type = 'danger'";
        break;
}

// Get notifications with pagination
$stmt = $pdo->query("
    SELECT * FROM notifications 
    $whereClause 
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$notifications = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications $whereClause");
$totalNotifications = $stmt->fetch()['total'];
$totalPages = ceil($totalNotifications / $perPage);

// Get notification counts for filter badges
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN type = 'warning' THEN 1 ELSE 0 END) as warnings,
        SUM(CASE WHEN type = 'danger' THEN 1 ELSE 0 END) as alerts
    FROM notifications 
    WHERE user_type = 'admin'
");
$counts = $stmt->fetch();

// Set page title for the header
$page_title = 'Notifications';

// Include the new header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
    .notification-card {
        background: white;
        border-radius: 10px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #dee2e6;
        transition: all 0.3s ease;
    }
    
    .notification-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    
    .notification-card.unread {
        background: #f8f9fa;
        border-left-color: #007bff;
    }
    
    .notification-card.type-success {
        border-left-color: #28a745;
    }
    
    .notification-card.type-warning {
        border-left-color: #ffc107;
    }
    
    .notification-card.type-danger {
        border-left-color: #dc3545;
    }
    
    .notification-card.type-info {
        border-left-color: #17a2b8;
    }
    
    .notification-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .notification-title {
        font-weight: 600;
        margin: 0;
        flex-grow: 1;
    }
    
    .notification-time {
        font-size: 0.875rem;
        color: #6c757d;
        margin-left: 1rem;
    }
    
    .notification-actions {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #dee2e6;
    }
    
    .filter-tabs {
        background: white;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .filter-tab {
        display: inline-block;
        padding: 0.5rem 1rem;
        margin-right: 0.5rem;
        border-radius: 20px;
        text-decoration: none;
        color: #6c757d;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }
    
    .filter-tab.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .filter-tab:hover {
        color: white;
        background: #6c757d;
    }
    
    .filter-tab.active:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .notification-icon {
        font-size: 1.2rem;
        margin-right: 0.5rem;
    }
    
    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
</style>

<!-- Page content starts here -->
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-bell text-primary me-2"></i>Notifications</h1>
        <div>
            <?php if ($counts['unread'] > 0): ?>
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                        <i class="fas fa-check-double me-1"></i> Mark All Read
                    </button>
                </form>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline-secondary ms-2">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            <i class="fas fa-list notification-icon"></i>All 
            <span class="badge bg-secondary"><?= $counts['total'] ?></span>
        </a>
        <a href="?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
            <i class="fas fa-envelope notification-icon"></i>Unread 
            <span class="badge bg-primary"><?= $counts['unread'] ?></span>
        </a>
        <a href="?filter=read" class="filter-tab <?= $filter === 'read' ? 'active' : '' ?>">
            <i class="fas fa-envelope-open notification-icon"></i>Read
        </a>
        <a href="?filter=warning" class="filter-tab <?= $filter === 'warning' ? 'active' : '' ?>">
            <i class="fas fa-exclamation-triangle notification-icon"></i>Warnings 
            <span class="badge bg-warning"><?= $counts['warnings'] ?></span>
        </a>
        <a href="?filter=danger" class="filter-tab <?= $filter === 'danger' ? 'active' : '' ?>">
            <i class="fas fa-exclamation-circle notification-icon"></i>Alerts 
            <span class="badge bg-danger"><?= $counts['alerts'] ?></span>
        </a>
    </div>

    <!-- Notifications List -->
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h4>No Notifications</h4>
            <p>You don't have any notifications matching the selected filter.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
            <div class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?> type-<?= $notification['type'] ?>">
                <div class="notification-header">
                    <h5 class="notification-title">
                        <?php
                        $icons = [
                            'info' => 'fas fa-info-circle text-info',
                            'success' => 'fas fa-check-circle text-success',
                            'warning' => 'fas fa-exclamation-triangle text-warning',
                            'danger' => 'fas fa-exclamation-circle text-danger'
                        ];
                        ?>
                        <i class="<?= $icons[$notification['type']] ?> me-2"></i>
                        <?= htmlspecialchars($notification['title']) ?>
                        <?php if (!$notification['is_read']): ?>
                            <span class="badge bg-primary ms-2">New</span>
                        <?php endif; ?>
                    </h5>
                    <span class="notification-time">
                        <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                    </span>
                </div>
                
                <p class="mb-0"><?= htmlspecialchars($notification['message']) ?></p>
                
                <div class="notification-actions">
                    <?php if (!$notification['is_read']): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                            <button type="submit" name="mark_read" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-check me-1"></i> Mark as Read
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                        <button type="submit" name="delete_notification" class="btn btn-sm btn-outline-danger ms-2" 
                                onclick="return confirm('Are you sure you want to delete this notification?')">
                            <i class="fas fa-trash me-1"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Notifications pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
