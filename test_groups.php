<?php
require_once 'config.php';

echo "<h2>Current Groups in Database:</h2>";
$groups = getAllGroups();
foreach ($groups as $group) {
    echo "<p>Group ID: {$group['id']}, Name: {$group['group_name']}, Members: {$group['total_members']}</p>";
}

echo "<h2>Current Members:</h2>";
$pdo = getDB();
$stmt = $pdo->query("SELECT m.*, g.group_name FROM members m JOIN bc_groups g ON m.group_id = g.id ORDER BY m.member_name");
$members = $stmt->fetchAll();
foreach ($members as $member) {
    echo "<p>Member: {$member['member_name']}, Group: {$member['group_name']}, Member #: {$member['member_number']}</p>";
}

// Let's create a second group and add Akhilesh to it for testing
echo "<h2>Creating Test Scenario:</h2>";

try {
    // Create a second group
    $stmt = $pdo->prepare("INSERT INTO bc_groups (group_name, total_members, monthly_contribution, total_monthly_collection, start_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Business BC Group', 8, 3000.00, 24000.00, '2025-06-01']);
    $newGroupId = $pdo->lastInsertId();
    echo "<p>✅ Created new group: Business BC Group (ID: $newGroupId)</p>";
    
    // Add Akhilesh to the new group
    $stmt = $pdo->prepare("INSERT INTO members (group_id, member_name, member_number, username, password, phone, email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $hashedPassword = password_hash('member123', PASSWORD_DEFAULT);
    $stmt->execute([$newGroupId, 'Akhilesh Vishwakarma', 1, 'akhilesh_business', $hashedPassword, '9876543210', 'akhilesh.business@example.com', 'active']);
    echo "<p>✅ Added Akhilesh to Business BC Group</p>";
    
    // Add some other members to the new group
    $businessMembers = [
        'Rajesh Kumar',
        'Priya Sharma', 
        'Amit Patel',
        'Sunita Gupta',
        'Vikash Singh',
        'Neha Agarwal',
        'Rohit Verma'
    ];
    
    foreach ($businessMembers as $index => $memberName) {
        $username = strtolower(str_replace(' ', '', $memberName)) . '_biz';
        $stmt->execute([$newGroupId, $memberName, $index + 2, $username, $hashedPassword, '987654321' . ($index + 1), strtolower(str_replace(' ', '.', $memberName)) . '@business.com', 'active']);
    }
    echo "<p>✅ Added " . count($businessMembers) . " additional members to Business BC Group</p>";
    
    // Create some sample payments for the new group
    $stmt = $pdo->prepare("INSERT INTO member_payments (group_id, member_id, month_number, payment_amount, payment_status, payment_date) VALUES (?, ?, ?, ?, ?, ?)");
    
    // Get the new member ID for Akhilesh in business group
    $stmt2 = $pdo->prepare("SELECT id FROM members WHERE group_id = ? AND member_name = ?");
    $stmt2->execute([$newGroupId, 'Akhilesh Vishwakarma']);
    $akhileshBusinessId = $stmt2->fetchColumn();
    
    // Add some payments for Akhilesh in business group
    for ($month = 1; $month <= 3; $month++) {
        $stmt->execute([$newGroupId, $akhileshBusinessId, $month, 3000, 'paid', '2025-0' . (5 + $month) . '-05']);
    }
    echo "<p>✅ Added sample payments for Akhilesh in Business BC Group</p>";
    
    echo "<p><strong>Test scenario created successfully! Now Akhilesh is in both groups.</strong></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
