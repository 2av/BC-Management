<?php
require_once 'config/config.php';
require_once 'common/functions.php';

// Debug: Check what groups are returned for member ID 1
$memberId = 1;
$memberGroups = getMemberGroups($memberId);

echo "<h3>Groups for Member ID: $memberId</h3>";
echo "<p>Total groups found: " . count($memberGroups) . "</p>";
echo "<pre>";
print_r($memberGroups);
echo "</pre>";

// Also check raw database query
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT gm.*, g.group_name 
    FROM group_members gm 
    JOIN bc_groups g ON gm.group_id = g.id 
    WHERE gm.member_id = ? 
    ORDER BY g.group_name
");
$stmt->execute([$memberId]);
$rawData = $stmt->fetchAll();

echo "<h3>Raw Database Query Results:</h3>";
echo "<p>Total raw records: " . count($rawData) . "</p>";
echo "<pre>";
print_r($rawData);
echo "</pre>";

// Check for duplicates in group_members table
$stmt2 = $pdo->prepare("
    SELECT group_id, COUNT(*) as count 
    FROM group_members 
    WHERE member_id = ? 
    GROUP BY group_id 
    HAVING COUNT(*) > 1
");
$stmt2->execute([$memberId]);
$duplicates = $stmt2->fetchAll();

echo "<h3>Duplicate Group Memberships:</h3>";
echo "<pre>";
print_r($duplicates);
echo "</pre>";

// Test the exact rendering logic from dashboard
echo "<h3>Simulated Dashboard Rendering:</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
foreach ($memberGroups as $group) {
    echo "<div style='border: 1px solid #ddd; margin: 5px; padding: 10px; background: #f9f9f9;'>";
    echo "<strong>Group ID:</strong> " . $group['id'] . "<br>";
    echo "<strong>Group Name:</strong> " . htmlspecialchars($group['group_name']) . "<br>";
    echo "<strong>Members:</strong> " . $group['total_members'] . "<br>";
    echo "<strong>Monthly:</strong> ₹" . number_format($group['monthly_contribution']) . "/month<br>";
    echo "</div>";
}
echo "</div>";
?>
