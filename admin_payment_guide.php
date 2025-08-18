<?php
require_once 'config.php';
requireAdminLogin();

// Get current payment configuration
$configs = getPaymentConfig();
$upiId = !empty($configs['upi_id']) ? $configs['upi_id'] : '9768985225kotak@ybl';
$qrEnabled = ($configs['qr_enabled'] ?? '1') == '1';
$qrImageExists = file_exists('QRCode.jpeg');

// Get some stats
$pdo = getDB();
$totalGroups = $pdo->query("SELECT COUNT(*) FROM bc_groups")->fetchColumn();
$activeGroups = $pdo->query("SELECT COUNT(*) FROM bc_groups WHERE status = 'active'")->fetchColumn();
$totalPayments = $pdo->query("SELECT COUNT(*) FROM member_payments")->fetchColumn();
$paidPayments = $pdo->query("SELECT COUNT(*) FROM member_payments WHERE payment_status = 'paid'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management Guide - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-map"></i> Payment Management Guide
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Current Status Overview -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <h5><?= $totalGroups ?></h5>
                                        <small>Total Groups</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-play fa-2x mb-2"></i>
                                        <h5><?= $activeGroups ?></h5>
                                        <small>Active Groups</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-credit-card fa-2x mb-2"></i>
                                        <h5><?= $totalPayments ?></h5>
                                        <small>Total Payments</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <h5><?= $paidPayments ?></h5>
                                        <small>Paid Payments</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment System Status -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-status"></i> Payment System Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-qrcode fa-2x me-3 <?= $qrEnabled ? 'text-success' : 'text-danger' ?>"></i>
                                            <div>
                                                <h6 class="mb-0">QR Code Payments</h6>
                                                <span class="badge bg-<?= $qrEnabled ? 'success' : 'danger' ?>">
                                                    <?= $qrEnabled ? 'Enabled' : 'Disabled' ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-image fa-2x me-3 <?= $qrImageExists ? 'text-success' : 'text-warning' ?>"></i>
                                            <div>
                                                <h6 class="mb-0">QR Code Image</h6>
                                                <span class="badge bg-<?= $qrImageExists ? 'success' : 'warning' ?>">
                                                    <?= $qrImageExists ? 'QRCode.jpeg Found' : 'QRCode.jpeg Missing' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-mobile-alt fa-2x me-3 text-info"></i>
                                            <div>
                                                <h6 class="mb-0">UPI ID</h6>
                                                <code><?= htmlspecialchars($upiId) ?></code>
                                            </div>
                                        </div>
                                        
                                        <?php if ($qrImageExists): ?>
                                            <div class="text-center">
                                                <img src="QRCode.jpeg" alt="QR Code" style="max-width: 100px; border: 1px solid #ddd; border-radius: 5px;">
                                                <br><small class="text-muted">Current QR Code</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Navigation Guide -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h5 class="mb-0"><i class="fas fa-cog"></i> Configuration & Setup</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <a href="admin_payment_config.php" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><i class="fas fa-qrcode text-primary"></i> QR Code Settings</h6>
                                                    <small class="text-muted">Configure</small>
                                                </div>
                                                <p class="mb-1">Set UPI ID, payee name, and enable/disable QR payments</p>
                                                <small class="text-muted">Main configuration for payment system</small>
                                            </a>
                                            
                                            <a href="test_qr_image_setup.php" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><i class="fas fa-test-tube text-info"></i> Test QR Setup</h6>
                                                    <small class="text-muted">Test</small>
                                                </div>
                                                <p class="mb-1">Verify QR code image and UPI configuration</p>
                                                <small class="text-muted">Check if QRCode.jpeg file exists and works</small>
                                            </a>
                                            
                                            <a href="test_qr_payment.php" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><i class="fas fa-vial text-success"></i> Test Payment System</h6>
                                                    <small class="text-muted">Test</small>
                                                </div>
                                                <p class="mb-1">Complete payment system functionality test</p>
                                                <small class="text-muted">Test QR generation, UPI URLs, and member interface</small>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="fas fa-tasks"></i> Management & Monitoring</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group list-group-flush">
                                            <a href="admin_payment_status.php" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><i class="fas fa-list-check text-warning"></i> Payment Status</h6>
                                                    <small class="text-muted">Manage</small>
                                                </div>
                                                <p class="mb-1">View and update member payment status</p>
                                                <small class="text-muted">Mark payments as paid/pending/failed</small>
                                            </a>
                                            
                                            <a href="admin_manage_groups.php" class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><i class="fas fa-users text-primary"></i> Manage Groups</h6>
                                                    <small class="text-muted">Groups</small>
                                                </div>
                                                <p class="mb-1">Group management with restart options</p>
                                                <small class="text-muted">Clone completed groups, manage members</small>
                                            </a>
                                            
                                            <a href="member_payment.php?month=1" class="list-group-item list-group-item-action" target="_blank">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><i class="fas fa-eye text-info"></i> Member View</h6>
                                                    <small class="text-muted">Preview</small>
                                                </div>
                                                <p class="mb-1">See what members see when making payments</p>
                                                <small class="text-muted">Preview member payment interface</small>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Access Buttons -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Access</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <a href="admin_payment_config.php" class="btn btn-primary w-100">
                                            <i class="fas fa-qrcode"></i><br>
                                            <strong>QR Settings</strong><br>
                                            <small>Configure UPI & QR</small>
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <a href="admin_payment_status.php" class="btn btn-warning w-100">
                                            <i class="fas fa-list-check"></i><br>
                                            <strong>Payment Status</strong><br>
                                            <small>Manage Payments</small>
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <a href="test_qr_image_setup.php" class="btn btn-info w-100">
                                            <i class="fas fa-test-tube"></i><br>
                                            <strong>Test System</strong><br>
                                            <small>Verify Setup</small>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Setup Instructions -->
                        <?php if (!$qrImageExists || !$qrEnabled): ?>
                            <div class="alert alert-warning mt-4">
                                <h5><i class="fas fa-exclamation-triangle"></i> Setup Required</h5>
                                <?php if (!$qrImageExists): ?>
                                    <p><strong>Missing QR Code Image:</strong> Please add QRCode.jpeg file to the main directory.</p>
                                <?php endif; ?>
                                <?php if (!$qrEnabled): ?>
                                    <p><strong>QR Payments Disabled:</strong> Enable QR payments in <a href="admin_payment_config.php">QR Settings</a>.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
