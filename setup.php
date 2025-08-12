<?php
// Simple Setup Script for BC Management System

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    $dbName = $_POST['db_name'] ?? 'bc_simple';
    
    try {
        // Connect to MySQL server
        $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
        $pdo->exec("USE `{$dbName}`");
        
        // Read and execute SQL file
        $sql = file_get_contents('database.sql');
        $sql = str_replace('CREATE DATABASE IF NOT EXISTS bc_simple;', '', $sql);
        $sql = str_replace('USE bc_simple;', '', $sql);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        // Update config file
        $configContent = file_get_contents('config.php');
        $configContent = str_replace("define('DB_HOST', 'localhost');", "define('DB_HOST', '{$dbHost}');", $configContent);
        $configContent = str_replace("define('DB_NAME', 'bc_simple');", "define('DB_NAME', '{$dbName}');", $configContent);
        $configContent = str_replace("define('DB_USER', 'root');", "define('DB_USER', '{$dbUser}');", $configContent);
        $configContent = str_replace("define('DB_PASS', '');", "define('DB_PASS', '{$dbPass}');", $configContent);
        file_put_contents('config.php', $configContent);
        
        $success = 'Database setup completed successfully! You can now login with username: admin, password: admin123';
        
    } catch (Exception $e) {
        $error = 'Setup failed: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - BC Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card setup-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-cogs fa-3x text-primary mb-3"></i>
                            <h3>BC Management System Setup</h3>
                            <p class="text-muted">Configure your database connection</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                                <hr>
                                <a href="login.php" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Go to Login
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Database Host</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" 
                                           value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Database Username</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" 
                                           value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Database Password</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass" 
                                           value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-4">
                                    <label for="db_name" class="form-label">Database Name</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" 
                                           value="<?= htmlspecialchars($_POST['db_name'] ?? 'bc_simple') ?>" required>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-database"></i> Setup Database
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <strong>Requirements:</strong><br>
                                • PHP 7.4+ with PDO MySQL extension<br>
                                • MySQL 5.7+ or MariaDB 10.2+<br>
                                • Web server (Apache/Nginx)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
