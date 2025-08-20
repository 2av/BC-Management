<?php
/**
 * Redirect file for admin_bidding.php
 * This file redirects to the actual bidding.php file
 */

// Get the group_id parameter if it exists
$groupId = $_GET['group_id'] ?? '';
$queryString = '';

if ($groupId) {
    $queryString = '?group_id=' . urlencode($groupId);
}

// Redirect to the actual bidding page
header('Location: bidding.php' . $queryString);
exit;
?>
