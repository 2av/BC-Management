<?php
require_once 'config/config.php';

// Get database connection
$pdo = getDB();

echo "<h2>Database Diagnostic Report</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
    .error { color: red; }
    .success { color: green; }
</style>";

// 1. Check member information
echo "<div class='section'>";
echo "<h3>1. Member Information</h3>";
$stmt = $pdo->query("SELECT id, member_name, username, group_id FROM members WHERE username = 'akhilesh' OR member_name LIKE '%Akhilesh%'");
$members = $stmt->fetchAll();

if ($members) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Username</th><th>Group ID</th></tr>";
    foreach ($members as $member) {
        echo "<tr>";
        echo "<td>{$member['id']}</td>";
        echo "<td>{$member['member_name']}</td>";
        echo "<td>{$member['username']}</td>";
        echo "<td>{$member['group_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>No member found with username 'akhilesh'</p>";
}
echo "</div>";

// 2. Check groups for this member
echo "<div class='section'>";
echo "<h3>2. Groups for Member</h3>";
if ($members) {
    $memberName = $members[0]['member_name'];
    $stmt = $pdo->prepare("
        SELECT DISTINCT g.*, m.id as member_id, m.member_number 
        FROM bc_groups g
        JOIN members m ON g.id = m.group_id
        WHERE m.member_name = ?
    ");
    $stmt->execute([$memberName]);
    $groups = $stmt->fetchAll();
    
    if ($groups) {
        echo "<table>";
        echo "<tr><th>Group ID</th><th>Group Name</th><th>Member ID in Group</th><th>Member Number</th></tr>";
        foreach ($groups as $group) {
            echo "<tr>";
            echo "<td>{$group['id']}</td>";
            echo "<td>{$group['group_name']}</td>";
            echo "<td>{$group['member_id']}</td>";
            echo "<td>{$group['member_number']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>No groups found for member: $memberName</p>";
    }
}
echo "</div>";

// 3. Check member_payments table structure and data
echo "<div class='section'>";
echo "<h3>3. Member Payments Table</h3>";

// Check if table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'member_payments'");
if ($stmt->fetch()) {
    echo "<p class='success'>✓ member_payments table exists</p>";
    
    // Show table structure
    echo "<h4>Table Structure:</h4>";
    $stmt = $pdo->query("DESCRIBE member_payments");
    $columns = $stmt->fetchAll();
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check total records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM member_payments");
    $count = $stmt->fetchColumn();
    echo "<p>Total payment records: <strong>$count</strong></p>";
    
    // Check records for our member
    if ($members && $groups) {
        foreach ($groups as $group) {
            $memberId = $group['member_id'];
            $groupId = $group['id'];
            
            echo "<h4>Payments for Member ID $memberId in Group $groupId ({$group['group_name']}):</h4>";
            $stmt = $pdo->prepare("
                SELECT * FROM member_payments 
                WHERE member_id = ? AND group_id = ?
                ORDER BY month_number
            ");
            $stmt->execute([$memberId, $groupId]);
            $payments = $stmt->fetchAll();
            
            if ($payments) {
                echo "<table>";
                echo "<tr><th>Month</th><th>Amount</th><th>Status</th><th>Date</th></tr>";
                foreach ($payments as $payment) {
                    echo "<tr>";
                    echo "<td>{$payment['month_number']}</td>";
                    echo "<td>₹{$payment['payment_amount']}</td>";
                    echo "<td>{$payment['payment_status']}</td>";
                    echo "<td>{$payment['payment_date']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='error'>No payment records found for Member ID $memberId in Group $groupId</p>";
            }
        }
    }
} else {
    echo "<p class='error'>✗ member_payments table does not exist</p>";
}
echo "</div>";

// 4. Check monthly_bids table
echo "<div class='section'>";
echo "<h3>4. Monthly Bids Table</h3>";

$stmt = $pdo->query("SHOW TABLES LIKE 'monthly_bids'");
if ($stmt->fetch()) {
    echo "<p class='success'>✓ monthly_bids table exists</p>";
    
    // Check total records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM monthly_bids");
    $count = $stmt->fetchColumn();
    echo "<p>Total bid records: <strong>$count</strong></p>";
    
    // Check records for our member
    if ($members && $groups) {
        foreach ($groups as $group) {
            $memberId = $group['member_id'];
            $groupId = $group['id'];
            
            echo "<h4>Bids won by Member ID $memberId in Group $groupId ({$group['group_name']}):</h4>";
            $stmt = $pdo->prepare("
                SELECT * FROM monthly_bids 
                WHERE group_id = ? AND taken_by_member_id = ?
                ORDER BY month_number
            ");
            $stmt->execute([$groupId, $memberId]);
            $bids = $stmt->fetchAll();
            
            if ($bids) {
                echo "<table>";
                echo "<tr><th>Month</th><th>Bid Amount</th><th>Net Payable</th><th>Date</th></tr>";
                foreach ($bids as $bid) {
                    echo "<tr>";
                    echo "<td>{$bid['month_number']}</td>";
                    echo "<td>₹{$bid['bid_amount']}</td>";
                    echo "<td>₹{$bid['net_payable']}</td>";
                    echo "<td>{$bid['payment_date']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='error'>No bid wins found for Member ID $memberId in Group $groupId</p>";
            }
        }
    }
} else {
    echo "<p class='error'>✗ monthly_bids table does not exist</p>";
}
echo "</div>";

// 5. Sample data from both tables
echo "<div class='section'>";
echo "<h3>5. Sample Data (First 5 records)</h3>";

echo "<h4>Sample member_payments:</h4>";
$stmt = $pdo->query("SELECT mp.*, m.member_name FROM member_payments mp LEFT JOIN members m ON mp.member_id = m.id LIMIT 5");
$samplePayments = $stmt->fetchAll();
if ($samplePayments) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Group ID</th><th>Member ID</th><th>Member Name</th><th>Month</th><th>Amount</th><th>Status</th></tr>";
    foreach ($samplePayments as $payment) {
        echo "<tr>";
        echo "<td>{$payment['id']}</td>";
        echo "<td>{$payment['group_id']}</td>";
        echo "<td>{$payment['member_id']}</td>";
        echo "<td>{$payment['member_name']}</td>";
        echo "<td>{$payment['month_number']}</td>";
        echo "<td>₹{$payment['payment_amount']}</td>";
        echo "<td>{$payment['payment_status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No payment records found</p>";
}

echo "<h4>Sample monthly_bids:</h4>";
$stmt = $pdo->query("SELECT mb.*, m.member_name FROM monthly_bids mb LEFT JOIN members m ON mb.taken_by_member_id = m.id LIMIT 5");
$sampleBids = $stmt->fetchAll();
if ($sampleBids) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Group ID</th><th>Month</th><th>Member ID</th><th>Member Name</th><th>Bid Amount</th><th>Net Payable</th></tr>";
    foreach ($sampleBids as $bid) {
        echo "<tr>";
        echo "<td>{$bid['id']}</td>";
        echo "<td>{$bid['group_id']}</td>";
        echo "<td>{$bid['month_number']}</td>";
        echo "<td>{$bid['taken_by_member_id']}</td>";
        echo "<td>{$bid['member_name']}</td>";
        echo "<td>₹{$bid['bid_amount']}</td>";
        echo "<td>₹{$bid['net_payable']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No bid records found</p>";
}
echo "</div>";

echo "<p><strong>Diagnostic completed!</strong></p>";
?>
