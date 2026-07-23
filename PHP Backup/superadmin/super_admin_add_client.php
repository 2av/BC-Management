<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('superadmin');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $clientName = trim($_POST['client_name'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'India');
    $pincode = trim($_POST['pincode'] ?? '');
    $subscriptionPlan = $_POST['subscription_plan'] ?? 'basic';
    $maxGroups = (int)($_POST['max_groups'] ?? 10);
    $maxMembersPerGroup = (int)($_POST['max_members_per_group'] ?? 50);
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($clientName)) $errors[] = 'Client name is required.';
    if (empty($contactPerson)) $errors[] = 'Contact person is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (!in_array($subscriptionPlan, ['basic', 'premium', 'enterprise'])) $errors[] = 'Invalid subscription plan.';
    if ($maxGroups < 1 || $maxGroups > 1000) $errors[] = 'Max groups must be between 1 and 1000.';
    if ($maxMembersPerGroup < 1 || $maxMembersPerGroup > 500) $errors[] = 'Max members per group must be between 1 and 500.';
    
    if (!empty($adminUsername)) {
        if (strlen($adminUsername) < 3) $errors[] = 'Admin username must be at least 3 characters.';
        if (empty($adminPassword)) $errors[] = 'Admin password is required when username is provided.';
        if (strlen($adminPassword) < 6) $errors[] = 'Admin password must be at least 6 characters.';
        if ($adminPassword !== $confirmPassword) $errors[] = 'Admin passwords do not match.';
    }

    // Check for duplicate email
    if (empty($errors)) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'A client with this email already exists.';
        }

        // Check for duplicate admin username if provided
        if (!empty($adminUsername)) {
            $stmt = $pdo->prepare("SELECT id FROM client_admins WHERE username = ?");
            $stmt->execute([$adminUsername]);
            if ($stmt->fetch()) {
                $errors[] = 'This admin username is already taken.';
            }
        }
    }

    // Create client if no errors
    if (empty($errors)) {
        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            // Insert client
            $stmt = $pdo->prepare("
                INSERT INTO clients (client_name, company_name, contact_person, email, phone, address, city, state, country, pincode, max_groups, max_members_per_group, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $clientName, $companyName, $contactPerson, $email, $phone, 
                $address, $city, $state, $country, $pincode, 
                $maxGroups, $maxMembersPerGroup, $_SESSION['super_admin_id']
            ]);
            
            $clientId = $pdo->lastInsertId();

            // Create admin account if provided
            if (!empty($adminUsername) && !empty($adminPassword)) {
                $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO client_admins (client_id, username, password, full_name, email, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $clientId, $adminUsername, $hashedPassword, 
                    $contactPerson, $email, $_SESSION['super_admin_id']
                ]);
            }

            $pdo->commit();
            setMessage('Client created successfully!', 'success');
            redirect('super_admin_clients.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Error creating client: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Client - Super Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .super-admin-navbar {
            background: linear-gradient(135deg, #2c3e50, #e74c3c);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
        }

        .btn-secondary {
            border-radius: 8px;
            padding: 0.75rem 2rem;
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
                        <a class="nav-link text-white" href="super_admin_clients.php">
                            <i class="fas fa-building me-1"></i>Manage Clients
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-crown me-1"></i>Super Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-plus me-2 text-primary"></i>Add New Client</h2>
                <p class="text-muted">Create a new client account with access to the BC Management platform</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="super_admin_clients.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Clients
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Basic Information -->
            <div class="form-card">
                <div class="section-header">
                    <h4><i class="fas fa-info-circle me-2 text-primary"></i>Basic Information</h4>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="client_name" class="form-label">Client Name *</label>
                        <input type="text" class="form-control" id="client_name" name="client_name" 
                               value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="company_name" class="form-label">Company Name</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" 
                               value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="contact_person" class="form-label">Contact Person *</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" 
                               value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="subscription_plan" class="form-label">Subscription Plan</label>
                        <select class="form-select" id="subscription_plan" name="subscription_plan">
                            <option value="basic" <?= ($_POST['subscription_plan'] ?? 'basic') === 'basic' ? 'selected' : '' ?>>Basic</option>
                            <option value="premium" <?= ($_POST['subscription_plan'] ?? '') === 'premium' ? 'selected' : '' ?>>Premium</option>
                            <option value="enterprise" <?= ($_POST['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="form-card">
                <div class="section-header">
                    <h4><i class="fas fa-map-marker-alt me-2 text-primary"></i>Address Information</h4>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="city" class="form-label">City</label>
                        <input type="text" class="form-control" id="city" name="city"
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="state" class="form-label">State</label>
                        <input type="text" class="form-control" id="state" name="state"
                               value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="pincode" class="form-label">Pincode</label>
                        <input type="text" class="form-control" id="pincode" name="pincode"
                               value="<?= htmlspecialchars($_POST['pincode'] ?? '') ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" class="form-control" id="country" name="country"
                               value="<?= htmlspecialchars($_POST['country'] ?? 'India') ?>">
                    </div>
                </div>
            </div>

            <!-- Platform Limits -->
            <div class="form-card">
                <div class="section-header">
                    <h4><i class="fas fa-cogs me-2 text-primary"></i>Platform Limits</h4>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="max_groups" class="form-label">Maximum Groups</label>
                        <input type="number" class="form-control" id="max_groups" name="max_groups"
                               value="<?= htmlspecialchars($_POST['max_groups'] ?? '10') ?>" min="1" max="1000">
                        <div class="form-text">Maximum number of BC groups this client can create</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="max_members_per_group" class="form-label">Maximum Members per Group</label>
                        <input type="number" class="form-control" id="max_members_per_group" name="max_members_per_group"
                               value="<?= htmlspecialchars($_POST['max_members_per_group'] ?? '50') ?>" min="1" max="500">
                        <div class="form-text">Maximum number of members allowed in each group</div>
                    </div>
                </div>
            </div>

            <!-- Admin Account (Optional) -->
            <div class="form-card">
                <div class="section-header">
                    <h4><i class="fas fa-user-shield me-2 text-primary"></i>Admin Account (Optional)</h4>
                    <p class="text-muted mb-0">Create an admin account for this client. If left empty, you can create it later.</p>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="admin_username" class="form-label">Admin Username</label>
                        <input type="text" class="form-control" id="admin_username" name="admin_username"
                               value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>">
                        <div class="form-text">Minimum 3 characters</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="admin_password" class="form-label">Admin Password</label>
                        <input type="password" class="form-control" id="admin_password" name="admin_password">
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="text-center">
                <button type="submit" class="btn btn-primary me-3">
                    <i class="fas fa-save me-2"></i>Create Client
                </button>
                <a href="super_admin_clients.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
