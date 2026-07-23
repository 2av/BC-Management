<?php
require_once '../config/config.php';

echo "<h2>Session Debug Information</h2>";
echo "<pre>";
echo "Session Status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data:\n";
print_r($_SESSION);
echo "\nAuthentication Status:\n";
echo "isMemberLoggedIn(): " . (isMemberLoggedIn() ? 'true' : 'false') . "\n";
echo "isAdminLoggedIn(): " . (isAdminLoggedIn() ? 'true' : 'false') . "\n";
echo "isClientAdminLoggedIn(): " . (isClientAdminLoggedIn() ? 'true' : 'false') . "\n";
echo "isSuperAdminLoggedIn(): " . (isSuperAdminLoggedIn() ? 'true' : 'false') . "\n";
echo "</pre>";
?>
