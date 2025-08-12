<?php
require_once 'config.php';
requireAdminLogin();

// Get all members with group information
$pdo = getDB();
$stmt = $pdo->query("
    SELECT m.*, g.group_name, g.status as group_status,
           COALESCE(ms.total_paid, 0) as total_paid,
           COALESCE(ms.given_amount, 0) as given_amount,
           COALESCE(ms.profit, 0) as profit
    FROM members m 
    JOIN bc_groups g ON m.group_id = g.id 
    LEFT JOIN member_summary ms ON m.id = ms.member_id
    ORDER BY g.group_name, m.member_number
");
$allMembers = $stmt->fetchAll();

// Get groups for filtering
$stmt = $pdo->query("SELECT * FROM bc_groups ORDER BY group_name");
$groups = $stmt->fetchAll();

// Handle member status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $newStatus = $_POST['status'] ?? 'active';
    
    if ($memberId) {
        $stmt = $pdo->prepare("UPDATE members SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $memberId]);
        setMessage('Member status updated successfully!');
        redirect('admin_members.php');
    }
}

// Handle member deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_member'])) {
    $memberId = (int)($_POST['member_id'] ?? 0);
    
    if ($memberId) {
        try {
            $pdo->beginTransaction();
            
            // Check if member has any payments or bids
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM member_payments WHERE member_id = ?");
            $stmt->execute([$memberId]);
            $paymentCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_bids WHERE taken_by_member_id = ?");
            $stmt->execute([$memberId]);
            $bidCount = $stmt->fetchColumn();
            
            if ($paymentCount > 0 || $bidCount > 0) {
                setMessage('Cannot delete member with existing payments or bids. Set status to inactive instead.', 'error');
            } else {
                // Safe to delete
                $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
                $stmt->execute([$memberId]);
                setMessage('Member deleted successfully!');
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setMessage('Failed to delete member.', 'error');
        }
        
        redirect('admin_members.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Management - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .profit-positive { color: #28a745; }
        .profit-negative { color: #dc3545; }
        .status-active { color: #28a745; }
        .status-inactive { color: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins"></i> <?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php $msg = getMessage(); ?>
        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($msg['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-users"></i> Members Management</h1>
            <div class="d-flex gap-2">
                <a href="admin_add_member.php" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Add New Member
                </a>
                <a href="admin_bulk_import.php" class="btn btn-info">
                    <i class="fas fa-upload"></i> Bulk Import
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h3><?= count($allMembers) ?></h3>
                        <p class="mb-0">Total Members</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h3><?= count(array_filter($allMembers, fn($m) => $m['status'] === 'active')) ?></h3>
                        <p class="mb-0">Active Members</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-times fa-2x mb-2"></i>
                        <h3><?= count(array_filter($allMembers, fn($m) => $m['status'] === 'inactive')) ?></h3>
                        <p class="mb-0">Inactive Members</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-layer-group fa-2x mb-2"></i>
                        <h3><?= count($groups) ?></h3>
                        <p class="mb-0">Total Groups</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-table"></i> All Members
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="membersTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Group</th>
                                <th>Member #</th>
                                <th>Username</th>
                                <th>Contact</th>
                                <th>Total Paid</th>
                                <th>Given Amount</th>
                                <th>Profit/Loss</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allMembers as $member): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="member-avatar me-2">
                                                <?= strtoupper(substr($member['member_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($member['member_name']) ?></div>
                                                <small class="text-muted">ID: <?= $member['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $member['group_status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= htmlspecialchars($member['group_name']) ?>
                                        </span>
                                    </td>
                                    <td><span class="badge bg-primary">#<?= $member['member_number'] ?></span></td>
                                    <td>
                                        <code><?= htmlspecialchars($member['username'] ?: 'Not Set') ?></code>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if ($member['phone']): ?>
                                                <i class="fas fa-phone text-success"></i> <?= htmlspecialchars($member['phone']) ?><br>
                                            <?php endif; ?>
                                            <?php if ($member['email']): ?>
                                                <i class="fas fa-envelope text-info"></i> <?= htmlspecialchars($member['email']) ?>
                                            <?php endif; ?>
                                            <?php if (!$member['phone'] && !$member['email']): ?>
                                                <span class="text-muted">No contact info</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= formatCurrency($member['total_paid']) ?></td>
                                    <td><?= formatCurrency($member['given_amount']) ?></td>
                                    <td>
                                        <span class="fw-bold <?= $member['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                            <?= formatCurrency($member['profit']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($member['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="admin_edit_member.php?id=<?= $member['id'] ?>" 
                                               class="btn btn-outline-primary" title="Edit Member">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="admin_member_details.php?id=<?= $member['id'] ?>" 
                                               class="btn btn-outline-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="toggleStatus(<?= $member['id'] ?>, '<?= $member['status'] ?>')" 
                                                    title="Toggle Status">
                                                <i class="fas fa-toggle-<?= $member['status'] === 'active' ? 'on' : 'off' ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteMember(<?= $member['id'] ?>, '<?= htmlspecialchars($member['member_name']) ?>')" 
                                                    title="Delete Member">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="update_status" value="1">
        <input type="hidden" name="member_id" id="statusMemberId">
        <input type="hidden" name="status" id="statusValue">
    </form>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_member" value="1">
        <input type="hidden" name="member_id" id="deleteMemberId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#membersTable').DataTable({
                pageLength: 25,
                order: [[1, 'asc'], [2, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [9] }
                ]
            });
        });

        function toggleStatus(memberId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${action} this member?`)) {
                document.getElementById('statusMemberId').value = memberId;
                document.getElementById('statusValue').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }

        function deleteMember(memberId, memberName) {
            if (confirm(`Are you sure you want to delete "${memberName}"?\n\nThis action cannot be undone and will only work if the member has no payments or bids.`)) {
                document.getElementById('deleteMemberId').value = memberId;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
