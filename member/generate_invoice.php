<?php
require_once '../config/config.php';
require_once '../common/middleware.php';

// Check if user is logged in (member, admin, or super admin)
$isLoggedIn = isMemberLoggedIn() || isAdminLoggedIn() || isClientAdminLoggedIn() || isSuperAdminLoggedIn();

if (!$isLoggedIn) {
    // Try to get session from URL parameters as fallback
    $sessionId = $_GET['session_id'] ?? '';
    if ($sessionId) {
        session_id($sessionId);
        session_start();
        $isLoggedIn = isMemberLoggedIn() || isAdminLoggedIn() || isClientAdminLoggedIn() || isSuperAdminLoggedIn();
    }
    
    if (!$isLoggedIn) {
        // Redirect to appropriate login page
        if (isset($_GET['admin']) && $_GET['admin'] == '1') {
            header("Location: ../auth/login.php");
        } else {
            header("Location: ../auth/member_login.php");
        }
        exit;
    }
}

$memberId = (int)($_GET['member_id'] ?? 0);
$groupId = (int)($_GET['group_id'] ?? 0);

if (!$memberId || !$groupId) {
    http_response_code(400);
    die('Invalid parameters');
}

// Get current member and verify access
$currentMember = getCurrentMember();
$hasAccess = false;

// Check access based on user type
if (isMemberLoggedIn()) {
    // For members, check if they have access to this group
    $memberGroups = getMemberGroups($currentMember['id']);
    foreach ($memberGroups as $memberGroup) {
        if ($memberGroup['id'] == $groupId) {
            $hasAccess = true;
            break;
        }
    }
} elseif (isAdminLoggedIn() || isClientAdminLoggedIn() || isSuperAdminLoggedIn()) {
    // For admins, check if they have access to this group's client
    $hasAccess = checkGroupAccess($groupId);
}

if (!$hasAccess) {
    http_response_code(403);
    die('Access denied');
}

// Get group and member data
$group = getGroupById($groupId);
$member = getMemberById($memberId);
$memberPayments = getMemberPayments($groupId);
$monthlyBids = getMonthlyBids($groupId);
$memberSummary = getMemberSummary($groupId);

// Filter payments for this specific member
$memberPayments = array_filter($memberPayments, function($payment) use ($memberId) {
    return $payment['member_id'] == $memberId;
});

// Get member summary
$memberSummaryData = null;
foreach ($memberSummary as $summary) {
    if ($summary['member_id'] == $memberId) {
        $memberSummaryData = $summary;
        break;
    }
}

// Set headers for HTML that can be printed as PDF
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="Invoice_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $group['group_name']) . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $member['member_name']) . '.html"');

// Generate HTML content for the invoice
$html = generateInvoiceHTML($group, $member, $memberPayments, $monthlyBids, $memberSummaryData);

echo $html;

