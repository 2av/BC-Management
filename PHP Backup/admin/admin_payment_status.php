<?php
/**
 * Redirect file for admin_payment_status.php
 * This file redirects to the actual payment_status.php file
 */

// Get the group_id parameter if it exists
$groupId = $_GET['group_id'] ?? '';
$queryString = '';

if ($groupId) {
    $queryString = '?group_id=' . urlencode($groupId);
}

// Redirect to the actual payment status page
header('Location: payment_status.php' . $queryString);
exit;
?>
