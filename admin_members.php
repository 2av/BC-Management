<?php
require_once 'config.php';
requireAdminLogin();

// Get consolidated member information
$pdo = getDB();

// Get unique members with their group memberships (consolidated by member_name only)
$stmt = $pdo->query("
    SELECT
        m.member_name,
        MAX(m.username) as username,  -- Take any username (they should be standardized)
        MAX(m.phone) as phone,        -- Take any phone (should be same for same person)
        MAX(m.email) as email,        -- Take any email (should be same for same person)
        MAX(m.status) as status,      -- Take the latest status
        COUNT(DISTINCT m.group_id) as total_groups,
        GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as group_names,
        GROUP_CONCAT(DISTINCT g.id ORDER BY g.group_name SEPARATOR ',') as group_ids,
        SUM(COALESCE(ms.total_paid, 0)) as total_paid_all_groups,
        SUM(COALESCE(ms.given_amount, 0)) as total_given_all_groups,
        SUM(COALESCE(ms.profit, 0)) as total_profit_all_groups,
        MIN(m.created_at) as first_joined
    FROM members m
    JOIN bc_groups g ON m.group_id = g.id
    LEFT JOIN member_summary ms ON m.id = ms.member_id
    WHERE m.member_name IS NOT NULL AND m.member_name != ''
    GROUP BY m.member_name
    ORDER BY m.member_name
");
$consolidatedMembers = $stmt->fetchAll();

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
        .group-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
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
                        <h3><?= count($consolidatedMembers) ?></h3>
                        <p class="mb-0">Unique Members</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-check fa-2x mb-2"></i>
                        <h3><?= count(array_filter($consolidatedMembers, fn($m) => $m['status'] === 'active')) ?></h3>
                        <p class="mb-0">Active Members</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="fas fa-user-times fa-2x mb-2"></i>
                        <h3><?= count(array_filter($consolidatedMembers, fn($m) => $m['status'] === 'inactive')) ?></h3>
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
                                <th>Groups</th>
                                <th>Username</th>
                                <th>Contact</th>
                                <th>Total Paid</th>
                                <th>Total Given</th>
                                <th>Total Profit/Loss</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consolidatedMembers as $member): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="member-avatar me-2">
                                                <?= strtoupper(substr($member['member_name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($member['member_name']) ?></div>
                                                <small class="text-muted">Joined: <?= date('M Y', strtotime($member['first_joined'])) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-info"><?= $member['total_groups'] ?> group<?= $member['total_groups'] > 1 ? 's' : '' ?></span>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    onclick="showGroupDetails('<?= htmlspecialchars($member['member_name']) ?>', '<?= $member['group_ids'] ?>', '<?= htmlspecialchars($member['group_names']) ?>')"
                                                    title="View Group Details">
                                                <i class="fas fa-eye"></i> View Groups
                                            </button>
                                        </div>
                                        <div class="mt-1">
                                            <small class="text-muted"><?= htmlspecialchars(strlen($member['group_names']) > 50 ? substr($member['group_names'], 0, 50) . '...' : $member['group_names']) ?></small>
                                        </div>
                                    </td>
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
                                    <td><?= formatCurrency($member['total_paid_all_groups']) ?></td>
                                    <td><?= formatCurrency($member['total_given_all_groups']) ?></td>
                                    <td>
                                        <span class="fw-bold <?= $member['total_profit_all_groups'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                            <?= formatCurrency($member['total_profit_all_groups']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($member['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary"
                                                    onclick="editMemberCredentials('<?= htmlspecialchars($member['member_name']) ?>', '<?= htmlspecialchars($member['username']) ?>')"
                                                    title="Edit Credentials">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-info"
                                                    onclick="showMemberDetails('<?= htmlspecialchars($member['member_name']) ?>', '<?= $member['group_ids'] ?>')"
                                                    title="View Full Details">
                                                <i class="fas fa-user"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success"
                                                    onclick="addToGroup('<?= htmlspecialchars($member['member_name']) ?>')"
                                                    title="Add to Another Group">
                                                <i class="fas fa-plus"></i>
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

    <!-- Group Details Modal -->
    <div class="modal fade" id="groupDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-layer-group"></i> Group Memberships - <span id="memberNameTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="groupDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Member Details Modal -->
    <div class="modal fade" id="memberDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user"></i> Member Details - <span id="memberDetailsTitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="memberDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

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
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [8] }
                ]
            });
        });

        function showGroupDetails(memberName, groupIds, groupNames) {
            document.getElementById('memberNameTitle').textContent = memberName;

            const groupIdArray = groupIds.split(',');
            const groupNameArray = groupNames.split(', ');

            let content = '<div class="row">';
            groupIdArray.forEach((groupId, index) => {
                content += `
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-users"></i> ${groupNameArray[index]}
                                </h6>
                                <p class="card-text">
                                    <small class="text-muted">Group ID: ${groupId}</small>
                                </p>
                                <div class="d-flex gap-2">
                                    <a href="view_group.php?id=${groupId}" class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-eye"></i> View Group
                                    </a>
                                    <a href="manage_members.php?group_id=${groupId}" class="btn btn-sm btn-info" target="_blank">
                                        <i class="fas fa-users-cog"></i> Manage Members
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            content += '</div>';

            document.getElementById('groupDetailsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('groupDetailsModal')).show();
        }

        function showMemberDetails(memberName, groupIds) {
            document.getElementById('memberDetailsTitle').textContent = memberName;
            document.getElementById('memberDetailsContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            // Load detailed member information via AJAX
            fetch(`admin_member_details_ajax.php?member_name=${encodeURIComponent(memberName)}&group_ids=${groupIds}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('memberDetailsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('memberDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading member details.</div>';
                });

            new bootstrap.Modal(document.getElementById('memberDetailsModal')).show();
        }

        function editMemberCredentials(memberName, username) {
            const newUsername = prompt(`Edit username for ${memberName}:`, username);
            if (newUsername && newUsername !== username) {
                // Implement username update logic
                alert('Username update functionality to be implemented');
            }
        }

        function addToGroup(memberName) {
            const groupId = prompt(`Enter Group ID to add ${memberName} to:`);
            if (groupId && !isNaN(groupId)) {
                window.open(`manage_members.php?group_id=${groupId}&add_member=${encodeURIComponent(memberName)}`, '_blank');
            }
        }
    </script>
</body>
</html>
