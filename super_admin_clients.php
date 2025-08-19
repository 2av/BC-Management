<?php
require_once 'mt_config.php';
requireSuperAdminLogin();

// Handle client status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $clientId = $_POST['client_id'] ?? 0;
    $action = $_POST['action'];
    
    if ($clientId && in_array($action, ['activate', 'deactivate', 'suspend', 'delete'])) {
        $pdo = getDB();
        
        try {
            if ($action === 'delete') {
                // Soft delete - we don't actually delete due to foreign key constraints
                $stmt = $pdo->prepare("UPDATE clients SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$clientId]);
                setMessage('Client has been deactivated successfully.', 'success');
            } else {
                $newStatus = ($action === 'activate') ? 'active' : (($action === 'suspend') ? 'suspended' : 'inactive');
                $stmt = $pdo->prepare("UPDATE clients SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $clientId]);
                setMessage("Client status updated to {$newStatus} successfully.", 'success');
            }
            
            // Log the action
            logAuditAction($clientId, 'super_admin', $_SESSION['super_admin_id'], "client_{$action}", 'clients', $clientId);
            
        } catch (Exception $e) {
            setMessage('Error updating client status: ' . $e->getMessage(), 'danger');
        }
        
        redirect('super_admin_clients.php');
    }
}

// Get all clients with statistics
$pdo = getDB();
$stmt = $pdo->query("
    SELECT 
        c.*,
        sa.full_name as created_by_name,
        COUNT(DISTINCT g.id) as total_groups,
        COUNT(DISTINCT CASE WHEN g.status = 'active' THEN g.id END) as active_groups,
        COUNT(DISTINCT m.id) as total_members,
        COUNT(DISTINCT CASE WHEN m.status = 'active' THEN m.id END) as active_members,
        SUM(g.monthly_contribution * g.total_members) as monthly_collection
    FROM clients c
    LEFT JOIN super_admins sa ON c.created_by = sa.id
    LEFT JOIN bc_groups g ON c.id = g.client_id
    LEFT JOIN members m ON g.id = m.group_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$clients = $stmt->fetchAll();

$message = getMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .super-admin-navbar {
            background: linear-gradient(135deg, #2c3e50, #e74c3c);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .client-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid #3498db;
        }

        .client-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .client-card.inactive {
            border-left-color: #95a5a6;
            opacity: 0.8;
        }

        .client-card.suspended {
            border-left-color: #f39c12;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-suspended {
            background: #fff3cd;
            color: #856404;
        }

        .stats-mini {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.5rem 0.8rem;
            margin: 0.2rem;
            display: inline-block;
            font-size: 0.85rem;
        }

        .action-buttons .btn {
            margin: 0.2rem;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .subscription-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .subscription-basic {
            background: #e3f2fd;
            color: #1976d2;
        }

        .subscription-premium {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .subscription-enterprise {
            background: #e8f5e8;
            color: #388e3c;
        }
    </style>
</head>
<body>
    <!-- Super Admin Navigation -->
    <nav class="navbar navbar-expand-lg super-admin-navbar">
        <div class="container">
            <a class="navbar-brand text-white" href="super_admin_dashboard.php">
                <i class="fas fa-crown me-2"></i>
                <span><?= APP_NAME ?> - Super Admin</span>
            </a>

            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="super_admin_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="super_admin_clients.php">
                            <i class="fas fa-building me-1"></i>Manage Clients
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-crown me-1"></i><?= htmlspecialchars($_SESSION['super_admin_name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show">
                <?= htmlspecialchars($message['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-building me-2 text-primary"></i>Client Management</h2>
                <p class="text-muted">Manage all client accounts and their access to the platform</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="super_admin_add_client.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Client
                </a>
            </div>
        </div>

        <!-- Clients List -->
        <?php if (empty($clients)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Clients Found</h4>
                <p class="text-muted">Get started by adding your first client to the platform.</p>
                <a href="super_admin_add_client.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add First Client
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($clients as $client): ?>
                <div class="client-card <?= $client['status'] ?>">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-2">
                                <h5 class="mb-0 me-3"><?= htmlspecialchars($client['client_name']) ?></h5>
                                <span class="status-badge status-<?= $client['status'] ?>">
                                    <?= ucfirst($client['status']) ?>
                                </span>
                                <span class="subscription-badge subscription-<?= $client['subscription_plan'] ?> ms-2">
                                    <?= ucfirst($client['subscription_plan']) ?>
                                </span>
                            </div>
                            
                            <p class="text-muted mb-2">
                                <strong><?= htmlspecialchars($client['company_name']) ?></strong>
                            </p>
                            
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i><?= htmlspecialchars($client['contact_person']) ?>
                                    <i class="fas fa-envelope ms-3 me-1"></i><?= htmlspecialchars($client['email']) ?>
                                    <i class="fas fa-phone ms-3 me-1"></i><?= htmlspecialchars($client['phone']) ?>
                                </small>
                            </div>
                            
                            <div>
                                <span class="stats-mini">
                                    <i class="fas fa-layer-group me-1"></i>
                                    <?= $client['total_groups'] ?> Groups (<?= $client['active_groups'] ?> Active)
                                </span>
                                <span class="stats-mini">
                                    <i class="fas fa-users me-1"></i>
                                    <?= $client['total_members'] ?> Members (<?= $client['active_members'] ?> Active)
                                </span>
                                <span class="stats-mini">
                                    <i class="fas fa-rupee-sign me-1"></i>
                                    <?= formatCurrency($client['monthly_collection'] ?? 0) ?> Monthly
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <small class="text-muted d-block">Created by: <?= htmlspecialchars($client['created_by_name']) ?></small>
                            <small class="text-muted d-block">Date: <?= formatDate($client['created_at']) ?></small>
                            <small class="text-muted d-block">
                                Limits: <?= $client['max_groups'] ?> groups, <?= $client['max_members_per_group'] ?> members/group
                            </small>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="action-buttons text-end">
                                <a href="super_admin_view_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="super_admin_edit_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                
                                <?php if ($client['status'] === 'active'): ?>
                                    <button class="btn btn-sm btn-outline-warning" onclick="updateClientStatus(<?= $client['id'] ?>, 'suspend')">
                                        <i class="fas fa-pause"></i> Suspend
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="updateClientStatus(<?= $client['id'] ?>, 'deactivate')">
                                        <i class="fas fa-ban"></i> Deactivate
                                    </button>
                                <?php elseif ($client['status'] === 'suspended'): ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="updateClientStatus(<?= $client['id'] ?>, 'activate')">
                                        <i class="fas fa-play"></i> Activate
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="updateClientStatus(<?= $client['id'] ?>, 'deactivate')">
                                        <i class="fas fa-ban"></i> Deactivate
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success" onclick="updateClientStatus(<?= $client['id'] ?>, 'activate')">
                                        <i class="fas fa-check"></i> Activate
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Hidden form for status updates -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="client_id" id="statusClientId">
        <input type="hidden" name="action" id="statusAction">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateClientStatus(clientId, action) {
            const actionText = action === 'activate' ? 'activate' : 
                              action === 'suspend' ? 'suspend' : 
                              action === 'deactivate' ? 'deactivate' : 'delete';
            
            if (confirm(`Are you sure you want to ${actionText} this client?`)) {
                document.getElementById('statusClientId').value = clientId;
                document.getElementById('statusAction').value = action;
                document.getElementById('statusForm').submit();
            }
        }
    </script>
</body>
</html>
