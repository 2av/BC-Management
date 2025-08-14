<?php
require_once 'config.php';

// Quick login for testing
if (memberLogin('akhilesh', 'member123')) {
    echo "Login successful! Redirecting to dashboard...";
    echo "<script>window.location.href = 'member_dashboard.php';</script>";
} else {
    echo "Login failed!";
}
?>
