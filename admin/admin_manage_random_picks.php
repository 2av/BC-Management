<?php
/**
 * Redirect file for admin_manage_random_picks.php
 * This file redirects to the actual manage_random_picks.php file
 */

// Get the group_id parameter if it exists
$groupId = $_GET['group_id'] ?? '';
$queryString = '';

if ($groupId) {
    $queryString = '?group_id=' . urlencode($groupId);
}

// Redirect to the actual manage random picks page
header('Location: manage_random_picks.php' . $queryString);
exit;
?>
