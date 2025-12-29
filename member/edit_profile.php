<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
checkRole('member');

if (isset($_GET['logout'])) {
    logout();
}

$member = getCurrentMember();
$error = '';
$success = '';

/**
 * Ensure optional profile columns exist on members table.
 * Older databases might miss address/updated_at, which would break updates.
 */
function ensureMemberProfileColumns(PDO $pdo): array {
    static $status = null;
    if ($status !== null) {
        return $status;
    }

    $hasAddress = false;
    $hasUpdatedAt = false;

    try {
        $columnsStmt = $pdo->query("SHOW COLUMNS FROM members");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

        $hasAddress = in_array('address', $columns, true);
        $hasUpdatedAt = in_array('updated_at', $columns, true);

        if (!$hasAddress) {
            try {
                $pdo->exec("ALTER TABLE members ADD COLUMN address TEXT NULL AFTER email");
                $hasAddress = true;
            } catch (Exception $ignored) {
                // Column might already exist or DB user may not have privileges.
            }
        }
        if (!$hasUpdatedAt) {
            try {
                $pdo->exec("ALTER TABLE members ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
                $hasUpdatedAt = true;
            } catch (Exception $ignored) {
                // Ignore failures; we'll avoid referencing the column later.
            }
        }
    } catch (Exception $ignored) {
        // Unable to inspect schema, we'll fall back to defaults below.
    }

    $status = [
        'address' => $hasAddress,
        'updated_at' => $hasUpdatedAt
    ];

    return $status;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberName = trim($_POST['member_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    if (empty($memberName)) {
        $error = 'Member name is required.';
    } elseif ($phone && !preg_match('/^[0-9]{10}$/', $phone)) {
        $error = 'Phone number must be 10 digits.';
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getDB();
            $columnStatus = ensureMemberProfileColumns($pdo);
            $hasAddressColumn = $columnStatus['address'];
            $hasUpdatedAtColumn = $columnStatus['updated_at'];
            
            // Check if member name already exists for other members (excluding current member's records across all groups)
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT member_name) as count
                FROM members
                WHERE member_name = ?
                AND member_name != ?
            ");
            $stmt->execute([$memberName, $member['member_name']]);
            $result = $stmt->fetch();

            if ($result['count'] > 0) {
                $error = 'A different member with this name already exists.';
            } else {
                $pdo->beginTransaction();

                try {
                    // Update member profile for current record
                    $setParts = [
                        'member_name = ?',
                        'phone = ?',
                        'email = ?'
                    ];
                    $params = [$memberName, $phone ?: null, $email ?: null];

                    if ($hasAddressColumn) {
                        $setParts[] = 'address = ?';
                        $params[] = $address ?: null;
                    }
                    if ($hasUpdatedAtColumn) {
                        $setParts[] = 'updated_at = NOW()';
                    }

                    $params[] = $member['id'];
                    $sql = "UPDATE members SET " . implode(', ', $setParts) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // If name changed, update all records with the old name to the new name
                    if ($memberName !== $member['member_name']) {
                        $nameUpdateParts = [
                            'member_name = ?',
                            'phone = ?',
                            'email = ?'
                        ];
                        $nameParams = [$memberName, $phone ?: null, $email ?: null];

                        if ($hasAddressColumn) {
                            $nameUpdateParts[] = 'address = ?';
                            $nameParams[] = $address ?: null;
                        }
                        if ($hasUpdatedAtColumn) {
                            $nameUpdateParts[] = 'updated_at = NOW()';
                        }

                        $nameParams[] = $member['member_name'];
                        $nameSql = "UPDATE members SET " . implode(', ', $nameUpdateParts) . " WHERE member_name = ?";
                        $stmt = $pdo->prepare($nameSql);
                        $stmt->execute($nameParams);

                        // Update session
                        $_SESSION['member_name'] = $memberName;
                    }

                    $pdo->commit();
                    $success = 'Profile updated successfully across all your group memberships!';

                    // Refresh member data
                    $member = getCurrentMember();

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            
        } catch (Exception $e) {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

// Set page title for the header
$page_title = 'Edit Profile';

// Include the member header
require_once 'includes/header.php';
?>

<!-- Page-specific CSS -->
<style>
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
            margin: 0 auto;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
    </style>
<!-- Page content starts here -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Profile Header -->
                <div class="card profile-card mb-4">
                    <div class="card-body text-center p-4">
                        <div class="member-avatar mb-3">
                            <?= strtoupper(substr($member['member_name'], 0, 1)) ?>
                        </div>
                        <h3 class="mb-1"><?= htmlspecialchars($member['member_name']) ?></h3>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-user"></i> Member Profile
                        </p>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-edit"></i> Edit Profile Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="member_name" class="form-label">
                                            <i class="fas fa-user"></i> Full Name *
                                        </label>
                                        <input type="text" class="form-control" id="member_name" name="member_name" 
                                               value="<?= htmlspecialchars($member['member_name']) ?>" required>
                                        <div class="form-text">This name will be used across all your group memberships.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">
                                            <i class="fas fa-phone"></i> Phone Number
                                        </label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($member['phone'] ?? '') ?>" 
                                               pattern="[0-9]{10}" maxlength="10">
                                        <div class="form-text">Enter 10-digit mobile number without country code.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope"></i> Email Address
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($member['email'] ?? '') ?>">
                                        <div class="form-text">We'll use this for important notifications.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user-tag"></i> Username
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?= htmlspecialchars($member['username'] ?? '') ?>" readonly>
                                        <div class="form-text">Username cannot be changed. Contact admin if needed.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="address" class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($member['address'] ?? '') ?></textarea>
                                <div class="form-text">Your residential address for communication purposes.</div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle"></i> Account Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Member Since:</strong> <?= date('F d, Y', strtotime($member['created_at'])) ?></p>
                                <p><strong>Account Status:</strong> 
                                    <span class="badge bg-<?= $member['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($member['status']) ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Last Updated:</strong> 
                                    <?= $member['updated_at'] ? date('F d, Y g:i A', strtotime($member['updated_at'])) : 'Never' ?>
                                </p>
                                <p><strong>Member ID:</strong> #<?= $member['id'] ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="change_password.php" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-key"></i> Change Password
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.slice(0, 10);
            }
            e.target.value = value;
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const email = document.getElementById('email').value;
            
            if (phone && phone.length !== 10) {
                e.preventDefault();
                alert('Phone number must be exactly 10 digits.');
                return;
            }
            
            if (email && !email.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
        });
    </script>

<?php require_once 'includes/footer.php'; ?>
