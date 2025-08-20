<?php
require_once '../config/config.php';
require_once '../common/middleware.php';
require_once '../common/qr_utils.php';
checkRole('admin');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $configs = [
        'upi_id' => trim($_POST['upi_id'] ?? ''),
        'bank_account_name' => trim($_POST['bank_account_name'] ?? ''), // Used as payee name in UPI
        'payment_note' => trim($_POST['payment_note'] ?? ''),
        'qr_enabled' => isset($_POST['qr_enabled']) ? '1' : '0'
    ];
    
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        foreach ($configs as $key => $value) {
            updatePaymentConfig($key, $value);
        }
        
        $pdo->commit();
        $success = 'Payment configuration updated successfully!';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to update payment configuration: ' . $e->getMessage();
    }
}

// Get current configuration
$currentConfigs = getPaymentConfig();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Configuration - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-cog"></i> Payment Configuration
                        </h4>
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
                            <!-- QR Code Settings -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-qrcode"></i> QR Code Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="qr_enabled" name="qr_enabled" 
                                               <?= ($currentConfigs['qr_enabled'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="qr_enabled">
                                            <strong>Enable QR Code Payments</strong>
                                        </label>
                                        <div class="form-text">Allow members to scan QR codes for payments</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- UPI Settings -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-mobile-alt"></i> UPI Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="upi_id" class="form-label">UPI ID *</label>
                                        <input type="text" class="form-control" id="upi_id" name="upi_id"
                                               value="<?= htmlspecialchars($currentConfigs['upi_id'] ?? '9768985225kotak@ybl') ?>"
                                               placeholder="9768985225kotak@ybl" required>
                                        <div class="form-text">Your UPI ID for receiving payments (Default: 9768985225kotak@ybl)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="payment_note" class="form-label">Default Payment Note</label>
                                        <input type="text" class="form-control" id="payment_note" name="payment_note" 
                                               value="<?= htmlspecialchars($currentConfigs['payment_note'] ?? 'BC Group Monthly Payment') ?>" 
                                               placeholder="BC Group Monthly Payment">
                                        <div class="form-text">Default description for payments</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payee Details -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-user"></i> Payee Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="bank_account_name" class="form-label">Payee Name *</label>
                                        <input type="text" class="form-control" id="bank_account_name" name="bank_account_name"
                                               value="<?= htmlspecialchars($currentConfigs['bank_account_name'] ?? '') ?>"
                                               placeholder="John Doe" required>
                                        <div class="form-text">This name will appear as the payee in UPI transactions</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Preview Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-eye"></i> UPI Payment Preview</h5>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <strong>When members scan the QR code, they will see:</strong><br>
                                        • <strong>Payee:</strong> <span id="preview_payee"><?= htmlspecialchars($currentConfigs['bank_account_name'] ?? 'BC Group Admin') ?></span><br>
                                        • <strong>UPI ID:</strong> <span id="preview_upi"><?= htmlspecialchars($currentConfigs['upi_id'] ?? '9768985225kotak@ybl') ?></span><br>
                                        • <strong>Amount:</strong> [Dynamic based on member's payment]<br>
                                        • <strong>Note:</strong> <span id="preview_note"><?= htmlspecialchars($currentConfigs['payment_note'] ?? 'BC Group Monthly Payment') ?></span> - [Group Name] - [Month] - [Member Name]
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Test QR Code Section -->
                <?php if (($currentConfigs['qr_enabled'] ?? '1') == '1' && !empty($currentConfigs['upi_id'])): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-test-tube"></i> Test QR Code</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php
                            $testUpiUrl = generateUpiPaymentUrl(100, 'Test Member', 'Test Group', 1);
                            $testQrUrl = generateQrCodeUrl($testUpiUrl);
                            ?>
                            <p>Test QR code for ₹100 payment:</p>
                            <img src="<?= htmlspecialchars($testQrUrl) ?>" alt="Test QR Code" style="max-width: 200px;">
                            <br><br>
                            <small class="text-muted">This is how the QR code will appear to members</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update preview in real-time
        document.addEventListener('DOMContentLoaded', function() {
            const fields = {
                'bank_account_name': 'preview_payee',
                'upi_id': 'preview_upi',
                'payment_note': 'preview_note'
            };

            Object.keys(fields).forEach(fieldId => {
                const field = document.getElementById(fieldId);
                const preview = document.getElementById(fields[fieldId]);

                if (field && preview) {
                    field.addEventListener('input', function() {
                        preview.textContent = this.value || this.placeholder;
                    });
                }
            });
        });
    </script>
</body>
</html>
