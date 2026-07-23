<?php
require_once 'config/config.php';

$groupId = 7;

echo "=== DEBUGGING MEMBERS ISSUE ===\n";

// Check current client
$clientId = getCurrentClientId();
echo "Current client ID: " . ($clientId ?? 'NULL') . "\n";

// Check if group exists
$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM bc_groups WHERE id = ?");
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if ($group) {
    echo "Group found: " . $group['group_name'] . "\n";
    echo "Group client_id: " . ($group['client_id'] ?? 'NULL') . "\n";
} else {
    echo "Group not found!\n";
    exit;
}

// Check members without client filtering
echo "\nMembers without client filtering:\n";
$stmt = $pdo->prepare("
    SELECT m.*, gm.member_number, gm.joined_date, gm.status as assignment_status
    FROM group_members gm
    JOIN members m ON gm.member_id = m.id
    WHERE gm.group_id = ? AND gm.status = 'active'
    ORDER BY gm.member_number
");
$stmt->execute([$groupId]);
$allMembers = $stmt->fetchAll();

echo "Found " . count($allMembers) . " members:\n";
foreach ($allMembers as $member) {
    echo "- ID: " . $member['id'] . " | Name: " . $member['member_name'] . " | Number: " . $member['member_number'] . "\n";
}

// Check members with client filtering (like getGroupMembers does)
echo "\nMembers with client filtering:\n";
$stmt = $pdo->prepare("
    SELECT m.*, gm.member_number, gm.joined_date, gm.status as assignment_status
    FROM group_members gm
    JOIN members m ON gm.member_id = m.id
    JOIN bc_groups g ON gm.group_id = g.id
    WHERE gm.group_id = ? AND g.client_id = ? AND gm.status = 'active'
    ORDER BY gm.member_number
");
$stmt->execute([$groupId, $clientId]);
$filteredMembers = $stmt->fetchAll();

echo "Found " . count($filteredMembers) . " members with client filtering:\n";
foreach ($filteredMembers as $member) {
    echo "- ID: " . $member['id'] . " | Name: " . $member['member_name'] . " | Number: " . $member['member_number'] . "\n";
}

// Test getGroupMembers function
echo "\nUsing getGroupMembers function:\n";
$members = getGroupMembers($groupId);
echo "Found " . count($members) . " members:\n";
foreach ($members as $member) {
    echo "- ID: " . $member['id'] . " | Name: " . $member['member_name'] . " | Number: " . $member['member_number'] . "\n";
}
?>
