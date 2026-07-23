<?php
/**
 * Migration Helper for Converting Admin Pages to Use Header/Footer Components
 * 
 * This script helps identify which admin pages need to be updated to use the new
 * header and footer components.
 */

// Only allow access from command line or admin users
if (php_sapi_name() !== 'cli') {
    require_once '../../config/config.php';
    require_once '../../common/middleware.php';
    checkRole('admin');
}

function scanAdminPages() {
    $adminDir = dirname(__FILE__) . '/..';
    $pages = [];
    
    // Get all PHP files in admin directory
    $files = glob($adminDir . '/*.php');
    
    foreach ($files as $file) {
        $filename = basename($file);
        
        // Skip the header/footer files and this helper
        if (in_array($filename, ['header.php', 'footer.php', 'migration_helper.php'])) {
            continue;
        }
        
        $content = file_get_contents($file);
        
        // Check if file has old structure
        $hasOldStructure = (
            strpos($content, '<!DOCTYPE html') !== false &&
            strpos($content, '<nav class="navbar') !== false &&
            strpos($content, 'includes/header.php') === false
        );
        
        // Check if already migrated
        $isMigrated = (
            strpos($content, 'includes/header.php') !== false &&
            strpos($content, 'includes/footer.php') !== false
        );

        // Check for duplicate Bootstrap JS
        $hasBootstrapJS = strpos($content, 'bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js') !== false;
        $bootstrapJSCount = substr_count($content, 'bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');

        $pages[] = [
            'file' => $filename,
            'path' => $file,
            'needs_migration' => $hasOldStructure,
            'is_migrated' => $isMigrated,
            'has_navbar' => strpos($content, '<nav class="navbar') !== false,
            'has_doctype' => strpos($content, '<!DOCTYPE html') !== false,
            'has_bootstrap_js' => $hasBootstrapJS,
            'bootstrap_js_count' => $bootstrapJSCount,
            'size' => filesize($file)
        ];
    }
    
    return $pages;
}

function generateMigrationReport($pages) {
    $report = "# Admin Pages Migration Report\n\n";
    $report .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
    
    $needsMigration = array_filter($pages, fn($p) => $p['needs_migration']);
    $alreadyMigrated = array_filter($pages, fn($p) => $p['is_migrated']);
    $noStructure = array_filter($pages, fn($p) => !$p['has_doctype'] && !$p['has_navbar']);
    $hasBootstrapConflicts = array_filter($pages, fn($p) => $p['has_bootstrap_js'] && $p['bootstrap_js_count'] > 0);

    $report .= "## Summary\n\n";
    $report .= "- **Total Pages**: " . count($pages) . "\n";
    $report .= "- **Already Migrated**: " . count($alreadyMigrated) . "\n";
    $report .= "- **Needs Migration**: " . count($needsMigration) . "\n";
    $report .= "- **Bootstrap JS Conflicts**: " . count($hasBootstrapConflicts) . "\n";
    $report .= "- **No HTML Structure**: " . count($noStructure) . "\n\n";
    
    if (!empty($alreadyMigrated)) {
        $report .= "## ✅ Already Migrated\n\n";
        foreach ($alreadyMigrated as $page) {
            $report .= "- `{$page['file']}`\n";
        }
        $report .= "\n";
    }
    
    if (!empty($needsMigration)) {
        $report .= "## 🔄 Needs Migration\n\n";
        foreach ($needsMigration as $page) {
            $report .= "- `{$page['file']}` (" . number_format($page['size']/1024, 1) . " KB)\n";
        }
        $report .= "\n";
    }

    if (!empty($hasBootstrapConflicts)) {
        $report .= "## ⚠️ Bootstrap JS Conflicts (URGENT)\n\n";
        $report .= "These pages have Bootstrap JS includes that will conflict with the header/footer:\n\n";
        foreach ($hasBootstrapConflicts as $page) {
            $report .= "- `{$page['file']}` ({$page['bootstrap_js_count']} Bootstrap JS includes)\n";
        }
        $report .= "\n**Action Required:** Remove duplicate Bootstrap JS includes from these pages.\n\n";
    }
    
    if (!empty($noStructure)) {
        $report .= "## ℹ️ No HTML Structure (API/Include files)\n\n";
        foreach ($noStructure as $page) {
            $report .= "- `{$page['file']}`\n";
        }
        $report .= "\n";
    }
    
    $report .= "## Migration Steps\n\n";
    $report .= "For each page that needs migration:\n\n";
    $report .= "1. **Backup the original file**\n";
    $report .= "2. **Add page title variable** (optional):\n";
    $report .= "   ```php\n";
    $report .= "   \$page_title = 'Your Page Title';\n";
    $report .= "   ```\n\n";
    $report .= "3. **Replace header section** with:\n";
    $report .= "   ```php\n";
    $report .= "   require_once 'includes/header.php';\n";
    $report .= "   ```\n\n";
    $report .= "4. **Remove old HTML structure**:\n";
    $report .= "   - Remove `<!DOCTYPE html>` through `<nav>` sections\n";
    $report .= "   - Remove closing `</nav>` through `</html>` sections\n\n";
    $report .= "5. **Add footer include**:\n";
    $report .= "   ```php\n";
    $report .= "   <?php require_once 'includes/footer.php'; ?>\n";
    $report .= "   ```\n\n";
    $report .= "6. **Move page-specific CSS** to `<style>` block after header\n";
    $report .= "7. **Test the page** to ensure it works correctly\n\n";
    
    return $report;
}

// Run the scan
$pages = scanAdminPages();
$report = generateMigrationReport($pages);

// If running from web, display as HTML
if (php_sapi_name() !== 'cli') {
    $page_title = 'Migration Helper';
    require_once 'header.php';
    ?>
    
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Admin Pages Migration Helper</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This tool helps identify which admin pages need to be updated to use the new header/footer components.
                    </div>
                    
                    <pre class="bg-light p-3 rounded" style="white-space: pre-wrap; font-family: monospace; font-size: 0.9rem;"><?= htmlspecialchars($report) ?></pre>
                    
                    <div class="mt-3">
                        <a href="README.md" class="btn btn-outline-primary me-2" target="_blank">
                            <i class="fas fa-book me-2"></i>View Documentation
                        </a>
                        <a href="example_page.php" class="btn btn-outline-success">
                            <i class="fas fa-eye me-2"></i>View Example Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once 'footer.php';
} else {
    // Command line output
    echo $report;
}
?>
