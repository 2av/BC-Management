<?php
require_once 'config.php';

// Simulate member login for testing
$_SESSION['member_id'] = 1; // Akhilesh's ID in the first group
$_SESSION['member_name'] = 'Akhilesh Vishwakarma';
$_SESSION['group_id'] = 1;
$_SESSION['group_name'] = 'Family BC Group';
$_SESSION['user_type'] = 'member';

echo "<h2>Testing Member Dashboard Data:</h2>";

$member = getCurrentMember();
echo "<p>Current Member: " . $member['member_name'] . "</p>";

// Get all groups where this member name exists
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT DISTINCT g.*, m.id as member_id, m.member_number, m.status as member_status, m.created_at as member_joined_date
    FROM bc_groups g 
    JOIN members m ON g.id = m.group_id 
    WHERE m.member_name = ? AND m.status = 'active'
    ORDER BY g.start_date DESC
");
$stmt->execute([$member['member_name']]);
$allMemberGroups = $stmt->fetchAll();

echo "<h3>Groups where " . $member['member_name'] . " is a member:</h3>";
foreach ($allMemberGroups as $group) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Group:</strong> " . $group['group_name'] . "<br>";
    echo "<strong>Member #:</strong> " . $group['member_number'] . "<br>";
    echo "<strong>Monthly Amount:</strong> ₹" . number_format($group['monthly_contribution']) . "<br>";
    echo "<strong>Start Date:</strong> " . $group['start_date'] . "<br>";
    echo "<strong>Total Members:</strong> " . $group['total_members'] . "<br>";
    echo "<strong>Status:</strong> " . $group['status'] . "<br>";
    
    // Calculate estimated end date
    $startDate = new DateTime($group['start_date']);
    $estimatedEndDate = clone $startDate;
    $estimatedEndDate->add(new DateInterval('P' . ($group['total_members'] - 1) . 'M'));
    echo "<strong>Estimated End Date:</strong> " . $estimatedEndDate->format('d/m/Y') . "<br>";
    echo "</div>";
}

echo "<p><a href='member_dashboard.php'>Go to Member Dashboard</a></p>";
?>