function generateInvoiceHTML($group, $member, $memberPayments, $monthlyBids, $memberSummary) {
    $currentDate = date('d/m/Y');
    $invoiceNumber = 'INV-' . $group['id'] . '-' . $member['id'] . '-' . date('Ymd');
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Transaction Invoice</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body { 
                font-family: "Inter", "Helvetica Neue", Arial, sans-serif; 
                margin: 0;
                padding: 20px;
                background: #f5f7fa;
                min-height: 100vh;
                line-height: 1.6;
            }
            
            .invoice-container {
                max-width: 900px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                overflow: hidden;
            }
            
            .header { 
                background: #1a365d;
                color: white;
                padding: 40px 50px;
                position: relative;
            }
            
            .header-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 30px;
            }
            
            .logo-section h1 {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 5px;
            }
            
            .logo-section p {
                font-size: 14px;
                opacity: 0.8;
            }
            
            .invoice-meta {
                text-align: right;
            }
            
            .invoice-number {
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 5px;
            }
            
            .invoice-date {
                font-size: 14px;
                opacity: 0.8;
            }
            
            .header-bottom {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 40px;
                margin-top: 30px;
            }
            
            .company-info, .client-info {
                background: rgba(255,255,255,0.1);
                padding: 20px;
                border-radius: 6px;
            }
            
            .company-info h3, .client-info h3 {
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 15px;
                color: #e2e8f0;
            }
            
            .info-line {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
                font-size: 14px;
            }
            
            .info-label {
                opacity: 0.8;
            }
            
            .info-value {
                font-weight: 500;
            }
            
            .content {
                padding: 50px;
            }
            
            .section-title { 
                font-size: 18px; 
                font-weight: 600; 
                color: #2d3748; 
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e2e8f0;
            }
            
            .transactions-table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 30px;
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            
            .transactions-table th { 
                background: #f7fafc;
                color: #4a5568;
                padding: 14px 16px;
                text-align: left;
                font-weight: 600;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 2px solid #e2e8f0;
            }
            
            .transactions-table th:nth-child(2),
            .transactions-table th:nth-child(3) {
                text-align: right;
            }
            
            .transactions-table td { 
                padding: 14px 16px;
                border-bottom: 1px solid #f1f5f9;
                font-size: 13px;
                color: #4a5568;
                vertical-align: middle;
            }
            
            .transactions-table td:nth-child(2),
            .transactions-table td:nth-child(3) {
                text-align: right;
            }
            
            .transactions-table tr:hover {
                background-color: rgba(0, 0, 0, 0.05) !important;
                transform: translateY(-1px);
                transition: all 0.2s ease;
            }
            
            .amount { 
                text-align: right; 
                font-weight: 600; 
                font-size: 13px;
            }
            
            .positive { color: #38a169; }
            .negative { color: #e53e3e; }
            
            /* Payment Status Colors */
            .paid { 
                color: #38a169; 
                font-weight: 600; 
            }
            
            .unpaid { 
                color: #d69e2e; 
                font-weight: 600; 
            }
            
            .pending { 
                color: #3182ce; 
                font-weight: 600; 
            }
            
            .completed { 
                color: #38a169; 
                font-weight: 600; 
            }
            
            .failed { 
                color: #e53e3e; 
                font-weight: 600; 
            }
            
            /* Status Column Specific Styles */
            .status-paid {
                color: #38a169;
                font-weight: 600;
                font-size: 12px;
            }
            
            .status-pending {
                color: #d69e2e;
                font-weight: 600;
                font-size: 12px;
            }
            
            .status-completed {
                color: #38a169;
                font-weight: 600;
                font-size: 12px;
            }
            
            .status-failed {
                color: #e53e3e;
                font-weight: 600;
                font-size: 12px;
            }
            
            /* Row Background Colors for Status */
            .row-paid {
                background-color: rgba(56, 161, 105, 0.05);
            }
            
            .row-unpaid {
                background-color: rgba(214, 158, 46, 0.05);
            }
            
            .row-pending {
                background-color: rgba(49, 130, 206, 0.05);
            }
            
            .row-failed {
                background-color: rgba(229, 62, 62, 0.05);
            }
            
            .legend {
                background: #f7fafc;
                padding: 16px 20px;
                border-radius: 6px;
                margin-bottom: 25px;
                border: 1px solid #e2e8f0;
            }
            
            .legend-items {
                display: flex;
                gap: 30px;
                flex-wrap: wrap;
            }
            
            .legend-item {
                display: flex;
                align-items: center;
                font-size: 13px;
                font-weight: 500;
                color: #4a5568;
            }
            
            .legend-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                margin-right: 8px;
            }
            
            .summary-section {
                background: #f7fafc;
                padding: 30px;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
            }
            
            .summary-row { 
                display: flex; 
                justify-content: space-between; 
                align-items: center;
                margin-bottom: 12px;
                padding: 8px 0;
                font-size: 14px;
            }
            
            .summary-row:last-child {
                border-top: 2px solid #e2e8f0;
                padding-top: 15px;
                margin-top: 15px;
                font-size: 16px;
                font-weight: 600;
            }
            
            .summary-label {
                color: #4a5568;
            }
            
            .summary-value {
                color: #2d3748;
                font-weight: 600;
            }
            
            .summary-total {
                font-size: 18px;
                font-weight: 700;
                color: #1a365d;
            }
            
            .footer { 
                background: #2d3748;
                color: #a0aec0;
                padding: 30px 50px;
                text-align: center;
                font-size: 13px;
                line-height: 1.6;
            }
            
            .footer p {
                margin-bottom: 5px;
            }
            
            .footer strong {
                color: white;
            }
            
            .print-btn { 
                position: fixed; 
                top: 20px; 
                right: 20px; 
                background: #3182ce;
                color: white; 
                border: none; 
                padding: 12px 20px; 
                border-radius: 6px; 
                cursor: pointer; 
                font-size: 14px;
                font-weight: 500;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(49, 130, 206, 0.3);
                transition: all 0.2s ease;
            }
            
            .print-btn:hover { 
                background: #2c5aa0;
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(49, 130, 206, 0.4);
            }
            
            .back-btn {
                position: fixed;
                top: 20px;
                left: 20px;
                background: #718096;
                color: white;
                border: none;
                padding: 12px 20px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(113, 128, 150, 0.3);
                transition: all 0.2s ease;
            }
            
            .back-btn:hover {
                background: #4a5568;
                transform: translateY(-1px);
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                .invoice-container {
                    box-shadow: none;
                    border-radius: 0;
                }
                .print-btn, .back-btn { display: none; }
                .header {
                    background: #1a365d !important;
                    -webkit-print-color-adjust: exact;
                }
            }
            
            @media (max-width: 768px) {
                body {
                    padding: 10px;
                }
                .content {
                    padding: 30px 20px;
                }
                .header {
                    padding: 30px 20px;
                }
                .header-top {
                    flex-direction: column;
                    gap: 20px;
                }
                .header-bottom {
                    grid-template-columns: 1fr;
                    gap: 20px;
                }
                .transactions-table {
                    font-size: 11px;
                }
                .transactions-table th,
                .transactions-table td {
                    padding: 10px 6px;
                }
                .transactions-table th:nth-child(2),
                .transactions-table th:nth-child(3),
                .transactions-table td:nth-child(2),
                .transactions-table td:nth-child(3) {
                    text-align: right;
                }
                .legend-items {
                    flex-direction: column;
                    gap: 10px;
                }
            }
        </style>
    </head>
    <body>
        <button class="back-btn" onclick="window.close()">← Back</button>
        <button class="print-btn" onclick="window.print()">🖨️ Print/Save as PDF</button>
        
        <div class="invoice-container">
            <div class="header">
                <div class="header-top">
                    <div class="logo-section">
                        <h1>BC Management</h1>
                        <p>Transaction Invoice</p>
                    </div>
                    <div class="invoice-meta">
                        <div class="invoice-number">' . $invoiceNumber . '</div>
                        <div class="invoice-date">' . $currentDate . '</div>
                    </div>
                </div>
                
                <div class="header-bottom">
                    <div class="company-info">
                        <h3>Group Information</h3>
                        <div class="info-line">
                            <span class="info-label">Group Name:</span>
                            <span class="info-value">' . htmlspecialchars($group['group_name']) . '</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Total Members:</span>
                            <span class="info-value">' . $group['total_members'] . '</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Monthly Contribution:</span>
                            <span class="info-value">' . formatCurrency($group['monthly_contribution']) . '</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Total Collection:</span>
                            <span class="info-value">' . formatCurrency($group['total_monthly_collection']) . '</span>
                        </div>
                    </div>
                    
                    <div class="client-info">
                        <h3>Member Information</h3>
                        <div class="info-line">
                            <span class="info-label">Member Name:</span>
                            <span class="info-value">' . htmlspecialchars($member['member_name']) . '</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Member Number:</span>
                            <span class="info-value">#' . $member['member_number'] . '</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Phone:</span>
                            <span class="info-value">' . htmlspecialchars($member['phone'] ?? 'N/A') . '</span>
                        </div>
                        <div class="info-line">
                            <span class="info-label">Email:</span>
                            <span class="info-value">' . htmlspecialchars($member['email'] ?? 'N/A') . '</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="content">

                <div class="section">
                    <div class="section-title">Transaction Details</div>
                    <div class="legend">
                        <div class="legend-items">
                            <div class="legend-item">
                                <div class="legend-dot" style="background-color: #38a169;"></div>
                                <span>Paid</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-dot" style="background-color: #d69e2e;"></div>
                                <span>Pending/Unpaid</span>
                            </div>
                             
                        </div>
                    </div>
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Payment Amount</th>
                        <th>Payment Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';

    // Add all months (paid and unpaid)
    for ($month = 1; $month <= $group['total_members']; $month++) {
        $payment = null;
        $expectedAmount = $group['monthly_contribution']; // Default monthly contribution
        
        // Find payment for this month
        foreach ($memberPayments as $p) {
            if ($p['month_number'] == $month) {
                $payment = $p;
                break;
            }
        }
        
        // Check if there's a bid for this month to get the correct expected amount
        $bid = array_filter($monthlyBids, function($b) use ($month) {
            return $b['month_number'] == $month;
        });
        $bid = reset($bid);
        
        if ($bid) {
            $expectedAmount = $bid['gain_per_member'];
        }
        
        // Determine status and colors
        if ($payment) {
            $paymentStatus = strtolower($payment['payment_status']);
            switch ($paymentStatus) {
                case 'completed':
                case 'paid':
                    $statusClass = 'paid';
                    $statusColumnClass = 'status-completed';
                    $rowClass = 'row-paid';
                    $statusText = 'Paid';
                    break;
                case 'pending':
                    $statusClass = 'pending';
                    $statusColumnClass = 'status-pending';
                    $rowClass = 'row-pending';
                    $statusText = 'Pending';
                    break;
               
            }
            $dateText = $payment['payment_date'] ? formatDate($payment['payment_date']) : 'Not Set';
        } else {
            $statusClass = 'unpaid';
            $statusColumnClass = 'status-pending';
            $rowClass = 'row-unpaid';
            $statusText = 'Unpaid';
            $dateText = 'Not Paid';
        }
        
        $html .= '
                    <tr class="' . $rowClass . '">
                        <td>Month ' . $month . '</td>
                        <td class="amount ' . $statusClass . '">' . ($payment ? formatCurrency($payment['payment_amount']) : formatCurrency($expectedAmount)) . '</td>
                        <td class="' . $statusClass . '">' . $dateText . '</td>
                        <td class="' . $statusColumnClass . '">' . $statusText . '</td>
                    </tr>';
    }

    $html .= '
                </tbody>
            </table>
        </div>

                <div class="section">
                    <div class="section-title">Financial Summary</div>
                    <div class="summary-section">
                        <div class="summary-row">
                            <span class="summary-label">Total Paid:</span>
                            <span class="summary-value">' . formatCurrency($memberSummary['total_paid'] ?? 0) . '</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Total Given:</span>
                            <span class="summary-value">' . formatCurrency($memberSummary['given_amount'] ?? 0) . '</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Paid Months:</span>
                            <span class="summary-value">' . count($memberPayments) . ' / ' . $group['total_members'] . '</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Remaining Months:</span>
                            <span class="summary-value">' . ($group['total_members'] - count($memberPayments)) . '</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label summary-total">Net Profit/Loss:</span>
                            <span class="summary-value summary-total ' . (($memberSummary['profit'] ?? 0) >= 0 ? 'positive' : 'negative') . '">' . formatCurrency($memberSummary['profit'] ?? 0) . '</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p><strong>BC Management System</strong></p>
                <p>This invoice was generated on ' . $currentDate . '</p>
                <p>For any queries, please contact the group administrator.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}
?>
