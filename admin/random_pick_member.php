<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in (either admin or member)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$groupId = (int)($_POST['group_id'] ?? 0);
$monthNumber = (int)($_POST['month_number'] ?? 0);

if (!$groupId || !$monthNumber) {
    echo json_encode(['success' => false, 'message' => 'Group ID and Month Number are required']);
    exit;
}

// Check if this is the current active month
$currentActiveMonth = getCurrentActiveMonthNumber($groupId);
if (!$currentActiveMonth || $monthNumber != $currentActiveMonth) {
    echo json_encode(['success' => false, 'message' => 'Random picks are only allowed for the current active month (' . ($currentActiveMonth ?: 'none') . ').']);
    exit;
}

try {
    $pdo = getDB();
    
    // Verify group exists
    $group = getGroupById($groupId);
    if (!$group) {
        echo json_encode(['success' => false, 'message' => 'Group not found']);
        exit;
    }
    
    // Check if this month already has a winner (either from monthly_bids or random_picks)
    $stmt = $pdo->prepare("SELECT taken_by_member_id FROM monthly_bids WHERE group_id = ? AND month_number = ?");
    $stmt->execute([$groupId, $monthNumber]);
    $existingBid = $stmt->fetch();
    
    if ($existingBid && $existingBid['taken_by_member_id']) {
        echo json_encode(['success' => false, 'message' => 'This month already has a winner']);
        exit;
    }
    
    // Check if random pick already done for this month
    $stmt = $pdo->prepare("SELECT * FROM random_picks WHERE group_id = ? AND month_number = ?");
    $stmt->execute([$groupId, $monthNumber]);
    $existingPick = $stmt->fetch();
    
    if ($existingPick) {
        echo json_encode(['success' => false, 'message' => 'Random pick already done for this month']);
        exit;
    }
    
    // Get all group members who haven't won yet
    $availableMembers = getAvailableMembersForRandomPick($groupId);
    
    if (empty($availableMembers)) {
        echo json_encode(['success' => false, 'message' => 'No members available for random selection']);
        exit;
    }
    
    // Randomly select a member
    $selectedMember = $availableMembers[array_rand($availableMembers)];
    
    // Get current user info for logging
    $pickedBy = null;
    $pickedByType = null;
    if (isset($_SESSION['admin_id'])) {
        $pickedBy = $_SESSION['admin_id'];
        $pickedByType = 'admin';
    } elseif (isset($_SESSION['member_id'])) {
        $pickedBy = $_SESSION['member_id'];
        $pickedByType = 'member';
    }
    
    // Save the random pick to database
    $stmt = $pdo->prepare("
        INSERT INTO random_picks (group_id, month_number, selected_member_id, picked_by, picked_by_type, picked_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    if ($stmt->execute([$groupId, $monthNumber, $selectedMember['id'], $pickedBy, $pickedByType])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Random pick successful!',
            'selected_member' => [
                'id' => $selectedMember['id'],
                'name' => $selectedMember['member_name'],
                'number' => $selectedMember['member_number']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save random pick']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
