<?php
require_once 'simple_mt_config.php';
require_once 'subscription_functions.php';
requireSuperAdminLogin();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_plan':
                    $features = array_filter(explode("\n", $_POST['features']));
                    $planData = [
                        'plan_name' => $_POST['plan_name'],
                        'duration_months' => (int)$_POST['duration_months'],
                        'price' => (float)$_POST['price'],
                        'currency' => $_POST['currency'],
                        'description' => $_POST['description'],
                        'features' => $features,
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
                    $features = array_filter(explode("\n", $_POST['features']));
                    $planData = [
                        'plan_name' => $_POST['plan_name'],
                        'duration_months' => (int)$_POST['duration_months'],
                        'price' => (float)$_POST['price'],
                        'currency' => $_POST['currency'],
                        'description' => $_POST['description'],
                        'features' => $features,
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
    <title>Subscription Plans Management - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/modern-design.css" rel="stylesheet">
    <style>
        .plan-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .plan-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .plan-card.promotional {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffffff 100%);
        }
        
        .plan-card.inactive {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
        
        .plan-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .plan-duration {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
        }
        
        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .feature-list li:last-child {
            border-bottom: none;
        }
        
        .feature-list li i {
            color: #28a745;
            margin-right: 0.5rem;
        }
        
        .promotional-badge {
            position: absolute;
            top: 15px;
            right: -30px;
            background: #ffc107;
            color: #000;
            padding: 5px 40px;
            transform: rotate(45deg);
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <?php include 'super_admin_navbar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-credit-card me-3"></i>Subscription Plans Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planModal">
                        <i class="fas fa-plus me-2"></i>Add New Plan
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $stats['active_subscriptions'] ?></div>
                            <div>Active Subscriptions</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= formatCurrency($stats['monthly_revenue'], 'INR', 2) ?></div>
                            <div>Monthly Revenue</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= $stats['expiring_soon'] ?></div>
                            <div>Expiring Soon</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?= count($plans) ?></div>
                            <div>Total Plans</div>
                        </div>
                    </div>
                </div>

                <!-- Subscription Plans -->
                <div class="row">
                    <?php foreach ($plans as $plan): ?>
                        <div class="col-md-4 mb-4">
                            <div class="plan-card <?= $plan['is_promotional'] ? 'promotional' : '' ?> <?= !$plan['is_active'] ? 'inactive' : '' ?>">
                                <?php if ($plan['is_promotional']): ?>
                                    <div class="promotional-badge">PROMO</div>
                                <?php endif; ?>
                                
                                <div class="card-body p-4">
                                    <div class="text-center mb-3">
                                        <h4 class="card-title"><?= htmlspecialchars($plan['plan_name']) ?></h4>
                                        <div class="plan-price"><?= formatCurrency($plan['price'], $plan['currency'], 2) ?></div>
                                        <div class="plan-duration">
                                            <?php if ($plan['duration_months'] == 0): ?>
                                                Free Trial
                                            <?php elseif ($plan['duration_months'] == 1): ?>
                                                Per Month
                                            <?php elseif ($plan['duration_months'] < 12): ?>
                                                <?= $plan['duration_months'] ?> Months
                                            <?php else: ?>
                                                <?= ($plan['duration_months'] / 12) ?> Year<?= ($plan['duration_months'] > 12) ? 's' : '' ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <p class="text-muted mb-3"><?= htmlspecialchars($plan['description']) ?></p>
                                    
                                    <ul class="feature-list">
                                        <?php 
                                        $features = json_decode($plan['features'], true) ?: [];
                                        foreach ($features as $feature): 
                                        ?>
                                            <li><i class="fas fa-check"></i><?= htmlspecialchars($feature) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <?php if ($plan['max_groups']): ?>
                                                Max Groups: <?= $plan['max_groups'] ?><br>
                                            <?php endif; ?>
                                            <?php if ($plan['max_members_per_group']): ?>
                                                Max Members per Group: <?= $plan['max_members_per_group'] ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <div class="mt-3 d-flex gap-2">
                                        <a href="?edit=<?= $plan['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                            <button type="submit" class="btn btn-outline-<?= $plan['is_active'] ? 'warning' : 'success' ?> btn-sm">
                                                <i class="fas fa-<?= $plan['is_active'] ? 'pause' : 'play' ?>"></i>
                                                <?= $plan['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Plan Modal -->
    <div class="modal fade" id="planModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $editPlan ? 'Edit Subscription Plan' : 'Add New Subscription Plan' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editPlan ? 'update_plan' : 'create_plan' ?>">
                        <?php if ($editPlan): ?>
                            <input type="hidden" name="plan_id" value="<?= $editPlan['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Plan Name</label>
                                    <input type="text" name="plan_name" class="form-control" 
                                           value="<?= $editPlan ? htmlspecialchars($editPlan['plan_name']) : '' ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Duration (Months)</label>
                                    <input type="number" name="duration_months" class="form-control" min="0"
                                           value="<?= $editPlan ? $editPlan['duration_months'] : '' ?>" required>
                                    <small class="text-muted">Use 0 for trial plans</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Price</label>
                                    <input type="number" name="price" class="form-control" step="0.01" min="0"
                                           value="<?= $editPlan ? $editPlan['price'] : '' ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-control">
                                        <option value="INR" <?= ($editPlan && $editPlan['currency'] == 'INR') ? 'selected' : '' ?>>INR (₹)</option>
                                        <option value="USD" <?= ($editPlan && $editPlan['currency'] == 'USD') ? 'selected' : '' ?>>USD ($)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?= $editPlan ? htmlspecialchars($editPlan['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Features (one per line)</label>
                            <textarea name="features" class="form-control" rows="4"><?php 
                                if ($editPlan) {
                                    $features = json_decode($editPlan['features'], true) ?: [];
                                    echo htmlspecialchars(implode("\n", $features));
                                }
                            ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Groups</label>
                                    <input type="number" name="max_groups" class="form-control" min="1"
                                           value="<?= $editPlan ? $editPlan['max_groups'] : '' ?>">
                                    <small class="text-muted">Leave empty for unlimited</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Max Members per Group</label>
                                    <input type="number" name="max_members_per_group" class="form-control" min="1"
                                           value="<?= $editPlan ? $editPlan['max_members_per_group'] : '' ?>">
                                    <small class="text-muted">Leave empty for unlimited</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input type="checkbox" name="is_promotional" class="form-check-input" id="isPromotional"
                                           <?= ($editPlan && $editPlan['is_promotional']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isPromotional">Promotional Plan</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Promotional Discount (%)</label>
                                    <input type="number" name="promotional_discount" class="form-control" min="0" max="100" step="0.01"
                                           value="<?= $editPlan ? $editPlan['promotional_discount'] : '0' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <?= $editPlan ? 'Update Plan' : 'Create Plan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($editPlan): ?>
    <script>
        // Show modal if editing
        document.addEventListener('DOMContentLoaded', function() {
            new bootstrap.Modal(document.getElementById('planModal')).show();
        });
    </script>
    <?php endif; ?>
</body>
</html>
