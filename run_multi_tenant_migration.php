<?php
/**
 * Multi-Tenant Migration Runner
 * This script runs the multi-tenant migration and sets up the system
 */

require_once 'config.php';

// Check if we're running from command line or web
$isCommandLine = php_sapi_name() === 'cli';

if (!$isCommandLine) {
    // Web interface
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Multi-Tenant Migration</title>";
    echo "<style>body{font-family:Arial,sans-serif;margin:40px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
    echo "</head><body>";
    echo "<h1>BC Management System - Multi-Tenant Migration</h1>";
}

function logMessage($message, $type = 'info') {
    global $isCommandLine;
    
    if ($isCommandLine) {
        echo "[" . strtoupper($type) . "] " . $message . "\n";
    } else {
        echo "<p class='$type'>" . htmlspecialchars($message) . "</p>";
        flush();
    }
}

function runMigration() {
    try {
        logMessage("Starting multi-tenant migration...", 'info');
        
        // Read the migration SQL file
        $migrationFile = 'multi_tenant_migration.sql';
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration file not found: $migrationFile");
        }
        
        $sql = file_get_contents($migrationFile);
        if ($sql === false) {
            throw new Exception("Could not read migration file");
        }
        
        // Get database connection
        $pdo = getDB();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        logMessage("Connected to database successfully", 'success');
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
            }
        );
        
        logMessage("Found " . count($statements) . " SQL statements to execute", 'info');
        
        // Execute each statement
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($statements as $index => $statement) {
            try {
                // Skip comments and empty statements
                if (empty(trim($statement)) || preg_match('/^\s*--/', $statement)) {
                    continue;
                }
                
                $pdo->exec($statement);
                $successCount++;
                
                // Log progress for major operations
                if (preg_match('/CREATE TABLE.*?(\w+)/i', $statement, $matches)) {
                    logMessage("Created table: " . $matches[1], 'success');
                } elseif (preg_match('/ALTER TABLE.*?(\w+)/i', $statement, $matches)) {
                    logMessage("Modified table: " . $matches[1], 'success');
                } elseif (preg_match('/INSERT INTO.*?(\w+)/i', $statement, $matches)) {
                    logMessage("Inserted data into: " . $matches[1], 'success');
                }
                
            } catch (PDOException $e) {
                $errorCount++;
                
                // Some errors are expected (like table already exists)
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'Duplicate column') !== false ||
                    strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    logMessage("Skipped (already exists): " . substr($statement, 0, 50) . "...", 'info');
                } else {
                    logMessage("Error executing statement: " . $e->getMessage(), 'error');
                    logMessage("Statement: " . substr($statement, 0, 100) . "...", 'error');
                }
            }
        }
        
        logMessage("Migration completed!", 'success');
        logMessage("Successful operations: $successCount", 'success');
        logMessage("Errors/Skipped: $errorCount", 'info');
        
        // Verify the migration
        verifyMigration($pdo);
        
        return true;
        
    } catch (Exception $e) {
        logMessage("Migration failed: " . $e->getMessage(), 'error');
        return false;
    }
}

function verifyMigration($pdo) {
    logMessage("Verifying migration...", 'info');
    
    $tables = [
        'super_admins',
        'clients', 
        'client_admins',
        'audit_log',
        'system_settings'
    ];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            logMessage("Table '$table' exists with $count records", 'success');
        } catch (PDOException $e) {
            logMessage("Table '$table' verification failed: " . $e->getMessage(), 'error');
        }
    }
    
    // Check if client_id columns were added
    $columnsToCheck = [
        'bc_groups' => 'client_id',
        'monthly_bids' => 'client_id',
        'member_payments' => 'client_id',
        'member_summary' => 'client_id'
    ];
    
    foreach ($columnsToCheck as $table => $column) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$column'");
            if ($stmt->fetch()) {
                logMessage("Column '$column' added to table '$table'", 'success');
            } else {
                logMessage("Column '$column' missing from table '$table'", 'error');
            }
        } catch (PDOException $e) {
            logMessage("Could not verify column '$column' in table '$table'", 'error');
        }
    }
    
    logMessage("Migration verification completed", 'info');
}

// Run the migration
$success = runMigration();

if (!$isCommandLine) {
    if ($success) {
        echo "<h2 class='success'>Migration Completed Successfully!</h2>";
        echo "<p>Your BC Management System has been upgraded to support multi-tenant functionality.</p>";
        echo "<h3>Next Steps:</h3>";
        echo "<ul>";
        echo "<li><a href='super_admin_login.php'>Login as Super Admin</a> (username: superadmin, password: superadmin123)</li>";
        echo "<li><a href='client_login.php'>Login as Client Admin</a> (use your existing admin credentials)</li>";
        echo "<li><a href='member_login.php'>Login as Member</a> (existing member credentials still work)</li>";
        echo "</ul>";
        echo "<h3>Default Accounts Created:</h3>";
        echo "<ul>";
        echo "<li><strong>Super Admin:</strong> username: superadmin, password: superadmin123</li>";
        echo "<li><strong>Default Client:</strong> Your existing data has been migrated to 'Default Client'</li>";
        echo "<li><strong>Client Admin:</strong> Your existing admin account now works as a client admin</li>";
        echo "</ul>";
    } else {
        echo "<h2 class='error'>Migration Failed!</h2>";
        echo "<p>Please check the error messages above and try again.</p>";
        echo "<p>You may need to restore your database backup and contact support.</p>";
    }
    
    echo "</body></html>";
} else {
    if ($success) {
        echo "\n=== MIGRATION COMPLETED SUCCESSFULLY ===\n";
        echo "Your BC Management System is now multi-tenant enabled!\n";
        echo "\nDefault login credentials:\n";
        echo "Super Admin: superadmin / superadmin123\n";
        echo "Client Admin: Use your existing admin credentials\n";
    } else {
        echo "\n=== MIGRATION FAILED ===\n";
        echo "Please check the error messages above.\n";
        exit(1);
    }
}
?>
