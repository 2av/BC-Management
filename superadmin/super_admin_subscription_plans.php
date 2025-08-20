<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
require_once '../common/subscription_functions.php';
checkRole('superadmin');

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($_POST['action']) {
            case 'create_plan':
                $planData = [
                    'plan_name' => $_POST['plan_name'],
                    'duration_months' => (int)$_POST['duration_months'],
                    'price' => (float)$_POST['price'],
                    'currency' => $_POST['currency'] ?? 'INR',
                    'description' => $_POST['description'],
                    'features' => explode("\n", $_POST['features']),
                    'is_promotional' => isset($_POST['is_promotional']),
                    'promotional_discount' => (float)($_POST['promotional_discount'] ?? 0),
                    'max_groups' => $_POST['max_groups'] ? (int)$_POST['max_groups'] : null,
                    'max_members_per_group' => $_POST['max_members_per_group'] ? (int)$_POST['max_members_per_group'] : null
                ];
                
                if (createSubscriptionPlan($planData)) {
                    $message = 'Subscription plan created successfully!';
                } else {
                    $error = 'Failed to create subscription plan.';
                }
                break;
                
            case 'update_plan':
                $planData = [
                    'plan_name' => $_POST['plan_name'],
                    'duration_months' => (int)$_POST['duration_months'],
                    'price' => (float)$_POST['price'],
                    'currency' => $_POST['currency'] ?? 'INR',
                    'description' => $_POST['description'],
                    'features' => explode("\n", $_POST['features']),
                    'is_promotional' => isset($_POST['is_promotional']),
                    'promotional_discount' => (float)($_POST['promotional_discount'] ?? 0),
                    'max_groups' => $_POST['max_groups'] ? (int)$_POST['max_groups'] : null,
                    'max_members_per_group' => $_POST['max_members_per_group'] ? (int)$_POST['max_members_per_group'] : null
                ];
                
                if (updateSubscriptionPlan($_POST['plan_id'], $planData)) {
                    $message = 'Subscription plan updated successfully!';
                } else {
                    $error = 'Failed to update subscription plan.';
                }
                break;
                
            case 'toggle_status':
                if (togglePlanStatus($_POST['plan_id'])) {
                    $message = 'Plan status updated successfully!';
                } else {
                    $error = 'Failed to update plan status.';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get all plans
$plans = getAllSubscriptionPlans();
$stats = getSubscriptionStats();

// Get plan for editing if requested
$editPlan = null;
if (isset($_GET['edit'])) {
    $editPlan = getSubscriptionPlan($_GET['edit']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-modern {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-link-modern {
            color: #667eea !important;
            font-weight: 600;
            padding: 0.75rem 1.5rem !important;
            border-radius: 50px;
            margin: 0 0.25rem;
            transition: all 0.3s ease;
        }

        .nav-link-modern:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white !important;
            transform: translateY(-2px);
        }

        .dashboard-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .welcome-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .welcome-title {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .section-title {
            color: #495057;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 1.2rem;
        }

        .plan-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .plan-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .plan-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #495057;
        }

        .plan-price {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .plan-features li {
            padding: 0.25rem 0;
            color: #6c757d;
        }

        .plan-features li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .btn-modern {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-outline-modern {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-modern:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-modern">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-crown text-primary me-2"></i>
                <span class="fw-bold text-primary"><?= APP_NAME ?></span>
                <small class="text-muted ms-2">Super Admin</small>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link-modern" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
                <a class="nav-link-modern" href="super_admin_subscription_plans.php">
                    <i class="fas fa-credit-card me-2"></i>Plans
                </a>
                <a class="nav-link-modern" href="super_admin_subscriptions.php">
                    <i class="fas fa-calendar-check me-2"></i>Subscriptions
                </a>
                <a class="nav-link-modern" href="super_admin_clients.php">
                    <i class="fas fa-building me-2"></i>Clients
                </a>
                <div class="dropdown">
                    <a class="nav-link-modern dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>Admin
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="welcome-title">
                        <i class="fas fa-credit-card me-3"></i>Subscription Plans Management
                    </h1>
                    <p class="welcome-subtitle mb-0">
                        Create and manage subscription plans for your clients
                    </p>
                </div>
                <div>
                    <button class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                        <i class="fas fa-plus me-2"></i>Create New Plan
                    </button>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Plans Overview -->
        <div class="section-card">
            <h3 class="section-title">
                <i class="fas fa-list"></i>All Subscription Plans
            </h3>

            <?php if (empty($plans)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-credit-card fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No subscription plans found</h4>
                    <p class="text-muted">Create your first subscription plan to get started.</p>
                    <button class="btn btn-modern" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                        <i class="fas fa-plus me-2"></i>Create First Plan
                    </button>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($plans as $plan): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="plan-card">
                                <div class="plan-header">
                                    <div>
                                        <div class="plan-name"><?= htmlspecialchars($plan['plan_name']) ?></div>
                                        <div class="plan-price">₹<?= number_format($plan['price']) ?></div>
                                        <small class="text-muted"><?= $plan['duration_months'] ?> months</small>
                                    </div>
                                    <div>
                                        <span class="status-badge <?= $plan['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                            <?= $plan['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                </div>

                                <p class="text-muted"><?= htmlspecialchars($plan['description']) ?></p>

                                <?php if ($plan['features']): ?>
                                    <?php $features = is_string($plan['features']) ? json_decode($plan['features'], true) : $plan['features']; ?>
                                    <?php if ($features): ?>
                                        <ul class="plan-features">
                                            <?php foreach (array_slice($features, 0, 3) as $feature): ?>
                                                <li><?= htmlspecialchars($feature) ?></li>
                                            <?php endforeach; ?>
                                            <?php if (count($features) > 3): ?>
                                                <li class="text-muted">+<?= count($features) - 3 ?> more features</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="d-flex gap-2 mt-3">
                                    <a href="?edit=<?= $plan['id'] ?>" class="btn btn-outline-modern btn-sm">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                        <button type="submit" class="btn btn-outline-modern btn-sm" 
                                                onclick="return confirm('Are you sure you want to <?= $plan['is_active'] ? 'deactivate' : 'activate' ?> this plan?')">
                                            <i class="fas fa-<?= $plan['is_active'] ? 'pause' : 'play' ?> me-1"></i>
                                            <?= $plan['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Plan Modal -->
    <div class="modal fade" id="createPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Create New Subscription Plan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_plan">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_name" class="form-label">Plan Name</label>
                                    <input type="text" class="form-control" id="plan_name" name="plan_name" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="duration_months" class="form-label">Duration (Months)</label>
                                    <input type="number" class="form-control" id="duration_months" name="duration_months" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price (₹)</label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="features" class="form-label">Features (one per line)</label>
                            <textarea class="form-control" id="features" name="features" rows="5"
                                      placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_groups" class="form-label">Max Groups (leave empty for unlimited)</label>
                                    <input type="number" class="form-control" id="max_groups" name="max_groups" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_members_per_group" class="form-label">Max Members per Group</label>
                                    <input type="number" class="form-control" id="max_members_per_group" name="max_members_per_group" min="1">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_promotional" name="is_promotional">
                                    <label class="form-check-label" for="is_promotional">
                                        Promotional Plan
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="promotional_discount" class="form-label">Promotional Discount (%)</label>
                                    <input type="number" class="form-control" id="promotional_discount" name="promotional_discount"
                                           min="0" max="100" step="0.01" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-save me-2"></i>Create Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <?php if ($editPlan): ?>
    <div class="modal fade show" id="editPlanModal" tabindex="-1" style="display: block;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Subscription Plan
                    </h5>
                    <a href="super_admin_subscription_plans.php" class="btn-close btn-close-white"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_plan">
                        <input type="hidden" name="plan_id" value="<?= $editPlan['id'] ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_plan_name" class="form-label">Plan Name</label>
                                    <input type="text" class="form-control" id="edit_plan_name" name="plan_name"
                                           value="<?= htmlspecialchars($editPlan['plan_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="edit_duration_months" class="form-label">Duration (Months)</label>
                                    <input type="number" class="form-control" id="edit_duration_months" name="duration_months"
                                           value="<?= $editPlan['duration_months'] ?>" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="edit_price" class="form-label">Price (₹)</label>
                                    <input type="number" class="form-control" id="edit_price" name="price"
                                           value="<?= $editPlan['price'] ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" required><?= htmlspecialchars($editPlan['description']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="edit_features" class="form-label">Features (one per line)</label>
                            <textarea class="form-control" id="edit_features" name="features" rows="5"><?php
                                $features = is_string($editPlan['features']) ? json_decode($editPlan['features'], true) : $editPlan['features'];
                                if ($features) {
                                    echo htmlspecialchars(implode("\n", $features));
                                }
                            ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_max_groups" class="form-label">Max Groups</label>
                                    <input type="number" class="form-control" id="edit_max_groups" name="max_groups"
                                           value="<?= $editPlan['max_groups'] ?>" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_max_members_per_group" class="form-label">Max Members per Group</label>
                                    <input type="number" class="form-control" id="edit_max_members_per_group" name="max_members_per_group"
                                           value="<?= $editPlan['max_members_per_group'] ?>" min="1">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="edit_is_promotional" name="is_promotional"
                                           <?= $editPlan['is_promotional'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_is_promotional">
                                        Promotional Plan
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_promotional_discount" class="form-label">Promotional Discount (%)</label>
                                    <input type="number" class="form-control" id="edit_promotional_discount" name="promotional_discount"
                                           value="<?= $editPlan['promotional_discount'] ?>" min="0" max="100" step="0.01"
                                           <?= !$editPlan['is_promotional'] ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="super_admin_subscription_plans.php" class="btn btn-outline-modern">Cancel</a>
                        <button type="submit" class="btn btn-modern">
                            <i class="fas fa-save me-2"></i>Update Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle promotional discount field
        document.getElementById('is_promotional').addEventListener('change', function() {
            const discountField = document.getElementById('promotional_discount');
            discountField.disabled = !this.checked;
            if (!this.checked) {
                discountField.value = '';
            }
        });

        // For edit modal
        const editPromotionalCheckbox = document.getElementById('edit_is_promotional');
        if (editPromotionalCheckbox) {
            editPromotionalCheckbox.addEventListener('change', function() {
                const discountField = document.getElementById('edit_promotional_discount');
                discountField.disabled = !this.checked;
                if (!this.checked) {
                    discountField.value = '';
                }
            });
        }

        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
