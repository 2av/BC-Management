<?php
require_once 'config.php';
requireAdminLogin();

$memberName = $_GET['member_name'] ?? '';
$groupIds = $_GET['group_ids'] ?? '';

if (!$memberName) {
    echo '<div class="alert alert-danger">Member name is required.</div>';
    exit;
}

try {
    $pdo = getDB();
    
    // Get detailed member information across all groups
    $groupIdArray = explode(',', $groupIds);
    $placeholders = str_repeat('?,', count($groupIdArray) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT
            m.*,
            g.group_name,
            g.monthly_contribution,
            g.total_members,
            g.status as group_status,
            gm.member_number,
            gm.joined_date,
            gm.status as assignment_status,
            COALESCE(ms.total_paid, 0) as total_paid,
            COALESCE(ms.given_amount, 0) as given_amount,
            COALESCE(ms.profit, 0) as profit
        FROM members m
        JOIN group_members gm ON m.id = gm.member_id
        JOIN bc_groups g ON gm.group_id = g.id
        LEFT JOIN member_summary ms ON m.id = ms.member_id AND g.id = ms.group_id
        WHERE m.member_name = ? AND gm.group_id IN ($placeholders) AND gm.status = 'active'
        ORDER BY g.group_name
    ");
    
    $params = array_merge([$memberName], $groupIdArray);
    $stmt->execute($params);
    $memberDetails = $stmt->fetchAll();
    
    if (empty($memberDetails)) {
        echo '<div class="alert alert-warning">No member details found.</div>';
        exit;
    }
    
    $firstMember = $memberDetails[0];
    
    echo '<div class="row">';
    
    // Member Summary Card
    echo '<div class="col-md-4">';
    echo '<div class="card mb-3">';
    echo '<div class="card-header bg-primary text-white">';
    echo '<h6 class="mb-0"><i class="fas fa-user"></i> Member Summary</h6>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="text-center mb-3">';
    echo '<div class="member-avatar mx-auto mb-2" style="width: 60px; height: 60px; font-size: 24px;">';
    echo strtoupper(substr($firstMember['member_name'], 0, 1));
    echo '</div>';
    echo '<h5>' . htmlspecialchars($firstMember['member_name']) . '</h5>';
    echo '</div>';
    
    echo '<table class="table table-sm">';
    echo '<tr><td><strong>Username:</strong></td><td><code>' . htmlspecialchars($firstMember['username'] ?: 'Not Set') . '</code></td></tr>';
    echo '<tr><td><strong>Phone:</strong></td><td>' . htmlspecialchars($firstMember['phone'] ?: 'Not Set') . '</td></tr>';
    echo '<tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($firstMember['email'] ?: 'Not Set') . '</td></tr>';
    echo '<tr><td><strong>Status:</strong></td><td><span class="badge bg-' . ($firstMember['status'] === 'active' ? 'success' : 'secondary') . '">' . ucfirst($firstMember['status']) . '</span></td></tr>';
    echo '<tr><td><strong>Joined:</strong></td><td>' . date('M d, Y', strtotime($firstMember['created_at'])) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Group Details
    echo '<div class="col-md-8">';
    echo '<div class="card">';
    echo '<div class="card-header bg-info text-white">';
    echo '<h6 class="mb-0"><i class="fas fa-layer-group"></i> Group Memberships (' . count($memberDetails) . ')</h6>';
    echo '</div>';
    echo '<div class="card-body">';
    
    $totalPaid = 0;
    $totalGiven = 0;
    $totalProfit = 0;
    
    foreach ($memberDetails as $detail) {
        $totalPaid += $detail['total_paid'];
        $totalGiven += $detail['given_amount'];
        $totalProfit += $detail['profit'];
        
        echo '<div class="border rounded p-3 mb-3">';
        echo '<div class="d-flex justify-content-between align-items-start">';
        echo '<div>';
        echo '<h6 class="mb-1">' . htmlspecialchars($detail['group_name']) . '</h6>';
        echo '<small class="text-muted">Member #' . $detail['member_number'] . ' | Monthly: ₹' . number_format($detail['monthly_contribution']) . '</small>';
        echo '</div>';
        echo '<span class="badge bg-' . ($detail['group_status'] === 'active' ? 'success' : 'secondary') . '">' . ucfirst($detail['group_status']) . '</span>';
        echo '</div>';
        
        echo '<div class="row mt-2">';
        echo '<div class="col-4">';
        echo '<small class="text-muted">Total Paid</small><br>';
        echo '<strong>₹' . number_format($detail['total_paid']) . '</strong>';
        echo '</div>';
        echo '<div class="col-4">';
        echo '<small class="text-muted">Given Amount</small><br>';
        echo '<strong>₹' . number_format($detail['given_amount']) . '</strong>';
        echo '</div>';
        echo '<div class="col-4">';
        echo '<small class="text-muted">Profit/Loss</small><br>';
        echo '<strong class="' . ($detail['profit'] >= 0 ? 'text-success' : 'text-danger') . '">₹' . number_format($detail['profit']) . '</strong>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="mt-2">';
        echo '<a href="view_group.php?id=' . $detail['group_id'] . '" class="btn btn-sm btn-outline-primary me-2" target="_blank">';
        echo '<i class="fas fa-eye"></i> View Group';
        echo '</a>';
        echo '<a href="manage_members.php?group_id=' . $detail['group_id'] . '" class="btn btn-sm btn-outline-info" target="_blank">';
        echo '<i class="fas fa-users-cog"></i> Manage';
        echo '</a>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    
    // Overall Summary
    echo '<div class="row mt-3">';
    echo '<div class="col-12">';
    echo '<div class="card bg-light">';
    echo '<div class="card-header">';
    echo '<h6 class="mb-0"><i class="fas fa-chart-line"></i> Overall Summary Across All Groups</h6>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="row text-center">';
    echo '<div class="col-md-3">';
    echo '<h4 class="text-primary">' . count($memberDetails) . '</h4>';
    echo '<small class="text-muted">Total Groups</small>';
    echo '</div>';
    echo '<div class="col-md-3">';
    echo '<h4 class="text-info">₹' . number_format($totalPaid) . '</h4>';
    echo '<small class="text-muted">Total Paid</small>';
    echo '</div>';
    echo '<div class="col-md-3">';
    echo '<h4 class="text-warning">₹' . number_format($totalGiven) . '</h4>';
    echo '<small class="text-muted">Total Given</small>';
    echo '</div>';
    echo '<div class="col-md-3">';
    echo '<h4 class="' . ($totalProfit >= 0 ? 'text-success' : 'text-danger') . '">₹' . number_format($totalProfit) . '</h4>';
    echo '<small class="text-muted">Net Profit/Loss</small>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading member details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
