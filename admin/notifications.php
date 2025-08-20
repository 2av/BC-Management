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

<!-- Enhanced Page-specific CSS -->
<style>
    :root {
        --primary-color: #3b82f6;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #06b6d4;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-600: #4b5563;
        --gray-900: #111827;
        --radius-lg: 12px;
        --radius-xl: 16px;
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        min-height: 100vh;
    }

    .notification-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: var(--radius-xl);
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-200);
        border-left: 5px solid #dee2e6;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .notification-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(135deg, transparent 0%, rgba(59, 130, 246, 0.3) 50%, transparent 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .notification-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
    }

    .notification-card:hover::before {
        opacity: 1;
    }

    .notification-card.unread {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border-left-color: var(--primary-color);
        box-shadow: var(--shadow-lg);
    }

    .notification-card.type-success {
        border-left-color: var(--success-color);
    }

    .notification-card.type-warning {
        border-left-color: var(--warning-color);
    }

    .notification-card.type-danger {
        border-left-color: var(--danger-color);
    }

    .notification-card.type-info {
        border-left-color: var(--info-color);
    }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        gap: 1rem;
    }

    .notification-title {
        font-weight: 700;
        margin: 0;
        flex-grow: 1;
        color: var(--gray-900);
        font-size: 1.1rem;
        line-height: 1.4;
    }

    .notification-time {
        font-size: 0.875rem;
        color: var(--gray-600);
        font-weight: 500;
        white-space: nowrap;
    }

    .notification-actions {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 2px solid var(--gray-100);
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .filter-tabs {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: var(--radius-xl);
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--gray-200);
    }

    .filter-tab {
        display: inline-flex;
        align-items: center;
        padding: 0.75rem 1.5rem;
        margin-right: 0.75rem;
        margin-bottom: 0.5rem;
        border-radius: 25px;
        text-decoration: none;
        color: var(--gray-600);
        background: var(--gray-50);
        transition: all 0.3s ease;
        font-weight: 500;
        border: 2px solid transparent;
    }

    .filter-tab.active {
        background: linear-gradient(135deg, var(--primary-color) 0%, #2563eb 100%);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .filter-tab:hover {
        color: white;
        background: linear-gradient(135deg, var(--gray-600) 0%, #374151 100%);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .filter-tab.active:hover {
        background: linear-gradient(135deg, var(--primary-color) 0%, #2563eb 100%);
    }

    .notification-icon {
        font-size: 1.3rem;
        margin-right: 0.75rem;
        width: 24px;
        text-align: center;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--gray-600);
        background: white;
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-md);
        margin: 2rem 0;
    }

    .empty-state i {
        font-size: 5rem;
        margin-bottom: 1.5rem;
        opacity: 0.3;
        color: var(--gray-400);
    }

    .empty-state h4 {
        color: var(--gray-900);
        margin-bottom: 0.5rem;
        font-weight: 700;
    }

    .empty-state p {
        color: var(--gray-600);
        font-size: 1.1rem;
    }

    /* Enhanced Buttons */
    .btn {
        border-radius: var(--radius-lg);
        font-weight: 600;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-outline-primary {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
    }

    .btn-outline-primary:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .btn-outline-danger {
        border: 2px solid var(--danger-color);
        color: var(--danger-color);
    }

    .btn-outline-danger:hover {
        background: var(--danger-color);
        border-color: var(--danger-color);
        color: white;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }

    /* Badge Enhancements */
    .badge {
        border-radius: var(--radius-lg);
        font-weight: 600;
        padding: 0.4rem 0.8rem;
    }

    .badge.bg-primary {
        background: linear-gradient(135deg, var(--primary-color) 0%, #2563eb 100%) !important;
    }

    .badge.bg-warning {
        background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%) !important;
    }

    .badge.bg-danger {
        background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%) !important;
    }

    /* Page Header Enhancement */
    .page-header {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: var(--radius-xl);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--gray-200);
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--info-color) 50%, var(--success-color) 100%);
    }

    .page-header h1 {
        color: var(--gray-900);
        font-weight: 800;
        margin-bottom: 0;
        font-size: 2.5rem;
    }

    .page-header h1 i {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--info-color) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    /* Animations */
    .animate-fadeIn {
        animation: fadeIn 0.6s ease-out;
    }

    .animate-slideUp {
        animation: slideUp 0.6s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .page-header {
            padding: 1.5rem;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2rem;
        }

        .notification-card {
            padding: 1.5rem;
        }

        .filter-tabs {
            padding: 1rem;
        }

        .filter-tab {
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            font-size: 0.875rem;
        }

        .notification-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .notification-time {
            align-self: flex-end;
        }
    }
</style>

<!-- Enhanced Page content starts here -->
<div class="container mt-4">
    <div class="page-header animate-fadeIn">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h1><i class="fas fa-bell me-3"></i>Notifications</h1>
                <p class="text-muted mb-0 mt-2">Stay updated with all your BC Management activities</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($counts['unread'] > 0): ?>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                            <i class="fas fa-check-double me-2"></i> Mark All Read
                        </button>
                    </form>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Enhanced Filter Tabs -->
    <div class="filter-tabs animate-slideUp">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 text-muted">
                <i class="fas fa-filter me-2"></i>Filter Notifications
            </h5>
            <small class="text-muted">Total: <?= $counts['total'] ?> notifications</small>
        </div>
        <div class="d-flex flex-wrap">
            <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-list notification-icon"></i>All Notifications
                <span class="badge bg-secondary ms-2"><?= $counts['total'] ?></span>
            </a>
            <a href="?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
                <i class="fas fa-envelope notification-icon"></i>Unread
                <span class="badge bg-primary ms-2"><?= $counts['unread'] ?></span>
            </a>
            <a href="?filter=read" class="filter-tab <?= $filter === 'read' ? 'active' : '' ?>">
                <i class="fas fa-envelope-open notification-icon"></i>Read
                <span class="badge bg-success ms-2"><?= $counts['total'] - $counts['unread'] ?></span>
            </a>
            <a href="?filter=warning" class="filter-tab <?= $filter === 'warning' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-triangle notification-icon"></i>Warnings
                <span class="badge bg-warning ms-2"><?= $counts['warnings'] ?></span>
            </a>
            <a href="?filter=danger" class="filter-tab <?= $filter === 'danger' ? 'active' : '' ?>">
                <i class="fas fa-exclamation-circle notification-icon"></i>Critical Alerts
                <span class="badge bg-danger ms-2"><?= $counts['alerts'] ?></span>
            </a>
        </div>
    </div>

    <!-- Notifications List -->
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h4>No Notifications</h4>
            <p>You don't have any notifications matching the selected filter.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $index => $notification): ?>
            <div class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?> type-<?= $notification['type'] ?> animate-slideUp" style="animation-delay: <?= $index * 0.1 ?>s;">
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
