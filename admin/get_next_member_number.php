<?php
require_once 'config.php';
requireAdminLogin();

header('Content-Type: application/json');

$groupId = (int)($_GET['group_id'] ?? 0);

if ($groupId <= 0) {
    echo json_encode(['error' => 'Invalid group ID']);
    exit;
}

try {
    $pdo = getDB();
    
    // Get total members in group
    $stmt = $pdo->prepare("SELECT total_members FROM bc_groups WHERE id = ?");
    $stmt->execute([$groupId]);
    $totalMembers = $stmt->fetchColumn();
    
    if (!$totalMembers) {
        echo json_encode(['error' => 'Group not found']);
        exit;
    }
    
    // Get existing member numbers
    $stmt = $pdo->prepare("SELECT member_number FROM members WHERE group_id = ? ORDER BY member_number");
    $stmt->execute([$groupId]);
    $existingNumbers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Find next available number
    $nextNumber = 1;
    for ($i = 1; $i <= $totalMembers; $i++) {
        if (!in_array($i, $existingNumbers)) {
            $nextNumber = $i;
            break;
        }
    }
    
    echo json_encode([
        'next_number' => $nextNumber,
        'total_members' => $totalMembers,
        'existing_numbers' => $existingNumbers
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to get member numbers']);
}
?>
