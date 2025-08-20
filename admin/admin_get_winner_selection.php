<?php
/**
 * Redirect file for admin_get_winner_selection.php
 * This file redirects to the actual get_winner_selection.php file
 */

// Get all query parameters
$queryString = $_SERVER['QUERY_STRING'] ?? '';

// Redirect to the actual get winner selection page
header('Location: get_winner_selection.php' . ($queryString ? '?' . $queryString : ''));
exit;
?>
