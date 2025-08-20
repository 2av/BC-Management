<?php
// Simple landing page without complex dependencies
session_start();

// Check if user is already logged in
if (isset($_SESSION['admin_id']) || isset($_SESSION['client_admin_id'])) {
    header('Location: ../admin/index.php');
    exit();
} elseif (isset($_SESSION['member_id'])) {
    header('Location: ../member/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitra Niidhi Samooh - Business Chit Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #10b981;
            --accent-color: #f59e0b;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --bg-light: #f8fafc;
            --border-color: #e5e7eb;
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
        }

        .landing-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .landing-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 1000px;
            width: 100%;
            position: relative;
        }

        .landing-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .landing-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 20%, rgba(255,255,255,0.1) 0%, transparent 50%),
                        radial-gradient(circle at 70% 80%, rgba(255,255,255,0.05) 0%, transparent 50%);
            opacity: 0.8;
        }

        .landing-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .landing-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 1;
            letter-spacing: -0.02em;
        }

        .landing-subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
            margin: 0;
            position: relative;
            z-index: 1;
            font-weight: 400;
        }

        .landing-body {
            padding: 2.5rem 2rem;
        }

        .login-option {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 1.75rem 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }

        .login-option:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-color);
            color: inherit;
            text-decoration: none;
        }

        .login-option.admin {
            border-left: 6px solid var(--primary-color);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .login-option.member {
            border-left: 6px solid var(--secondary-color);
            background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
        }

        .option-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .option-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .option-description {
            color: var(--text-secondary);
            margin: 0;
            font-size: 1rem;
            line-height: 1.5;
            position: relative;
            z-index: 1;
        }

        .legal-disclaimer {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .legal-disclaimer h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .disclaimer-content {
            max-height: 150px;
            overflow-y: auto;
            padding-right: 0.75rem;
            color: var(--text-secondary);
        }

        .system-info {
            background: linear-gradient(135deg, var(--bg-light) 0%, #ffffff 100%);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }

        .animate-fadeIn {
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .landing-header {
                padding: 2rem 1.5rem;
            }

            .landing-body {
                padding: 2rem 1.5rem;
            }

            .landing-title {
                font-size: 2rem;
            }

            .landing-icon {
                font-size: 3rem;
            }

            .option-icon {
                font-size: 2rem;
            }

            .legal-disclaimer {
                padding: 1rem;
                margin: 1.5rem 0;
            }

            .system-info {
                padding: 1rem;
                margin-top: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container landing-container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="landing-card animate-fadeIn">
                    <div class="landing-header">
                        <div class="landing-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h1 class="landing-title">Mitra Niidhi Samooh</h1>
                        <p class="landing-subtitle">Multi-Tenant Business Chit Management Platform</p>
                    </div>

                    <div class="landing-body">
                        <h3 class="text-center mb-4" style="color: var(--text-primary); font-weight: 600; font-size: 1.4rem;">Choose Your Access Portal</h3>
                        
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <a href="login.php" class="login-option admin">
                                    <div class="text-center">
                                        <div class="option-icon text-primary">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div class="option-title">Admin Portal</div>
                                        <p class="option-description">Admin and client access to manage BC groups, members, and payments</p>
                                    </div>
                                </a>
                            </div>
                            
                            <div class="col-md-6">
                                <a href="member_login.php" class="login-option member">
                                    <div class="text-center">
                                        <div class="option-icon text-success">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="option-title">Member Portal</div>
                                        <p class="option-description">Member access to view your BC group details and make payments</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Legal Disclaimer Section -->
                        <div class="legal-disclaimer">
                            <h5>
                                <i class="fas fa-balance-scale"></i>
                                Legal Disclaimer & Terms of Use
                            </h5>
                            <div class="disclaimer-content">
                                <p><strong>Welcome to Mitra Niidhi Samooh</strong>, a Multi-Tenant Business Chit Management Platform. By accessing or using our services, you acknowledge that you have read, understood, and agreed to be bound by these terms.</p>
                                
                                <p><strong>1. Nature of Services:</strong> Mitra Niidhi Samooh ("the Platform") is a software-as-a-service (SaaS) solution that provides tools for digital record-keeping, group management, auction scheduling, reporting, and notifications related to chit operations. The Platform does not operate chit funds, collect deposits, disburse funds, or act as a financial intermediary. We are a technology provider only.</p>
                                
                                <p><strong>2. No Financial Transactions:</strong> No financial transactions take place on this Platform. Payments, collections, subscriptions, or disbursals must occur outside the Platform using the users' own arrangements (such as cash, bank transfer, or UPI between members). The Platform does not provide any payment gateway, wallet, escrow, or fund-holding services.</p>
                                
                                <p><strong>3. User Responsibility & Compliance:</strong> Users are fully responsible for ensuring their activities comply with the Chit Funds Act, 1982, relevant State-specific chit fund laws, and any other applicable financial regulations. Organizers must obtain all licenses, permissions, or registrations as required under law before operating chit schemes.</p>
                                
                                <p><strong>4. Platform Liability:</strong> The Platform is not a party to any chit agreements, contracts, or financial arrangements made between users. We do not guarantee the performance, reliability, or legality of any chit operated by our users. We are not liable for defaults in payments, fraudulent chit operations, misrepresentation by users, financial losses, or regulatory violations by chit operators.</p>
                                
                                <p><strong>5. Use at Your Own Risk:</strong> The Platform is provided on an "as-is" and "as-available" basis. Users acknowledge that they use the Platform at their own risk. We do not guarantee uninterrupted, error-free, or secure access.</p>
                                
                                <p><strong>Summary:</strong> This is only software. No money is handled here. Users are solely responsible for compliance with chit fund laws.</p>
                            </div>
                        </div>

                        <div class="system-info">
                            <div class="mb-3">
                                <i class="fas fa-shield-alt text-primary me-2" style="font-size: 1.5rem;"></i>
                                <strong style="color: var(--primary-color);">Mitra Niidhi Samooh v2.0</strong>
                            </div>
                            <div class="text-muted">
                                Multi-Tenant Business Chit Management System
                                <br>
                                <span class="badge bg-primary me-2">Secure</span>
                                <span class="badge bg-success me-2">Scalable</span>
                                <span class="badge bg-info">Multi-Organization Support</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
