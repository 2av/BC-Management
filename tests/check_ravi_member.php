<?php
/**
 * Script to check member "ravi" data and password
 * Access via: http://localhost/BC-Management/tests/check_ravi_member.php
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Ravi Member Data</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .error { color: red; }
        .success { color: green; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .test-section { margin: 20px 0; padding: 15px; background: #e8f5e9; border-radius: 5px; }
    </style>
</head>
<body>
<h2>Checking Member 'ravi' Data</h2>
<?php
try {
    $pdo = getDB();
    
    echo "<div class='success'>✓ Database Connection: OK</div>";
    echo "<pre>";
    echo "Database: " . DB_NAME . "\n";
    echo "Host: " . DB_HOST . "\n";
    echo "User: " . DB_USER . "\n\n";
    echo "</pre>";
    
    // Search for member with username or name containing "ravi"
    $stmt = $pdo->prepare("
        SELECT m.*, 
               g.group_name, 
               g.client_id, 
               c.client_name, 
               gm.group_id, 
               gm.member_number,
               gm.status as group_member_status
        FROM members m
        LEFT JOIN group_members gm ON m.id = gm.member_id
        LEFT JOIN bc_groups g ON gm.group_id = g.id
        LEFT JOIN clients c ON g.client_id = c.id
        WHERE m.username LIKE ? OR m.member_name LIKE ? OR LOWER(m.member_name) LIKE ?
        ORDER BY m.id DESC
        LIMIT 10
    ");
    
    $searchTerm = '%ravi%';
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $members = $stmt->fetchAll();
    
    if (empty($members)) {
        echo "<div class='error'>No member found with 'ravi' in username or name.</div>";
        echo "<h3>First 20 Members in Database:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Member Name</th><th>Status</th></tr>";
        $stmt = $pdo->query("SELECT id, username, member_name, status FROM members LIMIT 20");
        $allMembers = $stmt->fetchAll();
        foreach ($allMembers as $m) {
            echo "<tr>";
            echo "<td>{$m['id']}</td>";
            echo "<td>" . htmlspecialchars($m['username'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($m['member_name'] ?? 'NULL') . "</td>";
            echo "<td>{$m['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='success'>Found " . count($members) . " member(s) matching 'ravi':</div>";
        
        foreach ($members as $member) {
            echo "<div class='test-section'>";
            echo "<h3>Member Details:</h3>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>Member ID</td><td>{$member['id']}</td></tr>";
            echo "<tr><td>Username</td><td>" . htmlspecialchars($member['username'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>Member Name</td><td>" . htmlspecialchars($member['member_name'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>Status</td><td>{$member['status']}</td></tr>";
            echo "<tr><td>Password Hash</td><td><code>" . htmlspecialchars(substr($member['password'] ?? 'NULL', 0, 60)) . "...</code></td></tr>";
            echo "<tr><td>Password Hash Length</td><td>" . (isset($member['password']) ? strlen($member['password']) : 0) . "</td></tr>";
            echo "<tr><td>Group ID</td><td>" . ($member['group_id'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>Group Name</td><td>" . htmlspecialchars($member['group_name'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>Group Member Status</td><td>" . ($member['group_member_status'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>Client ID</td><td>" . ($member['client_id'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>Client Name</td><td>" . htmlspecialchars($member['client_name'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td>Member Number</td><td>" . ($member['member_number'] ?? 'NULL') . "</td></tr>";
            echo "</table>";
            
            // Test password verification with common passwords
            if (!empty($member['password'])) {
                echo "<h4>Password Test:</h4>";
                $testPasswords = ['ravi', 'Ravi', 'RAVI', 'password', '123456', 'ravi123', 'Ravi123'];
                echo "<table>";
                echo "<tr><th>Test Password</th><th>Result</th></tr>";
                foreach ($testPasswords as $testPwd) {
                    $result = password_verify($testPwd, $member['password']) ? '<span class="success">✓ MATCH</span>' : '<span class="error">✗ No match</span>';
                    echo "<tr><td>" . htmlspecialchars($testPwd) . "</td><td>{$result}</td></tr>";
                }
                echo "</table>";
            }
            echo "</div>";
        }
        
        // Test the login function
        if (!empty($members)) {
            $ravi = $members[0];
            echo "<div class='test-section'>";
            echo "<h3>Testing Login Query:</h3>";
            echo "<p>Testing with username: <strong>" . htmlspecialchars($ravi['username'] ?? 'N/A') . "</strong></p>";
            
            // Check if member can be found by the login query
            $loginStmt = $pdo->prepare("
                SELECT m.*, g.group_name, g.client_id, c.client_name, gm.group_id, gm.member_number
                FROM members m
                JOIN group_members gm ON m.id = gm.member_id AND gm.status = 'active'
                JOIN bc_groups g ON gm.group_id = g.id
                JOIN clients c ON g.client_id = c.id
                WHERE m.username = ? AND m.status = 'active' AND c.status = 'active'
            ");
            $loginStmt->execute([$ravi['username'] ?? '']);
            $loginMember = $loginStmt->fetch();
            
            if ($loginMember) {
                echo "<div class='success'>✓ Member found by login query</div>";
                echo "<ul>";
                echo "<li>Member Status: " . $loginMember['status'] . "</li>";
                echo "<li>Group Member Status: active</li>";
                echo "<li>Client Status: active</li>";
                echo "</ul>";
                
                // Now test actual login function
                echo "<h4>Testing memberLogin() Function:</h4>";
                $testPasswords = ['ravi', 'Ravi', 'RAVI', 'password', '123456', 'ravi123', 'Ravi123'];
                echo "<table>";
                echo "<tr><th>Test Password</th><th>Login Result</th></tr>";
                foreach ($testPasswords as $testPwd) {
                    // Start session if not started
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $loginResult = memberLogin($ravi['username'], $testPwd);
                    $result = $loginResult ? '<span class="success">✓ LOGIN SUCCESS</span>' : '<span class="error">✗ Login failed</span>';
                    echo "<tr><td>" . htmlspecialchars($testPwd) . "</td><td>{$result}</td></tr>";
                    // Clear session after each test
                    if ($loginResult) {
                        session_destroy();
                        session_start();
                    }
                }
                echo "</table>";
            } else {
                echo "<div class='error'>✗ Member NOT found by login query</div>";
                echo "<p>Possible reasons:</p>";
                echo "<ul>";
                echo "<li>Member status is not 'active' (Current: " . ($ravi['status'] ?? 'NULL') . ")</li>";
                echo "<li>Group member status is not 'active' (Current: " . ($ravi['group_member_status'] ?? 'NULL') . ")</li>";
                echo "<li>Client status is not 'active'</li>";
                echo "<li>No active group_members record</li>";
                echo "<li>No bc_groups record</li>";
                echo "<li>No clients record</li>";
                echo "</ul>";
            }
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
</body>
</html>
