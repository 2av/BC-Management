<?php
require_once 'config.php';
requireAdminLogin();

$message = '';
$error = '';

// Handle migration execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    $pdo = getDB();
    
    try {
        $pdo->beginTransaction();
        
        // Get the Family BC Group ID
        $stmt = $pdo->prepare("SELECT id FROM bc_groups WHERE group_name = 'Family BC Group' LIMIT 1");
        $stmt->execute();
        $group = $stmt->fetch();
        
        if (!$group) {
            throw new Exception("Family BC Group not found");
        }
        
        $groupId = $group['id'];
        
        // Update bidding status for completed months
        $stmt = $pdo->prepare("
            UPDATE month_bidding_status 
            SET bidding_status = 'completed', admin_approved_at = NOW()
            WHERE group_id = ? AND month_number IN (1, 2, 3, 4)
        ");
        $stmt->execute([$groupId]);
        
        // Historical data based on your screenshot
        $historicalData = [
            1 => ['name' => 'Akhilesh Vishwakarma', 'bid' => 0, 'date' => '2025-04-25', 'net' => 18000],
            2 => ['name' => 'Ghanshyam Vishwakarma', 'bid' => 1000, 'date' => '2025-05-25', 'net' => 17000],
            3 => ['name' => 'Mohanish Patil', 'bid' => 800, 'date' => '2025-06-25', 'net' => 17200],
            4 => ['name' => 'Vishal Vishwakarma', 'bid' => 800, 'date' => '2025-07-25', 'net' => 17200]
        ];
        
        foreach ($historicalData as $month => $data) {
            // Get member ID
            $stmt = $pdo->prepare("SELECT id FROM members WHERE group_id = ? AND member_name = ?");
            $stmt->execute([$groupId, $data['name']]);
            $member = $stmt->fetch();
            
            if ($member) {
                $memberId = $member['id'];
                
                // Update month_bidding_status
                $stmt = $pdo->prepare("
                    UPDATE month_bidding_status 
                    SET winner_member_id = ?, winning_bid_amount = ?, 
                        bidding_status = 'completed', admin_approved_at = ?
                    WHERE group_id = ? AND month_number = ?
                ");
                $stmt->execute([$memberId, $data['bid'], $data['date'] . ' 10:00:00', $groupId, $month]);
                
                // Update member won status
                $stmt = $pdo->prepare("
                    UPDATE members 
                    SET has_won_month = ?, won_amount = ?
                    WHERE id = ?
                ");
                $stmt->execute([$month, $data['net'], $memberId]);
                
                // Create historical bid record
                $adminNotes = $data['bid'] == 0 ? 
                    "Historical winner - Month $month (Direct allocation, no bidding)" :
                    "Historical winner - Month $month (Winning bid: ₹" . number_format($data['bid']) . ")";
                
                $stmt = $pdo->prepare("
                    INSERT INTO member_bids (group_id, member_id, month_number, bid_amount, bid_status, bid_date, admin_notes)
                    VALUES (?, ?, ?, ?, 'approved', ?, ?)
                    ON DUPLICATE KEY UPDATE
                    bid_status = 'approved', admin_notes = VALUES(admin_notes)
                ");
                $stmt->execute([$groupId, $memberId, $month, $data['bid'], $data['date'] . ' 10:00:00', $adminNotes]);
            }
        }
        
        $pdo->commit();
        $message = "Historical data migration completed successfully!";
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Migration failed: " . $e->getMessage();
    }
}

// Get current status
function getMigrationStatus() {
    $pdo = getDB();
    
    // Get group info
    $stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE group_name = 'Family BC Group' LIMIT 1");
    $stmt->execute();
    $group = $stmt->fetch();
    
    if (!$group) {
        return ['error' => 'Family BC Group not found'];
    }
    
    // Get bidding status
    $stmt = $pdo->prepare("
        SELECT mbs.*, m.member_name as winner_name
        FROM month_bidding_status mbs
        LEFT JOIN members m ON mbs.winner_member_id = m.id
        WHERE mbs.group_id = ? AND mbs.month_number <= 4
        ORDER BY mbs.month_number
    ");
    $stmt->execute([$group['id']]);
    $biddingStatus = $stmt->fetchAll();
    
    // Get member won status
    $stmt = $pdo->prepare("
        SELECT member_name, member_number, has_won_month, won_amount
        FROM members 
        WHERE group_id = ? AND has_won_month IS NOT NULL
        ORDER BY has_won_month
    ");
    $stmt->execute([$group['id']]);
    $wonMembers = $stmt->fetchAll();
    
    // Get historical bids
    $stmt = $pdo->prepare("
        SELECT mb.*, m.member_name
        FROM member_bids mb
        JOIN members m ON mb.member_id = m.id
        WHERE mb.group_id = ?
        ORDER BY mb.month_number
    ");
    $stmt->execute([$group['id']]);
    $historicalBids = $stmt->fetchAll();
    
    return [
        'group' => $group,
        'bidding_status' => $biddingStatus,
        'won_members' => $wonMembers,
        'historical_bids' => $historicalBids
    ];
}

$status = getMigrationStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migrate Historical Bid Data - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-handshake text-warning me-2"></i><?= APP_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-database text-primary me-2"></i>Historical Bid Data Migration</h2>
                <p class="text-muted">Migrate your existing 4 months of bid winners to the new bidding system</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($status['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($status['error']) ?>
            </div>
        <?php else: ?>
            <!-- Migration Data Preview -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-eye"></i> Data to be Migrated</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Winner</th>
                                    <th>Bid Amount</th>
                                    <th>Net Amount</th>
                                    <th>Payment Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Month 1</td>
                                    <td>Akhilesh Vishwakarma</td>
                                    <td>₹0 (No bid)</td>
                                    <td>₹18,000</td>
                                    <td>25/04/2025</td>
                                </tr>
                                <tr>
                                    <td>Month 2</td>
                                    <td>Ghanshyam Vishwakarma</td>
                                    <td>₹1,000</td>
                                    <td>₹17,000</td>
                                    <td>25/05/2025</td>
                                </tr>
                                <tr>
                                    <td>Month 3</td>
                                    <td>Mohanish Patil</td>
                                    <td>₹800</td>
                                    <td>₹17,200</td>
                                    <td>25/06/2025</td>
                                </tr>
                                <tr>
                                    <td>Month 4</td>
                                    <td>Vishal Vishwakarma</td>
                                    <td>₹800</td>
                                    <td>₹17,200</td>
                                    <td>25/07/2025</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Current Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Current Migration Status</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($status['won_members'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> No historical data found. Migration is needed.
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="run_migration" class="btn btn-primary" 
                                    onclick="return confirm('Are you sure you want to migrate the historical bid data? This will update the database.')">
                                <i class="fas fa-play"></i> Run Migration
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Historical data has been migrated successfully!
                        </div>
                        
                        <h6>Migrated Winners:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Won Month</th>
                                        <th>Amount Received</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($status['won_members'] as $member): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($member['member_name']) ?> (#<?= $member['member_number'] ?>)</td>
                                            <td>Month <?= $member['has_won_month'] ?></td>
                                            <td>₹<?= number_format($member['won_amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-forward"></i> Next Steps</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li><strong>Verify Migration:</strong> Check that all 4 winners are correctly recorded</li>
                        <li><strong>Open Month 5 Bidding:</strong> Go to <a href="admin_bidding.php?group_id=<?= $status['group']['id'] ?>">Bidding Management</a></li>
                        <li><strong>Set Bidding Deadline:</strong> Allow remaining members to place bids</li>
                        <li><strong>Monitor Bids:</strong> Watch as members submit their bids</li>
                        <li><strong>Select Winner:</strong> Choose the winner for Month 5</li>
                    </ol>
                    
                    <div class="mt-3">
                        <a href="admin_bidding.php?group_id=<?= $status['group']['id'] ?>" class="btn btn-success">
                            <i class="fas fa-gavel"></i> Go to Bidding Management
                        </a>
                        <a href="view_group.php?id=<?= $status['group']['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Group Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
