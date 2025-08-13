<?php
require_once 'config.php';
requireAdminLogin();

$message = '';
$error = '';

// Handle opening Month 5 bidding
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_month5'])) {
    $endDate = $_POST['end_date'];
    $minBidAmount = (float)$_POST['min_bid_amount'];
    $maxBidAmount = (float)$_POST['max_bid_amount'];

    if (empty($endDate)) {
        $error = "Please select an end date for bidding.";
    } elseif ($minBidAmount < 0) {
        $error = "Minimum bid amount cannot be negative.";
    } elseif ($maxBidAmount <= $minBidAmount) {
        $error = "Maximum bid amount must be greater than minimum bid amount.";
    } else {
        try {
            $pdo = getDB();

            // Get the Family BC Group ID
            $stmt = $pdo->prepare("SELECT id FROM bc_groups WHERE group_name = 'Family BC Group' LIMIT 1");
            $stmt->execute();
            $group = $stmt->fetch();

            if (!$group) {
                throw new Exception("Family BC Group not found");
            }

            $groupId = $group['id'];

            // Open bidding for Month 5
            $stmt = $pdo->prepare("
                UPDATE month_bidding_status
                SET bidding_status = 'open',
                    bidding_start_date = CURDATE(),
                    bidding_end_date = ?,
                    minimum_bid_amount = ?,
                    maximum_bid_amount = ?
                WHERE group_id = ? AND month_number = 5
            ");

            if ($stmt->execute([$endDate, $minBidAmount, $maxBidAmount, $groupId])) {
                $message = "Month 5 bidding has been opened successfully! Members can place bids between ₹" . number_format($minBidAmount) . " - ₹" . number_format($maxBidAmount) . " until " . date('d/m/Y', strtotime($endDate));
            } else {
                $error = "Failed to open bidding for Month 5.";
            }

        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get current status of Month 5
function getMonth5Status() {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("SELECT id FROM bc_groups WHERE group_name = 'Family BC Group' LIMIT 1");
    $stmt->execute();
    $group = $stmt->fetch();
    
    if (!$group) {
        return ['error' => 'Family BC Group not found'];
    }
    
    $stmt = $pdo->prepare("
        SELECT mbs.*, 
               (SELECT COUNT(*) FROM member_bids mb WHERE mb.group_id = mbs.group_id AND mb.month_number = 5) as total_bids
        FROM month_bidding_status mbs 
        WHERE mbs.group_id = ? AND mbs.month_number = 5
    ");
    $stmt->execute([$group['id']]);
    $month5Status = $stmt->fetch();
    
    return [
        'group' => $group,
        'month5' => $month5Status
    ];
}

$status = getMonth5Status();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Month 5 Bidding - <?= APP_NAME ?></title>
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
                <h2><i class="fas fa-play text-success me-2"></i>Open Month 5 Bidding</h2>
                <p class="text-muted">Start the bidding process for Month 5 of Family BC Group</p>
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
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-calendar-alt"></i> Month 5 Bidding Status</h5>
                </div>
                <div class="card-body">
                    <?php if ($status['month5']): ?>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Current Status:</strong> 
                                <span class="badge bg-<?= $status['month5']['bidding_status'] === 'open' ? 'success' : ($status['month5']['bidding_status'] === 'not_started' ? 'secondary' : 'warning') ?>">
                                    <?= ucfirst(str_replace('_', ' ', $status['month5']['bidding_status'])) ?>
                                </span>
                            </div>
                            <div class="col-md-6">
                                <strong>Total Bids:</strong> <?= $status['month5']['total_bids'] ?>
                            </div>
                        </div>

                        <?php if ($status['month5']['bidding_start_date']): ?>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <strong>Started:</strong> <?= date('d/m/Y', strtotime($status['month5']['bidding_start_date'])) ?>
                                </div>
                                <?php if ($status['month5']['bidding_end_date']): ?>
                                    <div class="col-md-6">
                                        <strong>Ends:</strong> <?= date('d/m/Y', strtotime($status['month5']['bidding_end_date'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($status['month5']['bidding_status'] === 'not_started'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Month 5 bidding has not been started yet. You can open it now.
                            </div>

                            <form method="POST" class="mt-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="end_date" class="form-label">
                                                <i class="fas fa-calendar-times"></i> Bidding End Date
                                            </label>
                                            <input type="date" class="form-control" id="end_date" name="end_date"
                                                   min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                                   value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                                            <small class="form-text text-muted">
                                                Members will be able to place bids until this date.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="min_bid_amount" class="form-label">
                                                <i class="fas fa-arrow-down"></i> Minimum Bid (₹)
                                            </label>
                                            <input type="number" class="form-control" id="min_bid_amount" name="min_bid_amount"
                                                   min="0" step="1" value="500" required>
                                            <small class="form-text text-muted">
                                                Lowest amount members can bid.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="max_bid_amount" class="form-label">
                                                <i class="fas fa-arrow-up"></i> Maximum Bid (₹)
                                            </label>
                                            <input type="number" class="form-control" id="max_bid_amount" name="max_bid_amount"
                                                   min="1" step="1" value="17999" required>
                                            <small class="form-text text-muted">
                                                Highest amount members can bid.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> <strong>Bidding Guidelines:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li><strong>Minimum Bid:</strong> Prevents unrealistically low bids</li>
                                        <li><strong>Maximum Bid:</strong> Usually set just below total collection amount</li>
                                        <li><strong>Winner Selection:</strong> Lowest bidder typically wins</li>
                                        <li><strong>Payout:</strong> Winner receives Total Collection - Bid Amount</li>
                                    </ul>
                                </div>

                                <div class="text-center">
                                    <button type="submit" name="open_month5" class="btn btn-success btn-lg">
                                        <i class="fas fa-play"></i> Open Month 5 Bidding
                                    </button>
                                </div>
                            </form>
                        <?php elseif ($status['month5']['bidding_status'] === 'open'): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Month 5 bidding is currently open! Members can place bids.
                            </div>

                            <div class="mt-4">
                                <h6>Quick Actions:</h6>
                                <a href="admin_bidding.php?group_id=<?= $status['group']['id'] ?>" class="btn btn-primary me-2">
                                    <i class="fas fa-cog"></i> Manage Bidding
                                </a>
                                <a href="member_bidding.php" class="btn btn-info me-2">
                                    <i class="fas fa-eye"></i> View Member Portal
                                </a>
                                <a href="view_group.php?id=<?= $status['group']['id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-users"></i> View Group
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> Month 5 bidding status: <?= ucfirst(str_replace('_', ' ', $status['month5']['bidding_status'])) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Month 5 bidding status not found. Please run the migration first.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instructions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> How It Works</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li><strong>Open Bidding:</strong> Set an end date and open Month 5 for bidding</li>
                        <li><strong>Members Bid:</strong> Eligible members can place their bids through the member portal</li>
                        <li><strong>Monitor Progress:</strong> Watch as members submit their bids</li>
                        <li><strong>Close Bidding:</strong> When ready, close the bidding period</li>
                        <li><strong>Select Winner:</strong> Choose the winner (usually lowest bidder)</li>
                        <li><strong>Complete Month:</strong> Approve the winner and move to next month</li>
                    </ol>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> 
                        After opening bidding, members will see Month 5 prominently displayed in their bidding portal 
                        with a special highlight and easy bid submission form.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
